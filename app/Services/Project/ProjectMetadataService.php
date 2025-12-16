<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Domain\Project\ValueObjects\ProjectConfigurationVO;
use App\Services\Storage\JsonFileService;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\Log\LoggerInterface;
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
        private ProjectDirectoryService $directoryService,
        private JsonFileService $jsonService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Load the current project configuration.
     */
    public function load(): ProjectConfigurationVO
    {
        $path = $this->directoryService->getTutiPath('config.json');

        if (!$this->jsonService->exists($path)) {
            throw new RuntimeException("Configuration file not found at: {$path}");
        }

        // Define variables for substitution
        $variables = [
            '{{SYSTEM_USER}}' => $this->getSystemUser(),
            '{{PROJECT_ROOT}}' => $this->directoryService->getProjectRoot(),
        ];

        try {
            $data = $this->jsonService->read($path, $variables);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to load project config: " . $e->getMessage());
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
        $configPath = $this->directoryService->getTutiPath('config.json');

        if ($this->jsonService->exists($configPath)) {
            throw new RuntimeException('Configuration file already exists');
        }

        $this->jsonService->write($configPath, $config);
    }

    /**
     * Get the current system user.
     */
    private function getSystemUser(): string
    {
        $user = getenv('USER');

        if ($user !== false && $user !== '') {
            return $user;
        }

        if (isset($_SERVER['USER']) && $_SERVER['USER'] !== '') {
            return $_SERVER['USER'];
        }

        // Fallback for Windows if USER env var is missing
        $user = getenv('USERNAME');
        if ($user !== false && $user !== '') {
            return $user;
        }

        return 'tuti';
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
