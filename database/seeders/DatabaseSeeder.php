<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Admin users are created with
     * `php artisan make:admin` (phase B7), not here.
     */
    public function run(): void
    {
        $this->call(SampleTariffSeeder::class);
    }
}
