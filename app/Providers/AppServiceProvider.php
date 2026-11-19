<?php

namespace App\Providers;

use App\Services\Identification\FakeIdentificationClient;
use App\Services\Identification\HttpIdentificationClient;
use App\Services\Identification\IdentificationClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // IDENTIFICATION_DRIVER=fake (default, no network) or http (the AI engineer's service).
        $this->app->singleton(IdentificationClient::class, function () {
            $config = config('services.identification');

            return match ($config['driver']) {
                'http' => new HttpIdentificationClient($config),
                'fake' => new FakeIdentificationClient,
                default => throw new \InvalidArgumentException("Unknown IDENTIFICATION_DRIVER \"{$config['driver']}\""),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
