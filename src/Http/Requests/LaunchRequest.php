<?php

namespace Idosell\LaravelAppSdk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload uruchomienia aplikacji w panelu sprzedawcy (POST na URL aplikacji).
 *
 * W odpowiedzi panel oczekuje `{status, redirect, sign}` i przekierowuje sprzedawcę
 * pod wskazany adres.
 */
class LaunchRequest extends FormRequest
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
