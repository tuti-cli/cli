<?php

declare(strict_types=1);

/**
 * BedrockEnvHandler Unit Tests
 *
 * Tests the Bedrock-specific .env file handling.
 * Bedrock uses different variable names than standard WordPress:
 * - DB_NAME, DB_USER (not DB_DATABASE, DB_USERNAME)
 * - WP_HOME, WP_SITEURL (not APP_URL)
 * - Requires WordPress salts
 *
 * @see BedrockEnvHandler
 */

use App\Services\Support\EnvFileService;
use App\Services\Support\EnvHandlers\BedrockEnvHandler;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->envService = new EnvFileService;
    $this->handler = new BedrockEnvHandler($this->envService);
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── detect() ───────────────────────────────────────────────────────────

describe('detect', function (): void {

    it('returns true for Bedrock project (has config/application.php and web/wp-config.php)', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php return [];');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        expect($this->handler->detect($this->testDir))->toBeTrue();
    });

    it('returns false when config/application.php is missing', function (): void {
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        expect($this->handler->detect($this->testDir))->toBeFalse();
    });

    it('returns false when web/wp-config.php is missing', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php return [];');

        expect($this->handler->detect($this->testDir))->toBeFalse();
    });

    it('returns false for standard WordPress (no Bedrock structure)', function (): void {
        file_put_contents($this->testDir . '/wp-config.php', '<?php');

        expect($this->handler->detect($this->testDir))->toBeFalse();
    });

    it('returns false for Laravel project', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        expect($this->handler->detect($this->testDir))->toBeFalse();
    });
});

// ─── configure() ─────────────────────────────────────────────────────────

describe('configure', function (): void {

    it('copies .env.example to .env when .env does not exist', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        $bedrockExample = <<<ENV
DB_NAME=
DB_USER=
DB_PASSWORD=
WP_HOME=
WP_SITEURL=
ENV;
        file_put_contents($this->testDir . '/.env.example', $bedrockExample);

        $this->handler->configure($this->testDir, 'my-bedrock');

        expect($this->testDir . '/.env')->toBeFile();
    });

    it('updates database variables to use Docker service names', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        $envContent = <<<ENV
DB_NAME=old_db
DB_USER=old_user
DB_PASSWORD=old_pass
DB_HOST=localhost
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-bedrock');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('DB_HOST=database');
        expect($content)->toContain('DB_NAME=wordpress');
        expect($content)->toContain('DB_USER=wordpress');
        expect($content)->toContain('DB_PASSWORD=secret');
    });

    it('uncomments and sets DB_HOST when commented out (Bedrock default)', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        // Bedrock's .env.example has DB_HOST commented out
        $envContent = <<<ENV
# DB_HOST='localhost'
DB_NAME=database_name
DB_USER=database_user
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-bedrock');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('DB_HOST=database');
        expect($content)->not->toContain("# DB_HOST");
    });

    it('updates WP_HOME and WP_SITEURL with project domain', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        $envContent = <<<ENV
WP_HOME=http://localhost
WP_SITEURL=http://localhost/wp
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-bedrock');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('WP_HOME=https://my-bedrock.local.test');
        expect($content)->toContain('WP_SITEURL=https://my-bedrock.local.test/wp');
    });

    it('sets WP_ENV to development', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        $envContent = "WP_ENV=production\n";
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-bedrock');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('WP_ENV=development');
    });

    it('generates WordPress salts for generateme placeholder values', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        // This is how Bedrock's .env.example looks with 'generateme' placeholders
        $envContent = <<<ENV
AUTH_KEY='generateme'
SECURE_AUTH_KEY='generateme'
LOGGED_IN_KEY='generateme'
NONCE_KEY='generateme'
AUTH_SALT='generateme'
SECURE_AUTH_SALT='generateme'
LOGGED_IN_SALT='generateme'
NONCE_SALT='generateme'
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-bedrock');

        $content = file_get_contents($this->testDir . '/.env');

        // Salts should be replaced with actual values (no longer 'generateme')
        // The values come from WordPress.org API or local fallback
        expect($content)->not->toContain("AUTH_KEY='generateme'");
        expect($content)->not->toContain("SECURE_AUTH_KEY='generateme'");

        // Should contain actual salt values (64 chars wrapped in quotes)
        expect($content)->toMatch("/AUTH_KEY='[^']+'/");
        expect($content)->toMatch("/SECURE_AUTH_KEY='[^']+'/");
    });

    it('generates WordPress salts for empty values', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        $envContent = <<<ENV
AUTH_KEY=
SECURE_AUTH_KEY=
LOGGED_IN_KEY=
NONCE_KEY=
AUTH_SALT=
SECURE_AUTH_SALT=
LOGGED_IN_SALT=
NONCE_SALT=
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-bedrock');

        $content = file_get_contents($this->testDir . '/.env');

        // Should contain actual salt values (64 chars wrapped in quotes)
        expect($content)->toMatch("/AUTH_KEY='[^']+'/");
        expect($content)->toMatch("/SECURE_AUTH_KEY='[^']+'/");
        expect($content)->toMatch("/LOGGED_IN_KEY='[^']+'/");
        expect($content)->toMatch("/NONCE_KEY='[^']+'/");
    });

    it('appends Tuti section with bedrock stack type', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        file_put_contents($this->testDir . '/.env', 'DB_NAME=test');

        $this->handler->configure($this->testDir, 'my-bedrock');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('TUTI-CLI DOCKER CONFIGURATION');
        expect($content)->toContain('PROJECT_NAME=my-bedrock');
        expect($content)->toContain('STACK_TYPE=bedrock');
    });

    it('does not duplicate Tuti section on re-configure', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        file_put_contents($this->testDir . '/.env', "DB_NAME=test\n# TUTI-CLI DOCKER CONFIGURATION\nPROJECT_NAME=old");

        $this->handler->configure($this->testDir, 'my-bedrock');

        $content = file_get_contents($this->testDir . '/.env');
        $tutiCount = substr_count($content, 'TUTI-CLI DOCKER CONFIGURATION');
        expect($tutiCount)->toBe(1);
    });

    it('returns false when .env does not exist and .env.example also does not exist', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        // No .env and no .env.example
        $result = $this->handler->configure($this->testDir, 'my-bedrock');

        expect($result)->toBeFalse();
    });
});

// ─── Integration-like Tests ──────────────────────────────────────────────

describe('full bedrock setup', function (): void {

    it('configures a complete Bedrock .env from .env.example', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        // Typical Bedrock .env.example content
        $bedrockExample = <<<ENV
# Bedrock configuration
DB_NAME=
DB_USER=
DB_PASSWORD=
DB_HOST=localhost

WP_ENV=development
WP_HOME=http://example.com
WP_SITEURL=http://example.com/wp

# Salts
AUTH_KEY=
SECURE_AUTH_KEY=
LOGGED_IN_KEY=
NONCE_KEY=
AUTH_SALT=
SECURE_AUTH_SALT=
LOGGED_IN_SALT=
NONCE_SALT=
ENV;
        file_put_contents($this->testDir . '/.env.example', $bedrockExample);

        $this->handler->configure($this->testDir, 'acme-corp');

        $content = file_get_contents($this->testDir . '/.env');

        // Verify database settings
        expect($content)->toContain('DB_HOST=database');
        expect($content)->toContain('DB_NAME=wordpress');
        expect($content)->toContain('DB_USER=wordpress');
        expect($content)->toContain('DB_PASSWORD=secret');

        // Verify URLs
        expect($content)->toContain('WP_HOME=https://acme-corp.local.test');
        expect($content)->toContain('WP_SITEURL=https://acme-corp.local.test/wp');

        // Verify Tuti section
        expect($content)->toContain('PROJECT_NAME=acme-corp');
        expect($content)->toContain('STACK_TYPE=bedrock');
    });
});
