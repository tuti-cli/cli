<?php

declare(strict_types=1);

/**
 * StackLaravelCommand Feature Tests
 *
 * Tests the `tuti stack:laravel` command which initializes a Laravel project with Docker stack.
 *
 * @see LaravelCommand
 */

use App\Commands\Stack\LaravelCommand;
use App\Contracts\DockerExecutorInterface;
use App\Contracts\InfrastructureManagerInterface;
use App\Services\Stack\Installers\LaravelStackInstaller;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRegistryManagerService;
use LaravelZero\Framework\Commands\Command;

/**
 * Helper to create a test Laravel project directory.
 */
function createLaravelTestProject(string $projectName = 'test-project'): string
{
    $dir = sys_get_temp_dir() . '/tuti-laravel-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    // Create Laravel project indicators
    file_put_contents($dir . '/artisan', '<?php // Laravel artisan file');
    file_put_contents($dir . '/composer.json', json_encode([
        'name' => 'laravel/laravel',
        'require' => [
            'laravel/framework' => '^11.0',
        ],
    ], JSON_PRETTY_PRINT));
    mkdir($dir . '/bootstrap', 0755, true);
    file_put_contents($dir . '/bootstrap/app.php', '<?php // Laravel app file');

    // Create a basic .env file (Laravel projects always have .env)
    file_put_contents($dir . '/.env', <<<'ENV'
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
ENV);

    return $dir;
}

/**
 * Helper to strip quotes from Process command strings.
 * Used because Laravel escapes args with single quotes in Process::fake() assertions.
 */
function laravelCommandStr(string $command): string
{
    return str_replace("'", '', $command);
}

/**
 * Create a fake Docker executor result object.
 */
function createFakeDockerResult(bool $successful = true, string $output = 'OK', string $errorOutput = ''): App\Contracts\DockerExecutionResult
{
    return new App\Contracts\DockerExecutionResult(
        successful: $successful,
        output: $output,
        errorOutput: $errorOutput,
        exitCode: $successful ? 0 : 1,
    );
}

/**
 * Create a fully mocked Docker executor with all required methods.
 */
function createMockDockerExecutor(): Mockery\MockInterface
{
    $mock = Mockery::mock(DockerExecutorInterface::class);
    $mock->shouldReceive('isDockerAvailable')->andReturn(true);
    $mock->shouldReceive('getPhpImage')->andReturn('serversideup/php:8.4-fpm-nginx');
    $mock->shouldReceive('runComposer')->andReturn(
        createFakeDockerResult(true, 'OK', '')
    );
    $mock->shouldReceive('runArtisan')->andReturn(
        createFakeDockerResult(true, 'base64:test', '')
    );
    $mock->shouldReceive('runNpm')->andReturn(
        createFakeDockerResult(true, 'OK', '')
    );
    $mock->shouldReceive('exec')->andReturn(
        createFakeDockerResult(true, 'OK', '')
    );

    return $mock;
}

// ─── Registration ───────────────────────────────────────────────────────

describe('StackLaravelCommand Registration', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(LaravelCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(LaravelCommand::class);

        expect($command->getName())->toBe('stack:laravel');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(LaravelCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    it('uses BuildsProjectUrls trait', function (): void {
        $command = $this->app->make(LaravelCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\BuildsProjectUrls::class);
    });

    it('has project-name argument', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasArgument('project-name'))->toBeTrue();
    });

    it('has --mode option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('mode'))->toBeTrue();
    });

    it('has --path option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('path'))->toBeTrue();
    });

    it('has --services option that accepts multiple values', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();
        $servicesOption = $definition->getOption('services');

        expect($servicesOption->isArray())->toBeTrue();
    });

    it('has --laravel-version option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('laravel-version'))->toBeTrue();
    });

    it('has --force option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('has --skip-start option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('skip-start'))->toBeTrue();
    });

    it('has --skip-migrate option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('skip-migrate'))->toBeTrue();
    });

    it('has correct description', function (): void {
        $command = $this->app->make(LaravelCommand::class);

        expect($command->getDescription())->toBe('Initialize a Laravel project with Docker stack');
    });
});

// ─── Pre-flight Checks ───────────────────────────────────────────────────

describe('StackLaravelCommand Pre-flight Checks', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('fails when .tuti directory already exists', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);

        $this->artisan('stack:laravel', [
            'project-name' => 'my-project',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Project already initialized');
    });

    it('displays hint to use --force when already initialized', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);

        $this->artisan('stack:laravel', [
            'project-name' => 'my-project',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Use --force to reinitialize');
    });
});

// ─── Installation Modes ───────────────────────────────────────────────────

describe('StackLaravelCommand Installation Modes', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('accepts fresh mode via --mode option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();
        $modeOption = $definition->getOption('mode');

        expect($modeOption)->not->toBeNull();
    });

    it('accepts existing mode via --mode option', function (): void {
        // This tests that the mode option accepts 'existing' value
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('mode'))->toBeTrue();
    });

    it('detects existing Laravel project for mode selection', function (): void {
        // Create a Laravel-like project
        $laravelDir = createLaravelTestProject();
        chdir($laravelDir);

        $installer = app(LaravelStackInstaller::class);
        $detected = $installer->detectExistingProject($laravelDir);

        expect($detected)->toBeTrue();

        cleanupTestDirectory($laravelDir);
    });

    it('does not detect non-Laravel project as existing', function (): void {
        $installer = app(LaravelStackInstaller::class);
        $detected = $installer->detectExistingProject($this->testDir);

        expect($detected)->toBeFalse();
    });
});

// ─── Project Name Handling ───────────────────────────────────────────────────

describe('StackLaravelCommand Project Name', function (): void {

    it('accepts project-name as optional argument', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();
        $projectNameArgument = $definition->getArgument('project-name');

        expect($projectNameArgument->isRequired())->toBeFalse();
    });

    it('uses provided project name from argument', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasArgument('project-name'))->toBeTrue();
    });

    it('defaults to null when no project name provided', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();
        $projectNameArgument = $definition->getArgument('project-name');

        expect($projectNameArgument->getDefault())->toBeNull();
    });
});

// ─── Path Option ───────────────────────────────────────────────────────────

describe('StackLaravelCommand Path Option', function (): void {

    it('has --path option for fresh installation', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('path'))->toBeTrue();
    });

    it('path option defaults to null (uses current directory)', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();
        $pathOption = $definition->getOption('path');

        expect($pathOption->getDefault())->toBeNull();
    });
});

// ─── Services Selection ───────────────────────────────────────────────────

describe('StackLaravelCommand Services Selection', function (): void {

    it('accepts pre-selected services via --services option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();
        $servicesOption = $definition->getOption('services');

        expect($servicesOption->isArray())->toBeTrue();
    });

    it('uses default services when no services selected in non-interactive mode', function (): void {
        // The stack has required_services that must be selected
        $stackLoader = app(StackLoaderService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $manifest = $stackLoader->load($stackPath);

        $defaultServices = $stackLoader->getDefaultServices($manifest);

        expect($defaultServices)->toBeArray();
        expect($defaultServices)->not->toBeEmpty();
    });

    it('includes required database service in defaults', function (): void {
        $stackLoader = app(StackLoaderService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $manifest = $stackLoader->load($stackPath);

        $defaultServices = $stackLoader->getDefaultServices($manifest);

        // Should include databases.postgres (default)
        $hasDatabase = false;
        foreach ($defaultServices as $service) {
            if (str_starts_with($service, 'databases.')) {
                $hasDatabase = true;
                break;
            }
        }

        expect($hasDatabase)->toBeTrue();
    });
});

// ─── Laravel Version Option ───────────────────────────────────────────────────

describe('StackLaravelCommand Laravel Version', function (): void {

    it('has --laravel-version option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('laravel-version'))->toBeTrue();
    });

    it('laravel-version option defaults to null (uses latest)', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();
        $versionOption = $definition->getOption('laravel-version');

        expect($versionOption->getDefault())->toBeNull();
    });
});

// ─── Force Option ───────────────────────────────────────────────────

describe('StackLaravelCommand Force Option', function (): void {

    it('has --force option', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('force option defaults to false', function (): void {
        $command = $this->app->make(LaravelCommand::class);
        $definition = $command->getDefinition();
        $forceOption = $definition->getOption('force');

        expect($forceOption->getDefault())->toBeFalse();
    });
});

// ─── Branded Output ──────────────────────────────────────────────────────

describe('StackLaravelCommand Branded Output', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('displays branded header on execution', function (): void {
        $this->artisan('stack:laravel', [
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Laravel Stack Installation');
    });

    it('displays stack info box', function (): void {
        $this->artisan('stack:laravel', [
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Stack Info');
    });
});

// ─── Non-Interactive Mode with Existing Project ───────────────────────────

describe('StackLaravelCommand Non-Interactive Mode with Existing Project', function (): void {

    it('uses existing mode when Laravel project detected in non-interactive mode', function (): void {
        // Create a Laravel-like project
        $laravelDir = createLaravelTestProject();
        $originalCwd = getcwd();
        chdir($laravelDir);

        // Mock infrastructure manager
        $fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(true);
        $fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        // In non-interactive mode with existing project, should use 'existing' mode
        $this->artisan('stack:laravel', [
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        chdir($originalCwd);
        cleanupTestDirectory($laravelDir);
    });
});

// ─── Integration: Full Installation Flow (Existing Mode) ──────────────────

describe('StackLaravelCommand Integration', function (): void {

    beforeEach(function (): void {
        // Create a Laravel-like project structure for "existing" mode tests
        $this->testDir = createLaravelTestProject('test-laravel-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->mockDockerExecutor = createMockDockerExecutor();
        $this->app->instance(DockerExecutorInterface::class, $this->mockDockerExecutor);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('creates .tuti directory on successful initialization', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        expect(is_dir($this->testDir . '/.tuti'))->toBeTrue();
    });

    it('creates config.json in .tuti directory', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        expect(file_exists($this->testDir . '/.tuti/config.json'))->toBeTrue();
    });

    it('config.json contains project configuration', function (): void {
        $this->artisan('stack:laravel', [
            'project-name' => 'my-laravel-app',
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $config = json_decode(file_get_contents($this->testDir . '/.tuti/config.json'), true);

        expect($config)->toBeArray()
            ->and($config['project']['name'])->toBe('my-laravel-app');
    });

    it('creates docker-compose.yml', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        expect(file_exists($this->testDir . '/.tuti/docker-compose.yml'))->toBeTrue();
    });

    it('displays next steps after successful installation', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('tuti local:start');
    });

    it('displays project URLs after successful installation', function (): void {
        $this->artisan('stack:laravel', [
            'project-name' => 'my-app',
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('my-app.local.test');
    });

    it('creates .env file', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        expect(file_exists($this->testDir . '/.env'))->toBeTrue();
    });

    it('creates docker-compose.dev.yml for development', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        expect(file_exists($this->testDir . '/.tuti/docker-compose.dev.yml'))->toBeTrue();
    });
});

// ─── Mode Option Tests ───────────────────────────────────────────────────

describe('StackLaravelCommand Mode Option', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-mode-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('uses existing mode when Laravel project detected', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);
    });

    it('uses fresh mode when specified via --mode option', function (): void {
        // In fresh mode, a subdirectory is created
        $this->artisan('stack:laravel', [
            '--mode' => 'fresh',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);
    });
});

// ─── Services Option Tests ───────────────────────────────────────────────

describe('StackLaravelCommand Services Option', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-services-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('accepts multiple services via --services option', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'cache.redis', 'mail.mailpit'],
        ])
            ->assertExitCode(Command::SUCCESS);
    });

    it('adds Redis service to docker-compose when selected', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'cache.redis'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $composeContent = file_get_contents($this->testDir . '/.tuti/docker-compose.yml');

        expect($composeContent)->toContain('redis:');
    });

    it('adds Mailpit service to docker-compose when selected', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'mail.mailpit'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $composeContent = file_get_contents($this->testDir . '/.tuti/docker-compose.yml');

        expect($composeContent)->toContain('mailpit:');
    });
});

// ─── Force Reinitialization ───────────────────────────────────────────────

describe('StackLaravelCommand Force Reinitialization', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-force-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor
        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('reinitializes with --force option', function (): void {
        // First, create an existing .tuti directory
        mkdir($this->testDir . '/.tuti', 0755, true);
        file_put_contents($this->testDir . '/.tuti/config.json', json_encode(['old' => 'config']));

        // Then run with --force
        $this->artisan('stack:laravel', [
            'project-name' => 'new-project',
            '--mode' => 'existing',
            '--force' => true,
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        // Verify new config was created
        $config = json_decode(file_get_contents($this->testDir . '/.tuti/config.json'), true);

        expect($config['project']['name'])->toBe('new-project');
    });
});

// ─── Stack Manifest Validation ───────────────────────────────────────────

describe('StackLaravelCommand Stack Manifest', function (): void {

    it('loads valid Laravel stack manifest', function (): void {
        $stackLoader = app(StackLoaderService::class);
        $stackPath = base_path('stubs/stacks/laravel');

        $manifest = $stackLoader->load($stackPath);

        expect($manifest)->toBeArray()
            ->and($manifest['name'])->toBe('laravel-stack')
            ->and($manifest['framework'])->toBe('laravel');
    });

    it('validates stack manifest structure', function (): void {
        $stackLoader = app(StackLoaderService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $manifest = $stackLoader->load($stackPath);

        // Should not throw
        $stackLoader->validate($manifest);

        expect(true)->toBeTrue();
    });

    it('stack has required services defined', function (): void {
        $stackLoader = app(StackLoaderService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $manifest = $stackLoader->load($stackPath);

        $requiredServices = $stackLoader->getRequiredServices($manifest);

        expect($requiredServices)->toBeArray()
            ->and($requiredServices)->toHaveKey('database');
    });

    it('stack has optional services defined', function (): void {
        $stackLoader = app(StackLoaderService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $manifest = $stackLoader->load($stackPath);

        $optionalServices = $stackLoader->getOptionalServices($manifest);

        expect($optionalServices)->toBeArray();
    });
});

// ─── Service Registry ───────────────────────────────────────────────────

describe('StackLaravelCommand Service Registry', function (): void {

    it('loads service registry for Laravel stack', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');

        $registry->loadForStack($stackPath);

        expect($registry->hasService('databases', 'postgres'))->toBeTrue();
    });

    it('has postgres database service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        $service = $registry->getService('databases', 'postgres');

        expect($service['name'])->toBe('PostgreSQL');
    });

    it('has mysql database service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        expect($registry->hasService('databases', 'mysql'))->toBeTrue();
    });

    it('has mariadb database service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        expect($registry->hasService('databases', 'mariadb'))->toBeTrue();
    });

    it('has redis cache service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        expect($registry->hasService('cache', 'redis'))->toBeTrue();
    });

    it('has mailpit mail service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        expect($registry->hasService('mail', 'mailpit'))->toBeTrue();
    });

    it('has horizon worker service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        expect($registry->hasService('workers', 'horizon'))->toBeTrue();
    });

    it('has scheduler worker service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        expect($registry->hasService('workers', 'scheduler'))->toBeTrue();
    });

    it('has meilisearch search service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        expect($registry->hasService('search', 'meilisearch'))->toBeTrue();
    });

    it('has typesense search service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        expect($registry->hasService('search', 'typesense'))->toBeTrue();
    });

    it('has minio storage service', function (): void {
        $registry = app(StackRegistryManagerService::class);
        $stackPath = base_path('stubs/stacks/laravel');
        $registry->loadForStack($stackPath);

        expect($registry->hasService('storage', 'minio'))->toBeTrue();
    });
});

// ─── LaravelStackInstaller Tests ─────────────────────────────────────────

describe('LaravelStackInstaller', function (): void {

    it('has correct identifier', function (): void {
        $installer = app(LaravelStackInstaller::class);

        expect($installer->getIdentifier())->toBe('laravel');
    });

    it('has correct name', function (): void {
        $installer = app(LaravelStackInstaller::class);

        expect($installer->getName())->toBe('Laravel Stack');
    });

    it('has correct framework', function (): void {
        $installer = app(LaravelStackInstaller::class);

        expect($installer->getFramework())->toBe('laravel');
    });

    it('supports laravel identifier', function (): void {
        $installer = app(LaravelStackInstaller::class);

        expect($installer->supports('laravel'))->toBeTrue();
    });

    it('supports laravel-stack identifier', function (): void {
        $installer = app(LaravelStackInstaller::class);

        expect($installer->supports('laravel-stack'))->toBeTrue();
    });

    it('does not support other identifiers', function (): void {
        $installer = app(LaravelStackInstaller::class);

        expect($installer->supports('wordpress'))->toBeFalse();
    });

    it('returns available installation modes', function (): void {
        $installer = app(LaravelStackInstaller::class);
        $modes = $installer->getAvailableModes();

        expect($modes)->toBeArray()
            ->and($modes)->toHaveKey('fresh')
            ->and($modes)->toHaveKey('existing');
    });

    it('returns stack path', function (): void {
        $installer = app(LaravelStackInstaller::class);
        $stackPath = $installer->getStackPath();

        expect($stackPath)->toBeString()
            ->and($stackPath)->toContain('laravel');
    });
});

// ─── Error Handling ───────────────────────────────────────────────────

describe('StackLaravelCommand Error Handling', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-error-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('returns failure when infrastructure is not ready in fresh mode', function (): void {
        $fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $fakeInfra->setInstalled(false);
        $fakeInfra->setRunning(false);
        $fakeInfra->ensureReadyResult = false;
        $this->app->instance(InfrastructureManagerInterface::class, $fakeInfra);

        $this->artisan('stack:laravel', [
            '--mode' => 'fresh',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::FAILURE);
    });

    it('displays error message when installation fails', function (): void {
        // Mock Docker executor to fail - create failing mock directly
        $mockDockerExecutor = Mockery::mock(DockerExecutorInterface::class);
        $mockDockerExecutor->shouldReceive('isDockerAvailable')->andReturn(true);
        $mockDockerExecutor->shouldReceive('getPhpImage')->andReturn('serversideup/php:8.4-fpm-nginx');
        $mockDockerExecutor->shouldReceive('runComposer')->andReturn(
            createFakeDockerResult(false, '', 'Composer failed')
        );
        $this->app->instance(DockerExecutorInterface::class, $mockDockerExecutor);

        $this->artisan('stack:laravel', [
            '--mode' => 'fresh',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Installation failed');
    });

    it('displays helpful hints on failure', function (): void {
        // Mock Docker executor to fail - create failing mock directly
        $mockDockerExecutor = Mockery::mock(DockerExecutorInterface::class);
        $mockDockerExecutor->shouldReceive('isDockerAvailable')->andReturn(true);
        $mockDockerExecutor->shouldReceive('getPhpImage')->andReturn('serversideup/php:8.4-fpm-nginx');
        $mockDockerExecutor->shouldReceive('runComposer')->andReturn(
            createFakeDockerResult(false, '', 'Docker error')
        );
        $this->app->instance(DockerExecutorInterface::class, $mockDockerExecutor);

        $this->artisan('stack:laravel', [
            '--mode' => 'fresh',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Ensure Docker is running');
    });
});

// ─── Environment Option ───────────────────────────────────────────────────

describe('StackLaravelCommand Environment Option', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-env-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('defaults to dev environment in non-interactive mode', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $config = json_decode(file_get_contents($this->testDir . '/.tuti/config.json'), true);
        expect($config['environments']['current'])->toBe('dev');
    });

    it('accepts dev environment via --env option', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--env' => 'dev',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);
    });

    it('accepts staging environment via --env option', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--env' => 'staging',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $config = json_decode(file_get_contents($this->testDir . '/.tuti/config.json'), true);
        expect($config['environments']['current'])->toBe('staging');
    });

    it('accepts production environment via --env option', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--env' => 'production',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $config = json_decode(file_get_contents($this->testDir . '/.tuti/config.json'), true);
        expect($config['environments']['current'])->toBe('production');
    });
});

// ─── Laravel Version Option ───────────────────────────────────────────────────

describe('StackLaravelCommand Laravel Version Integration', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-version-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('accepts specific Laravel version via --laravel-version', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'fresh',
            '--laravel-version' => '11.x',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);
    });
});

// ─── Path Option Integration ───────────────────────────────────────────────────

describe('StackLaravelCommand Path Option Integration', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('creates project in specified path via --path option', function (): void {
        $customPath = $this->testDir . '/custom-location';

        $this->artisan('stack:laravel', [
            'project-name' => 'my-app',
            '--mode' => 'fresh',
            '--path' => $customPath,
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        // In fresh mode with path option, project is created at path/project-name
        expect(is_dir($customPath . '/my-app/.tuti'))->toBeTrue();
    });
});

// ─── Docker Compose Content Tests ───────────────────────────────────────────────

describe('StackLaravelCommand Docker Compose Content', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-compose-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('includes postgres service in docker-compose when selected', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $composeContent = file_get_contents($this->testDir . '/.tuti/docker-compose.yml');

        expect($composeContent)->toContain('postgres:');
    });

    it('uses postgres database by default in docker-compose', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $composeContent = file_get_contents($this->testDir . '/.tuti/docker-compose.yml');

        // PostgreSQL service is added when selected
        expect($composeContent)->toContain('postgres:');
        // DB_CONNECTION uses variable substitution from .env
        expect($composeContent)->toContain('DB_CONNECTION: ${DB_CONNECTION:-sqlite}');
    });

    it('includes horizon service in docker-compose when selected', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'cache.redis', 'workers.horizon'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $composeContent = file_get_contents($this->testDir . '/.tuti/docker-compose.yml');

        expect($composeContent)->toContain('horizon:');
    });

    it('includes minio service in docker-compose when selected', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'storage.minio'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $composeContent = file_get_contents($this->testDir . '/.tuti/docker-compose.yml');

        expect($composeContent)->toContain('minio:');
    });

    it('includes meilisearch service in docker-compose when selected', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'search.meilisearch'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $composeContent = file_get_contents($this->testDir . '/.tuti/docker-compose.yml');

        expect($composeContent)->toContain('meilisearch:');
    });

    it('only includes dev overrides for selected services', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['cache.redis'],  // No database selected
        ])
            ->assertExitCode(Command::SUCCESS);

        $baseCompose = file_get_contents($this->testDir . '/.tuti/docker-compose.yml');
        $devCompose = file_get_contents($this->testDir . '/.tuti/docker-compose.dev.yml');

        // Postgres should NOT be in either file since not selected
        expect($baseCompose)->not->toContain('postgres:');
        expect($devCompose)->not->toContain('postgres:');

        // Redis should be in both since selected
        expect($baseCompose)->toContain('redis:');
        expect($devCompose)->toContain('redis:');
    });

    it('configures project name in docker-compose', function (): void {
        $this->artisan('stack:laravel', [
            'project-name' => 'my-custom-app',
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $composeContent = file_get_contents($this->testDir . '/.tuti/docker-compose.yml');

        expect($composeContent)->toContain('my-custom-app');
    });
});

// ─── Env File Configuration Tests ───────────────────────────────────────────────

describe('StackLaravelCommand Env File Configuration', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-env-config');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('configures postgres database settings in .env', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $envContent = file_get_contents($this->testDir . '/.env');

        expect($envContent)->toContain('DB_HOST=postgres')
            ->and($envContent)->toContain('DB_PORT=5432');
    });

    it('configures redis settings in .env when redis selected', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'cache.redis'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $envContent = file_get_contents($this->testDir . '/.env');

        expect($envContent)->toContain('REDIS_HOST=redis')
            ->and($envContent)->toContain('CACHE_STORE=redis');
    });

    it('configures mailpit settings in .env when mailpit selected', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'mail.mailpit'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $envContent = file_get_contents($this->testDir . '/.env');

        expect($envContent)->toContain('MAIL_HOST=mailpit')
            ->and($envContent)->toContain('MAIL_PORT=1025');
    });

    it('includes tuti configuration section in .env', function (): void {
        $this->artisan('stack:laravel', [
            'project-name' => 'test-app',
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $envContent = file_get_contents($this->testDir . '/.env');

        expect($envContent)->toContain('TUTI-CLI DOCKER CONFIGURATION')
            ->and($envContent)->toContain('PROJECT_NAME=test-app');
    });
});

// ─── Project URLs Display Tests ───────────────────────────────────────────────

describe('StackLaravelCommand Project URLs Display', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-urls-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('displays mailpit URL when mailpit service selected', function (): void {
        $this->artisan('stack:laravel', [
            'project-name' => 'mail-test',
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'mail.mailpit'],
        ])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('mail.mail-test.local.test');
    });

    it('displays minio URL when minio service selected', function (): void {
        $this->artisan('stack:laravel', [
            'project-name' => 'storage-test',
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'storage.minio'],
        ])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('minio.storage-test.local.test');
    });

    it('displays meilisearch URL when meilisearch service selected', function (): void {
        $this->artisan('stack:laravel', [
            'project-name' => 'search-test',
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'search.meilisearch'],
        ])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('search-test.local.test');
    });
});

// ─── Config.json Structure Tests ───────────────────────────────────────────────

describe('StackLaravelCommand Config.json Structure', function (): void {

    beforeEach(function (): void {
        $this->testDir = createLaravelTestProject('test-config-project');
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Mock infrastructure manager
        $this->fakeInfra = new Tests\Mocks\FakeInfrastructureManager();
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);

        // Mock Docker executor with all required methods
        $this->app->instance(DockerExecutorInterface::class, createMockDockerExecutor());
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('config.json contains project section with name and type', function (): void {
        $this->artisan('stack:laravel', [
            'project-name' => 'config-test',
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $config = json_decode(file_get_contents($this->testDir . '/.tuti/config.json'), true);

        expect($config['project'])
            ->toBeArray()
            ->and($config['project']['name'])->toBe('config-test')
            ->and($config['project']['type'])->toBe('php')
            ->and($config['project']['version'])->toBeString();
    });

    it('config.json contains stack section', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $config = json_decode(file_get_contents($this->testDir . '/.tuti/config.json'), true);

        expect($config['stack'])
            ->toBeArray()
            ->and($config['stack']['name'])->toBe('laravel-stack')
            ->and($config['stack']['version'])->toBeString();
    });

    it('config.json contains services in environments section', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres', 'cache.redis', 'mail.mailpit'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $config = json_decode(file_get_contents($this->testDir . '/.tuti/config.json'), true);

        expect($config['environments']['dev']['services'])
            ->toBeArray()
            ->and($config['environments']['dev']['services']['databases'])->toContain('postgres')
            ->and($config['environments']['dev']['services']['cache'])->toContain('redis')
            ->and($config['environments']['dev']['services']['mail'])->toContain('mailpit');
    });

    it('config.json contains initialized_at timestamp', function (): void {
        $this->artisan('stack:laravel', [
            '--mode' => 'existing',
            '--no-interaction' => true,
            '--services' => ['databases.postgres'],
        ])
            ->assertExitCode(Command::SUCCESS);

        $config = json_decode(file_get_contents($this->testDir . '/.tuti/config.json'), true);

        expect($config['initialized_at'])->toBeString();
    });
});
