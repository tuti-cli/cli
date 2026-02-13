<?php

declare(strict_types=1);

/**
 * GlobalSettingsService Unit Tests
 *
 * Tests the global user preferences store (~/.tuti/settings.json).
 * This service uses dot-notation (like 'editor.theme') to read/write
 * nested JSON settings, powered by Laravel's Arr::get/set helpers.
 *
 * Testing trick used here: we override the HOME environment variable
 * to point at a temp directory, so the service writes to a safe location
 * instead of the real ~/.tuti/. This is cleaner than mocking because
 * we test real file I/O.
 *
 * @see GlobalSettingsService
 */

use App\Services\Global\GlobalSettingsService;
use App\Services\Storage\JsonFileService;

// ─── Setup & Cleanup ────────────────────────────────────────────────────
// We redirect HOME to a temp directory so settings.json is created there.
// After each test, we restore the original HOME environment variable.

beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    $this->originalHome = getenv('HOME');

    // Point HOME to our temp dir → service will use $testDir/.tuti/settings.json
    putenv("HOME={$this->testDir}");

    // Create the .tuti directory (the service expects it to exist for writes)
    mkdir($this->testDir . '/.tuti', 0755, true);

    $this->service = new GlobalSettingsService(new JsonFileService);
});

afterEach(function (): void {
    // Restore original HOME so we don't affect other tests
    putenv("HOME={$this->originalHome}");
    cleanupTestDirectory($this->testDir);
});

// ─── get() ──────────────────────────────────────────────────────────────
// Reads a setting by dot-notation key. Returns default if missing.
// Pass null key to get ALL settings as an array.

describe('get', function (): void {

    it('returns default value when settings file does not exist', function (): void {
        // No settings.json created yet → should return the default
        expect($this->service->get('some.key', 'fallback'))->toBe('fallback');
    });

    it('returns null by default when key is missing', function (): void {
        expect($this->service->get('nonexistent.key'))->toBeNull();
    });

    it('returns all settings when key is null', function (): void {
        // No file exists → empty array
        expect($this->service->get())->toBe([]);
    });

    it('reads a previously saved setting', function (): void {
        // Write a settings file manually to simulate existing settings
        $settingsPath = $this->testDir . '/.tuti/settings.json';
        file_put_contents($settingsPath, json_encode([
            'editor' => ['theme' => 'ocean'],
        ]));

        expect($this->service->get('editor.theme'))->toBe('ocean');
    });

    it('reads nested values with dot notation', function (): void {
        $settingsPath = $this->testDir . '/.tuti/settings.json';
        file_put_contents($settingsPath, json_encode([
            'docker' => [
                'compose' => [
                    'version' => '3.8',
                    'timeout' => 300,
                ],
            ],
        ]));

        expect($this->service->get('docker.compose.version'))->toBe('3.8');
        expect($this->service->get('docker.compose.timeout'))->toBe(300);
    });

    it('returns entire nested structure when accessing parent key', function (): void {
        $settingsPath = $this->testDir . '/.tuti/settings.json';
        file_put_contents($settingsPath, json_encode([
            'docker' => [
                'timeout' => 300,
                'build' => true,
            ],
        ]));

        $docker = $this->service->get('docker');

        expect($docker)
            ->toBeArray()
            ->toHaveKey('timeout')
            ->toHaveKey('build');
    });

    it('returns all settings as array when key is null', function (): void {
        $settingsPath = $this->testDir . '/.tuti/settings.json';
        file_put_contents($settingsPath, json_encode([
            'theme' => 'dark',
            'verbose' => true,
        ]));

        $all = $this->service->get();

        expect($all)
            ->toBeArray()
            ->toHaveKey('theme')
            ->toHaveKey('verbose');
    });
});

// ─── set() ──────────────────────────────────────────────────────────────
// Writes a setting to settings.json. Supports dot-notation for nested keys.
// Creates the file if it doesn't exist, merges with existing data if it does.

describe('set', function (): void {

    it('creates settings file when it does not exist', function (): void {
        $this->service->set('theme', 'dark');

        $settingsPath = $this->testDir . '/.tuti/settings.json';
        expect($settingsPath)->toBeFile();

        $saved = json_decode(file_get_contents($settingsPath), true);
        expect($saved['theme'])->toBe('dark');
    });

    it('writes a simple key-value setting', function (): void {
        $this->service->set('verbose', true);

        expect($this->service->get('verbose'))->toBeTrue();
    });

    it('writes nested values using dot notation', function (): void {
        $this->service->set('docker.compose.timeout', 600);

        // The JSON file should have nested structure, not a flat "docker.compose.timeout" key
        $settingsPath = $this->testDir . '/.tuti/settings.json';
        $saved = json_decode(file_get_contents($settingsPath), true);

        expect($saved['docker']['compose']['timeout'])->toBe(600);
    });

    it('preserves existing settings when adding new ones', function (): void {
        $this->service->set('theme', 'dark');
        $this->service->set('verbose', true);

        // Both should exist
        expect($this->service->get('theme'))->toBe('dark');
        expect($this->service->get('verbose'))->toBeTrue();
    });

    it('overwrites existing setting with new value', function (): void {
        $this->service->set('theme', 'dark');
        $this->service->set('theme', 'light');

        expect($this->service->get('theme'))->toBe('light');
    });

    it('handles various value types', function (): void {
        $this->service->set('string', 'hello');
        $this->service->set('int', 42);
        $this->service->set('float', 3.14);
        $this->service->set('bool', false);
        $this->service->set('null_val', null);
        $this->service->set('array', ['a', 'b', 'c']);

        expect($this->service->get('string'))->toBe('hello');
        expect($this->service->get('int'))->toBe(42);
        expect($this->service->get('float'))->toBe(3.14);
        expect($this->service->get('bool'))->toBeFalse();
        expect($this->service->get('null_val'))->toBeNull();
        expect($this->service->get('array'))->toBe(['a', 'b', 'c']);
    });

    it('merges deeply nested settings without losing siblings', function (): void {
        // Set two sibling keys under the same parent
        $this->service->set('docker.compose.timeout', 300);
        $this->service->set('docker.compose.version', '3.8');

        // Both should exist - set() should merge, not overwrite the parent
        expect($this->service->get('docker.compose.timeout'))->toBe(300);
        expect($this->service->get('docker.compose.version'))->toBe('3.8');
    });
});

// ─── Round-trip: get + set together ─────────────────────────────────────
// Verifies that what you set() is exactly what you get() back.

describe('round-trip', function (): void {

    it('returns exactly what was set', function (): void {
        $this->service->set('project.default_stack', 'laravel');

        expect($this->service->get('project.default_stack'))->toBe('laravel');
    });

    it('persists across new service instances', function (): void {
        // Write with one instance
        $this->service->set('persisted', 'yes');

        // Read with a fresh instance (simulates CLI relaunch)
        $freshService = new GlobalSettingsService(new JsonFileService);

        expect($freshService->get('persisted'))->toBe('yes');
    });
});
