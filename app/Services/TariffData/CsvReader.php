<?php

namespace App\Services\TariffData;

/**
 * Reads a CSV file with a header row into rows keyed by column name.
 * Values are trimmed and blank cells become null. Columns must match exactly (any order),
 * so a typo in a header is caught instead of silently importing nulls.
 */
final class CsvReader
{
    /**
     * @param  list<string>  $columns
     * @return list<array{row: int, data: array<string, ?string>}>
     */
    public function read(string $path, array $columns): array
    {
        $file = basename($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw TariffImportException::single($file, 0, 'cannot open file');
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if ($header === false || $header === [null]) {
                throw TariffImportException::single($file, 1, 'file is empty');
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]); // UTF-8 BOM from Excel
            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

            $missing = array_diff($columns, $header);
            $unknown = array_diff($header, $columns);
            if ($missing || $unknown) {
                $parts = array_filter([
                    $missing ? 'missing columns: '.implode(', ', $missing) : null,
                    $unknown ? 'unknown columns: '.implode(', ', $unknown) : null,
                ]);
                throw TariffImportException::single($file, 1, implode('; ', $parts));
            }

            $rows = [];
            $line = 1;
            while (($cells = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if ($cells === [null]) {
                    continue; // blank line
                }
                if (count($cells) !== count($header)) {
                    throw TariffImportException::single($file, $line, 'expected '.count($header).' columns, found '.count($cells));
                }
                $data = [];
                foreach ($header as $i => $name) {
                    $value = trim((string) $cells[$i]);
                    $data[$name] = $value === '' ? null : $value;
                }
                $rows[] = ['row' => $line, 'data' => $data];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
