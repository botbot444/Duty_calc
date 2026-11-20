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
 * The export product: Zambian export duty for goods leaving through Kasumbalesa, shown in
 * USD with the ZMW amounts beside it. Expected amounts: docs/golden-cases.md, E1–E7.
 */
class ExportEstimateTest extends TestCase
{
    use RefreshDatabase;
    use TariffFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        config(['dutycalc.missing_freight_insurance' => 'zero']);
        $this->seedTariffFixtures();   // default config: export_duty only, display in USD
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function estimate(array $input): EstimateResult
    {
        return $this->app->make(EstimateService::class)->estimate(new EstimateRequest(...$input + [
            'tradeRouteId' => $this->kasumbalesa->id,
            'currency' => 'ZMW',
        ]));
    }

    private function assumptions(EstimateResult $result): string
    {
        return implode("\n", $result->assumptions);
    }

    // --- golden cases ----------------------------------------------------

    public static function exportCases(): array
    {
        return [
            'E1 sourced 0% export duty' => [
                ['catalogueItemId' => 'CAT-0001', 'declaredValue' => '10000.00'],
                'FOB', '10000.00', '0.00', '0.00',
            ],
            'E2 compound max, ad valorem side wins' => [
                ['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500'],
                'FOB', '10000.00', '500.00', '18.87',
            ],
            'E3 compound max, specific side wins' => [
                ['catalogueItemId' => 'CAT-0004', 'declaredValue' => '500', 'quantity' => '1000'],
                'FOB', '500.00', '100.00', '3.77',
            ],
            'E4 specific per kg, USD input' => [
                ['catalogueItemId' => 'CAT-0003', 'currency' => 'USD', 'declaredValue' => '400', 'quantity' => '2000'],
                'FOB', '10600.00', '400.00', '15.09',
            ],
            'E5 compound sum' => [
                ['catalogueItemId' => 'CAT-0008', 'declaredValue' => '6000', 'quantity' => '400'],
                'FOB', '6000.00', '140.00', '5.28',
            ],
            'E6 base of declared value + freight, insurance left out' => [
                ['catalogueItemId' => 'CAT-0005', 'declaredValue' => '8000', 'freight' => '400', 'insurance' => '100'],
                'declared_value+freight', '8400.00', '840.00', '31.70',
            ],
            'E7 USD input rounded before duty' => [
                ['catalogueItemId' => 'CAT-0004', 'currency' => 'USD', 'declaredValue' => '10.01', 'quantity' => '1'],
                'FOB', '265.27', '13.26', '0.50',
            ],
        ];
    }

    #[DataProvider('exportCases')]
    public function test_export_case(array $input, string $basis, string $valueForDuty, string $duty, string $usd): void
    {
        $result = $this->estimate($input);

        $this->assertSame(EstimateStatus::Estimated, $result->status, json_encode($result->missing));
        $this->assertSame($basis, $result->valuation['basis']);
        $this->assertSame($valueForDuty, $result->valuation['value_for_duty_zmw']);
        $this->assertCount(1, $result->lineItems, 'only export duty is charged');
        $this->assertSame('export_duty', $result->lineItems[0]->taxType->value);
        $this->assertSame($duty, $result->lineItems[0]->amount);
        $this->assertSame($usd, $result->lineItems[0]->amountDisplay);
        $this->assertSame($duty, $result->total);
        $this->assertSame($usd, $result->display['total']);
    }

    // --- shape -----------------------------------------------------------

    public function test_the_result_shows_usd_first_data_with_zmw_beside_it(): void
    {
        $data = $this->estimate(['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500'])->toArray();

        $this->assertSame('ZMW', $data['currency']);
        $this->assertSame('500.00', $data['total_estimated_charges']);
        $this->assertSame([
            'currency' => 'USD',
            'exchange_rate' => '26.50000000',
            'exchange_rate_date' => '2026-11-16',
            'exchange_rate_source' => 'Test rate',
            'total' => '18.87',
        ], $data['display']);

        $line = $data['line_items'][0];
        $this->assertSame('Export duty', $line['label']);
        $this->assertSame('500.00', $line['amount']);
        $this->assertSame('18.87', $line['amount_display']);
        $this->assertSame(['declared_value'], $line['base_components']);

        $this->assertSame(['iso2' => 'ZM', 'name' => 'Zambia'], ['iso2' => $data['route']['origin'], 'name' => $data['route']['origin_name']]);
        $this->assertSame('Kasumbalesa', $data['route']['border_post']);
        $this->assertSame('DR Congo', $data['route']['destination_name']);
        $this->assertSame('FOB', $data['valuation']['basis']);
        $this->assertSame('2.0.0', $data['engine_version']);
    }

    public function test_the_display_rate_is_copied_into_the_saved_estimate(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500']);

        $calc = Calculation::with('breakdowns')->findOrFail($result->estimateId);
        $this->assertSame('USD', $calc->display_currency);
        $this->assertSame('26.50000000', $calc->display_rate_value);
        $this->assertSame('18.87', $calc->total_display);
        $this->assertSame('18.87', $calc->breakdowns[0]->amount_display);
        $this->assertSame('FOB', $calc->valuation_basis);
        $this->assertSame('10000.00', $calc->value_for_duty_zmw);

        // A later rate never rewrites it.
        ExchangeRate::where('currency', 'USD')->update(['effective_to' => '2026-11-20']);
        ExchangeRate::create(['currency' => 'USD', 'rate_to_zmw' => '30', 'effective_from' => '2026-11-21', 'source' => 'Later rate']);
        Carbon::setTestNow(Carbon::parse('2026-11-22 10:00', 'Africa/Lusaka'));

        $this->assertEquals($result->toArray(), $this->app->make(EstimateService::class)->find($result->estimateId)->toArray());
    }

    // --- assumptions -----------------------------------------------------

    public function test_assumptions_say_only_zambian_charges_fob_and_the_usd_rate(): void
    {
        $text = $this->assumptions($this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100']));

        $this->assertStringContainsString('Only charges in Zambia are shown. Charges in DR Congo are not included.', $text);
        $this->assertStringContainsString('Charged on the value of the goods (FOB), converted to ZMW.', $text);
        $this->assertStringContainsString('USD amounts are converted from ZMW at USD 1 = ZMW 26.50000000 (Test rate, from 2026-11-16).', $text);
        $this->assertStringNotContainsString('CIF', $text);
    }

    public function test_freight_and_insurance_are_not_asked_for_when_no_rule_uses_them(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100', 'freight' => null, 'insurance' => null]);

        $this->assertSame(EstimateStatus::Estimated, $result->status);
        $this->assertStringNotContainsString('not given', $this->assumptions($result));

        config(['dutycalc.missing_freight_insurance' => 'incomplete']);
        $this->assertSame(EstimateStatus::Estimated, $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100'])->status);
    }

    public function test_only_the_inputs_a_rule_uses_are_asked_for(): void
    {
        // Soft drink: base is declared value + freight. Insurance is never asked for.
        $result = $this->estimate(['catalogueItemId' => 'CAT-0005', 'declaredValue' => '8000']);
        $this->assertStringContainsString('Freight not given: counted as ZMW 0.00.', $this->assumptions($result));
        $this->assertStringNotContainsString('Insurance not given', $this->assumptions($result));
        $this->assertStringContainsString('Charged on the value of the goods + freight, converted to ZMW.', $this->assumptions($result));

        config(['dutycalc.missing_freight_insurance' => 'incomplete']);
        $result = $this->estimate(['catalogueItemId' => 'CAT-0005', 'declaredValue' => '8000']);
        $this->assertSame(EstimateStatus::Incomplete, $result->status);
        $this->assertSame(['freight'], array_column($result->missing, 'item'));
    }

    public function test_the_form_learns_which_inputs_each_item_needs(): void
    {
        $service = $this->app->make(EstimateService::class);

        $this->assertSame(['quantity' => 'kg'], $service->requiredInputs(CatalogueItem::find('CAT-0004')));
        $this->assertSame([], $service->optionalInputs(CatalogueItem::find('CAT-0004')));
        $this->assertSame([], $service->requiredInputs(CatalogueItem::find('CAT-0005')));
        $this->assertSame(['freight'], $service->optionalInputs(CatalogueItem::find('CAT-0005')));
    }

    // --- import rules on the same line are ignored ------------------------

    public function test_import_charges_on_the_same_tariff_line_are_not_charged(): void
    {
        // The sugar line also has 40%/K5 customs duty and 16% VAT (import fixtures).
        $result = $this->estimate(['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500']);

        $this->assertSame(['export_duty'], array_map(fn ($l) => $l->taxType->value, $result->lineItems));
    }

    // --- unsupported -----------------------------------------------------

    public function test_a_line_with_no_export_duty_row_is_unsupported_never_zero(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0006', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertSame('No tax rates are in force for Phone charger (HS 85044000) on 2026-11-20.', $result->missing[0]['message']);
        $this->assertNull($result->total);
        $this->assertNull($result->display);
    }

    public function test_an_export_duty_that_ended_with_no_successor_is_unsupported(): void
    {
        $this->ruleFor('61091000', 'export_duty')->update(['effective_to' => '2026-11-19']);

        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100']);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertStringContainsString('Export duty rate ended on 2026-11-19', $result->missing[0]['message']);
    }

    public function test_an_unsupported_export_route_is_named(): void
    {
        $result = $this->estimate(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100', 'tradeRouteId' => $this->mokambo->id]);

        $this->assertSame(EstimateStatus::Unsupported, $result->status);
        $this->assertSame('The route Zambia to DR Congo via Mokambo is not supported yet.', $result->missing[0]['message']);
    }

    // --- display currency ------------------------------------------------

    public function test_with_no_usd_rate_the_estimate_is_still_given_in_zmw(): void
    {
        ExchangeRate::query()->delete();

        $result = $this->estimate(['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500']);

        $this->assertSame(EstimateStatus::Estimated, $result->status);
        $this->assertSame('500.00', $result->total);
        $this->assertNull($result->display);
        $this->assertNull($result->lineItems[0]->amountDisplay);
        $this->assertStringContainsString('No USD rate is in force today: amounts are shown in ZMW only.', $this->assumptions($result));
    }

    public function test_display_can_be_switched_off(): void
    {
        config(['dutycalc.display_currency' => null]);

        $result = $this->estimate(['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500']);

        $this->assertNull($result->display);
        $this->assertStringNotContainsString('USD', $this->assumptions($result));
    }
}
