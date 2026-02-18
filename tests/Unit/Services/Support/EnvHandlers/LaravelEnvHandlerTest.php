<?php

declare(strict_types=1);

/**
 * LaravelEnvHandler Unit Tests
 *
 * Tests the Laravel-specific .env file handling.
 * Laravel uses standard variable names and supports Redis configuration.
 *
 * @see LaravelEnvHandler
 */

use App\Services\Support\EnvFileService;
use App\Services\Support\EnvHandlers\LaravelEnvHandler;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->envService = new EnvFileService;
    $this->handler = new LaravelEnvHandler($this->envService);
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── detect() ───────────────────────────────────────────────────────────

describe('detect', function (): void {

    it('returns true for Laravel project (has artisan file)', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        expect($this->handler->detect($this->testDir))->toBeTrue();
    });

    it('returns false when artisan file is missing', function (): void {
        file_put_contents($this->testDir . '/composer.json', '{}');

        expect($this->handler->detect($this->testDir))->toBeFalse();
    });

    it('returns false for WordPress project', function (): void {
        file_put_contents($this->testDir . '/wp-config.php', '<?php');

        expect($this->handler->detect($this->testDir))->toBeFalse();
    });

    it('returns false for Bedrock project', function (): void {
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php');

        expect($this->handler->detect($this->testDir))->toBeFalse();
    });
});

// ─── configure() ─────────────────────────────────────────────────────────

describe('configure', function (): void {

    it('returns false when .env does not exist', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        $result = $this->handler->configure($this->testDir, 'my-laravel');

        expect($result)->toBeFalse();
    });

    it('updates database settings to use PostgreSQL Docker container', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        $envContent = <<<ENV
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-laravel');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('DB_CONNECTION=pgsql');
        expect($content)->toContain('DB_HOST=postgres');
        expect($content)->toContain('DB_PORT=5432');
        expect($content)->toContain('DB_DATABASE=laravel');
        expect($content)->toContain('DB_USERNAME=laravel');
        expect($content)->toContain('DB_PASSWORD=secret');
    });

    it('updates APP_URL with project domain', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        file_put_contents($this->testDir . '/.env', 'APP_URL=http://localhost');

        $this->handler->configure($this->testDir, 'my-app');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('APP_URL=https://my-app.local.test');
    });

    it('updates mail settings for Mailpit', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        $envContent = <<<ENV
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-laravel');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('MAIL_HOST=mailpit');
        expect($content)->toContain('MAIL_PORT=1025');
    });

    it('updates Redis settings for Docker container', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        file_put_contents($this->testDir . '/.env', 'REDIS_HOST=127.0.0.1');

        $this->handler->configure($this->testDir, 'my-laravel');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('REDIS_HOST=redis');
    });

    it('enables Redis cache/session when has_redis option is true', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        // Include all the variables that will be updated
        $envContent = <<<ENV
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-laravel', ['has_redis' => true]);

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('CACHE_STORE=redis');
        expect($content)->toContain('SESSION_DRIVER=redis');
        expect($content)->toContain('QUEUE_CONNECTION=redis');
    });

    it('does not enable Redis cache/session when has_redis is false', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        file_put_contents($this->testDir . '/.env', 'CACHE_STORE=file');

        $this->handler->configure($this->testDir, 'my-laravel', ['has_redis' => false]);

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->not->toContain('CACHE_STORE=redis');
    });

    it('appends Tuti section with laravel stack type', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        $this->handler->configure($this->testDir, 'my-laravel');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('TUTI-CLI DOCKER CONFIGURATION');
        expect($content)->toContain('PROJECT_NAME=my-laravel');
        expect($content)->toContain('STACK_TYPE=laravel');
    });

    it('does not duplicate Tuti section on re-configure', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        file_put_contents($this->testDir . '/.env', "APP_ENV=local\n# TUTI-CLI DOCKER CONFIGURATION\nPROJECT_NAME=old");

        $this->handler->configure($this->testDir, 'my-laravel');

        $content = file_get_contents($this->testDir . '/.env');
        $tutiCount = substr_count($content, 'TUTI-CLI DOCKER CONFIGURATION');
        expect($tutiCount)->toBe(1);
    });

    it('handles commented DB variables in fresh Laravel install', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        // Fresh Laravel .env often has commented DB_* vars
        $envContent = <<<ENV
APP_NAME=Laravel
# DB_HOST=127.0.0.1
# DB_PORT=3306
ENV;
        file_put_contents($this->testDir . '/.env', $envContent);

        $this->handler->configure($this->testDir, 'my-laravel');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('DB_HOST=postgres');
        expect($content)->toContain('DB_PORT=5432');
    });
});
