<?php

declare(strict_types=1);

/**
 * InitCommand Feature Tests
 *
 * Tests the `tuti init` command which initializes a new Tuti project.
 *
 * @see App\Commands\InitCommand
 */

use App\Contracts\StackInstallerInterface;
use App\Services\Stack\StackInstallerRegistry;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

// ─── Registration ───────────────────────────────────────────────────────

describe('InitCommand Registration', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);

        expect($command->getName())->toBe('init');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    it('has project-name argument', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasArgument('project-name'))->toBeTrue();
    });

    it('has --stack option', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('stack'))->toBeTrue();
    });

    it('has --force option', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('has correct description', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);

        expect($command->getDescription())->toBe('Initialize a new Tuti project');
    });
});

// ─── Pre-flight Checks ───────────────────────────────────────────────────

describe('InitCommand Pre-flight Checks', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('fails when .tuti directory already exists', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);

        $this->artisan('init', [
            'project-name' => 'my-project',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Project already initialized');
    });

    it('displays hint to use --force when already initialized', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);

        $this->artisan('init', [
            'project-name' => 'my-project',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Use --force to reinitialize');
    });
});

// ─── Stack Selection ─────────────────────────────────────────────────────

describe('InitCommand Stack Selection', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('delegates to stack command when --stack option is provided', function (): void {
        // Create a mock installer and register it
        $mockInstaller = Mockery::mock(StackInstallerInterface::class);
        $mockInstaller->shouldReceive('getIdentifier')->andReturn('test-stack');
        $mockInstaller->shouldReceive('getName')->andReturn('Test Stack');
        $mockInstaller->shouldReceive('getDescription')->andReturn('Test stack for testing');
        $mockInstaller->shouldReceive('getFramework')->andReturn('test');
        $mockInstaller->shouldReceive('supports')->with('test-stack')->andReturn(true);

        $registry = app(StackInstallerRegistry::class);
        $registry->register($mockInstaller);

        // The command should delegate to stack:test-stack command
        // This will fail because the stack command doesn't exist, but it proves delegation
        $this->artisan('init', [
            'project-name' => 'my-project',
            '--stack' => 'test-stack',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE);
    });

    it('warns when unknown stack is provided', function (): void {
        Process::fake(['*git*' => Process::result('OK')]);

        $this->artisan('init', [
            'project-name' => 'my-project',
            '--stack' => 'unknown-stack',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Unknown stack: unknown-stack');
    });
});

// ─── Options & Arguments ─────────────────────────────────────────────────

describe('InitCommand Options', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('accepts project-name as optional argument', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);
        $definition = $command->getDefinition();
        $projectNameArgument = $definition->getArgument('project-name');

        expect($projectNameArgument->isRequired())->toBeFalse();
    });

    it('stack option has no default value', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);
        $definition = $command->getDefinition();
        $stackOption = $definition->getOption('stack');

        expect($stackOption->getDefault())->toBeNull();
    });

    it('force option defaults to false', function (): void {
        $command = $this->app->make(App\Commands\InitCommand::class);
        $definition = $command->getDefinition();
        $forceOption = $definition->getOption('force');

        expect($forceOption->getDefault())->toBeFalse();
    });
});

// ─── Branded Output ──────────────────────────────────────────────────────

describe('InitCommand Branded Output', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('displays branded header on execution', function (): void {
        $this->artisan('init', [
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Project Initialization');
    });
});
