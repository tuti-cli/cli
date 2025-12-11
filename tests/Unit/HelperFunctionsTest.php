<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/tuti-helper-test-' . uniqid();
    mkdir($this->testDir);
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        array_map('unlink', glob($this->testDir . '/*'));
        @rmdir($this->testDir);
    }
});

describe('tuti_path', function () {
    it('returns base .tuti path', function (): void {
        $path = tuti_path(null, $this->testDir);

        expect($path)->toBe($this->testDir . '/.tuti');
    });

    it('returns path with subdirectory', function (): void {
        $path = tuti_path('docker', $this->testDir);

        expect($path)->toBe($this->testDir . '/.tuti/docker');
    });

    it('strips leading slash from path', function (): void {
        $path = tuti_path('/docker', $this->testDir);

        expect($path)->toBe($this->testDir . '/.tuti/docker');
    });
});

describe('tuti_exists', function () {
    it('returns false when .tuti does not exist', function (): void {
        expect(tuti_exists($this->testDir))->toBeFalse();
    });

    it('returns false when .tuti exists but no tuti.json', function (): void {
        mkdir($this->testDir . '/.tuti');

        expect(tuti_exists($this->testDir))->toBeFalse();
    });

    it('returns true when .tuti and tuti.json exist', function (): void {
        mkdir($this->testDir . '/.tuti');
        file_put_contents($this->testDir . '/.tuti/tuti.json', '{}');

        expect(is_tuti_exists($this->testDir))->toBeTrue();
    });
});

describe('stack_path', function () {
    it('returns base stacks path', function (): void {
        $path = stack_path();

        expect($path)->toEndWith('/stacks');
    });

    it('returns path with stack name', function (): void {
        $path = stack_path('laravel-stack');

        expect($path)->toEndWith('/stacks/laravel-stack');
    });
});

describe('stack_name', function () {
    it('extracts name from full path', function (): void {
        $name = stack_name('/path/to/stacks/laravel-stack');

        expect($name)->toBe('laravel-stack');
    });

    it('handles paths with trailing slash', function (): void {
        $name = stack_name('/path/to/stacks/laravel-stack/');

        expect($name)->toBe('laravel-stack');
    });
});
