<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;

/**
 * StackRegistryManagerService is responsible for reading and managing
 * the service registry.json file for each stack.
 *
 * Each stack has its own services/registry.json that defines available services.
 */
final class StackRegistryManagerService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $registryCache = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $currentRegistry = null;

    /**
     * @var string|null
     */
    private ?string $currentStackPath = null;

    /**
     * Load registry for a specific stack.
     *
     * @param  string  $stackPath  Path to the stack directory
     */
    public function loadForStack(string $stackPath): void
    {
        $this->currentStackPath = $stackPath;

        if (isset($this->registryCache[$stackPath])) {
            $this->currentRegistry = $this->registryCache[$stackPath];
            return;
        }

        $registryPath = $stackPath . '/services/registry.json';

        if (! file_exists($registryPath)) {
            throw new RuntimeException("Service registry not found at: {$registryPath}");
        }

        $content = file_get_contents($registryPath);

        if ($content === false) {
            throw new RuntimeException("Failed to read service registry: {$registryPath}");
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to parse service registry JSON: ' . json_last_error_msg());
        }

        $this->validateRegistry($decoded, $registryPath);

        $this->registryCache[$stackPath] = $decoded;
        $this->currentRegistry = $decoded;
    }

    /**
     * Validate registry structure after loading.
     *
     * @param  array<string, mixed>  $registry
     */
    private function validateRegistry(array $registry, string $path): void
    {
        if (! isset($registry['version'])) {
            throw new RuntimeException("Service registry missing 'version' key: {$path}");
        }

        if (! isset($registry['services']) || ! is_array($registry['services'])) {
            throw new RuntimeException("Service registry missing 'services' key: {$path}");
        }

        foreach ($registry['services'] as $category => $services) {
            foreach ($services as $serviceName => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                if (! isset($entry['name'])) {
                    throw new RuntimeException("Service '{$category}.{$serviceName}' missing 'name' field in: {$path}");
                }

                if (! isset($entry['stub'])) {
                    throw new RuntimeException("Service '{$category}.{$serviceName}' missing 'stub' field in: {$path}");
                }
            }
        }
    }

    /**
     * Get the registry version
     */
    public function getVersion(): string
    {
        $this->ensureRegistryLoaded();
        return $this->currentRegistry['version'] ?? '0.0.0';
    }

    /**
     * Get all services from the registry
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getAllServices(): array
    {
        $this->ensureRegistryLoaded();
        return $this->currentRegistry['services'] ?? [];
    }

    /**
     * Get services by category
     *
     * @return array<string, array<string, mixed>>
     */
    public function getServicesByCategory(string $category): array
    {
        $this->ensureRegistryLoaded();
        return $this->currentRegistry['services'][$category] ?? [];
    }

    /**
     * Get a specific service configuration
     *
     * @return array<string, mixed>
     */
    public function getService(string $category, string $serviceName): array
    {
        $this->ensureRegistryLoaded();
        $service = $this->currentRegistry['services'][$category][$serviceName] ?? null;

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
        $this->ensureRegistryLoaded();
        return isset($this->currentRegistry['services'][$category][$serviceName]);
    }

    /**
     * Get the stub path for a service (returns absolute path).
     *
     * @param  string  $category  Service category (e.g., 'databases')
     * @param  string  $serviceName  Service name (e.g., 'postgres')
     * @return string  Absolute path to stub file
     */
    public function getServiceStubPath(string $category, string $serviceName): string
    {
        $this->ensureRegistryLoaded();

        $service = $this->getService($category, $serviceName);
        $stubRelativePath = $service['stub'] ?? "{$category}/{$serviceName}.stub";

        // Return absolute path within current stack
        return $this->currentStackPath . '/services/' . $stubRelativePath;
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
        $this->ensureRegistryLoaded();
        return array_keys($this->currentRegistry['services'] ?? []);
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
        $this->ensureRegistryLoaded();

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
     * Ensure a registry is loaded
     */
    private function ensureRegistryLoaded(): void
    {
        if ($this->currentRegistry === null) {
            throw new RuntimeException(
                'No stack registry loaded. Call loadForStack() first.'
            );
        }
    }
}
