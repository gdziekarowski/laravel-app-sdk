<?php

use Idosell\LaravelAppSdk\Events\LicenseActivated;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('odrzuca webhook z nieprawidłowym podpisem i nie tworzy licencji', function () {
    Log::spy();

    $this->postIdosellNewLicense(['sign' => 'zly-podpis'])
        ->assertOk()
        ->assertJson(['status' => 'error']);

    expect(IdosellLicense::count())->toBe(0);
    Log::shouldHaveReceived('warning');
});

it('nie ujawnia ważnego podpisu w odpowiedzi na błędny podpis', function () {
    foreach (['new-license', 'remove-license', 'launch'] as $event) {
        $response = $this->postJson(route('idosell.webhooks.'.$event), [
            'client_id' => 1,
            'application_id' => 4242,
            'sign' => 'zly-podpis',
        ]);

        $response->assertOk()->assertExactJson(['status' => 'error']);
        expect($response->getContent())->not->toContain($this->idosellSign());
    }
});

it('odrzuca podpis przesłany jako tablica', function () {
    $this->postJson(route('idosell.webhooks.new-license'), ['client_id' => 1, 'sign' => ['x']])
        ->assertOk()
        ->assertExactJson(['status' => 'error']);
});

it('odrzuca webhook bez podpisu', function () {
    $this->postJson(route('idosell.webhooks.new-license'), ['client_id' => 1])
        ->assertOk()
        ->assertJson(['status' => 'error']);

    expect(IdosellLicense::count())->toBe(0);
});

it('aktywuje licencję: deszyfruje klucz, zapisuje i finalizuje instalację', function () {
    $this->fakeIdosellApps();

    $response = $this->postIdosellNewLicense([
        'client_id' => 555001,
        'api_key' => $this->idosellEncryptedApiKey('REAL-ADMIN-KEY-987654321'),
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);
    expect($response->json('sign'))->toBe($this->idosellSign());

    $license = IdosellLicense::where('client_id', 555001)->first();

    expect($license)->not->toBeNull()
        ->and($license->api_key)->toBe('REAL-ADMIN-KEY-987654321')
        ->and($license->api_url)->toBe('https://demo-shop.example.com/api')
        ->and($license->active)->toBeTrue()
        ->and($license->installation_confirmed)->toBeTrue()
        ->and($license->shops())->toBe([['id' => 1, 'name' => 'Sklep 1']]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'installation/done'));
});

it('zapisuje sekrety zaszyfrowane w bazie', function () {
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense(['api_key' => $this->idosellEncryptedApiKey('REAL-ADMIN-KEY')]);

    $raw = DB::table(config('idosell.licenses.table'))->first();

    expect($raw->api_key)->not->toContain('REAL-ADMIN-KEY')
        ->and($raw->api_license)->not->toContain('LIC-TEST');
});

it('obsługuje ponowne dostarczenie webhooka bez duplikowania instalacji', function () {
    $this->fakeIdosellApps();
    Event::fake([LicenseActivated::class]);

    $this->postIdosellNewLicense()->assertJson(['status' => 'ok']);
    $this->postIdosellNewLicense([
        'api_url' => 'https://nowy-adres.example.com/api',
        'api_key' => $this->idosellEncryptedApiKey('admin-api-key-1111111111111111'),
    ])->assertJson(['status' => 'ok']);

    expect(IdosellLicense::count())->toBe(1)
        ->and(IdosellLicense::first()->api_url)->toBe('https://nowy-adres.example.com/api');

    Event::assertDispatchedTimes(LicenseActivated::class, 2);
    Event::assertDispatched(LicenseActivated::class, fn (LicenseActivated $e): bool => $e->isNew === true);
    Event::assertDispatched(LicenseActivated::class, fn (LicenseActivated $e): bool => $e->isNew === false);
});

it('nie finalizuje instalacji dla aplikacji typu downloadable', function () {
    config(['idosell.apps.type' => 'downloadable']);
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense(['api_key' => null, 'authorization_type' => null])
        ->assertJson(['status' => 'ok']);

    expect(IdosellLicense::first()->installation_confirmed)->toBeFalse();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'installation/done'));
});

it('zgłasza błąd, gdy finalizacja instalacji się nie powiodła — licencja czeka na ponowienie', function () {
    config(['idosell.apps.retries' => 0]);

    Http::fake([
        '*keyset' => Http::response($this->idosellIv(), 200),
        '*application/license' => $this->idosellLicenseResponse(),
        '*installation/done' => Http::response(['status' => 'error'], 500),
    ]);
    Http::preventStrayRequests();

    $this->postIdosellNewLicense()
        ->assertOk()
        ->assertJson(['status' => 'error']);

    // Dane sprzedawcy są już zapisane — kolejne dostarczenie webhooka dokończy instalację.
    // Do tego czasu licencja jest nieaktywna, więc panel aplikacji się nie otworzy.
    expect(IdosellLicense::count())->toBe(1)
        ->and(IdosellLicense::first()->installation_confirmed)->toBeFalse()
        ->and(IdosellLicense::first()->active)->toBeFalse();
});

it('odrzuca payload bez wymaganych pól', function () {
    $this->postJson(route('idosell.webhooks.new-license'), [
        'client_id' => 555001,
        'sign' => $this->idosellSign(),
    ])->assertOk()->assertJson(['status' => 'error']);
});

it('odrzuca api_url bez https, z adresem IP albo w sieci wewnętrznej', function (string $apiUrl) {
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense(['api_url' => $apiUrl])->assertOk()->assertJson(['status' => 'error']);

    expect(IdosellLicense::count())->toBe(0);
})->with([
    'http' => 'http://demo-shop.example.com/api',
    'adres IP' => 'https://169.254.169.254/latest',
    'IP dziesiętnie' => 'https://2130706433/api',
    'IPv6' => 'https://[::1]/api',
    'localhost' => 'https://localhost/api',
    'domena wewnętrzna' => 'https://admin.internal/api',
]);

it('przyjmuje własną domenę panelu sklepu, gdy sprawdzanie listy jest wyłączone', function () {
    config(['idosell.admin_api.allowed_hosts' => '']);
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense(['api_url' => 'https://panel.sklep-przyklad.example.net/api'])
        ->assertJson(['status' => 'ok']);

    expect(IdosellLicense::first()->api_url)->toBe('https://panel.sklep-przyklad.example.net/api');
});

it('odrzuca host spoza listy, gdy aplikacja zawęziła hosty paneli', function (string $apiUrl) {
    config(['idosell.admin_api.allowed_hosts' => '*.example.com']);
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense(['api_url' => $apiUrl])->assertOk()->assertJson(['status' => 'error']);
})->with([
    'obcy host' => 'https://attacker.example.org/api',
    'host podszywający się pod domenę' => 'https://demo-shop.example.com.attacker.example.org/api',
    'userinfo z dozwolonym hostem' => 'https://demo-shop.example.com@attacker.example.org/api',
]);

it('nie przenosi zapisanego klucza pod nowy api_url bez nowego api_key', function () {
    $this->fakeIdosellApps();
    license(['client_id' => 555001, 'api_key' => 'REAL-ADMIN-KEY']);

    $this->postIdosellNewLicense([
        'api_url' => 'https://inny-panel.example.com/api',
        'api_key' => null,
    ])->assertOk()->assertJson(['status' => 'error']);

    $license = IdosellLicense::first();

    expect($license->api_url)->toBe('https://demo-shop.example.com/api')
        ->and($license->api_key)->toBe('REAL-ADMIN-KEY');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'installation/done'));
});

it('pozwala ponowić webhook bez api_key, gdy panel się nie zmienia', function () {
    $this->fakeIdosellApps();
    license(['client_id' => 555001, 'api_key' => 'REAL-ADMIN-KEY']);

    $this->postIdosellNewLicense(['api_key' => null])->assertJson(['status' => 'ok']);

    expect(IdosellLicense::first()->api_key)->toBe('REAL-ADMIN-KEY');
});

it('nie przywraca deaktywowanej licencji, gdy finalizacja instalacji się nie powiodła', function () {
    config(['idosell.apps.retries' => 0]);
    license(['client_id' => 555001, 'active' => false]);

    Http::fake([
        '*keyset' => Http::response($this->idosellIv(), 200),
        '*application/license' => $this->idosellLicenseResponse(),
        '*installation/done' => Http::response(['status' => 'error'], 500),
    ]);
    Http::preventStrayRequests();

    $this->postIdosellNewLicense()->assertJson(['status' => 'error']);

    expect(IdosellLicense::first()->active)->toBeFalse();
});

it('nie nadpisuje danych istniejącej licencji, gdy finalizacja instalacji się nie powiodła', function () {
    config(['idosell.apps.retries' => 0]);
    license(['client_id' => 555001, 'api_license' => 'LIC-TEST-0000000000000000']);

    Http::fake([
        '*keyset' => Http::response($this->idosellIv(), 200),
        '*application/license' => $this->idosellLicenseResponse(),
        '*installation/done' => Http::response(['status' => 'error'], 500),
    ]);
    Http::preventStrayRequests();

    $this->postIdosellNewLicense([
        'api_license' => 'LIC-FAKE-1111111111111111',
        'api_key' => $this->idosellEncryptedApiKey('ATTACKER-KEY'),
    ])->assertJson(['status' => 'error']);

    $license = IdosellLicense::first();

    expect($license->api_license)->toBe('LIC-TEST-0000000000000000')
        ->and($license->api_key)->toBe('admin-api-key-0000000000000000')
        ->and($license->active)->toBeTrue();
});

it('aktywuje ponownie deaktywowaną licencję po udanej finalizacji', function () {
    $this->fakeIdosellApps();
    license(['client_id' => 555001, 'active' => false]);

    $this->postIdosellNewLicense()->assertJson(['status' => 'ok']);

    expect(IdosellLicense::first()->active)->toBeTrue();
});

it('traktuje pierwszą udaną aktywację po nieudanej finalizacji jako nową instalację', function () {
    config(['idosell.apps.retries' => 0]);
    Event::fake([LicenseActivated::class]);

    Http::fake([
        '*keyset' => Http::response($this->idosellIv(), 200),
        '*application/license' => $this->idosellLicenseResponse(),
        '*installation/done' => Http::sequence()
            ->push(['status' => 'error'], 500)
            ->push(['status' => 'ok'], 200),
    ]);
    Http::preventStrayRequests();

    $this->postIdosellNewLicense()->assertJson(['status' => 'error']);
    $this->postIdosellNewLicense()->assertJson(['status' => 'ok']);

    expect(IdosellLicense::first()->active)->toBeTrue();

    Event::assertDispatchedTimes(LicenseActivated::class, 1);
    Event::assertDispatched(LicenseActivated::class, fn (LicenseActivated $e): bool => $e->isNew === true);
});

it('nie wymaga nowego api_key, gdy api_url różni się tylko jawnym portem 443', function () {
    $this->fakeIdosellApps();
    license(['client_id' => 555001, 'api_key' => 'REAL-ADMIN-KEY']);

    $this->postIdosellNewLicense([
        'api_url' => 'https://demo-shop.example.com:443/api',
        'api_key' => null,
    ])->assertJson(['status' => 'ok']);
});

it('nie przenosi klucza pod nowy api_url przy odtworzeniu webhooka z tym samym api_key', function () {
    $this->fakeIdosellApps();
    license(['client_id' => 555001, 'api_key' => 'REAL-ADMIN-KEY']);

    $this->postIdosellNewLicense([
        'api_url' => 'https://attacker.example.com/api',
        'api_key' => $this->idosellEncryptedApiKey('REAL-ADMIN-KEY'),
    ])->assertJson(['status' => 'error']);

    expect(IdosellLicense::first()->api_url)->toBe('https://demo-shop.example.com/api');
});

it('odrzuca api_license należący do innej instalacji', function () {
    $this->fakeIdosellApps();
    license(['client_id' => 555001, 'api_license' => 'LIC-TEST-0000000000000000']);

    $this->postIdosellNewLicense([
        'client_id' => 555002,
        'api_url' => 'https://attacker.example.com/api',
        'api_license' => 'LIC-TEST-0000000000000000',
    ])->assertJson(['status' => 'error']);

    expect(IdosellLicense::count())->toBe(1);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'installation/done'));
});

it('odrzuca aktywację, gdy Apps API przypisuje licencję innemu sprzedawcy', function () {
    $this->fakeIdosellApps(clientId: 999001);

    $this->postIdosellNewLicense(['client_id' => 555001])->assertJson(['status' => 'error']);

    expect(IdosellLicense::count())->toBe(0);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'installation/done'));
});

it('odrzuca aktywację, gdy Apps API nie zna licencji', function () {
    $this->fakeIdosellApps([
        '*application/license' => Http::response(['status' => 'success', 'license' => []], 200),
    ]);

    $this->postIdosellNewLicense()->assertJson(['status' => 'error']);

    expect(IdosellLicense::count())->toBe(0);
});

it('weryfikuje właściciela licencji także dla aplikacji downloadable', function () {
    config(['idosell.apps.type' => 'downloadable']);
    $this->fakeIdosellApps(clientId: 999001);

    $this->postIdosellNewLicense(['api_key' => null, 'authorization_type' => null])
        ->assertJson(['status' => 'error']);

    expect(IdosellLicense::count())->toBe(0);
});

it('pyta Apps API o licencję z webhooka', function () {
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense()->assertJson(['status' => 'ok']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'application/license')
        && $request->data()['api_license'] === 'LIC-TEST-0000000000000000');
});

it('pomija weryfikację w Apps API, gdy ją wyłączono', function () {
    config(['idosell.apps.verify_license_owner' => false]);
    $this->fakeIdosellApps(clientId: 999001);

    $this->postIdosellNewLicense()->assertJson(['status' => 'ok']);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'application/license'));
});

it('odrzuca domenę spoza listy i loguje host z podpowiedzią', function () {
    config(['idosell.admin_api.allowed_hosts' => '*.idosell.com,*.iai-shop.com']);
    Log::spy();
    $this->fakeIdosellApps();

    $this->postIdosellNewLicense(['api_url' => 'https://panel.sklep-przyklad.example.net/api'])
        ->assertOk()
        ->assertJson(['status' => 'error']);

    expect(IdosellLicense::count())->toBe(0);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'IDOSELL_ADMIN_API_ALLOWED_HOSTS')
        && $context['reason'] === 'not_allowed'
        && $context['host'] === 'panel.sklep-przyklad.example.net');
});

it('odpowiada podpisanym status error przy błędnych danych, także bez nagłówka Accept JSON', function () {
    Log::spy();

    $response = $this->post(route('idosell.webhooks.new-license'), [
        'client_id' => 555001,
        'sign' => $this->idosellSign(),
    ]);

    $response->assertOk()->assertExactJson(['status' => 'error', 'sign' => $this->idosellSign()]);

    expect(IdosellLicense::count())->toBe(0);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'IdoSell webhook: nieprawidłowe dane.'
        && in_array('api_url', $context['fields'], true)
        && $context['client_id'] === 555001);
});
