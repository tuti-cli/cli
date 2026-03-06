<?php

declare(strict_types=1);

namespace App\Services\Stack\Installers;

use App\Contracts\InfrastructureManagerInterface;
use App\Contracts\StackInstallerInterface;
use App\Services\Stack\Installers\Laravel\LaravelProjectCreator;
use App\Services\Stack\StackFilesCopierService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRepositoryService;
use JsonException;
use RuntimeException;

/**
 * Laravel Stack Installer.
 *
 * Handles installation and configuration of Laravel projects with Docker.
 * Supports two modes:
 * - Fresh installation: Creates a new Laravel project with Docker configuration
 * - Apply to existing: Adds Docker configuration to an existing Laravel project
 *
 * Delegates specialized tasks to sub-services:
 * - LaravelProjectCreator: Project creation logic
 * - LaravelDatabaseConfigurator: Database environment configuration
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
        private InfrastructureManagerInterface $infrastructureManager,
        private LaravelProjectCreator $projectCreator,
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
            try {
                $composer = json_decode(file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

                return isset($composer['require']['laravel/framework']);
            } catch (JsonException) {
                return false;
            }
        }

        return false;
    }

    public function installFresh(string $projectPath, string $projectName, array $options = []): bool
    {
        $this->ensureInfrastructureReady();

        // Delegate project creation to LaravelProjectCreator
        return $this->projectCreator->createProject($projectPath, $projectName, $options);
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
}
