<?php

declare(strict_types=1);

/**
 * LocalRebuildCommand Feature Tests
 *
 * Tests the `local:rebuild` command which rebuilds containers to apply configuration changes.
 *
 * @see \App\Commands\Local\RebuildCommand
 */

use App\Commands\Local\RebuildCommand;
use App\Contracts\InfrastructureManagerInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeInfrastructureManager;

function createRebuildTestProject(bool $withDevCompose = true, bool $withEnv = true): string
{
    $dir = sys_get_temp_dir() . '/tuti-rebuild-test-' . bin2hex(random_bytes(4));
    mkdir($dir . '/.tuti', 0755, true);

    file_put_contents(
        $dir . '/.tuti/config.json',
        json_encode([
            'project' => ['name' => 'test-project', 'type' => 'laravel', 'version' => '1.0.0'],
            'environments' => ['current' => 'dev', 'dev' => ['services' => new stdClass()]],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    file_put_contents(
        $dir . '/.tuti/docker-compose.yml',
        "services:\n  app:\n    image: php:8.4"
    );

    if ($withDevCompose) {
        file_put_contents(
            $dir . '/.tuti/docker-compose.dev.yml',
            "services:\n  app:\n    volumes:\n      - .:/var/www"
        );
    }

    if ($withEnv) {
        file_put_contents($dir . '/.env', "APP_NAME=test\nAPP_ENV=dev");
    }

    return $dir;
}

/**
 * Helper to convert process command to string for assertions.
 * Handles both array commands (from Process::run([])) and string commands.
 */
function rebuildCommandStr(string|array $command): string
{
    if (is_array($command)) {
        return implode(' ', $command);
    }

    return str_replace("'", '', $command);
}

// ─── Registration ───────────────────────────────────────────────────────

describe('LocalRebuildCommand', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(RebuildCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(RebuildCommand::class);

        expect($command->getName())->toBe('local:rebuild');
    });

    it('has --no-cache, --force, and --detach options', function (): void {
        $command = $this->app->make(RebuildCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('no-cache'))->toBeTrue()
            ->and($definition->hasOption('force'))->toBeTrue()
            ->and($definition->hasOption('detach'))->toBeTrue();
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(RebuildCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(\App\Concerns\HasBrandedOutput::class);
    });

    it('has correct description', function (): void {
        $command = $this->app->make(RebuildCommand::class);

        expect($command->getDescription())->toBe('Rebuild containers to apply configuration changes');
    });
});

// ─── Project Validation ───────────────────────────────────────────────

describe('LocalRebuildCommand Project Validation', function (): void {

    it('fails when no .tuti directory exists', function (): void {
        $testDir = sys_get_temp_dir() . '/tuti-rebuild-noinit-' . bin2hex(random_bytes(4));
        mkdir($testDir, 0755, true);
        $originalDir = getcwd();
        chdir($testDir);

        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('No tuti project found');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });

    it('displays hint when no .tuti directory', function (): void {
        $testDir = sys_get_temp_dir() . '/tuti-rebuild-hint-' . bin2hex(random_bytes(4));
        mkdir($testDir, 0755, true);
        $originalDir = getcwd();
        chdir($testDir);

        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('tuti stack:laravel');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });

    it('fails when docker-compose.yml is missing', function (): void {
        $testDir = sys_get_temp_dir() . '/tuti-rebuild-nocomp-' . bin2hex(random_bytes(4));
        mkdir($testDir . '/.tuti', 0755, true);
        file_put_contents($testDir . '/.tuti/config.json', json_encode([
            'project' => ['name' => 'test-project', 'type' => 'laravel', 'version' => '1.0.0'],
            'environments' => ['current' => 'dev', 'dev' => ['services' => new stdClass()]],
        ], JSON_THROW_ON_ERROR));
        // No docker-compose.yml

        $originalDir = getcwd();
        chdir($testDir);

        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('docker-compose.yml not found');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });
});

// ─── Infrastructure Check ─────────────────────────────────────────────

describe('LocalRebuildCommand Infrastructure Check', function (): void {

    beforeEach(function (): void {
        $this->testDir = createRebuildTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        Process::fake([
            '*docker*compose*' => Process::result(''),
        ]);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('ensures infrastructure is ready when not running', function (): void {
        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS);

        expect($fakeInfra->ensureReadyCalled)->toBeTrue();
    });

    it('succeeds when infrastructure is already running', function (): void {
        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Infrastructure is running');
    });
});

// ─── Container Rebuild ────────────────────────────────────────────────

describe('LocalRebuildCommand Container Rebuild', function (): void {

    beforeEach(function (): void {
        $this->testDir = createRebuildTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        Process::fake([
            '*docker*compose*' => Process::result(''),
        ]);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('rebuilds containers successfully', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS);

        Process::assertRan(fn ($process) => str_contains(rebuildCommandStr($process->command), 'docker compose') &&
            str_contains(rebuildCommandStr($process->command), 'down'));

        Process::assertRan(fn ($process) => str_contains(rebuildCommandStr($process->command), 'docker compose') &&
            str_contains(rebuildCommandStr($process->command), 'build'));

        Process::assertRan(fn ($process) => str_contains(rebuildCommandStr($process->command), 'docker compose') &&
            str_contains(rebuildCommandStr($process->command), 'up'));
    });

    it('displays project name', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('test-project');
    });

    it('displays containers built message', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Containers built successfully');
    });

    it('displays containers started message', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Containers started');
    });

    it('displays rebuild complete message', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Rebuild Complete');
    });

    it('displays project is running message', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Project is running');
    });

    it('displays project URL', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://test-project.local.test');
    });

    it('includes dev compose file when exists', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS);

        Process::assertRan(fn ($process) => str_contains(rebuildCommandStr($process->command), 'docker-compose.dev.yml'));
    });

    it('includes env file when exists', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS);

        Process::assertRan(fn ($process) => str_contains(rebuildCommandStr($process->command), '--env-file'));
    });

    it('adds --pull flag to build command', function (): void {
        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS);

        Process::assertRan(fn ($process) => str_contains(rebuildCommandStr($process->command), 'build') &&
            str_contains(rebuildCommandStr($process->command), '--pull'));
    });
});

// ─── Options ──────────────────────────────────────────────────────────

describe('LocalRebuildCommand Options', function (): void {

    beforeEach(function (): void {
        $this->testDir = createRebuildTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        Process::fake([
            '*docker*compose*' => Process::result(''),
        ]);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('uses --no-cache flag for build', function (): void {
        $this->artisan('local:rebuild', ['--no-cache' => true])
            ->assertExitCode(Command::SUCCESS);

        Process::assertRan(fn ($process) => str_contains(rebuildCommandStr($process->command), 'build') &&
            str_contains(rebuildCommandStr($process->command), '--no-cache'));
    });

    it('skips down with --force flag', function (): void {
        $this->artisan('local:rebuild', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);

        Process::assertNotRan(fn ($process) => str_contains(rebuildCommandStr($process->command), 'down'));
    });

    it('runs build with --detach flag quietly', function (): void {
        $this->artisan('local:rebuild', ['--detach' => true])
            ->assertExitCode(Command::SUCCESS);

        Process::assertRan(fn ($process) => str_contains(rebuildCommandStr($process->command), 'build'));
    });
});

// ─── Error Handling ───────────────────────────────────────────────────

describe('LocalRebuildCommand Error Handling', function (): void {

    beforeEach(function (): void {
        $this->testDir = createRebuildTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('returns failure when build fails', function (): void {
        // Call order: 1=down, 2=build (fail), 3=up (never reached)
        $callIndex = 0;
        Process::fake(function () use (&$callIndex) {
            $callIndex++;

            return $callIndex === 2
                ? Process::result(errorOutput: 'Build error', exitCode: 1)
                : Process::result('');
        });

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to build containers');
    });

    it('displays helpful hints on build failure', function (): void {
        $callIndex = 0;
        Process::fake(function () use (&$callIndex) {
            $callIndex++;

            return $callIndex === 2
                ? Process::result(errorOutput: 'Build error', exitCode: 1)
                : Process::result('');
        });

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('--no-cache');
    });

    it('returns failure when start fails after build', function (): void {
        // Call order: 1=down, 2=build (ok), 3=up (fail)
        $callIndex = 0;
        Process::fake(function () use (&$callIndex) {
            $callIndex++;

            return $callIndex === 3
                ? Process::result(errorOutput: 'Start error', exitCode: 1)
                : Process::result('');
        });

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to start containers');
    });

    it('displays hints when start fails after build', function (): void {
        $callIndex = 0;
        Process::fake(function () use (&$callIndex) {
            $callIndex++;

            return $callIndex === 3
                ? Process::result(errorOutput: 'Start error', exitCode: 1)
                : Process::result('');
        });

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('tuti local:logs');
    });

    it('continues when down fails', function (): void {
        // Call order: 1=down (fail), 2=build (ok), 3=up (ok)
        $callIndex = 0;
        Process::fake(function () use (&$callIndex) {
            $callIndex++;

            return $callIndex === 1
                ? Process::result(errorOutput: 'Down error', exitCode: 1)
                : Process::result('');
        });

        $this->artisan('local:rebuild')
            ->assertExitCode(Command::SUCCESS);
    });
});
