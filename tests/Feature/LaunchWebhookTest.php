<?php

use Idosell\LaravelAppSdk\Events\AppLaunched;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

it('zwraca podpisany URL przekierowania do panelu aplikacji', function () {
    $response = $this->postIdosellLaunch(['client_id' => 42]);

    $response->assertOk()->assertJson(['status' => 'ok']);
    expect($response->json('sign'))->toBe($this->idosellSign());

    $redirect = $response->json('redirect');

    expect($redirect)->toContain('/panel')
        ->and($redirect)->toContain('client=42')
        ->and($redirect)->toContain('signature=');

    // Podpis URL to jedyna identyfikacja sprzedawcy przy wejściu z panelu sklepu.
    expect(URL::hasValidSignature(Request::create($redirect)))->toBeTrue();
});

it('generuje adres, którym da się wejść do panelu aplikacji', function () {
    $redirect = $this->postIdosellLaunch()->json('redirect');

    $this->get($redirect)->assertOk()->assertSee('panel aplikacji');
});

it('odrzuca wejście do panelu bez ważnego podpisu URL', function () {
    $this->get(route('app.panel', ['client' => 42]))->assertForbidden();
});

it('wygasza adres po czasie z konfiguracji', function () {
    config(['idosell.launch.ttl' => 30]);

    $redirect = $this->postIdosellLaunch()->json('redirect');

    $this->travel(31)->minutes();

    $this->get($redirect)->assertForbidden();
});

it('pozwala podmienić sposób budowania adresu', function () {
    Idosell::resolveLaunchUrlUsing(fn (array $payload): string => 'https://moja-aplikacja.example.com/wejscie/'.$payload['client_id']);

    $this->postIdosellLaunch(['client_id' => 4242])
        ->assertJson(['redirect' => 'https://moja-aplikacja.example.com/wejscie/4242']);
});

it('używa stałego adresu, gdy nie wskazano trasy', function () {
    config([
        'idosell.launch.route' => null,
        'idosell.launch.url' => 'https://moja-aplikacja.example.com/start',
    ]);

    $this->postIdosellLaunch()->assertJson(['redirect' => 'https://moja-aplikacja.example.com/start']);
});

it('zwraca błąd, gdy nie skonfigurowano dokąd przekierować', function () {
    config(['idosell.launch.route' => null, 'idosell.launch.url' => null]);

    $this->postIdosellLaunch()
        ->assertOk()
        ->assertJson(['status' => 'error'])
        ->assertJsonMissing(['redirect']);
});

it('emituje zdarzenie uruchomienia aplikacji', function () {
    Event::fake([AppLaunched::class]);

    $this->postIdosellLaunch(['client_id' => 123]);

    Event::assertDispatched(AppLaunched::class, fn (AppLaunched $e): bool => $e->payload['client_id'] === 123
        && str_contains($e->redirect, '/panel'));
});

it('odrzuca uruchomienie z nieprawidłowym podpisem', function () {
    $this->postIdosellLaunch(['sign' => 'zly-podpis'])->assertJson(['status' => 'error']);
});
