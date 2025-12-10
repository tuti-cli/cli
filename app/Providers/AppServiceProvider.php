<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Stack\ServiceComposeStackBuilder;
use App\Services\Stack\ServiceEnvGenerator;
use App\Services\Stack\ServiceRegistryJsonReader;
use App\Services\Stack\ServiceStackLoader;
use App\Services\Stack\ServiceStubLoader;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        //
    }

    public function register(): void
    {
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
    }
}
