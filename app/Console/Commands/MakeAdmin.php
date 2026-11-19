<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * The only way to create an admin: there is no public sign-up. Traders have no accounts.
 * The password is asked for interactively so it never lands in shell history.
 */
class MakeAdmin extends Command
{
    protected $signature = 'make:admin {email : The admin\'s email address} {--name= : Display name}';

    protected $description = 'Create an admin user (there is no public sign-up)';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $name = $this->option('name') ?: strstr($email, '@', true);

        $password = (string) $this->secret('Password (at least 12 characters)');
        $confirm = (string) $this->secret('Repeat the password');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password, 'password_confirmation' => $confirm],
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:12', 'confirmed'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $this->info("Admin {$email} created.");

        return self::SUCCESS;
    }
}
