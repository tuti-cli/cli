<?php

declare(strict_types=1);

namespace App\Services\Stack\Installers;

use App\Contracts\DockerExecutorInterface;
use App\Contracts\InfrastructureManagerInterface;
use App\Contracts\StackInstallerInterface;
use App\Services\Stack\StackFilesCopierService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRepositoryService;
use RuntimeException;

/**
 * WordPress Stack Installer.
 *
 * Handles installation and configuration of WordPress projects with Docker.
 * Supports two installation types:
 * - Standard: Traditional WordPress installation with classic file structure
 * - Bedrock: Modern WordPress development with Composer (Roots)
 *
 * Uses Docker to run WP-CLI and Composer, so no local PHP installation is required.
 */
final class WordPressStackInstaller implements StackInstallerInterface
{
    private const IDENTIFIER = 'wordpress';

    private const SUPPORTED_IDENTIFIERS = [
        'wordpress',
        'wordpress-stack',
        'wp',
    ];

    public function __construct(
        private readonly StackLoaderService $stackLoader,
        private readonly StackFilesCopierService $copierService,
        private readonly StackRepositoryService $repositoryService,
        private readonly DockerExecutorInterface $dockerExecutor,
        private readonly InfrastructureManagerInterface $infrastructureManager,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getName(): string
    {
        return 'WordPress Stack';
    }

    public function getDescription(): string
    {
        return 'Production-ready WordPress application stack with Docker';
    }

    public function getFramework(): string
    {
        return 'wordpress';
    }

    public function supports(string $stackIdentifier): bool
    {
        return in_array($stackIdentifier, self::SUPPORTED_IDENTIFIERS, true);
    }

    public function detectExistingProject(string $path): bool
    {
        // Check for standard WordPress installation
        $standardIndicators = [
            $path . '/wp-config.php',
            $path . '/wp-content',
            $path . '/wp-includes',
        ];

        $isStandard = true;
        foreach ($standardIndicators as $indicator) {
            if (! file_exists($indicator)) {
                $isStandard = false;
                break;
            }
        }

        if ($isStandard) {
            return true;
        }

        // Check for Bedrock installation
        $bedrockIndicators = [
            $path . '/composer.json',
            $path . '/web/wp-config.php',
            $path . '/config/application.php',
        ];

        foreach ($bedrockIndicators as $indicator) {
            if (! file_exists($indicator)) {
                return false;
            }
        }

        // Additionally check composer.json for Bedrock
        $composerPath = $path . '/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);

            return isset($composer['require']['roots/bedrock']);
        }

        return false;
    }

    /**
     * Detect the WordPress installation type.
     *
     * @return string|null 'standard', 'bedrock', or null if not WordPress
     */
    public function detectInstallationType(string $path): ?string
    {
        // Check for Bedrock first (more specific)
        if (file_exists($path . '/config/application.php') && file_exists($path . '/web')) {
            return 'bedrock';
        }

        // Check for standard WordPress
        if (file_exists($path . '/wp-config.php') || file_exists($path . '/wp-includes')) {
            return 'standard';
        }

        return null;
    }

    public function installFresh(string $projectPath, string $projectName, array $options = []): bool
    {
        $this->validateFreshInstallation($projectPath);

        // Ensure infrastructure is ready (Traefik)
        $this->ensureInfrastructureReady();

        // Get installation type (standard or bedrock)
        $installationType = $options['installation_type'] ?? 'standard';

        // Create the project using Docker
        $result = match ($installationType) {
            'bedrock' => $this->createBedrockProject($projectPath, $projectName, $options),
            default => $this->createStandardWordPressProject($projectPath, $projectName, $options),
        };

        if (! $result) {
            throw new RuntimeException('Failed to create WordPress project');
        }

        return true;
    }

    public function applyToExisting(string $projectPath, array $options = []): bool
    {
        if (! $this->detectExistingProject($projectPath)) {
            throw new RuntimeException('No WordPress project detected in the specified path');
        }

        // Ensure infrastructure is ready (Traefik)
        $this->ensureInfrastructureReady();

        // Detect installation type
        $installationType = $this->detectInstallationType($projectPath);
        if ($installationType !== null) {
            $options['installation_type'] = $installationType;
        }

        // Copy Docker configuration files from stack
        $stackPath = $this->getStackPath();
        $this->copierService->copyFromStack($stackPath);

        return true;
    }

    public function getStackPath(): string
    {
        return $this->repositoryService->getStackPath('wordpress');
    }

    public function getAvailableModes(): array
    {
        return [
            'fresh' => 'Create new WordPress project with Docker configuration',
            'existing' => 'Add Docker configuration to existing WordPress project',
        ];
    }

    /**
     * Get the stack manifest.
     *
     * @return array<string, mixed>
     */
    public function getStackManifest(): array
    {
        return $this->stackLoader->load($this->getStackPath());
    }

    /**
     * Get available installation types.
     *
     * @return array<string, array<string, string>>
     */
    public function getInstallationTypes(): array
    {
        return [
            'standard' => [
                'name' => 'Standard WordPress',
                'description' => 'Traditional WordPress installation with classic file structure',
            ],
            'bedrock' => [
                'name' => 'Bedrock (Roots)',
                'description' => 'Modern WordPress development with Composer, enhanced security, and 12-factor app methodology',
            ],
        ];
    }

    /**
     * Ensure the global infrastructure (Traefik) is ready.
     */
    private function ensureInfrastructureReady(): void
    {
        if (! $this->infrastructureManager->ensureReady()) {
            throw new RuntimeException(
                'Failed to start global infrastructure. Please run "tuti install" first.'
            );
        }
    }

    /**
     * Create a standard WordPress project using Docker + WP-CLI.
     *
     * @param  array<string, mixed>  $options
     */
    private function createStandardWordPressProject(string $projectPath, string $projectName, array $options): bool
    {
        // Ensure project directory exists
        if (! is_dir($projectPath)) {
            if (! mkdir($projectPath, 0755, true) && ! is_dir($projectPath)) {
                throw new RuntimeException("Failed to create directory: {$projectPath}");
            }
        }

        // Download WordPress core using WP-CLI via Docker
        $wpVersion = $options['wp_version'] ?? 'latest';
        $locale = $options['locale'] ?? 'en_US';

        $command = "core download --version={$wpVersion} --locale={$locale} --path=/app";

        $result = $this->dockerExecutor->runWpCli($command, $projectPath);

        if (! $result->successful) {
            throw new RuntimeException(
                'Failed to download WordPress: ' . $result->errorOutput
            );
        }

        return true;
    }

    /**
     * Create a Bedrock WordPress project using Docker + Composer.
     *
     * @param  array<string, mixed>  $options
     */
    private function createBedrockProject(string $projectPath, string $projectName, array $options): bool
    {
        // Ensure project directory exists
        if (! is_dir($projectPath)) {
            if (! mkdir($projectPath, 0755, true) && ! is_dir($projectPath)) {
                throw new RuntimeException("Failed to create directory: {$projectPath}");
            }
        }

        // Create Bedrock project using Composer via Docker
        $command = 'create-project roots/bedrock . --prefer-dist --no-interaction';

        $result = $this->dockerExecutor->runComposer($command, $projectPath);

        if (! $result->successful) {
            throw new RuntimeException(
                'Failed to create Bedrock project: ' . $result->errorOutput
            );
        }

        return true;
    }

    /**
     * Validate that fresh installation can proceed.
     */
    private function validateFreshInstallation(string $projectPath): void
    {
        if (is_dir($projectPath) && count(scandir($projectPath)) > 2) {
            throw new RuntimeException(
                "Directory {$projectPath} is not empty. Cannot create fresh WordPress project."
            );
        }

        // Check if Docker is available
        if (! $this->dockerExecutor->isDockerAvailable()) {
            throw new RuntimeException(
                'Docker is not available. Please install Docker to create WordPress projects.'
            );
        }
    }

    /**
     * Generate WordPress salts (authentication keys).
     *
     * @return array<string, string>
     */
    public function generateSalts(): array
    {
        $salts = [];
        $keys = [
            'WP_AUTH_KEY',
            'WP_SECURE_AUTH_KEY',
            'WP_LOGGED_IN_KEY',
            'WP_NONCE_KEY',
            'WP_AUTH_SALT',
            'WP_SECURE_AUTH_SALT',
            'WP_LOGGED_IN_SALT',
            'WP_NONCE_SALT',
        ];

        foreach ($keys as $key) {
            $salts[$key] = $this->generateSecureKey(64);
        }

        return $salts;
    }

    /**
     * Generate a secure random key.
     */
    private function generateSecureKey(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{}|;:,.<>?';
        $key = '';

        for ($i = 0; $i < $length; $i++) {
            $key .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $key;
    }

    /**
     * Run WP-CLI command in the project.
     */
    public function runWpCli(string $projectPath, string $command): bool
    {
        $result = $this->dockerExecutor->runWpCli($command, $projectPath);

        return $result->successful;
    }

    /**
     * Install a WordPress plugin using WP-CLI.
     */
    public function installPlugin(string $projectPath, string $plugin, bool $activate = true): bool
    {
        $command = "plugin install {$plugin}";
        if ($activate) {
            $command .= ' --activate';
        }

        return $this->runWpCli($projectPath, $command);
    }

    /**
     * Install a WordPress theme using WP-CLI.
     */
    public function installTheme(string $projectPath, string $theme, bool $activate = false): bool
    {
        $command = "theme install {$theme}";
        if ($activate) {
            $command .= ' --activate';
        }

        return $this->runWpCli($projectPath, $command);
    }
}
