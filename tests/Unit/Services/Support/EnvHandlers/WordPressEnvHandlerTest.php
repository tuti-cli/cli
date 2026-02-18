<?php

declare(strict_types=1);

/**
 * WordPressEnvHandler Unit Tests.
 *
 * Tests the standard WordPress .env handler.
 * Standard WordPress doesn't use .env natively - this is for Docker Compose only.
 *
 * @see WordPressEnvHandler
 */

use App\Services\Support\EnvFileService;
use App\Services\Support\EnvHandlers\WordPressEnvHandler;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->envService = new EnvFileService;
    $this->handler = new WordPressEnvHandler($this->envService);
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── detect() ───────────────────────────────────────────────────────────

describe('detect', function (): void {

    it('returns true for standard WordPress with wp-config.php', function (): void {
        file_put_contents($this->testDir . '/wp-config.php', '<?php // WordPress config');

        expect($this->handler->detect($this->testDir))->toBeTrue();
    });

    it('returns true for standard WordPress with wp-config-sample.php only', function (): void {
        file_put_contents($this->testDir . '/wp-config-sample.php', '<?php // WordPress sample config');

        expect($this->handler->detect($this->testDir))->toBeTrue();
    });

    it('returns false for Bedrock installation', function (): void {
        // Create Bedrock structure
        mkdir($this->testDir . '/config', 0755, true);
        mkdir($this->testDir . '/web', 0755, true);
        file_put_contents($this->testDir . '/config/application.php', '<?php // Bedrock config');
        file_put_contents($this->testDir . '/web/wp-config.php', '<?php // Bedrock wp-config');
        file_put_contents($this->testDir . '/wp-config.php', '<?php // This is still WordPress');

        // Bedrock should NOT match WordPressEnvHandler
        expect($this->handler->detect($this->testDir))->toBeFalse();
    });

    it('returns false for non-WordPress projects', function (): void {
        // Just a random directory
        file_put_contents($this->testDir . '/index.php', '<?php // Not WordPress');

        expect($this->handler->detect($this->testDir))->toBeFalse();
    });
});

// ─── configure() ─────────────────────────────────────────────────────────

describe('configure', function (): void {

    it('creates .env file with Tuti section when .env does not exist', function (): void {
        // Create WordPress structure
        file_put_contents($this->testDir . '/wp-config.php', '<?php // WordPress config');

        // No .env exists initially
        expect($this->testDir . '/.env')->not->toBeFile();

        $result = $this->handler->configure($this->testDir, 'my-wordpress');

        expect($result)->toBeTrue();
        expect($this->testDir . '/.env')->toBeFile();

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('TUTI-CLI DOCKER CONFIGURATION');
        expect($content)->toContain('PROJECT_NAME=my-wordpress');
        expect($content)->toContain('STACK_TYPE=wordpress');
        expect($content)->toContain('APACHE_DOCUMENT_ROOT=/var/www/html'); // Standard WordPress path
    });

    it('appends Tuti section to existing .env file', function (): void {
        // Create WordPress structure with existing .env
        file_put_contents($this->testDir . '/wp-config.php', '<?php // WordPress config');
        file_put_contents($this->testDir . '/.env', 'SOME_VAR=value');

        $result = $this->handler->configure($this->testDir, 'my-wordpress');

        expect($result)->toBeTrue();

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('SOME_VAR=value');
        expect($content)->toContain('TUTI-CLI DOCKER CONFIGURATION');
        expect($content)->toContain('PROJECT_NAME=my-wordpress');
    });

    it('returns false when Tuti section already exists', function (): void {
        // Create WordPress structure with Tuti section already present
        file_put_contents($this->testDir . '/wp-config.php', '<?php // WordPress config');
        file_put_contents($this->testDir . '/.env', "SOME_VAR=value\n# TUTI-CLI DOCKER CONFIGURATION\nPROJECT_NAME=existing");

        $result = $this->handler->configure($this->testDir, 'my-wordpress');

        // Should return false because section already exists
        expect($result)->toBeFalse();
    });

    it('uses custom PHP version from options', function (): void {
        file_put_contents($this->testDir . '/wp-config.php', '<?php // WordPress config');

        $this->handler->configure($this->testDir, 'my-wordpress', [
            'php_version' => '8.2',
        ]);

        $content = file_get_contents($this->testDir . '/.env');
        expect($content)->toContain('PHP_VERSION=8.2');
    });
});

// ─── Integration ─────────────────────────────────────────────────────────

describe('integration with EnvFileService', function (): void {

    it('creates valid .env that Docker Compose can use', function (): void {
        file_put_contents($this->testDir . '/wp-config.php', '<?php // WordPress config');

        $this->handler->configure($this->testDir, 'test-project');

        $content = file_get_contents($this->testDir . '/.env');

        // Verify all Docker Compose variables are present
        expect($content)->toContain('PROJECT_NAME=test-project');
        expect($content)->toContain('APP_DOMAIN=test-project.local.test');
        expect($content)->toContain('STACK_TYPE=wordpress');
        expect($content)->toContain('PHP_VERSION=');
        expect($content)->toContain('DOCKER_USER_ID=');
        expect($content)->toContain('DOCKER_GROUP_ID=');
    });
});
