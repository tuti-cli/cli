<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Domain\Project\ValueObjects\ProjectConfigurationVO;
use JsonException;
use RuntimeException;

/**
 * Service ProjectMetadataService
 *
 * Context: Persistence
 * Responsibility: load() and save() the simplified ProjectConfiguration from/to JSON.
 */
final readonly class ProjectMetadataService
{
    public function __construct(
        private ProjectDirectoryService $directoryService
    ) {}

    /**
     * Load the current project configuration.
     */
    public function load(): ProjectConfigurationVO
    {
        // Try config.json in .tuti folder (new standard)
        $path = $this->directoryService->getTutiPath('config.json');

        // Fallback for transition: Check root if not directly in .tuti
        if (! file_exists($path)) {
            // Check for legacy tuti.json in root
            $rootPath = $this->directoryService->getProjectRoot() . '/tuti.json';

            // If legacy exists, use it
            if (file_exists($rootPath)) {
                $path = $rootPath;
            } else {
                // If neither exists, we stick to the primary path for the error message
                throw new RuntimeException("Configuration file not found at: {$path}");
            }
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read config file at {$path}");
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Invalid JSON in config file: ' . $e->getMessage());
        }

        return ProjectConfigurationVO::fromArray($data);
    }

    /**
     * Create a new project configuration file.
     *
     * @param  array<string, mixed>  $config
     */
    public function create(array $config): void
    {
        $configPath = $this->directoryService->getTutiPath('config. json');

        if (file_exists($configPath)) {
            throw new RuntimeException('Configuration file already exists');
        }

        file_put_contents(
            $configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Check if project is initialized.
     */
    public function isInitialized(): bool
    {
        try {
            $this->directoryService->getProjectRoot();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
