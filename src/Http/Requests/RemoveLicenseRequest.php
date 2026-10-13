<?php

namespace Idosell\LaravelAppSdk\Http\Requests;

/**
 * Payload webhooka deaktywacji licencji (`url_webhook_remove_license`).
 *
 * Instalację identyfikują `client_id` i `application_id`; `api_license` jest wymagany i musi
 * zgadzać się z zapisaną licencją.
 */
class RemoveLicenseRequest extends WebhookRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer'],
            'application_id' => ['required', 'integer'],
            'api_url' => ['nullable', 'url'],
            'api_license' => ['required', 'string'],
            'sign' => ['required', 'string'],
        ];
    }
}
