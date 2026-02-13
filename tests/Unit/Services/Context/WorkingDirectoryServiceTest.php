<?php

declare(strict_types=1);

/**
 * WorkingDirectoryService Unit Tests
 *
 * Tests the "where am I?" service that tracks the user's current directory.
 * When a developer runs `tuti local:start` from /projects/my-app, this service
 * provides path resolution for .tuti/, .env, and other project files.
 *
 * Key behavior: the working directory is cached after the first call.
 * setWorkingDirectory() overrides this cache (used by tests and commands).
 * reset() clears the cache so getcwd() is called again.
 *
 * @see WorkingDirectoryService
 */

use App\Services\Context\WorkingDirectoryService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────
// We create a temp directory to simulate a project directory.
// We use setWorkingDirectory() instead of chdir() — it's safer because
// chdir() changes the process-wide directory and can leak between tests.

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->service = new WorkingDirectoryService;
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── getWorkingDirectory() ──────────────────────────────────────────────
// Returns the directory the user is running the command from.
// First call uses getcwd(), then caches the result.

describe('getWorkingDirectory', function (): void {

    it('returns the explicitly set working directory', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getWorkingDirectory())->toBe(realpath($this->testDir));
    });

    it('returns current directory when none is set', function (): void {
        // No setWorkingDirectory() call → falls back to getcwd()
        $result = $this->service->getWorkingDirectory();

        expect($result)
            ->toBeString()
            ->toBeDirectory();
    });

    it('caches the result after first call', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        $first = $this->service->getWorkingDirectory();
        $second = $this->service->getWorkingDirectory();

        // Same object, same result — no repeated filesystem calls
        expect($second)->toBe($first);
    });
});

// ─── setWorkingDirectory() ──────────────────────────────────────────────
// Overrides the working directory. Used by commands that need to operate
// on a different project, and by tests to avoid chdir().

describe('setWorkingDirectory', function (): void {

    it('sets the working directory to a valid path', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getWorkingDirectory())->toBe(realpath($this->testDir));
    });

    it('resolves symlinks via realpath', function (): void {
        // realpath() resolves any symlinks or relative components
        // The stored path should be the canonical absolute path
        $this->service->setWorkingDirectory($this->testDir);

        $stored = $this->service->getWorkingDirectory();

        expect($stored)->toBe(realpath($this->testDir));
        expect($stored)->not->toContain('..'); // No relative components
    });

    it('throws RuntimeException for nonexistent directory', function (): void {
        expect(fn () => $this->service->setWorkingDirectory('/nonexistent/fake/path'))
            ->toThrow(RuntimeException::class, 'Directory does not exist');
    });

    it('throws RuntimeException when path is a file, not a directory', function (): void {
        $filePath = $this->testDir . '/some-file.txt';
        file_put_contents($filePath, 'content');

        expect(fn () => $this->service->setWorkingDirectory($filePath))
            ->toThrow(RuntimeException::class, 'Directory does not exist');
    });

    it('overrides a previously set directory', function (): void {
        $secondDir = createTestDirectory();

        $this->service->setWorkingDirectory($this->testDir);
        $this->service->setWorkingDirectory($secondDir);

        expect($this->service->getWorkingDirectory())->toBe(realpath($secondDir));

        cleanupTestDirectory($secondDir);
    });
});

// ─── getTutiPath() ──────────────────────────────────────────────────────
// Returns the path to the .tuti directory (or a subpath within it).
// Example: getTutiPath('docker-compose.yml') → /project/.tuti/docker-compose.yml

describe('getTutiPath', function (): void {

    it('returns .tuti directory path with no argument', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getTutiPath())
            ->toBe(realpath($this->testDir) . '/.tuti');
    });

    it('returns subpath within .tuti directory', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getTutiPath('docker-compose.yml'))
            ->toBe(realpath($this->testDir) . '/.tuti/docker-compose.yml');
    });

    it('returns nested subpath within .tuti directory', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getTutiPath('docker/Dockerfile'))
            ->toBe(realpath($this->testDir) . '/.tuti/docker/Dockerfile');
    });

    it('strips leading slashes from subpath', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        // Leading slash should be trimmed so we don't get .tuti//config.json
        expect($this->service->getTutiPath('/config.json'))
            ->toBe(realpath($this->testDir) . '/.tuti/config.json');
    });

    it('returns null subpath as just .tuti path', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getTutiPath(null))
            ->toBe($this->service->getTutiPath());
    });
});

// ─── tutiExists() ───────────────────────────────────────────────────────
// Checks if the .tuti directory exists in the working directory.
// Used to detect whether a project has been initialized with tuti-cli.

describe('tutiExists', function (): void {

    it('returns true when .tuti directory exists', function (): void {
        mkdir($this->testDir . '/.tuti');
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->tutiExists())->toBeTrue();
    });

    it('returns false when .tuti directory does not exist', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->tutiExists())->toBeFalse();
    });

    it('returns false when .tuti is a file not a directory', function (): void {
        // Edge case: someone created a file named .tuti (not a directory)
        file_put_contents($this->testDir . '/.tuti', 'not a directory');
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->tutiExists())->toBeFalse();
    });
});

// ─── getPath() ──────────────────────────────────────────────────────────
// Returns a path relative to the working directory.
// Example: getPath('.env') → /project/.env

describe('getPath', function (): void {

    it('returns path relative to working directory', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getPath('.env'))
            ->toBe(realpath($this->testDir) . '/.env');
    });

    it('returns nested relative path', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getPath('storage/logs/laravel.log'))
            ->toBe(realpath($this->testDir) . '/storage/logs/laravel.log');
    });

    it('strips leading slashes from relative path', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getPath('/composer.json'))
            ->toBe(realpath($this->testDir) . '/composer.json');
    });
});

// ─── reset() ────────────────────────────────────────────────────────────
// Clears the cached working directory. Next call to getWorkingDirectory()
// will call getcwd() again. Used between tests or when the directory changes.

describe('reset', function (): void {

    it('clears the cached working directory', function (): void {
        $this->service->setWorkingDirectory($this->testDir);

        expect($this->service->getWorkingDirectory())->toBe(realpath($this->testDir));

        $this->service->reset();

        // After reset, it should fall back to getcwd() — which is the
        // process working directory, not our testDir
        $afterReset = $this->service->getWorkingDirectory();

        // It should be a valid directory (getcwd()), but NOT our test dir
        // (unless the process happens to be in testDir, which is unlikely)
        expect($afterReset)->toBeString()->toBeDirectory();

        // Verify the cache was actually cleared by confirming it re-resolved
        // (in most CI/local environments, getcwd() won't equal our temp dir)
        if (getcwd() !== realpath($this->testDir)) {
            expect($afterReset)->not->toBe(realpath($this->testDir));
        }
    });

    it('allows setting a new directory after reset', function (): void {
        $secondDir = createTestDirectory();

        $this->service->setWorkingDirectory($this->testDir);
        $this->service->reset();
        $this->service->setWorkingDirectory($secondDir);

        expect($this->service->getWorkingDirectory())->toBe(realpath($secondDir));

        cleanupTestDirectory($secondDir);
    });
});
