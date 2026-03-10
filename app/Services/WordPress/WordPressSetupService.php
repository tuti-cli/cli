<?php

declare(strict_types=1);

namespace App\Services\WordPress;

use App\Enums\MultisiteModeEnum;
use App\Enums\WordPressTypeEnum;
use App\Services\Stack\Installers\WordPressStackInstaller;
use RuntimeException;

/**
 * WordPress Setup Service.
 *
 * Handles WordPress installation configuration, detection, validation, and setup execution
 * for different installation types (Standard, Bedrock) and multisite modes.
 */
final readonly class WordPressSetupService
{
    public function __construct(
        private WordPressStackInstaller $installer,
    ) {}

    /**
     * Detect the WordPress installation type.
     *
     * @return WordPressTypeEnum The detected installation type
     */
    public function detectInstallationType(string $projectPath): WordPressTypeEnum
    {
        $detectedType = $this->installer->detectInstallationType($projectPath);

        return match ($detectedType) {
            'bedrock' => WordPressTypeEnum::BEDROCK,
            'standard' => WordPressTypeEnum::SINGLE,
            default => WordPressTypeEnum::SINGLE,
        };
    }

    /**
     * Detect the multisite mode from an existing WordPress installation.
     *
     * @return MultisiteModeEnum The detected multisite mode
     */
    public function detectMultisiteMode(string $projectPath): MultisiteModeEnum
    {
        // Check for wp-config.php (standard) or config/application.php (Bedrock)
        $configPath = $this->getConfigFilePath($projectPath);

        if (! file_exists($configPath)) {
            return MultisiteModeEnum::NONE;
        }

        $content = file_get_contents($configPath);

        if ($content === false) {
            return MultisiteModeEnum::NONE;
        }

        // Check for MULTISITE constant
        if (! preg_match('/define\s*\(\s*[\'"]MULTISITE[\'"]\s*,\s*true\s*\)/', $content)) {
            return MultisiteModeEnum::NONE;
        }

        // Check for SUBDOMAIN_INSTALL constant
        if (preg_match('/define\s*\(\s*[\'"]SUBDOMAIN_INSTALL[\'"]\s*,\s*true\s*\)/', $content)) {
            return MultisiteModeEnum::SUBDOMAIN;
        }

        return MultisiteModeEnum::SUBDIRECTORY;
    }

    /**
     * Validate configuration options.
     *
     * @param  array<string, mixed>  $options  Configuration options
     *
     * @throws RuntimeException If validation fails
     */
    public function validateConfiguration(array $options): void
    {
        $type = WordPressTypeEnum::tryFrom($options['type'] ?? 'single') ?? WordPressTypeEnum::SINGLE;
        $multisite = MultisiteModeEnum::tryFrom($options['multisite'] ?? 'none') ?? MultisiteModeEnum::NONE;

        // Bedrock does not support multisite in the initial implementation
        if ($type === WordPressTypeEnum::BEDROCK && $multisite !== MultisiteModeEnum::NONE) {
            throw new RuntimeException(
                'Bedrock multisite support is not yet available. ' .
                'Please use standard WordPress for multisite installations.'
            );
        }

        // Validate required inputs based on type
        $this->validateRequiredInputs($options, $type);
    }

    /**
     * Install WordPress with the given configuration.
     *
     * @param  string  $projectPath  Project directory path
     * @param  array<string, mixed>  $options  Installation options
     *
     * @throws RuntimeException If installation fails
     */
    public function install(string $projectPath, array $options): bool
    {
        // Validate configuration
        $this->validateConfiguration($options);

        // Detect or use specified installation type
        $type = $options['type'] ?? null;
        if ($type === null) {
            $type = $this->detectInstallationType($projectPath);
        }

        // Detect or use specified multisite mode
        $multisite = $options['multisite'] ?? null;
        if ($multisite === null) {
            $multisite = $this->detectMultisiteMode($projectPath);
        }

        // Execute installation based on type
        return match ($type) {
            WordPressTypeEnum::BEDROCK => $this->installBedrock($projectPath, $options),
            default => $this->installStandard($projectPath, $options, $multisite),
        };
    }

    /**
     * Convert existing WordPress installation to multisite.
     *
     * @param  string  $projectPath  Project directory path
     * @param  MultisiteModeEnum  $mode  Multisite mode (SUBDOMAIN or SUBDIRECTORY)
     *
     * @throws RuntimeException If conversion fails
     */
    public function convertToMultisite(string $projectPath, MultisiteModeEnum $mode): bool
    {
        if ($mode === MultisiteModeEnum::NONE) {
            throw new RuntimeException('Cannot convert to multisite with NONE mode');
        }

        // Prepare multisite configuration
        $this->prepareMultisiteConfig($projectPath, $mode);

        return true;
    }

    /**
     * Get the path to wp-config.php for the installation type.
     *
     * @return string Path to the config file
     */
    public function getConfigFilePath(string $projectPath): string
    {
        $type = $this->detectInstallationType($projectPath);

        return match ($type) {
            WordPressTypeEnum::BEDROCK => $projectPath . '/config/application.php',
            default => $projectPath . '/wp-config.php',
        };
    }

    /**
     * Install standard WordPress.
     *
     * @param  string  $projectPath  Project directory path
     * @param  array<string, mixed>  $options  Installation options
     * @param  MultisiteModeEnum  $multisite  Multisite mode
     */
    private function installStandard(string $projectPath, array $options, MultisiteModeEnum $multisite): bool
    {
        // Standard WordPress installation via WP-CLI
        // The existing WpSetupCommand handles the actual WordPress core installation
        // This method prepares configuration for multisite if needed
        if ($multisite !== MultisiteModeEnum::NONE) {
            $this->prepareMultisiteConfig($projectPath, $multisite);
        }

        return true;
    }

    /**
     * Install Bedrock WordPress.
     *
     * @param  string  $projectPath  Project directory path
     * @param  array<string, mixed>  $options  Installation options
     */
    private function installBedrock(string $projectPath, array $options): bool
    {
        // Bedrock installation is handled by WordPressStackInstaller
        // This method prepares configuration for Bedrock
        return true;
    }

    /**
     * Prepare multisite configuration for wp-config.php.
     *
     * @param  string  $projectPath  Project directory path
     * @param  MultisiteModeEnum  $mode  Multisite mode
     */
    private function prepareMultisiteConfig(string $projectPath, MultisiteModeEnum $mode): void
    {
        $configPath = $projectPath . '/wp-config.php';

        if (! file_exists($configPath)) {
            throw new RuntimeException('wp-config.php not found for multisite configuration');
        }

        $content = file_get_contents($configPath);

        if ($content === false) {
            throw new RuntimeException('Failed to read wp-config.php');
        }

        // Add multisite constants before "That's all, stop editing!"
        $multisiteConfig = $this->generateMultisiteConstants($mode);

        $stopEditing = "/* That's all, stop editing! Happy publishing. */";
        $content = str_replace(
            $stopEditing,
            $multisiteConfig . "\n\n/* That's all, stop editing! Happy publishing. */",
            $content
        );

        file_put_contents($configPath, $content);
    }

    /**
     * Generate multisite constants for wp-config.php.
     *
     * @param  MultisiteModeEnum  $mode  Multisite mode
     * @return string Multisite configuration block
     */
    private function generateMultisiteConstants(MultisiteModeEnum $mode): string
    {
        $subdomainValue = $mode->getSubdomainInstallConstant();
        $subdomainInstall = $subdomainValue === true ? 'true' : 'false';

        $config = <<<'PHP'
/**
 * WordPress Multisite Configuration
 */
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', SUBDOMAIN_VALUE);
define('DOMAIN_CURRENT_SITE', parse_url(getenv('WORDPRESS_SITEURL') ?: 'localhost', PHP_URL_HOST));
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

PHP;

        return str_replace('SUBDOMAIN_VALUE', $subdomainInstall, $config);
    }

    /**
     * Validate required inputs based on installation type.
     *
     * @param  array<string, mixed>  $options  Configuration options
     * @param  WordPressTypeEnum  $type  Installation type
     *
     * @throws RuntimeException If required inputs are missing
     */
    private function validateRequiredInputs(array $options, WordPressTypeEnum $type): void
    {
        // For now, all types have the same required inputs
        // This can be extended in the future for type-specific requirements
        $requiredInputs = ['site_url', 'site_title', 'admin_user', 'admin_password', 'admin_email'];

        $missingInputs = array_filter($requiredInputs, fn (string $key): bool => ! isset($options[$key]));

        if ($missingInputs !== []) {
            throw new RuntimeException(
                'Missing required inputs: ' . implode(', ', $missingInputs)
            );
        }
    }
}
