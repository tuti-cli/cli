<?php

declare(strict_types=1);

namespace App\Services\Project;

use RuntimeException;

/**
 * Service ProjectInitializationService
 *
 * Handles the business logic for initializing a new Tuti project.
 * This service coordinates:
 * 1. Directory creation (via ProjectDirectoryService)
 * 2. Configuration generation (via ProjectMetadataService)
 * 3. Validation of initialization
 *
 * Following the same pattern as ProjectStateManagerService,
 * this service acts as the "orchestrator" for project initialization.
 */
final readonly class ProjectInitializationService
{
    public function __construct(
        private ProjectDirectoryService $directoryService,
        private ProjectMetadataService $metadataService
    ) {}

    /**
     * Initialize a new project with the given name and environment.
     *
     * @throws RuntimeException If initialization fails
     */
    public function initialize(string $projectName, string $environment): bool
    {
        // 1. Create directory structure
        $this->directoryService->initialize();

        // 2. Create minimal configuration
        $config = $this->buildMinimalConfig($projectName, $environment);
        $this->metadataService->create($config);

        // 3. Validate the initialization
        if (! $this->directoryService->validate()) {
            throw new RuntimeException('Project initialization validation failed');
        }

        return true;
    }

    /**
     * Build the minimal configuration structure for a new project.
     *
     * @return array<string, mixed>
     */
    private function buildMinimalConfig(string $projectName, string $environment): array
    {
        return [
            'project' => [
                'name' => $projectName,
                'type' => 'custom',
                'version' => '1.0.0',
            ],
            'environments' => [
                'current' => $environment,
                $environment => [
                    'domain' => "{$projectName}.test",
                ],
            ],
            'initialized_at' => now()->toIso8601String(),
        ];
    }
}
