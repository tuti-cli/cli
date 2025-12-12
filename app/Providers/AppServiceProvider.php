<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackEnvGeneratorService;
use App\Services\Stack\StackFilesCopierService;
use App\Services\Stack\StackJsonRegistryManagerService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackStubLoaderService;
use App\Services\Tuti\TutiDirectoryManagerService;
use App\Services\Tuti\TutiJsonMetadataManagerService;
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
        // Stack Management
        $this->app->singleton(abstract: StackJsonRegistryManagerService::class, concrete: fn(): \App\Services\Stack\StackJsonRegistryManagerService => new StackJsonRegistryManagerService('services/registry.json'));

        $this->app->singleton(abstract: StackStubLoaderService::class, concrete: fn(): \App\Services\Stack\StackStubLoaderService => new StackStubLoaderService());

        $this->app->singleton(abstract: StackLoaderService::class, concrete: fn(): \App\Services\Stack\StackLoaderService => new StackLoaderService());

        $this->app->singleton(abstract: StackEnvGeneratorService::class, concrete: fn(): \App\Services\Stack\StackEnvGeneratorService => new StackEnvGeneratorService());

        $this->app->singleton(abstract: StackComposeBuilderService::class, concrete: fn($app): \App\Services\Stack\StackComposeBuilderService => new StackComposeBuilderService(
            registry: $app->make(StackJsonRegistryManagerService::class),
            stubLoader: $app->make(StackStubLoaderService::class),
            stackLoader: $app->make(StackLoaderService::class)
        ));

        // Tuti Directory Management
        $this->app->bind(TutiDirectoryManagerService::class, fn($app, array $params): \App\Services\Tuti\TutiDirectoryManagerService => new TutiDirectoryManagerService($params['projectRoot'] ?? null));

        $this->app->bind(TutiJsonMetadataManagerService::class, function ($app, array $params): \App\Services\Tuti\TutiJsonMetadataManagerService {
            // If we are resolving this manually with a specific directory manager, use it
            if (isset($params['directoryManager'])) {
                return new TutiJsonMetadataManagerService($params['directoryManager']);
            }

            return new TutiJsonMetadataManagerService(
                $app->make(TutiDirectoryManagerService::class)
            );
        });

        $this->app->bind(StackFilesCopierService::class, fn($app): StackFilesCopierService => new StackFilesCopierService(
            directoryManager: $app->make(TutiDirectoryManagerService::class)
        ));
    }

    /**
     * Configure the application's dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
