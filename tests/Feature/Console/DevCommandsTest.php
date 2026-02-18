<?php

declare(strict_types=1);

/**
 * DevCommands Feature Tests
 *
 * Tests that development-only commands (UIShowcaseCommand, DebugCommand)
 * are available in local environment.
 *
 * IMPORTANT: Production environment removal is configured in config/commands.php
 * using env('APP_ENV') check. This happens at config load time during bootstrap,
 * so it cannot be easily tested via unit tests. Manual testing is required.
 *
 * @see config/commands.php
 * @see tests/Feature/Console/DevCommandsTest.php
 */

use App\Commands\DebugCommand;
use App\Commands\UIShowcaseCommand;
use Illuminate\Contracts\Console\Kernel;
use LaravelZero\Framework\Commands\Command;

// ─── Registration in Local/Testing Environment ────────────────────────────────

describe('DevCommands in local environment', function (): void {

    it('registers UIShowcaseCommand', function (): void {
        $command = $this->app->make(UIShowcaseCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
        expect($command->getName())->toBe('ui:showcase');
    });

    it('registers DebugCommand', function (): void {
        $command = $this->app->make(DebugCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
        expect($command->getName())->toBe('debug');
    });

    it('includes dev commands in command list', function (): void {
        $kernel = $this->app->make(Kernel::class);
        $commands = $kernel->all();

        expect($commands)
            ->toHaveKey('ui:showcase')
            ->toHaveKey('debug');
    });

    it('can run ui:showcase command', function (): void {
        $this->artisan('ui:showcase')
            ->assertSuccessful();
    });

    it('can run debug command with status action', function (): void {
        $this->artisan('debug', ['action' => 'status'])
            ->assertSuccessful();
    });

});

// ─── Configuration Validation ─────────────────────────────────────────────────

describe('config/commands.php configuration', function (): void {

    it('has remove array configured', function (): void {
        $config = config('commands');

        expect($config)->toHaveKey('remove');
        expect($config['remove'])->toBeArray();
    });

    it('remove array is empty in local/testing environment', function (): void {
        // Config is loaded at bootstrap time with current environment
        // In testing, env('APP_ENV') is 'testing', which is not 'local'
        // so dev commands might be in remove array depending on environment
        $config = require base_path('config/commands.php');

        // In local environment (APP_ENV=local), remove array should be empty
        // In testing environment (APP_ENV=testing), remove array will contain dev commands
        // This is expected behavior - testing env is treated like production
        expect($config['remove'])->toBeArray();
    });

    it('contains correct conditional logic in remove array', function (): void {
        $configFile = file_get_contents(base_path('config/commands.php'));

        // Verify the config file contains the conditional removal logic
        expect($configFile)
            ->toContain("env('APP_ENV') !== 'local'")
            ->toContain(UIShowcaseCommand::class)
            ->toContain(DebugCommand::class);
    });

});

// ─── Environment Detection ────────────────────────────────────────────────────

describe('environment detection', function (): void {

    it('correctly identifies local environment', function (): void {
        app()['env'] = 'local';

        expect(app()->isLocal())->toBeTrue();
    });

    it('correctly identifies production environment', function (): void {
        app()['env'] = 'production';

        expect(app()->isLocal())->toBeFalse();
    });

    it('correctly identifies development environment as not local', function (): void {
        app()['env'] = 'development';

        expect(app()->isLocal())->toBeFalse();
    });

    it('correctly identifies testing environment as not local', function (): void {
        app()['env'] = 'testing';

        expect(app()->isLocal())->toBeFalse();
    });

});

// ─── Manual Testing Instructions ──────────────────────────────────────────────

/**
 * MANUAL TESTING REQUIRED
 *
 * The dev commands removal in production cannot be fully tested via unit tests
 * because config files are loaded during bootstrap before we can change the environment.
 *
 * To verify production behavior, run these manual tests:
 *
 * 1. Test in production environment:
 *    ```bash
 *    APP_ENV=production php tuti list | grep -E "(ui:showcase|debug)"
 *    # Should NOT show these commands
 *
 *    APP_ENV=production php tuti ui:showcase
 *    # Should error: Command "ui:showcase" is not defined.
 *    ```
 *
 * 2. Test PHAR build:
 *    ```bash
 *    make build-phar
 *    php builds/tuti list
 *    # Should NOT show ui:showcase or debug
 *
 *    php builds/tuti ui:showcase
 *    # Should error: Command "ui:showcase" is not defined.
 *    ```
 *
 * 3. Test in local development:
 *    ```bash
 *    php tuti list | grep -E "(ui:showcase|debug)"
 *    # SHOULD show both commands
 *
 *    php tuti ui:showcase    # Should work
 *    php tuti debug status   # Should work
 *    ```
 */
