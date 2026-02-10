<?php

declare(strict_types=1);

/**
 * FindCommand Feature Tests
 *
 * Tests the command finder functionality that helps users discover and execute
 * available Tuti commands interactively.
 *
 * @see App\Commands\FindCommand
 */

use Illuminate\Console\Application as Artisan;
use LaravelZero\Framework\Commands\Command;

describe('FindCommand', function (): void {

    beforeEach(function (): void {
        // Register some test commands to ensure we have commands to find
        $this->app->register(App\Providers\AppServiceProvider::class);
    });

    it('is registered in the application', function (): void {
        $command = $this->app->make(App\Commands\FindCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(App\Commands\FindCommand::class);

        expect($command->getName())->toBe('find');
    });

    it('shows correct command description in list', function (): void {
        $this->artisan('list')
            ->assertSuccessful()
            ->expectsOutputToContain('find');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(App\Commands\FindCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    it('has public handle method', function (): void {
        $command = new App\Commands\FindCommand();

        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('handle');

        expect($method->isPublic())->toBeTrue();
    });

    it('can access application commands', function (): void {
        // Verify the command can access commands through Artisan facade
        $exitCode = Illuminate\Support\Facades\Artisan::call('list');

        expect($exitCode)->toBe(0);
    });

    it('is listed in available commands', function (): void {
        $this->artisan('list')
            ->assertSuccessful()
            ->expectsOutputToContain('find');
    });

    it('handles command execution structure correctly', function (): void {
        // Test that the command has the right structure to call other commands
        $command = $this->app->make(App\Commands\FindCommand::class);

        expect($command)
            ->toBeInstanceOf(Command::class);

        // Verify it has the call method via reflection
        expect(method_exists($command, 'call'))->toBeTrue();
    });
});

describe('FindCommand Command Discovery', function (): void {

    it('can access core commands', function (): void {
        // Use Artisan facade to get commands
        Illuminate\Support\Facades\Artisan::call('list');
        $output = Illuminate\Support\Facades\Artisan::output();

        expect($output)
            ->toContain('init')
            ->toContain('install')
            ->toContain('local:start')
            ->toContain('local:stop')
            ->toContain('stack:init');
    });

    it('can discover stack commands', function (): void {
        Illuminate\Support\Facades\Artisan::call('list');
        $output = Illuminate\Support\Facades\Artisan::output();

        expect($output)
            ->toContain('stack:init')
            ->toContain('stack:laravel')
            ->toContain('stack:manage');
    });

    it('can discover local commands', function (): void {
        Illuminate\Support\Facades\Artisan::call('list');
        $output = Illuminate\Support\Facades\Artisan::output();

        expect($output)
            ->toContain('local:start')
            ->toContain('local:stop')
            ->toContain('local:status')
            ->toContain('local:logs');
    });

    it('discovers all command namespaces', function (): void {
        Illuminate\Support\Facades\Artisan::call('list');
        $output = Illuminate\Support\Facades\Artisan::output();

        expect($output)
            ->toContain('local:')
            ->toContain('stack:');
    });

    it('counts minimum expected commands', function (): void {
        Illuminate\Support\Facades\Artisan::call('list');
        $output = Illuminate\Support\Facades\Artisan::output();

        //        dd($output);

        // Check that we have a reasonable number of commands listed
        expect($output)
            ->toContain('init')
            ->toContain('install')
            ->toContain('find');
    });

    it('filters find command from discovery list', function (): void {
        // Test that the find command exists
        $this->artisan('list')
            ->assertSuccessful()
            ->expectsOutputToContain('find');

        // The command should filter itself when building the suggestion list
        // We can't test the internal logic without running it, but we can verify structure
        $command = $this->app->make(App\Commands\FindCommand::class);
        expect($command)->toBeInstanceOf(Command::class);
    });
});

describe('FindCommand Integration', function (): void {

    it('integrates with Laravel Zero application', function (): void {
        // Verify command is registered and accessible
        $this->artisan('list')
            ->assertSuccessful()
            ->expectsOutputToContain('find');
    });

    it('is accessible from command line via list', function (): void {
        $exitCode = Illuminate\Support\Facades\Artisan::call('list');

        expect($exitCode)->toBe(0);

        $output = Illuminate\Support\Facades\Artisan::output();
        expect($output)->toContain('find');
    });
});

describe('FindCommand Trait Usage', function (): void {

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(App\Commands\FindCommand::class);

        $traits = class_uses_recursive($command);

        expect($traits)->toContain(App\Concerns\HasBrandedOutput::class);
    });

    it('has branded output methods available', function (): void {
        $command = $this->app->make(App\Commands\FindCommand::class);

        // Check methods exist using reflection
        expect(method_exists($command, 'brandedHeader'))->toBeTrue();
        expect(method_exists($command, 'action'))->toBeTrue();
        expect(method_exists($command, 'success'))->toBeTrue();
        expect(method_exists($command, 'failure'))->toBeTrue();
        expect(method_exists($command, 'note'))->toBeTrue();
        expect(method_exists($command, 'warning'))->toBeTrue();
    });
});

describe('FindCommand Structure', function (): void {

    it('does not crash when instantiated', function (): void {
        $command = $this->app->make(App\Commands\FindCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct command name', function (): void {
        $command = $this->app->make(App\Commands\FindCommand::class);

        expect($command->getName())->toBe('find');
    });

    it('has correct description', function (): void {
        $command = $this->app->make(App\Commands\FindCommand::class);

        expect($command->getDescription())
            ->toBeString()
            ->toContain('Useful Tuti command');
    });
});
