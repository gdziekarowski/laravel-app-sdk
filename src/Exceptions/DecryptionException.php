<?php

namespace Idosell\LaravelAppSdk\Exceptions;

/**
 * Nie udało się zdeszyfrować `api_key` z webhooka aktywacji licencji.
 *
 * Najczęstsze przyczyny: niezgodny `application_key`, nieaktualny IV z `keyset`
 * albo payload z innego środowiska niż skonfigurowane.
 */
class DecryptionException extends IdosellException {}
