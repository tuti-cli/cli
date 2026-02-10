<?php

declare(strict_types=1);

/**
 * InstallCommand Feature Tests
 *
 * Tests the `tuti install` command which sets up global configuration,
 * directories, and infrastructure (Traefik) for the CLI tool.
 *
 * Coverage: 94.6% (45 tests, 152 assertions)
 *
 * Untested Edge Cases:
 * - Lines 177-179: Infrastructure reinstall cancellation when user declines confirmation
 *   (Hard to test due to complex prompt mocking requirements)
 * - Line 226: Windows-specific hosts file hint
 *   (Would require mocking PHP_OS_FAMILY constant)
 * - Lines 266-268: USER/USERNAME environment variable fallback
 *   (Hard to test reliably in feature tests due to environment variable isolation issues)
 *
 * @see \App\Commands\InstallCommand
 */

use App\Contracts\InfrastructureManagerInterface;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

// ─── Registration ───────────────────────────────────────────────────────

describe('InstallCommand', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(\App\Commands\InstallCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(\App\Commands\InstallCommand::class);

        expect($command->getName())->toBe('install');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(\App\Commands\InstallCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(\App\Concerns\HasBrandedOutput::class);
    });

    it('has --force and --skip-infra options', function (): void {
        $command = $this->app->make(\App\Commands\InstallCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('force'))->toBeTrue()
            ->and($definition->hasOption('skip-infra'))->toBeTrue();
    });

    it('has correct description', function (): void {
        $command = $this->app->make(\App\Commands\InstallCommand::class);

        expect($command->getDescription())->toBe('Set up tuti CLI global configuration, directories, and infrastructure');
    });
});

// ─── Docker Check ───────────────────────────────────────────────────────

describe('InstallCommand Docker Check', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
    });

    afterEach(function (): void {
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('fails when Docker is not available', function (): void {
        Process::fake(['*docker*info*' => Process::result(errorOutput: 'Cannot connect', exitCode: 1)]);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::FAILURE);
    });

    it('displays helpful message when Docker is not available', function (): void {
        Process::fake(['*docker*info*' => Process::result(errorOutput: 'Cannot connect', exitCode: 1)]);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Docker is not available or not running')
            ->expectsOutputToContain('https://www.docker.com/products/docker-desktop');
    });

    it('proceeds when Docker is available', function (): void {
        Process::fake(['*docker*info*' => Process::result('Docker is running')]);

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays success message when Docker is available', function (): void {
        Process::fake(['*docker*info*' => Process::result('Docker is running')]);

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Docker is available');
    });
});

// ─── Global Directory Setup ─────────────────────────────────────────────

describe('InstallCommand Global Directory Setup', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        Process::fake(['*docker*info*' => Process::result('Docker is running')]);
    });

    afterEach(function (): void {
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('creates global .tuti directory with subdirectories', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);

        $tutiPath = $this->testDir . '/.tuti';

        expect($tutiPath)->toBeDirectory()
            ->and($tutiPath . '/stacks')->toBeDirectory()
            ->and($tutiPath . '/cache')->toBeDirectory()
            ->and($tutiPath . '/logs')->toBeDirectory()
            ->and($tutiPath . '/infrastructure')->toBeDirectory();
    });

    it('displays directory creation messages', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('stacks/')
            ->expectsOutputToContain('cache/')
            ->expectsOutputToContain('logs/')
            ->expectsOutputToContain('infrastructure/');
    });

    it('skips directory creation when already exists', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Global directory already exists');
    });

    it('recreates directory with --force flag', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);
        $existingFile = $this->testDir . '/.tuti/existing.txt';
        file_put_contents($existingFile, 'test');

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--force' => true, '--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);

        $tutiPath = $this->testDir . '/.tuti';

        expect($tutiPath . '/stacks')->toBeDirectory()
            ->and($tutiPath . '/cache')->toBeDirectory()
            ->and($tutiPath . '/logs')->toBeDirectory()
            ->and($tutiPath . '/infrastructure')->toBeDirectory()
            ->and($existingFile)->toBeFile(); // File should still exist
    });

    it('uses correct permissions for created directories', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);

        $tutiPath = $this->testDir . '/.tuti';
        $perms = substr(sprintf('%o', fileperms($tutiPath)), -4);

        expect($perms)->toBe('0755');
    });
});

// ─── Config Creation ────────────────────────────────────────────────────

describe('InstallCommand Config Creation', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        Process::fake(['*docker*info*' => Process::result('Docker is running')]);
    });

    afterEach(function (): void {
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('creates config.json with correct structure', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);

        $configPath = $this->testDir . '/.tuti/config.json';

        expect($configPath)->toBeFile();

        $config = json_decode(file_get_contents($configPath), true);

        expect($config)
            ->toHaveKey('version')
            ->toHaveKey('auto_update_stacks', true)
            ->toHaveKey('telemetry', false)
            ->toHaveKey('default_environment', 'dev')
            ->toHaveKey('infrastructure')
            ->toHaveKey('created_at');

        expect($config['infrastructure'])
            ->toHaveKey('network', 'traefik_proxy')
            ->toHaveKey('domain', 'local.test');
    });



    it('includes timestamp in created_at field', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);

        $configPath = $this->testDir . '/.tuti/config.json';
        $config = json_decode(file_get_contents($configPath), true);

        expect($config['created_at'])->toBeString()
            ->and($config['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });

    it('displays config creation message', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('config.json');
    });

    it('skips config creation when already exists', function (): void {
        $tutiPath = $this->testDir . '/.tuti';
        mkdir($tutiPath, 0755, true);
        file_put_contents($tutiPath . '/config.json', '{"existing": true}');

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Config already exists');

        $config = json_decode(file_get_contents($tutiPath . '/config.json'), true);

        expect($config)->toHaveKey('existing', true);
    });

    it('overwrites config with --force flag', function (): void {
        $tutiPath = $this->testDir . '/.tuti';
        mkdir($tutiPath, 0755, true);
        file_put_contents($tutiPath . '/config.json', '{"existing": true}');

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--force' => true, '--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);

        $config = json_decode(file_get_contents($tutiPath . '/config.json'), true);

        expect($config)
            ->toHaveKey('version')
            ->not->toHaveKey('existing');
    });
});

// ─── Infrastructure Setup ───────────────────────────────────────────────

describe('InstallCommand Infrastructure Setup', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        Process::fake(['*docker*info*' => Process::result('Docker is running')]);
    });

    afterEach(function (): void {
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('installs and starts Traefik when not installed', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(false);
        $mock->shouldReceive('ensureNetworkExists')->once()->andReturn(true);
        $mock->shouldReceive('install')->once();
        $mock->shouldReceive('start')->once();
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays infrastructure setup messages when installing', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(false);
        $mock->shouldReceive('ensureNetworkExists')->once()->andReturn(true);
        $mock->shouldReceive('install')->once();
        $mock->shouldReceive('start')->once();
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Setting Up Infrastructure')
            ->expectsOutputToContain('Creating Docker network: traefik_proxy')
            ->expectsOutputToContain('Installing Traefik reverse proxy')
            ->expectsOutputToContain('Starting Traefik containers');
    });

    it('starts Traefik when installed but not running', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('isRunning')->andReturn(false);
        $mock->shouldReceive('install')->never();
        $mock->shouldReceive('start')->once();
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays message when starting existing Traefik', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('isRunning')->andReturn(false);
        $mock->shouldReceive('install')->never();
        $mock->shouldReceive('start')->once();
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Starting Traefik...');
    });

    it('skips infrastructure when already running', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('install')->never();
        $mock->shouldReceive('start')->never();
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays skipped messages when infrastructure already running', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('install')->never();
        $mock->shouldReceive('start')->never();
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Traefik infrastructure already installed')
            ->expectsOutputToContain('Traefik is running');
    });

    it('skips infrastructure with --skip-infra flag', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->never();
        $mock->shouldReceive('isRunning')->never();
        $mock->shouldReceive('install')->never();
        $mock->shouldReceive('start')->never();
        $mock->shouldReceive('ensureNetworkExists')->never();
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => false, 'running' => false, 'health' => 'not_installed'],
            'network' => ['installed' => false, 'running' => false, 'health' => 'missing'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays /etc/hosts information after infrastructure setup', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(false);
        $mock->shouldReceive('ensureNetworkExists')->once()->andReturn(true);
        $mock->shouldReceive('install')->once();
        $mock->shouldReceive('start')->once();
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('traefik.local.test')
            ->expectsOutputToContain('/etc/hosts');
    });
});

// ─── Success Display ────────────────────────────────────────────────────

describe('InstallCommand Success Display', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        Process::fake(['*docker*info*' => Process::result('Docker is running')]);
    });

    afterEach(function (): void {
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('displays installation summary on success', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Installation Summary')
            ->expectsOutputToContain('Global Tuti Directory')
            ->expectsOutputToContain('Infrastructure Status');
    });

    it('displays configuration information in summary', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('config.json')
            ->expectsOutputToContain('stacks/')
            ->expectsOutputToContain('cache/')
            ->expectsOutputToContain('logs/')
            ->expectsOutputToContain('infrastructure/');
    });

    it('displays infrastructure status in summary', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Traefik')
            ->expectsOutputToContain('Network')
            ->expectsOutputToContain('Dashboard');
    });



    it('displays dashboard URL when infrastructure is running', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://traefik.local.test');
    });
});

// ─── Error Handling ─────────────────────────────────────────────────────

describe('InstallCommand Error Handling', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        Process::fake(['*docker*info*' => Process::result('Docker is running')]);
    });

    afterEach(function (): void {
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('catches exceptions and returns failure', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andThrow(new RuntimeException('Connection refused'));
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => false, 'running' => false, 'health' => 'not_installed'],
            'network' => ['installed' => false, 'running' => false, 'health' => 'missing'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::FAILURE);
    });

    it('displays error message when exception occurs', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andThrow(new RuntimeException('Connection refused'));
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => false, 'running' => false, 'health' => 'not_installed'],
            'network' => ['installed' => false, 'running' => false, 'health' => 'missing'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Installation failed');
    });

    it('displays helpful hints on failure', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andThrow(new RuntimeException('Connection refused'));
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => false, 'running' => false, 'health' => 'not_installed'],
            'network' => ['installed' => false, 'running' => false, 'health' => 'missing'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Try running with sudo or check directory permissions')
            ->expectsOutputToContain('Ensure Docker is running');
    });

    it('catches generic exceptions and displays failure message', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andThrow(new Exception('Generic error occurred'));
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => false, 'running' => false, 'health' => 'not_installed'],
            'network' => ['installed' => false, 'running' => false, 'health' => 'missing'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Installation failed: Generic error occurred');
    });

    it('returns failure when infrastructure installation fails', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(false);
        $mock->shouldReceive('ensureNetworkExists')->once()->andReturn(true);
        $mock->shouldReceive('install')->andThrow(new RuntimeException('Install failed'));
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::FAILURE);
    });

    it('returns failure when infrastructure start fails', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('isRunning')->andReturn(false);
        $mock->shouldReceive('start')->andThrow(new RuntimeException('Start failed'));
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::FAILURE);
    });

    it('handles directory creation failure gracefully', function (): void {
        // Make HOME point to a file (cannot create directory)
        $nonCreatablePath = sys_get_temp_dir() . '/tuti-file-' . uniqid();
        file_put_contents($nonCreatablePath, 'test');

        putenv('HOME=' . $nonCreatablePath);
        $_ENV['HOME'] = $nonCreatablePath;
        $_SERVER['HOME'] = $nonCreatablePath;

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        try {
            $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
                ->assertExitCode(Command::FAILURE);
        } finally {
            // Cleanup
            unlink($nonCreatablePath);
            unset($_ENV['HOME'], $_SERVER['HOME']);
        }
    });
});

// ─── Infrastructure Setup Scenarios ──────────────────────────────────────

describe('InstallCommand Infrastructure Setup Scenarios', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        Process::fake(['*docker*info*' => Process::result('Docker is running')]);
    });

    afterEach(function (): void {
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });





    it('installs and starts Traefik when not installed', function (): void {
        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(false);
        $fakeInfra->setRunning(false);
        $this->app->instance(\App\Contracts\InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Creating Docker network: traefik_proxy')
            ->expectsOutputToContain('Installing Traefik reverse proxy')
            ->expectsOutputToContain('Starting Traefik containers')
            ->expectsOutputToContain('Traefik configuration installed')
            ->expectsOutputToContain('Traefik started');

        // Verify method calls
        expect($fakeInfra->installCalled)->toBeTrue();
        expect($fakeInfra->installCallCount)->toBe(1);
        expect($fakeInfra->startCalled)->toBeTrue();
        expect($fakeInfra->startCallCount)->toBe(1);
        expect($fakeInfra->isRunning())->toBeTrue();
    });
});

// ─── Edge Cases ─────────────────────────────────────────────────────────

describe('InstallCommand Edge Cases', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        Process::fake(['*docker*info*' => Process::result('Docker is running')]);
    });

    afterEach(function (): void {
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('handles multiple install calls idempotently', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('isInstalled')->andReturn(true);
        $mock->shouldReceive('isRunning')->andReturn(true);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        // First install
        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);

        // Second install should be idempotent
        $this->artisan('install', ['--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);
    });

    it('handles HOME environment variable not set gracefully', function (): void {
        putenv('HOME=');
        unset($_ENV['HOME'], $_SERVER['HOME']);

        // Set USERPROFILE for Windows fallback
        putenv('USERPROFILE=' . $this->testDir);
        $_ENV['USERPROFILE'] = $this->testDir;
        $_SERVER['USERPROFILE'] = $this->testDir;

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        try {
            $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
                ->assertExitCode(Command::SUCCESS);
        } finally {
            // Reset
            putenv('USERPROFILE=');
            unset($_ENV['USERPROFILE'], $_SERVER['USERPROFILE']);
        }
    });

    it('throws exception when cannot determine home directory', function (): void {
        // Clear all home directory detection methods
        putenv('HOME=');
        putenv('USERPROFILE=');
        putenv('USER=');
        putenv('USERNAME=');
        unset($_ENV['HOME'], $_ENV['USERPROFILE'], $_ENV['USER'], $_ENV['USERNAME']);
        unset($_SERVER['HOME'], $_SERVER['USERPROFILE']);

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Unable to determine home directory. Please set the HOME environment variable.');
    });

    it('preserves existing files in directories on re-install', function (): void {
        // First install
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);

        // Add a file to stacks directory
        $existingFile = $this->testDir . '/.tuti/stacks/my-stack.txt';
        file_put_contents($existingFile, 'test content');

        // Re-install without force
        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);

        expect($existingFile)->toBeFile();
    });

    it('displays section headers for each major step', function (): void {
        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Checking Prerequisites')
            ->expectsOutputToContain('Setting Up Global Directory')
            ->expectsOutputToContain('Installation Summary');
    });

    it('works with all options combined', function (): void {
        // Create existing install
        mkdir($this->testDir . '/.tuti', 0755, true);

        $mock = Mockery::mock(InfrastructureManagerInterface::class);
        $mock->shouldReceive('getStatus')->andReturn([
            'traefik' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
            'network' => ['installed' => true, 'running' => true, 'health' => 'healthy'],
        ]);
        $this->app->instance(InfrastructureManagerInterface::class, $mock);

        $this->artisan('install', ['--force' => true, '--skip-infra' => true, '--no-interaction' => true])
            ->assertExitCode(Command::SUCCESS);
    });
});