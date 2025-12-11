<?php

declare(strict_types=1);

use App\Services\Tuti\TutiDirectoryManagerService;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-test-' .  uniqid();
    mkdir($this->testDir);

    $this->manager = new TutiDirectoryManagerService($this->testDir);
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        $this->manager->clean();
        @rmdir($this->testDir);
    }
});

it('does not exist initially', function (): void {
    expect($this->manager->exists())->toBeFalse();
});

it('can initialize directory structure', function (): void {
    $this->manager->initialize();

    expect($this->manager->exists())->toBeTrue()
        ->and(is_dir($this->manager->getTutiPath()))->toBeTrue();
});

it('creates all required directories', function (): void {
    $this->manager->initialize();

    $requiredDirs = $this->manager->getRequiredDirectories();

    foreach ($requiredDirs as $dir) {
        expect(is_dir($this->manager->getTutiPath($dir)))
            ->toBeTrue("Directory {$dir} should exist");
    }
});

it('validates directory structure', function (): void {
    $this->manager->initialize();

    expect($this->manager->validate())->toBeTrue();
});

it('throws exception when initializing twice', function (): void {
    $this->manager->initialize();

    expect(fn () => $this->manager->initialize())
        ->toThrow(RuntimeException::class, 'already initialized');
});

it('can get tuti path', function (): void {
    $path = $this->manager->getTutiPath();

    expect($path)->toBe($this->testDir .  '/.tuti');
});

it('can get tuti path with subdirectory', function (): void {
    $path = $this->manager->getTutiPath('docker');

    expect($path)->toBe($this->testDir . '/.tuti/docker');
});

it('can clean directory', function (): void {
    $this->manager->initialize();

    expect($this->manager->exists())->toBeTrue();

    $this->manager->clean();

    expect($this->manager->exists())->toBeFalse();
});

it('returns project root', function (): void {
    expect($this->manager->getProjectRoot())->toBe($this->testDir);
});
