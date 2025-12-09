<?php

declare(strict_types=1);

use Illuminate\Support\Str;

if (! function_exists('project_path')) {
    /**
     * Get the project path
     */
    function project_path(?string $path = null): string
    {
        $base = getcwd();

        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (! function_exists('tuti_path')) {
    /**
     * Get the .tuti directory path
     */
    function tuti_path(?string $path = null): string
    {
        $base = project_path('.tuti');

        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (! function_exists('stub_path')) {
    /**
     * Get the stubs directory path
     */
    function stub_path(?string $path = null): string
    {
        $base = project_path('stubs');

        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (! function_exists('stack_path')) {
    /**
     * Get the stubs directory path
     */
    function stack_path(?string $path = null): string
    {
        $base = project_path('stacks');

        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (! function_exists('global_tuti_path')) {
    /**
     * Get the global ~/.tuti directory path
     */
    function global_tuti_path(?string $path = null): string
    {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '/root';
        $base = $home . '/.tuti';

        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (! function_exists('is_tuti_initialized')) {
    /**
     * Check if current project is initialized with Tuti
     */
    function is_tuti_initialized(): bool
    {
        return is_dir(tuti_path());
    }
}

if (! function_exists('get_project_name')) {
    /**
     * Get current project name
     */
    function get_project_name(): string
    {
        if (is_tuti_initialized()) {
            $config = yaml_parse_file(tuti_path('.tuti.yml'));

            return $config['project']['name'] ?? basename(project_path());
        }

        return basename(project_path());
    }
}

if (! function_exists('mask_sensitive')) {
    /**
     * Mask sensitive values
     */
    function mask_sensitive(string $key, string $value): string
    {
        $sensitive = ['PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'API', 'PRIVATE'];

        foreach ($sensitive as $word) {
            if (Str::contains(strtoupper($key), $word)) {
                return str_repeat('*', min(strlen($value), 20));
            }
        }

        return $value;
    }
}

if (! function_exists('time_ago')) {
    /**
     * Convert timestamp to human-readable format
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
