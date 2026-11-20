<?php

namespace Tests\Feature\TariffData;

use App\Enums\RuleMethod;
use App\Models\CatalogueItem;
use App\Models\ExchangeRate;
use App\Models\ProductAlias;
use App\Models\TariffLine;
use App\Models\TariffRule;
use App\Models\TariffSource;
use App\Models\TradeRoute;
use App\Services\TariffData\TariffCsvImporter;
use App\Services\TariffData\TariffImportException;
use Database\Seeders\SampleTariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TariffCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_SOURCE = 'SAMPLE – not verified, for testing only';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/tariff-import-'.uniqid());
        File::copyDirectory(database_path('data/sample'), $this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function importer(): TariffCsvImporter
    {
        return $this->app->make(TariffCsvImporter::class);
    }

    private function appendRow(string $file, string $line): void
    {
        File::append("{$this->dir}/{$file}", $line."\n");
    }

    private function assertNothingSaved(): void
    {
        $this->assertSame(0, TariffSource::count());
        $this->assertSame(0, TariffLine::count());
        $this->assertSame(0, TariffRule::count());
        $this->assertSame(0, ExchangeRate::count());
    }

    public function test_the_sample_seeder_loads_every_file(): void
    {
        $this->seed(SampleTariffSeeder::class);

        $this->assertSame(1, TariffSource::count());
        $this->assertSame(7, TariffLine::count());
        $this->assertSame(8, CatalogueItem::count());
        $this->assertSame(30, ProductAlias::count());
        $this->assertSame(7, TariffRule::count());   // one export duty row per line
        $this->assertSame(1, ExchangeRate::count());  // USD
        $this->assertSame(1, TradeRoute::supported()->count());
    }

    public function test_every_sample_line_and_rule_points_at_the_labelled_sample_source(): void
    {
        $this->seed(SampleTariffSeeder::class);

        $source = TariffSource::sole();
        $this->assertSame(self::SAMPLE_SOURCE, $source->name);
        $this->assertSame(0, TariffLine::where('tariff_source_id', '!=', $source->id)->count());
        $this->assertSame(0, TariffRule::where('tariff_source_id', '!=', $source->id)->count());
        $this->assertSame(0, TariffRule::whereNull('source_page')->count());
    }

    public function test_the_sample_covers_each_method_on_export_duty_and_a_non_fob_base(): void
    {
        $this->seed(SampleTariffSeeder::class);

        $duty = fn (string $hs) => TariffRule::whereHas('tariffLine', fn ($q) => $q->where('hs_code', $hs))
            ->where('tax_type', 'export_duty')->sole();

        $this->assertSame(['export_duty'], TariffRule::distinct()->pluck('tax_type')->map->value->all());
        $this->assertSame(RuleMethod::AdValorem, $duty('61091000')->method);
        $this->assertSame('0.000000', $duty('61091000')->rate, 'a sourced 0% row, not a missing one');
        $this->assertSame(RuleMethod::Specific, $duty('10063000')->method);
        $this->assertSame('max', $duty('17019900')->combine->value);
        $this->assertSame('sum', $duty('15079000')->combine->value);
        $this->assertSame(['declared_value', 'freight'], $duty('22021000')->base_components);

        $this->assertSame(['Kasumbalesa'], TradeRoute::supported()->pluck('border_post')->all());

        // Two catalogue items share the T-shirt line.
        $this->assertSame(
            CatalogueItem::find('CAT-0001')->tariff_line_id,
            CatalogueItem::find('CAT-0002')->tariff_line_id
        );
    }

    public function test_one_bad_row_in_the_last_file_rejects_the_whole_import(): void
    {
        $this->appendRow('exchange_rates.csv', 'ZMW,1,2026-11-16,,typo');

        try {
            $this->importer()->importDirectory($this->dir);
            $this->fail('Import should have been rejected');
        } catch (TariffImportException $e) {
            $this->assertSame('exchange_rates.csv', $e->errors[0]->file);
            $this->assertSame(3, $e->errors[0]->row);
        }

        $this->assertNothingSaved();
    }

    public function test_a_rule_row_without_a_page_number_rejects_the_import(): void
    {
        $this->appendRow('tariff_rules.csv', '61091000,excise,2,ad_valorem,0.05,,,,customs_value,,2026-01-01,,"'.self::SAMPLE_SOURCE.'",');

        $this->expectException(TariffImportException::class);
        $this->expectExceptionMessage('tariff_rules.csv row 9');
        try {
            $this->importer()->importDirectory($this->dir);
        } finally {
            $this->assertNothingSaved();
        }
    }

    public function test_a_dry_run_reports_counts_and_saves_nothing(): void
    {
        $counts = $this->importer()->importDirectory($this->dir, dryRun: true);

        $this->assertSame(7, $counts['tariff_rules.csv']);
        $this->assertNothingSaved();
    }

    public function test_importing_the_same_rows_twice_is_rejected_rather_than_edited(): void
    {
        $this->importer()->importDirectory($this->dir);
        $rate = TariffRule::first()->only(['id', 'rate', 'updated_at']);

        try {
            $this->importer()->importDirectory($this->dir);
            $this->fail('Second import should have been rejected');
        } catch (TariffImportException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }

        $this->assertEquals($rate, TariffRule::find($rate['id'])->only(['id', 'rate', 'updated_at']));
        $this->assertSame(7, TariffRule::count());
    }

    public function test_a_misspelt_column_header_is_rejected(): void
    {
        $path = "{$this->dir}/tariff_lines.csv";
        File::put($path, str_replace('source_page', 'source_pg', File::get($path)));

        $this->expectException(TariffImportException::class);
        $this->expectExceptionMessage('missing columns: source_page; unknown columns: source_pg');
        $this->importer()->importDirectory($this->dir);
    }

    public function test_a_row_with_the_wrong_number_of_cells_is_rejected(): void
    {
        $this->appendRow('countries.csv', 'KE,Kenya,1');

        $this->expectException(TariffImportException::class);
        $this->expectExceptionMessage('countries.csv row 4: expected 4 columns, found 3');
        $this->importer()->importDirectory($this->dir);
    }

    public function test_a_spreadsheet_byte_order_mark_is_ignored(): void
    {
        $path = "{$this->dir}/countries.csv";
        File::put($path, "\xEF\xBB\xBF".File::get($path));

        $counts = $this->importer()->importDirectory($this->dir, dryRun: true);

        $this->assertSame(2, $counts['countries.csv']);
    }
}
