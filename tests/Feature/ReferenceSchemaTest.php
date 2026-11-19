<?php

namespace Tests\Feature;

use App\Enums\CombineMode;
use App\Enums\Language;
use App\Enums\RuleMethod;
use App\Enums\StatUnit;
use App\Enums\TaxType;
use App\Models\CatalogueItem;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\TariffLine;
use App\Models\TariffRule;
use App\Models\TariffSource;
use App\Models\TradeRoute;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceSchemaTest extends TestCase
{
    use RefreshDatabase;

    private TariffSource $source;

    private TariffLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = TariffSource::create(['name' => 'SAMPLE – not verified, for testing only', 'edition' => 'test']);
        $this->line = TariffLine::create([
            'hs_code' => '62034200',
            'official_description' => 'Trousers of cotton (illustrative)',
            'stat_unit' => 'u',
            'valid_from' => '2026-01-01',
            'tariff_source_id' => $this->source->id,
            'source_page' => 412,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rule(array $overrides = []): TariffRule
    {
        return TariffRule::create(array_merge([
            'tariff_line_id' => $this->line->id,
            'tax_type' => TaxType::CustomsDuty,
            'sequence' => 1,
            'method' => RuleMethod::AdValorem,
            'rate' => '0.25',
            'base_components' => ['customs_value'],
            'effective_from' => '2026-01-01',
            'tariff_source_id' => $this->source->id,
            'source_page' => 412,
        ], $overrides));
    }

    public function test_catalogue_item_uses_a_string_key_and_links_to_its_line_and_aliases(): void
    {
        $item = CatalogueItem::create([
            'id' => 'CAT-0012',
            'tariff_line_id' => $this->line->id,
            'display_name' => 'Cotton trousers',
        ]);
        $item->aliases()->create(['alias' => 'jeans', 'language' => 'en']);
        $item->aliases()->create(['alias' => 'suruali', 'language' => 'sw']);

        $found = CatalogueItem::with('tariffLine.source', 'aliases')->find('CAT-0012');

        $this->assertSame('CAT-0012', $found->getKey());
        $this->assertTrue($found->active);
        $this->assertSame('62034200', $found->tariffLine->hs_code);
        $this->assertSame(StatUnit::Unit, $found->tariffLine->stat_unit);
        $this->assertSame($this->source->id, $found->tariffLine->source->id);
        $this->assertEqualsCanonicalizing([Language::English, Language::Swahili], $found->aliases->pluck('language')->all());
    }

    public function test_money_and_rate_columns_come_back_as_fixed_scale_strings_not_floats(): void
    {
        $rule = $this->rule([
            'method' => RuleMethod::Compound,
            'combine' => CombineMode::Max,
            'specific_amount' => '5',
            'specific_currency' => 'ZMW',
        ])->fresh();

        $this->assertSame('0.250000', $rule->rate);
        $this->assertSame('5.0000', $rule->specific_amount);
        $this->assertSame(CombineMode::Max, $rule->combine);
        $this->assertSame(['customs_value'], $rule->base_components);
        $this->assertTrue($rule->needsQuantity());

        $usd = ExchangeRate::create([
            'currency' => 'USD', 'rate_to_zmw' => '26.12345678', 'effective_from' => '2026-11-16', 'source' => 'Bank of Zambia',
        ])->fresh();
        $this->assertSame('26.12345678', $usd->rate_to_zmw);
    }

    public function test_a_second_standard_rate_rule_for_the_same_line_tax_and_date_is_rejected(): void
    {
        $this->rule();

        $this->expectException(QueryException::class);
        $this->rule(['rate' => '0.40']);   // origin_country null both times
    }

    public function test_an_origin_specific_rule_can_sit_beside_the_standard_rule(): void
    {
        $this->rule();
        $this->rule(['origin_country' => 'TZ', 'rate' => '0.00']);

        $this->assertSame(2, TariffRule::count());
        $this->assertSame(1, TariffRule::forOrigin(null)->count());
        $this->assertSame(2, TariffRule::forOrigin('TZ')->count());
    }

    public function test_a_rule_ending_31_december_is_in_force_until_midnight_lusaka_time(): void
    {
        $this->rule([
            'tax_type' => TaxType::Vat, 'sequence' => 2, 'rate' => '0.16',
            'base_components' => ['customs_value', 'customs_duty'],
            'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31',
        ]);
        $this->rule([
            'tax_type' => TaxType::Vat, 'sequence' => 2, 'rate' => '0.165',
            'base_components' => ['customs_value', 'customs_duty'],
            'effective_from' => '2027-01-01',
        ]);

        $vatRateAt = function (string $moment, string $tz): string {
            Carbon::setTestNow(Carbon::parse($moment, $tz));

            return TariffRule::inForce()->where('tax_type', 'vat')->sole()->rate;
        };

        $this->assertSame('0.160000', $vatRateAt('2026-12-31 23:59:00', 'Africa/Lusaka'));
        $this->assertSame('0.165000', $vatRateAt('2027-01-01 00:01:00', 'Africa/Lusaka'));
        // 22:30 UTC on 31 Dec is already 00:30 on 1 Jan in Lusaka (UTC+2).
        $this->assertSame('0.165000', $vatRateAt('2026-12-31 22:30:00', 'UTC'));
    }

    public function test_is_in_force_matches_the_query_scope(): void
    {
        $rule = $this->rule(['effective_from' => '2026-12-01', 'effective_to' => '2026-12-31']);

        Carbon::setTestNow(Carbon::parse('2026-11-30 23:59', 'Africa/Lusaka'));
        $this->assertFalse($rule->isInForce());
        $this->assertSame(0, TariffRule::inForce()->count());

        Carbon::setTestNow(Carbon::parse('2026-12-01 00:00', 'Africa/Lusaka'));
        $this->assertTrue($rule->isInForce());
        $this->assertSame(1, TariffRule::inForce()->count());
    }

    public function test_tariff_lines_use_valid_from_and_valid_to_for_in_force(): void
    {
        $this->line->update(['valid_to' => '2026-06-30']);

        Carbon::setTestNow(Carbon::parse('2026-06-30 12:00', 'Africa/Lusaka'));
        $this->assertSame(1, TariffLine::inForce()->count());

        Carbon::setTestNow(Carbon::parse('2026-07-01 12:00', 'Africa/Lusaka'));
        $this->assertSame(0, TariffLine::inForce()->count());
    }

    public function test_a_source_in_use_cannot_be_deleted(): void
    {
        $this->assertTrue($this->source->isInUse());

        $this->expectException(QueryException::class);
        $this->source->delete();
    }

    public function test_trade_routes_link_two_countries(): void
    {
        Country::create(['iso2' => 'TZ', 'name' => 'Tanzania', 'is_origin' => true]);
        Country::create(['iso2' => 'ZM', 'name' => 'Zambia', 'is_destination' => true]);
        TradeRoute::create(['origin_iso2' => 'TZ', 'destination_iso2' => 'ZM', 'border_post' => 'Nakonde', 'supported' => true]);
        TradeRoute::create(['origin_iso2' => 'TZ', 'destination_iso2' => 'ZM', 'border_post' => 'Other post']);

        $route = TradeRoute::supported()->with('origin', 'destination')->sole();

        $this->assertSame('Tanzania', $route->origin->name);
        $this->assertSame('Zambia', $route->destination->name);
        $this->assertSame(1, Country::find('TZ')->outboundRoutes()->supported()->count());
    }

    public function test_alias_column_has_a_fulltext_index(): void
    {
        $indexes = collect(DB::select("SHOW INDEX FROM product_aliases WHERE Index_type = 'FULLTEXT'"));

        $this->assertSame(['alias'], $indexes->pluck('Column_name')->all());
    }
}
