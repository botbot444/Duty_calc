<?php

namespace Tests\Feature\TariffData;

use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\TariffLine;
use App\Models\TariffRule;
use App\Models\TariffSource;
use App\Services\TariffData\TariffDataValidator;
use App\Services\TariffData\ValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TariffDataValidatorTest extends TestCase
{
    use RefreshDatabase;

    private TariffDataValidator $validator;

    private TariffLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new TariffDataValidator;
        $source = TariffSource::create(['name' => 'Test source']);
        $this->line = TariffLine::create([
            'hs_code' => '61091000', 'official_description' => 'T-shirts', 'stat_unit' => 'u',
            'valid_from' => '2026-01-01', 'tariff_source_id' => $source->id, 'source_page' => 12,
        ]);
        // Existing standard customs duty, sequence 1, open-ended.
        TariffRule::create([
            'tariff_line_id' => $this->line->id, 'tax_type' => 'customs_duty', 'sequence' => 1,
            'method' => 'ad_valorem', 'rate' => '0.25', 'base_components' => ['customs_value'],
            'effective_from' => '2026-01-01', 'tariff_source_id' => $source->id, 'source_page' => 12,
        ]);
    }

    /** Builds CsvReader-shaped rows; row numbers start at 2 like a real file. */
    private function rows(array $defaults, array ...$overrides): array
    {
        return array_map(
            fn (int $i, array $o) => ['row' => $i + 2, 'data' => array_merge($defaults, $o)],
            array_keys($overrides), $overrides
        );
    }

    private function vatRow(array $overrides = []): array
    {
        return array_merge([
            'hs_code' => '61091000', 'tax_type' => 'vat', 'sequence' => '3', 'method' => 'ad_valorem',
            'rate' => '0.16', 'specific_amount' => null, 'specific_currency' => null, 'combine' => null,
            'base_components' => 'customs_value|customs_duty', 'origin_country' => null,
            'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31',
            'source_name' => 'Test source', 'source_page' => '12',
        ], $overrides);
    }

    private function rules(array ...$overrides): ValidationResult
    {
        return $this->validator->tariffRules($this->rows($this->vatRow(), ...$overrides));
    }

    private function assertRejected(ValidationResult $result, string $expected): void
    {
        $messages = array_map('strval', $result->errors);
        $this->assertFalse($result->passes(), "Expected an error containing \"{$expected}\" but the rows passed");
        $this->assertTrue(
            collect($messages)->contains(fn ($m) => str_contains(strtolower($m), strtolower($expected))),
            "No error contains \"{$expected}\". Errors:\n".implode("\n", $messages)
        );
    }

    // --- tariff rules: rows that pass -----------------------------------

    public function test_a_valid_vat_rule_passes_and_becomes_an_insertable_record(): void
    {
        $result = $this->rules([]);

        $this->assertTrue($result->passes(), implode("\n", array_map('strval', $result->errors)));
        $record = $result->records[0];
        $this->assertSame($this->line->id, $record['tariff_line_id']);
        $this->assertSame(['customs_value', 'customs_duty'], $record['base_components']);
        $this->assertSame('0.16', $record['rate']);   // kept as a string, never a float
        $this->assertSame(12, $record['source_page']);
        $this->assertArrayNotHasKey('row', $record);
    }

    public function test_a_rate_change_as_end_and_new_row_passes(): void
    {
        $result = $this->rules([], ['rate' => '0.165', 'effective_from' => '2027-01-01', 'effective_to' => null]);

        $this->assertTrue($result->passes(), implode("\n", array_map('strval', $result->errors)));
    }

    public function test_base_components_may_refer_to_a_rule_later_in_the_same_file(): void
    {
        $result = $this->rules(
            ['base_components' => 'customs_value|customs_duty|excise'],
            ['tax_type' => 'excise', 'sequence' => '2', 'rate' => '0.03', 'effective_to' => null],
        );

        $this->assertTrue($result->passes(), implode("\n", array_map('strval', $result->errors)));
    }

    public function test_an_origin_specific_rule_can_sit_beside_the_standard_rule(): void
    {
        $result = $this->rules([], ['origin_country' => 'TZ', 'rate' => '0.10']);

        $this->assertTrue($result->passes(), implode("\n", array_map('strval', $result->errors)));
    }

    public function test_specific_and_compound_rules_with_their_own_fields_pass(): void
    {
        TariffLine::create([
            'hs_code' => '17019900', 'official_description' => 'Sugar', 'stat_unit' => 'kg',
            'valid_from' => '2026-01-01', 'tariff_source_id' => TariffSource::first()->id, 'source_page' => 40,
        ]);
        $duty = ['hs_code' => '17019900', 'tax_type' => 'customs_duty', 'sequence' => '1', 'base_components' => 'customs_value', 'effective_to' => null];

        $specific = $this->rules($duty + ['method' => 'specific', 'rate' => null, 'specific_amount' => '1.50', 'specific_currency' => 'ZMW']);
        $compound = $this->rules($duty + ['method' => 'compound', 'rate' => '0.40', 'specific_amount' => '5', 'specific_currency' => 'ZMW', 'combine' => 'max']);

        $this->assertTrue($specific->passes(), implode("\n", array_map('strval', $specific->errors)));
        $this->assertTrue($compound->passes(), implode("\n", array_map('strval', $compound->errors)));
    }

    // --- tariff rules: each kind of bad row ------------------------------

    public static function badRuleFields(): array
    {
        return [
            'unknown source' => [['source_name' => 'Nobody\'s book'], 'unknown source'],
            'no page number' => [['source_page' => null], 'source page'],
            'page number zero' => [['source_page' => '0'], 'source page'],
            'no source' => [['source_name' => null], 'source name'],
            '7-digit HS code' => [['hs_code' => '6109100'], 'hs code'],
            'HS code with dots' => [['hs_code' => '6109.10.00'], 'hs code'],
            'unknown tax type' => [['tax_type' => 'stamp_duty'], 'tax type'],
            'unknown method' => [['method' => 'percent'], 'method'],
            'unknown combine' => [['method' => 'compound', 'specific_amount' => '5', 'specific_currency' => 'ZMW', 'combine' => 'min'], 'combine'],
            'percent sign in rate' => [['rate' => '16%'], 'rate'],
            'rate in exponent form' => [['rate' => '1.6e-1'], 'rate'],
            'rate with too many decimals' => [['rate' => '0.1234567'], 'rate'],
            'lower-case currency' => [['method' => 'specific', 'rate' => null, 'specific_amount' => '1', 'specific_currency' => 'zmw'], 'specific currency'],
            'bad date' => [['effective_from' => '01/01/2026'], 'effective from'],
            'end before start' => [['effective_to' => '2025-12-31'], 'effective to'],
            'ad valorem without rate' => [['rate' => null], 'rate is required'],
            'ad valorem with a specific amount' => [['specific_amount' => '5'], 'specific_amount must be blank'],
            'specific without currency' => [['method' => 'specific', 'rate' => null, 'specific_amount' => '1.50'], 'specific_currency is required'],
            'specific with a rate' => [['method' => 'specific', 'specific_amount' => '1.50', 'specific_currency' => 'ZMW'], 'rate must be blank'],
            'compound without combine' => [['method' => 'compound', 'specific_amount' => '5', 'specific_currency' => 'ZMW'], 'combine is required'],
            'no line for HS code' => [['hs_code' => '99999999'], 'no tariff line for hs 99999999'],
            'line not yet valid' => [['effective_from' => '2025-06-01'], 'no tariff line'],
        ];
    }

    #[DataProvider('badRuleFields')]
    public function test_bad_rule_rows_are_rejected(array $overrides, string $expected): void
    {
        $this->assertRejected($this->rules($overrides), $expected);
    }

    public function test_base_components_may_not_name_a_tax_charged_later(): void
    {
        $this->assertRejected(
            $this->rules(['tax_type' => 'excise', 'sequence' => '1', 'base_components' => 'customs_value|customs_duty']),
            'no customs_duty rule with a lower sequence'
        );
    }

    public function test_base_components_may_not_name_a_tax_the_line_does_not_have(): void
    {
        $this->assertRejected(
            $this->rules(['base_components' => 'customs_value|customs_duty|excise']),
            'no excise rule with a lower sequence'
        );
    }

    public function test_base_components_may_not_name_a_tax_from_another_period(): void
    {
        $this->assertRejected(
            $this->rules(
                ['base_components' => 'customs_value|customs_duty|excise'],
                ['tax_type' => 'excise', 'sequence' => '2', 'rate' => '0.03', 'effective_from' => '2027-01-01', 'effective_to' => null],
            ),
            'no excise rule with a lower sequence'
        );
    }

    public function test_base_components_may_not_include_the_rule_itself_or_unknown_words(): void
    {
        $this->assertRejected($this->rules(['base_components' => 'customs_value|vat']), 'cannot include itself');
        $this->assertRejected($this->rules(['base_components' => 'cif']), 'unknown component "cif"');
        $this->assertRejected($this->rules(['base_components' => 'customs_value||customs_duty']), 'empty entry');
        $this->assertRejected($this->rules(['base_components' => 'customs_value|customs_value']), 'twice');
    }

    public function test_dates_may_not_overlap_a_rule_already_in_the_database(): void
    {
        $this->assertRejected(
            $this->rules(['tax_type' => 'customs_duty', 'sequence' => '1', 'base_components' => 'customs_value', 'effective_from' => '2026-06-01']),
            'dates overlap another customs_duty rule on this tariff line (standard rate)'
        );
    }

    public function test_dates_may_not_overlap_an_earlier_row_in_the_same_file(): void
    {
        $result = $this->rules([], ['rate' => '0.165', 'effective_from' => '2026-12-31', 'effective_to' => null]);

        $this->assertRejected($result, 'dates overlap another vat rule');
        $this->assertSame(3, $result->errors[0]->row);   // the second data row, not the first
    }

    public function test_a_rule_may_not_run_past_the_end_of_its_line(): void
    {
        $this->line->update(['valid_to' => '2026-06-30']);

        $this->assertRejected($this->rules(['effective_to' => null]), 'past the end of its tariff line');
    }

    // --- tariff lines ----------------------------------------------------

    private function lines(array ...$overrides): ValidationResult
    {
        return $this->validator->tariffLines($this->rows([
            'hs_code' => '10063000', 'official_description' => 'Rice', 'stat_unit' => 'kg',
            'valid_from' => '2026-01-01', 'valid_to' => null, 'source_name' => 'Test source', 'source_page' => '7',
        ], ...$overrides));
    }

    public function test_a_valid_tariff_line_passes(): void
    {
        $result = $this->lines([]);

        $this->assertTrue($result->passes(), implode("\n", array_map('strval', $result->errors)));
        $this->assertSame(TariffSource::first()->id, $result->records[0]['tariff_source_id']);
    }

    public static function badLineFields(): array
    {
        return [
            'unknown unit' => [['stat_unit' => 'kgs'], 'stat unit'],
            'no page number' => [['source_page' => null], 'source page'],
            'unknown source' => [['source_name' => 'Elsewhere'], 'unknown source'],
            '10-digit HS code' => [['hs_code' => '1006300000'], 'hs code'],
            'no description' => [['official_description' => null], 'official description'],
        ];
    }

    #[DataProvider('badLineFields')]
    public function test_bad_line_rows_are_rejected(array $overrides, string $expected): void
    {
        $this->assertRejected($this->lines($overrides), $expected);
    }

    public function test_line_dates_may_not_overlap_for_the_same_hs_code(): void
    {
        $this->assertRejected($this->lines(['hs_code' => '61091000', 'valid_from' => '2026-03-01']), 'overlap another tariff line for hs 61091000');
        $this->assertRejected($this->lines([], ['valid_from' => '2026-05-01']), 'overlap another tariff line for hs 10063000');
    }

    // --- other reference files -------------------------------------------

    public function test_exchange_rates_reject_zmw_zero_and_overlaps(): void
    {
        ExchangeRate::create(['currency' => 'USD', 'rate_to_zmw' => '26.5', 'effective_from' => '2026-11-16', 'source' => 'BoZ']);
        $rate = fn (array $o) => $this->validator->exchangeRates($this->rows(
            ['currency' => 'TZS', 'rate_to_zmw' => '0.0105', 'effective_from' => '2026-11-16', 'effective_to' => null, 'source' => 'BoZ'], $o
        ));

        $this->assertTrue($rate([])->passes());
        $this->assertRejected($rate(['currency' => 'ZMW']), 'currency');
        $this->assertRejected($rate(['rate_to_zmw' => '0.000']), 'rate to zmw');
        $this->assertRejected($rate(['rate_to_zmw' => '-1']), 'rate to zmw');
        $this->assertRejected($rate(['currency' => 'USD', 'effective_from' => '2026-11-20']), 'overlap another usd rate');
    }

    public function test_trade_routes_need_a_known_origin_and_destination(): void
    {
        Country::create(['iso2' => 'TZ', 'name' => 'Tanzania', 'is_origin' => true]);
        Country::create(['iso2' => 'ZM', 'name' => 'Zambia', 'is_destination' => true]);
        $route = fn (array $o) => $this->validator->tradeRoutes($this->rows(
            ['origin_iso2' => 'TZ', 'destination_iso2' => 'ZM', 'border_post' => 'Nakonde', 'supported' => 'TRUE', 'notes' => null], $o
        ));

        $passing = $route([]);
        $this->assertTrue($passing->passes());
        $this->assertTrue($passing->records[0]['supported']);
        $this->assertRejected($route(['destination_iso2' => 'MW']), 'not a known destination');
        Country::create(['iso2' => 'ZA', 'name' => 'South Africa']);   // exists, but not marked as an origin
        $this->assertRejected($route(['origin_iso2' => 'ZA']), 'not a known origin');
        $this->assertRejected($route(['origin_iso2' => 'ZM']), 'must be different');
        $this->assertRejected($route(['supported' => 'maybe']), 'supported');
    }

    public function test_catalogue_items_need_a_cat_id_and_a_known_line(): void
    {
        $item = fn (array $o) => $this->validator->catalogueItems($this->rows(
            ['id' => 'CAT-0001', 'hs_code' => '61091000', 'display_name' => 'T-shirt', 'active' => '1'], $o
        ));

        $this->assertSame($this->line->id, $item([])->records[0]['tariff_line_id']);
        $this->assertRejected($item(['id' => 'ITEM-1']), 'id');
        $this->assertRejected($item(['hs_code' => '10063000']), 'no tariff line for hs 10063000');
    }

    public function test_aliases_need_a_known_item_and_language(): void
    {
        $alias = fn (array $o) => $this->validator->productAliases($this->rows(
            ['catalogue_item_id' => 'CAT-0404', 'alias' => 'shati', 'language' => 'sw'], $o
        ));

        $this->assertRejected($alias([]), 'unknown catalogue item cat-0404');
        $this->assertRejected($alias(['language' => 'fr']), 'language');
    }
}
