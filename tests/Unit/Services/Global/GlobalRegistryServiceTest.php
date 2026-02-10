<?php

declare(strict_types=1);

/**
 * GlobalRegistryService Unit Tests
 *
 * Tests the global project registry (~/.tuti/projects.json).
 * This service acts as a "phone book" for all projects managed by tuti-cli.
 * When you create a project with `tuti stack:laravel`, it gets registered here
 * so that `projects:list` and other multi-project commands can find it.
 *
 * Same putenv('HOME') technique as GlobalSettingsServiceTest — we redirect
 * the home directory to a temp folder so we don't touch real user data.
 *
 * @see GlobalRegistryService
 */

use App\Services\Global\GlobalRegistryService;
use App\Services\Storage\JsonFileService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->originalHome = getenv('HOME');

    putenv("HOME={$this->testDir}");
    mkdir($this->testDir . '/.tuti', 0755, true);

    $this->service = new GlobalRegistryService(new JsonFileService);
});

afterEach(function (): void {
    putenv("HOME={$this->originalHome}");
    cleanupTestDirectory($this->testDir);
});

// ─── Helper: get the path to projects.json for assertions ───────────────

function registryPath(string $testDir): string
{
    return $testDir . '/.tuti/projects.json';
}

// ─── register() ─────────────────────────────────────────────────────────
// Adds or updates a project in the registry. Merges with existing data
// and always sets last_accessed_at to the current timestamp.

describe('register', function (): void {

    it('creates projects.json when registering the first project', function (): void {
        $this->service->register('my-app', [
            'path' => '/home/user/projects/my-app',
            'stack' => 'laravel',
        ]);

        expect(registryPath($this->testDir))->toBeFile();
    });

    it('stores project data with the given name as key', function (): void {
        $this->service->register('my-app', [
            'path' => '/home/user/projects/my-app',
            'stack' => 'laravel',
        ]);

        $project = $this->service->getProject('my-app');

        expect($project)
            ->toBeArray()
            ->toHaveKey('path')
            ->toHaveKey('stack');

        expect($project['path'])->toBe('/home/user/projects/my-app');
        expect($project['stack'])->toBe('laravel');
    });

    it('adds last_accessed_at timestamp automatically', function (): void {
        $this->service->register('my-app', [
            'path' => '/home/user/projects/my-app',
        ]);

        $project = $this->service->getProject('my-app');

        expect($project)
            ->toHaveKey('last_accessed_at');

        // Should be a valid ISO 8601 date string
        expect($project['last_accessed_at'])
            ->toBeString()
            ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    });

    it('merges new data with existing project data', function (): void {
        // First registration: path and stack
        $this->service->register('my-app', [
            'path' => '/home/user/projects/my-app',
            'stack' => 'laravel',
        ]);

        // Second registration: add port info (path and stack should survive)
        $this->service->register('my-app', [
            'port' => 8080,
        ]);

        $project = $this->service->getProject('my-app');

        // Old data preserved
        expect($project['path'])->toBe('/home/user/projects/my-app');
        expect($project['stack'])->toBe('laravel');

        // New data added
        expect($project['port'])->toBe(8080);
    });

    it('overwrites existing keys when re-registering with new values', function (): void {
        $this->service->register('my-app', [
            'stack' => 'laravel',
        ]);

        $this->service->register('my-app', [
            'stack' => 'wordpress',
        ]);

        expect($this->service->getProject('my-app')['stack'])->toBe('wordpress');
    });

    it('updates last_accessed_at on re-registration', function (): void {
        $this->service->register('my-app', ['path' => '/first']);
        $firstTimestamp = $this->service->getProject('my-app')['last_accessed_at'];

        // Sleep briefly so timestamp differs (Carbon uses seconds)
        usleep(1_100_000); // 1.1 seconds

        $this->service->register('my-app', ['path' => '/first']);
        $secondTimestamp = $this->service->getProject('my-app')['last_accessed_at'];

        expect($secondTimestamp)->not->toBe($firstTimestamp);
    });

    it('registers multiple different projects', function (): void {
        $this->service->register('app-one', ['stack' => 'laravel']);
        $this->service->register('app-two', ['stack' => 'wordpress']);
        $this->service->register('app-three', ['stack' => 'laravel']);

        $all = $this->service->all();

        expect($all)
            ->toHaveCount(3)
            ->toHaveKey('app-one')
            ->toHaveKey('app-two')
            ->toHaveKey('app-three');
    });
});

// ─── getProject() ───────────────────────────────────────────────────────
// Retrieves a single project by name. Returns null if not found.

describe('getProject', function (): void {

    it('returns project data for a registered project', function (): void {
        $this->service->register('my-app', [
            'path' => '/projects/my-app',
            'stack' => 'laravel',
        ]);

        $project = $this->service->getProject('my-app');

        expect($project)
            ->toBeArray()
            ->toHaveKey('path')
            ->toHaveKey('stack')
            ->toHaveKey('last_accessed_at');
    });

    it('returns null for unregistered project', function (): void {
        expect($this->service->getProject('nonexistent'))->toBeNull();
    });

    it('returns null when registry file does not exist', function (): void {
        // Remove the .tuti directory entirely
        cleanupTestDirectory($this->testDir . '/.tuti');

        expect($this->service->getProject('anything'))->toBeNull();
    });
});

// ─── all() ──────────────────────────────────────────────────────────────
// Returns all registered projects as an associative array (name => data).

describe('all', function (): void {

    it('returns empty array when no projects registered', function (): void {
        expect($this->service->all())
            ->toBeArray()
            ->toBeEmpty();
    });

    it('returns empty array when registry file does not exist', function (): void {
        cleanupTestDirectory($this->testDir . '/.tuti');

        expect($this->service->all())
            ->toBeArray()
            ->toBeEmpty();
    });

    it('returns all registered projects', function (): void {
        $this->service->register('app-one', ['stack' => 'laravel']);
        $this->service->register('app-two', ['stack' => 'wordpress']);

        $all = $this->service->all();

        expect($all)
            ->toHaveCount(2)
            ->toHaveKey('app-one')
            ->toHaveKey('app-two');

        expect($all['app-one']['stack'])->toBe('laravel');
        expect($all['app-two']['stack'])->toBe('wordpress');
    });

    it('reflects changes after new registration', function (): void {
        expect($this->service->all())->toHaveCount(0);

        $this->service->register('first', ['stack' => 'laravel']);
        expect($this->service->all())->toHaveCount(1);

        $this->service->register('second', ['stack' => 'wordpress']);
        expect($this->service->all())->toHaveCount(2);
    });
});

// ─── Data persistence ───────────────────────────────────────────────────
// Verifies the JSON file on disk contains correct structure.

describe('persistence', function (): void {

    it('writes valid JSON to projects.json', function (): void {
        $this->service->register('my-app', ['stack' => 'laravel']);

        $raw = file_get_contents(registryPath($this->testDir));

        expect($raw)->toBeValidJson();
    });

    it('stores projects under a "projects" key in the JSON', function (): void {
        $this->service->register('my-app', ['stack' => 'laravel']);

        $data = json_decode(file_get_contents(registryPath($this->testDir)), true);

        expect($data)
            ->toHaveKey('projects')
            ->and($data['projects'])
            ->toHaveKey('my-app');
    });

    it('survives a fresh service instance (data persisted on disk)', function (): void {
        $this->service->register('persisted-app', ['stack' => 'laravel']);

        // New instance reads from the same file
        $fresh = new GlobalRegistryService(new JsonFileService);

        expect($fresh->getProject('persisted-app'))
            ->toBeArray()
            ->toHaveKey('stack');
    });
});
