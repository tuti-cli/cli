<?php

declare(strict_types=1);

use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackFilesCopierService;
use App\Services\Stack\StackInitializationService;
use App\Services\Stack\StackLoaderService;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-stack-init-test-' . uniqid();
    $this->stackDir = sys_get_temp_dir() . '/tuti-test-stack-' . uniqid();

    mkdir($this->testDir);
    mkdir($this->stackDir);
    chdir($this->testDir);

    $manifest = [
        'name' => 'test-stack',
        'version' => '1.0.0',
        'type' => 'php',
        'framework' => 'laravel',
        'description' => 'Test stack for testing',
        'required_services' => [
            'database' => [
                'category' => 'databases',
                'options' => ['postgres'],
                'default' => 'postgres',
            ],
        ],
        'optional_services' => [
            'cache' => [
                'category' => 'cache',
                'options' => ['redis'],
            ],
        ],
    ];

    file_put_contents($this->stackDir . '/stack.json', json_encode($manifest));

    mkdir($this->stackDir . '/docker');
    file_put_contents($this->stackDir . '/docker/Dockerfile', 'FROM php:8.4');

    $this->directoryService = new ProjectDirectoryService();
    $this->metadataService = new ProjectMetadataService($this->directoryService);
    $this->copierService = new StackFilesCopierService($this->directoryService);
    $this->composeBuilder = app(StackComposeBuilderService::class);
    $this->stackLoader = app(StackLoaderService::class);

    $this->initService = new StackInitializationService(
        $this->directoryService,
        $this->metadataService,
        $this->copierService,
        $this->composeBuilder,
        $this->stackLoader
    );
});

afterEach(function (): void {
    if (isset($this->testDir) && is_dir($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }

    if (isset($this->stackDir) && is_dir($this->stackDir)) {
        File::deleteDirectory($this->stackDir);
    }
});

it('initializes a project from stack successfully', function (): void {
    $result = $this->initService->initialize(
        $this->stackDir,
        'test-project',
        'dev',
        ['databases. postgres', 'cache.redis']
    );

    expect($result)->toBeTrue()
        ->and(is_dir('. tuti'))->toBeTrue()
        ->and(file_exists('.tuti/config.json'))->toBeTrue()
        ->and(file_exists('.tuti/docker-compose.yml'))->toBeTrue();
});

it('creates config with stack information', function (): void {
    $this->initService->initialize(
        $this->stackDir,
        'my-project',
        'staging',
        ['databases.postgres']
    );

    $config = json_decode(file_get_contents('.tuti/config.json'), true);

    expect($config)->toHaveKey('stack')
        ->and($config['stack'])->toHaveKey('name', 'test-stack')
        ->and($config['stack'])->toHaveKey('version', '1.0.0');
});

it('groups services by category in config', function (): void {
    $this->initService->initialize(
        $this->stackDir,
        'test-project',
        'dev',
        ['databases.postgres', 'databases.mysql', 'cache.redis']
    );

    $config = json_decode(file_get_contents('. tuti/config.json'), true);

    expect($config['environments']['dev']['services'])->toHaveKey('databases')
        ->and($config['environments']['dev']['services']['databases'])->toContain('postgres', 'mysql')
        ->and($config['environments']['dev']['services'])->toHaveKey('cache')
        ->and($config['environments']['dev']['services']['cache'])->toContain('redis');
});

it('copies stack files to project', function (): void {
    $this->initService->initialize(
        $this->stackDir,
        'test-project',
        'dev',
        ['databases. postgres']
    );

    expect(file_exists('.tuti/docker/Dockerfile'))->toBeTrue()
        ->and(file_exists('.tuti/stack.json'))->toBeTrue();
});

it('generates docker-compose.yml with selected services', function (): void {
    $this->initService->initialize(
        $this->stackDir,
        'test-project',
        'dev',
        ['databases.postgres', 'cache. redis']
    );

    expect(file_exists('.tuti/docker-compose.yml'))->toBeTrue();

    $compose = file_get_contents('.tuti/docker-compose.yml');

    expect($compose)->toContain('postgres')
        ->and($compose)->toContain('redis');
});

it('validates directory structure after initialization', function (): void {
    $this->initService->initialize(
        $this->stackDir,
        'test-project',
        'dev',
        ['databases.postgres']
    );

    expect($this->directoryService->validate())->toBeTrue();
});

it('throws exception if stack manifest is invalid', function (): void {
    // Create invalid stack
    $invalidStack = $this->stackDir . '-invalid';
    mkdir($invalidStack);
    file_put_contents($invalidStack . '/stack.json', '{"invalid": true}');

    expect(fn () => $this->initService->initialize(
        $invalidStack,
        'test-project',
        'dev',
        []
    ))->toThrow(RuntimeException::class);

    File::deleteDirectory($invalidStack);
});

it('sets correct project type from stack', function (): void {
    $this->initService->initialize(
        $this->stackDir,
        'test-project',
        'dev',
        ['databases.postgres']
    );

    $config = json_decode(file_get_contents('.tuti/config.json'), true);

    expect($config['project']['type'])->toBe('php');
});
