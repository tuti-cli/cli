<?php

declare(strict_types=1);

/**
 * ProjectMetadataService Unit Tests
 *
 * Tests the project config manager that reads/writes .tuti/config.json.
 * This service converts raw JSON into a ProjectConfigurationVO value object
 * and handles variable substitution ({{SYSTEM_USER}}, {{PROJECT_ROOT}}).
 *
 * Dependency chain: ProjectMetadataService → ProjectDirectoryService
 *                                          → JsonFileService
 *                   ProjectDirectoryService → WorkingDirectoryService
 *
 * We use real instances for all dependencies since they're all lightweight
 * filesystem-only services we've already tested.
 *
 * @see \App\Services\Project\ProjectMetadataService
 * @see \App\Domain\Project\ValueObjects\ProjectConfigurationVO
 */

use App\Domain\Project\ValueObjects\ProjectConfigurationVO;
use App\Services\Context\WorkingDirectoryService;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use App\Services\Storage\JsonFileService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────
// We build the full real dependency chain: WorkingDirectory → ProjectDirectory → ProjectMetadata
// All pointed at a temp directory with a .tuti/ folder ready to go.

beforeEach(function (): void {
    $this->testDir = createTestDirectory();

    // Build the dependency chain with real services
    $workingDir = new WorkingDirectoryService;
    $workingDir->setWorkingDirectory($this->testDir);

    $this->directoryService = new ProjectDirectoryService($workingDir);
    $this->jsonService = new JsonFileService;
    $this->service = new ProjectMetadataService($this->directoryService, $this->jsonService);

    // Create .tuti directory (most tests need it)
    mkdir($this->testDir . '/.tuti', 0755, true);
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── Helper: write a config.json with given data ────────────────────────

function writeConfig(string $testDir, array $data): void
{
    file_put_contents(
        $testDir . '/.tuti/config.json',
        json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    );
}

// ─── A reusable valid config structure ──────────────────────────────────

function validConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'project' => [
            'name' => 'my-app',
            'type' => 'laravel',
            'version' => '1.0.0',
        ],
        'environments' => [
            'dev' => [
                'domain' => 'my-app.local.test',
                'app_env' => 'dev',
            ],
        ],
    ], $overrides);
}

// ─── load() ─────────────────────────────────────────────────────────────
// Reads .tuti/config.json, substitutes variables, and returns a
// ProjectConfigurationVO value object.

describe('load', function (): void {

    it('loads config and returns a ProjectConfigurationVO', function (): void {
        writeConfig($this->testDir, validConfig());

        $config = $this->service->load();

        expect($config)->toBeInstanceOf(ProjectConfigurationVO::class);
    });

    it('populates VO properties from config data', function (): void {
        writeConfig($this->testDir, validConfig());

        $config = $this->service->load();

        expect($config->name)->toBe('my-app');
        expect($config->type)->toBe('laravel');
        expect($config->version)->toBe('1.0.0');
    });

    it('loads environments into the VO', function (): void {
        writeConfig($this->testDir, validConfig());

        $config = $this->service->load();

        expect($config->environments)
            ->toBeArray()
            ->toHaveKey('dev');

        expect($config->environments['dev']['domain'])->toBe('my-app.local.test');
    });

    it('preserves the full raw config in the VO', function (): void {
        $data = validConfig();
        writeConfig($this->testDir, $data);

        $config = $this->service->load();

        // rawConfig should contain the original (post-substitution) data
        expect($config->rawConfig)
            ->toBeArray()
            ->toHaveKey('project')
            ->toHaveKey('environments');
    });

    it('substitutes {{PROJECT_ROOT}} in config values', function (): void {
        // Config file with a placeholder that should be replaced with the real path
        $data = validConfig([
            'project' => [
                'root' => '{{PROJECT_ROOT}}',
            ],
        ]);

        writeConfig($this->testDir, $data);

        $config = $this->service->load();

        expect($config->rawConfig['project']['root'])
            ->toBe(realpath($this->testDir))
            ->not->toContain('{{PROJECT_ROOT}}');
    });

    it('substitutes {{SYSTEM_USER}} in config values', function (): void {
        $data = validConfig([
            'project' => [
                'owner' => '{{SYSTEM_USER}}',
            ],
        ]);

        writeConfig($this->testDir, $data);

        $config = $this->service->load();

        // Should be replaced with something (the current user or 'tuti' fallback)
        expect($config->rawConfig['project']['owner'])
            ->toBeString()
            ->not->toContain('{{SYSTEM_USER}}')
            ->not->toBeEmpty();
    });

    it('uses defaults for missing project fields', function (): void {
        // Config with empty project section — VO should use fallback values
        writeConfig($this->testDir, ['project' => [], 'environments' => []]);

        $config = $this->service->load();

        expect($config->name)->toBe('unknown');
        expect($config->type)->toBe('unknown');
        expect($config->version)->toBe('0.0.0');
    });

    it('uses defaults when project key is completely missing', function (): void {
        writeConfig($this->testDir, ['environments' => []]);

        $config = $this->service->load();

        expect($config->name)->toBe('unknown');
        expect($config->type)->toBe('unknown');
    });

    it('throws RuntimeException when config.json does not exist', function (): void {
        // .tuti exists but config.json doesn't
        expect(fn () => $this->service->load())
            ->toThrow(RuntimeException::class, 'Configuration file not found');
    });

    it('throws RuntimeException when config.json has invalid JSON', function (): void {
        file_put_contents($this->testDir . '/.tuti/config.json', '{ broken json!!!');

        expect(fn () => $this->service->load())
            ->toThrow(RuntimeException::class, 'Failed to load project config');
    });
});

// ─── create() ───────────────────────────────────────────────────────────
// Writes a new config.json. Refuses if one already exists to prevent
// accidental overwrites of project configuration.

describe('create', function (): void {

    it('creates config.json in the .tuti directory', function (): void {
        $this->service->create(validConfig());

        $path = $this->testDir . '/.tuti/config.json';

        expect($path)->toBeFile();
    });

    it('writes valid JSON content', function (): void {
        $this->service->create(validConfig());

        $raw = file_get_contents($this->testDir . '/.tuti/config.json');

        expect($raw)->toBeValidJson();
    });

    it('stores the provided config data', function (): void {
        $data = validConfig();
        $this->service->create($data);

        $saved = json_decode(
            file_get_contents($this->testDir . '/.tuti/config.json'),
            true,
        );

        expect($saved['project']['name'])->toBe('my-app');
        expect($saved['project']['type'])->toBe('laravel');
        expect($saved['environments']['dev']['domain'])->toBe('my-app.local.test');
    });

    it('throws RuntimeException if config.json already exists', function (): void {
        writeConfig($this->testDir, validConfig());

        expect(fn () => $this->service->create(validConfig()))
            ->toThrow(RuntimeException::class, 'Configuration file already exists');
    });
});

// ─── isInitialized() ────────────────────────────────────────────────────
// Checks if the project root can be determined. Returns true if the
// working directory is set and valid, false if it throws.

describe('isInitialized', function (): void {

    it('returns true when working directory is set', function (): void {
        expect($this->service->isInitialized())->toBeTrue();
    });
});

// ─── Round-trip: create() → load() ─────────────────────────────────────
// The most important test: what you create() should be what you load() back.

describe('round-trip', function (): void {

    it('loads back exactly what was created', function (): void {
        $data = validConfig([
            'project' => [
                'name' => 'roundtrip-app',
                'type' => 'wordpress',
                'version' => '2.0.0',
            ],
        ]);

        $this->service->create($data);
        $config = $this->service->load();

        expect($config->name)->toBe('roundtrip-app');
        expect($config->type)->toBe('wordpress');
        expect($config->version)->toBe('2.0.0');
    });

    it('preserves environment configuration through round-trip', function (): void {
        $data = validConfig([
            'environments' => [
                'dev' => [
                    'domain' => 'test.local.test',
                    'debug' => true,
                ],
                'staging' => [
                    'domain' => 'staging.example.com',
                    'debug' => false,
                ],
            ],
        ]);

        $this->service->create($data);
        $config = $this->service->load();

        expect($config->environments)
            ->toHaveCount(2)
            ->toHaveKey('dev')
            ->toHaveKey('staging');

        expect($config->environments['dev']['debug'])->toBeTrue();
        expect($config->environments['staging']['debug'])->toBeFalse();
    });
});
