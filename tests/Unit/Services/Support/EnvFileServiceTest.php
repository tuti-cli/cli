<?php

declare(strict_types=1);

/**
 * EnvFileService Unit Tests
 *
 * Tests the centralized .env file manipulation service.
 * This service handles all .env operations for all stack installers.
 *
 * @see EnvFileService
 */

use App\Services\Support\EnvFileService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->service = new EnvFileService;
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── exists() ───────────────────────────────────────────────────────────

describe('exists', function (): void {

    it('returns false when .env does not exist', function (): void {
        expect($this->service->exists($this->testDir))->toBeFalse();
    });

    it('returns true when .env exists', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        expect($this->service->exists($this->testDir))->toBeTrue();
    });
});

// ─── exampleExists() ────────────────────────────────────────────────────

describe('exampleExists', function (): void {

    it('returns false when .env.example does not exist', function (): void {
        expect($this->service->exampleExists($this->testDir))->toBeFalse();
    });

    it('returns true when .env.example exists', function (): void {
        file_put_contents($this->testDir . '/.env.example', 'APP_ENV=local');

        expect($this->service->exampleExists($this->testDir))->toBeTrue();
    });
});

// ─── copyExampleToEnv() ─────────────────────────────────────────────────

describe('copyExampleToEnv', function (): void {

    it('copies .env.example to .env when .env does not exist', function (): void {
        file_put_contents($this->testDir . '/.env.example', "APP_ENV=local\nAPP_KEY=");

        $result = $this->service->copyExampleToEnv($this->testDir);

        expect($result)->toBeTrue();
        expect($this->testDir . '/.env')->toBeFile();
        expect(file_get_contents($this->testDir . '/.env'))->toBe("APP_ENV=local\nAPP_KEY=");
    });

    it('returns false when .env already exists', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=production');
        file_put_contents($this->testDir . '/.env.example', 'APP_ENV=local');

        $result = $this->service->copyExampleToEnv($this->testDir);

        expect($result)->toBeFalse();
        expect(file_get_contents($this->testDir . '/.env'))->toBe('APP_ENV=production');
    });

    it('returns false when .env.example does not exist', function (): void {
        $result = $this->service->copyExampleToEnv($this->testDir);

        expect($result)->toBeFalse();
        expect($this->testDir . '/.env')->not->toBeFile();
    });
});

// ─── read() ─────────────────────────────────────────────────────────────

describe('read', function (): void {

    it('returns empty string when .env does not exist', function (): void {
        expect($this->service->read($this->testDir))->toBe('');
    });

    it('returns content when .env exists', function (): void {
        file_put_contents($this->testDir . '/.env', "APP_ENV=local\nAPP_KEY=base64:xxx");

        $content = $this->service->read($this->testDir);

        expect($content)->toBe("APP_ENV=local\nAPP_KEY=base64:xxx");
    });
});

// ─── write() ────────────────────────────────────────────────────────────

describe('write', function (): void {

    it('creates .env file with content', function (): void {
        $result = $this->service->write($this->testDir, "APP_ENV=local\n");

        expect($result)->toBeTrue();
        expect(file_get_contents($this->testDir . '/.env'))->toBe("APP_ENV=local\n");
    });

    it('overwrites existing .env file', function (): void {
        file_put_contents($this->testDir . '/.env', 'OLD_CONTENT');

        $this->service->write($this->testDir, 'NEW_CONTENT');

        expect(file_get_contents($this->testDir . '/.env'))->toBe('NEW_CONTENT');
    });
});

// ─── setValue() ─────────────────────────────────────────────────────────

describe('setValue', function (): void {

    it('updates existing value in .env', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        $this->service->setValue($this->testDir, 'APP_ENV', 'production');

        expect(file_get_contents($this->testDir . '/.env'))->toBe('APP_ENV=production');
    });

    it('adds new value when key does not exist', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        $this->service->setValue($this->testDir, 'APP_DEBUG', 'true');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('APP_DEBUG=true');
    });
});

// ─── updateValues() ─────────────────────────────────────────────────────

describe('updateValues', function (): void {

    it('updates multiple values using regex patterns', function (): void {
        file_put_contents($this->testDir . '/.env', "DB_HOST=localhost\nDB_PORT=3306\nDB_NAME=test");

        $this->service->updateValues($this->testDir, [
            '/^DB_HOST=.*$/m' => 'DB_HOST=postgres',
            '/^DB_PORT=.*$/m' => 'DB_PORT=5432',
        ]);

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('DB_HOST=postgres');
        expect($content)->toContain('DB_PORT=5432');
        expect($content)->toContain('DB_NAME=test'); // Unchanged
    });

    it('handles commented variables', function (): void {
        file_put_contents($this->testDir . '/.env', "# DB_HOST=localhost\n# DB_PORT=3306");

        $this->service->updateValues($this->testDir, [
            '/^# DB_HOST=.*$/m' => 'DB_HOST=postgres',
        ]);

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('DB_HOST=postgres');
    });
});

// ─── hasTutiSection() ───────────────────────────────────────────────────

describe('hasTutiSection', function (): void {

    it('returns false when Tuti section does not exist', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        expect($this->service->hasTutiSection($this->testDir))->toBeFalse();
    });

    it('returns true when TUTI-CLI DOCKER CONFIGURATION marker exists', function (): void {
        file_put_contents($this->testDir . '/.env', "APP_ENV=local\n# TUTI-CLI DOCKER CONFIGURATION\nPROJECT_NAME=test");

        expect($this->service->hasTutiSection($this->testDir))->toBeTrue();
    });

    it('returns true when emoji TUTI-CLI marker exists', function (): void {
        file_put_contents($this->testDir . '/.env', "APP_ENV=local\n# 🐳 TUTI-CLI DOCKER CONFIGURATION\nPROJECT_NAME=test");

        expect($this->service->hasTutiSection($this->testDir))->toBeTrue();
    });
});

// ─── appendTutiSection() ────────────────────────────────────────────────

describe('appendTutiSection', function (): void {

    it('appends Tuti section to .env file', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        $result = $this->service->appendTutiSection($this->testDir, 'my-project');

        expect($result)->toBeTrue();
        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('APP_ENV=local');
        expect($content)->toContain('TUTI-CLI DOCKER CONFIGURATION');
        expect($content)->toContain('PROJECT_NAME=my-project');
        expect($content)->toContain('APP_DOMAIN=my-project.local.test');
    });

    it('returns false when Tuti section already exists', function (): void {
        file_put_contents($this->testDir . '/.env', "APP_ENV=local\n# TUTI-CLI DOCKER CONFIGURATION");

        $result = $this->service->appendTutiSection($this->testDir, 'my-project');

        expect($result)->toBeFalse();
    });

    it('includes stack_type option when provided', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        $this->service->appendTutiSection($this->testDir, 'my-project', [
            'stack_type' => 'bedrock',
        ]);

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('STACK_TYPE=bedrock');
    });

    it('sets APACHE_DOCUMENT_ROOT for bedrock stack type', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        $this->service->appendTutiSection($this->testDir, 'my-project', [
            'stack_type' => 'bedrock',
        ]);

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('APACHE_DOCUMENT_ROOT=/var/www/html/web');
    });

    it('sets APACHE_DOCUMENT_ROOT for non-bedrock stack types', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        $this->service->appendTutiSection($this->testDir, 'my-project', [
            'stack_type' => 'laravel',
        ]);

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('APACHE_DOCUMENT_ROOT=/var/www/html');
    });

    it('includes php_version option when provided', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        $this->service->appendTutiSection($this->testDir, 'my-project', [
            'php_version' => '8.3',
        ]);

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('PHP_VERSION=8.3');
    });

    it('includes Docker user/group IDs', function (): void {
        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        $this->service->appendTutiSection($this->testDir, 'my-project');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('DOCKER_USER_ID=');
        expect($content)->toContain('DOCKER_GROUP_ID=');
    });
});

// ─── Edge Cases ─────────────────────────────────────────────────────────

describe('edge cases', function (): void {

    it('handles paths with trailing slashes', function (): void {
        $pathWithSlash = $this->testDir . '/';

        file_put_contents($this->testDir . '/.env', 'APP_ENV=local');

        expect($this->service->exists($pathWithSlash))->toBeTrue();
        expect($this->service->read($pathWithSlash))->toBe('APP_ENV=local');
    });

    it('handles empty .env file', function (): void {
        file_put_contents($this->testDir . '/.env', '');

        $this->service->appendTutiSection($this->testDir, 'test-project');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('TUTI-CLI DOCKER CONFIGURATION');
    });

    it('handles .env with only whitespace', function (): void {
        file_put_contents($this->testDir . '/.env', "   \n\n   ");

        $this->service->appendTutiSection($this->testDir, 'test-project');

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('TUTI-CLI DOCKER CONFIGURATION');
    });
});
