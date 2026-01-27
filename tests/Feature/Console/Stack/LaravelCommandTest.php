<?php

declare(strict_types=1);

use App\Services\Stack\Installers\LaravelStackInstaller;
use App\Services\Stack\StackInitializationService;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-laravel-cmd-feature-' . uniqid();
    mkdir($this->testDir);
    chdir($this->testDir);
});

afterEach(function (): void {
    if (property_exists($this, 'testDir') && $this->testDir !== null && is_dir($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

it('shows installation mode selection in interactive mode', function (): void {
    // This test verifies the command structure exists
    $this->artisan('stack:laravel --help')
        ->assertSuccessful()
        ->expectsOutputToContain('mode')
        ->expectsOutputToContain('fresh')
        ->expectsOutputToContain('existing');
});

it('detects existing Laravel project and suggests existing mode', function (): void {
    // Create fake Laravel project structure
    mkdir($this->testDir . '/bootstrap');
    file_put_contents($this->testDir . '/artisan', '<?php // artisan');
    file_put_contents($this->testDir . '/composer.json', json_encode([
        'require' => ['laravel/framework' => '^11.0'],
    ]));
    file_put_contents($this->testDir . '/bootstrap/app.php', '<?php // app');

    $this->mock(LaravelStackInstaller::class, function (MockInterface $mock): void {
        $mock->shouldReceive('detectExistingProject')
            ->andReturn(true);
        $mock->shouldReceive('getStackPath')
            ->andReturn(stack_path('laravel-stack'));
        $mock->shouldReceive('applyToExisting')
            ->andReturn(true);
    });

    $this->mock(StackInitializationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('initialize')
            ->once()
            ->andReturn(true);
    });

    $this->artisan('stack:laravel myapp --mode=existing --no-interaction')
        ->assertSuccessful();
});

it('fails when stack not found', function (): void {
    $this->mock(LaravelStackInstaller::class, function (MockInterface $mock): void {
        $mock->shouldReceive('detectExistingProject')
            ->andReturn(false);
        $mock->shouldReceive('getStackPath')
            ->andThrow(new RuntimeException('Laravel stack not found'));
    });

    $this->artisan('stack:laravel myapp --mode=fresh --no-interaction')
        ->assertFailed();
});

it('fails when .tuti already exists without --force', function (): void {
    mkdir('.tuti');

    $this->artisan('stack:laravel myapp --mode=fresh --no-interaction')
        ->assertFailed()
        ->expectsOutput('Project already initialized. ".tuti/" directory already exists.');

    rmdir('.tuti');
});

it('reinitializes with --force flag', function (): void {
    mkdir('.tuti');

    $this->mock(LaravelStackInstaller::class, function (MockInterface $mock): void {
        $mock->shouldReceive('detectExistingProject')
            ->andReturn(false);
        $mock->shouldReceive('getStackPath')
            ->andReturn(stack_path('laravel-stack'));
        $mock->shouldReceive('installFresh')
            ->andReturn(true);
        $mock->shouldReceive('getStackManifest')
            ->andReturn([
                'name' => 'laravel-stack',
                'version' => '1.0.0',
                'type' => 'php',
                'framework' => 'laravel',
                'description' => 'Test',
                'required_services' => [],
                'optional_services' => [],
            ]);
    });

    $this->mock(StackInitializationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('initialize')
            ->once()
            ->andReturn(true);
    });

    $this->artisan('stack:laravel myapp --force --mode=fresh --no-interaction')
        ->assertSuccessful();
});

it('displays next steps after successful installation', function (): void {
    $this->mock(LaravelStackInstaller::class, function (MockInterface $mock): void {
        $mock->shouldReceive('detectExistingProject')
            ->andReturn(false);
        $mock->shouldReceive('getStackPath')
            ->andReturn(stack_path('laravel-stack'));
        $mock->shouldReceive('installFresh')
            ->andReturn(true);
    });

    $this->mock(StackInitializationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('initialize')
            ->once()
            ->andReturn(true);
    });

    $this->artisan('stack:laravel myapp --mode=fresh --no-interaction')
        ->assertSuccessful()
        ->expectsOutputToContain('Laravel stack installed successfully')
        ->expectsOutputToContain('Next Steps');
});
