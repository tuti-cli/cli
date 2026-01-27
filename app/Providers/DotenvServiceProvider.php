<?php

declare(strict_types=1);

namespace App\Providers;

use Dotenv\Dotenv;
use Illuminate\Support\ServiceProvider;

final class DotenvServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load .env file if it exists
        $envPath = base_path();
        $envFile = '.env';

        if (file_exists($envPath . '/' . $envFile)) {
            $dotenv = Dotenv::createImmutable($envPath, $envFile);
            $dotenv->safeLoad(); // Use safeLoad to avoid errors if already loaded
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
