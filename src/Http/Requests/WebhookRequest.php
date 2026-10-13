<?php

namespace Idosell\LaravelAppSdk\Http\Requests;

use Idosell\LaravelAppSdk\Services\SignatureService;
use Idosell\LaravelAppSdk\Support\LogChannel;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Wspólna baza payloadów webhooków IdoSell Apps.
 *
 * Platforma oczekuje HTTP 200 z polem `status` także przy błędzie, więc zamiast domyślnej
 * odpowiedzi walidacji Laravela (422 albo przekierowanie przy żądaniu bez `Accept: application/json`)
 * zwracamy podpisane `{status: "error"}`. Podpis `sign` został już zweryfikowany przez
 * `idosell.verify-sign`, więc odpowiedź może go zawierać.
 */
abstract class WebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * W logu wyłącznie NAZWY błędnych pól — wartości bywają sekretami albo danymi osobowymi.
     */
    protected function failedValidation(Validator $validator): void
    {
        $clientId = $this->input('client_id');

        LogChannel::resolve()->warning('IdoSell webhook: nieprawidłowe dane.', [
            'route' => $this->route()?->getName(),
            'fields' => array_keys($validator->errors()->messages()),
            'client_id' => is_numeric($clientId) ? (int) $clientId : null,
        ]);

        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'sign' => app(SignatureService::class)->make(),
        ]));
    }
}
