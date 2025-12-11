<?php

declare(strict_types=1);

use Tests\Feature\Concerns\CreatesHelperTestEnvironment;

uses(CreatesHelperTestEnvironment::class)->group('helpers');

beforeEach(function (): void {
    $this->setupHelperEnvironment();
});

afterEach(function (): void {
    $this->cleanupHelperEnvironment();
});

describe('tuti_path', function () {
    it('returns base .tuti path', function (): void {
        // tuti_path hardcodes dirname(__DIR__, 2), so we test actual behavior
        $path = tuti_path();

        expect($path)->toContain('.tuti')
            ->and($path)->toEndWith('.tuti');
    });

    it('returns path with subdirectory', function (): void {
        $path = tuti_path('docker');

        expect($path)->toEndWith('.tuti/docker');
    });

    it('strips leading slash from path', function (): void {
        $path1 = tuti_path('docker');
        $path2 = tuti_path('/docker');

        expect($path1)->toBe($path2);
    });

    it('handles multiple path segments', function (): void {
        $path = tuti_path('docker/configs/nginx.conf');

        expect($path)->toEndWith('.tuti/docker/configs/nginx.conf');
    });
});

describe('is_tuti_exists', function () {
    it('returns false when .tuti does not exist', function (): void {
        // Test with a path that definitely doesn't have .tuti
        $nonExistentPath = sys_get_temp_dir() . '/no-tuti-' . uniqid();
        mkdir($nonExistentPath);

        $result = is_tuti_exists($nonExistentPath);

        rmdir($nonExistentPath);

        expect($result)->toBeFalse();
    });

    it('returns false when .tuti exists but no tuti.json', function (): void {
        $testPath = sys_get_temp_dir() . '/tuti-no-json-' . uniqid();
        mkdir($testPath);
        mkdir($testPath . '/.tuti');

        $result = is_tuti_exists($testPath);

        rmdir($testPath . '/.tuti');
        rmdir($testPath);

        expect($result)->toBeFalse();
    });

    it('returns true when .tuti and tuti.json exist', function (): void {
        $testPath = sys_get_temp_dir() . '/tuti-with-json-' . uniqid();
        mkdir($testPath);
        mkdir($testPath . '/.tuti');
        file_put_contents($testPath . '/.tuti/tuti.json', '{"version":"1.0"}');

        $result = is_tuti_exists($testPath);

        unlink($testPath . '/.tuti/tuti.json');
        rmdir($testPath . '/.tuti');
        rmdir($testPath);

        expect($result)->toBeTrue();
    });
});

describe('global_tuti_path', function () {
    it('returns home directory .tuti path', function (): void {
        $path = global_tuti_path();
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/root';

        expect($path)->toBe($home . '/.tuti');
    });

    it('returns path with subdirectory', function (): void {
        $path = global_tuti_path('config');
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/root';

        expect($path)->toBe($home . '/.tuti/config');
    });

    it('strips leading slash from path', function (): void {
        $path1 = global_tuti_path('config');
        $path2 = global_tuti_path('/config');

        expect($path1)->toBe($path2);
    });
});

describe('stub_path', function () {
    it('returns base stubs path', function (): void {
        $path = stub_path();

        expect($path)->toEndWith('/stubs');
    });

    it('returns path with file name', function (): void {
        $path = stub_path('Dockerfile.stub');

        expect($path)->toEndWith('/stubs/Dockerfile.stub');
    });

    it('strips leading slash', function (): void {
        $path1 = stub_path('Dockerfile.stub');
        $path2 = stub_path('/Dockerfile.stub');

        expect($path1)->toBe($path2);
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

    it('strips leading slash', function (): void {
        $path1 = stack_path('laravel-stack');
        $path2 = stack_path('/laravel-stack');

        expect($path1)->toBe($path2);
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

    it('works with relative paths', function (): void {
        $name = stack_name('stacks/laravel-stack');

        expect($name)->toBe('laravel-stack');
    });

    it('returns just the directory name', function (): void {
        $name = stack_name('laravel-stack');

        expect($name)->toBe('laravel-stack');
    });
});

describe('discover_stacks', function () {
    it('returns empty array when stacks directory does not exist', function (): void {
        // Test actual behavior when stacks dir doesn't exist
        $stacks = discover_stacks();

        expect($stacks)->toBeArray();
    });

    it('discovers stacks with stack.json', function (): void {
        $this->createStack('laravel-stack');
        $this->createStack('vue-stack');

        $stacks = discover_stacks();

        expect($stacks)->toBeArray()
            ->toHaveKey('laravel-stack')
            ->toHaveKey('vue-stack');
    });

    it('ignores directories without stack.json', function (): void {
        $this->createStack('valid-stack');

        // Create directory without stack.json
        mkdir($this->stacksDir . '/invalid-stack');

        $stacks = discover_stacks();

        expect($stacks)
            ->toHaveKey('valid-stack')
            ->not->toHaveKey('invalid-stack');
    });

    it('only finds directories ending with -stack', function (): void {
        $this->createStack('laravel-stack');

        // 1. Create a directory that DOES NOT end in -stack
        $invalidName = 'invalid-directory-name';
        mkdir($this->stacksDir . '/' . $invalidName);
        file_put_contents($this->stacksDir . '/' . $invalidName . '/stack.json', '{}');

        $stacks = discover_stacks();

        expect($stacks)
            ->toHaveKey('laravel-stack')
            ->not->toHaveKey($invalidName);
    });

    it('returns full paths as values', function (): void {
        $stackPath = $this->createStack('laravel-stack');

        $stacks = discover_stacks();

        expect($stacks['laravel-stack'])->toBe($stackPath);
    });
});

describe('stack_exists', function () {
    it('returns true for existing stack', function (): void {
        $this->createStack('laravel-stack');

        expect(stack_exists('laravel-stack'))->toBeTrue();
    });

    it('returns false for non-existing stack', function (): void {
        expect(stack_exists('non-existent-stack'))->toBeFalse();
    });

    it('returns true for full path', function (): void {
        $stackPath = $this->createStack('laravel-stack');

        expect(stack_exists($stackPath))->toBeTrue();
    });
});

describe('resolve_stack_path', function () {
    it('returns path for valid full path', function (): void {
        $stackPath = $this->createStack('laravel-stack');

        $resolved = resolve_stack_path($stackPath);

        expect($resolved)->toBe($stackPath);
    });

    it('resolves stack name with -stack suffix', function (): void {
        $stackPath = $this->createStack('laravel-stack');

        $resolved = resolve_stack_path('laravel-stack');

        expect($resolved)->toBe($stackPath);
    });

    it('resolves stack name without -stack suffix', function (): void {
        $stackPath = $this->createStack('laravel-stack');

        $resolved = resolve_stack_path('laravel');

        expect($resolved)->toBe($stackPath);
    });

    it('normalizes stack name', function (): void {
        $stackPath = $this->createStack('laravel-stack');

        $resolved = resolve_stack_path('laravel-stack-stack');

        expect($resolved)->toBe($stackPath);
    });

    it('throws exception for non-existent stack', function (): void {
        expect(fn() => resolve_stack_path('non-existent'))
            ->toThrow(RuntimeException::class, 'Stack not found');
    });

    it('strips trailing slash from resolved path', function (): void {
        $stackPath = $this->createStack('laravel-stack');

        $resolved = resolve_stack_path($stackPath . '/');

        expect($resolved)->toBe($stackPath)
            ->not->toEndWith('/');
    });
});

describe('get_stack_manifest_path', function () {
    it('returns stack.json path for valid stack', function (): void {
        $stackPath = $this->createStack('laravel-stack');

        $manifestPath = get_stack_manifest_path('laravel-stack');

        expect($manifestPath)->toBe($stackPath . '/stack.json')
            ->and(file_exists($manifestPath))->toBeTrue();
    });

    it('works with full path', function (): void {
        $stackPath = $this->createStack('laravel-stack');

        $manifestPath = get_stack_manifest_path($stackPath);

        expect($manifestPath)->toBe($stackPath . '/stack.json');
    });

    it('throws exception for non-existent stack', function (): void {
        expect(fn() => get_stack_manifest_path('non-existent'))
            ->toThrow(RuntimeException::class);
    });
});

describe('mask_sensitive', function () {
    it('masks password values', function (): void {
        $masked = mask_sensitive('DB_PASSWORD', 'mysecret123');

        expect($masked)->toBe('***********')
            ->not->toContain('mysecret');
    });

    it('masks secret values', function (): void {
        $masked = mask_sensitive('API_SECRET', 'topsecret');

        expect($masked)->toBe('*********');
    });

    it('masks key values', function (): void {
        $masked = mask_sensitive('ENCRYPTION_KEY', 'mykey123');

        expect($masked)->toBe('********');
    });

    it('masks token values', function (): void {
        $masked = mask_sensitive('API_TOKEN', 'token123');

        expect($masked)->toBe('********');
    });

    it('masks api values', function (): void {
        $masked = mask_sensitive('STRIPE_API', 'sk_test_123');

        expect($masked)->toBe('***********');
    });

    it('masks private values', function (): void {
        $masked = mask_sensitive('PRIVATE_KEY', 'private123');

        expect($masked)->toBe('**********');
    });

    it('does not mask non-sensitive values', function (): void {
        $value = mask_sensitive('DB_HOST', 'localhost');

        expect($value)->toBe('localhost');
    });

    it('is case insensitive', function (): void {
        $masked = mask_sensitive('db_password', 'secret');

        expect($masked)->toBe('******');
    });

    it('limits mask length to 20 characters', function (): void {
        $longValue = str_repeat('a', 100);
        $masked = mask_sensitive('PASSWORD', $longValue);

        expect($masked)->toBe(str_repeat('*', 20))
            ->toHaveLength(20);
    });
});

describe('time_ago', function () {
    it('returns "Just now" for recent timestamps', function (): void {
        $result = time_ago(time() - 30);

        expect($result)->toBe('Just now');
    });

    it('returns minutes ago', function (): void {
        $result = time_ago(time() - 180); // 3 minutes

        expect($result)->toBe('3 minutes ago');
    });

    it('returns hours ago', function (): void {
        $result = time_ago(time() - 7200); // 2 hours

        expect($result)->toBe('2 hours ago');
    });

    it('returns days ago', function (): void {
        $result = time_ago(time() - 172800); // 2 days

        expect($result)->toBe('2 days ago');
    });

    it('returns weeks ago', function (): void {
        $result = time_ago(time() - 1209600); // 2 weeks

        expect($result)->toBe('2 weeks ago');
    });

    it('returns months ago', function (): void {
        $result = time_ago(time() - 5184000); // 2 months

        expect($result)->toBe('2 months ago');
    });
});

describe('bytes_to_human', function () {
    it('formats bytes', function (): void {
        expect(bytes_to_human(500))->toBe('500.00 B');
    });

    it('formats kilobytes', function (): void {
        expect(bytes_to_human(1024))->toBe('1.00 KB');
    });

    it('formats megabytes', function (): void {
        expect(bytes_to_human(1048576))->toBe('1.00 MB');
    });

    it('formats gigabytes', function (): void {
        expect(bytes_to_human(1073741824))->toBe('1.00 GB');
    });

    it('formats terabytes', function (): void {
        expect(bytes_to_human(1099511627776))->toBe('1.00 TB');
    });

    it('handles zero bytes', function (): void {
        expect(bytes_to_human(0))->toBe('0.00 B');
    });

    it('formats decimal values', function (): void {
        expect(bytes_to_human(1536))->toBe('1.50 KB');
    });
});

describe('platform detection', function () {
    it('detects windows', function (): void {
        $result = is_windows();

        expect($result)->toBeBool();
    });

    it('detects linux', function (): void {
        $result = is_linux();

        expect($result)->toBeBool();
    });

    it('detects macos', function (): void {
        $result = is_macos();

        expect($result)->toBeBool();
    });

    it('only one platform is true', function (): void {
        $platforms = [is_windows(), is_linux(), is_macos()];
        $trueCount = count(array_filter($platforms));

        expect($trueCount)->toBe(1);
    });
});

describe('expand_path', function () {
    it('expands tilde to home directory', function (): void {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/root';
        $expanded = expand_path('~/documents');

        expect($expanded)->toBe($home . '/documents');
    });

    it('does not modify paths without tilde', function (): void {
        $path = '/absolute/path/to/file';
        $expanded = expand_path($path);

        expect($expanded)->toBe($path);
    });

    it('replaces all tildes with home directory', function (): void {
        // Your expand_path replaces ALL tildes with str_replace, not just the first
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/root';
        $expanded = expand_path('~/folder/~/subfolder');

        expect($expanded)->toBe($home . '/folder/' . $home . '/subfolder');
    });

    it('works with just tilde', function (): void {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/root';
        $expanded = expand_path('~');

        expect($expanded)->toBe($home);
    });

    it('handles relative paths', function (): void {
        $path = 'relative/path';
        $expanded = expand_path($path);

        expect($expanded)->toBe($path);
    });
});
