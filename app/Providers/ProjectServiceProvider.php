<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectInitializationService;
use App\Services\Project\ProjectMetadataService;
use Illuminate\Support\ServiceProvider;

final class ProjectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProjectDirectoryService::class);
        $this->app->singleton(ProjectMetadataService::class);
        $this->app->singleton(ProjectInitializationService::class);
    }
}
