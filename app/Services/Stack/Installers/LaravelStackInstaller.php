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
 * Laravel Stack Installer.
 *
 * Handles installation and configuration of Laravel projects with Docker.
 * Supports two modes:
 * - Fresh installation: Creates a new Laravel project with Docker configuration
 * - Apply to existing: Adds Docker configuration to an existing Laravel project
 *
 * Uses Docker to run Composer, so no local PHP/Composer installation is required.
 */
final readonly class LaravelStackInstaller implements StackInstallerInterface
{
    private const string IDENTIFIER = 'laravel';

    private const array SUPPORTED_IDENTIFIERS = [
        'laravel',
        'laravel-stack',
    ];

    public function __construct(
        private StackLoaderService $stackLoader,
        private StackFilesCopierService $copierService,
        private StackRepositoryService $repositoryService,
        private DockerExecutorInterface $dockerExecutor,
        private InfrastructureManagerInterface $infrastructureManager,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getName(): string
    {
        return 'Laravel Stack';
    }

    public function getDescription(): string
    {
        return 'Production-ready Laravel application stack with Docker';
    }

    public function getFramework(): string
    {
        return 'laravel';
    }

    public function supports(string $stackIdentifier): bool
    {
        return in_array($stackIdentifier, self::SUPPORTED_IDENTIFIERS, true);
    }

    public function detectExistingProject(string $path): bool
    {
        // Check for Laravel-specific files
        $laravelIndicators = [
            $path . '/artisan',
            $path . '/composer.json',
            $path . '/bootstrap/app.php',
        ];

        foreach ($laravelIndicators as $indicator) {
            if (! file_exists($indicator)) {
                return false;
            }
        }

        // Additionally check composer.json for Laravel framework
        $composerPath = $path . '/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);

            return isset($composer['require']['laravel/framework']);
        }

        return false;
    }

    public function installFresh(string $projectPath, string $projectName, array $options = []): bool
    {
        $this->validateFreshInstallation($projectPath);

        // Ensure infrastructure is ready (Traefik)
        $this->ensureInfrastructureReady();

        // Check if we should use interactive Laravel installer
        $useInteractive = $options['interactive'] ?? true;

        if ($useInteractive && ! ($options['no_interaction'] ?? false)) {
            // Use Laravel installer with interactive prompts
            $result = $this->createLaravelProjectInteractive($projectPath, $options);
        } else {
            // Fallback to composer create-project (non-interactive)
            $result = $this->createLaravelProject($projectPath, $options);
        }

        if (! $result) {
            throw new RuntimeException('Failed to create Laravel project');
        }

        return true;
    }

    public function applyToExisting(string $projectPath, array $options = []): bool
    {
        if (! $this->detectExistingProject($projectPath)) {
            throw new RuntimeException('No Laravel project detected in the specified path');
        }

        // Ensure infrastructure is ready (Traefik)
        $this->ensureInfrastructureReady();

        // Copy Docker configuration files from stack
        $stackPath = $this->getStackPath();
        $this->copierService->copyFromStack($stackPath);

        return true;
    }

    public function getStackPath(): string
    {
        return $this->repositoryService->getStackPath('laravel');
    }

    public function getAvailableModes(): array
    {
        return [
            'fresh' => 'Create new Laravel project with Docker configuration',
            'existing' => 'Add Docker configuration to existing Laravel project',
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
     * Generate Laravel APP_KEY using Docker.
     */
    public function generateAppKey(string $projectPath): ?string
    {
        $result = $this->dockerExecutor->runArtisan('key:generate --show', $projectPath);

        if (! $result->successful) {
            return null;
        }

        // The output may contain Docker/PHP banner text before the actual key
        // We need to extract only the base64 key (format: base64:xxx...)
        $output = $result->output;

        // Look for a line that starts with "base64:"
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = mb_trim($line);
            if (str_starts_with($line, 'base64:')) {
                return $line;
            }
        }

        // Fallback: if no base64: prefix found, return the last non-empty line
        $nonEmptyLines = array_filter($lines, fn ($l): bool => ! in_array(mb_trim($l), ['', '0'], true));
        $lastLine = mb_trim(end($nonEmptyLines));

        // Only return if it looks like a base64 key
        if (str_starts_with($lastLine, 'base64:') || mb_strlen($lastLine) > 40) {
            return $lastLine;
        }

        return null;
    }

    /**
     * Run artisan command in the project.
     */
    public function runArtisan(string $projectPath, string $command): bool
    {
        $result = $this->dockerExecutor->runArtisan($command, $projectPath);

        return $result->successful;
    }

    /**
     * Run composer require to install a package.
     */
    public function runComposerRequire(string $projectPath, string $package): bool
    {
        $result = $this->dockerExecutor->runComposer("require {$package} --no-interaction", $projectPath);

        return $result->successful;
    }

    /**
     * Install npm dependencies using Docker.
     */
    public function installNpmDependencies(string $projectPath): bool
    {
        $result = $this->dockerExecutor->runNpm('install', $projectPath);

        return $result->successful;
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
     * Create a new Laravel project using Docker + Composer.
     *
     * @param  array<string, mixed>  $options
     */
    private function createLaravelProject(string $projectPath, array $options): bool
    {
        // Ensure project directory exists
        if (! is_dir($projectPath) && (! mkdir($projectPath, 0755, true) && ! is_dir($projectPath))) {
            throw new RuntimeException("Failed to create directory: {$projectPath}");
        }

        // Build the composer command
        $composerCommand = $this->buildComposerCommand($options);

        // Execute composer create-project via Docker
        $result = $this->dockerExecutor->runComposer($composerCommand, $projectPath);

        if (! $result->successful) {
            throw new RuntimeException(
                'Failed to create Laravel project: ' . $result->errorOutput
            );
        }

        return true;
    }

    /**
     * Create a new Laravel project using the Laravel installer with interactive prompts.
     *
     * This allows users to select stacks (React, Vue, API-only, etc.) through
     * Laravel's native interactive prompts.
     *
     * @param  array<string, mixed>  $options
     */
    private function createLaravelProjectInteractive(string $projectPath, array $options): bool
    {
        // Extract project name and parent directory
        $projectName = basename($projectPath);
        $parentDir = dirname($projectPath);

        // Ensure parent directory exists
        if (! is_dir($parentDir) && (! mkdir($parentDir, 0755, true) && ! is_dir($parentDir))) {
            throw new RuntimeException("Failed to create directory: {$parentDir}");
        }

        // Use PHP image with composer to run Laravel installer
        $image = $this->dockerExecutor->getPhpImage('8.4');

        // Set COMPOSER_HOME to a known path
        $composerHome = '/tmp/.composer';

        // Map Tuti database selection to Laravel installer database flag
        $databaseFlag = $this->buildDatabaseFlag($options['database'] ?? null);

        // Build command to run Laravel installer:
        // 1. Create composer home directory
        // 2. Install laravel/installer globally to a known path
        // 3. Run laravel new with the full path to the binary and database flag
        $command = [
            'sh',
            '-c',
            sprintf(
                'mkdir -p %s && ' .
                'COMPOSER_HOME=%s composer global require laravel/installer --no-interaction 2>/dev/null && ' .
                '%s/vendor/bin/laravel new %s%s',
                escapeshellarg($composerHome),
                escapeshellarg($composerHome),
                escapeshellarg($composerHome),
                escapeshellarg($projectName),
                $databaseFlag
            ),
        ];

        // Environment variables for composer
        $env = [
            'COMPOSER_HOME' => $composerHome,
            'COMPOSER_ALLOW_SUPERUSER' => '1',
        ];

        // Run with TTY support for interactive prompts
        $exitCode = $this->dockerExecutor->runInteractive(
            $image,
            $command,
            $parentDir,
            $env,
            []
        );

        if ($exitCode !== 0) {
            // Check if project was still created (laravel new might fail on final steps)
            if (file_exists($projectPath . '/artisan')) {
                // Project exists, consider it a success
                return true;
            }

            throw new RuntimeException(
                "Failed to create Laravel project (exit code: {$exitCode})"
            );
        }

        return true;
    }

    /**
     * Build the --database flag for Laravel installer based on Tuti database selection.
     *
     * Maps Tuti's database service names to Laravel installer database values:
     * - databases.postgres → pgsql
     * - databases.mysql → mysql
     * - databases.mariadb → mariadb
     */
    private function buildDatabaseFlag(?string $tutiDatabase): string
    {
        if ($tutiDatabase === null) {
            return '';
        }

        $map = [
            'databases.postgres' => 'pgsql',
            'databases.mysql' => 'mysql',
            'databases.mariadb' => 'mariadb',
        ];

        $laravelDb = $map[$tutiDatabase] ?? null;

        if ($laravelDb === null) {
            return '';
        }

        return " --database={$laravelDb}";
    }

    /**
     * Build the composer create-project command.
     *
     * @param  array<string, mixed>  $options
     */
    private function buildComposerCommand(array $options): string
    {
        $package = 'laravel/laravel';
        $version = $options['laravel_version'] ?? null;

        $command = "create-project {$package}";

        if ($version !== null) {
            $command .= ":{$version}";
        }

        // Install in current directory (which is mounted as /app in container)
        $command .= ' .';

        // Add options
        $command .= ' --prefer-dist';
        $command .= ' --no-interaction';

        if (! empty($options['no_dev'])) {
            $command .= ' --no-dev';
        }

        return $command;
    }

    /**
     * Validate that fresh installation can proceed.
     */
    private function validateFreshInstallation(string $projectPath): void
    {
        if (is_dir($projectPath) && count(scandir($projectPath)) > 2) {
            throw new RuntimeException(
                "Directory {$projectPath} is not empty. Cannot create fresh Laravel project."
            );
        }

        // Check if Docker is available (instead of local Composer)
        if (! $this->dockerExecutor->isDockerAvailable()) {
            throw new RuntimeException(
                'Docker is not available. Please install Docker to create Laravel projects.'
            );
        }
    }
}
