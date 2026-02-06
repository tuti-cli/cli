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

        // Create wp-config.php using environment variables
        $this->createWpConfig($projectPath, $projectName);

        return true;
    }

    /**
     * Create wp-config.php file for WordPress.
     * Copies wp-config-sample.php and modifies it for Docker environment.
     */
    private function createWpConfig(string $projectPath, string $projectName): void
    {
        $samplePath = $projectPath . '/wp-config-sample.php';
        $configPath = $projectPath . '/wp-config.php';

        if (! file_exists($samplePath)) {
            throw new RuntimeException('wp-config-sample.php not found. WordPress may not have downloaded correctly.');
        }

        // Read the sample config
        $content = file_get_contents($samplePath);

        // Replace database constants with environment variable lookups
        $replacements = [
            // Database settings
            "define( 'DB_NAME', 'database_name_here' );"
                => "define( 'DB_NAME', getenv('WORDPRESS_DB_NAME') ?: 'wordpress' );",
            "define( 'DB_USER', 'username_here' );"
                => "define( 'DB_USER', getenv('WORDPRESS_DB_USER') ?: 'wordpress' );",
            "define( 'DB_PASSWORD', 'password_here' );"
                => "define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: 'secret' );",
            "define( 'DB_HOST', 'localhost' );"
                => "define( 'DB_HOST', getenv('WORDPRESS_DB_HOST') ?: 'database' );",
        ];

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        // Replace salt placeholders with generated salts
        $salts = $this->generateWordPressSalts();
        foreach ($salts as $key => $value) {
            // Match the pattern: define( 'KEY', 'put your unique phrase here' );
            $pattern = "/define\(\s*'" . preg_quote($key, '/') . "',\s*'put your unique phrase here'\s*\);/";
            $replacement = "define( '{$key}', getenv('WORDPRESS_{$key}') ?: '{$value}' );";
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Replace table prefix to use environment variable
        $content = str_replace(
            "\$table_prefix = 'wp_';",
            "\$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';",
            $content
        );

        // Add WP_DEBUG environment variable support (replace the existing define)
        $content = str_replace(
            "define( 'WP_DEBUG', false );",
            "define( 'WP_DEBUG', filter_var(getenv('WORDPRESS_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN) );",
            $content
        );

        // Add additional Docker-friendly configurations before "That's all, stop editing!"
        $additionalConfig = <<<'PHP'

/**
 * Docker Environment Configurations
 */

// Reverse Proxy / Load Balancer SSL Support (Traefik)
// This fixes redirect loops when behind a reverse proxy handling SSL
if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
    $_SERVER['HTTPS'] = 'on';
}

// WordPress URLs - from environment for Docker flexibility
if ( getenv('WORDPRESS_HOME') ) {
    define( 'WP_HOME', getenv('WORDPRESS_HOME') );
}
if ( getenv('WORDPRESS_SITEURL') ) {
    define( 'WP_SITEURL', getenv('WORDPRESS_SITEURL') );
}

// Debug logging (only when WP_DEBUG is true)
define( 'WP_DEBUG_LOG', filter_var(getenv('WORDPRESS_DEBUG_LOG') ?: false, FILTER_VALIDATE_BOOLEAN) );
define( 'WP_DEBUG_DISPLAY', filter_var(getenv('WORDPRESS_DEBUG_DISPLAY') ?: false, FILTER_VALIDATE_BOOLEAN) );

// Filesystem method - use direct for Docker containers
define( 'FS_METHOD', 'direct' );

// Disable file editing in admin (security best practice for containers)
define( 'DISALLOW_FILE_EDIT', true );

// Force SSL for admin when behind proxy
define( 'FORCE_SSL_ADMIN', true );

PHP;

        // Insert before "That's all, stop editing!"
        $content = str_replace(
            "/* That's all, stop editing! Happy publishing. */",
            $additionalConfig . "\n/* That's all, stop editing! Happy publishing. */",
            $content
        );

        file_put_contents($configPath, $content);
    }

    /**
     * Generate WordPress security salts.
     *
     * @return array<string, string>
     */
    private function generateWordPressSalts(): array
    {
        $keys = [
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        ];

        $salts = [];
        foreach ($keys as $key) {
            $salts[$key] = $this->generateSalt();
        }

        return $salts;
    }

    /**
     * Generate a random salt string.
     */
    private function generateSalt(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{}|;:,.<>?';
        $salt = '';
        for ($i = 0; $i < 64; $i++) {
            $salt .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $salt;
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
     * Uses docker run with proper network and volume mounts.
     *
     * @param  bool  $useNetwork  Whether to connect to the project's Docker network
     */
    public function runWpCli(string $projectPath, string $command, bool $useNetwork = true): bool
    {
        // Get project name from .tuti/config.json or directory name
        $projectName = basename($projectPath);
        $configPath = $projectPath . '/.tuti/config.json';

        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $projectName = $config['project']['name'] ?? $projectName;
        }

        // Build docker run command with network access to running containers
        $networkName = "{$projectName}_dev_network";
        $image = 'wordpress:cli-2-php8.3';

        $dockerCommand = sprintf(
            'docker run --rm -v "%s:/var/www/html" -w /var/www/html',
            $projectPath
        );

        // Add network if requested (for commands that need database access)
        if ($useNetwork) {
            $dockerCommand .= sprintf(' --network %s', $networkName);
        }

        // Add environment variables for database connection
        $dockerCommand .= ' -e WORDPRESS_DB_HOST=database';
        $dockerCommand .= ' -e WORDPRESS_DB_NAME=wordpress';
        $dockerCommand .= ' -e WORDPRESS_DB_USER=wordpress';
        $dockerCommand .= ' -e WORDPRESS_DB_PASSWORD=secret';

        // Add user mapping for file permissions
        if (PHP_OS_FAMILY !== 'Windows') {
            $uid = getmyuid();
            $gid = getmygid();
            if ($uid !== false && $gid !== false) {
                $dockerCommand .= " --user {$uid}:{$gid}";
            }
        }

        // Add image and WP-CLI command
        $dockerCommand .= sprintf(' %s wp %s 2>&1', $image, $command);

        exec($dockerCommand, $output, $exitCode);

        return $exitCode === 0;
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
