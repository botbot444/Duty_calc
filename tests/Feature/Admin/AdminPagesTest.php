<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

/**
 * The admin portal pages. They only read; changes go through the /api/admin endpoints
 * (tested in AdminDataTest and AdminRateChangesTest) from public/js/admin.js.
 */
class AdminPagesTest extends TestCase
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

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create(['email' => 'admin@example.org']));
    }

    public function test_guests_are_sent_to_the_sign_in_page(): void
    {
        foreach (['/admin', '/admin/lines', '/admin/lines/'.$this->lines['17019900']->id, '/admin/import', '/admin/rates',
            '/admin/catalogue', '/admin/reference', '/admin/log'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $this->get('/admin/login')->assertOk()->assertSee('Sign in')->assertSee('There is no sign-up');
    }

    public function test_a_signed_in_admin_skips_the_sign_in_page(): void
    {
        $this->admin()->get('/admin/login')->assertRedirect('/admin/lines');
        $this->get('/admin')->assertRedirect('/admin/lines');
    }

    public function test_tariff_lines_show_the_rate_in_force_and_lines_without_one(): void
    {
        $this->admin()->get('/admin/lines')
            ->assertOk()
            ->assertSee('Signed in as')->assertSee('admin@example.org')
            ->assertSee('1701.99.00')
            ->assertSee('5% or K0.10 per kg, whichever is higher')
            ->assertSee('Estimates refused')   // the phone charger fixture has no export duty
            ->assertSee('Rates in use come from sources nobody has checked yet');
    }

    public function test_the_lines_list_filters_and_searches(): void
    {
        $this->admin()->get('/admin/lines?show=attention')
            ->assertOk()->assertSee('8504.40.00')->assertDontSee('1701.99.00');

        $this->get('/admin/lines?q=sugar')->assertOk()->assertSee('1701.99.00')->assertDontSee('1006.30.00');
        $this->get('/admin/lines?q=1006')->assertOk()->assertSee('1006.30.00')->assertDontSee('1701.99.00');
    }

    public function test_a_line_with_a_rate_offers_replace_and_end(): void
    {
        $this->admin()->get('/admin/lines/'.$this->lines['17019900']->id)
            ->assertOk()
            ->assertSee('Rates on this line')
            ->assertSee('Replace this rate')
            ->assertSee('End this rate')
            ->assertSee('Not charged')   // the import fixtures' customs duty and VAT on the same line
            ->assertSee('Add export duty for a later period')
            ->assertSee(route('api.admin.rules.replace', $this->ruleFor('17019900', 'export_duty')), false);
    }

    public function test_after_a_replacement_only_the_latest_rate_can_be_replaced(): void
    {
        $old = $this->ruleFor('17019900', 'export_duty');
        $this->admin()->postJson("/api/admin/tariff-rules/{$old->id}/replace", [
            'effective_from' => '2026-12-01', 'rate' => '0.06', 'tariff_source_id' => $this->fixtureSource->id, 'source_page' => 9,
        ])->assertCreated();
        $new = $this->ruleFor('17019900', 'export_duty');

        $this->get('/admin/lines/'.$this->lines['17019900']->id)
            ->assertOk()
            ->assertSee('Starts 1 Dec 2026')
            ->assertSee('#replace-'.$new->id, false)
            ->assertDontSee('#replace-'.$old->id, false);

        $this->get('/admin/log')->assertOk()->assertSee('Replaces rate #'.$old->id);
    }

    public function test_a_line_without_a_rate_asks_for_one(): void
    {
        $this->admin()->get('/admin/lines/'.$this->lines['85044000']->id)
            ->assertOk()
            ->assertSee('No export duty is in force today')
            ->assertSee('Add export duty')
            ->assertSee(route('api.admin.rules.store', $this->lines['85044000']), false);
    }

    public function test_the_other_pages_show_their_data(): void
    {
        $this->admin();

        $this->get('/admin/import')->assertOk()->assertSee('Check files');
        $this->get('/admin/rates')->assertOk()->assertSee('ZMW 26.50')->assertSee("Add this week's rate", false);
        $this->get('/admin/catalogue?item=CAT-0004')->assertOk()->assertSee('Sugar')->assertSee('Search words')->assertSee('Add product');
        $this->get('/admin/reference')->assertOk()->assertSee('Kasumbalesa')->assertSee('Not checked')->assertSee('Test fixture source');
        $this->get('/admin/reference?source='.$this->fixtureSource->id)->assertOk()->assertSee('Edit source');
    }

    public function test_the_change_log_lists_changes_and_filters_them(): void
    {
        $this->admin()->postJson("/api/admin/trade-routes/{$this->mokambo->id}/toggle", ['supported' => true])->assertOk();

        $this->get('/admin/log')->assertOk()->assertSee('Switched')->assertSee('Route')->assertSee('admin@example.org');
        $this->get('/admin/log?type=imports')->assertOk()->assertDontSee('Switched')->assertSee('No changes recorded');
        $this->get('/admin/log?from=2026-11-21')->assertOk()->assertDontSee('Switched');
    }
}
