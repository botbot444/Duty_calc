<?php

namespace App\Services\TariffData;

use App\Enums\CombineMode;
use App\Enums\Language;
use App\Enums\RuleMethod;
use App\Enums\StatUnit;
use App\Enums\TaxType;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Checks reference-data rows before they are written. Shared by the seeders, the
 * admin CSV import and (later) the admin forms, so every path enforces the same rules:
 *
 * - every tariff line and rule names an existing source and a page number
 * - HS codes are 8 digits; enum columns hold allowed values only
 * - decimals are plain decimal strings that fit their column (never floats)
 * - base_components lists only customs_value and tax types with a lower sequence
 * - date ranges for the same key never overlap, against the database and the file itself
 *
 * Each method takes rows as read by CsvReader and returns a ValidationResult whose
 * records are ready to insert. Rows refer to earlier files through the database, so the
 * importer must write each file before validating the next.
 */
class TariffDataValidator
{
    private const DATE = 'date_format:Y-m-d';

    private const BOOLEAN_WORDS = ['1' => true, '0' => false, 'true' => true, 'false' => false, 'yes' => true, 'no' => false];

    /** @var array<string, list<int>> source name => ids */
    private array $sourceIdsByName = [];

    /** @param list<array{row: int, data: array<string, ?string>}> $rows */
    public function sources(array $rows, string $file = 'tariff_sources.csv'): ValidationResult
    {
        $errors = [];
        $records = [];
        $seen = [];

        foreach ($rows as ['row' => $row, 'data' => $d]) {
            $rowErrors = $this->fieldErrors($d, [
                'name' => ['required', 'string', 'max:255'],
                'document_url' => ['nullable', 'url', 'max:500'],
                'edition' => ['nullable', 'string', 'max:100'],
                'published_on' => ['nullable', self::DATE],
                'retrieved_on' => ['nullable', self::DATE],
                'verified_by' => ['nullable', 'string', 'max:255'],
            ], $file, $row);

            if (! $rowErrors) {
                $key = mb_strtolower($d['name']).'|'.mb_strtolower((string) $d['edition']);
                $exists = DB::table('tariff_sources')->where('name', $d['name'])
                    ->where(fn ($q) => $d['edition'] === null ? $q->whereNull('edition') : $q->where('edition', $d['edition']))
                    ->exists();
                if ($exists || isset($seen[$key])) {
                    $rowErrors[] = $this->error($file, $row, "source \"{$d['name']}\" with this edition already exists");
                }
                $seen[$key] = true;
            }

            $this->collect($errors, $records, $rowErrors, $d);
        }

        return new ValidationResult($errors, $records);
    }

    /** @param list<array{row: int, data: array<string, ?string>}> $rows */
    public function countries(array $rows, string $file = 'countries.csv'): ValidationResult
    {
        $errors = [];
        $records = [];
        $seen = [];

        foreach ($rows as ['row' => $row, 'data' => $d]) {
            $rowErrors = $this->fieldErrors($d, [
                'iso2' => ['required', 'regex:/^[A-Z]{2}$/'],
                'name' => ['required', 'string', 'max:100'],
                'is_origin' => ['required', $this->booleanRule()],
                'is_destination' => ['required', $this->booleanRule()],
            ], $file, $row);

            if (! $rowErrors && (isset($seen[$d['iso2']]) || DB::table('countries')->where('iso2', $d['iso2'])->exists())) {
                $rowErrors[] = $this->error($file, $row, "country {$d['iso2']} already exists");
            }
            $seen[$d['iso2'] ?? ''] = true;

            $this->collect($errors, $records, $rowErrors, $rowErrors ? [] : [
                'iso2' => $d['iso2'],
                'name' => $d['name'],
                'is_origin' => $this->bool($d['is_origin']),
                'is_destination' => $this->bool($d['is_destination']),
            ]);
        }

        return new ValidationResult($errors, $records);
    }

    /** @param list<array{row: int, data: array<string, ?string>}> $rows */
    public function tradeRoutes(array $rows, string $file = 'trade_routes.csv'): ValidationResult
    {
        $errors = [];
        $records = [];
        $seen = [];

        foreach ($rows as ['row' => $row, 'data' => $d]) {
            $rowErrors = $this->fieldErrors($d, [
                'origin_iso2' => ['required', 'regex:/^[A-Z]{2}$/'],
                'destination_iso2' => ['required', 'regex:/^[A-Z]{2}$/', 'different:origin_iso2'],
                'border_post' => ['required', 'string', 'max:100'],
                'supported' => ['required', $this->booleanRule()],
                'notes' => ['nullable', 'string', 'max:2000'],
            ], $file, $row);

            if (! $rowErrors) {
                $origin = DB::table('countries')->where('iso2', $d['origin_iso2'])->first();
                $destination = DB::table('countries')->where('iso2', $d['destination_iso2'])->first();
                if (! $origin || ! $origin->is_origin) {
                    $rowErrors[] = $this->error($file, $row, "origin {$d['origin_iso2']} is not a known origin country");
                }
                if (! $destination || ! $destination->is_destination) {
                    $rowErrors[] = $this->error($file, $row, "destination {$d['destination_iso2']} is not a known destination country");
                }

                $key = "{$d['origin_iso2']}|{$d['destination_iso2']}|".mb_strtolower($d['border_post']);
                $exists = DB::table('trade_routes')->where([
                    'origin_iso2' => $d['origin_iso2'],
                    'destination_iso2' => $d['destination_iso2'],
                    'border_post' => $d['border_post'],
                ])->exists();
                if ($exists || isset($seen[$key])) {
                    $rowErrors[] = $this->error($file, $row, 'this route already exists');
                }
                $seen[$key] = true;
            }

            $this->collect($errors, $records, $rowErrors, $rowErrors ? [] : [
                'origin_iso2' => $d['origin_iso2'],
                'destination_iso2' => $d['destination_iso2'],
                'border_post' => $d['border_post'],
                'supported' => $this->bool($d['supported']),
                'notes' => $d['notes'],
            ]);
        }

        return new ValidationResult($errors, $records);
    }

    /** @param list<array{row: int, data: array<string, ?string>}> $rows */
    public function tariffLines(array $rows, string $file = 'tariff_lines.csv'): ValidationResult
    {
        $errors = [];
        $records = [];
        $accepted = [];   // [hs_code, from, to] of earlier rows in this file

        foreach ($rows as ['row' => $row, 'data' => $d]) {
            $rowErrors = $this->fieldErrors($d, [
                'hs_code' => ['required', 'regex:/^\d{8}$/'],
                'official_description' => ['required', 'string', 'max:2000'],
                'stat_unit' => ['required', Rule::enum(StatUnit::class)],
                'valid_from' => ['required', self::DATE],
                'valid_to' => ['nullable', self::DATE, 'after_or_equal:valid_from'],
                'source_name' => ['required', 'string'],
                'source_page' => ['required', 'integer', 'min:1', 'max:65535'],
            ], $file, $row);

            $sourceId = null;
            if (! $rowErrors) {
                $sourceId = $this->resolveSource($d['source_name'], $file, $row, $rowErrors);

                $clash = DB::table('tariff_lines')->where('hs_code', $d['hs_code'])->get(['valid_from', 'valid_to'])
                    ->contains(fn ($l) => $this->overlaps($d['valid_from'], $d['valid_to'], $l->valid_from, $l->valid_to));
                foreach ($accepted as [$hs, $from, $to]) {
                    $clash = $clash || ($hs === $d['hs_code'] && $this->overlaps($d['valid_from'], $d['valid_to'], $from, $to));
                }
                if ($clash) {
                    $rowErrors[] = $this->error($file, $row, "dates overlap another tariff line for HS {$d['hs_code']}");
                }
            }

            if (! $rowErrors) {
                $accepted[] = [$d['hs_code'], $d['valid_from'], $d['valid_to']];
            }

            $this->collect($errors, $records, $rowErrors, $rowErrors ? [] : [
                'hs_code' => $d['hs_code'],
                'official_description' => $d['official_description'],
                'stat_unit' => $d['stat_unit'],
                'valid_from' => $d['valid_from'],
                'valid_to' => $d['valid_to'],
                'tariff_source_id' => $sourceId,
                'source_page' => (int) $d['source_page'],
            ]);
        }

        return new ValidationResult($errors, $records);
    }

    /** @param list<array{row: int, data: array<string, ?string>}> $rows */
    public function catalogueItems(array $rows, string $file = 'catalogue_items.csv'): ValidationResult
    {
        $errors = [];
        $records = [];
        $seen = [];
        $today = now()->toDateString();

        foreach ($rows as ['row' => $row, 'data' => $d]) {
            $rowErrors = $this->fieldErrors($d, [
                'id' => ['required', 'max:20', 'regex:/^CAT-\d{4,}$/'],
                'hs_code' => ['required', 'regex:/^\d{8}$/'],
                'display_name' => ['required', 'string', 'max:255'],
                'active' => ['required', $this->booleanRule()],
            ], $file, $row);

            $lineId = null;
            if (! $rowErrors) {
                if (isset($seen[$d['id']]) || DB::table('catalogue_items')->where('id', $d['id'])->exists()) {
                    $rowErrors[] = $this->error($file, $row, "catalogue item {$d['id']} already exists");
                }
                $seen[$d['id']] = true;

                // The line in force today, or else the most recent one for this HS code.
                $lineId = DB::table('tariff_lines')->where('hs_code', $d['hs_code'])
                    ->orderByRaw('(valid_from <= ? and (valid_to is null or valid_to >= ?)) desc', [$today, $today])
                    ->orderByDesc('valid_from')
                    ->value('id');
                if ($lineId === null) {
                    $rowErrors[] = $this->error($file, $row, "no tariff line for HS {$d['hs_code']}");
                }
            }

            $this->collect($errors, $records, $rowErrors, $rowErrors ? [] : [
                'id' => $d['id'],
                'tariff_line_id' => $lineId,
                'display_name' => $d['display_name'],
                'active' => $this->bool($d['active']),
            ]);
        }

        return new ValidationResult($errors, $records);
    }

    /** @param list<array{row: int, data: array<string, ?string>}> $rows */
    public function productAliases(array $rows, string $file = 'product_aliases.csv'): ValidationResult
    {
        $errors = [];
        $records = [];
        $seen = [];

        foreach ($rows as ['row' => $row, 'data' => $d]) {
            $rowErrors = $this->fieldErrors($d, [
                'catalogue_item_id' => ['required', 'max:20'],
                'alias' => ['required', 'string', 'max:150'],
                'language' => ['required', Rule::enum(Language::class)],
            ], $file, $row);

            if (! $rowErrors) {
                if (! DB::table('catalogue_items')->where('id', $d['catalogue_item_id'])->exists()) {
                    $rowErrors[] = $this->error($file, $row, "unknown catalogue item {$d['catalogue_item_id']}");
                }
                $key = $d['catalogue_item_id'].'|'.mb_strtolower($d['alias']).'|'.$d['language'];
                $exists = DB::table('product_aliases')->where([
                    'catalogue_item_id' => $d['catalogue_item_id'], 'alias' => $d['alias'], 'language' => $d['language'],
                ])->exists();
                if ($exists || isset($seen[$key])) {
                    $rowErrors[] = $this->error($file, $row, "alias \"{$d['alias']}\" ({$d['language']}) already exists for {$d['catalogue_item_id']}");
                }
                $seen[$key] = true;
            }

            $this->collect($errors, $records, $rowErrors, $rowErrors ? [] : [
                'catalogue_item_id' => $d['catalogue_item_id'],
                'alias' => $d['alias'],
                'language' => $d['language'],
            ]);
        }

        return new ValidationResult($errors, $records);
    }

    /** @param list<array{row: int, data: array<string, ?string>}> $rows */
    public function tariffRules(array $rows, string $file = 'tariff_rules.csv'): ValidationResult
    {
        $errors = [];
        $candidates = [];   // rows that passed the per-row checks, for the cross-row checks

        foreach ($rows as ['row' => $row, 'data' => $d]) {
            $rowErrors = $this->fieldErrors($d, [
                'hs_code' => ['required', 'regex:/^\d{8}$/'],
                'tax_type' => ['required', Rule::enum(TaxType::class)],
                'sequence' => ['required', 'integer', 'min:1', 'max:255'],
                'method' => ['required', Rule::enum(RuleMethod::class)],
                'rate' => ['nullable', 'regex:/^\d{1,3}(\.\d{1,6})?$/'],
                'specific_amount' => ['nullable', 'regex:/^\d{1,11}(\.\d{1,4})?$/'],
                'specific_currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
                'combine' => ['nullable', Rule::enum(CombineMode::class)],
                'base_components' => ['required', 'string'],
                'origin_country' => ['nullable', 'regex:/^[A-Z]{2}$/'],
                'effective_from' => ['required', self::DATE],
                'effective_to' => ['nullable', self::DATE, 'after_or_equal:effective_from'],
                'source_name' => ['required', 'string'],
                'source_page' => ['required', 'integer', 'min:1', 'max:65535'],
            ], $file, $row);

            if ($rowErrors) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $this->checkMethodFields($d, $file, $row, $rowErrors);
            $sourceId = $this->resolveSource($d['source_name'], $file, $row, $rowErrors);
            $line = $this->resolveLineForRule($d, $file, $row, $rowErrors);

            $components = array_map('trim', explode('|', $d['base_components']));
            if (in_array('', $components, true)) {
                $rowErrors[] = $this->error($file, $row, 'base_components has an empty entry');
            }
            if (count($components) !== count(array_unique($components))) {
                $rowErrors[] = $this->error($file, $row, 'base_components lists a component twice');
            }

            if ($rowErrors) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $candidates[] = [
                'row' => $row,
                'tariff_line_id' => (int) $line->id,
                'tax_type' => $d['tax_type'],
                'sequence' => (int) $d['sequence'],
                'method' => $d['method'],
                'rate' => $d['rate'],
                'specific_amount' => $d['specific_amount'],
                'specific_currency' => $d['specific_currency'],
                'combine' => $d['combine'],
                'base_components' => $components,
                'origin_country' => $d['origin_country'],
                'effective_from' => $d['effective_from'],
                'effective_to' => $d['effective_to'],
                'tariff_source_id' => $sourceId,
                'source_page' => (int) $d['source_page'],
            ];
        }

        $existing = $this->existingRules(array_unique(array_column($candidates, 'tariff_line_id')));
        $records = [];

        foreach ($candidates as $i => $c) {
            $rowErrors = [];

            // No overlap with the same (line, tax type, origin), in the database or earlier in this file.
            $others = array_merge($existing, array_slice($candidates, 0, $i));
            foreach ($others as $o) {
                if ($o['tariff_line_id'] === $c['tariff_line_id']
                    && $o['tax_type'] === $c['tax_type']
                    && ($o['origin_country'] ?? '') === ($c['origin_country'] ?? '')
                    && $this->overlaps($c['effective_from'], $c['effective_to'], $o['effective_from'], $o['effective_to'])) {
                    $rowErrors[] = $this->error($file, $c['row'], "dates overlap another {$c['tax_type']} rule on this tariff line"
                        .($c['origin_country'] ? " for origin {$c['origin_country']}" : ' (standard rate)'));
                    break;
                }
            }

            // base_components: customs_value, or a tax type charged earlier on this line in the same period.
            $all = array_merge($existing, $candidates);
            foreach ($c['base_components'] as $component) {
                if ($component === 'customs_value') {
                    continue;
                }
                if (TaxType::tryFrom($component) === null) {
                    $rowErrors[] = $this->error($file, $c['row'], "base_components has unknown component \"{$component}\"");

                    continue;
                }
                if ($component === $c['tax_type']) {
                    $rowErrors[] = $this->error($file, $c['row'], "a {$component} rule cannot include itself in its base");

                    continue;
                }
                $found = false;
                foreach ($all as $j => $o) {
                    if ($j === count($existing) + $i) {
                        continue; // itself
                    }
                    if ($o['tariff_line_id'] === $c['tariff_line_id']
                        && $o['tax_type'] === $component
                        && $o['sequence'] < $c['sequence']
                        && ($o['origin_country'] === null || $o['origin_country'] === $c['origin_country'])
                        && $this->overlaps($c['effective_from'], $c['effective_to'], $o['effective_from'], $o['effective_to'])) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $rowErrors[] = $this->error($file, $c['row'],
                        "base_components lists {$component}, but this line has no {$component} rule with a lower sequence than {$c['sequence']} in the same period");
                }
            }

            $record = $c;
            unset($record['row']);
            $this->collect($errors, $records, $rowErrors, $record);
        }

        return new ValidationResult($errors, $records);
    }

    /** @param list<array{row: int, data: array<string, ?string>}> $rows */
    public function exchangeRates(array $rows, string $file = 'exchange_rates.csv'): ValidationResult
    {
        $errors = [];
        $records = [];
        $accepted = [];

        foreach ($rows as ['row' => $row, 'data' => $d]) {
            $rowErrors = $this->fieldErrors($d, [
                'currency' => ['required', 'regex:/^[A-Z]{3}$/', 'not_in:'.ExchangeRate::BASE_CURRENCY],
                'rate_to_zmw' => ['required', 'regex:/^\d{1,10}(\.\d{1,8})?$/', 'not_regex:/^0+(\.0+)?$/'],
                'effective_from' => ['required', self::DATE],
                'effective_to' => ['nullable', self::DATE, 'after_or_equal:effective_from'],
                'source' => ['required', 'string', 'max:255'],
            ], $file, $row);

            if (! $rowErrors) {
                $clash = DB::table('exchange_rates')->where('currency', $d['currency'])->get(['effective_from', 'effective_to'])
                    ->contains(fn ($r) => $this->overlaps($d['effective_from'], $d['effective_to'], $r->effective_from, $r->effective_to));
                foreach ($accepted as [$currency, $from, $to]) {
                    $clash = $clash || ($currency === $d['currency'] && $this->overlaps($d['effective_from'], $d['effective_to'], $from, $to));
                }
                if ($clash) {
                    $rowErrors[] = $this->error($file, $row, "dates overlap another {$d['currency']} rate");
                } else {
                    $accepted[] = [$d['currency'], $d['effective_from'], $d['effective_to']];
                }
            }

            $this->collect($errors, $records, $rowErrors, $rowErrors ? [] : [
                'currency' => $d['currency'],
                'rate_to_zmw' => $d['rate_to_zmw'],
                'effective_from' => $d['effective_from'],
                'effective_to' => $d['effective_to'],
                'source' => $d['source'],
            ]);
        }

        return new ValidationResult($errors, $records);
    }

    // ---------------------------------------------------------------------

    /** Each method needs exactly its own fields; anything else is a data-entry mistake. */
    private function checkMethodFields(array $d, string $file, int $row, array &$rowErrors): void
    {
        $needs = match (RuleMethod::from($d['method'])) {
            RuleMethod::AdValorem => ['rate'],
            RuleMethod::Specific => ['specific_amount', 'specific_currency'],
            RuleMethod::Compound => ['rate', 'specific_amount', 'specific_currency', 'combine'],
        };

        foreach (['rate', 'specific_amount', 'specific_currency', 'combine'] as $field) {
            $required = in_array($field, $needs, true);
            if ($required && $d[$field] === null) {
                $rowErrors[] = $this->error($file, $row, "{$field} is required for a {$d['method']} rule");
            } elseif (! $required && $d[$field] !== null) {
                $rowErrors[] = $this->error($file, $row, "{$field} must be blank for a {$d['method']} rule");
            }
        }
    }

    private function resolveSource(string $name, string $file, int $row, array &$rowErrors): ?int
    {
        $ids = $this->sourceIdsByName[$name] ??= DB::table('tariff_sources')->where('name', $name)->pluck('id')->all();

        if (count($ids) === 1) {
            return $ids[0];
        }

        $rowErrors[] = $this->error($file, $row, $ids === []
            ? "unknown source \"{$name}\""
            : "source name \"{$name}\" matches more than one source");

        return null;
    }

    /** The tariff line for this HS code that is valid on the rule's start date, and covers its whole range. */
    private function resolveLineForRule(array $d, string $file, int $row, array &$rowErrors): ?object
    {
        $line = DB::table('tariff_lines')
            ->where('hs_code', $d['hs_code'])
            ->where('valid_from', '<=', $d['effective_from'])
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $d['effective_from']))
            ->first(['id', 'valid_to']);

        if ($line === null) {
            $rowErrors[] = $this->error($file, $row, "no tariff line for HS {$d['hs_code']} is valid on {$d['effective_from']}");

            return null;
        }

        if ($line->valid_to !== null && ($d['effective_to'] === null || $d['effective_to'] > $line->valid_to)) {
            $rowErrors[] = $this->error($file, $row, "rule runs past the end of its tariff line ({$line->valid_to})");
        }

        return $line;
    }

    /** @return list<array<string, mixed>> */
    private function existingRules(array $lineIds): array
    {
        if ($lineIds === []) {
            return [];
        }

        return DB::table('tariff_rules')->whereIn('tariff_line_id', $lineIds)
            ->get(['tariff_line_id', 'tax_type', 'sequence', 'origin_country', 'effective_from', 'effective_to'])
            ->map(fn ($r) => [
                'tariff_line_id' => (int) $r->tariff_line_id,
                'tax_type' => $r->tax_type,
                'sequence' => (int) $r->sequence,
                'origin_country' => $r->origin_country,
                'effective_from' => $r->effective_from,
                'effective_to' => $r->effective_to,
            ])->all();
    }

    /** Inclusive date ranges as Y-m-d strings; null end = open-ended. */
    private function overlaps(string $fromA, ?string $toA, string $fromB, ?string $toB): bool
    {
        return $fromA <= ($toB ?? '9999-12-31') && $fromB <= ($toA ?? '9999-12-31');
    }

    /** @return list<RowError> */
    private function fieldErrors(array $data, array $rules, string $file, int $row): array
    {
        foreach (['is_origin', 'is_destination', 'active', 'supported'] as $flag) {
            if (isset($data[$flag])) {
                $data[$flag] = strtolower($data[$flag]); // TRUE / Yes from spreadsheets
            }
        }

        return array_map(
            fn (string $message) => $this->error($file, $row, $message),
            Validator::make($data, $rules)->errors()->all()
        );
    }

    private function booleanRule(): string
    {
        return 'in:'.implode(',', array_keys(self::BOOLEAN_WORDS));
    }

    private function bool(string $value): bool
    {
        return self::BOOLEAN_WORDS[strtolower($value)];
    }

    private function error(string $file, int $row, string $message): RowError
    {
        return new RowError($file, $row, $message);
    }

    /** @param list<RowError> $rowErrors */
    private function collect(array &$errors, array &$records, array $rowErrors, array $record): void
    {
        if ($rowErrors) {
            array_push($errors, ...$rowErrors);
        } else {
            $records[] = $record;
        }
    }
}
