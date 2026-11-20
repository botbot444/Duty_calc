<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Formatting for the trader screens. Works on the 2-decimal strings the engine returns,
 * never on floats.
 */
final class Display
{
    /** "10000.5" in ZMW → "K10,000.50"; USD → "US$18.87". */
    public static function money(?string $amount, ?string $currency = 'ZMW'): string
    {
        if ($amount === null) {
            return '–';
        }

        $value = BigDecimal::of($amount)->toScale(2);
        $negative = $value->isNegative();
        [$whole, $cents] = explode('.', (string) $value->abs());
        $whole = strrev(implode(',', str_split(strrev($whole), 3)));

        $prefix = match ($currency) {
            'ZMW' => 'K',
            'USD' => 'US$',
            null => '',
            default => $currency.' ',
        };

        return ($negative ? '-' : '').$prefix.$whole.'.'.$cents;
    }

    /** A fraction rate "0.050000" → "5%"; "0.165000" → "16.5%". */
    public static function percent(?string $rate): string
    {
        if ($rate === null) {
            return '–';
        }

        return (string) BigDecimal::of($rate)->multipliedBy(100)->stripTrailingZeros().'%';
    }

    /** An exchange rate "26.50000000" → "26.50"; "0.01050000" → "0.0105". */
    public static function rate(?string $rate): string
    {
        if ($rate === null) {
            return '–';
        }
        $value = BigDecimal::of($rate)->stripTrailingZeros();

        return (string) ($value->getScale() < 2 ? $value->toScale(2) : $value);
    }

    /** "17019900" → "1701.99.00". */
    public static function hs(?string $code): string
    {
        if ($code === null || strlen($code) !== 8) {
            return (string) $code;
        }

        return substr($code, 0, 4).'.'.substr($code, 4, 2).'.'.substr($code, 6, 2);
    }

    /** "2026-01-01" → "1 Jan 2026", in the current language. */
    public static function date(?string $date): string
    {
        return $date === null ? '–' : CarbonImmutable::parse($date)->translatedFormat('j M Y');
    }

    /**
     * How a charge's rate reads, from the line item's values (plan D7.9–D7.12), e.g.
     * "5% or K0.10 per kg, whichever is higher".
     *
     * @param  array<string, mixed>  $line  one entry of EstimateResult::toArray()['line_items']
     */
    public static function rule(array $line, ?string $unit): string
    {
        $percent = self::percent($line['rate']);
        $perUnit = $line['specific_amount'] === null ? null
            : __(':amount per :unit', ['amount' => self::money($line['specific_amount'], $line['specific_currency']), 'unit' => $unit]);

        return match ($line['method']) {
            'ad_valorem' => $percent,
            'specific' => $perUnit,
            'compound' => $line['combine'] === 'max'
                ? __(':first or :second, whichever is higher', ['first' => $percent, 'second' => $perUnit])
                : __(':first plus :second', ['first' => $percent, 'second' => $perUnit]),
            default => '',
        };
    }
}
