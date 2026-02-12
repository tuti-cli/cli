<?php

declare(strict_types=1);

/**
 * DoctorCommand Feature Tests
 *
 * Tests the `tuti doctor` command which performs system health checks.
 *
 * @see \App\Commands\DoctorCommand
 */

use App\Contracts\InfrastructureManagerInterface;
use App\Services\Debug\DebugLogService;
use App\Services\Project\ProjectDirectoryService;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

// ─── Registration ───────────────────────────────────────────────────────

describe('DoctorCommand', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(\App\Commands\DoctorCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(\App\Commands\DoctorCommand::class);

        expect($command->getName())->toBe('doctor');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(\App\Commands\DoctorCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(\App\Concerns\HasBrandedOutput::class);
    });

    it('has --fix option', function (): void {
        $command = $this->app->make(\App\Commands\DoctorCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('fix'))->toBeTrue();
    });

    it('has correct description', function (): void {
        $command = $this->app->make(\App\Commands\DoctorCommand::class);

        expect($command->getDescription())->toBe('Check system requirements and diagnose issues');
    });
});

// ─── Docker Check ───────────────────────────────────────────────────────

describe('DoctorCommand Docker Check', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");

        // Create global .tuti directory for these tests (needed for command to pass)
        if (! is_dir($this->testDir . '/.tuti')) {
            mkdir($this->testDir . '/.tuti', 0755, true);
        }
        // Create logs directory to avoid DebugLogService errors
        if (! is_dir($this->testDir . '/.tuti/logs')) {
            mkdir($this->testDir . '/.tuti/logs', 0755, true);
        }

        // Reset and rebind DebugLogService singleton with fresh HOME path
        DebugLogService::resetInstance();
        $this->app->instance(DebugLogService::class, DebugLogService::getInstance());

        // Change to a subdirectory without .tuti to avoid project detection
        $workDir = $this->testDir . '/work';
        mkdir($workDir, 0755, true);
        chdir($workDir);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);
    });

    afterEach(function (): void {
        chdir('/var/www/html');
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('fails when Docker is not installed', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result(exitCode: 1, errorOutput: 'command not found'),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('❌ Docker not found')
            ->expectsOutputToContain('Docker is not installed');
    });

    it('displays helpful hint when Docker is not installed', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result(exitCode: 1, errorOutput: 'command not found'),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('https://www.docker.com/products/docker-desktop');
    });

    it('fails when Docker daemon is not running', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result(exitCode: 1, errorOutput: 'Cannot connect'),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('❌ Docker daemon not running')
            ->expectsOutputToContain('Docker daemon is not running');
    });

    it('displays hint when Docker daemon is not running', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result(exitCode: 1, errorOutput: 'Cannot connect'),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Start Docker Desktop or run "sudo systemctl start docker"');
    });

    it('passes when Docker is installed and daemon is running', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result('Docker Compose version v2.30.0'),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('✅ Docker version 27.0.0')
            ->expectsOutputToContain('✅ Docker daemon is running');
    });
});

// ─── Docker Compose Check ────────────────────────────────────────────────

describe('DoctorCommand Docker Compose Check', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");

        // Create global .tuti directory for these tests
        if (! is_dir($this->testDir . '/.tuti')) {
            mkdir($this->testDir . '/.tuti', 0755, true);
        }
        mkdir($this->testDir . '/.tuti/logs', 0755, true);

        // Reset and rebind DebugLogService singleton with fresh HOME path
        DebugLogService::resetInstance();
        $this->app->instance(DebugLogService::class, DebugLogService::getInstance());

        // Change to a subdirectory without .tuti to avoid project detection
        $workDir = $this->testDir . '/work';
        mkdir($workDir, 0755, true);
        chdir($workDir);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);
    });

    afterEach(function (): void {
        chdir('/var/www/html');
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('fails when Docker Compose is not available', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result(exitCode: 1, errorOutput: 'not found'),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('❌ Docker Compose not found')
            ->expectsOutputToContain('Docker Compose is not available');
    });

    it('displays helpful hint when Docker Compose is not available', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result(exitCode: 1, errorOutput: 'not found'),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Docker Compose V2 is included with Docker Desktop');
    });

    it('passes when Docker Compose is available', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result('Docker Compose version v2.30.0'),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('✅ Docker Compose version v2.30.0');
    });
});

// ─── Global Configuration Check ──────────────────────────────────────────

describe('DoctorCommand Global Configuration Check', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");

        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result('Docker Compose version v2.30.0'),
        ]);

        // Create global .tuti directory for these tests
        if (! is_dir($this->testDir . '/.tuti')) {
            mkdir($this->testDir . '/.tuti', 0755, true);
        }
        mkdir($this->testDir . '/.tuti/logs', 0755, true);

        // Reset and rebind DebugLogService singleton with fresh HOME path
        DebugLogService::resetInstance();
        $this->app->instance(DebugLogService::class, DebugLogService::getInstance());

        // Change to a subdirectory without .tuti to avoid project detection
        $workDir = $this->testDir . '/work';
        mkdir($workDir, 0755, true);
        chdir($workDir);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);
    });

    afterEach(function (): void {
        chdir('/var/www/html');
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('fails when global .tuti directory does not exist', function (): void {
        // Remove the directory that beforeEach created (recursively)
        $tutiDir = $this->testDir . '/.tuti';
        if (is_dir($tutiDir)) {
            // Delete logs subdirectory first
            if (is_dir($tutiDir . '/logs')) {
                rmdir($tutiDir . '/logs');
            }
            rmdir($tutiDir);
        }

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('❌ Global directory not found:')
            ->expectsOutputToContain('Global tuti directory not found');
    });

    it('displays hint when global directory is missing', function (): void {
        // Remove the directory that beforeEach created (recursively)
        $tutiDir = $this->testDir . '/.tuti';
        if (is_dir($tutiDir)) {
            // Delete logs subdirectory first
            if (is_dir($tutiDir . '/logs')) {
                rmdir($tutiDir . '/logs');
            }
            rmdir($tutiDir);
        }

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Run "tuti install" to set up tuti-cli');
    });

    it('passes when global .tuti directory exists', function (): void {
        // Directory is created in beforeEach, so this should pass
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('✅ Global directory:');
    });

    it('warns when config.json is missing from global directory', function (): void {
        // Directory is created in beforeEach, config.json doesn't exist
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('⚠️  config.json not found');
    });

    it('shows success when config.json exists', function (): void {
        file_put_contents($this->testDir . '/.tuti/config.json', '{"test": true}');

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('✅ config.json exists');
    });
});

// ─── Infrastructure Check ───────────────────────────────────────────────

describe('DoctorCommand Infrastructure Check', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        mkdir($this->testDir . '/.tuti', 0755, true);
        mkdir($this->testDir . '/.tuti/logs', 0755, true);

        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result('Docker Compose version v2.30.0'),
        ]);

        // Reset and rebind DebugLogService singleton with fresh HOME path
        DebugLogService::resetInstance();
        $this->app->instance(DebugLogService::class, DebugLogService::getInstance());

        // Change to a subdirectory without .tuti to avoid project detection
        $workDir = $this->testDir . '/work';
        mkdir($workDir, 0755, true);
        chdir($workDir);
    });

    afterEach(function (): void {
        chdir('/var/www/html');
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('fails when Traefik is not installed', function (): void {
        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(false);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('❌ Traefik not installed')
            ->expectsOutputToContain('Traefik infrastructure not installed');
    });

    it('displays hint when Traefik is not installed', function (): void {
        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(false);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Run "tuti install" to set up infrastructure');
    });

    it('warns when Traefik is installed but not running', function (): void {
        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('⚠️  Traefik not running')
            ->expectsOutputToContain('1 warning(s) found:');
    });

    it('displays hint when Traefik is not running', function (): void {
        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Run "tuti infra:start" to start Traefik');
    });

    it('passes when Traefik is installed and running', function (): void {
        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('✅ Traefik installed')
            ->expectsOutputToContain('✅ Traefik is running');
    });
});

// ─── Summary Display ────────────────────────────────────────────────────

describe('DoctorCommand Summary Display', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        mkdir($this->testDir . '/.tuti', 0755, true);
        mkdir($this->testDir . '/.tuti/logs', 0755, true);

        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result('Docker Compose version v2.30.0'),
        ]);

        // Reset and rebind DebugLogService singleton with fresh HOME path
        DebugLogService::resetInstance();
        $this->app->instance(DebugLogService::class, DebugLogService::getInstance());

        // Change to a subdirectory without .tuti to avoid project detection
        $workDir = $this->testDir . '/work';
        mkdir($workDir, 0755, true);
        chdir($workDir);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);
    });

    afterEach(function (): void {
        chdir('/var/www/html');
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('displays success message when all checks pass', function (): void {
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('All checks passed! Your system is ready to use tuti-cli.');
    });

    it('displays warning count when warnings exist', function (): void {
        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('⚠ 1 warning(s) found:');
    });

    it('displays issue count when issues exist', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result(exitCode: 1, errorOutput: 'not found'),
        ]);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(false);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('2 issue(s) found:');
    });

    it('returns failure when issues exist', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result(exitCode: 1, errorOutput: 'not found'),
        ]);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(false);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE);
    });

    it('returns success when only warnings exist', function (): void {
        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays fix hint when issues exist', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result(exitCode: 1, errorOutput: 'not found'),
        ]);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(false);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Fix the issues above and run "tuti doctor" again');
    });
});

// ─── Current Project Check ────────────────────────────────────────────

describe('DoctorCommand Current Project Check', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        mkdir($this->testDir . '/.tuti', 0755, true);
        mkdir($this->testDir . '/.tuti/logs', 0755, true);

        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result('Docker Compose version v2.30.0'),
        ]);

        // Reset and rebind DebugLogService singleton with fresh HOME path
        DebugLogService::resetInstance();
        $this->app->instance(DebugLogService::class, DebugLogService::getInstance());

        // NOTE: Do NOT mock ProjectDirectoryService here - these tests need real project checking

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);
    });

    afterEach(function (): void {
        chdir('/');
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('skips project check when not in a project directory', function (): void {
        // Change to a directory without .tuti (e.g., /tmp or a new subdir)
        $nonProjectDir = $this->testDir . '/nonproject';
        mkdir($nonProjectDir, 0755, true);
        chdir($nonProjectDir);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->doesntExpectOutput('Current Project');
    });

    it('checks project when in a project directory', function (): void {
        // Set up a project directory structure with config.json
        $tutiPath = $this->testDir . '/.tuti';
        file_put_contents($tutiPath . '/config.json', '{"project": {"name": "test"}}');
        file_put_contents($tutiPath . '/docker-compose.yml', 'services: {}');

        chdir($this->testDir);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Current Project');
    });

    it('warns when project config.json is missing', function (): void {
        // .tuti exists but no config.json - project IS detected but config.json is missing
        $tutiPath = $this->testDir . '/.tuti';
        mkdir($tutiPath . '/docker', 0755, true);

        chdir($this->testDir);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Current Project')
            ->expectsOutputToContain('❌ config.json not found');
    });

    it('warns when project docker-compose.yml is missing', function (): void {
        $tutiPath = $this->testDir . '/.tuti';
        file_put_contents($tutiPath . '/config.json', '{"project": {"name": "test"}}');

        chdir($this->testDir);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('❌ docker-compose.yml not found')
            ->expectsOutputToContain('1 issue(s) found:');
    });

    it('warns when project Dockerfile is missing', function (): void {
        $tutiPath = $this->testDir . '/.tuti';
        file_put_contents($tutiPath . '/config.json', '{"project": {"name": "test"}}');
        file_put_contents($tutiPath . '/docker-compose.yml', 'services: {}');

        chdir($this->testDir);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('⚠️  Dockerfile not found')
            ->expectsOutputToContain('1 warning(s) found:');
    });

    it('warns when project .env is missing', function (): void {
        $tutiPath = $this->testDir . '/.tuti';
        mkdir($tutiPath . '/docker', 0755, true);
        file_put_contents($tutiPath . '/config.json', '{"project": {"name": "test"}}');
        file_put_contents($tutiPath . '/docker-compose.yml', 'services: {}');
        file_put_contents($tutiPath . '/docker/Dockerfile', 'FROM php:8.4');

        chdir($this->testDir);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('⚠️  .env not found in .tuti/');
    });

    it('fails when docker-compose.yml has syntax errors', function (): void {
        $tutiPath = $this->testDir . '/.tuti';
        file_put_contents($tutiPath . '/config.json', '{"project": {"name": "test"}}');
        file_put_contents($tutiPath . '/docker-compose.yml', 'invalid yaml: [');

        chdir($this->testDir);

        Process::fake([
            '*docker*compose*config*' => Process::result(
                exitCode: 1,
                errorOutput: 'invalid YAML syntax'
            ),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('❌ docker-compose.yml has errors')
            ->expectsOutputToContain('docker-compose.yml has syntax errors');
    });

    it('passes when all project files exist and are valid', function (): void {
        $tutiPath = $this->testDir . '/.tuti';
        mkdir($tutiPath . '/docker', 0755, true);
        file_put_contents($tutiPath . '/config.json', '{"project": {"name": "test"}}');
        file_put_contents($tutiPath . '/docker-compose.yml', 'services: {}');
        file_put_contents($tutiPath . '/docker/Dockerfile', 'FROM php:8.4');
        file_put_contents($tutiPath . '/.env', 'APP_ENV=dev');

        chdir($this->testDir);

        Process::fake([
            '*docker*compose*config*' => Process::result(''),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('📁 .tuti directory:')
            ->expectsOutputToContain('✅ config.json exists')
            ->expectsOutputToContain('✅ docker-compose.yml exists')
            ->expectsOutputToContain('✅ docker-compose.yml syntax valid')
            ->expectsOutputToContain('✅ Dockerfile exists')
            ->expectsOutputToContain('✅ .env exists');
    });
});

// ─── Section Headers ────────────────────────────────────────────────────

describe('DoctorCommand Section Headers', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        mkdir($this->testDir . '/.tuti', 0755, true);
        // Create logs directory to avoid DebugLogService errors
        mkdir($this->testDir . '/.tuti/logs', 0755, true);

        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result('Docker Compose version v2.30.0'),
        ]);

        // Reset and rebind DebugLogService singleton with fresh HOME path
        DebugLogService::resetInstance();
        $this->app->instance(DebugLogService::class, DebugLogService::getInstance());

        // Change to a subdirectory without .tuti to avoid project detection
        $workDir = $this->testDir . '/work';
        mkdir($workDir, 0755, true);
        chdir($workDir);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);
    });

    afterEach(function (): void {
        chdir('/var/www/html');
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('displays branded header', function (): void {
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('System Health Check');
    });

    it('displays Docker section', function (): void {
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Docker');
    });

    it('displays Docker Compose section', function (): void {
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Docker Compose');
    });

    it('displays Global Configuration section', function (): void {
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Global Configuration');
    });

    it('displays Infrastructure section', function (): void {
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Infrastructure');
    });

    it('displays Debug section', function (): void {
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Debug');
    });

    it('displays Summary section', function (): void {
        $this->artisan('doctor')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Summary');
    });
});

// ─── Edge Cases ─────────────────────────────────────────────────────────

describe('DoctorCommand Edge Cases', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalHome = getenv('HOME');
        putenv("HOME={$this->testDir}");
        mkdir($this->testDir . '/.tuti', 0755, true);
        mkdir($this->testDir . '/.tuti/logs', 0755, true);

        Process::fake([
            '*docker*--version*' => Process::result('Docker version 27.0.0'),
            '*docker*info*' => Process::result('Server Version: 27.0.0'),
            '*docker*compose*version*' => Process::result('Docker Compose version v2.30.0'),
        ]);

        // Reset and rebind DebugLogService singleton with fresh HOME path
        DebugLogService::resetInstance();
        $this->app->instance(DebugLogService::class, DebugLogService::getInstance());

        // Change to a subdirectory without .tuti to avoid project detection
        $workDir = $this->testDir . '/work';
        mkdir($workDir, 0755, true);
        chdir($workDir);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);
    });

    afterEach(function (): void {
        chdir('/var/www/html');
        putenv("HOME={$this->originalHome}");
        cleanupTestDirectory($this->testDir);
    });

    it('handles multiple issues and warnings together', function (): void {
        Process::fake([
            '*docker*--version*' => Process::result(exitCode: 1, errorOutput: 'not found'),
        ]);

        $fakeInfra = new \Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('1 warning(s) found:')
            ->expectsOutputToContain('1 issue(s) found:');
    });

    it('works with --fix option (option exists but no auto-fix implemented)', function (): void {
        $this->artisan('doctor', ['--fix' => true])
            ->assertExitCode(Command::SUCCESS);
    });

    it('handles both warnings and issues in project directory', function (): void {
        $tutiPath = $this->testDir . '/.tuti';
        mkdir($tutiPath . '/docker', 0755, true);
        // Create invalid compose file (error) - note: DoctorCommand looks for .tuti/docker-compose.yml
        file_put_contents($tutiPath . '/docker-compose.yml', 'invalid: [');
        // config.json exists
        file_put_contents($tutiPath . '/config.json', '{"project": {"name": "test"}}');
        // Dockerfile missing (warning)

        chdir($this->testDir);

        Process::fake([
            '*docker*compose*config*' => Process::result(
                exitCode: 1,
                errorOutput: 'invalid YAML syntax'
            ),
        ]);

        $this->artisan('doctor')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('1 warning(s) found:')
            ->expectsOutputToContain('1 issue(s) found:');
    });
});
