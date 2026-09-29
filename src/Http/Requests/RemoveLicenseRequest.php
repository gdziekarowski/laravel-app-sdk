<?php

namespace Idosell\LaravelAppSdk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload webhooka deaktywacji licencji (`url_webhook_remove_license`).
 *
 * Do odnalezienia instalacji wystarczą `client_id` i `application_id`; pozostałe pola
 * bywają pomijane i nie mogą decydować o powodzeniu webhooka.
 */
class RemoveLicenseRequest extends FormRequest
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
            'api_url' => ['nullable', 'url'],
            'api_license' => ['nullable', 'string'],
            'sign' => ['required', 'string'],
        ];
    }
}
