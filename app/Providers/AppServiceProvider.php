<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Stack\ServiceComposeStackBuilder;
use App\Services\Stack\ServiceEnvGenerator;
use App\Services\Stack\ServiceRegistryJsonReader;
use App\Services\Stack\ServiceStackLoader;
use App\Services\Stack\ServiceStubLoader;
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
        $this->app->singleton(ServiceRegistryJsonReader::class, function () {
            return new ServiceRegistryJsonReader('services/registry.json');
        });

        $this->app->singleton(ServiceStubLoader::class, function () {
            return new ServiceStubLoader();
        });

        $this->app->singleton(ServiceStackLoader::class, function () {
            return new ServiceStackLoader();
        });

        $this->app->singleton(ServiceEnvGenerator::class, function () {
            return new ServiceEnvGenerator();
        });

        $this->app->singleton(ServiceComposeStackBuilder::class, function ($app) {
            return new ServiceComposeStackBuilder(
                registry:  $app->make(ServiceRegistryJsonReader::class),
                stubLoader: $app->make(ServiceStubLoader::class),
                stackLoader: $app->make(ServiceStackLoader::class)
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
