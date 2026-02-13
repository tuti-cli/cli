<?php

declare(strict_types=1);

/**
 * ProjectDirectoryService Unit Tests
 *
 * Tests the .tuti directory builder and inspector. This service creates
 * the project's .tuti/ directory structure, validates it, detects whether
 * you're in a real project, and can clean it up.
 *
 * It wraps WorkingDirectoryService with project-specific logic. We use
 * the real WorkingDirectoryService (not a mock) pointed at a temp dir.
 *
 * @see ProjectDirectoryService
 */

use App\Services\Context\WorkingDirectoryService;
use App\Services\Project\ProjectDirectoryService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->testDir = createTestDirectory();

    $this->workingDirService = new WorkingDirectoryService;
    $this->workingDirService->setWorkingDirectory($this->testDir);

    $this->service = new ProjectDirectoryService($this->workingDirService);
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

// ─── getProjectRoot() / getTutiPath() ───────────────────────────────────
// Delegation to WorkingDirectoryService — basic path resolution.

describe('path resolution', function (): void {

    it('returns the project root directory', function (): void {
        expect($this->service->getProjectRoot())
            ->toBe(realpath($this->testDir));
    });

    it('returns the .tuti directory path', function (): void {
        expect($this->service->getTutiPath())
            ->toBe(realpath($this->testDir) . '/.tuti');
    });

    it('returns subpath within .tuti', function (): void {
        expect($this->service->getTutiPath('config.json'))
            ->toBe(realpath($this->testDir) . '/.tuti/config.json');
    });
});

// ─── setInitializationRoot() ────────────────────────────────────────────
// Allows creating .tuti in a different directory than the current one.
// Used when `tuti stack:laravel my-app` creates a new project folder.

describe('setInitializationRoot', function (): void {

    it('changes the project root to a different directory', function (): void {
        $otherDir = createTestDirectory();

        $this->service->setInitializationRoot($otherDir);

        expect($this->service->getProjectRoot())->toBe(realpath($otherDir));

        cleanupTestDirectory($otherDir);
    });

    it('throws RuntimeException for nonexistent directory', function (): void {
        expect(fn () => $this->service->setInitializationRoot('/fake/path'))
            ->toThrow(RuntimeException::class);
    });
});

// ─── exists() ───────────────────────────────────────────────────────────
// Checks if .tuti directory exists in the project root.

describe('exists', function (): void {

    it('returns false when .tuti does not exist', function (): void {
        expect($this->service->exists())->toBeFalse();
    });

    it('returns true when .tuti directory exists', function (): void {
        mkdir($this->testDir . '/.tuti');

        expect($this->service->exists())->toBeTrue();
    });
});

// ─── create() ───────────────────────────────────────────────────────────
// Creates the .tuti directory. Throws if it already exists (prevents
// accidental re-initialization that could overwrite config).

describe('create', function (): void {

    it('creates the .tuti directory', function (): void {
        $this->service->create();

        expect($this->testDir . '/.tuti')->toBeDirectory();
    });

    it('creates with correct permissions (0755)', function (): void {
        $this->service->create();

        $perms = fileperms($this->testDir . '/.tuti') & 0777;
        expect($perms)->toBe(0755);
    });

    it('throws RuntimeException if .tuti already exists', function (): void {
        mkdir($this->testDir . '/.tuti');

        expect(fn () => $this->service->create())
            ->toThrow(RuntimeException::class, '.tuti directory already exists');
    });
});

// ─── createSubDirectories() ─────────────────────────────────────────────
// Creates subdirectories inside .tuti. Default: docker/, environments/, scripts/
// These hold the Dockerfile, .env templates, and entrypoint scripts.

describe('createSubDirectories', function (): void {

    it('creates default subdirectories (docker, environments, scripts)', function (): void {
        // First create .tuti itself
        $this->service->create();
        $this->service->createSubDirectories();

        $tutiPath = $this->testDir . '/.tuti';

        expect($tutiPath . '/docker')->toBeDirectory();
        expect($tutiPath . '/environments')->toBeDirectory();
        expect($tutiPath . '/scripts')->toBeDirectory();
    });

    it('creates custom subdirectories when specified', function (): void {
        $this->service->create();
        $this->service->createSubDirectories(['backups', 'logs']);

        $tutiPath = $this->testDir . '/.tuti';

        expect($tutiPath . '/backups')->toBeDirectory();
        expect($tutiPath . '/logs')->toBeDirectory();
    });

    it('does not fail if subdirectory already exists', function (): void {
        $this->service->create();
        mkdir($this->testDir . '/.tuti/docker');

        // Should not throw — just skips existing directories
        $this->service->createSubDirectories(['docker', 'scripts']);

        expect($this->testDir . '/.tuti/docker')->toBeDirectory();
        expect($this->testDir . '/.tuti/scripts')->toBeDirectory();
    });

    it('handles empty directories array', function (): void {
        $this->service->create();

        // Should not throw or create anything
        $this->service->createSubDirectories([]);

        // Only .tuti itself exists, no subdirs
        $contents = scandir($this->testDir . '/.tuti');
        $entries = array_diff($contents, ['.', '..']);

        expect($entries)->toBeEmpty();
    });
});

// ─── validate() ─────────────────────────────────────────────────────────
// Checks that .tuti exists AND has config.json inside it.
// This tells us the project was properly initialized, not just mkdir'd.

describe('validate', function (): void {

    it('returns false when .tuti does not exist', function (): void {
        expect($this->service->validate())->toBeFalse();
    });

    it('returns false when .tuti exists but config.json is missing', function (): void {
        mkdir($this->testDir . '/.tuti');

        expect($this->service->validate())->toBeFalse();
    });

    it('returns true when .tuti exists with config.json', function (): void {
        mkdir($this->testDir . '/.tuti');
        file_put_contents($this->testDir . '/.tuti/config.json', '{}');

        expect($this->service->validate())->toBeTrue();
    });

    it('returns true regardless of other files present', function (): void {
        // config.json is the only required file — extra files don't matter
        mkdir($this->testDir . '/.tuti');
        file_put_contents($this->testDir . '/.tuti/config.json', '{}');
        file_put_contents($this->testDir . '/.tuti/extra.txt', 'ignored');

        expect($this->service->validate())->toBeTrue();
    });
});

// ─── clean() ────────────────────────────────────────────────────────────
// Deletes the entire .tuti directory. Used for re-initialization or cleanup.

describe('clean', function (): void {

    it('removes the .tuti directory and its contents', function (): void {
        // Create a full structure
        $this->service->create();
        $this->service->createSubDirectories();
        file_put_contents($this->testDir . '/.tuti/config.json', '{}');

        $this->service->clean();

        expect($this->testDir . '/.tuti')->not->toBeDirectory();
        expect($this->service->exists())->toBeFalse();
    });

    it('does nothing when .tuti does not exist', function (): void {
        // Should not throw — just silently returns
        $this->service->clean();

        expect($this->service->exists())->toBeFalse();
    });

    it('removes nested files and directories', function (): void {
        $this->service->create();
        $this->service->createSubDirectories();

        // Create some nested content
        file_put_contents($this->testDir . '/.tuti/docker/Dockerfile', 'FROM php:8.4');
        file_put_contents($this->testDir . '/.tuti/scripts/entrypoint.sh', '#!/bin/bash');

        $this->service->clean();

        expect($this->testDir . '/.tuti')->not->toBeDirectory();
    });
});

// ─── validateProjectDirectory() ─────────────────────────────────────────
// Checks that the current directory looks like a real project by looking
// for common project indicators: composer.json, package.json, artisan, .git
// If none found, it throws — this prevents running tuti in random dirs.

describe('validateProjectDirectory', function (): void {

    it('passes when composer.json exists (PHP project)', function (): void {
        file_put_contents($this->testDir . '/composer.json', '{}');

        // Should not throw
        $this->service->validateProjectDirectory();

        // If we get here, validation passed
        expect(true)->toBeTrue();
    });

    it('passes when package.json exists (Node project)', function (): void {
        file_put_contents($this->testDir . '/package.json', '{}');

        $this->service->validateProjectDirectory();

        expect(true)->toBeTrue();
    });

    it('passes when artisan file exists (Laravel project)', function (): void {
        file_put_contents($this->testDir . '/artisan', '#!/usr/bin/env php');

        $this->service->validateProjectDirectory();

        expect(true)->toBeTrue();
    });

    it('passes when .git directory exists', function (): void {
        mkdir($this->testDir . '/.git');

        $this->service->validateProjectDirectory();

        expect(true)->toBeTrue();
    });

    it('throws RuntimeException in empty directory with no project indicators', function (): void {
        expect(fn () => $this->service->validateProjectDirectory())
            ->toThrow(RuntimeException::class, "doesn't appear to be a project directory");
    });

    it('checks only the project root, not subdirectories', function (): void {
        // composer.json exists in a subfolder but NOT in root
        mkdir($this->testDir . '/subfolder');
        file_put_contents($this->testDir . '/subfolder/composer.json', '{}');

        // Should still throw because root has no indicators
        expect(fn () => $this->service->validateProjectDirectory())
            ->toThrow(RuntimeException::class);
    });
});
