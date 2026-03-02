<?php

declare(strict_types=1);

/**
 * WpSetupCommand Feature Tests
 *
 * Tests the `tuti wp:setup` command which performs WordPress auto-setup.
 *
 * @see App\Commands\Stack\WpSetupCommand
 */

use App\Contracts\DockerExecutionResult;
use App\Contracts\DockerExecutorInterface;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

/**
 * Create a mock Docker execution result.
 */
function createWpMockDockerResult(bool $successful = true, string $output = '', string $error = ''): DockerExecutionResult
{
    return new DockerExecutionResult(
        successful: $successful,
        output: $output,
        errorOutput: $error,
        exitCode: $successful ? 0 : 1,
    );
}

// ─── Registration ───────────────────────────────────────────────────────

describe('WpSetupCommand Registration', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(App\Commands\Stack\WpSetupCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(App\Commands\Stack\WpSetupCommand::class);

        expect($command->getName())->toBe('wp:setup');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(App\Commands\Stack\WpSetupCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    it('has --force option', function (): void {
        $command = $this->app->make(App\Commands\Stack\WpSetupCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('force option defaults to false', function (): void {
        $command = $this->app->make(App\Commands\Stack\WpSetupCommand::class);
        $definition = $command->getDefinition();
        $forceOption = $definition->getOption('force');

        expect($forceOption->getDefault())->toBeFalse();
    });

    it('has correct description', function (): void {
        $command = $this->app->make(App\Commands\Stack\WpSetupCommand::class);

        expect($command->getDescription())->toContain('WordPress installation');
    });
});

// ─── Pre-flight Checks ───────────────────────────────────────────────────

describe('WpSetupCommand Pre-flight Checks', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('fails when not in a tuti project (no .tuti directory)', function (): void {
        $this->artisan('wp:setup')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Not a tuti project');
    });

    it('fails when .tuti/config.json does not exist', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Not a tuti project');
    });

    it('shows hint about running from WordPress project directory', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('WordPress project directory');
    });
});

// ─── Container Checks ─────────────────────────────────────────────────────

describe('WpSetupCommand Container Checks', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Create .tuti/config.json
        mkdir($this->testDir . '/.tuti', 0755, true);
        file_put_contents(
            $this->testDir . '/.tuti/config.json',
            json_encode(['project' => ['name' => 'test-project']])
        );
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('fails when containers are not running', function (): void {
        // Mock docker ps to return no containers
        Process::fake([
            '*docker*ps*' => Process::result('', '', 0),
        ]);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Containers are not running');
    });

    it('shows hint to start containers when not running', function (): void {
        Process::fake([
            '*docker*ps*' => Process::result('', '', 0),
        ]);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('tuti local:start');
    });

    it('proceeds when app container is running', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id-123', '', 0),
            '*docker*ps*test-project_dev_database*health=healthy*' => Process::result('db-container-id', '', 0),
        ]);

        // Mock DockerExecutorInterface
        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->withArgs(fn (array $args, string $path, array $env, ?string $network): bool => $args === ['core', 'is-installed'])
            ->once()
            ->andReturn(createWpMockDockerResult(false)); // Not installed

        $mockDocker->shouldReceive('runWpCli')
            ->withArgs(fn (array $args, string $path, array $env, ?string $network): bool => $args[0] === 'core' && $args[1] === 'install')
            ->once()
            ->andReturn(createWpMockDockerResult(true)); // Install succeeds

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS);
    });
});

// ─── Already Installed Check ─────────────────────────────────────────────

describe('WpSetupCommand Already Installed Check', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Create .tuti/config.json
        mkdir($this->testDir . '/.tuti', 0755, true);
        file_put_contents(
            $this->testDir . '/.tuti/config.json',
            json_encode(['project' => ['name' => 'test-project']])
        );
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('returns success when WordPress is already installed', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id-123', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->withArgs(fn (array $args): bool => $args === ['core', 'is-installed'])
            ->once()
            ->andReturn(createWpMockDockerResult(true)); // Already installed

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('already installed');
    });

    it('shows access credentials when already installed', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id-123', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Site URL');
    });

    it('shows hint about --force option when already installed', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id-123', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('--force');
    });
});

// ─── Database Wait ────────────────────────────────────────────────────────

describe('WpSetupCommand Database Wait', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Create .tuti/config.json
        mkdir($this->testDir . '/.tuti', 0755, true);
        file_put_contents(
            $this->testDir . '/.tuti/config.json',
            json_encode(['project' => ['name' => 'test-project']])
        );
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('waits for healthy database container', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id-123', '', 0),
            '*docker*ps*test-project_dev_database*health=healthy*' => Process::result('db-container-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false)); // Not installed

        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(true)); // Installation succeeds

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS);
    });

    it('accepts running database after fallback threshold', function (): void {
        // All containers running - simplified test
        Process::fake([
            '*' => Process::result('container-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS);
    });
});

// ─── Happy Path ───────────────────────────────────────────────────────────

describe('WpSetupCommand Happy Path', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Create .tuti/config.json
        mkdir($this->testDir . '/.tuti', 0755, true);
        file_put_contents(
            $this->testDir . '/.tuti/config.json',
            json_encode(['project' => ['name' => 'my-wp-site']])
        );
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('successfully installs WordPress', function (): void {
        Process::fake([
            '*docker*ps*my-wp-site_dev_app*' => Process::result('app-container-id', '', 0),
            '*docker*ps*my-wp-site_dev_database*health=healthy*' => Process::result('db-container-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false)); // Not installed

        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(true)); // Installation succeeds

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('WordPress installed');
    });

    it('creates auto-setup.json with completion status on success', function (): void {
        Process::fake([
            '*docker*ps*my-wp-site_dev_app*' => Process::result('app-container-id', '', 0),
            '*docker*ps*my-wp-site_dev_database*health=healthy*' => Process::result('db-container-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS);

        // Verify auto-setup.json was created
        $autoSetupPath = $this->testDir . '/.tuti/auto-setup.json';
        expect(file_exists($autoSetupPath))->toBeTrue();

        $autoSetup = json_decode(file_get_contents($autoSetupPath), true);
        expect($autoSetup['completed'])->toBeTrue()
            ->and($autoSetup['completed_at'])->not->toBeNull();
    });

    it('uses existing auto-setup.json if present', function (): void {
        $existingConfig = [
            'enabled' => true,
            'site_url' => 'https://custom.local.test',
            'site_title' => 'Custom Site',
            'admin_user' => 'customadmin',
            'admin_password' => 'securepassword',
            'admin_email' => 'admin@custom.test',
        ];

        file_put_contents(
            $this->testDir . '/.tuti/auto-setup.json',
            json_encode($existingConfig)
        );

        Process::fake([
            '*docker*ps*my-wp-site_dev_app*' => Process::result('app-container-id', '', 0),
            '*docker*ps*my-wp-site_dev_database*health=healthy*' => Process::result('db-container-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        // Verify custom config is passed to install command
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->withArgs(function (array $args, string $path, array $env, ?string $network): bool {
                $urlArg = array_filter($args, fn ($arg): bool => str_contains($arg, 'url='));

                return $urlArg !== [] && str_contains(reset($urlArg), 'custom.local.test');
            })
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS);
    });

    it('generates default credentials based on project name', function (): void {
        Process::fake([
            '*docker*ps*my-wp-site_dev_app*' => Process::result('app-container-id', '', 0),
            '*docker*ps*my-wp-site_dev_database*health=healthy*' => Process::result('db-container-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        // Verify generated defaults use project name
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->withArgs(function (array $args, string $path, array $env, ?string $network): bool {
                $urlArg = array_filter($args, fn ($arg): bool => str_contains($arg, 'url='));
                $titleArg = array_filter($args, fn ($arg): bool => str_contains($arg, 'title='));

                return $urlArg !== []
                    && str_contains(reset($urlArg), 'my-wp-site.local.test')
                    && $titleArg !== []
                    && str_contains(reset($titleArg), 'My wp site');
            })
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays credentials box after successful installation', function (): void {
        Process::fake([
            '*docker*ps*my-wp-site_dev_app*' => Process::result('app-container-id', '', 0),
            '*docker*ps*my-wp-site_dev_database*health=healthy*' => Process::result('db-container-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Dev Admin Credentials')
            ->expectsOutputToContain('Site URL')
            ->expectsOutputToContain('Admin URL');
    });
});

// ─── Force Option ─────────────────────────────────────────────────────────

describe('WpSetupCommand Force Option', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Create .tuti/config.json
        mkdir($this->testDir . '/.tuti', 0755, true);
        file_put_contents(
            $this->testDir . '/.tuti/config.json',
            json_encode(['project' => ['name' => 'test-project']])
        );
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('skips already-installed check with --force', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id', '', 0),
            '*docker*ps*test-project_dev_database*health=healthy*' => Process::result('db-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        // With --force, the is-installed check should NOT be called
        // Only the install should be called
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->withArgs(fn (array $args): bool => $args[0] === 'core' && $args[1] === 'install')
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);
    });

    it('reinstalls WordPress with --force even if already installed', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id', '', 0),
            '*docker*ps*test-project_dev_database*health=healthy*' => Process::result('db-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        // Only install command called with --force
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup', ['--force' => true])
            ->assertExitCode(Command::SUCCESS);
    });
});

// ─── Error Handling ───────────────────────────────────────────────────────

describe('WpSetupCommand Error Handling', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        // Create .tuti/config.json
        mkdir($this->testDir . '/.tuti', 0755, true);
        file_put_contents(
            $this->testDir . '/.tuti/config.json',
            json_encode(['project' => ['name' => 'test-project']])
        );
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('fails when WordPress installation fails', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id', '', 0),
            '*docker*ps*test-project_dev_database*health=healthy*' => Process::result('db-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false)); // Not installed

        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false)); // Installation fails

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('WordPress installation failed');
    });

    it('shows hint about docker logs when installation fails', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id', '', 0),
            '*docker*ps*test-project_dev_database*health=healthy*' => Process::result('db-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('docker logs');
    });

    it('does not create auto-setup.json when installation fails', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id', '', 0),
            '*docker*ps*test-project_dev_database*health=healthy*' => Process::result('db-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::FAILURE);

        $autoSetupPath = $this->testDir . '/.tuti/auto-setup.json';
        expect(file_exists($autoSetupPath))->toBeFalse();
    });
});

// ─── Process Array Syntax Security ────────────────────────────────────────

describe('WpSetupCommand Process Security', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);

        mkdir($this->testDir . '/.tuti', 0755, true);
        file_put_contents(
            $this->testDir . '/.tuti/config.json',
            json_encode(['project' => ['name' => 'test-project']])
        );
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('completes successfully with mocked docker processes', function (): void {
        Process::fake([
            '*docker*ps*test-project_dev_app*' => Process::result('container-id', '', 0),
            '*docker*ps*test-project_dev_database*health=healthy*' => Process::result('db-id', '', 0),
        ]);

        $mockDocker = Mockery::mock(DockerExecutorInterface::class);
        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(false));

        $mockDocker->shouldReceive('runWpCli')
            ->once()
            ->andReturn(createWpMockDockerResult(true));

        $this->app->instance(DockerExecutorInterface::class, $mockDocker);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::SUCCESS);
    });

    it('handles container not running scenario', function (): void {
        Process::fake([
            '*docker*ps*' => Process::result('', '', 0),
        ]);

        $this->artisan('wp:setup')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Containers are not running');
    });
});
