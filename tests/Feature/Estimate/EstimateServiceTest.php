<?php

namespace Tests\Feature\Estimate;

use App\Enums\EstimateStatus;
use App\Models\Calculation;
use App\Models\CatalogueItem;
use App\Models\ExchangeRate;
use App\Services\Estimate\EstimateRequest;
use App\Services\Estimate\EstimateResult;
use App\Services\Estimate\EstimateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

/**
 * Rule mechanics on the import charges (customs duty, excise, VAT), switched on by
 * chargeImportTaxes(). Expected amounts are worked out by hand in docs/golden-cases.md.
 * The export product itself is tested in ExportEstimateTest.
 */
class EstimateServiceTest extends TestCase
{
    use RefreshDatabase;
    use TariffFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        config(['dutycalc.missing_freight_insurance' => 'zero']);
        $this->chargeImportTaxes();
        $this->seedTariffFixtures();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function estimate(array $input): EstimateResult
    {
        return $this->app->make(EstimateService::class)->estimate(new EstimateRequest(...$input + [
            'tradeRouteId' => $this->nakonde->id,
            'currency' => 'ZMW',
            'freight' => '0',
            'insurance' => '0',
        ]));
    }

    /** @return array<string, string> tax type => amount */
    private function amounts(EstimateResult $result): array
    {
        $amounts = [];
        foreach ($result->lineItems as $line) {
            $amounts[$line->taxType->value] = $line->amount;
        }

        return $amounts;
    }

    private function missingItems(EstimateResult $result): array
    {
        return array_column($result->missing, 'item');
    }

    private function assertMissingMentions(EstimateResult $result, string $text): void
    {
        $messages = implode("\n", array_column($result->missing, 'message'));
        $this->assertStringContainsStringIgnoringCase($text, $messages);
    }

    // --- golden cases ----------------------------------------------------

    public static function goldenCases(): array
    {
        return [
            'G1 ad valorem duty + VAT, ZMW' => [
                ['catalogueItemId' => 'CAT-0001', 'declaredValue' => '10000.00', 'freight' => '500.00', 'insurance' => '100.00'],
                '10600.00', ['customs_duty' => '2650.00', 'vat' => '2120.00'], '4770.00',
            ],
            'G2 USD input converted at 26.50' => [
                ['catalogueItemId' => 'CAT-0001', 'currency' => 'USD', 'declaredValue' => '400', 'freight' => '50', 'insurance' => '10'],
                '12190.00', ['customs_duty' => '3047.50', 'vat' => '2438.00'], '5485.50',
            ],
            'G3 specific duty per kg' => [
                ['catalogueItemId' => 'CAT-0003', 'declaredValue' => '5000', 'freight' => '300', 'insurance' => '0', 'quantity' => '1000'],
                '5300.00', ['customs_duty' => '1500.00', 'vat' => '1088.00'], '2588.00',
            ],
            'G4 compound max, ad valorem side wins' => [
                ['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500'],
                '10000.00', ['customs_duty' => '4000.00', 'vat' => '2240.00'], '6240.00',
            ],
            'G5 compound max, specific side wins' => [
                ['catalogueItemId' => 'CAT-0004', 'declaredValue' => '2000', 'quantity' => '500'],
                '2000.00', ['customs_duty' => '2500.00', 'vat' => '720.00'], '3220.00',
            ],
            'G6 excise in the VAT base' => [
                ['catalogueItemId' => 'CAT-0005', 'declaredValue' => '8000', 'freight' => '400', 'insurance' => '100'],
                '8500.00', ['customs_duty' => '2125.00', 'excise' => '318.75', 'vat' => '1751.00'], '4194.75',
            ],
            'G7 compound sum' => [
                ['catalogueItemId' => 'CAT-0008', 'declaredValue' => '6000', 'freight' => '200', 'insurance' => '50', 'quantity' => '400'],
                '6250.00', ['customs_duty' => '512.50', 'vat' => '1082.00'], '1594.50',
            ],
            'G8 TZS input' => [
                ['catalogueItemId' => 'CAT-0006', 'currency' => 'TZS', 'declaredValue' => '1000000', 'freight' => '50000'],
                '11025.00', ['customs_duty' => '1653.75', 'vat' => '2028.60'], '3682.35',
            ],
            'G9 conversion rounded half up before duty' => [
                ['catalogueItemId' => 'CAT-0001', 'currency' => 'USD', 'declaredValue' => '10.01'],
                '265.27', ['customs_duty' => '66.32', 'vat' => '53.05'], '119.37',
            ],
            'R1 duty of exactly .005 rounds up' => [
                ['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100.02'],
                '100.02', ['customs_duty' => '25.01', 'vat' => '20.00'], '45.01',
            ],
            'shared line: a second item on the T-shirt line' => [
                ['catalogueItemId' => 'CAT-0002', 'declaredValue' => '10000.00', 'freight' => '500.00', 'insurance' => '100.00'],
                '10600.00', ['customs_duty' => '2650.00', 'vat' => '2120.00'], '4770.00',
            ],
        ];
    }

    #[DataProvider('goldenCases')]
    public function test_golden_case(array $input, string $customsValue, array $amounts, string $total): void
    {
        $result = $this->estimate($input);

        $this->assertSame(EstimateStatus::Estimated, $result->status, json_encode($result->missing));
        $this->assertSame($customsValue, $result->valuation['customs_value_zmw']);
        $this->assertSame($amounts, $this->amounts($result));
        $this->assertSame($total, $result->total);
        $this->assertSame([], $result->missing);
    }

    // --- response shape, copies and records ------------------------------

    public function test_the_result_has_the_developer_brief_shape_with_2dp_strings(): void
    {
        $data = $this->estimate(['catalogueItemId' => 'CAT-0005', 'declaredValue' => '8000', 'freight' => '400', 'insurance' => '100'])->toArray();

        foreach (['status', 'estimate_id', 'currency', 'line_items', 'total_estimated_charges', 'tariff_effective_date',
            'disclaimer', 'assumptions', 'missing', 'product', 'route', 'valuation', 'calculated_on', 'engine_version'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        $this->assertSame('estimated', $data['status']);
        $this->assertSame('ZMW', $data['currency']);
        $this->assertSame('4194.75', $data['total_estimated_charges']);
        $this->assertSame('2026-01-01', $data['tariff_effective_date']);
        $this->assertSame('2026-11-20', $data['calculated_on']);
        $this->assertSame(['id' => 'CAT-0005', 'name' => 'Soft drink', 'hs_code' => '22021000',
            'official_description' => 'Soft drink (test fixture)', 'stat_unit' => 'l'], $data['product']);
        $this->assertSame('Nakonde', $data['route']['border_post']);

        $this->assertSame(['customs_duty', 'excise', 'vat'], array_column($data['line_items'], 'tax_type'));
        $excise = $data['line_items'][1];
        $this->assertSame('Excise duty', $excise['label']);
        $this->assertSame('10625.00', $excise['taxable_base']);
        $this->assertSame('318.75', $excise['amount']);
        $this->assertSame('0.030000', $excise['rate']);
        $this->assertSame(['customs_value', 'customs_duty'], $excise['base_components']);
        $this->assertSame(['name' => 'Test fixture source – not real rates', 'page' => 101], $excise['source']);

        foreach ($data['line_items'] as $line) {
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $line['amount']);
            $this->assertIsString($line['amount']);
        }
        $json = json_encode($data);
        $this->assertStringNotContainsString('4194.75,', $json, 'amounts must be JSON strings, not numbers');
    }

    public function test_an_estimate_is_saved_with_every_value_it_used_copied_in(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'currency' => 'USD', 'declaredValue' => '400', 'freight' => '50', 'insurance' => '10']);

        $calc = Calculation::with('breakdowns')->findOrFail($result->estimateId);
        $this->assertSame(EstimateStatus::Estimated, $calc->status);
        $this->assertSame('CAT-0001', $calc->catalogue_item_id);
        $this->assertSame('Cotton T-shirt', $calc->catalogue_item_name);
        $this->assertSame('61091000', $calc->hs_code);
        $this->assertSame('USD', $calc->input_currency);
        $this->assertSame('26.50000000', $calc->exchange_rate_value);
        $this->assertSame('2026-11-16', $calc->exchange_rate_effective_from->toDateString());
        $this->assertSame('10600.00', $calc->declared_value_zmw);
        $this->assertSame('1325.00', $calc->freight_zmw);
        $this->assertSame('265.00', $calc->insurance_zmw);
        $this->assertSame('12190.00', $calc->customs_value_zmw);
        $this->assertSame('5485.50', $calc->total_zmw);
        $this->assertSame('400', $calc->inputs['declared_value']);
        $this->assertSame(config('dutycalc.engine_version'), $calc->engine_version);
        $this->assertSame('2027-02-18', $calc->expires_at->toDateString());   // 90 days

        $this->assertCount(2, $calc->breakdowns);
        $vat = $calc->breakdowns[1];
        $this->assertSame('vat', $vat->tax_type->value);
        $this->assertSame('15237.50', $vat->taxable_base_zmw);
        $this->assertSame('0.160000', $vat->rate);
        $this->assertSame('2438.00', $vat->amount_zmw);
        $this->assertSame('2026-12-31', $vat->rule_effective_to->toDateString());
        $this->assertSame(101, $vat->source_page);
        $this->assertSame($this->ruleFor('61091000', 'vat')->id, $vat->tariff_rule_id);
    }

    public function test_a_saved_estimate_does_not_change_when_the_rate_is_replaced_later(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '10000', 'freight' => '500', 'insurance' => '100']);

        // Replace the duty from tomorrow: end the old row, add a new one.
        $this->ruleFor('61091000', 'customs_duty')->update(['effective_to' => '2026-11-20']);
        $this->fixtureRule($this->lines['61091000'], ['tax_type' => 'customs_duty', 'sequence' => 1, 'method' => 'ad_valorem',
            'rate' => '0.40', 'effective_from' => '2026-11-21']);
        CatalogueItem::find('CAT-0001')->update(['display_name' => 'Renamed later']);

        $reloaded = $this->app->make(EstimateService::class)->find($result->estimateId);

        $this->assertEquals($result->toArray(), $reloaded->toArray());
        $this->assertSame('Cotton T-shirt', $reloaded->product['name']);
    }

    public function test_the_same_input_on_the_same_data_gives_the_same_output(): void
    {
        $input = ['catalogueItemId' => 'CAT-0004', 'declaredValue' => '2000', 'quantity' => '500'];

        $first = $this->estimate($input)->toArray();
        $second = $this->estimate($input)->toArray();

        $this->assertNotSame($first['estimate_id'], $second['estimate_id']);
        unset($first['estimate_id'], $second['estimate_id']);
        $this->assertSame($first, $second);
    }

    public function test_every_estimate_records_the_standard_rate_and_rounding_assumptions(): void
    {
        $assumptions = implode("\n", $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100'])->assumptions);

        $this->assertStringContainsString('standard rate', $assumptions);
        $this->assertStringContainsString('no trade preference', $assumptions);
        $this->assertStringContainsString('half up', $assumptions);
        $this->assertStringContainsString('CIF', $assumptions);
    }

    // --- currency --------------------------------------------------------

    public function test_zmw_input_uses_rate_1_and_records_no_conversion(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100']);
        $calc = Calculation::findOrFail($result->estimateId);

        $this->assertNull($calc->exchange_rate_id);
        $this->assertSame('1.00000000', $calc->exchange_rate_value);
        $this->assertStringContainsString('no conversion', implode("\n", $result->assumptions));
    }

    public function test_foreign_currency_input_records_the_rate_and_its_date(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'currency' => 'USD', 'declaredValue' => '100']);

        $this->assertSame('26.50000000', $result->valuation['exchange_rate']);
        $this->assertSame('2026-11-16', $result->valuation['exchange_rate_date']);
        $this->assertStringContainsString('USD 1 = ZMW 26.50000000 (Test rate, from 2026-11-16)', implode("\n", $result->assumptions));
    }

    public function test_a_currency_with_no_rate_in_force_is_unsupported(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'currency' => 'EUR', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertSame(['exchange_rate'], $this->missingItems($result));
        $this->assertMissingMentions($result, 'EUR');
        $this->assertNull($result->total);
    }

    public function test_an_exchange_rate_that_has_ended_is_not_used(): void
    {
        ExchangeRate::where('currency', 'USD')->update(['effective_to' => '2026-11-19']);

        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'currency' => 'USD', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertMissingMentions($result, 'USD');
    }

    // --- freight and insurance -------------------------------------------

    public function test_explicit_zero_freight_is_not_an_assumption(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100', 'freight' => '0', 'insurance' => '0']);

        $this->assertStringNotContainsString('not given', implode("\n", $result->assumptions));
    }

    public function test_blank_freight_counts_as_zero_and_says_so_when_policy_is_zero(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '10000', 'freight' => null, 'insurance' => '100']);

        $this->assertSame(EstimateStatus::Estimated, $result->status);
        $this->assertSame('10100.00', $result->valuation['customs_value_zmw']);
        $this->assertStringContainsString('Freight not given: counted as ZMW 0.00', implode("\n", $result->assumptions));
    }

    public function test_blank_freight_and_insurance_are_incomplete_when_policy_is_incomplete(): void
    {
        config(['dutycalc.missing_freight_insurance' => 'incomplete']);

        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '10000', 'freight' => null, 'insurance' => null]);

        $this->assertSame(EstimateStatus::Incomplete, $result->status);
        $this->assertSame(['freight', 'insurance'], $this->missingItems($result));
        $this->assertSame([], $result->lineItems);
    }

    // --- quantity --------------------------------------------------------

    public function test_a_specific_duty_item_without_quantity_is_incomplete_and_saved(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0003', 'declaredValue' => '5000']);

        $this->assertSame(EstimateStatus::Incomplete, $result->status);
        $this->assertSame(['quantity'], $this->missingItems($result));
        $this->assertMissingMentions($result, 'kg');
        $this->assertNull($result->total);
        $this->assertSame(EstimateStatus::Incomplete, Calculation::findOrFail($result->estimateId)->status);
    }

    public function test_a_zero_quantity_is_incomplete_when_a_rule_needs_it(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0004', 'declaredValue' => '5000', 'quantity' => '0']);

        $this->assertSame(EstimateStatus::Incomplete, $result->status);
        $this->assertMissingMentions($result, 'more than 0');
    }

    public function test_quantity_is_not_asked_for_when_every_rule_is_ad_valorem(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Estimated, $result->status);
        $this->assertNull($result->valuation['quantity']);
    }

    public function test_required_inputs_lists_quantity_only_for_items_whose_rules_need_it(): void
    {
        $service = $this->app->make(EstimateService::class);

        $this->assertSame(['quantity' => 'kg'], $service->requiredInputs(CatalogueItem::find('CAT-0003')));
        $this->assertSame([], $service->requiredInputs(CatalogueItem::find('CAT-0001')));
    }

    // --- unsupported -----------------------------------------------------

    public function test_an_unknown_item_is_unsupported_and_still_saved(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-9999', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertSame(['catalogue_item'], $this->missingItems($result));
        $this->assertMissingMentions($result, 'CAT-9999');

        $calc = Calculation::findOrFail($result->estimateId);
        $this->assertNull($calc->catalogue_item_id);
        $this->assertSame('CAT-9999', $calc->inputs['catalogue_item_id']);
        $this->assertCount(0, $calc->breakdowns);
    }

    public function test_an_inactive_item_is_unsupported(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0099', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertMissingMentions($result, 'no longer supported');
    }

    public function test_an_unsupported_route_is_named(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100', 'tradeRouteId' => $this->mwami->id]);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertSame(['trade_route'], $this->missingItems($result));
        $this->assertMissingMentions($result, 'Malawi to Zambia via Mwami');
        $this->assertSame($this->mwami->id, Calculation::findOrFail($result->estimateId)->trade_route_id);
    }

    public function test_an_unknown_route_is_unsupported(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100', 'tradeRouteId' => 999999]);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertSame(['trade_route'], $this->missingItems($result));
        $this->assertNull(Calculation::findOrFail($result->estimateId)->trade_route_id);
    }

    public function test_every_unsupported_reason_is_listed_together(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-9999', 'declaredValue' => '100', 'currency' => 'EUR', 'tradeRouteId' => $this->mwami->id]);

        $this->assertSame(['catalogue_item', 'trade_route', 'exchange_rate'], $this->missingItems($result));
    }

    public function test_unsupported_wins_over_incomplete(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0003', 'declaredValue' => '100', 'tradeRouteId' => $this->mwami->id]);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertSame(['trade_route'], $this->missingItems($result));
    }

    public function test_a_line_no_longer_valid_is_unsupported(): void
    {
        $this->lines['61091000']->update(['valid_to' => '2026-11-19']);

        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertMissingMentions($result, 'HS 61091000');
    }

    public function test_an_item_whose_rules_have_all_expired_is_unsupported(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-05 10:00', 'Africa/Lusaka'));
        ExchangeRate::query()->delete();

        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100']);

        // Duty is open-ended, but VAT ended on 2026-12-31 with no new rate: never estimate without it.
        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertSame(['tariff_rule'], array_unique($this->missingItems($result)));
        $this->assertMissingMentions($result, 'VAT');
        $this->assertMissingMentions($result, '2026-12-31');
    }

    public function test_a_rule_that_ended_with_no_successor_is_unsupported_even_if_not_required(): void
    {
        // Excise is not in required_tax_types, but it existed and stopped without a new rate.
        $this->ruleFor('22021000', 'excise')->update(['effective_to' => '2026-11-19']);

        $result = $this->estimate(['catalogueItemId' => 'CAT-0005', 'declaredValue' => '8000']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertMissingMentions($result, 'Excise duty rate ended on 2026-11-19');
    }

    public function test_a_line_missing_a_required_charge_is_unsupported(): void
    {
        $this->ruleFor('85044000', 'vat')->delete();

        $result = $this->estimate(['catalogueItemId' => 'CAT-0006', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertMissingMentions($result, 'No VAT rule in force');
    }

    public function test_a_line_with_no_rules_at_all_is_unsupported_naming_the_item(): void
    {
        $line = $this->fixtureLine('63026000', 'u', 'Towel', 'CAT-0010', []);

        $result = $this->estimate(['catalogueItemId' => 'CAT-0010', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertMissingMentions($result, 'Towel');
        $this->assertSame($line->id, Calculation::findOrFail($result->estimateId)->tariff_line_id);
    }

    public function test_a_base_component_with_no_rule_in_force_is_unsupported(): void
    {
        // Remove excise entirely: soft-drink VAT still lists excise in its base.
        $this->ruleFor('22021000', 'excise')->delete();

        $result = $this->estimate(['catalogueItemId' => 'CAT-0005', 'declaredValue' => '8000']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertMissingMentions($result, 'VAT base needs Excise duty');
    }

    public function test_a_specific_amount_in_a_foreign_currency_is_unsupported(): void
    {
        $this->ruleFor('10063000', 'customs_duty')->update(['specific_currency' => 'USD']);

        $result = $this->estimate(['catalogueItemId' => 'CAT-0003', 'declaredValue' => '5000', 'quantity' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertMissingMentions($result, 'USD');
    }

    public function test_origin_specific_preference_rules_are_ignored(): void
    {
        // A 0% preferential duty for Tanzanian goods: out of scope, so the standard 25% applies.
        $this->fixtureRule($this->lines['61091000'], ['tax_type' => 'customs_duty', 'sequence' => 1,
            'method' => 'ad_valorem', 'rate' => '0', 'origin_country' => 'TZ']);

        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '10000', 'freight' => '500', 'insurance' => '100']);

        $this->assertSame('2650.00', $this->amounts($result)['customs_duty']);
    }

    // --- dates -----------------------------------------------------------

    public function test_a_rule_starting_tomorrow_is_not_used_today_but_is_tomorrow(): void
    {
        $this->ruleFor('61091000', 'customs_duty')->update(['effective_to' => '2026-11-20']);
        $this->fixtureRule($this->lines['61091000'], ['tax_type' => 'customs_duty', 'sequence' => 1, 'method' => 'ad_valorem',
            'rate' => '0.40', 'effective_from' => '2026-11-21']);
        $input = ['catalogueItemId' => 'CAT-0001', 'declaredValue' => '10000', 'freight' => '500', 'insurance' => '100'];

        $this->assertSame('2650.00', $this->amounts($this->estimate($input))['customs_duty']);

        Carbon::setTestNow(Carbon::parse('2026-11-21 00:00', 'Africa/Lusaka'));
        $tomorrow = $this->estimate($input);
        $this->assertSame('4240.00', $this->amounts($tomorrow)['customs_duty']);   // 40% x 10,600.00
        $this->assertSame('2026-11-21', $tomorrow->tariffEffectiveDate);
    }

    public function test_vat_ending_31_december_is_used_at_2359_lusaka_and_not_at_0001(): void
    {
        $this->fixtureRule($this->lines['61091000'], ['tax_type' => 'vat', 'sequence' => 3, 'method' => 'ad_valorem',
            'rate' => '0.165', 'base_components' => ['customs_value', 'customs_duty'], 'effective_from' => '2027-01-01']);
        $input = ['catalogueItemId' => 'CAT-0001', 'declaredValue' => '10000', 'freight' => '500', 'insurance' => '100'];

        Carbon::setTestNow(Carbon::parse('2026-12-31 23:59', 'Africa/Lusaka'));
        $l1 = $this->estimate($input);
        $this->assertSame('2120.00', $this->amounts($l1)['vat']);
        $this->assertSame('4770.00', $l1->total);

        Carbon::setTestNow(Carbon::parse('2027-01-01 00:01', 'Africa/Lusaka'));
        $l2 = $this->estimate($input);
        $this->assertSame('2186.25', $this->amounts($l2)['vat']);
        $this->assertSame('4836.25', $l2->total);
        $this->assertSame('2027-01-01', $l2->calculatedOn);

        // 22:30 UTC on 31 Dec is already 1 Jan in Lusaka.
        Carbon::setTestNow(Carbon::parse('2026-12-31 22:30', 'UTC'));
        $this->assertSame('2186.25', $this->amounts($this->estimate($input))['vat']);
    }

    // --- input guards ----------------------------------------------------

    public static function invalidRequests(): array
    {
        return [
            'float-style exponent' => [['declaredValue' => '1e3']],
            'negative value' => [['declaredValue' => '-5']],
            'zero value' => [['declaredValue' => '0']],
            'three decimals on money' => [['declaredValue' => '10.005']],
            'negative freight' => [['freight' => '-1']],
            'five decimals on quantity' => [['quantity' => '1.00001']],
            'lower-case currency' => [['currency' => 'usd']],
        ];
    }

    #[DataProvider('invalidRequests')]
    public function test_the_request_object_refuses_malformed_numbers(array $override): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EstimateRequest(...$override + [
            'catalogueItemId' => 'CAT-0001', 'tradeRouteId' => 1, 'declaredValue' => '100', 'currency' => 'ZMW',
        ]);
    }
}
