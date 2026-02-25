<?php

declare(strict_types=1);

namespace App\Services\Support;

use Illuminate\Support\Facades\Process;

/**
 * Service for managing /etc/hosts file entries.
 *
 * Provides methods to check, add, and verify domain entries
 * in the system hosts file. Requires sudo privileges for modifications.
 */
final readonly class HostsFileService
{
    private const string HOSTS_PATH = '/etc/hosts';

    /**
     * Check if the hosts file exists and can potentially be modified.
     *
     * This checks for file existence only. Actual modification requires
     * sudo privileges which are checked at execution time.
     */
    public function canModifyHosts(): bool
    {
        return file_exists(self::HOSTS_PATH) && is_readable(self::HOSTS_PATH);
    }

    /**
     * Check if a domain entry already exists in the hosts file.
     */
    public function entryExists(string $domain): bool
    {
        if (! file_exists(self::HOSTS_PATH)) {
            return false;
        }

        $content = file_get_contents(self::HOSTS_PATH);

        if ($content === false) {
            return false;
        }

        // Look for the domain with common IP prefixes
        $patterns = [
            "/^127\.0\.0\.1\s+.*\b" . preg_quote($domain, '/') . "\b/im",
            "/^::1\s+.*\b" . preg_quote($domain, '/') . "\b/im",
        ];

        return array_any($patterns, fn ($pattern): int|false => preg_match($pattern, $content));
    }

    /**
     * Add a domain entry to the hosts file.
     *
     * Requires sudo privileges. The entry is added as:
     * 127.0.0.1 {domain}
     *
     * @return bool True if entry was added or already exists, false on failure
     */
    public function addEntry(string $domain): bool
    {
        // Check if entry already exists
        if ($this->entryExists($domain)) {
            return true;
        }

        // Check if hosts file exists
        if (! file_exists(self::HOSTS_PATH)) {
            return false;
        }

        // Build the entry
        $entry = "127.0.0.1 {$domain}";

        // Try to add entry using sudo
        // Using sh -c to properly handle the redirection with sudo
        $result = Process::run([
            'sudo',
            'sh',
            '-c',
            "echo '{$entry}' >> " . self::HOSTS_PATH,
        ]);

        return $result->successful();
    }

    /**
     * Get the path to the hosts file.
     */
    public function getHostsPath(): string
    {
        return self::HOSTS_PATH;
    }

    /**
     * Build the full hosts entry line for a domain.
     */
    public function buildEntry(string $domain): string
    {
        return "127.0.0.1 {$domain}";
    }
}
