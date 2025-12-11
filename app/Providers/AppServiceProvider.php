<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackEnvGeneratorService;
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
        $this->app->singleton(StackJsonRegistryManagerService::class, function () {
            return new StackJsonRegistryManagerService('services/registry.json');
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
                registry:  $app->make(StackJsonRegistryManagerService::class),
                stubLoader: $app->make(StackStubLoaderService::class),
                stackLoader: $app->make(StackLoaderService::class)
            );
        });

        // Tuti Directory Management
        $this->app->bind(TutiDirectoryManagerService:: class, function (): TutiDirectoryManagerService {
            return new TutiDirectoryManagerService();
        });

        $this->app->bind(TutiJsonMetadataManagerService::class, function ($app): TutiJsonMetadataManagerService {
            return new TutiJsonMetadataManagerService(
                $app->make(TutiDirectoryManagerService::class)
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
