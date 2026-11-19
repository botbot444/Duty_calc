<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_refused_on_every_admin_route(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (! str_starts_with($name, 'api.admin.') || $name === 'api.admin.login') {
                continue;
            }
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();

            $this->json($method, '/'.$uri)->assertStatus(401);
            $checked++;
        }

        $this->assertGreaterThanOrEqual(20, $checked, 'expected every admin route to be checked');
    }

    public function test_guests_get_json_401_even_without_an_accept_header(): void
    {
        $this->get('/api/admin/me')->assertStatus(401)->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_an_admin_logs_in_and_out_with_the_session(): void
    {
        User::factory()->create(['email' => 'admin@dutycalc.test', 'password' => 'a-long-test-password']);

        $this->postJson('/api/admin/login', ['email' => 'admin@dutycalc.test', 'password' => 'a-long-test-password'])
            ->assertOk()->assertJsonPath('user.email', 'admin@dutycalc.test')->assertJsonMissingPath('user.password');
        $this->getJson('/api/admin/me')->assertOk();

        $this->postJson('/api/admin/logout')->assertOk();
        $this->assertGuest();
    }

    public function test_a_wrong_password_is_refused(): void
    {
        User::factory()->create(['email' => 'admin@dutycalc.test', 'password' => 'a-long-test-password']);

        $this->postJson('/api/admin/login', ['email' => 'admin@dutycalc.test', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/admin/login', ['email' => 'x@dutycalc.test', 'password' => 'wrong'])->assertStatus(422);
        }

        $this->postJson('/api/admin/login', ['email' => 'x@dutycalc.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_there_is_no_public_sign_up(): void
    {
        $this->postJson('/api/admin/register', ['email' => 'x@dutycalc.test', 'password' => 'a-long-test-password'])->assertNotFound();
        $this->postJson('/register')->assertStatus(404);
    }

    public function test_make_admin_creates_an_admin_with_a_hashed_password(): void
    {
        $this->artisan('make:admin', ['email' => 'Ann@DutyCalc.test', '--name' => 'Ann'])
            ->expectsQuestion('Password (at least 12 characters)', 'correct horse battery')
            ->expectsQuestion('Repeat the password', 'correct horse battery')
            ->expectsOutput('Admin ann@dutycalc.test created.')
            ->assertSuccessful();

        $user = User::where('email', 'ann@dutycalc.test')->sole();
        $this->assertSame('Ann', $user->name);
        $this->assertTrue(Hash::check('correct horse battery', $user->password));
    }

    public function test_make_admin_refuses_short_mismatched_or_duplicate_passwords_and_emails(): void
    {
        $this->artisan('make:admin', ['email' => 'a@dutycalc.test'])
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Repeat the password', 'short')
            ->assertFailed();

        $this->artisan('make:admin', ['email' => 'a@dutycalc.test'])
            ->expectsQuestion('Password (at least 12 characters)', 'correct horse battery')
            ->expectsQuestion('Repeat the password', 'different horse battery')
            ->assertFailed();

        User::factory()->create(['email' => 'taken@dutycalc.test']);
        $this->artisan('make:admin', ['email' => 'taken@dutycalc.test'])
            ->expectsQuestion('Password (at least 12 characters)', 'correct horse battery')
            ->expectsQuestion('Repeat the password', 'correct horse battery')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }
}
