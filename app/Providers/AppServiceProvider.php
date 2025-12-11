<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackEnvGeneratorService;
use App\Services\Stack\StackRegistryReaderService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackStubLoaderService;
use App\Services\Tuti\ServiceTutiDirectoryManager;
use App\Services\Tuti\ServiceTutiJsonMetadataManager;
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
        $this->app->singleton(StackRegistryReaderService::class, function () {
            return new StackRegistryReaderService('services/registry.json');
        });

        $this->app->singleton(StackStubLoaderService::class, function () {
            return new StackStubLoaderService();
        });

        $this->app->singleton(StackLoaderService::class, function () {
            return new StackLoaderService();
        });

        $this->app->singleton(StackEnvGeneratorService::class, function () {
            return new StackEnvGeneratorService();
        });

        $this->app->singleton(StackComposeBuilderService::class, function ($app) {
            return new StackComposeBuilderService(
                registry:  $app->make(StackRegistryReaderService::class),
                stubLoader: $app->make(StackStubLoaderService::class),
                stackLoader: $app->make(StackLoaderService::class)
            );
        });

        // Tuti Directory Management
        $this->app->bind(ServiceTutiDirectoryManager:: class, function (): ServiceTutiDirectoryManager {
            return new ServiceTutiDirectoryManager();
        });

        $this->app->bind(ServiceTutiJsonMetadataManager::class, function ($app): ServiceTutiJsonMetadataManager {
            return new ServiceTutiJsonMetadataManager(
                $app->make(ServiceTutiDirectoryManager::class)
            );
        });
    }

    /**
     * Configure the application's dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
