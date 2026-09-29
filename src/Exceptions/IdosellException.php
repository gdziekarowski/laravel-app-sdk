<?php

namespace Idosell\LaravelAppSdk\Exceptions;

use RuntimeException;

/**
 * Bazowy wyjątek paczki — pozwala złapać jednym `catch` wszystkie błędy integracji.
 */
class IdosellException extends RuntimeException {}
