<?php

namespace App\Services\TariffData;

use App\Models\CatalogueItem;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\ProductAlias;
use App\Models\TariffLine;
use App\Models\TariffRule;
use App\Models\TariffSource;
use App\Models\TradeRoute;
use Illuminate\Support\Facades\DB;

/**
 * Loads a folder of reference-data CSVs (see database/data/sample/README.md).
 *
 * Files are loaded in foreign-key order, each one validated and then written before the
 * next is read, all inside one transaction: any bad row rejects the whole import and
 * nothing is saved. Rows are only ever inserted, never updated, so a rate that is
 * already in use cannot be changed by an import.
 *
 * Used by SampleTariffSeeder now, and by VerifiedTariffSeeder and the admin import later.
 */
class TariffCsvImporter
{
    /** file => [columns, validator method, model] in the order they must load. */
    public const FILES = [
        'tariff_sources.csv' => [
            ['name', 'document_url', 'edition', 'published_on', 'retrieved_on', 'verified_by'],
            'sources', TariffSource::class,
        ],
        'countries.csv' => [
            ['iso2', 'name', 'is_origin', 'is_destination'],
            'countries', Country::class,
        ],
        'trade_routes.csv' => [
            ['origin_iso2', 'destination_iso2', 'border_post', 'supported', 'notes'],
            'tradeRoutes', TradeRoute::class,
        ],
        'tariff_lines.csv' => [
            ['hs_code', 'official_description', 'stat_unit', 'valid_from', 'valid_to', 'source_name', 'source_page'],
            'tariffLines', TariffLine::class,
        ],
        'catalogue_items.csv' => [
            ['id', 'hs_code', 'display_name', 'active'],
            'catalogueItems', CatalogueItem::class,
        ],
        'product_aliases.csv' => [
            ['catalogue_item_id', 'alias', 'language'],
            'productAliases', ProductAlias::class,
        ],
        'tariff_rules.csv' => [
            ['hs_code', 'tax_type', 'sequence', 'method', 'rate', 'specific_amount', 'specific_currency', 'combine',
                'base_components', 'origin_country', 'effective_from', 'effective_to', 'source_name', 'source_page'],
            'tariffRules', TariffRule::class,
        ],
        'exchange_rates.csv' => [
            ['currency', 'rate_to_zmw', 'effective_from', 'effective_to', 'source'],
            'exchangeRates', ExchangeRate::class,
        ],
    ];

    public function __construct(private readonly CsvReader $reader) {}

    /**
     * @param  bool  $dryRun  validate everything, then roll back (for the admin preview)
     * @return array<string, int> rows imported (or that would be imported) per file
     *
     * @throws TariffImportException
     */
    public function importDirectory(string $directory, bool $dryRun = false): array
    {
        $paths = [];
        foreach (array_keys(self::FILES) as $file) {
            $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) {
                $paths[$file] = $path;
            }
        }

        if ($paths === []) {
            throw TariffImportException::single(basename($directory), 0, 'no known CSV files in this folder');
        }

        return $this->import($paths, $dryRun);
    }

    /**
     * @param  array<string, string>  $paths  file name (a key of FILES) => path on disk
     * @return array<string, int>
     *
     * @throws TariffImportException
     */
    public function import(array $paths, bool $dryRun = false): array
    {
        $validator = new TariffDataValidator;   // fresh lookup caches for each import
        $counts = [];

        DB::beginTransaction();
        try {
            foreach (self::FILES as $file => [$columns, $method, $model]) {
                if (! isset($paths[$file])) {
                    continue;
                }

                $rows = $this->reader->read($paths[$file], $columns);
                $result = $validator->{$method}($rows, $file);
                if (! $result->passes()) {
                    throw new TariffImportException($result->errors);
                }

                foreach ($result->records as $attributes) {
                    $model::create($attributes);
                }
                $counts[$file] = count($result->records);
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $counts;
    }
}
