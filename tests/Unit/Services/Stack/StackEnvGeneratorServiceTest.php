<?php

declare(strict_types=1);

/**
 * StackEnvGeneratorService Unit Tests
 *
 * Tests .env file generation from templates with variable replacement
 * and secure password generation.
 *
 * This service is SECURITY-CRITICAL — it generates passwords for databases,
 * Redis, MinIO, WordPress salts, etc. If it fails silently, production
 * environments could end up with default or empty passwords.
 *
 * Bug fixed in this PR:
 *   - Regex had a space in `(? :` making it invalid → `(?:` (no space)
 *   - preg_replace_callback passed array $matches to a method expecting int
 *   - When regex failed, preg_replace_callback returned NULL, destroying
 *     the entire .env content
 *
 * @see StackEnvGeneratorService
 */

use App\Services\Stack\StackEnvGeneratorService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->service = new StackEnvGeneratorService;
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── Helper: create an .env template file ───────────────────────────────

function createEnvTemplate(string $dir, string $content, string $name = '.env.example'): string
{
    $path = $dir . '/' . $name;
    file_put_contents($path, $content);

    return $path;
}

// ─── generate() ─────────────────────────────────────────────────────────
// The main method: reads template, replaces variables, generates passwords,
// writes .env file. This is the full pipeline.

describe('generate', function (): void {

    it('generates an .env file from a template', function (): void {
        $template = createEnvTemplate($this->testDir, "APP_NAME=MyApp\nAPP_ENV=local");
        $output = $this->testDir . '/.env';

        $this->service->generate($template, [], [], $output);

        expect($output)->toBeFile();
        expect(file_get_contents($output))->toContain('APP_NAME=MyApp');
    });

    it('replaces project config variables in the template', function (): void {
        $template = createEnvTemplate($this->testDir, <<<'ENV'
PROJECT_NAME={PROJECT_NAME}
APP_DOMAIN={APP_DOMAIN}
APP_ENV={APP_ENV}
ENV);
        $output = $this->testDir . '/.env';

        $this->service->generate($template, [], [
            'PROJECT_NAME' => 'my-laravel-app',
            'APP_DOMAIN' => 'my-laravel-app.local.test',
            'APP_ENV' => 'dev',
        ], $output);

        $content = file_get_contents($output);

        expect($content)
            ->toContain('PROJECT_NAME=my-laravel-app')
            ->toContain('APP_DOMAIN=my-laravel-app.local.test')
            ->toContain('APP_ENV=dev')
            ->not->toContain('{PROJECT_NAME}');
    });

    it('replaces CHANGE_THIS with secure random passwords', function (): void {
        $template = createEnvTemplate($this->testDir, <<<'ENV'
DB_PASSWORD=CHANGE_THIS
REDIS_PASSWORD=CHANGE_THIS
ENV);
        $output = $this->testDir . '/.env';

        $this->service->generate($template, [], [], $output);

        $content = file_get_contents($output);

        // CHANGE_THIS should be gone
        expect($content)->not->toContain('CHANGE_THIS');

        // Passwords should be 32-char hex strings (bin2hex of 16 random bytes)
        $lines = explode("\n", mb_trim($content));
        $dbPassword = explode('=', $lines[0], 2)[1];
        $redisPassword = explode('=', $lines[1], 2)[1];

        expect($dbPassword)
            ->toHaveLength(32)
            ->toMatch('/^[a-f0-9]{32}$/');

        expect($redisPassword)
            ->toHaveLength(32)
            ->toMatch('/^[a-f0-9]{32}$/');
    });

    it('replaces CHANGE_THIS_IN_PRODUCTION with secure random passwords', function (): void {
        // This was the regression bug: the broken regex never matched this pattern
        $template = createEnvTemplate($this->testDir, 'SECRET_KEY=CHANGE_THIS_IN_PRODUCTION');
        $output = $this->testDir . '/.env';

        $this->service->generate($template, [], [], $output);

        $content = file_get_contents($output);

        expect($content)
            ->not->toContain('CHANGE_THIS_IN_PRODUCTION')
            ->not->toContain('CHANGE_THIS');

        $password = explode('=', mb_trim($content), 2)[1];

        expect($password)
            ->toHaveLength(32)
            ->toMatch('/^[a-f0-9]{32}$/');
    });

    it('generates unique passwords for each CHANGE_THIS placeholder', function (): void {
        $template = createEnvTemplate($this->testDir, <<<'ENV'
DB_PASSWORD=CHANGE_THIS
REDIS_PASSWORD=CHANGE_THIS
MINIO_PASSWORD=CHANGE_THIS
ENV);
        $output = $this->testDir . '/.env';

        $this->service->generate($template, [], [], $output);

        $lines = explode("\n", mb_trim(file_get_contents($output)));
        $passwords = array_map(
            fn (string $line): string => explode('=', $line, 2)[1],
            $lines,
        );

        // All 3 passwords should be different (unique per placeholder)
        expect($passwords)->toHaveCount(3);
        expect(array_unique($passwords))->toHaveCount(3);
    });

    it('preserves values that are not placeholders', function (): void {
        $template = createEnvTemplate($this->testDir, <<<'ENV'
APP_NAME=MyApp
DB_PASSWORD=CHANGE_THIS
DB_HOST=postgres
DB_PORT=5432
APP_DEBUG=true
ENV);
        $output = $this->testDir . '/.env';

        $this->service->generate($template, [], [], $output);

        $content = file_get_contents($output);

        // Non-placeholder values should be untouched
        expect($content)
            ->toContain('APP_NAME=MyApp')
            ->toContain('DB_HOST=postgres')
            ->toContain('DB_PORT=5432')
            ->toContain('APP_DEBUG=true');

        // Placeholder should be replaced
        expect($content)->not->toContain('CHANGE_THIS');
    });

    it('handles both variable replacement and password generation together', function (): void {
        $template = createEnvTemplate($this->testDir, <<<'ENV'
PROJECT_NAME={PROJECT_NAME}
DB_PASSWORD=CHANGE_THIS
APP_DOMAIN={APP_DOMAIN}
REDIS_PASSWORD=CHANGE_THIS_IN_PRODUCTION
ENV);
        $output = $this->testDir . '/.env';

        $this->service->generate($template, [], [
            'PROJECT_NAME' => 'test-app',
            'APP_DOMAIN' => 'test-app.local.test',
        ], $output);

        $content = file_get_contents($output);

        expect($content)
            ->toContain('PROJECT_NAME=test-app')
            ->toContain('APP_DOMAIN=test-app.local.test')
            ->not->toContain('CHANGE_THIS')
            ->not->toContain('{PROJECT_NAME}')
            ->not->toContain('{APP_DOMAIN}');
    });

    it('preserves comments and blank lines', function (): void {
        $template = createEnvTemplate($this->testDir, <<<'ENV'
# Database Configuration
DB_HOST=postgres

# Security
DB_PASSWORD=CHANGE_THIS
ENV);
        $output = $this->testDir . '/.env';

        $this->service->generate($template, [], [], $output);

        $content = file_get_contents($output);

        expect($content)
            ->toContain('# Database Configuration')
            ->toContain('# Security');
    });

    it('throws RuntimeException when template file does not exist', function (): void {
        expect(fn () => $this->service->generate(
            '/nonexistent/template.env',
            [],
            [],
            $this->testDir . '/.env',
        ))->toThrow(RuntimeException::class, 'Template not found');
    });

    it('creates output file in the specified path', function (): void {
        $template = createEnvTemplate($this->testDir, 'APP_ENV=local');
        $outputDir = $this->testDir . '/project';
        mkdir($outputDir);
        $output = $outputDir . '/.env';

        $this->service->generate($template, [], [], $output);

        expect($output)->toBeFile();
    });
});

// ─── exists() ───────────────────────────────────────────────────────────
// Simple check: does an .env file exist at the given path?

describe('exists', function (): void {

    it('returns true when .env file exists', function (): void {
        $path = $this->testDir . '/.env';
        file_put_contents($path, 'APP_ENV=local');

        expect($this->service->exists($path))->toBeTrue();
    });

    it('returns false when .env file does not exist', function (): void {
        expect($this->service->exists($this->testDir . '/.env'))->toBeFalse();
    });
});

// ─── Security: password quality ─────────────────────────────────────────
// These tests verify the cryptographic quality of generated passwords.
// Since passwords are random, we can't test exact values — we test
// properties: length, format, uniqueness.

describe('password security', function (): void {

    it('generates 32-character hex passwords by default', function (): void {
        $template = createEnvTemplate($this->testDir, 'PASSWORD=CHANGE_THIS');
        $output = $this->testDir . '/.env';

        $this->service->generate($template, [], [], $output);

        $password = explode('=', mb_trim(file_get_contents($output)), 2)[1];

        // 32 hex chars = 16 bytes of entropy = 128 bits
        // This is cryptographically strong enough for service passwords
        expect($password)
            ->toHaveLength(32)
            ->toMatch('/^[a-f0-9]+$/');
    });

    it('produces different passwords on each generation', function (): void {
        $passwords = [];

        for ($i = 0; $i < 5; $i++) {
            $template = createEnvTemplate($this->testDir, 'PW=CHANGE_THIS', ".env.tpl.{$i}");
            $output = $this->testDir . "/.env.{$i}";

            $this->service->generate($template, [], [], $output);

            $passwords[] = explode('=', mb_trim(file_get_contents($output)), 2)[1];
        }

        // All 5 passwords should be unique (probability of collision is ~0)
        expect(array_unique($passwords))->toHaveCount(5);
    });
});
