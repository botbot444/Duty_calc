<?php

namespace Tests\Feature\Http;

use App\Models\ExchangeRate;
use App\Services\Estimate\EstimateRequest;
use App\Services\Estimate\EstimateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

/**
 * The Blade screens: the trader page, the language switch and the result page.
 * (The screens' JavaScript calls the JSON endpoints tested in TextJourneyTest and PhotoJourneyTest.)
 */
class TraderPagesTest extends TestCase
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

    private function estimateId(array $input): string
    {
        return $this->app->make(EstimateService::class)->estimate(new EstimateRequest(...$input + [
            'tradeRouteId' => $this->kasumbalesa->id,
            'currency' => 'ZMW',
        ]))->estimateId;
    }

    // --- trader page -----------------------------------------------------

    public function test_the_trader_page_shows_the_search_screen_with_routes_and_endpoints(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('What are you taking out of Zambia?')
            ->assertSee('Type the name of the product')
            ->assertSee('capture="environment"', false)
            ->assertSee('<meta name="csrf-token"', false)
            ->assertSee('window.DutyCalc', false)
            ->assertSee('Kasumbalesa')
            ->assertSee('identifyText', false)
            ->assertSee('css/dutycalc.css', false)
            ->assertSee('js/dutycalc.js', false);
    }

    public function test_only_supported_routes_reach_the_page(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Mokambo')->assertDontSee('Mwami');
    }

    // --- language ------------------------------------------------------------

    public function test_the_language_switch_is_remembered_and_used_for_screen_text(): void
    {
        Lang::addLines(['*.Search' => 'Tafuta'], 'sw');

        $this->from('/')->get('/language/sw')->assertRedirect('/')->assertSessionHas('locale', 'sw');

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="sw">', false)
            ->assertSeeInOrder(['lang="sw"', 'aria-current="true"', 'Kiswahili'], false)
            ->assertSee('Tafuta');
    }

    public function test_an_unknown_language_is_refused(): void
    {
        $this->get('/language/fr')->assertNotFound();
    }

    public function test_the_language_switch_returns_to_the_same_screen_but_only_on_this_site(): void
    {
        $this->get('/language/bem?to='.urlencode('/#details'))->assertRedirect('/#details');
        $this->from('/')->get('/language/bem?to='.urlencode('//evil.example/x'))->assertRedirect('/');
        $this->from('/')->get('/language/bem?to='.urlencode('https://evil.example/'))->assertRedirect('/');
    }

    public function test_api_validation_messages_follow_the_chosen_language(): void
    {
        // Lang::addLines() splits keys on dots, so load the JSON line directly.
        app('translator')->setLoaded(['*' => ['*' => ['sw' => ['Type a word to search.' => 'Andika neno la kutafuta.']]]]);

        $this->withSession(['locale' => 'sw'])->postJson('/api/identify/text', ['description' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.description.0', 'Andika neno la kutafuta.');
    }

    // --- result page -----------------------------------------------------------

    public function test_the_result_page_shows_usd_first_with_zmw_beside_it(): void
    {
        $id = $this->estimateId(['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500']);

        $this->get("/estimates/{$id}")
            ->assertOk()
            ->assertSeeInOrder(['Estimated total to pay at the Zambian border', 'US$18.87', '≈ K500.00'])
            ->assertSee('Sugar · 500 kg · Zambia → DR Congo via Kasumbalesa')
            ->assertSee('Export duty')
            ->assertSee('5% or K0.10 per kg, whichever is higher · Charged on K10,000.00')
            ->assertSee('Value for duty (FOB)')
            ->assertSee('USD 1 = ZMW 26.50')
            ->assertSee('Test fixture source – not real rates, page 101')
            ->assertSee('Only charges in Zambia are shown. Charges in DR Congo are not included.')
            ->assertSee('This is an estimate only, not an official ZRA assessment.')
            ->assertSee('Estimate number: '.substr($id, 0, 8))
            ->assertSee('Change quantity');
    }

    public function test_the_same_url_still_answers_json_to_a_json_request(): void
    {
        $id = $this->estimateId(['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500']);

        $this->getJson("/estimates/{$id}")->assertOk()->assertJsonPath('display.total', '18.87');
    }

    public function test_with_no_usd_rate_the_result_page_shows_zmw(): void
    {
        ExchangeRate::query()->delete();
        $id = $this->estimateId(['catalogueItemId' => 'CAT-0004', 'declaredValue' => '10000', 'quantity' => '500']);

        $this->get("/estimates/{$id}")
            ->assertOk()
            ->assertSeeInOrder(['Estimated total to pay at the Zambian border', 'K500.00'])
            ->assertDontSee('US$')
            ->assertSee('No USD rate is in force today: amounts are shown in ZMW only.');
    }

    public function test_an_incomplete_estimate_says_what_is_missing_and_offers_to_add_it(): void
    {
        $id = $this->estimateId(['catalogueItemId' => 'CAT-0003', 'declaredValue' => '5000']);

        $this->get("/estimates/{$id}")
            ->assertOk()
            ->assertSee('We need more information')
            ->assertSee('Enter the quantity in kilograms (kg): the Export duty is charged per kg.')
            ->assertSee('Add the missing details')
            ->assertDontSee('Estimated total to pay');
    }

    public function test_an_unsupported_route_offers_another_route(): void
    {
        $id = $this->estimateId(['catalogueItemId' => 'CAT-0001', 'declaredValue' => '100', 'tradeRouteId' => $this->mokambo->id]);

        $this->get("/estimates/{$id}")
            ->assertOk()
            ->assertSee('We cannot estimate this')
            ->assertSee('The route Zambia to DR Congo via Mokambo is not supported yet.')
            ->assertSee('Choose another route');
    }

    public function test_a_product_with_no_rate_points_to_a_clearing_agent(): void
    {
        $id = $this->estimateId(['catalogueItemId' => 'CAT-0006', 'declaredValue' => '100']);

        $this->get("/estimates/{$id}")
            ->assertOk()
            ->assertSee('For this product, ask a clearing agent or ZRA.')
            ->assertSee('Try another product');
    }

    public function test_an_unknown_or_expired_estimate_page_is_a_friendly_404(): void
    {
        $this->get('/estimates/'.Str::uuid())
            ->assertNotFound()
            ->assertSee('This estimate has expired or does not exist.')
            ->assertSee('New estimate');
    }
}
