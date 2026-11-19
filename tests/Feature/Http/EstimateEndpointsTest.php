<?php

namespace Tests\Feature\Http;

use App\Models\Calculation;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

class EstimateEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use TariffFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        $this->seedTariffFixtures();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function select(string $itemId): void
    {
        $this->postJson('/api/selection', ['catalogue_item_id' => $itemId])->assertOk();
    }

    private function estimate(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/estimates', $overrides + [
            'catalogue_item_id' => 'CAT-0001',
            'trade_route_id' => $this->nakonde->id,
            'declared_value' => '10000',
            'currency' => 'ZMW',
            'freight' => '500',
            'insurance' => '100',
        ]);
    }

    // --- selection -------------------------------------------------------

    public function test_selection_returns_the_item_and_only_the_inputs_its_rules_need(): void
    {
        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0003'])
            ->assertOk()
            ->assertJsonPath('selected.hs_code', '10063000')
            ->assertJsonPath('required_inputs.quantity', ['unit' => 'kg', 'unit_label' => 'kilograms']);

        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0001'])
            ->assertOk()
            ->assertJsonPath('required_inputs', []);
    }

    public function test_selection_refuses_unknown_and_inactive_items(): void
    {
        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-9999'])->assertStatus(422)->assertJsonValidationErrors('catalogue_item_id');
        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0099'])->assertStatus(422)->assertJsonValidationErrors('catalogue_item_id');
        $this->postJson('/api/selection', [])->assertStatus(422)->assertJsonValidationErrors('catalogue_item_id');
    }

    // --- confirmation is required -----------------------------------------

    public function test_an_estimate_for_an_item_that_was_never_confirmed_is_refused(): void
    {
        $this->estimate()
            ->assertStatus(422)
            ->assertJsonValidationErrors(['catalogue_item_id' => 'Please choose and confirm a product first.']);

        $this->assertSame(0, Calculation::count());
    }

    public function test_an_estimate_for_a_different_item_than_the_confirmed_one_is_refused(): void
    {
        $this->select('CAT-0001');

        $this->estimate(['catalogue_item_id' => 'CAT-0006'])->assertStatus(422)->assertJsonValidationErrors('catalogue_item_id');
        $this->estimate(['catalogue_item_id' => 'CAT-9999'])->assertStatus(422)->assertJsonValidationErrors('catalogue_item_id');
    }

    public function test_choosing_another_item_replaces_the_confirmation(): void
    {
        $this->select('CAT-0001');
        $this->select('CAT-0006');

        $this->estimate()->assertStatus(422);
        $this->estimate(['catalogue_item_id' => 'CAT-0006'])->assertCreated();
    }

    // --- unsupported and incomplete are saved and returned ----------------

    public function test_an_unsupported_route_is_saved_and_returned(): void
    {
        $this->select('CAT-0001');

        $response = $this->estimate(['trade_route_id' => $this->mwami->id])
            ->assertCreated()
            ->assertJsonPath('status', 'unsupported')
            ->assertJsonPath('total_estimated_charges', null)
            ->assertJsonPath('missing.0.item', 'trade_route')
            ->assertJsonPath('line_items', []);

        $this->getJson('/estimates/'.$response->json('estimate_id'))
            ->assertOk()
            ->assertJsonPath('status', 'unsupported');
    }

    public function test_an_unknown_route_is_saved_as_unsupported(): void
    {
        $this->select('CAT-0001');

        $this->estimate(['trade_route_id' => 999999])->assertCreated()->assertJsonPath('status', 'unsupported');
    }

    public function test_a_missing_quantity_is_saved_and_returned_as_incomplete(): void
    {
        $this->select('CAT-0003');

        $response = $this->estimate(['catalogue_item_id' => 'CAT-0003'])
            ->assertCreated()
            ->assertJsonPath('status', 'incomplete')
            ->assertJsonPath('missing.0.item', 'quantity');

        $this->assertSame('incomplete', Calculation::findOrFail($response->json('estimate_id'))->status->value);
    }

    // --- input validation --------------------------------------------------

    public static function badInputs(): array
    {
        return [
            'thousands separator' => [['declared_value' => '10,000'], 'declared_value'],
            'exponent' => [['declared_value' => '1e4'], 'declared_value'],
            'negative value' => [['declared_value' => '-5'], 'declared_value'],
            'zero value' => [['declared_value' => '0'], 'declared_value'],
            'three decimals' => [['declared_value' => '10.005'], 'declared_value'],
            'value as a JSON number' => [['declared_value' => 10000], 'declared_value'],
            'missing value' => [['declared_value' => null], 'declared_value'],
            'freight with three decimals' => [['freight' => '1.234'], 'freight'],
            'currency with no rate' => [['currency' => 'EUR'], 'currency'],
            'lower-case currency' => [['currency' => 'usd'], 'currency'],
            'zero quantity' => [['quantity' => '0'], 'quantity'],
            'quantity with five decimals' => [['quantity' => '1.00001'], 'quantity'],
            'route not a number' => [['trade_route_id' => 'nakonde'], 'trade_route_id'],
        ];
    }

    #[DataProvider('badInputs')]
    public function test_bad_inputs_are_refused_with_field_errors(array $override, string $field): void
    {
        $this->select('CAT-0001');

        $this->estimate($override)->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertSame(0, Calculation::count());
    }

    public function test_the_decimal_places_message_names_the_limit(): void
    {
        $this->select('CAT-0001');

        $this->estimate(['declared_value' => '10.005', 'quantity' => '1.00001'])
            ->assertJsonValidationErrors([
                'declared_value' => 'The value of the goods must be a number with at most 2 decimal places.',
                'quantity' => 'The quantity must be a number with at most 4 decimal places.',
            ]);
    }

    public function test_blank_freight_and_insurance_are_accepted(): void
    {
        $this->select('CAT-0001');

        $this->estimate(['freight' => null, 'insurance' => null])->assertCreated()->assertJsonPath('status', 'estimated');
    }

    // --- saved estimates ---------------------------------------------------

    public function test_an_unknown_estimate_id_is_404(): void
    {
        $this->getJson('/estimates/'.Str::uuid())->assertNotFound();
        $this->getJson('/estimates/not-a-uuid')->assertNotFound();
    }

    public function test_an_expired_estimate_is_404(): void
    {
        $this->select('CAT-0001');
        $id = $this->estimate()->json('estimate_id');

        Carbon::setTestNow(Carbon::parse('2027-02-19 10:00', 'Africa/Lusaka'));   // > 90 days later

        $this->getJson("/estimates/{$id}")->assertNotFound();
    }

    // --- reference data ----------------------------------------------------

    public function test_routes_lists_only_supported_routes(): void
    {
        $this->getJson('/api/routes')
            ->assertOk()
            ->assertJsonCount(1, 'routes')
            ->assertJsonPath('routes.0.border_post', 'Nakonde')
            ->assertJsonPath('routes.0.origin', ['iso2' => 'TZ', 'name' => 'Tanzania'])
            ->assertJsonPath('routes.0.destination', ['iso2' => 'ZM', 'name' => 'Zambia']);
    }

    public function test_currencies_are_zmw_plus_those_with_a_rate_in_force(): void
    {
        ExchangeRate::create(['currency' => 'EUR', 'rate_to_zmw' => '28', 'effective_from' => '2026-10-01',
            'effective_to' => '2026-10-31', 'source' => 'Old rate']);

        $currencies = $this->getJson('/api/currencies')->assertOk()->json('currencies');

        $this->assertSame(['ZMW', 'TZS', 'USD'], array_column($currencies, 'code'));
        $this->assertSame('1', $currencies[0]['rate_to_zmw']);
        $this->assertSame('26.50000000', $currencies[2]['rate_to_zmw']);
        $this->assertSame('2026-11-16', $currencies[2]['effective_from']);
    }

    // --- CSRF --------------------------------------------------------------

    public function test_posts_need_a_csrf_token(): void
    {
        // Laravel skips the CSRF check while running tests; switch that off for this test.
        $this->app['env'] = 'local';

        $this->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0001'])->assertStatus(419);
        $this->postJson('/api/estimates', [])->assertStatus(419);

        $this->withSession(['_token' => 'test-token'])
            ->postJson('/api/selection', ['catalogue_item_id' => 'CAT-0001'], ['X-CSRF-TOKEN' => 'test-token'])
            ->assertOk();
    }

    public function test_gets_do_not_need_a_csrf_token(): void
    {
        $this->app['env'] = 'local';

        $this->getJson('/api/routes')->assertOk();
        $this->getJson('/api/catalogue/search?q=rice')->assertOk();
    }

    // --- pruning -----------------------------------------------------------

    public function test_prune_deletes_only_expired_estimates_and_their_breakdowns(): void
    {
        $this->select('CAT-0001');
        $old = $this->estimate()->json('estimate_id');
        Carbon::setTestNow(Carbon::parse('2027-01-10 10:00', 'Africa/Lusaka'));
        ExchangeRate::query()->delete();
        $this->fixtureRule($this->lines['61091000'], ['tax_type' => 'vat', 'sequence' => 3, 'method' => 'ad_valorem',
            'rate' => '0.165', 'base_components' => ['customs_value', 'customs_duty'], 'effective_from' => '2027-01-01']);
        $recent = $this->estimate()->json('estimate_id');

        Carbon::setTestNow(Carbon::parse('2027-02-19 10:00', 'Africa/Lusaka'));
        $this->artisan('estimates:prune')->expectsOutputToContain('Deleted 1 expired estimate(s).')->assertSuccessful();

        $this->assertNull(Calculation::find($old));
        $this->assertNotNull(Calculation::find($recent));
        $this->assertDatabaseMissing('calculation_breakdowns', ['calculation_id' => $old]);
        $this->assertDatabaseHas('calculation_breakdowns', ['calculation_id' => $recent]);
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'estimates:prune'));

        $this->assertNotNull($event, 'estimates:prune is not scheduled');
        $this->assertSame('0 0 * * *', $event->expression);
    }
}
