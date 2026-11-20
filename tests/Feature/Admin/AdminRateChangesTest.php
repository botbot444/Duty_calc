<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\ExchangeRate;
use App\Models\TariffRule;
use App\Models\User;
use App\Services\Estimate\EstimateRequest;
use App\Services\Estimate\EstimateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

/**
 * Rates are never edited in place: replace = end the old row + add a new one; end = shorten.
 */
class AdminRateChangesTest extends TestCase
{
    use RefreshDatabase;
    use TariffFixtures;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        $this->chargeImportTaxes();   // rate changes are tested on the import fixture rules
        $this->seedTariffFixtures();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function replace(TariffRule $rule, array $input)
    {
        return $this->postJson("/api/admin/tariff-rules/{$rule->id}/replace", $input + [
            'tariff_source_id' => $this->fixtureSource->id, 'source_page' => 205,
        ]);
    }

    private function tshirtEstimate(): array
    {
        return $this->app->make(EstimateService::class)->estimate(new EstimateRequest(
            catalogueItemId: 'CAT-0001', tradeRouteId: $this->nakonde->id, declaredValue: '10000', currency: 'ZMW', freight: '500', insurance: '100',
        ))->toArray();
    }

    // --- replace -------------------------------------------------------------

    public function test_replace_ends_the_old_rule_and_creates_a_new_one(): void
    {
        $old = $this->ruleFor('61091000', 'customs_duty');

        $response = $this->replace($old, ['effective_from' => '2026-12-01', 'rate' => '0.30'])
            ->assertCreated()
            ->assertJsonPath('ended.id', $old->id)
            ->assertJsonPath('ended.effective_to', '2026-11-30')
            ->assertJsonPath('created.rate', '0.300000')
            ->assertJsonPath('created.effective_from', '2026-12-01')
            ->assertJsonPath('created.effective_to', null)
            ->assertJsonPath('created.method', 'ad_valorem')
            ->assertJsonPath('created.base_components', ['customs_value'])
            ->assertJsonPath('created.source.page', 205);

        $old->refresh();
        $this->assertSame('0.250000', $old->rate, 'the old rate is never edited');
        $this->assertSame('2026-11-30', $old->effective_to->toDateString());
        $this->assertNotSame($old->id, $response->json('created.id'));
    }

    public function test_replace_writes_end_and_replace_audit_rows(): void
    {
        $old = $this->ruleFor('61091000', 'customs_duty');
        $newId = $this->replace($old, ['effective_from' => '2026-12-01', 'rate' => '0.30'])->json('created.id');

        $logs = AdminAuditLog::orderBy('id')->get();
        $this->assertSame(['end', 'replace'], $logs->pluck('action')->all());
        $this->assertSame([(string) $old->id, (string) $newId], $logs->pluck('record_id')->all());
        $this->assertNull($logs[0]->before['effective_to']);
        $this->assertSame('2026-11-30', $logs[0]->after['effective_to']);
        $this->assertSame(['replaces_rule_id' => $old->id], $logs[1]->before);
        $this->assertSame($this->admin->id, $logs[1]->user_id);
    }

    public function test_estimates_use_the_old_rate_until_the_switch_and_the_new_one_after(): void
    {
        $before = $this->tshirtEstimate();
        $this->replace($this->ruleFor('61091000', 'customs_duty'), ['effective_from' => '2026-12-01', 'rate' => '0.30'])->assertCreated();

        $this->assertSame('2650.00', $this->tshirtEstimate()['line_items'][0]['amount']);   // still 25% today
        $this->assertEquals($before, $this->app->make(EstimateService::class)->find($before['estimate_id'])->toArray());

        Carbon::setTestNow(Carbon::parse('2026-12-01 08:00', 'Africa/Lusaka'));
        $this->assertSame('3180.00', $this->tshirtEstimate()['line_items'][0]['amount']);   // 30% x 10,600.00
    }

    public function test_a_replacement_can_change_the_method_with_its_own_fields(): void
    {
        $old = $this->ruleFor('61091000', 'customs_duty');

        $this->replace($old, ['effective_from' => '2026-12-01', 'method' => 'compound', 'rate' => '0.25',
            'specific_amount' => '3.00', 'specific_currency' => 'ZMW', 'combine' => 'max'])
            ->assertCreated()->assertJsonPath('created.method', 'compound')->assertJsonPath('created.combine', 'max');

        // A new method does not silently inherit the old method's fields.
        $other = $this->ruleFor('85044000', 'customs_duty');
        $this->replace($other, ['effective_from' => '2026-12-01', 'method' => 'specific'])
            ->assertStatus(422)->assertJsonValidationErrors('row');
    }

    public function test_a_replacement_goes_through_the_import_validator_and_rolls_back_on_failure(): void
    {
        $vat = $this->ruleFor('61091000', 'vat');

        $this->replace($vat, ['effective_from' => '2026-12-01', 'base_components' => ['customs_value', 'excise']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('row');

        $this->assertSame('2026-12-31', $vat->fresh()->effective_to->toDateString(), 'the old rule was not ended');
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_a_replacement_must_cite_a_source_and_page(): void
    {
        $old = $this->ruleFor('61091000', 'customs_duty');

        $this->postJson("/api/admin/tariff-rules/{$old->id}/replace", ['effective_from' => '2026-12-01', 'rate' => '0.30'])
            ->assertStatus(422)->assertJsonValidationErrors(['tariff_source_id', 'source_page']);
    }

    public function test_a_replacement_must_start_after_the_current_rule(): void
    {
        $old = $this->ruleFor('61091000', 'customs_duty');

        $this->replace($old, ['effective_from' => '2026-01-01', 'rate' => '0.30'])->assertStatus(422)->assertJsonValidationErrors('effective_from');
        $this->replace($old, ['effective_from' => '2025-06-01', 'rate' => '0.30'])->assertStatus(422);
        $this->assertNull($old->fresh()->effective_to);
    }

    public function test_a_rule_that_has_already_ended_cannot_be_replaced_after_a_gap(): void
    {
        $vat = $this->ruleFor('61091000', 'vat');   // ends 2026-12-31

        $this->replace($vat, ['effective_from' => '2027-03-01', 'rate' => '0.165'])->assertStatus(422)->assertJsonValidationErrors('effective_from');
        $this->replace($vat, ['effective_from' => '2027-01-01', 'rate' => '0.165'])->assertCreated();
    }

    public function test_there_is_no_route_that_edits_a_rule_in_place(): void
    {
        $rule = $this->ruleFor('61091000', 'customs_duty');

        foreach (['patchJson', 'putJson'] as $method) {
            $status = $this->{$method}("/api/admin/tariff-rules/{$rule->id}", ['rate' => '0.99'])->status();
            $this->assertContains($status, [404, 405]);
        }
        $this->deleteJson("/api/admin/tariff-rules/{$rule->id}")->assertStatus(404);
        $this->assertSame('0.250000', $rule->fresh()->rate);
    }

    // --- end ---------------------------------------------------------------

    public function test_a_rule_can_be_ended_and_the_end_can_only_move_earlier(): void
    {
        $vat = $this->ruleFor('61091000', 'vat');   // ends 2026-12-31

        $this->postJson("/api/admin/tariff-rules/{$vat->id}/end", ['effective_to' => '2027-06-30'])->assertStatus(422);
        $this->postJson("/api/admin/tariff-rules/{$vat->id}/end", ['effective_to' => '2025-12-31'])->assertStatus(422);
        $this->postJson("/api/admin/tariff-rules/{$vat->id}/end", ['effective_to' => '2026-11-30'])
            ->assertOk()->assertJsonPath('ended.effective_to', '2026-11-30');

        $this->assertSame('end', AdminAuditLog::sole()->action);
    }

    public function test_the_line_list_shows_rules_with_their_dates_and_sources(): void
    {
        $this->getJson('/api/admin/tariff-lines?hs=6109')
            ->assertOk()
            ->assertJsonPath('data.0.hs_code', '61091000')
            ->assertJsonPath('data.0.rules.0.tax_type', 'customs_duty')
            ->assertJsonPath('data.0.rules.0.in_force', true)
            ->assertJsonPath('data.0.rules.0.source.page', 101);

        $this->getJson('/api/admin/tariff-lines/'.$this->lines['61091000']->id)->assertOk()->assertJsonPath('catalogue_items.0.id', 'CAT-0001');
    }

    // --- exchange rates ----------------------------------------------------

    // --- add a rate -------------------------------------------------------------

    private function addRate(string $hs, array $input = [])
    {
        return $this->postJson("/api/admin/tariff-lines/{$this->lines[$hs]->id}/rules", $input + [
            'tax_type' => 'export_duty', 'method' => 'ad_valorem', 'rate' => '0', 'base_components' => ['declared_value'],
            'effective_from' => '2026-11-21', 'tariff_source_id' => $this->fixtureSource->id, 'source_page' => 310,
        ]);
    }

    public function test_a_rate_can_be_added_to_a_line_that_has_none(): void
    {
        // The phone charger fixture has no export duty row: "no duty" is recorded as a sourced 0% rule.
        $this->addRate('85044000')
            ->assertCreated()
            ->assertJsonPath('created.tax_type', 'export_duty')
            ->assertJsonPath('created.rate', '0.000000')
            ->assertJsonPath('created.source.page', 310);

        $this->assertSame(1, TariffRule::where('tariff_line_id', $this->lines['85044000']->id)->where('tax_type', 'export_duty')->count());
        $this->assertSame(['create'], AdminAuditLog::pluck('action')->all());
    }

    public function test_adding_a_rate_that_overlaps_one_in_force_is_refused(): void
    {
        $this->addRate('17019900')->assertStatus(422)->assertJsonValidationErrors('row');
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_an_added_rate_must_cite_its_page(): void
    {
        $this->addRate('85044000', ['source_page' => null])->assertStatus(422)->assertJsonValidationErrors('source_page');
    }

    public function test_after_a_rate_ends_a_new_one_is_added_rather_than_replaced(): void
    {
        $sugar = $this->ruleFor('17019900', 'export_duty');
        $this->postJson("/api/admin/tariff-rules/{$sugar->id}/end", ['effective_to' => '2026-11-30'])->assertOk();

        $this->replace($sugar, ['effective_from' => '2026-12-15'])
            ->assertStatus(422)->assertJsonValidationErrors('effective_from');
        $this->addRate('17019900', ['effective_from' => '2026-12-15', 'rate' => '0.06'])->assertCreated();
    }

    public function test_saving_an_exchange_rate_ends_the_previous_one(): void
    {
        $old = ExchangeRate::where('currency', 'USD')->sole();

        $this->postJson('/api/admin/exchange-rates', ['currency' => 'USD', 'rate_to_zmw' => '27.10', 'effective_from' => '2026-11-23', 'source' => 'Bank of Zambia'])
            ->assertCreated()
            ->assertJsonPath('created.rate_to_zmw', '27.10000000')
            ->assertJsonPath('created.entered_by', $this->admin->id);

        $this->assertSame('2026-11-22', $old->fresh()->effective_to->toDateString());
        $this->assertSame('26.50000000', $old->fresh()->rate_to_zmw);
        $this->assertSame(['end', 'create'], AdminAuditLog::orderBy('id')->pluck('action')->all());

        // Today still uses the old rate; from the 23rd the new one.
        $this->assertSame('26.50000000', ExchangeRate::where('currency', 'USD')->inForce()->sole()->rate_to_zmw);
        Carbon::setTestNow(Carbon::parse('2026-11-23 00:00', 'Africa/Lusaka'));
        $this->assertSame('27.10000000', ExchangeRate::where('currency', 'USD')->inForce()->sole()->rate_to_zmw);
    }

    public function test_a_new_currency_needs_no_previous_rate(): void
    {
        $this->postJson('/api/admin/exchange-rates', ['currency' => 'MWK', 'rate_to_zmw' => '0.0155', 'effective_from' => '2026-11-20', 'source' => 'BoZ'])
            ->assertCreated();
        $this->assertSame(['create'], AdminAuditLog::pluck('action')->all());
    }

    public function test_bad_exchange_rates_are_refused_and_nothing_is_ended(): void
    {
        $post = fn (array $o) => $this->postJson('/api/admin/exchange-rates', $o + ['currency' => 'USD', 'rate_to_zmw' => '27.10', 'effective_from' => '2026-11-23', 'source' => 'BoZ']);

        $post(['effective_from' => '2026-11-16'])->assertStatus(422)->assertJsonValidationErrors('effective_from');
        $post(['rate_to_zmw' => '0'])->assertStatus(422);
        $post(['rate_to_zmw' => '27,10'])->assertStatus(422)->assertJsonValidationErrors('rate_to_zmw');
        $post(['currency' => 'ZMW'])->assertStatus(422)->assertJsonValidationErrors('currency');

        $this->assertNull(ExchangeRate::where('currency', 'USD')->sole()->effective_to);
        $this->assertSame(0, AdminAuditLog::count());
    }
}
