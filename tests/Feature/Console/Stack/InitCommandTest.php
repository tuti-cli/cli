<?php

declare(strict_types=1);

use App\Services\Stack\StackInitializationService;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-stack-init-feature-' . uniqid();
    mkdir($this->testDir);
    chdir($this->testDir);

    $this->stackDir = sys_get_temp_dir() . '/test-stack-' . uniqid();

    mkdir($this->stackDir);

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
});

afterEach(function (): void {
    if (isset($this->testDir) && is_dir($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }

    if (isset($this->stackDir) && is_dir($this->stackDir)) {
        File::deleteDirectory($this->stackDir);
    }
});

it('fails when no stack is provided in non-interactive mode', function (): void {
    $this->artisan('stack:init --no-interaction')
        ->assertFailed()
        ->expectsOutput('No stack selected.  Exiting.');
});

it('fails when stack does not exist', function (): void {
    $this->artisan('stack:init nonexistent-stack myapp --no-interaction')
        ->assertFailed();
});

it('fails when project is already initialized', function (): void {
    mkdir('. tuti');

    $this->artisan("stack:init {$this->stackDir} myapp --no-interaction")
        ->assertFailed()
        ->expectsOutput('Project already initialized. ". tuti/" directory already exists in your project root.');

    rmdir('.tuti');
});

it('reinitializes with --force flag', function (): void {
    mkdir('.tuti');

    $this->artisan("stack:init {$this->stackDir} myapp --force --no-interaction")
        ->assertSuccessful()
        ->expectsOutput('✅ Stack initialized successfully!');
});

it('delegates to StackInitializationService', function (): void {
    $this->mock(StackInitializationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('initialize')
            ->once()
            ->andReturn(true);
    });

    $this->artisan("stack:init {$this->stackDir} myapp --no-interaction")
        ->assertSuccessful();
});

it('displays stack information', function (): void {
    $this->artisan("stack:init {$this->stackDir} myapp --no-interaction")
        ->assertSuccessful()
        ->expectsOutputToContain('test-stack')
        ->expectsOutputToContain('Test stack for testing');
});

it('accepts pre-selected services via option', function (): void {
    $this->artisan("stack: init {$this->stackDir} myapp --services=databases.postgres --services=cache.redis --no-interaction")
        ->assertSuccessful();

    $config = json_decode(file_get_contents('.tuti/config.json'), true);

    expect($config['environments']['dev']['services'])->toHaveKey('databases')
        ->and($config['environments']['dev']['services'])->toHaveKey('cache');
});

it('accepts environment via option', function (): void {
    $this->artisan("stack:init {$this->stackDir} myapp --env=staging --no-interaction")
        ->assertSuccessful();

    $config = json_decode(file_get_contents('.tuti/config.json'), true);

    expect($config['environments']['current'])->toBe('staging');
});

it('displays next steps after initialization', function (): void {
    $this->artisan("stack:init {$this->stackDir} myapp --no-interaction")
        ->assertSuccessful()
        ->expectsOutputToContain('Next Steps:')
        ->expectsOutputToContain('tuti local:start');
});
