<?php

namespace Tests\Feature\Http;

use App\Models\Calculation;
use App\Models\ProductAlias;
use Carbon\Carbon;
use Tests\Support\FullTextDatabase;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

/**
 * B5 gate: the whole text route through HTTP, carrying the session between requests:
 * search -> select -> estimate -> fetch the saved estimate.
 */
class TextJourneyTest extends TestCase
{
    use FullTextDatabase;   // the search step uses FULLTEXT
    use TariffFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        $this->seedTariffFixtures();
        ProductAlias::create(['catalogue_item_id' => 'CAT-0004', 'alias' => 'sukari', 'language' => 'sw']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_trader_searches_in_swahili_selects_estimates_and_fetches_the_result(): void
    {
        // 1. Search.
        $search = $this->getJson('/api/catalogue/search?q=sukari&lang=sw')->assertOk();
        $itemId = $search->json('results.0.id');
        $this->assertSame('CAT-0004', $itemId);

        // 2. Reference data for the form.
        $routeId = $this->getJson('/api/routes')->assertOk()->json('routes.0.id');
        $this->assertSame($this->nakonde->id, $routeId);
        $this->assertContains('USD', array_column($this->getJson('/api/currencies')->json('currencies'), 'code'));

        // 3. Confirm the item: the form learns it needs a quantity in kg.
        $this->postJson('/api/selection', ['catalogue_item_id' => $itemId])
            ->assertOk()
            ->assertJsonPath('selected.id', 'CAT-0004')
            ->assertJsonPath('required_inputs.quantity.unit', 'kg');

        // 4. Estimate (golden case G4: 40% or K5/kg, ad valorem wins).
        $estimate = $this->postJson('/api/estimates', [
            'catalogue_item_id' => $itemId,
            'trade_route_id' => $routeId,
            'declared_value' => '10000',
            'currency' => 'ZMW',
            'freight' => '0',
            'insurance' => '0',
            'quantity' => '500',
        ]);

        $estimate->assertCreated()
            ->assertJsonPath('status', 'estimated')
            ->assertJsonPath('total_estimated_charges', '6240.00')
            ->assertJsonPath('line_items.0.amount', '4000.00')
            ->assertJsonPath('line_items.1.amount', '2240.00');
        $id = $estimate->json('estimate_id');
        $estimate->assertHeader('Location', route('estimates.show', $id));

        // 5. Fetch the saved estimate: identical to what was returned.
        $this->getJson("/estimates/{$id}")
            ->assertOk()
            ->assertExactJson($estimate->json());

        // 6. Change the quantity on the same selection: the cost moves (demo step 3).
        $this->postJson('/api/estimates', [
            'catalogue_item_id' => $itemId, 'trade_route_id' => $routeId,
            'declared_value' => '2000', 'currency' => 'ZMW', 'freight' => '0', 'insurance' => '0', 'quantity' => '500',
        ])->assertCreated()->assertJsonPath('total_estimated_charges', '3220.00');

        $this->assertSame(2, Calculation::count());
    }
}
