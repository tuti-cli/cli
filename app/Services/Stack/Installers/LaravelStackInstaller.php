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
final class LaravelStackInstaller implements StackInstallerInterface
{
    private const IDENTIFIER = 'laravel';

    private const SUPPORTED_IDENTIFIERS = [
        'laravel',
        'laravel-stack',
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

        // Create the project using Docker + Composer
        $result = $this->createLaravelProject($projectPath, $projectName, $options);

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
    private function createLaravelProject(string $projectPath, string $projectName, array $options): bool
    {
        // Ensure project directory exists
        if (! is_dir($projectPath)) {
            if (! mkdir($projectPath, 0755, true) && ! is_dir($projectPath)) {
                throw new RuntimeException("Failed to create directory: {$projectPath}");
            }
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
        $command .= " .";

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

    /**
     * Generate Laravel APP_KEY using Docker.
     */
    public function generateAppKey(string $projectPath): ?string
    {
        $result = $this->dockerExecutor->runArtisan('key:generate --show', $projectPath);

        if (! $result->successful) {
            return null;
        }

        return trim($result->output);
    }

    /**
     * Run artisan command in the project.
     */
    public function runArtisan(string $command, string $projectPath): bool
    {
        $result = $this->dockerExecutor->runArtisan($command, $projectPath);

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
}
