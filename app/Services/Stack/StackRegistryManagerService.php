<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;

/**
 * StackRegistryManagerService is responsible for reading and managing
 * the service registry.json file that contains all available services
 * for Docker stacks.
 */
final class StackRegistryManagerService
{
    /**
     * @var array<string, mixed>
     */
    private array $registry;

    public function __construct(
        private readonly string $registryPath = 'services/registry.json'
    ) {
        $this->loadRegistry();
    }

    /**
     * Get the registry version
     */
    public function getVersion(): string
    {
        return $this->registry['version'] ?? '0.0.0';
    }

    /**
     * Get all services from the registry
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getAllServices(): array
    {
        return $this->registry['services'] ?? [];
    }

    /**
     * Get services by category
     *
     * @return array<string, array<string, mixed>>
     */
    public function getServicesByCategory(string $category): array
    {
        return $this->registry['services'][$category] ?? [];
    }

    /**
     * Get a specific service configuration
     *
     * @return array<string, mixed>
     */
    public function getService(string $category, string $serviceName): array
    {
        $service = $this->registry['services'][$category][$serviceName] ?? null;

        if ($service === null) {
            throw new RuntimeException("Service not found: {$category}.{$serviceName}");
        }

        return $service;
    }

    /**
     * Check if a service exists
     */
    public function hasService(string $category, string $serviceName): bool
    {
        return isset($this->registry['services'][$category][$serviceName]);
    }

    /**
     * Get the stub path for a service
     */
    public function getServiceStubPath(string $category, string $serviceName): string
    {
        $service = $this->getService($category, $serviceName);

        return $service['stub'] ?? "{$category}/{$serviceName}.stub";
    }

    /**
     * Get all services compatible with a framework
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getCompatibleServices(string $framework): array
    {
        $compatible = [];

        foreach ($this->getAllServices() as $category => $services) {
            foreach ($services as $name => $config) {
                if (in_array($framework, $config['compatible_with'] ?? [], true)) {
                    $compatible[$category][$name] = $config;
                }
            }
        }

        return $compatible;
    }

    /**
     * Get default variables for a service
     *
     * @return array<string, mixed>
     */
    public function getServiceDefaultVariables(string $category, string $serviceName): array
    {
        $service = $this->getService($category, $serviceName);

        return $service['default_variables'] ?? [];
    }

    /**
     * Get required variables for a service
     *
     * @return array<int, string>
     */
    public function getServiceRequiredVariables(string $category, string $serviceName): array
    {
        $service = $this->getService($category, $serviceName);

        return $service['required_variables'] ?? [];
    }

    /**
     * Get all available categories
     *
     * @return array<int, string>
     */
    public function getCategories(): array
    {
        return array_keys($this->registry['services'] ?? []);
    }

    /**
     * Get dependencies for a service
     *
     * @return array<int, string>
     */
    public function getServiceDependencies(string $category, string $serviceName): array
    {
        $service = $this->getService($category, $serviceName);

        return $service['depends_on'] ?? [];
    }

    /**
     * Resolve dependencies for selected services
     * Adds any missing dependencies to the service list
     *
     * @param  array<int, string>  $selectedServices  Array of service keys (e.g., ['workers.horizon'])
     * @return array<int, string>  Array of service keys including resolved dependencies
     */
    public function resolveDependencies(array $selectedServices): array
    {
        $resolved = [];
        $toProcess = $selectedServices;

        while ($toProcess !== []) {
            $serviceKey = array_shift($toProcess);

            if (in_array($serviceKey, $resolved, true)) {
                continue;
            }

            $resolved[] = $serviceKey;

            // Parse service key
            $parts = explode('.', $serviceKey);
            if (count($parts) !== 2) {
                continue;
            }

            [$category, $serviceName] = $parts;

            if (! $this->hasService($category, $serviceName)) {
                continue;
            }

            // Get dependencies and add to processing queue
            $dependencies = $this->getServiceDependencies($category, $serviceName);

            foreach ($dependencies as $depService) {
                // Dependencies are specified as service names (e.g., 'redis')
                // We need to find which category they belong to
                $depServiceKey = $this->findServiceKey($depService);

                if ($depServiceKey !== null && ! in_array($depServiceKey, $resolved, true)) {
                    // Insert dependency before the service that depends on it
                    array_unshift($toProcess, $depServiceKey);
                }
            }
        }

        // Ensure dependencies come before dependents
        return $this->sortByDependencies($resolved);
    }

    /**
     * Find the full service key for a service name
     *
     * @return string|null Service key (e.g., 'cache.redis') or null if not found
     */
    private function findServiceKey(string $serviceName): ?string
    {
        foreach ($this->getAllServices() as $category => $services) {
            if (isset($services[$serviceName])) {
                return "{$category}.{$serviceName}";
            }
        }

        return null;
    }

    /**
     * Sort services so dependencies come before dependents
     *
     * @param  array<int, string>  $services
     * @return array<int, string>
     */
    private function sortByDependencies(array $services): array
    {
        $sorted = [];
        $remaining = $services;

        while ($remaining !== []) {
            $added = false;

            foreach ($remaining as $key => $serviceKey) {
                $parts = explode('.', $serviceKey);
                if (count($parts) !== 2) {
                    $sorted[] = $serviceKey;
                    unset($remaining[$key]);
                    $added = true;
                    continue;
                }

                [$category, $serviceName] = $parts;

                if (! $this->hasService($category, $serviceName)) {
                    $sorted[] = $serviceKey;
                    unset($remaining[$key]);
                    $added = true;
                    continue;
                }

                $dependencies = $this->getServiceDependencies($category, $serviceName);
                $allDependenciesSatisfied = true;

                foreach ($dependencies as $dep) {
                    $depKey = $this->findServiceKey($dep);
                    if ($depKey !== null && ! in_array($depKey, $sorted, true) && in_array($depKey, $remaining, true)) {
                        $allDependenciesSatisfied = false;
                        break;
                    }
                }

                if ($allDependenciesSatisfied) {
                    $sorted[] = $serviceKey;
                    unset($remaining[$key]);
                    $added = true;
                }
            }

            // Prevent infinite loop - if nothing was added, add remaining services
            if (! $added) {
                $sorted = array_merge($sorted, array_values($remaining));
                break;
            }
        }

        return array_values(array_unique($sorted));
    }

    /**
     * Load the service registry from JSON file
     */
    private function loadRegistry(): void
    {
        $path = stub_path($this->registryPath);

        if (! file_exists($path)) {
            throw new RuntimeException("Service registry not found at: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Failed to read service registry: {$path}");
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse service registry JSON: ' . json_last_error_msg());
        }

        $this->registry = $decoded;
    }
}
