<?php

declare(strict_types=1);

namespace App\Services\Stack\Installers\Laravel;

use App\Contracts\DockerExecutorInterface;
use RuntimeException;

/**
 * Laravel Project Creator.
 *
 * Handles creation of new Laravel projects using Docker + Composer.
 * Supports starter kits, authentication options, and Laravel versions.
 */
final readonly class LaravelProjectCreator
{
    /**
     * Starter kit package mappings.
     *
     * Maps starter kit choice to composer package name.
     * 'auth' packages include Laravel's built-in authentication.
     * 'blank' packages are for API-only or custom auth setups.
     */
    private const array STARTER_KITS = [
        'react' => [
            'auth' => 'laravel/react-starter-kit',
            'blank' => 'laravel/blank-react-starter-kit',
        ],
        'vue' => [
            'auth' => 'laravel/vue-starter-kit',
            'blank' => 'laravel/blank-vue-starter-kit',
        ],
        'livewire' => [
            'auth' => 'laravel/livewire-starter-kit',
            'blank' => 'laravel/blank-livewire-starter-kit',
        ],
        'svelte' => [
            'auth' => 'laravel/svelte-starter-kit',
            'blank' => 'laravel/blank-svelte-starter-kit',
        ],
    ];

    public function __construct(
        private DockerExecutorInterface $dockerExecutor,
    ) {}

    /**
     * Create a new Laravel project using Docker + Composer.
     *
     * @param  array<string, mixed>  $options
     */
    public function createProject(string $projectPath, string $projectName, array $options): bool
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
     * Validate that fresh installation can proceed.
     */
    public function validateFreshInstallation(string $projectPath): void
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
     * Install npm dependencies using Docker.
     */
    public function installNpmDependencies(string $projectPath): bool
    {
        $result = $this->dockerExecutor->runNpm('install', $projectPath);

        return $result->successful;
    }

    /**
     * Get the starter kit composer package based on options.
     *
     * @param  array<string, mixed>  $options
     */
    public function getStarterKit(array $options): ?string
    {
        $kit = $options['starter_kit'] ?? 'none';

        if ($kit === 'none') {
            return null;
        }

        // If no auth, use blank starter kits
        if (($options['authentication'] ?? null) === 'none') {
            return self::STARTER_KITS[$kit]['blank'] ?? null;
        }

        return self::STARTER_KITS[$kit]['auth'] ?? null;
    }

    /**
     * Determine the composer package to install.
     *
     * @param  array<string, mixed>  $options
     */
    public function determinePackage(array $options): string
    {
        $kit = $options['starter_kit'] ?? 'none';

        if ($kit === 'none') {
            return 'laravel/laravel';
        }

        $auth = $options['authentication'] ?? 'laravel';

        if ($auth === 'none') {
            return self::STARTER_KITS[$kit]['blank'];
        }

        return self::STARTER_KITS[$kit]['auth'];
    }

    /**
     * Build the composer create-project command.
     *
     * @param  array<string, mixed>  $options
     */
    private function buildComposerCommand(array $options): string
    {
        // Determine package based on starter kit and authentication options
        $package = $this->determinePackage($options);
        $version = $options['laravel_version'] ?? null;

        // Handle special versions for Livewire single-file
        if (($options['starter_kit'] ?? null) === 'livewire'
            && ($options['authentication'] ?? null) === 'laravel'
            && ($options['livewire_single_file'] ?? false)) {
            $package = 'laravel/livewire-starter-kit:dev-components';
        }

        // Handle WorkOS authentication
        if (($options['authentication'] ?? null) === 'workos') {
            $package = str_replace('-starter-kit', '-starter-kit:dev-workos', $package);
        }

        $command = "create-project {$package}";

        if ($version !== null && ! str_contains($package, ':')) {
            $command .= ":{$version}";
        }

        // Install in current directory (which is mounted as /app in container)
        $command .= ' .';

        // Add options - Laravel's scripts will handle key:generate and migrate
        $command .= ' --stability=dev';
        $command .= ' --prefer-dist';
        $command .= ' --no-interaction';

        if (! empty($options['no_dev'])) {
            $command .= ' --no-dev';
        }

        return $command;
    }
}
