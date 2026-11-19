<?php

namespace App\Services\TariffData;

use RuntimeException;

/**
 * Thrown when an import is rejected. Nothing was written: the import runs in one transaction.
 */
class TariffImportException extends RuntimeException
{
    /** @param list<RowError> $errors */
    public function __construct(public readonly array $errors)
    {
        $lines = array_map('strval', array_slice($errors, 0, 20));
        $more = count($errors) > 20 ? "\n... and ".(count($errors) - 20).' more' : '';

        parent::__construct("Tariff import rejected:\n".implode("\n", $lines).$more);
    }

    public static function single(string $file, int $row, string $message): self
    {
        return new self([new RowError($file, $row, $message)]);
    }
}
