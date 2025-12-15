<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackEnvGeneratorService;
use App\Services\Stack\StackFilesCopierService;
use App\Services\Stack\StackInitializationService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRegistryManagerService;
use App\Services\Stack\StackStubLoaderService;
use Illuminate\Support\ServiceProvider;

final class StackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StackRegistryManagerService::class);
        $this->app->singleton(StackStubLoaderService::class);
        $this->app->singleton(StackLoaderService::class);
        $this->app->singleton(StackEnvGeneratorService::class);
        $this->app->singleton(StackComposeBuilderService::class);
        $this->app->singleton(StackFilesCopierService::class);
        $this->app->singleton(StackInitializationService::class);
    }
}
