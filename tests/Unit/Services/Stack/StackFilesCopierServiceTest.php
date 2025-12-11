<?php

declare(strict_types=1);

use Tests\Feature\Concerns\CreatesTestStackEnvironment;

uses(CreatesTestStackEnvironment::class)->group('stack');

beforeEach(function (): void {
    $this->setupStackEnvironment();
});

afterEach(function (): void {
    $this->cleanupStackEnvironment();
});

it('throws exception when stack directory does not exist', function (): void {
    expect(fn () => $this->copier->copyFromStack('/nonexistent'))
        ->toThrow(RuntimeException::class, 'Stack directory not found');
});

it('copies docker directory', function (): void {
    $this->copier->copyFromStack($this->stackDir);

    expect(is_dir($this->manager->getTutiPath('docker')))->toBeTrue()
        ->and(file_exists($this->manager->getTutiPath('docker/Dockerfile')))->toBeTrue();
});

it('copies environments directory', function (): void {
    $this->copier->copyFromStack($this->stackDir);

    expect(is_dir($this->manager->getTutiPath('environments')))->toBeTrue()
        ->and(file_exists($this->manager->getTutiPath('environments/.env.dev.example')))->toBeTrue()
        ->and(file_exists($this->manager->getTutiPath('environments/.env.prod.example')))->toBeTrue();
});

it('copies scripts directory', function (): void {
    $this->copier->copyFromStack($this->stackDir);

    expect(is_dir($this->manager->getTutiPath('scripts')))->toBeTrue()
        ->and(file_exists($this->manager->getTutiPath('scripts/deploy.sh')))->toBeTrue()
        ->and(file_exists($this->manager->getTutiPath('scripts/health-check.sh')))->toBeTrue();
});

it('copies individual files', function (): void {
    $this->copier->copyFromStack($this->stackDir);

    expect(file_exists($this->manager->getTutiPath('deploy.sh')))->toBeTrue()
        ->and(file_exists($this->manager->getTutiPath('stack.json')))->toBeTrue()
        ->and(file_exists($this->manager->getTutiPath('PREDEPLOYMENT-CHECKLIST.md')))->toBeTrue();
});

it('makes scripts executable', function (): void {
    $this->copier->copyFromStack($this->stackDir);

    $scripts = [
        $this->manager->getTutiPath('scripts/deploy.sh'),
        $this->manager->getTutiPath('scripts/health-check.sh'),
        $this->manager->getTutiPath('deploy.sh'),
    ];

    foreach ($scripts as $script) {
        expect(is_executable($script))->toBeTrue("Script should be executable: {$script}");
    }
});

it('handles missing optional directories gracefully', function (): void {
    // Create completely isolated environment to avoid interference from other tests
    $isolated = $this->createIsolatedEnvironment();

    $tempStackDir = $this->createStackWithoutDocker();

    // The service should not throw an exception when optional directories are missing
    expect(fn() => $isolated['copier']->copyFromStack($tempStackDir))
        ->not->toThrow(RuntimeException::class);

    $dockerPath = $isolated['manager']->getTutiPath('docker');

    // Verify that if docker directory exists, it's empty (no files copied)
    if (is_dir($dockerPath)) {
        $files = array_diff(scandir($dockerPath), ['.', '..']);
        expect($files)->toBeEmpty("Docker directory should be empty when source doesn't have it");
    }
});

it('can get file list from stack', function (): void {
    $files = $this->copier->getFileList($this->stackDir);

    expect($files)->toBeArray()
        ->toHaveKey('docker')
        ->toHaveKey('environments')
        ->toHaveKey('scripts')
        ->toHaveKey('root')
        ->and($files['docker'])->toContain('Dockerfile')
        ->and($files['environments'])->toContain('.env.dev.example')
        ->and($files['scripts'])->toContain('deploy.sh')
        ->and($files['root'])->toContain('stack.json');
});

it('preserves file contents when copying', function (): void {
    $this->copier->copyFromStack($this->stackDir);

    $original = file_get_contents($this->stackDir . '/docker/Dockerfile');
    $copied = file_get_contents($this->manager->getTutiPath('docker/Dockerfile'));

    expect($copied)->toBe($original);
});

it('creates nested directories if needed', function (): void {
    $tempStackDir = $this->createStackWithNestedStructure();

    $this->copier->copyFromStack($tempStackDir);

    expect(file_exists($this->manager->getTutiPath('docker/configs/nginx.conf')))->toBeTrue();
});
