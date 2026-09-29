<?php

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Exceptions\DecryptionException;
use Idosell\LaravelAppSdk\Services\ApiKeyDecryptor;
use Illuminate\Support\Facades\Http;

it('deszyfruje api_key pobierając IV z keyset', function () {
    $plain = 'SECRET-ADMIN-KEY-12345';

    Http::fake(['*keyset' => Http::response($this->idosellIv(), 200)]);
    Http::preventStrayRequests();

    expect((new ApiKeyDecryptor())->decrypt($this->idosellEncryptedApiKey($plain)))->toBe($plain);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'keyset'));
});

it('rzuca wyjątek, gdy deszyfracja się nie powiedzie', function () {
    Http::fake(['*keyset' => Http::response($this->idosellIv(), 200)]);
    Http::preventStrayRequests();

    expect(fn () => (new ApiKeyDecryptor())->decrypt('@@niepoprawny-szyfrogram@@'))
        ->toThrow(DecryptionException::class);
});

/**
 * Test dokumentujący powód, dla którego IV NIE jest cache'owany.
 *
 * W trybie CBC nieprawidłowy IV nie wywraca deszyfracji — psuje wyłącznie pierwszy blok
 * (16 bajtów) tekstu jawnego. Nieaktualny IV z cache dałby więc „klucz API", który wygląda
 * poprawnie i bez szemrania trafiłby do bazy, a błąd wyszedłby dopiero przy pierwszym
 * wywołaniu Admin API. Dlatego IV pobieramy zawsze na świeżo.
 */
it('przy nieprawidłowym IV zwraca uszkodzony klucz zamiast błędu — dlatego IV nie jest cache’owany', function () {
    $plain = 'SECRET-ADMIN-KEY-12345';
    $wire = $this->idosellEncryptedApiKey($plain);

    Http::fake(['*keyset' => Http::response(str_repeat('z', 16), 200)]);
    Http::preventStrayRequests();

    $result = (new ApiKeyDecryptor())->decrypt($wire);

    expect($result)->not->toBe($plain)
        // Uszkodzony jest tylko pierwszy blok — końcówka odszyfrowała się poprawnie.
        ->and(substr($result, 16))->toBe(substr($plain, 16));
});

it('pobiera IV przy każdej deszyfracji', function () {
    Http::fake(['*keyset' => Http::response($this->idosellIv(), 200)]);
    Http::preventStrayRequests();

    $decryptor = new ApiKeyDecryptor();
    $decryptor->decrypt($this->idosellEncryptedApiKey('SECRET'));
    $decryptor->decrypt($this->idosellEncryptedApiKey('SECRET'));

    Http::assertSentCount(2);
});

it('zgłasza błąd API, gdy keyset jest niedostępny', function () {
    Http::fake(['*keyset' => Http::response('service unavailable', 503)]);
    Http::preventStrayRequests();

    config(['idosell.apps.retries' => 1]);

    expect(fn () => (new ApiKeyDecryptor())->decrypt('cokolwiek'))
        ->toThrow(ApiException::class);
});
