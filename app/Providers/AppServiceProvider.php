<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureDates();
    }

    public function register(): void
    {
        //
    }

    /**
     * Configure the application's dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
