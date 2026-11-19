<?php

namespace App\Services\Identification;

use RuntimeException;

/** The service answered, but not in the agreed shape. Callers fall back to plain search. */
class InvalidAiResponse extends RuntimeException {}
