<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: the SAMPLE tariff data, and the admin account
     * (AdminUserSeeder; not in production).
     */
    public function run(): void
    {
        $this->call(SampleTariffSeeder::class);
        $this->call(AdminUserSeeder::class);
    }
}
