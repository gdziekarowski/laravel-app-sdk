<?php

namespace Idosell\LaravelAppSdk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload webhooka aktywacji licencji (`url_webhook_new_license`).
 *
 * Autentyczność (`sign`) sprawdza wcześniej middleware `idosell.verify-sign`.
 *
 * `api_key` i `authorization_type` są opcjonalne, bo dostają je wyłącznie aplikacje
 * typu online — payload aplikacji downloadable jest uboższy.
 */
class NewLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer'],
            'application_id' => ['required', 'integer'],
            'api_url' => ['required', 'url'],
            'api_license' => ['required', 'string'],
            'api_key' => ['nullable', 'string'],
            'authorization_type' => ['nullable', 'string', 'in:key,OAuth'],
            'contact_data' => ['nullable', 'array'],
            'selected_shops' => ['nullable', 'array'],
            'sign' => ['required', 'string'],
        ];
    }
}
