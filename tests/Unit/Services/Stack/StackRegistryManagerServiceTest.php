<?php

declare(strict_types=1);

use App\Services\Stack\StackRegistryManagerService;

beforeEach(function () {
    $this->registry = app(StackRegistryManagerService::class);
    $this->registry->loadForStack(base_path('stubs/stacks/laravel'));
});

describe('StackRegistryManagerService', function () {
    it('can get horizon service', function () {
        $service = $this->registry->getService('workers', 'horizon');

        expect($service)
            ->toBeArray()
            ->and($service['name'])->toBe('Laravel Horizon')
            ->and($service['depends_on'])->toContain('redis');
    });

    it('can get scheduler service', function () {
        $service = $this->registry->getService('workers', 'scheduler');

        expect($service)
            ->toBeArray()
            ->and($service['name'])->toBe('Laravel Scheduler');
    });

    it('returns horizon dependencies', function () {
        $dependencies = $this->registry->getServiceDependencies('workers', 'horizon');

        expect($dependencies)
            ->toBeArray()
            ->toContain('redis');
    });

    it('returns empty array for services without dependencies', function () {
        $dependencies = $this->registry->getServiceDependencies('workers', 'scheduler');

        expect($dependencies)->toBeArray()->toBeEmpty();
    });

    it('resolves horizon dependencies to include redis', function () {
        $selected = ['workers.horizon'];
        $resolved = $this->registry->resolveDependencies($selected);

        expect($resolved)
            ->toBeArray()
            ->toContain('cache.redis')
            ->toContain('workers.horizon');

        // Redis should come before horizon
        $redisIndex = array_search('cache.redis', $resolved);
        $horizonIndex = array_search('workers.horizon', $resolved);
        expect($redisIndex)->toBeLessThan($horizonIndex);
    });

    it('does not duplicate services when resolving dependencies', function () {
        $selected = ['cache.redis', 'workers.horizon'];
        $resolved = $this->registry->resolveDependencies($selected);

        $redisCount = count(array_filter($resolved, fn ($s) => $s === 'cache.redis'));
        expect($redisCount)->toBe(1);
    });

    it('handles services without dependencies', function () {
        $selected = ['workers.scheduler', 'cache.redis'];
        $resolved = $this->registry->resolveDependencies($selected);

        expect($resolved)
            ->toContain('workers.scheduler')
            ->toContain('cache.redis');
    });
});
