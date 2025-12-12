<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;

/**
 * ServiceRegistryJsonReader is responsible for reading and parsing
 * the service registry.json file.
 */
final readonly class StackJsonRegistryManagerService
{
    private array $registry;

    public function __construct(private string $registryPath)
    {
        $this->loadRegistry();
    }

    /**
     * Load the service registry from JSON file
     */
    private function loadRegistry(): void
    {
        $fullPath = stub_path($this->registryPath);

        if (! file_exists($fullPath)) {
            throw new RuntimeException("Service registry not found at:  {$fullPath}");
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw new RuntimeException("Failed to read service registry:  {$fullPath}");
        }

        /** @var array{version: string, description: string, services: array<string, array<string, array<string, mixed>>>} */
        $this->registry = json_decode(
            json:  $content,
            associative: true,
            flags:  JSON_THROW_ON_ERROR
        );
    }

    /**
     * Get all available services grouped by category
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getAllServices(): array
    {
        return $this->registry['services'];
    }

    /**
     * Get a specific service by category and name
     *
     * @param string $category Service category (e.g., 'databases', 'cache')
     * @param string $service Service name (e.g., 'postgres', 'redis')
     * @return array<string, mixed>
     */
    public function getService(string $category, string $service): array
    {
        if (! isset($this->registry['services'][$category][$service])) {
            throw new RuntimeException(
                "Service {$category}.{$service} not found in registry"
            );
        }

        return $this->registry['services'][$category][$service];
    }

    /**
     * Get all services in a specific category
     *
     * @param string $category Service category (e.g., 'databases', 'cache')
     * @return array<string, array<string, mixed>>
     */
    public function getCategory(string $category): array
    {
        if (! isset($this->registry['services'][$category])) {
            throw new RuntimeException("Category {$category} not found in registry");
        }

        return $this->registry['services'][$category];
    }

    /**
     * Check if a service exists
     */
    public function hasService(string $category, string $service): bool
    {
        return isset($this->registry['services'][$category][$service]);
    }

    /**
     * Get service stub path
     */
    public function getServiceStubPath(string $category, string $service): string
    {
        $serviceConfig = $this->getService($category, $service);

        return stub_path('services/' . $serviceConfig['stub']);
    }

    /**
     * Get registry version
     */
    public function getVersion(): string
    {
        return $this->registry['version'];
    }

    /**
     * Get all services compatible with a specific stack
     *
     * @param string $stackType Stack type (e.g., 'laravel', 'wordpress')
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getCompatibleServices(string $stackType): array
    {
        $compatible = [];

        foreach ($this->registry['services'] as $category => $services) {
            foreach ($services as $serviceName => $serviceConfig) {
                if (in_array($stackType, $serviceConfig['compatible_with'] ?? [], true)) {
                    $compatible[$category][$serviceName] = $serviceConfig;
                }
            }
        }

        return $compatible;
    }
}
