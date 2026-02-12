<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\StackInstallerInterface;
use App\Services\Stack\Installers\LaravelStackInstaller;
use App\Services\Stack\Installers\WordPressStackInstaller;
use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackEnvGeneratorService;
use App\Services\Stack\StackFilesCopierService;
use App\Services\Stack\StackInitializationService;
use App\Services\Stack\StackInstallerRegistry;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRegistryManagerService;
use App\Services\Stack\StackRepositoryService;
use App\Services\Stack\StackStubLoaderService;
use Illuminate\Support\ServiceProvider;

final class StackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core stack services
        $this->app->singleton(StackRegistryManagerService::class);
        $this->app->singleton(StackStubLoaderService::class);
        $this->app->singleton(StackLoaderService::class);
        $this->app->singleton(StackEnvGeneratorService::class);
        $this->app->singleton(StackComposeBuilderService::class);
        $this->app->singleton(StackFilesCopierService::class);
        $this->app->singleton(StackInitializationService::class);
        $this->app->singleton(StackRepositoryService::class);

        // Stack installers
        $this->app->singleton(LaravelStackInstaller::class);
        $this->app->singleton(WordPressStackInstaller::class);

        // Stack installer registry
        $this->app->singleton(StackInstallerRegistry::class, function ($app): StackInstallerRegistry {
            $registry = new StackInstallerRegistry();

            // Register all stack installers
            $registry->register($app->make(LaravelStackInstaller::class));
            $registry->register($app->make(WordPressStackInstaller::class));

            return $registry;
        });

        // Bind interface to default implementation
        $this->app->bind(StackInstallerInterface::class, LaravelStackInstaller::class);
    }
}
