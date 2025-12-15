<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Tuti\TutiDirectoryManagerService;
use App\Services\Tuti\TutiJsonMetadataManagerService;

class TutiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TutiDirectoryManagerService::class);
        $this->app->singleton(TutiJsonMetadataManagerService::class);
    }
}
