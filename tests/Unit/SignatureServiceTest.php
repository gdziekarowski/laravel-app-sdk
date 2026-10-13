<?php

use Idosell\LaravelAppSdk\Exceptions\ConfigurationException;
use Idosell\LaravelAppSdk\Services\SignatureService;
use Illuminate\Support\Facades\Date;

afterEach(function () {
    Date::setTestNow();
});

it('buduje podpis jako sha256(developer|data|klucz)', function () {
    $expected = hash('sha256', 'dev-login|2026-01-02|'.str_repeat('K', 32));

    expect((new SignatureService())->make('2026-01-02'))->toBe($expected);
});

it('akceptuje podpis z dzisiejszą datą', function () {
    Date::setTestNow('2026-01-02 12:00:00');
    $service = new SignatureService();

    expect($service->verify($service->make()))->toBeTrue();
});

it('akceptuje podpis w granicach tolerancji daty (±1 dzień)', function () {
    Date::setTestNow('2026-01-02 00:30:00');
    $service = new SignatureService();

    expect($service->verify($service->make('2026-01-01')))->toBeTrue()
        ->and($service->verify($service->make('2026-01-03')))->toBeTrue();
});

it('odrzuca podpis poza tolerancją daty', function () {
    Date::setTestNow('2026-01-02 12:00:00');

    expect((new SignatureService())->verify((new SignatureService())->make('2025-12-30')))->toBeFalse();
});

it('odrzuca pusty i nieprawidłowy podpis', function () {
    $service = new SignatureService();

    expect($service->verify(''))->toBeFalse()
        ->and($service->verify('to-nie-jest-poprawny-podpis'))->toBeFalse();
});

it('zawęża tolerancję do zera, gdy tak skonfigurowano', function () {
    Date::setTestNow('2026-01-02 12:00:00');
    config(['idosell.apps.sign_date_tolerance_days' => 0]);

    $service = new SignatureService();

    expect($service->verify($service->make('2026-01-01')))->toBeFalse()
        ->and($service->verify($service->make('2026-01-02')))->toBeTrue();
});

it('pozwala podać dane aplikacji wprost, z pominięciem configu', function () {
    $service = new SignatureService('inny-dev', str_repeat('X', 32));

    expect($service->make('2026-01-02'))
        ->toBe(hash('sha256', 'inny-dev|2026-01-02|'.str_repeat('X', 32)));
});

it('rzuca czytelny wyjątek zamiast podpisywać pustym kluczem', function () {
    config(['idosell.apps.application_key' => '']);

    expect(fn () => (new SignatureService())->make())
        ->toThrow(ConfigurationException::class, 'idosell.apps.application_key');
});
