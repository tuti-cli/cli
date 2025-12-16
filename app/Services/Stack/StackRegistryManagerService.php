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
    public function __construct(
        private string $registryPath = 'services/registry.json'
    ) {
        $this->loadRegistry();
    }

    /**
     * Load the service registry from JSON file
     */
    private function loadRegistry(): void
    {
        stub_path($this->registryPath);
    }
}
