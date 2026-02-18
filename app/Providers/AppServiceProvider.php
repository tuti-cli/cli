<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\DockerExecutorInterface;
use App\Contracts\InfrastructureManagerInterface;
use App\Contracts\OrchestratorInterface;
use App\Contracts\StateManagerInterface;
use App\Infrastructure\Docker\DockerComposeOrchestrator;
use App\Services\Debug\DebugLogService;
use App\Services\Docker\DockerExecutorService;
use App\Services\Infrastructure\GlobalInfrastructureManager;
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
        // Register Debug Service as singleton (shared instance)
        $this->app->singleton(DebugLogService::class, fn (): DebugLogService => DebugLogService::getInstance());

        $this->app->bind(OrchestratorInterface::class, DockerComposeOrchestrator::class);
        $this->app->bind(StateManagerInterface::class, ProjectStateManagerService::class);
        $this->app->bind(DockerExecutorInterface::class, DockerExecutorService::class);

        // Register GlobalInfrastructureManager with the global tuti path
        $this->app->singleton(InfrastructureManagerInterface::class, fn ($app): GlobalInfrastructureManager => new GlobalInfrastructureManager($this->getGlobalTutiPath()));
    }

    /**
     * Get the global tuti path (~/.tuti).
     */
    private function getGlobalTutiPath(): string
    {
        $home = env('HOME') ?: env('USERPROFILE');

        if (empty($home) && function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $userInfo = posix_getpwuid(posix_getuid());
            $home = $userInfo['dir'] ?? null;
        }

        if (empty($home)) {
            $user = env('USER') ?: env('USERNAME');
            $home = PHP_OS_FAMILY === 'Windows'
                ? "C:\\Users\\{$user}"
                : "/home/{$user}";
        }

        return mb_rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.tuti';
    }

    /**
     * Configure the application's dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
