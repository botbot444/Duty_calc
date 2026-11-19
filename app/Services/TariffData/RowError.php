<?php

namespace App\Services\TariffData;

/**
 * One problem with one CSV row. Row numbers are file line numbers (header = 1).
 */
final class RowError
{
    public function __construct(
        public readonly string $file,
        public readonly int $row,
        public readonly string $message,
    ) {}

    public function __toString(): string
    {
        return "{$this->file} row {$this->row}: {$this->message}";
    }

    public function toArray(): array
    {
        return ['file' => $this->file, 'row' => $this->row, 'message' => $this->message];
    }
}
