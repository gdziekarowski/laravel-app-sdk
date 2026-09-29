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
    $this->postIdosellNewLicense(['api_url' => 'https://nowy-adres.example.com/api'])
        ->assertJson(['status' => 'ok']);

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
        '*installation/done' => Http::response(['status' => 'error'], 500),
    ]);
    Http::preventStrayRequests();

    $this->postIdosellNewLicense()
        ->assertOk()
        ->assertJson(['status' => 'error']);

    // Dane sprzedawcy są już zapisane — kolejne dostarczenie webhooka dokończy instalację.
    expect(IdosellLicense::count())->toBe(1)
        ->and(IdosellLicense::first()->installation_confirmed)->toBeFalse();
});

it('odrzuca payload bez wymaganych pól', function () {
    $this->postJson(route('idosell.webhooks.new-license'), [
        'client_id' => 555001,
        'sign' => $this->idosellSign(),
    ])->assertStatus(422);
});
