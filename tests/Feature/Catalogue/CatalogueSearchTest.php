<?php

namespace Tests\Feature\Catalogue;

use App\Enums\Language;
use App\Models\CatalogueItem;
use App\Models\ProductAlias;
use App\Services\Catalogue\CatalogueSearchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

/**
 * DatabaseTruncation, not RefreshDatabase: InnoDB FULLTEXT does not see rows that are not
 * committed yet, so a test wrapped in a transaction would find nothing through MATCH ... AGAINST.
 */
class CatalogueSearchTest extends TestCase
{
    use DatabaseTruncation;
    use TariffFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        $this->seedTariffFixtures();

        $this->aliases([
            'CAT-0001' => ['en' => ['t-shirt', 'tshirt', 't-shrit'], 'sw' => ['fulana'], 'bem' => ['ishati']],
            'CAT-0003' => ['en' => ['rice'], 'sw' => ['mchele'], 'bem' => ['umupunga']],
            'CAT-0004' => ['en' => ['sugar', 'suger'], 'sw' => ['sukari'], 'bem' => ['shuga']],
            'CAT-0005' => ['en' => ['soda', 'soft drink'], 'sw' => ['soda']],
            'CAT-0006' => ['en' => ['phone charger', 'charger'], 'sw' => ['chaja']],
            'CAT-0008' => ['en' => ['cooking oil'], 'sw' => ['mafuta ya kupikia'], 'bem' => ['amafuta ya kuipikila']],
            'CAT-0099' => ['en' => ['retired shirt']],   // inactive item
        ]);

        $tv = $this->fixtureLine('85287200', 'u', 'Colour television', 'CAT-0007', []);
        $this->aliases(['CAT-0007' => ['en' => ['tv', 'television'], 'sw' => ['televisheni']]]);
        CatalogueItem::create(['id' => 'CAT-0011', 'tariff_line_id' => $this->lines['10063000']->id, 'display_name' => 'Rice cooker (wrongly lined, test only)']);
        $this->aliases(['CAT-0011' => ['en' => ['rice cooker']]]);
        unset($tv);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        // DatabaseTruncation empties tables *before* each test, so rows from the last one stay
        // committed. Empty them afterwards too, or RefreshDatabase tests that run next would
        // start on top of these rows.
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    /** @param array<string, array<string, list<string>>> $map item => language => aliases */
    private function aliases(array $map): void
    {
        foreach ($map as $itemId => $byLanguage) {
            foreach ($byLanguage as $language => $aliases) {
                foreach ($aliases as $alias) {
                    ProductAlias::create(['catalogue_item_id' => $itemId, 'alias' => $alias, 'language' => $language]);
                }
            }
        }
    }

    private function search(string $q, ?Language $lang = null): array
    {
        return $this->app->make(CatalogueSearchService::class)->search($q, $lang);
    }

    private function ids(array $results): array
    {
        return array_column($results, 'id');
    }

    // --- service ---------------------------------------------------------

    public function test_an_english_alias_finds_the_item(): void
    {
        $results = $this->search('sugar');

        $this->assertSame('CAT-0004', $results[0]['id']);
        $this->assertSame('17019900', $results[0]['hs_code']);
        $this->assertSame('exact', $results[0]['match_type']);
    }

    public function test_a_swahili_alias_finds_the_item(): void
    {
        $results = $this->search('sukari', Language::Swahili);

        $this->assertSame(['CAT-0004'], $this->ids($results));
        $this->assertSame('sw', $results[0]['matched_language']);
    }

    public function test_a_bemba_alias_finds_the_item(): void
    {
        $this->assertSame(['CAT-0003'], $this->ids($this->search('umupunga', Language::Bemba)));
    }

    public function test_a_multi_word_swahili_phrase_finds_the_item(): void
    {
        $this->assertSame('CAT-0008', $this->search('mafuta ya kupikia')[0]['id']);
    }

    public function test_fulltext_matches_a_word_inside_a_longer_alias(): void
    {
        $results = $this->search('kupikia');

        $this->assertSame('CAT-0008', $results[0]['id']);
        $this->assertSame('text', $results[0]['match_type']);
    }

    public function test_a_misspelling_stored_as_an_alias_finds_the_item(): void
    {
        $this->assertSame('CAT-0004', $this->search('suger')[0]['id']);
        $this->assertSame('CAT-0001', $this->search('t-shrit')[0]['id']);
    }

    public function test_a_two_letter_alias_is_found_although_fulltext_ignores_it(): void
    {
        $results = $this->search('tv');

        $this->assertSame('CAT-0007', $results[0]['id']);
        $this->assertSame('exact', $results[0]['match_type']);
    }

    public function test_the_start_of_a_word_finds_the_item(): void
    {
        $results = $this->search('char');

        $this->assertSame('CAT-0006', $results[0]['id']);
        $this->assertSame('prefix', $results[0]['match_type']);
    }

    public function test_case_and_extra_spaces_are_ignored(): void
    {
        $this->assertSame('CAT-0006', $this->search('  Phone   CHARGER ')[0]['id']);
    }

    public function test_the_official_description_is_searched_too(): void
    {
        $this->fixtureLine('84509000', 'u', 'Washing machine part', 'CAT-0012', []);
        $this->lines['84509000']->update(['official_description' => 'Parts of household laundry machines']);

        $results = $this->search('laundry');

        $this->assertSame(['CAT-0012'], $this->ids($results));
        $this->assertSame('description', $results[0]['match_type']);
    }

    public function test_an_exact_alias_ranks_above_a_longer_alias(): void
    {
        $this->assertSame(['CAT-0003', 'CAT-0011'], $this->ids($this->search('rice')));
    }

    public function test_the_chosen_language_is_preferred_when_an_alias_is_shared(): void
    {
        $result = $this->search('soda', Language::Swahili)[0];

        $this->assertSame('CAT-0005', $result['id']);
        $this->assertSame('sw', $result['matched_language']);
    }

    public function test_other_languages_are_still_searched(): void
    {
        $this->assertSame('CAT-0004', $this->search('sukari', Language::Bemba)[0]['id']);
    }

    public function test_inactive_items_are_not_returned(): void
    {
        // "shirt" rightly still finds the active T-shirt; the inactive item must never appear.
        $ids = $this->ids($this->search('retired shirt'));

        $this->assertNotContains('CAT-0099', $ids);
        $this->assertContains('CAT-0001', $ids);
    }

    public function test_items_whose_tariff_line_has_ended_are_not_returned(): void
    {
        $this->lines['17019900']->update(['valid_to' => '2026-11-19']);

        $this->assertSame([], $this->search('sugar'));
    }

    public function test_no_results_is_an_empty_list(): void
    {
        $this->assertSame([], $this->search('xyzzy'));
        $this->assertSame([], $this->search('   '));
    }

    public function test_like_wildcards_in_the_query_are_taken_literally(): void
    {
        $this->assertSame([], $this->search('%'));
        $this->assertSame([], $this->search('_'));
    }

    public function test_at_most_ten_results_are_returned(): void
    {
        foreach (range(20, 31) as $n) {
            $this->fixtureLine('420221'.$n, 'u', "Handbag model {$n}", "CAT-00{$n}", []);
            $this->aliases(["CAT-00{$n}" => ['en' => ["handbag {$n}"]]]);
        }

        $this->assertCount(10, $this->search('handbag'));
    }

    // --- endpoint --------------------------------------------------------

    public function test_the_endpoint_returns_results_as_json(): void
    {
        $this->getJson('/api/catalogue/search?q=sukari&lang=sw')
            ->assertOk()
            ->assertJsonPath('query', 'sukari')
            ->assertJsonPath('language', 'sw')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('results.0.id', 'CAT-0004')
            ->assertJsonPath('results.0.name', 'Sugar')
            ->assertJsonPath('results.0.hs_code', '17019900')
            ->assertJsonPath('results.0.official_description', 'Sugar (test fixture)')
            ->assertJsonPath('results.0.stat_unit', 'kg');
    }

    public function test_the_endpoint_returns_an_empty_list_when_nothing_matches(): void
    {
        $this->getJson('/api/catalogue/search?q=xyzzy')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('results', []);
    }

    public function test_the_endpoint_rejects_a_missing_query_or_unknown_language_as_json(): void
    {
        $this->getJson('/api/catalogue/search')->assertStatus(422)->assertJsonValidationErrors('q');
        $this->getJson('/api/catalogue/search?q=%20%20')->assertStatus(422)->assertJsonValidationErrors('q');
        $this->getJson('/api/catalogue/search?q=rice&lang=fr')->assertStatus(422)->assertJsonValidationErrors('lang');
    }

    public function test_validation_errors_are_json_even_without_an_accept_header(): void
    {
        $this->get('/api/catalogue/search')->assertStatus(422)->assertJsonValidationErrors('q');
    }
}
