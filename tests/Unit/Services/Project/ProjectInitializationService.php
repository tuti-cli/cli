<?php

declare(strict_types=1);

use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectInitializationService;
use App\Services\Project\ProjectMetadataService;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-init-service-test-' . uniqid();
    mkdir($this->testDir);
    chdir($this->testDir);

    $this->directoryService = new ProjectDirectoryService();
    $this->metadataService = new ProjectMetadataService($this->directoryService);
    $this->initService = new ProjectInitializationService(
        $this->directoryService,
        $this->metadataService
    );
});

afterEach(function (): void {
    if (property_exists($this, 'testDir') && $this->testDir !== null && is_dir($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

it('initializes a project successfully', function (): void {
    $result = $this->initService->initialize('test-project', 'dev');

    expect($result)->toBeTrue()
        ->and(is_dir('.tuti'))->toBeTrue()
        ->and(file_exists('.tuti/config.json'))->toBeTrue();
});

it('creates correct directory structure', function (): void {
    $this->initService->initialize('test-project', 'dev');

    expect(is_dir('.tuti/docker'))->toBeTrue()
        ->and(is_dir('.tuti/environments'))->toBeTrue()
        ->and(is_dir('.tuti/scripts'))->toBeTrue();
});

it('creates config with correct project name', function (): void {
    $this->initService->initialize('my-awesome-project', 'staging');

    $config = json_decode(file_get_contents('.tuti/config.json'), true);

    expect($config['project']['name'])->toBe('my-awesome-project');
});

it('creates config with correct environment', function (): void {
    $this->initService->initialize('test-project', 'production');

    $config = json_decode(file_get_contents('.tuti/config.json'), true);

    expect($config['environments']['current'])->toBe('production');
});

it('validates initialization result', function (): void {
    $result = $this->initService->initialize('test-project', 'dev');

    expect($result)->toBeTrue()
        ->and($this->directoryService->validate())->toBeTrue();
});

it('throws exception if validation fails', function (): void {
    // Create .tuti but don't create all required subdirectories
    mkdir('.tuti');

    expect(fn () => $this->initService->initialize('test-project', 'dev'))
        ->toThrow(RuntimeException::class, 'Project already initialized');
});

it('creates custom type by default', function (): void {
    $this->initService->initialize('test-project', 'dev');

    $config = json_decode(file_get_contents('.tuti/config.json'), true);

    expect($config['project']['type'])->toBe('custom');
});

it('includes initialization timestamp', function (): void {
    $this->initService->initialize('test-project', 'dev');

    $config = json_decode(file_get_contents('.tuti/config.json'), true);

    expect($config)->toHaveKey('initialized_at')
        ->and($config['initialized_at'])->toBeString();
});

it('sets correct domain for environment', function (): void {
    $this->initService->initialize('my-project', 'dev');

    $config = json_decode(file_get_contents('.tuti/config.json'), true);

    expect($config['environments']['dev']['domain'])->toBe('my-project.test');
});
