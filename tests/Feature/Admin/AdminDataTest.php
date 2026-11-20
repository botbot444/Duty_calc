<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\CatalogueItem;
use App\Models\ProductAlias;
use App\Models\TariffRule;
use App\Models\TariffSource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TariffFixtures;
use Tests\TestCase;

/**
 * Sources, CSV import, catalogue, routes and the change log.
 */
class AdminDataTest extends TestCase
{
    use RefreshDatabase;
    use TariffFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Africa/Lusaka'));
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return list<UploadedFile> the sample CSVs, optionally with one file's content changed */
    private function sampleFiles(array $replace = []): array
    {
        $files = [];
        foreach (glob(database_path('data/sample/*.csv')) as $path) {
            $name = basename($path);
            $files[] = UploadedFile::fake()->createWithContent($name, $replace[$name] ?? file_get_contents($path));
        }

        return $files;
    }

    private function preview(array $files)
    {
        return $this->post('/api/admin/imports', ['files' => $files], ['Accept' => 'application/json']);
    }

    // --- CSV import ----------------------------------------------------------

    public function test_preview_validates_everything_and_saves_nothing(): void
    {
        $counts = $this->preview($this->sampleFiles())->assertOk()->assertJsonPath('valid', true)->json('counts');

        $this->assertSame(7, $counts['tariff_rules.csv']);   // file names contain dots, so no assertJsonPath
        $this->assertSame(30, $counts['product_aliases.csv']);

        $this->assertSame(0, TariffRule::count());
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_confirm_imports_the_previewed_files_and_logs_it(): void
    {
        $token = $this->preview($this->sampleFiles())->json('token');

        $counts = $this->postJson("/api/admin/imports/{$token}/confirm")->assertOk()->json('counts');
        $this->assertSame(7, $counts['tariff_lines.csv']);

        $this->assertSame(7, TariffRule::count());
        $log = AdminAuditLog::sole();
        $this->assertSame('import', $log->action);
        $this->assertSame($token, $log->record_id);
        $this->assertSame(8, $log->after['files']['catalogue_items.csv']);
        $this->assertSame([], Storage::disk('local')->allFiles('admin-imports'), 'uploaded CSVs are removed');
    }

    public function test_a_preview_token_works_once(): void
    {
        $token = $this->preview($this->sampleFiles())->json('token');

        $this->postJson("/api/admin/imports/{$token}/confirm")->assertOk();
        $this->postJson("/api/admin/imports/{$token}/confirm")->assertNotFound();
        $this->postJson('/api/admin/imports/not-a-token/confirm')->assertNotFound();
    }

    public function test_import_rejects_bad_rows_with_file_and_row_numbers(): void
    {
        $rules = file_get_contents(database_path('data/sample/tariff_rules.csv'));
        $bad = $rules.'61091000,stamp_duty,2,ad_valorem,0.05,,,,customs_value,,2026-01-01,,"SAMPLE – not verified, for testing only",1'."\n";

        $this->preview($this->sampleFiles(['tariff_rules.csv' => $bad]))
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('errors.0.file', 'tariff_rules.csv')
            ->assertJsonPath('errors.0.row', 9);

        $this->assertSame(0, TariffSource::count());
        $this->assertSame([], Storage::disk('local')->allFiles('admin-imports'));
    }

    public function test_import_refuses_unknown_file_names(): void
    {
        $this->preview([UploadedFile::fake()->createWithContent('rates.csv', "a,b\n1,2\n")])
            ->assertStatus(422)->assertJsonValidationErrors('files.0');
    }

    // --- sources -------------------------------------------------------------

    public function test_a_source_in_use_cannot_be_deleted(): void
    {
        $this->seedTariffFixtures();

        $this->deleteJson('/api/admin/tariff-sources/'.$this->fixtureSource->id)
            ->assertStatus(409)
            ->assertJsonPath('tariff_lines', 6);

        $this->assertNotNull($this->fixtureSource->fresh());
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_an_unused_source_can_be_created_corrected_and_deleted_with_a_log_each_time(): void
    {
        $id = $this->postJson('/api/admin/tariff-sources', ['name' => 'ZRA Customs Tariff Book', 'edition' => '2026', 'retrieved_on' => '2026-11-20'])
            ->assertCreated()->json('source.id');
        $this->postJson('/api/admin/tariff-sources', ['name' => 'ZRA Customs Tariff Book', 'edition' => '2026'])
            ->assertStatus(422);   // same name and edition

        $this->patchJson("/api/admin/tariff-sources/{$id}", ['verified_by' => 'Second checker'])->assertOk()->assertJsonPath('source.verified_by', 'Second checker');
        $this->deleteJson("/api/admin/tariff-sources/{$id}")->assertOk();

        $this->assertSame(['create', 'update', 'delete'], AdminAuditLog::orderBy('id')->pluck('action')->all());
        $this->assertSame('Second checker', AdminAuditLog::where('action', 'delete')->sole()->before['verified_by']);
    }

    // --- catalogue -------------------------------------------------------------

    public function test_catalogue_items_are_created_through_the_validator_and_switched_off_not_deleted(): void
    {
        $this->seedTariffFixtures();

        $this->postJson('/api/admin/catalogue-items', ['id' => 'CAT-0050', 'hs_code' => '61091000', 'display_name' => 'Cotton polo shirt'])
            ->assertCreated()->assertJsonPath('item.tariff_line.hs_code', '61091000');
        $this->postJson('/api/admin/catalogue-items', ['id' => 'CAT-0051', 'hs_code' => '99999999', 'display_name' => 'Unknown line'])
            ->assertStatus(422)->assertJsonValidationErrors('row');
        $this->postJson('/api/admin/catalogue-items', ['id' => 'ITEM-1', 'hs_code' => '61091000', 'display_name' => 'Bad id'])
            ->assertStatus(422);

        $this->patchJson('/api/admin/catalogue-items/CAT-0050', ['active' => false])->assertOk()->assertJsonPath('item.active', false);
        $this->deleteJson('/api/admin/catalogue-items/CAT-0050')->assertStatus(405);

        $this->assertFalse(CatalogueItem::find('CAT-0050')->active);
        $this->assertSame(['create', 'toggle'], AdminAuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_aliases_are_added_checked_and_removed(): void
    {
        $this->seedTariffFixtures();

        $aliasId = $this->postJson('/api/admin/catalogue-items/CAT-0004/aliases', ['alias' => 'sukari', 'language' => 'sw'])->assertCreated()->json('alias.id');
        $this->postJson('/api/admin/catalogue-items/CAT-0004/aliases', ['alias' => 'sukari', 'language' => 'sw'])->assertStatus(422);
        $this->postJson('/api/admin/catalogue-items/CAT-0004/aliases', ['alias' => 'sucre', 'language' => 'fr'])->assertStatus(422);

        $this->deleteJson("/api/admin/catalogue-items/CAT-0001/aliases/{$aliasId}")->assertNotFound();   // wrong item
        $this->deleteJson("/api/admin/catalogue-items/CAT-0004/aliases/{$aliasId}")->assertOk();

        $this->assertSame(0, ProductAlias::count());
        $this->assertSame(['create', 'delete'], AdminAuditLog::orderBy('id')->pluck('action')->all());
    }

    // --- routes and change log ----------------------------------------------

    public function test_a_route_can_be_switched_on_and_off_and_the_change_is_logged(): void
    {
        $this->seedTariffFixtures();

        $this->postJson("/api/admin/trade-routes/{$this->mwami->id}/toggle", ['supported' => true])->assertOk()->assertJsonPath('route.supported', true);
        $this->getJson('/api/routes')->assertJsonCount(3, 'routes');   // Nakonde, Kasumbalesa + Mwami

        $this->postJson("/api/admin/trade-routes/{$this->mwami->id}/toggle", ['supported' => false])->assertOk();
        $this->postJson("/api/admin/trade-routes/{$this->mwami->id}/toggle", [])->assertStatus(422);
        $this->getJson('/api/routes')->assertJsonCount(2, 'routes');

        $log = AdminAuditLog::orderBy('id')->first();
        $this->assertSame('toggle', $log->action);
        $this->assertSame('trade_routes', $log->table_name);
        $this->assertFalse($log->before['supported']);
        $this->assertTrue($log->after['supported']);
    }

    public function test_the_change_log_lists_newest_first_and_filters_by_table(): void
    {
        $this->seedTariffFixtures();
        $this->postJson("/api/admin/trade-routes/{$this->mwami->id}/toggle", ['supported' => true]);
        $this->patchJson('/api/admin/catalogue-items/CAT-0001', ['display_name' => 'Cotton T-shirt (any colour)']);

        $this->getJson('/api/admin/audit-logs')->assertOk()
            ->assertJsonPath('data.0.table_name', 'catalogue_items')
            ->assertJsonPath('data.0.action', 'update')
            ->assertJsonPath('data.1.table_name', 'trade_routes');

        $this->getJson('/api/admin/audit-logs?table=trade_routes')->assertJsonCount(1, 'data');
    }
}
