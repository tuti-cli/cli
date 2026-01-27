<?php

declare(strict_types=1);

use Illuminate\Support\Str;

if (! function_exists('tuti_path')) {
    /**
     * Get the .tuti directory path for current project
     */
    function tuti_path(?string $path = null, ?string $projectRoot = null): string
    {
        if ($projectRoot === null) {
            $projectRoot = getcwd();

            if ($projectRoot === false) {
                throw new RuntimeException('Unable to determine current working directory.');
            }
        }

        $base = mb_rtrim($projectRoot, '/') . '/.tuti';

        return $path ? $base . '/' . mb_ltrim($path, '/') : $base;
    }
}

if (! function_exists('tuti_exists')) {
    /**
     * Check if .tuti directory exists for current project
     */
    function tuti_exists(?string $projectRoot = null): bool
    {
        return is_dir(tuti_path(null, $projectRoot));
    }
}

if (! function_exists('global_tuti_path')) {
    /**
     * Get the global ~/.tuti directory path
     */
    function global_tuti_path(?string $path = null): string
    {
        $home = getenv('HOME');

        if (empty($home)) {
            $home = $_SERVER['HOME'] ?? null;
        }

        if (empty($home)) {
            $home = $_SERVER['USERPROFILE'] ?? null;
        }

        // Try posix_getpwuid on Unix systems
        if (empty($home) && function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $userInfo = posix_getpwuid(posix_getuid());
            $home = $userInfo['dir'] ?? null;
        }

        // Fallback: try to construct from username
        if (empty($home)) {
            $user = getenv('USER') ?: getenv('USERNAME');
            if (! empty($user)) {
                if (PHP_OS_FAMILY === 'Windows') {
                    $home = "C:\\Users\\{$user}";
                } else {
                    $home = "/home/{$user}";
                }
            }
        }

        if (empty($home)) {
            throw new RuntimeException(
                'Unable to determine home directory. ' .
                'Please set the HOME environment variable or run: tuti install'
            );
        }

        $base = mb_rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.tuti';

        return $path ? $base . DIRECTORY_SEPARATOR . mb_ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR) : $base;
    }
}

if (! function_exists('cli_base_path')) {
    /**
     * Get the CLI installation path (where tuti binary is installed).
     */
    function cli_base_path(?string $path = null): string
    {
        $base = dirname(__DIR__, 2);

        return $path ? $base . '/' . mb_ltrim($path, '/') : $base;
    }
}

if (! function_exists('stub_path')) {
    /**
     * Get the stubs directory path
     */
    function stub_path(?string $path = null): string
    {
        $base = cli_base_path('stubs');

        return $path ? $base . '/' . mb_ltrim($path, '/') : $base;
    }
}

if (! function_exists('stack_path')) {
    /**
     * Get the stacks directory path
     */
    function stack_path(?string $path = null): string
    {
        $base = cli_base_path('stacks');

        return $path ? $base . '/' . mb_ltrim($path, '/') : $base;
    }
}

if (! function_exists('stack_name')) {
    /**
     * Get the display name from a stack path
     *
     * Converts full path to just the directory name
     * Example: /path/to/stacks/laravel-stack → laravel-stack
     */
    function stack_name(string $path): string
    {
        return basename(mb_rtrim($path, '/'));
    }
}

if (! function_exists('discover_stacks')) {
    /**
     * Discover all available stacks in the stacks directory
     * Example: discover_stacks() → ['laravel-stack' => '/path/to/stacks/laravel-stack', ...]
     *
     * @return array<string, string> Array of [stack-name => full-path]
     */
    function discover_stacks(): array
    {
        $stacksDir = stack_path();

        if (! is_dir($stacksDir)) {
            return [];
        }

        $stacks = [];
        $directories = glob($stacksDir . '/*-stack', GLOB_ONLYDIR);

        if ($directories === false) {
            return [];
        }

        foreach ($directories as $dir) {
            $name = basename($dir);
            if (! str_ends_with($name, '-stack')) {
                continue;
            }

            if (file_exists($dir . '/stack.json')) {
                $name = stack_name($dir);
                $stacks[$name] = $dir;
            }
        }

        return $stacks;
    }
}

if (! function_exists('stack_exists')) {
    /**
     * Check if a stack exists
     */
    function stack_exists(string $stack): bool
    {
        try {
            resolve_stack_path($stack);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}

if (! function_exists('resolve_stack_path')) {
    /**
     * Resolve stack name to full path
     *
     * Accepts:
     * - Full path:  /absolute/path/to/laravel-stack
     * - Stack name with suffix: laravel-stack
     * - Stack name without suffix: laravel (auto-adds -stack)
     * Example: resolve_stack_path('laravel') → /path/to/stacks/laravel-stack
     */
    function resolve_stack_path(string $stack): string
    {
        // If it's already a valid directory path with stack. json, return it
        if (is_dir($stack) && file_exists($stack . '/stack.json')) {
            return mb_rtrim($stack, '/');
        }

        // Try to find in stacks directory
        $stacksDir = stack_path();

        $possibleNames = [
            $stack,                                           // As provided (e.g., "laravel-stack")
            "{$stack}-stack",                                 // With -stack suffix (e.g., "laravel" → "laravel-stack")
            str_replace('-stack', '', $stack) . '-stack',     // Normalize
        ];

        foreach ($possibleNames as $name) {
            $fullPath = "{$stacksDir}/{$name}";

            if (is_dir($fullPath) && file_exists($fullPath . '/stack.json')) {
                return $fullPath;
            }
        }

        // Not found
        throw new RuntimeException(
            "Stack not found: {$stack}\n" .
            "Searched in: {$stacksDir}\n" .
            'Tried: ' . implode(', ', $possibleNames)
        );
    }
}

if (! function_exists('get_stack_manifest_path')) {
    /**
     * Get path to stack. json for a given stack
     */
    function get_stack_manifest_path(string $stack): string
    {
        $stackPath = resolve_stack_path($stack);

        return $stackPath . '/stack.json';
    }
}

if (! function_exists('mask_sensitive')) {
    /**
     * Mask sensitive values
     * Example: mask_sensitive('DB_PASSWORD', 'mysecretpassword') → '********************'
     */
    function mask_sensitive(string $key, string $value): string
    {
        $sensitive = ['PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'API', 'PRIVATE'];

        foreach ($sensitive as $word) {
            if (Str::contains(mb_strtoupper($key), $word)) {
                return str_repeat('*', min(mb_strlen($value), 20));
            }
        }

        return $value;
    }
}

if (! function_exists('time_ago')) {
    /**
     * Convert timestamp to human-readable format
     * Example: time_ago(time() - 3600) → '1 hours ago'
     */
    function time_ago(int $timestamp): string
    {
        $diff = time() - $timestamp;

        return match (true) {
            $diff < 60 => 'Just now',
            $diff < 3600 => floor($diff / 60) . ' minutes ago',
            $diff < 86400 => floor($diff / 3600) . ' hours ago',
            $diff < 604800 => floor($diff / 86400) . ' days ago',
            $diff < 2592000 => floor($diff / 604800) . ' weeks ago',
            default => floor($diff / 2592000) . ' months ago',
        };
    }
}

if (! function_exists('bytes_to_human')) {
    /**
     * Convert bytes to human-readable format
     * Example: bytes_to_human(1048576) → '1.00 MB'
     */
    function bytes_to_human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}

if (! function_exists('is_windows')) {
    /**
     * Check if running on Windows
     */
    function is_windows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if (! function_exists('is_linux')) {
    /**
     * Check if running on Linux
     */
    function is_linux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }
}

if (! function_exists('is_macos')) {
    /**
     * Check if running on macOS
     */
    function is_macos(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }
}

if (! function_exists('expand_path')) {
    /**
     * Expand ~ in file paths
     * Example: expand_path('~/folder') → '/home/user/folder'
     */
    function expand_path(string $path): string
    {
        if (str_starts_with($path, '~')) {
            $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/root';

            return str_replace('~', $home, $path);
        }

        return $path;
    }
}
