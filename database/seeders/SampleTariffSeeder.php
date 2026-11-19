<?php

namespace Database\Seeders;

use App\Services\TariffData\TariffCsvImporter;
use Illuminate\Database\Seeder;

/**
 * Loads the clearly labelled SAMPLE data in database/data/sample. Not verified; for building
 * and testing only. Expects empty reference tables: run `php artisan migrate:fresh --seed`.
 */
class SampleTariffSeeder extends Seeder
{
    public function run(TariffCsvImporter $importer): void
    {
        $counts = $importer->importDirectory(database_path('data/sample'));

        foreach ($counts as $file => $count) {
            $this->command?->line(sprintf('  %-22s %d rows', $file, $count));
        }
        $this->command?->warn('  SAMPLE data loaded: not verified, do not use for real estimates.');
    }
}
