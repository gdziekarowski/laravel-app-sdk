<?php

use Idosell\LaravelAppSdk\Events\LicenseDeactivated;
use Idosell\LaravelAppSdk\Events\LicenseDeactivating;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Support\Facades\Event;

it('deaktywuje licencję, zachowując klucz do posprzątania zasobów', function () {
    license(['client_id' => 777001]);

    $this->postIdosellRemoveLicense(['client_id' => 777001])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $license = IdosellLicense::where('client_id', 777001)->first();

    expect($license)->not->toBeNull()
        ->and($license->active)->toBeFalse()
        ->and($license->api_key)->toBe('admin-api-key-0000000000000000');
});

it('daje listenerowi szansę posprzątać, zanim licencja przestanie być aktywna', function () {
    license(['client_id' => 777002]);

    $activeWhileCleaningUp = null;
    $keyWhileCleaningUp = null;

    Event::listen(LicenseDeactivating::class, function (LicenseDeactivating $event) use (&$activeWhileCleaningUp, &$keyWhileCleaningUp): void {
        $fresh = $event->license->fresh();
        $activeWhileCleaningUp = $fresh->active;
        $keyWhileCleaningUp = $fresh->api_key;
    });

    $this->postIdosellRemoveLicense(['client_id' => 777002])->assertJson(['status' => 'ok']);

    expect($activeWhileCleaningUp)->toBeTrue()
        ->and($keyWhileCleaningUp)->toBe('admin-api-key-0000000000000000');
});

it('usuwa licencję w trybie delete', function () {
    config(['idosell.licenses.on_deactivation' => 'delete']);
    license(['client_id' => 777003]);

    Event::fake([LicenseDeactivated::class]);

    $this->postIdosellRemoveLicense(['client_id' => 777003])->assertJson(['status' => 'ok']);

    expect(IdosellLicense::count())->toBe(0);
    Event::assertDispatched(LicenseDeactivated::class, fn (LicenseDeactivated $e): bool => $e->deleted === true);
});

it('potwierdza webhook także dla nieznanej instalacji', function () {
    $this->postIdosellRemoveLicense(['client_id' => 999999])
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

it('nie rusza licencji innej aplikacji tego samego sprzedawcy', function () {
    license(['client_id' => 777004, 'application_id' => 4242]);
    license(['client_id' => 777004, 'application_id' => 9999]);

    $this->postIdosellRemoveLicense(['client_id' => 777004, 'application_id' => 4242]);

    expect(IdosellLicense::where('application_id', 4242)->first()->active)->toBeFalse()
        ->and(IdosellLicense::where('application_id', 9999)->first()->active)->toBeTrue();
});

it('odrzuca deaktywację z nieprawidłowym podpisem', function () {
    license(['client_id' => 777005]);

    $this->postIdosellRemoveLicense(['client_id' => 777005, 'sign' => 'zly-podpis'])
        ->assertJson(['status' => 'error']);

    expect(IdosellLicense::first()->active)->toBeTrue();
});
