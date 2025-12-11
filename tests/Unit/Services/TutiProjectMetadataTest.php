<?php

declare(strict_types=1);

use App\Services\Tuti\ServiceTutiDirectoryManager;
use App\Services\Tuti\ServiceTutiJsonMetadataManager;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-test-' . uniqid();
    mkdir($this->testDir);
    $this->manager = new ServiceTutiDirectoryManager($this->testDir);
    $this->manager->initialize();
    $this->metadata = new ServiceTutiJsonMetadataManager($this->manager);
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        $this->manager->clean();
        @rmdir($this->testDir);
    }
});

it('does not exist initially', function (): void {
    expect($this->metadata->exists())->toBeFalse();
});

it('can create metadata', function (): void {
    $this->metadata->create([
        'stack' => 'laravel-stack',
        'stack_version' => '1.0.0',
        'project_name' => 'test-project',
        'environment' => 'dev',
        'services' => [
            'databases' => ['postgres'],
            'cache' => ['redis'],
        ],
    ]);

    expect($this->metadata->exists())->toBeTrue();
});

it('throws exception when creating twice', function (): void {
    $this->metadata->create(['project_name' => 'test']);

    expect(fn () => $this->metadata->create(['project_name' => 'test2']))
        ->toThrow(RuntimeException::class, 'already exists');
});

it('can load metadata', function (): void {
    $data = [
        'stack' => 'laravel-stack',
        'project_name' => 'test-project',
        'environment' => 'dev',
        'services' => ['databases' => ['postgres']],
    ];

    $this->metadata->create($data);
    $loaded = $this->metadata->load();

    expect($loaded)
        ->toHaveKey('stack', 'laravel-stack')
        ->toHaveKey('project_name', 'test-project')
        ->toHaveKey('environments.current', 'dev')
        ->toHaveKey('created_at')
        ->toHaveKey('updated_at');
});

it('can update metadata', function (): void {
    $this->metadata->create([
        'project_name' => 'test',
        'services' => ['databases' => ['postgres']],
    ]);

    $this->metadata->update([
        'services' => ['databases' => ['postgres'], 'cache' => ['redis']],
    ]);

    $loaded = $this->metadata->load();

    expect($loaded['services'])
        ->toHaveKey('cache')
        ->and($loaded['services']['cache'])->toBe(['redis']);
});

it('can get stack name', function (): void {
    $this->metadata->create(['stack' => 'laravel-stack']);

    expect($this->metadata->getStack())->toBe('laravel-stack');
});

it('can get project name', function (): void {
    $this->metadata->create(['project_name' => 'my-project']);

    expect($this->metadata->getProjectName())->toBe('my-project');
});

it('can get current environment', function (): void {
    $this->metadata->create(['environment' => 'staging']);

    expect($this->metadata->getCurrentEnvironment())->toBe('staging');
});

it('can set current environment', function (): void {
    $this->metadata->create(['environment' => 'dev']);
    $this->metadata->setCurrentEnvironment('production');

    expect($this->metadata->getCurrentEnvironment())->toBe('production');
});

it('adds environment to configured list when setting', function (): void {
    $this->metadata->create(['environment' => 'dev']);
    $this->metadata->setCurrentEnvironment('production');

    $loaded = $this->metadata->load();

    expect($loaded['environments']['configured'])
        ->toContain('dev')
        ->toContain('production');
});

it('updates timestamp when modifying metadata', function (): void {
    $this->metadata->create(['project_name' => 'test']);
    $created = $this->metadata->load();

    sleep(1);

    $this->metadata->update(['project_name' => 'updated']);
    $updated = $this->metadata->load();

    expect($updated['updated_at'])->not->toBe($created['updated_at']);
});
