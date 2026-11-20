<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the admin account. To change the password, edit PASSWORD below and run
 * `php artisan db:seed --class=AdminUserSeeder`: re-seeding sets the admin's password to
 * the one written here.
 *
 * This password is in the repository, so anyone who can read the code knows it. The seeder
 * therefore refuses to run when APP_ENV=production; create the live admin with
 * `php artisan make:admin` (it asks for the password) instead.
 */
class AdminUserSeeder extends Seeder
{
    public const EMAIL = 'admin@dutycalc.test';

    public const PASSWORD = 'dutycalc-admin';

    public const NAME = 'DutyCalc admin';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Admin not seeded: the seeded password is in the code. In production, use `php artisan make:admin`.');

            return;
        }

        $user = User::updateOrCreate(['email' => self::EMAIL], ['name' => self::NAME, 'password' => self::PASSWORD]);

        $this->command?->info($user->wasRecentlyCreated
            ? 'Admin '.self::EMAIL.' seeded.'
            : 'Admin '.self::EMAIL.' updated to the password in AdminUserSeeder.');
    }
}
