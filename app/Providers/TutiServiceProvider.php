<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Project\ProjectMetadataManagerService;
use App\Services\Project\ProjectDirectoryManagerService;
use Illuminate\Support\ServiceProvider;

class TutiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProjectDirectoryManagerService::class);
        $this->app->singleton(ProjectMetadataManagerService::class);
    }
}
