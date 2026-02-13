<?php

declare(strict_types=1);

use App\Services\Stack\StackRegistryManagerService;

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->registry = app(StackRegistryManagerService::class);
    $this->registry->loadForStack(base_path('stubs/stacks/laravel'));
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

describe('StackRegistryManagerService', function (): void {
    it('can get horizon service', function (): void {
        $service = $this->registry->getService('workers', 'horizon');

        expect($service)
            ->toBeArray()
            ->and($service['name'])->toBe('Laravel Horizon')
            ->and($service['depends_on'])->toContain('redis');
    });

    it('can get scheduler service', function (): void {
        $service = $this->registry->getService('workers', 'scheduler');

        expect($service)
            ->toBeArray()
            ->and($service['name'])->toBe('Laravel Scheduler');
    });

    it('returns horizon dependencies', function (): void {
        $dependencies = $this->registry->getServiceDependencies('workers', 'horizon');

        expect($dependencies)
            ->toBeArray()
            ->toContain('redis');
    });

    it('returns empty array for services without dependencies', function (): void {
        $dependencies = $this->registry->getServiceDependencies('workers', 'scheduler');

        expect($dependencies)->toBeArray()->toBeEmpty();
    });

    it('resolves horizon dependencies to include redis', function (): void {
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

    it('does not duplicate services when resolving dependencies', function (): void {
        $selected = ['cache.redis', 'workers.horizon'];
        $resolved = $this->registry->resolveDependencies($selected);

        $redisCount = count(array_filter($resolved, fn ($s): bool => $s === 'cache.redis'));
        expect($redisCount)->toBe(1);
    });

    it('handles services without dependencies', function (): void {
        $selected = ['workers.scheduler', 'cache.redis'];
        $resolved = $this->registry->resolveDependencies($selected);

        expect($resolved)
            ->toContain('workers.scheduler')
            ->toContain('cache.redis');
    });
});

// ─── Helper: create a registry.json in a temp directory ─────────────────

function createRegistryFile(string $testDir, array $data): string
{
    $stackDir = $testDir . '/test-stack';
    $servicesDir = $stackDir . '/services';

    if (! is_dir($servicesDir)) {
        mkdir($servicesDir, 0755, true);
    }

    file_put_contents(
        $servicesDir . '/registry.json',
        json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );

    return $stackDir;
}

// ─── registry validation ────────────────────────────────────────────────

describe('registry validation', function (): void {

    it('throws when registry is missing version', function (): void {
        $stackDir = createRegistryFile($this->testDir, [
            'services' => ['databases' => []],
        ]);

        $fresh = app(StackRegistryManagerService::class);

        expect(fn () => $fresh->loadForStack($stackDir))
            ->toThrow(RuntimeException::class, "missing 'version'");
    });

    it('throws when registry is missing services key', function (): void {
        $stackDir = createRegistryFile($this->testDir, [
            'version' => '1.0.0',
        ]);

        $fresh = app(StackRegistryManagerService::class);

        expect(fn () => $fresh->loadForStack($stackDir))
            ->toThrow(RuntimeException::class, "missing 'services'");
    });

    it('throws when service entry is missing name', function (): void {
        $stackDir = createRegistryFile($this->testDir, [
            'version' => '1.0.0',
            'services' => [
                'databases' => [
                    'postgres' => [
                        'stub' => 'databases/postgres.stub',
                    ],
                ],
            ],
        ]);

        $fresh = app(StackRegistryManagerService::class);

        expect(fn () => $fresh->loadForStack($stackDir))
            ->toThrow(RuntimeException::class, "missing 'name'");
    });

    it('throws when service entry is missing stub', function (): void {
        $stackDir = createRegistryFile($this->testDir, [
            'version' => '1.0.0',
            'services' => [
                'databases' => [
                    'postgres' => [
                        'name' => 'PostgreSQL',
                    ],
                ],
            ],
        ]);

        $fresh = app(StackRegistryManagerService::class);

        expect(fn () => $fresh->loadForStack($stackDir))
            ->toThrow(RuntimeException::class, "missing 'stub'");
    });
});
