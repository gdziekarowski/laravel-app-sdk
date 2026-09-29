<?php

use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Support\Facades\DB;

it('szyfruje sekrety w bazie, udostępniając je jawnie przez model', function () {
    $license = license(['api_key' => 'tajny-klucz-admin', 'api_license' => 'LIC-TAJNA']);

    $raw = DB::table(config('idosell.licenses.table'))->where('id', $license->id)->first();

    expect($raw->api_key)->not->toBe('tajny-klucz-admin')
        ->and($license->fresh()->api_key)->toBe('tajny-klucz-admin')
        ->and($license->fresh()->api_license)->toBe('LIC-TAJNA');
});

it('nie ujawnia sekretów przy serializacji do tablicy/JSON', function () {
    $license = license();

    expect($license->toArray())->not->toHaveKey('api_key')
        ->and($license->toArray())->not->toHaveKey('api_license')
        ->and($license->toArray())->toHaveKey('client_id');
});

it('wyciąga domenę panelu z api_url', function () {
    expect(license(['api_url' => 'https://example-shop.iai-shop.com/api'])->domain())
        ->toBe('example-shop.iai-shop.com');
});

it('normalizuje listę sklepów wybranych przy instalacji', function () {
    $license = license(['meta' => ['selected_shops' => [
        ['id' => '3', 'name' => 'Sklep PL'],
        ['id' => 4, 'name' => 'Sklep EN'],
        'śmieć',
    ]]]);

    expect($license->shops())->toBe([
        ['id' => 3, 'name' => 'Sklep PL'],
        ['id' => 4, 'name' => 'Sklep EN'],
    ]);
});

it('zwraca pustą listę sklepów, gdy webhook ich nie przysłał', function () {
    expect(license(['meta' => null])->shops())->toBe([]);
});

it('filtruje licencje zakresami', function () {
    license(['client_id' => 1001]);
    license(['client_id' => 1002, 'active' => false]);
    license(['client_id' => 1003, 'application_id' => 9999]);

    expect(IdosellLicense::query()->active()->count())->toBe(2)
        ->and(IdosellLicense::query()->forClient(1001)->count())->toBe(1)
        // Bez argumentu bierze aplikację z configu.
        ->and(IdosellLicense::query()->forApplication()->count())->toBe(2);
});

it('wyszukuje instalację przez fasadę', function () {
    license(['client_id' => 2001]);

    expect(Idosell::license(2001)?->client_id)->toBe(2001)
        ->and(Idosell::license(9999))->toBeNull();
});

it('pilnuje jednej licencji na parę sprzedawca + aplikacja', function () {
    license(['client_id' => 3001]);

    expect(fn () => license(['client_id' => 3001]))->toThrow(Illuminate\Database\QueryException::class);
});
