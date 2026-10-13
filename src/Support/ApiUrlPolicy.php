<?php

namespace Idosell\LaravelAppSdk\Support;

use Idosell\LaravelAppSdk\Exceptions\ConfigurationException;
use Idosell\LaravelAppSdk\Exceptions\DisallowedApiUrlException;
use Illuminate\Support\Str;

/**
 * Reguły adresów, pod które SDK wysyła sekrety (klucz Admin API sprzedawcy, `sign`).
 *
 * `api_url` przychodzi w webhooku, więc zanim SDK wyśle pod niego klucz Admin API, sprawdza go
 * (https, publiczna domena, lista dozwolonych hostów) — ochrona przed wyciekiem klucza i SSRF.
 */
class ApiUrlPolicy
{
    /**
     * Sufiksy nazw, które nie mogą być publicznym panelem sklepu (sieć lokalna/wewnętrzna).
     *
     * @var array<int, string>
     */
    private const INTERNAL_SUFFIXES = ['localhost', 'localdomain', 'local', 'internal', 'intranet', 'lan', 'home.arpa', 'corp'];

    /**
     * Czy `api_url` może być adresem Admin API panelu sprzedawcy.
     *
     * Domeny paneli nie da się przewidzieć (sklep może mieć własną), więc zawsze wymagamy:
     * https i publicznej nazwy domenowej — bez adresów IP (także zapisanych dziesiętnie
     * lub szesnastkowo), `localhost` i domen wewnętrznych. Dodatkowo host musi pasować do listy
     * `idosell.admin_api.allowed_hosts` (wzorce `Str::is`, domyślnie `*.idosell.com,*.iai-shop.com`);
     * pusta lista wyłącza to sprawdzenie.
     *
     * Reguła nie rozwiązuje DNS — dodatkową ochronę zapewnia aktywacja dopiero po potwierdzeniu
     * instalacji przez Apps API (zob. Actions\ActivateLicense).
     */
    public static function allowsAdminApi(string $url): bool
    {
        return self::adminApiOrigin($url) !== null;
    }

    /**
     * `https://{host}[:port]` z hostem znormalizowanym do ASCII (punycode) — dokładnie tym,
     * który sprawdziły reguły — albo null, gdy adres ich nie przechodzi.
     */
    public static function adminApiOrigin(string $url): ?string
    {
        try {
            return self::assertAdminApi($url);
        } catch (DisallowedApiUrlException) {
            return null;
        }
    }

    /**
     * Jak `adminApiOrigin()`, ale odrzucenie zgłasza wyjątkiem z przyczyną i hostem —
     * do komunikatów i logów.
     *
     * @throws DisallowedApiUrlException
     */
    public static function assertAdminApi(string $url): string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || empty($parts['host'])) {
            throw DisallowedApiUrlException::make(DisallowedApiUrlException::INVALID, null);
        }

        if (strtolower($parts['scheme'] ?? '') !== 'https') {
            throw DisallowedApiUrlException::make(DisallowedApiUrlException::SCHEME, $parts['host']);
        }

        $host = self::asciiHost($parts['host']);

        if ($host === null || !self::isPublicDomainName($host)) {
            throw DisallowedApiUrlException::make(DisallowedApiUrlException::NOT_PUBLIC, $host ?? $parts['host']);
        }

        if (!self::matchesAllowedHosts($host)) {
            throw DisallowedApiUrlException::make(DisallowedApiUrlException::NOT_ALLOWED, $host);
        }

        return 'https://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * Pochodzenie (`scheme://host[:port]`) adresu — do porównania, czy webhook zmienia panel.
     */
    public static function origin(?string $url): ?string
    {
        $parts = parse_url((string) $url);

        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        // Jawny port domyślny (`:443`) to ten sam panel.
        $port = isset($parts['port']) && !($scheme === 'https' && $parts['port'] === 443) ? ':'.$parts['port'] : '';

        return $scheme.'://'.(self::asciiHost($parts['host']) ?? mb_strtolower($parts['host'])).$port;
    }

    /**
     * Ścieżka wywołania musi być względna: absolutny URL ominąłby `baseUrl()` klienta
     * i wysłał nagłówki autoryzacji pod inny host.
     *
     * @throws ConfigurationException
     */
    public static function assertRelativePath(string $path): void
    {
        if (preg_match('#^([a-z][a-z0-9+.\-]*:|//|\\\\)#i', ltrim($path)) === 1) {
            throw new ConfigurationException(
                'Ścieżka wywołania API musi być względna (np. `/admin/v8/...`), otrzymano absolutny adres.'
            );
        }
    }

    /**
     * Host w ASCII małymi literami. Nazwy z Unicode (IDN) zamieniamy na punycode wg UTS#46 —
     * tak jak zrobi to curl — żeby np. `ｌｏｃａｌｈｏｓｔ` (pełna szerokość) nie ominął
     * listy domen wewnętrznych. Bez `idn_to_ascii` i `Normalizer` (`ext-intl` albo polyfille
     * Symfony) hosty spoza ASCII odrzucamy.
     */
    private static function asciiHost(string $host): ?string
    {
        $host = rtrim($host, '.');

        if (preg_match('/^[\x21-\x7e]+\z/', $host) !== 1) {
            if (!function_exists('idn_to_ascii') || !class_exists(\Normalizer::class)) {
                return null;
            }

            // NFKC jawnie, przed IDNA: starsze polyfille `idn_to_ascii` nie mapują znaków
            // zgodności (np. pełnej szerokości), a curl z libidn2 by je zmapował.
            $host = \Normalizer::normalize($host, \Normalizer::FORM_KC);

            if ($host === false) {
                return null;
            }

            $host = mb_strtolower($host);

            if (preg_match('/^[\x21-\x7e]+\z/', $host) !== 1) {
                $host = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_USE_STD3_RULES, INTL_IDNA_VARIANT_UTS46);

                if ($host === false) {
                    return null;
                }
            }
        }

        return strtolower(rtrim($host, '.'));
    }

    /**
     * Nazwa domenowa ASCII (LDH) z co najmniej jedną kropką i domeną najwyższego poziomu
     * zaczynającą się literą. Odrzuca IP w każdym zapisie (`127.0.0.1`, `2130706433`,
     * `0x7f.1`, `[::1]`) oraz nazwy sieci wewnętrznych.
     */
    private static function isPublicDomainName(string $host): bool
    {
        $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';

        if (strlen($host) > 253 || preg_match('/^(?:'.$label.'\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\z/', $host) !== 1) {
            return false;
        }

        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pusta lista = dowolna publiczna domena. Wzorce porównujemy z hostem w ASCII,
     * więc domeny IDN wpisuj w configu jako punycode.
     */
    private static function matchesAllowedHosts(string $host): bool
    {
        $allowed = self::allowedHosts();

        if ($allowed === []) {
            return true;
        }

        foreach ($allowed as $pattern) {
            if (Str::is(strtolower($pattern), $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function allowedHosts(): array
    {
        $hosts = config('idosell.admin_api.allowed_hosts', []);

        if (is_string($hosts)) {
            $hosts = explode(',', $hosts);
        }

        return array_values(array_filter(array_map('trim', (array) $hosts)));
    }
}
