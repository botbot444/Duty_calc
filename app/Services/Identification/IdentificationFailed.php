<?php

namespace App\Services\Identification;

use RuntimeException;

/**
 * The identification service could not be used: not configured, timed out, refused the
 * request, or sent something that is not valid JSON. Callers fall back to plain search.
 */
class IdentificationFailed extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : $reason, 0, $previous);
    }
}
