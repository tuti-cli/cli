<?php

declare(strict_types=1);

/**
 * StackLoaderService Unit Tests
 *
 * Tests the stack manifest (stack.json) loading and data extraction.
 * This service reads a stack's definition file and provides methods
 * to access required/optional services, defaults, overrides, and metadata.
 *
 * Think of stack.json as a "recipe" — it tells tuti-cli what ingredients
 * (services) a stack needs and how to configure them.
 *
 * @see \App\Services\Stack\StackLoaderService
 */

use App\Services\Stack\StackLoaderService;
use App\Services\Storage\JsonFileService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────
// We create a real temp directory with real JSON files.
// We use the REAL JsonFileService (not a mock) because it's a simple
// JSON reader — mocking it would just test our mock, not real behavior.

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->jsonService = new JsonFileService;
    $this->service = new StackLoaderService($this->jsonService);
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── Helper: create a stack.json in a temp directory ────────────────────

function createStackManifest(string $dir, array $data): string
{
    $stackDir = $dir . '/test-stack';

    if (! is_dir($stackDir)) {
        mkdir($stackDir, 0755, true);
    }

    file_put_contents(
        $stackDir . '/stack.json',
        json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );

    return $stackDir;
}

// ─── A reusable "full" Laravel-like manifest for tests that need it ─────

function laravelManifest(): array
{
    return [
        'name' => 'laravel-stack',
        'version' => '1.0.0',
        'type' => 'php',
        'framework' => 'laravel',
        'description' => 'Laravel application stack',
        'required_services' => [
            'database' => [
                'category' => 'databases',
                'options' => ['postgres', 'mysql', 'mariadb'],
                'default' => 'postgres',
                'prompt' => 'Which database?',
            ],
        ],
        'optional_services' => [
            'cache' => [
                'category' => 'cache',
                'options' => ['redis'],
                'default' => null,
                'prompt' => 'Add Redis?',
            ],
            'mail' => [
                'category' => 'mail',
                'options' => ['mailpit'],
                'default' => 'mailpit',
                'prompt' => 'Add mail testing?',
            ],
        ],
        'service_overrides' => [
            'cache.redis' => [
                'memory_limit' => '256mb',
                'environments' => [
                    'dev' => ['maxmemory' => '128mb'],
                    'production' => ['maxmemory' => '512mb'],
                ],
            ],
        ],
    ];
}

// ─── load() ─────────────────────────────────────────────────────────────
// Reads stack.json from a directory. This is the entry point - everything
// else works on the array this method returns.

describe('load', function (): void {

    it('loads a valid stack.json from a directory', function (): void {
        $stackDir = createStackManifest($this->testDir, laravelManifest());

        $manifest = $this->service->load($stackDir);

        expect($manifest)
            ->toBeArray()
            ->toHaveKey('name')
            ->toHaveKey('version')
            ->toHaveKey('type')
            ->toHaveKey('framework');

        expect($manifest['name'])->toBe('laravel-stack');
        expect($manifest['framework'])->toBe('laravel');
    });

    it('throws RuntimeException when stack directory has no stack.json', function (): void {
        $emptyDir = $this->testDir . '/empty-stack';
        mkdir($emptyDir);

        expect(fn () => $this->service->load($emptyDir))
            ->toThrow(RuntimeException::class, 'Failed to load stack manifest');
    });

    it('throws RuntimeException when stack.json contains invalid JSON', function (): void {
        $stackDir = $this->testDir . '/bad-stack';
        mkdir($stackDir);
        file_put_contents($stackDir . '/stack.json', '{ invalid json !!!');

        expect(fn () => $this->service->load($stackDir))
            ->toThrow(RuntimeException::class, 'Failed to load stack manifest');
    });

    it('handles trailing slashes in stack path', function (): void {
        $stackDir = createStackManifest($this->testDir, laravelManifest());

        // Path with trailing slash should still work
        $manifest = $this->service->load($stackDir . '/');

        expect($manifest['name'])->toBe('laravel-stack');
    });
});

// ─── validate() ─────────────────────────────────────────────────────────
// Checks that a manifest has the 4 required fields: name, version, type, framework.
// Without these, the stack can't be identified or used.

describe('validate', function (): void {

    it('returns true for a valid manifest', function (): void {
        $manifest = laravelManifest();

        expect($this->service->validate($manifest))->toBeTrue();
    });

    it('throws RuntimeException when name is missing', function (): void {
        $manifest = laravelManifest();
        unset($manifest['name']);

        expect(fn () => $this->service->validate($manifest))
            ->toThrow(RuntimeException::class, 'missing required field: name');
    });

    it('throws RuntimeException when version is missing', function (): void {
        $manifest = laravelManifest();
        unset($manifest['version']);

        expect(fn () => $this->service->validate($manifest))
            ->toThrow(RuntimeException::class, 'missing required field: version');
    });

    it('throws RuntimeException when type is missing', function (): void {
        $manifest = laravelManifest();
        unset($manifest['type']);

        expect(fn () => $this->service->validate($manifest))
            ->toThrow(RuntimeException::class, 'missing required field: type');
    });

    it('throws RuntimeException when framework is missing', function (): void {
        $manifest = laravelManifest();
        unset($manifest['framework']);

        expect(fn () => $this->service->validate($manifest))
            ->toThrow(RuntimeException::class, 'missing required field: framework');
    });

    it('passes validation with only the 4 required fields', function (): void {
        $minimal = [
            'name' => 'test',
            'version' => '0.1.0',
            'type' => 'php',
            'framework' => 'laravel',
        ];

        expect($this->service->validate($minimal))->toBeTrue();
    });
});

// ─── validate - service structure ────────────────────────────────────────
// Validates that required_services and optional_services entries have correct structure.

describe('validate - service structure', function (): void {

    it('validates required_services have category and options', function (): void {
        $manifest = laravelManifest();
        $manifest['required_services']['database'] = ['prompt' => 'Pick DB'];

        expect(fn () => $this->service->validate($manifest))
            ->toThrow(RuntimeException::class, "required_services.database must have a string 'category' field");
    });

    it('validates required_services entries are arrays', function (): void {
        $manifest = laravelManifest();
        $manifest['required_services']['database'] = 'not-an-array';

        expect(fn () => $this->service->validate($manifest))
            ->toThrow(RuntimeException::class, 'required_services.database must be an array');
    });

    it('validates optional_services structure', function (): void {
        $manifest = laravelManifest();
        $manifest['optional_services']['cache'] = ['category' => 'cache'];

        expect(fn () => $this->service->validate($manifest))
            ->toThrow(RuntimeException::class, "optional_services.cache must have an array 'options' field");
    });

    it('validates generated_variables have valid generators', function (): void {
        $manifest = laravelManifest();
        $manifest['generated_variables'] = [
            'SECRET' => [
                'generator' => 'invalid_generator',
                'length' => 32,
            ],
        ];

        expect(fn () => $this->service->validate($manifest))
            ->toThrow(RuntimeException::class, "invalid generator 'invalid_generator'");
    });

    it('passes validation for well-formed manifest with all sections', function (): void {
        $manifest = laravelManifest();
        $manifest['generated_variables'] = [
            'APP_KEY' => [
                'generator' => 'laravel_key',
                'command' => 'php artisan key:generate --show',
            ],
            'DB_PASSWORD' => [
                'generator' => 'secure_random',
                'length' => 32,
            ],
        ];

        expect($this->service->validate($manifest))->toBeTrue();
    });
});

// ─── Metadata accessors ─────────────────────────────────────────────────
// Simple getters: getStackName(), getStackType(), getFramework()
// These just read keys from the manifest array.

describe('metadata accessors', function (): void {

    it('returns the stack name', function (): void {
        expect($this->service->getStackName(laravelManifest()))
            ->toBe('laravel-stack');
    });

    it('returns the stack type', function (): void {
        expect($this->service->getStackType(laravelManifest()))
            ->toBe('php');
    });

    it('returns the framework', function (): void {
        expect($this->service->getFramework(laravelManifest()))
            ->toBe('laravel');
    });
});

// ─── getRequiredServices() ──────────────────────────────────────────────
// Required services are ones the stack MUST have. For Laravel, a database
// is required — you can't run Laravel without one.

describe('getRequiredServices', function (): void {

    it('returns required services from manifest', function (): void {
        $required = $this->service->getRequiredServices(laravelManifest());

        expect($required)
            ->toBeArray()
            ->toHaveKey('database');

        expect($required['database']['category'])->toBe('databases');
        expect($required['database']['default'])->toBe('postgres');
    });

    it('returns empty array when no required services defined', function (): void {
        $manifest = [
            'name' => 'test',
            'version' => '1.0.0',
            'type' => 'php',
            'framework' => 'test',
        ];

        expect($this->service->getRequiredServices($manifest))
            ->toBeArray()
            ->toBeEmpty();
    });
});

// ─── getOptionalServices() ──────────────────────────────────────────────
// Optional services are things like Redis, Meilisearch, Mailpit — nice to
// have but not required.

describe('getOptionalServices', function (): void {

    it('returns optional services from manifest', function (): void {
        $optional = $this->service->getOptionalServices(laravelManifest());

        expect($optional)
            ->toBeArray()
            ->toHaveKey('cache')
            ->toHaveKey('mail');
    });

    it('returns empty array when no optional services defined', function (): void {
        $manifest = [
            'name' => 'test',
            'version' => '1.0.0',
            'type' => 'php',
            'framework' => 'test',
        ];

        expect($this->service->getOptionalServices($manifest))
            ->toBeArray()
            ->toBeEmpty();
    });
});

// ─── getDefaultServices() ───────────────────────────────────────────────
// This is the interesting one. It combines required + optional services
// and returns the ones that have a non-null default.
// Format: "category.service" like "databases.postgres" or "mail.mailpit"

describe('getDefaultServices', function (): void {

    it('includes required services with their defaults', function (): void {
        $defaults = $this->service->getDefaultServices(laravelManifest());

        // "database" is required with default "postgres" → "databases.postgres"
        expect($defaults)->toContain('databases.postgres');
    });

    it('includes optional services that have a non-null default', function (): void {
        $defaults = $this->service->getDefaultServices(laravelManifest());

        // "mail" has default "mailpit" → "mail.mailpit"
        expect($defaults)->toContain('mail.mailpit');
    });

    it('excludes optional services with null default', function (): void {
        $defaults = $this->service->getDefaultServices(laravelManifest());

        // "cache" has default null → should NOT appear
        expect($defaults)->not->toContain('cache.redis');
    });

    it('returns empty array when manifest has no services', function (): void {
        $manifest = [
            'name' => 'bare',
            'version' => '1.0.0',
            'type' => 'static',
            'framework' => 'html',
        ];

        expect($this->service->getDefaultServices($manifest))
            ->toBeArray()
            ->toBeEmpty();
    });
});

// ─── getServiceOverrides() ──────────────────────────────────────────────
// Some services need stack-specific config tweaks. For example, Redis in
// the Laravel stack might have a different memory limit than in WordPress.
// The key format is "category.service" like "cache.redis".

describe('getServiceOverrides', function (): void {

    it('returns overrides for a known service', function (): void {
        $overrides = $this->service->getServiceOverrides(laravelManifest(), 'cache.redis');

        expect($overrides)
            ->toBeArray()
            ->toHaveKey('memory_limit');

        expect($overrides['memory_limit'])->toBe('256mb');
    });

    it('returns empty array for service without overrides', function (): void {
        $overrides = $this->service->getServiceOverrides(laravelManifest(), 'databases.postgres');

        expect($overrides)
            ->toBeArray()
            ->toBeEmpty();
    });

    it('returns empty array when manifest has no service_overrides', function (): void {
        $manifest = ['name' => 'test', 'version' => '1.0.0', 'type' => 'php', 'framework' => 'test'];

        expect($this->service->getServiceOverrides($manifest, 'cache.redis'))
            ->toBeArray()
            ->toBeEmpty();
    });
});

// ─── getEnvironmentOverrides() ──────────────────────────────────────────
// Goes one level deeper: overrides for a specific service in a specific
// environment. Example: Redis maxmemory is 128mb in dev but 512mb in prod.

describe('getEnvironmentOverrides', function (): void {

    it('returns environment-specific overrides for a service', function (): void {
        $devOverrides = $this->service->getEnvironmentOverrides(
            laravelManifest(), 'cache.redis', 'dev',
        );

        expect($devOverrides)
            ->toBeArray()
            ->toHaveKey('maxmemory');

        expect($devOverrides['maxmemory'])->toBe('128mb');
    });

    it('returns different overrides per environment', function (): void {
        $manifest = laravelManifest();

        $dev = $this->service->getEnvironmentOverrides($manifest, 'cache.redis', 'dev');
        $prod = $this->service->getEnvironmentOverrides($manifest, 'cache.redis', 'production');

        expect($dev['maxmemory'])->toBe('128mb');
        expect($prod['maxmemory'])->toBe('512mb');
    });

    it('returns empty array for unknown environment', function (): void {
        $overrides = $this->service->getEnvironmentOverrides(
            laravelManifest(), 'cache.redis', 'nonexistent',
        );

        expect($overrides)
            ->toBeArray()
            ->toBeEmpty();
    });

    it('returns empty array for service without overrides', function (): void {
        $overrides = $this->service->getEnvironmentOverrides(
            laravelManifest(), 'databases.postgres', 'dev',
        );

        expect($overrides)
            ->toBeArray()
            ->toBeEmpty();
    });
});
