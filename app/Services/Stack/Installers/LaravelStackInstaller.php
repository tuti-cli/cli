<?php

declare(strict_types=1);

namespace App\Services\Stack\Installers;

use App\Contracts\StackInstallerInterface;
use App\Services\Stack\StackFilesCopierService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRepositoryService;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Laravel Stack Installer.
 *
 * Handles installation and configuration of Laravel projects with Docker.
 * Supports two modes:
 * - Fresh installation: Creates a new Laravel project with Docker configuration
 * - Apply to existing: Adds Docker configuration to an existing Laravel project
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

        // Create the project using composer create-project
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
     * Create a new Laravel project using Composer.
     *
     * @param  array<string, mixed>  $options
     */
    private function createLaravelProject(string $projectPath, string $projectName, array $options): bool
    {
        $parentDir = dirname($projectPath);
        $projectDir = basename($projectPath);

        // Ensure parent directory exists
        if (! is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        // Build the composer create-project command
        $command = $this->buildComposerCommand($projectDir, $options);

        // Execute composer create-project
        $process = Process::path($parentDir)
            ->timeout(600) // 10 minutes timeout for slow connections
            ->run($command);

        if (! $process->successful()) {
            throw new RuntimeException(
                'Failed to create Laravel project: ' . $process->errorOutput()
            );
        }

        return true;
    }

    /**
     * Build the composer create-project command.
     *
     * @param  array<string, mixed>  $options
     */
    private function buildComposerCommand(string $projectDir, array $options): string
    {
        $package = 'laravel/laravel';
        $version = $options['laravel_version'] ?? null;

        $command = "composer create-project {$package}";

        if ($version !== null) {
            $command .= ":{$version}";
        }

        $command .= " {$projectDir}";

        // Add options
        if (! empty($options['prefer_dist'])) {
            $command .= ' --prefer-dist';
        }

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

        // Check if composer is available
        $process = Process::run('composer --version');

        if (! $process->successful()) {
            throw new RuntimeException(
                'Composer is not available. Please install Composer to create Laravel projects.'
            );
        }
    }
}
