<?php

declare(strict_types=1);

/**
 * DevCommands Feature Tests
 *
 * Tests the configuration for development-only commands.
 *
 * Note: UIShowcaseCommand is only available in local environment.
 * Note: DebugCommand is available in ALL environments for user debugging.
 *
 * IMPORTANT: Production environment removal is configured in config/commands.php
 * using env('APP_ENV') check. This happens at config load time during bootstrap,
 * so command execution cannot be tested in CI (testing env). Manual testing is required.
 *
 * @see config/commands.php
 */

use App\Commands\UIShowcaseCommand;

// ─── Configuration Validation ─────────────────────────────────────────────────

describe('config/commands.php configuration', function (): void {

    it('has remove array configured', function (): void {
        $config = config('commands');

        expect($config)
            ->toHaveKey('remove')
            ->and($config['remove'])->toBeArray();
    });

    it('contains conditional removal logic for dev commands', function (): void {
        $configFile = file_get_contents(base_path('config/commands.php'));

        // Verify the config file contains the conditional removal logic
        expect($configFile)
            ->toContain("env('APP_ENV') !== 'local'")
            ->toContain(UIShowcaseCommand::class);
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
 * The ui:showcase command removal in production cannot be fully tested via unit tests
 * because config files are loaded during bootstrap before we can change the environment.
 *
 * Note: DebugCommand is always available for user debugging in all environments.
 *
 * To verify production behavior, run these manual tests:
 *
 * 1. Test in production environment:
 *    ```bash
 *    APP_ENV=production php tuti list | grep "ui:showcase"
 *    # Should NOT show this command
 *
 *    APP_ENV=production php tuti ui:showcase
 *    # Should error: Command "ui:showcase" is not defined.
 *    ```
 *
 * 2. Test PHAR build:
 *    ```bash
 *    make build-phar
 *    php builds/tuti list
 *    # Should NOT show ui:showcase
 *
 *    php builds/tuti ui:showcase
 *    # Should error: Command "ui:showcase" is not defined.
 *    ```
 *
 * 3. Test in local development:
 *    ```bash
 *    php tuti list | grep "ui:showcase"
 *    # SHOULD show the command
 *
 *    php tuti ui:showcase    # Should work
 *    ```
 */
