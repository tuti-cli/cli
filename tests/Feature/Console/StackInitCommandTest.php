<?php

declare(strict_types=1);

/**
 * StackInitCommand Feature Tests
 *
 * Tests the `tuti stack:init` command which initializes a project with a stack template.
 *
 * Note: Integration tests use the real 'laravel' stack from stubs/stacks/.
 * For unit testing services, use tests/Fixtures/ with mocked dependencies.
 *
 * @see \App\Commands\Stack\InitCommand
 */

use LaravelZero\Framework\Commands\Command;

// ─── Registration ───────────────────────────────────────────────────────

describe('StackInitCommand Registration', function (): void {

    it('is registered in the application', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);

        expect($command->getName())->toBe('stack:init');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(\App\Concerns\HasBrandedOutput::class);
    });

    it('has stack argument', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasArgument('stack'))->toBeTrue();
    });

    it('has project-name argument', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasArgument('project-name'))->toBeTrue();
    });

    it('has --services option', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('services'))->toBeTrue();
    });

    it('has --force option', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);
        $definition = $command->getDefinition();

        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('has correct description', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);

        expect($command->getDescription())->toBe('Initialize a new project with selected stack and services');
    });
});

// ─── Pre-flight Checks ───────────────────────────────────────────────────

describe('StackInitCommand Pre-flight Checks', function (): void {

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

        $this->artisan('stack:init', [
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Project already initialized');
    });

    it('displays hint to use --force when already initialized', function (): void {
        mkdir($this->testDir . '/.tuti', 0755, true);

        $this->artisan('stack:init', [
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Use --force to reinitialize');
    });

    it('fails when no stack selected in non-interactive mode', function (): void {
        $this->artisan('stack:init', [
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('No stack selected');
    });

    it('fails when stack not found in non-interactive mode', function (): void {
        $this->artisan('stack:init', [
            'stack' => 'non-existent-stack',
            '--no-interaction' => true,
        ])
            ->assertExitCode(Command::FAILURE);
    });
});

// ─── Options & Arguments ─────────────────────────────────────────────────

describe('StackInitCommand Options', function (): void {

    beforeEach(function (): void {
        $this->testDir = createTestDirectory();
        $this->originalCwd = getcwd();
        chdir($this->testDir);
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        cleanupTestDirectory($this->testDir);
    });

    it('accepts stack as first argument', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);
        $definition = $command->getDefinition();
        $stackArgument = $definition->getArgument('stack');

        expect($stackArgument->isRequired())->toBeFalse();
    });

    it('accepts project-name as second argument', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);
        $definition = $command->getDefinition();
        $projectNameArgument = $definition->getArgument('project-name');

        expect($projectNameArgument->isRequired())->toBeFalse();
    });

    it('services option accepts multiple values', function (): void {
        $command = $this->app->make(\App\Commands\Stack\InitCommand::class);
        $definition = $command->getDefinition();
        $servicesOption = $definition->getOption('services');

        expect($servicesOption->isArray())->toBeTrue();
    });
});

// ─── Branded Output ──────────────────────────────────────────────────────

describe('StackInitCommand Branded Output', function (): void {

    it('displays branded header on execution', function (): void {
        $testDir = createTestDirectory();
        $originalCwd = getcwd();
        chdir($testDir);

        try {
            $this->artisan('stack:init', [
                '--no-interaction' => true,
            ])
                ->expectsOutputToContain('Stack Initialization');
        } finally {
            chdir($originalCwd);
            cleanupTestDirectory($testDir);
        }
    });
});
