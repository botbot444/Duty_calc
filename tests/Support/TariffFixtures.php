<?php

namespace Tests\Support;

use App\Models\CatalogueItem;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\TariffLine;
use App\Models\TariffRule;
use App\Models\TariffSource;
use App\Models\TradeRoute;

/**
 * Test-owned tariff data for engine tests. Kept in code, not read from the sample CSVs, so
 * the golden cases in docs/golden-cases.md never move when the sample files change.
 * Every rate here is a made-up test value.
 */
trait TariffFixtures
{
    protected TariffSource $fixtureSource;

    protected TradeRoute $nakonde;

    protected TradeRoute $mwami;

    /** @var array<string, TariffLine> keyed by HS code */
    protected array $lines = [];

    protected function seedTariffFixtures(): void
    {
        $this->fixtureSource = TariffSource::create(['name' => 'Test fixture source – not real rates', 'edition' => 'test']);

        Country::create(['iso2' => 'ZM', 'name' => 'Zambia', 'is_destination' => true]);
        Country::create(['iso2' => 'TZ', 'name' => 'Tanzania', 'is_origin' => true]);
        Country::create(['iso2' => 'MW', 'name' => 'Malawi', 'is_origin' => true]);
        $this->nakonde = TradeRoute::create(['origin_iso2' => 'TZ', 'destination_iso2' => 'ZM', 'border_post' => 'Nakonde', 'supported' => true]);
        $this->mwami = TradeRoute::create(['origin_iso2' => 'MW', 'destination_iso2' => 'ZM', 'border_post' => 'Mwami', 'supported' => false]);

        ExchangeRate::create(['currency' => 'USD', 'rate_to_zmw' => '26.5', 'effective_from' => '2026-11-16', 'source' => 'Test rate']);
        ExchangeRate::create(['currency' => 'TZS', 'rate_to_zmw' => '0.0105', 'effective_from' => '2026-11-16', 'source' => 'Test rate']);

        $vat = ['tax_type' => 'vat', 'sequence' => 3, 'method' => 'ad_valorem', 'rate' => '0.16',
            'base_components' => ['customs_value', 'customs_duty'], 'effective_to' => '2026-12-31'];

        // T-shirt: 25% duty + VAT. Two active items and one inactive share the line.
        $this->fixtureLine('61091000', 'u', 'Cotton T-shirt', 'CAT-0001', [
            ['tax_type' => 'customs_duty', 'sequence' => 1, 'method' => 'ad_valorem', 'rate' => '0.25'],
            $vat,
        ]);
        CatalogueItem::create(['id' => 'CAT-0002', 'tariff_line_id' => $this->lines['61091000']->id, 'display_name' => 'Cotton vest']);
        CatalogueItem::create(['id' => 'CAT-0099', 'tariff_line_id' => $this->lines['61091000']->id, 'display_name' => 'Retired item', 'active' => false]);

        // Rice: K1.50 per kg + VAT.
        $this->fixtureLine('10063000', 'kg', 'Rice', 'CAT-0003', [
            ['tax_type' => 'customs_duty', 'sequence' => 1, 'method' => 'specific', 'specific_amount' => '1.50', 'specific_currency' => 'ZMW'],
            $vat,
        ]);

        // Sugar: 40% or K5.00 per kg, whichever is higher + VAT.
        $this->fixtureLine('17019900', 'kg', 'Sugar', 'CAT-0004', [
            ['tax_type' => 'customs_duty', 'sequence' => 1, 'method' => 'compound', 'rate' => '0.40',
                'specific_amount' => '5.00', 'specific_currency' => 'ZMW', 'combine' => 'max'],
            $vat,
        ]);

        // Soft drink: 25% duty, 3% excise on CIF + duty, VAT on CIF + duty + excise.
        $this->fixtureLine('22021000', 'l', 'Soft drink', 'CAT-0005', [
            ['tax_type' => 'customs_duty', 'sequence' => 1, 'method' => 'ad_valorem', 'rate' => '0.25'],
            ['tax_type' => 'excise', 'sequence' => 2, 'method' => 'ad_valorem', 'rate' => '0.03',
                'base_components' => ['customs_value', 'customs_duty']],
            ['base_components' => ['customs_value', 'customs_duty', 'excise']] + $vat,
        ]);

        // Phone charger: 15% duty + VAT.
        $this->fixtureLine('85044000', 'u', 'Phone charger', 'CAT-0006', [
            ['tax_type' => 'customs_duty', 'sequence' => 1, 'method' => 'ad_valorem', 'rate' => '0.15'],
            $vat,
        ]);

        // Cooking oil: 5% plus K0.50 per kg + VAT.
        $this->fixtureLine('15079000', 'kg', 'Cooking oil', 'CAT-0008', [
            ['tax_type' => 'customs_duty', 'sequence' => 1, 'method' => 'compound', 'rate' => '0.05',
                'specific_amount' => '0.50', 'specific_currency' => 'ZMW', 'combine' => 'sum'],
            $vat,
        ]);
    }

    /** @param list<array<string, mixed>> $rules */
    protected function fixtureLine(string $hs, string $unit, string $name, string $itemId, array $rules): TariffLine
    {
        $line = TariffLine::create([
            'hs_code' => $hs, 'official_description' => "{$name} (test fixture)", 'stat_unit' => $unit,
            'valid_from' => '2026-01-01', 'tariff_source_id' => $this->fixtureSource->id, 'source_page' => 100,
        ]);
        CatalogueItem::create(['id' => $itemId, 'tariff_line_id' => $line->id, 'display_name' => $name]);

        foreach ($rules as $rule) {
            $this->fixtureRule($line, $rule);
        }

        return $this->lines[$hs] = $line;
    }

    protected function fixtureRule(TariffLine $line, array $attributes): TariffRule
    {
        return TariffRule::create($attributes + [
            'tariff_line_id' => $line->id,
            'base_components' => ['customs_value'],
            'effective_from' => '2026-01-01',
            'tariff_source_id' => $this->fixtureSource->id,
            'source_page' => 101,
        ]);
    }

    protected function ruleFor(string $hs, string $taxType): TariffRule
    {
        return TariffRule::where('tariff_line_id', $this->lines[$hs]->id)->where('tax_type', $taxType)
            ->orderByDesc('effective_from')->firstOrFail();
    }
}
