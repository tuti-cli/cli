<?php

declare(strict_types=1);

namespace App\Support;

final class SystemEnvironment
{
    public static function detect(): string
    {
        // Detect WSL (Windows Subsystem for Linux)
        if (self::isWSL()) {
            return 'wsl';
        }

        // Detect Windows (native PowerShell / CMD)
        if (self::isWindows()) {
            return 'windows';
        }

        // Detect macOS
        if (self::isMacOS()) {
            return 'macos';
        }

        // Detect Linux
        if (self::isLinux()) {
            return 'linux';
        }

        return 'unknown';
    }

    public static function isWindows(): bool
    {
        return mb_strtoupper(mb_substr(PHP_OS, 0, 3)) === 'WIN';
    }

    public static function isMacOS(): bool
    {
        return PHP_OS === 'Darwin';
    }

    public static function isLinux(): bool
    {
        return PHP_OS === 'Linux' && ! self::isWSL();
    }

    public static function isWSL(): bool
    {
        // WSL exposes this file
        if (is_file('/proc/sys/fs/binfmt_misc/WSLInterop')) {
            return true;
        }

        // Fallback check
        if (is_readable('/proc/version')) {
            $version = file_get_contents('/proc/version');
            if (mb_stripos($version, 'microsoft') !== false || mb_stripos($version, 'wsl') !== false) {
                return true;
            }
        }

        return false;
    }

    /** Get correct docker binary location depending on OS */
    public static function dockerBinary(): string
    {
        // WSL uses the Linux docker shim when integration is enabled
        if (self::isWSL()) {
            return '/usr/bin/docker';
        }

        // Native Windows (CMD/PowerShell)
        if (self::isWindows()) {

            $paths = [
                'C:\Program Files\Docker\Docker\resources\bin\docker.exe',
                'C:\Program Files\Docker\resources\bin\docker.exe',
                'C:\Program Files\Docker\docker.exe',
            ];

            foreach ($paths as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            // fallback to PATH
            return 'docker.exe';
        }

        // macOS or Linux → normal
        return 'docker';
    }
}
