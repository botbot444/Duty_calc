<?php

namespace App\Services\Admin;

use App\Models\ExchangeRate;
use App\Models\TariffLine;
use App\Models\TariffRule;
use App\Models\TariffSource;
use App\Services\TariffData\RowError;
use App\Services\TariffData\TariffDataValidator;
use App\Services\TariffData\ValidationResult;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The only ways an admin changes a rate. A rule or exchange rate is never edited in place:
 * it is ended (effective_to) and, for a new value, a new row is added. The new row goes
 * through the same TariffDataValidator as the CSV import, inside the same transaction,
 * so the admin pages cannot save anything the import would refuse.
 */
class RateChangeService
{
    public function __construct(
        private readonly AuditLog $audit,
        private readonly TariffDataValidator $validator,
    ) {}

    /**
     * End $old the day before the new rate starts and add the new rule. Fields not given
     * keep the old rule's value, except the source and page, which every new rate must cite.
     */
    public function replaceRule(TariffRule $old, array $input): TariffRule
    {
        $from = CarbonImmutable::parse($input['effective_from'])->toDateString();

        if ($from <= $old->effective_from->toDateString()) {
            $this->fail('effective_from', "The new rate must start after the current one ({$old->effective_from->toDateString()}).");
        }
        if ($old->effective_to !== null && $from > $old->effective_to->addDay()->toDateString()) {
            $this->fail('effective_from', "The current rate already ends on {$old->effective_to->toDateString()}; use “Add a rate” on the tariff line for the new one.");
        }

        return $this->audit->change(function (AuditLog $audit) use ($old, $input, $from) {
            $before = AuditLog::snapshot($old);
            $old->update(['effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString()]);
            $audit->record('end', $old, $before, AuditLog::snapshot($old));

            $source = TariffSource::findOrFail($input['tariff_source_id']);
            $method = $input['method'] ?? $old->method->value;
            $keep = fn (string $field) => array_key_exists($field, $input) ? $input[$field] : null;
            $carry = fn (string $field, mixed $oldValue) => array_key_exists($field, $input) ? $input[$field] : $oldValue;

            $row = [
                'hs_code' => $old->tariffLine->hs_code,
                'tax_type' => $old->tax_type->value,
                'sequence' => (string) $carry('sequence', $old->sequence),
                'method' => $method,
                // A changed method starts clean; the same method carries the old values unless replaced.
                'rate' => $method === $old->method->value ? $carry('rate', $this->plain($old->rate)) : $keep('rate'),
                'specific_amount' => $method === $old->method->value ? $carry('specific_amount', $this->plain($old->specific_amount)) : $keep('specific_amount'),
                'specific_currency' => $method === $old->method->value ? $carry('specific_currency', $old->specific_currency) : $keep('specific_currency'),
                'combine' => $method === $old->method->value ? $carry('combine', $old->combine?->value) : $keep('combine'),
                'base_components' => implode('|', $input['base_components'] ?? $old->base_components),
                'origin_country' => $old->origin_country,
                'effective_from' => $from,
                'effective_to' => $input['effective_to'] ?? null,
                'source_name' => $source->name,
                'source_page' => (string) $input['source_page'],
            ];

            $record = $this->passOrFail($this->validator->tariffRules([['row' => 1, 'data' => $this->strings($row)]], 'admin'))[0];
            $new = TariffRule::create($record);
            $audit->record('replace', $new, ['replaces_rule_id' => $old->id], AuditLog::snapshot($new));

            return $new;
        });
    }

    /**
     * Add a rule to a line for a period with no rule of that charge (a new line, or after a
     * rate ended). Same checks as the CSV import: source and page required, no overlap with
     * another rule of the same charge, base components that exist. A "no duty" fact is a 0% rule.
     */
    public function addRule(TariffLine $line, array $input): TariffRule
    {
        $source = TariffSource::findOrFail($input['tariff_source_id']);

        return $this->audit->change(function (AuditLog $audit) use ($line, $input, $source) {
            $row = [
                'hs_code' => $line->hs_code,
                'tax_type' => $input['tax_type'],
                'sequence' => (string) ($input['sequence'] ?? 1),
                'method' => $input['method'],
                'rate' => $input['rate'] ?? null,
                'specific_amount' => $input['specific_amount'] ?? null,
                'specific_currency' => $input['specific_currency'] ?? null,
                'combine' => $input['combine'] ?? null,
                'base_components' => implode('|', $input['base_components']),
                'origin_country' => null,
                'effective_from' => CarbonImmutable::parse($input['effective_from'])->toDateString(),
                'effective_to' => $input['effective_to'] ?? null,
                'source_name' => $source->name,
                'source_page' => (string) $input['source_page'],
            ];

            $record = $this->passOrFail($this->validator->tariffRules([['row' => 1, 'data' => $this->strings($row)]], 'admin'))[0];
            // The validator finds the line by HS code and start date; it must be this line.
            if ($record['tariff_line_id'] !== $line->id) {
                $this->fail('effective_from', "The rate must start while this tariff line is valid (from {$line->valid_from->toDateString()}).");
            }
            $rule = TariffRule::create($record);
            $audit->record('create', $rule, null, AuditLog::snapshot($rule));

            return $rule;
        });
    }

    /** End a rule without a replacement (for example a charge that is abolished). */
    public function endRule(TariffRule $rule, string $effectiveTo): TariffRule
    {
        $to = CarbonImmutable::parse($effectiveTo)->toDateString();

        if ($to < $rule->effective_from->toDateString()) {
            $this->fail('effective_to', 'The end date cannot be before the rule starts.');
        }
        if ($rule->effective_to !== null && $to >= $rule->effective_to->toDateString()) {
            $this->fail('effective_to', "The rule already ends on {$rule->effective_to->toDateString()}; an end date can only move earlier.");
        }

        return $this->audit->change(function (AuditLog $audit) use ($rule, $to) {
            $before = AuditLog::snapshot($rule);
            $rule->update(['effective_to' => $to]);
            $audit->record('end', $rule, $before, AuditLog::snapshot($rule));

            return $rule;
        });
    }

    /** Add a weekly exchange rate. The currency's open rate is ended the day before. */
    public function addExchangeRate(array $input): ExchangeRate
    {
        $currency = $input['currency'];
        $from = CarbonImmutable::parse($input['effective_from'])->toDateString();

        return $this->audit->change(function (AuditLog $audit) use ($input, $currency, $from) {
            $open = ExchangeRate::where('currency', $currency)->whereNull('effective_to')->lockForUpdate()->first();

            if ($open !== null) {
                if ($open->effective_from->toDateString() >= $from) {
                    $this->fail('effective_from', "The current {$currency} rate starts on {$open->effective_from->toDateString()}; a new rate must start after it.");
                }
                $before = AuditLog::snapshot($open);
                $open->update(['effective_to' => CarbonImmutable::parse($from)->subDay()->toDateString()]);
                $audit->record('end', $open, $before, AuditLog::snapshot($open));
            }

            $record = $this->passOrFail($this->validator->exchangeRates([['row' => 1, 'data' => $this->strings([
                'currency' => $currency,
                'rate_to_zmw' => $input['rate_to_zmw'],
                'effective_from' => $from,
                'effective_to' => null,
                'source' => $input['source'],
            ])]], 'admin'))[0];

            $rate = ExchangeRate::create($record + ['entered_by' => Auth::id()]);
            $audit->record('create', $rate, null, AuditLog::snapshot($rate));

            return $rate;
        });
    }

    // ---------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function passOrFail(ValidationResult $result): array
    {
        if (! $result->passes()) {
            throw ValidationException::withMessages([
                'row' => array_map(fn (RowError $e) => $e->message, $result->errors),
            ]);
        }

        return $result->records;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** CsvReader-style row: trimmed strings, blanks as null. */
    private function strings(array $row): array
    {
        return array_map(fn ($v) => $v === null || $v === '' ? null : trim((string) $v), $row);
    }

    /** "0.250000" -> "0.25", so a carried-over value passes the same decimal checks as typed input. */
    private function plain(?string $decimal): ?string
    {
        return $decimal === null ? null : (string) BigDecimal::of($decimal)->strippedOfTrailingZeros();
    }
}
