<?php

use Idosell\LaravelAppSdk\Support\Redactor;

it('maskuje sekrety, zostawiając informację o długości', function () {
    $redacted = Redactor::redact([
        'client_id' => 555001,
        'api_key' => 'bardzo-tajny-klucz',
    ]);

    expect($redacted['client_id'])->toBe(555001)
        ->and($redacted['api_key'])->toBe('[redacted len=18]');
});

it('maskuje dane osobowe także w zagnieżdżonych strukturach', function () {
    $redacted = Redactor::redact([
        'contact_data' => ['name' => 'Jan Kowalski', 'email' => 'jan.kowalski@example.com'],
        'selected_shops' => [['id' => 1, 'name' => 'Sklep 1']],
    ]);

    // `contact_data` jest na liście kluczy wrażliwych — maskujemy całą gałąź.
    expect($redacted['contact_data'])->toBe('[redacted]')
        ->and($redacted['selected_shops'])->toBe([['id' => 1, 'name' => 'Sklep 1']]);
});

it('maskuje wrażliwe pola zagnieżdżone w tablicach technicznych', function () {
    $redacted = Redactor::redact([
        'shops' => [
            ['id' => 1, 'token' => 'abcd'],
        ],
    ]);

    expect($redacted['shops'][0]['id'])->toBe(1)
        ->and($redacted['shops'][0]['token'])->toBe('[redacted len=4]');
});

it('respektuje własną listę kluczy', function () {
    $redacted = Redactor::redact(['custom' => 'wartosc', 'api_key' => 'x'], ['custom']);

    expect($redacted['custom'])->toBe('[redacted len=7]')
        ->and($redacted['api_key'])->toBe('x');
});

it('maskuje klucze zawierające wrażliwy fragment nazwy', function (string $key) {
    $redacted = Redactor::redact([$key => 'sekret-1234', 'client_id' => 1]);

    expect($redacted[$key])->toBe('[redacted len=11]')
        ->and($redacted['client_id'])->toBe(1);
})->with([
    'access_token', 'refreshToken', 'client_secret', 'X-Api-Key', 'private_key', 'db_password',
    'signature', 'aws_credentials',
]);

it('nie maskuje pól technicznych potrzebnych do diagnozy', function () {
    $data = [
        'client_id' => 555001,
        'application_id' => 4242,
        'api_url' => 'https://clientxxxx.idosell.com/api/admin/',
        'authorization_type' => 'key',
        'selected_shops' => [['id' => 1, 'name' => 'Sklep 1']],
    ];

    expect(Redactor::redact($data))->toBe($data);
});

it('maskuje fragmenty także w zagnieżdżonych strukturach', function () {
    $redacted = Redactor::redact(['oauth' => ['access_token' => 'abc', 'expires_in' => 3600]]);

    expect($redacted['oauth']['access_token'])->toBe('[redacted len=3]')
        ->and($redacted['oauth']['expires_in'])->toBe(3600);
});
