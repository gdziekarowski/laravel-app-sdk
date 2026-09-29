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
