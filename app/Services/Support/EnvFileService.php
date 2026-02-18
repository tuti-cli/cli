<?php

declare(strict_types=1);

namespace App\Services\Support;

/**
 * Centralized service for .env file manipulation.
 * Used by all stack installers for consistent environment handling.
 */
final readonly class EnvFileService
{
    private const string TUTI_SECTION_MARKER = 'TUTI-CLI DOCKER CONFIGURATION';

    /**
     * Get the full path to .env file in a directory.
     */
    private function getEnvPath(string $directory): string
    {
        return rtrim($directory, '/') . '/.env';
    }

    /**
     * Get the full path to .env.example file in a directory.
     */
    private function getExamplePath(string $directory): string
    {
        return rtrim($directory, '/') . '/.env.example';
    }

    /**
     * Check if .env file exists.
     */
    public function exists(string $directory): bool
    {
        return file_exists($this->getEnvPath($directory));
    }

    /**
     * Check if .env.example exists.
     */
    public function exampleExists(string $directory): bool
    {
        return file_exists($this->getExamplePath($directory));
    }

    /**
     * Copy .env.example to .env if .env doesn't exist.
     * Returns true if copied, false if .env already exists or .env.example doesn't exist.
     */
    public function copyExampleToEnv(string $directory): bool
    {
        $envPath = $this->getEnvPath($directory);
        $examplePath = $this->getExamplePath($directory);

        if (file_exists($envPath)) {
            return false; // Already exists
        }

        if (! file_exists($examplePath)) {
            return false; // No example to copy from
        }

        return copy($examplePath, $envPath);
    }

    /**
     * Read .env file content.
     */
    public function read(string $directory): string
    {
        $path = $this->getEnvPath($directory);

        if (! file_exists($path)) {
            return '';
        }

        $content = file_get_contents($path);

        return $content !== false ? $content : '';
    }

    /**
     * Write content to .env file.
     */
    public function write(string $directory, string $content): bool
    {
        $path = $this->getEnvPath($directory);

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Update or add a single value in .env file.
     */
    public function setValue(string $directory, string $key, string $value): bool
    {
        $content = $this->read($directory);
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            // Add new variable at the end (before Tuti section if exists)
            $content = $this->insertBeforeTutiSection($content, $replacement);
        }

        return $this->write($directory, (string) $content);
    }

    /**
     * Update multiple values using regex patterns.
     *
     * @param  array<string, string>  $replacements  pattern => replacement
     */
    public function updateValues(string $directory, array $replacements): bool
    {
        $content = $this->read($directory);

        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, (string) $content);
        }

        return $this->write($directory, (string) $content);
    }

    /**
     * Check if Tuti section marker exists in the file.
     */
    public function hasTutiSection(string $directory): bool
    {
        $content = $this->read($directory);

        return str_contains($content, self::TUTI_SECTION_MARKER);
    }

    /**
     * Append Tuti Docker configuration section to .env file.
     * Only appends if section doesn't already exist.
     *
     * @param  array<string, mixed>  $options
     */
    public function appendTutiSection(string $directory, string $projectName, array $options = []): bool
    {
        if ($this->hasTutiSection($directory)) {
            return false; // Already has Tuti section
        }

        $content = $this->read($directory);
        $tutiSection = $this->buildTutiSection($projectName, $options);

        return $this->write($directory, $content . $tutiSection);
    }

    /**
     * Build Tuti configuration section content.
     *
     * @param  array<string, mixed>  $options
     */
    private function buildTutiSection(string $projectName, array $options = []): string
    {
        $appDomain = $projectName . '.local.test';
        $userId = $this->getCurrentUserId();
        $groupId = $this->getCurrentGroupId();
        $stackType = $options['stack_type'] ?? 'laravel';
        $phpVersion = $options['php_version'] ?? '8.4';

        // Set Apache document root based on stack type
        // Bedrock uses /var/www/html/web, others use /var/www/html
        $apacheDocumentRoot = $stackType === 'bedrock'
            ? '/var/www/html/web'
            : '/var/www/html';

        return <<<EOT


# ============================================================================
# 🐳 TUTI-CLI DOCKER CONFIGURATION
# ============================================================================
# The following variables are used by Docker Compose for container setup.
# These are managed by tuti-cli and should not be changed manually unless
# you know what you're doing.
# ============================================================================

# ----------------------------------------------------------------------------
# Project Configuration
# ----------------------------------------------------------------------------
PROJECT_NAME={$projectName}
APP_DOMAIN={$appDomain}
STACK_TYPE={$stackType}
APACHE_DOCUMENT_ROOT={$apacheDocumentRoot}

# ----------------------------------------------------------------------------
# Docker Build Configuration
# ----------------------------------------------------------------------------
PHP_VERSION={$phpVersion}
PHP_VARIANT=fpm-apache
BUILD_TARGET=development

# Docker User/Group IDs (auto-detected from current user)
DOCKER_USER_ID={$userId}
DOCKER_GROUP_ID={$groupId}
EOT;
    }

    /**
     * Insert content before Tuti section, or at end if no Tuti section.
     */
    private function insertBeforeTutiSection(string $content, string $newLine): string
    {
        // Look for the Tuti section marker
        $tutiPos = mb_strpos($content, self::TUTI_SECTION_MARKER);

        if ($tutiPos === false) {
            // No Tuti section, append at end
            return mb_rtrim($content) . "\n" . $newLine . "\n";
        }

        // Find the start of the line containing the marker
        $lineStart = mb_strrpos(mb_substr($content, 0, $tutiPos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;

        // Insert before Tuti section
        return mb_substr($content, 0, $lineStart)
            . $newLine . "\n"
            . mb_substr($content, $lineStart);
    }

    /**
     * Get current user's UID.
     */
    private function getCurrentUserId(): int
    {
        if (function_exists('posix_getuid')) {
            $uid = posix_getuid();
            if ($uid > 0) {
                return $uid;
            }
        }

        $output = @shell_exec('id -u 2>/dev/null');

        if ($output !== null && is_numeric(mb_trim($output))) {
            return (int) mb_trim($output);
        }

        return 1000; // Default fallback
    }

    /**
     * Get current user's GID.
     */
    private function getCurrentGroupId(): int
    {
        if (function_exists('posix_getgid')) {
            $gid = posix_getgid();
            if ($gid > 0) {
                return $gid;
            }
        }

        $output = @shell_exec('id -g 2>/dev/null');

        if ($output !== null && is_numeric(mb_trim($output))) {
            return (int) mb_trim($output);
        }

        return 1000; // Default fallback
    }
}
