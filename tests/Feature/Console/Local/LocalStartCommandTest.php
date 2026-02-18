<?php

declare(strict_types=1);

/**
 * LocalStartCommand Feature Tests
 *
 * Tests the `local:start` command which starts the local development environment.
 *
 * @see StartCommand
 */

use App\Commands\Local\StartCommand;
use App\Contracts\InfrastructureManagerInterface;
use App\Contracts\OrchestratorInterface;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeDockerOrchestrator;
use Tests\Mocks\FakeInfrastructureManager;

function createStartTestProject(array $configOverrides = []): string
{
    $dir = sys_get_temp_dir() . '/tuti-start-test-' . bin2hex(random_bytes(4));
    mkdir($dir . '/.tuti', 0755, true);

    $config = [
        'project' => ['name' => 'test-project', 'type' => 'laravel', 'version' => '1.0.0'],
        'environments' => ['current' => 'dev', 'dev' => ['services' => new stdClass()]],
    ];

    if ($configOverrides !== []) {
        $config = array_replace_recursive($config, $configOverrides);
    }

    file_put_contents(
        $dir . '/.tuti/config.json',
        json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    file_put_contents(
        $dir . '/.tuti/docker-compose.yml',
        "services:\n  app:\n    image: php:8.4"
    );

    return $dir;
}

// ─── Registration ───────────────────────────────────────────────────────

describe('LocalStartCommand', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(StartCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(StartCommand::class);

        expect($command->getName())->toBe('local:start');
    });

    it('uses HasBrandedOutput and BuildsProjectUrls traits', function (): void {
        $command = $this->app->make(StartCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)
            ->toContain(App\Concerns\HasBrandedOutput::class)
            ->toContain(App\Concerns\BuildsProjectUrls::class);
    });

    it('has --skip-infra option', function (): void {
        $command = $this->app->make(StartCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('skip-infra'))->toBeTrue();
    });

    it('has correct description', function (): void {
        $command = $this->app->make(StartCommand::class);

        expect($command->getDescription())->toBe('Start the local development environment');
    });
});

// ─── Project Validation ───────────────────────────────────────────────

describe('LocalStartCommand Project Validation', function (): void {

    it('fails when no .tuti directory exists', function (): void {
        $testDir = sys_get_temp_dir() . '/tuti-start-noinit-' . bin2hex(random_bytes(4));
        mkdir($testDir, 0755, true);
        $originalDir = getcwd();
        chdir($testDir);

        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:start', ['--skip-infra' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('No tuti project found');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });

    it('displays hint when no .tuti directory', function (): void {
        $testDir = sys_get_temp_dir() . '/tuti-start-hint-' . bin2hex(random_bytes(4));
        mkdir($testDir, 0755, true);
        $originalDir = getcwd();
        chdir($testDir);

        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:start', ['--skip-infra' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('tuti stack:laravel');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });

    it('fails when docker-compose.yml is missing', function (): void {
        $testDir = sys_get_temp_dir() . '/tuti-start-nocomp-' . bin2hex(random_bytes(4));
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

        $this->artisan('local:start', ['--skip-infra' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('docker-compose.yml not found');

        chdir($originalDir);
        File::deleteDirectory($testDir);
    });
});

// ─── Infrastructure Check ─────────────────────────────────────────────

describe('LocalStartCommand Infrastructure Check', function (): void {

    beforeEach(function (): void {
        $this->testDir = createStartTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $this->fakeOrchestrator = new FakeDockerOrchestrator();
        $this->fakeOrchestrator->startResult = true;
        $this->app->instance(OrchestratorInterface::class, $this->fakeOrchestrator);
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

        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS);

        expect($fakeInfra->ensureReadyCalled)->toBeTrue();
    });

    it('skips infrastructure check with --skip-infra', function (): void {
        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(false);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:start', ['--skip-infra' => true])
            ->assertExitCode(Command::SUCCESS);

        expect($fakeInfra->ensureReadyCalled)->toBeFalse();
    });

    it('succeeds when infrastructure is already running', function (): void {
        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Infrastructure is running');
    });
});

// ─── Container Start ──────────────────────────────────────────────────

describe('LocalStartCommand Container Start', function (): void {

    beforeEach(function (): void {
        $this->testDir = createStartTestProject();
        $this->originalDir = getcwd();
        chdir($this->testDir);

        $this->fakeOrchestrator = new FakeDockerOrchestrator();
        $this->fakeOrchestrator->startResult = true;
        $this->app->instance(OrchestratorInterface::class, $this->fakeOrchestrator);

        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);
    });

    afterEach(function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);
    });

    it('starts containers successfully', function (): void {
        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->startCalled)->toBeTrue();
    });

    it('displays project name during start', function (): void {
        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('test-project');
    });

    it('displays containers started message', function (): void {
        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Containers started');
    });

    it('displays project is running message', function (): void {
        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Project is running');
    });

    it('displays application URL', function (): void {
        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://test-project.local.test');
    });

    it('displays Traefik dashboard URL', function (): void {
        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://traefik.local.test');
    });

    it('displays service URLs when services are configured', function (): void {
        chdir($this->originalDir);
        File::deleteDirectory($this->testDir);

        $this->testDir = createStartTestProject([
            'environments' => [
                'current' => 'dev',
                'dev' => [
                    'services' => [
                        'mail' => ['mailpit'],
                        'workers' => ['horizon'],
                    ],
                ],
            ],
        ]);
        chdir($this->testDir);

        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('https://mail.test-project.local.test')
            ->expectsOutputToContain('horizon');
    });

    it('displays helpful hints after start', function (): void {
        $this->artisan('local:start')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('tuti local:status')
            ->expectsOutputToContain('tuti local:logs');
    });
});

// ─── Error Handling ───────────────────────────────────────────────────

describe('LocalStartCommand Error Handling', function (): void {

    beforeEach(function (): void {
        $this->testDir = createStartTestProject();
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

    it('returns failure when start fails', function (): void {
        $fakeOrchestrator = new FakeDockerOrchestrator();
        $fakeOrchestrator->startResult = false;
        $this->app->instance(OrchestratorInterface::class, $fakeOrchestrator);

        $this->artisan('local:start', ['--skip-infra' => true])
            ->assertExitCode(Command::FAILURE);
    });

    it('displays error message on start failure', function (): void {
        $fakeOrchestrator = new FakeDockerOrchestrator();
        $fakeOrchestrator->startResult = false;
        $this->app->instance(OrchestratorInterface::class, $fakeOrchestrator);

        $this->artisan('local:start', ['--skip-infra' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed to start project');
    });

    it('displays helpful hints on failure', function (): void {
        $fakeOrchestrator = new FakeDockerOrchestrator();
        $fakeOrchestrator->startResult = false;
        $this->app->instance(OrchestratorInterface::class, $fakeOrchestrator);

        $this->artisan('local:start', ['--skip-infra' => true])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Ensure Docker is running');
    });

    it('returns failure when infrastructure check fails', function (): void {
        $fakeInfra = new FakeInfrastructureManager();
        $fakeInfra->setInstalled(false);
        $fakeInfra->setRunning(false);
        $fakeInfra->ensureReadyResult = false;
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('local:start')
            ->assertExitCode(Command::FAILURE);
    });
});
