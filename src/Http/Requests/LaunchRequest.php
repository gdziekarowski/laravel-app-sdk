<?php

namespace Idosell\LaravelAppSdk\Http\Requests;

/**
 * Payload uruchomienia aplikacji w panelu sprzedawcy (POST na URL aplikacji).
 *
 * W odpowiedzi panel oczekuje `{status, redirect, sign}` i przekierowuje sprzedawcę
 * pod wskazany adres.
 */
class LaunchRequest extends WebhookRequest
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
