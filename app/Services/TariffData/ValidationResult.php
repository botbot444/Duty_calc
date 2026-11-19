<?php

namespace App\Services\TariffData;

/**
 * Outcome of validating one file: the errors found, and the rows that passed,
 * already turned into attributes ready to insert (sources and lines resolved to IDs).
 */
final class ValidationResult
{
    /**
     * @param  list<RowError>  $errors
     * @param  list<array<string, mixed>>  $records
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $records,
    ) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }
}
