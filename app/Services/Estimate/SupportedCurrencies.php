<?php

namespace App\Services\Estimate;

use App\Models\ExchangeRate;

/**
 * Currencies a trader may enter values in: ZMW (no conversion) plus every currency with an
 * exchange rate in force today (Lusaka date).
 */
class SupportedCurrencies
{
    /** @return list<array{code: string, rate_to_zmw: string, effective_from: ?string, source: ?string}> */
    public function all(): array
    {
        $rates = ExchangeRate::inForce()
            ->orderBy('currency')
            ->orderByDesc('effective_from')
            ->get()
            ->unique('currency')
            ->map(fn (ExchangeRate $r) => [
                'code' => $r->currency,
                'rate_to_zmw' => $r->rate_to_zmw,
                'effective_from' => $r->effective_from->toDateString(),
                'source' => $r->source,
            ])
            ->values()
            ->all();

        return [
            ['code' => ExchangeRate::BASE_CURRENCY, 'rate_to_zmw' => '1', 'effective_from' => null, 'source' => null],
            ...$rates,
        ];
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_column($this->all(), 'code');
    }
}
