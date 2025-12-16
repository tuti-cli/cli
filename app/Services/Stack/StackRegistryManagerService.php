<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;

/**
 * ServiceRegistryJsonReader is responsible for reading and parsing
 * the service registry.json file.
 */
final readonly class StackRegistryManagerService
{
    private array $registry;

    public function __construct(
        private \App\Services\Storage\JsonFileService $jsonService,
        private string $registryPath = 'services/registry.json'
    ) {
        $this->loadRegistry();
    }

    /**
     * Load the service registry from JSON file
     */
    private function loadRegistry(): void
    {
        $fullPath = stub_path($this->registryPath);

        try {
            $this->registry = $this->jsonService->read($fullPath);
        } catch (\RuntimeException $e) {
            throw new RuntimeException("Failed to read service registry: {$fullPath}. " . $e->getMessage());
        }
    }
}
