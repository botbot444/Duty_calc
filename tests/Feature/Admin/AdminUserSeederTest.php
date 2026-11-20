<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_is_seeded_with_a_hashed_password_and_can_sign_in(): void
    {
        $this->seed(AdminUserSeeder::class);

        $user = User::sole();
        $this->assertSame(AdminUserSeeder::EMAIL, $user->email);
        $this->assertNotSame(AdminUserSeeder::PASSWORD, $user->password, 'stored hashed, never as typed');
        $this->assertTrue(Hash::check(AdminUserSeeder::PASSWORD, $user->password));

        $this->postJson('/api/admin/login', ['email' => AdminUserSeeder::EMAIL, 'password' => AdminUserSeeder::PASSWORD])->assertOk();
    }

    public function test_reseeding_sets_the_password_to_the_one_in_the_code(): void
    {
        User::create(['name' => 'Old', 'email' => AdminUserSeeder::EMAIL, 'password' => 'an-older-password']);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check(AdminUserSeeder::PASSWORD, User::sole()->password));
    }

    public function test_the_seeded_password_is_never_used_in_production(): void
    {
        $this->app['env'] = 'production';

        // Called directly: `db:seed` itself stops to ask "Are you sure?" in production.
        $this->app->make(AdminUserSeeder::class)->run();

        $this->assertSame(0, User::count());
    }
}
