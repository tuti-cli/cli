<?php

declare(strict_types=1);

namespace App\Services\Support\EnvHandlers;

use App\Services\Support\EnvFileService;

/**
 * Handles .env file operations for WordPress Bedrock projects.
 * Bedrock uses a different .env structure than standard WordPress.
 *
 * Key differences from standard WordPress:
 * - Has its own .env.example with specific variable names
 * - Uses DB_NAME, DB_USER (not DB_DATABASE, DB_USERNAME)
 * - Uses WP_HOME, WP_SITEURL for URLs
 * - Requires WordPress salts (AUTH_KEY, SECURE_AUTH_KEY, etc.) in .env format
 */
final readonly class BedrockEnvHandler
{
    public function __construct(
        private EnvFileService $envService,
    ) {}

    /**
     * Detect if this is a Bedrock project.
     */
    public function detect(string $directory): bool
    {
        $directory = mb_rtrim($directory, '/');

        return file_exists($directory . '/config/application.php')
            && file_exists($directory . '/web/wp-config.php');
    }

    /**
     * Configure .env for Bedrock Docker environment.
     *
     * @param  array<string, mixed>  $options
     */
    public function configure(string $directory, string $projectName, array $options = []): bool
    {
        // Copy .env.example to .env if it doesn't exist
        if (! $this->envService->exists($directory)) {
            $this->envService->copyExampleToEnv($directory);
        }

        if (! $this->envService->exists($directory)) {
            return false;
        }

        $appDomain = $projectName . '.local.test';

        // Bedrock-specific variable names (different from standard WordPress)
        $replacements = $this->getBedrockReplacements($appDomain);

        $this->envService->updateValues($directory, $replacements);

        // Generate WordPress salts from WordPress.org API
        $this->generateSaltsFromApi($directory);

        // Append Tuti section with Bedrock-specific settings
        return $this->envService->appendTutiSection($directory, $projectName, [
            'stack_type' => 'bedrock',
            'php_version' => $options['php_version'] ?? '8.3',
        ]);
    }

    /**
     * Get Bedrock-specific replacements.
     *
     * @return array<string, string>
     */
    private function getBedrockReplacements(string $appDomain): array
    {
        return [
            // Database configuration (Bedrock uses DB_NAME, DB_USER)
            '/^#?\s*DB_HOST=.*$/m' => 'DB_HOST=database',
            '/^DB_PORT=.*$/m' => 'DB_PORT=3306',
            '/^DB_NAME=.*$/m' => 'DB_NAME=wordpress',
            '/^DB_USER=.*$/m' => 'DB_USER=wordpress',
            '/^DB_PASSWORD=.*$/m' => 'DB_PASSWORD=secret',

            // WordPress URLs (Bedrock uses WP_HOME and WP_SITEURL)
            '/^WP_HOME=.*$/m' => "WP_HOME=https://{$appDomain}",
            '/^WP_SITEURL=.*$/m' => "WP_SITEURL=https://{$appDomain}/wp",

            // Environment
            '/^WP_ENV=.*$/m' => 'WP_ENV=development',
        ];
    }

    /**
     * Generate WordPress salts from WordPress.org API and update .env.
     * Converts define('KEY', 'value') format to KEY='value' for Bedrock .env.
     */
    private function generateSaltsFromApi(string $directory): void
    {
        // Fetch salts from WordPress.org API
        $salts = @file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/');

        if ($salts === false || $salts === '') {
            // Fallback to local generation if API fails
            $this->generateSaltsLocally($directory);

            return;
        }

        // Parse define('KEY', 'value') format and convert to KEY='value'
        $content = $this->envService->read($directory);

        // Match all define statements
        if (preg_match_all("/define\s*\(\s*'([A-Z_]+)'\s*,\s*'([^']+)'\s*\)\s*;/", $salts, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1];
                $value = $match[2];

                // Escape single quotes in value for .env format
                $escapedValue = addslashes($value);

                // Replace in .env file: handles KEY=, KEY='', KEY='generateme'
                $patterns = [
                    "/^{$key}=\s*$/m" => "{$key}='{$escapedValue}'",
                    "/^{$key}=\s*['\"][^'\"]*['\"]/m" => "{$key}='{$escapedValue}'",
                ];

                foreach ($patterns as $pattern => $replacement) {
                    if (preg_match($pattern, $content)) {
                        $content = preg_replace($pattern, $replacement, $content);

                        break;
                    }
                }
            }
        }

        $this->envService->write($directory, (string) $content);
    }

    /**
     * Fallback: Generate salts locally if API is unavailable.
     */
    private function generateSaltsLocally(string $directory): void
    {
        $content = $this->envService->read($directory);

        $salts = [
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        ];

        foreach ($salts as $salt) {
            $generatedSalt = $this->generateSecureSalt(64);
            $escapedSalt = addslashes($generatedSalt);

            $patterns = [
                "/^{$salt}=\s*$/m" => "{$salt}='{$escapedSalt}'",
                "/^{$salt}=\s*['\"][^'\"]*['\"]/m" => "{$salt}='{$escapedSalt}'",
            ];

            foreach ($patterns as $pattern => $replacement) {
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, $replacement, $content);

                    break;
                }
            }
        }

        $this->envService->write($directory, (string) $content);
    }

    /**
     * Generate a secure random string for salts.
     */
    private function generateSecureSalt(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';

        return mb_substr(
            str_shuffle(str_repeat($chars, (int) ceil($length / mb_strlen($chars)))),
            1,
            $length
        );
    }
}
