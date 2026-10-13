<?php

namespace Idosell\LaravelAppSdk\Http\Requests;

use Closure;
use Idosell\LaravelAppSdk\Exceptions\DisallowedApiUrlException;
use Idosell\LaravelAppSdk\Support\ApiUrlPolicy;
use Idosell\LaravelAppSdk\Support\LogChannel;

/**
 * Payload webhooka aktywacji licencji (`url_webhook_new_license`).
 *
 * Autentyczność (`sign`) sprawdza wcześniej middleware `idosell.verify-sign`.
 *
 * `api_key` i `authorization_type` są opcjonalne, bo dostają je wyłącznie aplikacje
 * typu online — payload aplikacji downloadable jest uboższy.
 */
class NewLicenseRequest extends WebhookRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer'],
            'application_id' => ['required', 'integer'],
            'api_url' => ['required', 'url', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    ApiUrlPolicy::assertAdminApi(is_string($value) ? $value : '');
                } catch (DisallowedApiUrlException $e) {
                    // Ostrzeżenie z podpowiedzią: przy `not_allowed` to zwykle prawidłowy panel
                    // z domeną spoza listy — wystarczy dopisać ją do IDOSELL_ADMIN_API_ALLOWED_HOSTS.
                    LogChannel::resolve()->warning('IdoSell new_license: odrzucono `api_url`. '.$e->getMessage(), [
                        'reason' => $e->reason,
                        'host' => $e->host,
                        'client_id' => (int) $this->input('client_id'),
                    ]);

                    $fail($e->getMessage());
                }
            }],
            'api_license' => ['required', 'string'],
            'api_key' => ['nullable', 'string'],
            'authorization_type' => ['nullable', 'string', 'in:key,OAuth'],
            'contact_data' => ['nullable', 'array'],
            'selected_shops' => ['nullable', 'array'],
            'sign' => ['required', 'string'],
        ];
    }
}
