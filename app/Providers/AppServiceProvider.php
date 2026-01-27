<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\OrchestratorInterface;
use App\Contracts\StateManagerInterface;
use App\Infrastructure\Docker\DockerComposeOrchestrator;
use App\Services\Project\ProjectStateManagerService;
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
        $this->app->bind(OrchestratorInterface::class, DockerComposeOrchestrator::class);
        $this->app->bind(StateManagerInterface::class, ProjectStateManagerService::class);
    }

    /**
     * Configure the application's dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
