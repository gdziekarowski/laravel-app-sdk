<?php

use Idosell\LaravelAppSdk\Exceptions\DisallowedApiUrlException;
use Idosell\LaravelAppSdk\Support\ApiUrlPolicy;

it('bez listy hostów przepuszcza https do dowolnej publicznej domeny', function (string $url) {
    config(['idosell.admin_api.allowed_hosts' => '']);

    expect(ApiUrlPolicy::allowsAdminApi($url))->toBeTrue();
})->with([
    'https://clientxxxx.idosell.com/api',
    'https://xxxx.iai-shop.com',
    'https://panel.sklep-przyklad.example.net:8443/api',
    'https://CLIENTXXXX.IDOSELL.COM/api',
    'https://clientxxxx.idosell.com./api',
    'https://xn--sklep-przykad-0ob.example.pl/api',
]);

it('zawsze odrzuca adresy bez https, IP i sieć wewnętrzną', function (string $url) {
    expect(ApiUrlPolicy::allowsAdminApi($url))->toBeFalse();
})->with([
    'http://clientxxxx.idosell.com/api',
    'ftp://clientxxxx.idosell.com',
    'clientxxxx.idosell.com/api',
    '',
    'https://127.0.0.1/api',
    'https://169.254.169.254/latest',
    'https://2130706433/api',
    'https://0x7f.1/api',
    'https://[::1]/api',
    'https://localhost/api',
    'https://panel.localhost/api',
    'https://admin.internal/api',
    'https://intranet/api',
    'https://router.home.arpa/api',
    'https://db.localdomain/api',
    'https://panel.ｌｏｃａｌｈｏｓｔ/api',
    'https://x.ｉｎｔｅｒｎａｌ/api',
    'https://panel_1.example.com/api',
]);

it('zamienia domenę IDN na punycode przed sprawdzeniem i połączeniem', function () {
    config(['idosell.admin_api.allowed_hosts' => '']);

    expect(ApiUrlPolicy::adminApiOrigin('https://sklep-żółw.example.pl/api'))
        ->toBe('https://xn--sklep-w-q0a52e8r.example.pl');
})->skip(!function_exists('idn_to_ascii'), 'wymaga ext-intl');

it('zwraca origin ze znormalizowanym hostem i portem', function () {
    config(['idosell.admin_api.allowed_hosts' => '*.idosell.com']);

    expect(ApiUrlPolicy::adminApiOrigin('https://Clientxxxx.IdoSell.com.:8443/api/admin'))
        ->toBe('https://clientxxxx.idosell.com:8443');
});

it('z listą hostów przepuszcza tylko pasujące domeny', function (string $url, bool $allowed) {
    config(['idosell.admin_api.allowed_hosts' => '*.idosell.com, *.iai-shop.com']);

    expect(ApiUrlPolicy::allowsAdminApi($url))->toBe($allowed);
})->with([
    ['https://clientxxxx.idosell.com/api', true],
    ['https://xxxx.iai-shop.com', true],
    ['https://idosell.com.attacker.example.org/api', false],
    ['https://example-idosell.com/api', false],
    ['https://clientxxxx.idosell.com@attacker.example.org/api', false],
]);

it('przyjmuje listę hostów jako tablicę', function () {
    config(['idosell.admin_api.allowed_hosts' => ['panel.example.com']]);

    expect(ApiUrlPolicy::allowsAdminApi('https://panel.example.com/api'))->toBeTrue()
        ->and(ApiUrlPolicy::allowsAdminApi('https://inny.example.com/api'))->toBeFalse();
});

it('domyślnie przepuszcza tylko domeny paneli IdoSell', function () {
    // Wartość domyślna z configu paczki (bez nadpisania z TestCase).
    $default = require __DIR__.'/../../config/idosell.php';
    config(['idosell.admin_api.allowed_hosts' => $default['admin_api']['allowed_hosts']]);

    expect(ApiUrlPolicy::allowsAdminApi('https://clientxxxx.idosell.com/api/admin/'))->toBeTrue()
        ->and(ApiUrlPolicy::allowsAdminApi('https://xxxx.iai-shop.com/api'))->toBeTrue()
        ->and(ApiUrlPolicy::allowsAdminApi('https://panel.sklep-przyklad.example.net/api'))->toBeFalse();
});

it('podaje przyczynę i host odrzucenia', function (string $url, string $reason, ?string $host) {
    config(['idosell.admin_api.allowed_hosts' => '*.idosell.com']);

    try {
        ApiUrlPolicy::assertAdminApi($url);
        $this->fail('Oczekiwano DisallowedApiUrlException.');
    } catch (DisallowedApiUrlException $e) {
        expect($e->reason)->toBe($reason)
            ->and($e->host)->toBe($host);
    }
})->with([
    'brak hosta' => ['nie-adres', DisallowedApiUrlException::INVALID, null],
    'http' => ['http://clientxxxx.idosell.com/api', DisallowedApiUrlException::SCHEME, 'clientxxxx.idosell.com'],
    'IP' => ['https://10.0.0.1/api', DisallowedApiUrlException::NOT_PUBLIC, '10.0.0.1'],
    'spoza listy' => ['https://panel.sklep-przyklad.example.net/api', DisallowedApiUrlException::NOT_ALLOWED, 'panel.sklep-przyklad.example.net'],
]);

it('podpowiada w komunikacie, jak dopisać domenę spoza listy', function () {
    config(['idosell.admin_api.allowed_hosts' => '*.idosell.com']);

    expect(fn () => ApiUrlPolicy::assertAdminApi('https://panel.sklep-przyklad.example.net/api'))
        ->toThrow(DisallowedApiUrlException::class, 'IDOSELL_ADMIN_API_ALLOWED_HOSTS');
});
