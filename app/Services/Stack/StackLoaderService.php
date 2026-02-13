<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;

/**
 * ServiceStackLoader is responsible for loading and processing
 * stack manifest files (stack.json).
 */
final readonly class StackLoaderService
{
    public function __construct(
        private \App\Services\Storage\JsonFileService $jsonService
    ) {}

    /**
     * Load a stack manifest from a stack.json file
     *
     * @param  string  $stackPath  Path to the stack directory
     * @return array<string, mixed>
     */
    public function load(string $stackPath): array
    {
        $manifestPath = mb_rtrim($stackPath, '/') . '/stack.json';

        try {
            return $this->jsonService->read($manifestPath);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to load stack manifest at {$manifestPath}: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get service overrides for a specific service
     *
     * @param  array<string, mixed>  $stackManifest
     * @param  string  $serviceKey  Service key (e.g., 'cache.redis')
     * @return array<string, mixed>
     */
    public function getServiceOverrides(array $stackManifest, string $serviceKey): array
    {
        return $stackManifest['service_overrides'][$serviceKey] ?? [];
    }

    /**
     * Get environment-specific overrides for a service
     *
     * @param  array<string, mixed>  $stackManifest
     * @return array<string, mixed>
     */
    public function getEnvironmentOverrides(
        array $stackManifest,
        string $serviceKey,
        string $environment
    ): array {
        $serviceOverrides = $this->getServiceOverrides($stackManifest, $serviceKey);

        return $serviceOverrides['environments'][$environment] ?? [];
    }

    /**
     * Get required services from stack manifest
     *
     * @param  array<string, mixed>  $stackManifest
     * @return array<string, array<string, mixed>>
     */
    public function getRequiredServices(array $stackManifest): array
    {
        return $stackManifest['required_services'] ?? [];
    }

    /**
     * Get optional services from stack manifest
     *
     * @param  array<string, mixed>  $stackManifest
     * @return array<string, array<string, mixed>>
     */
    public function getOptionalServices(array $stackManifest): array
    {
        return $stackManifest['optional_services'] ?? [];
    }

    /**
     * Get default services (required + default optional)
     *
     * @param  array<string, mixed>  $stackManifest
     * @return array<int, string>
     */
    public function getDefaultServices(array $stackManifest): array
    {
        $services = [];

        // Add required services (use defaults)
        foreach ($this->getRequiredServices($stackManifest) as $config) {
            $category = $config['category'];
            $default = $config['default'];
            $services[] = "{$category}.{$default}";
        }

        // Add optional services that have defaults
        foreach ($this->getOptionalServices($stackManifest) as $config) {
            if ($config['default'] !== null) {
                $category = $config['category'];
                $default = $config['default'];
                $services[] = "{$category}.{$default}";
            }
        }

        return $services;
    }

    /**
     * Validate stack manifest structure
     *
     * @param  array<string, mixed>  $stackManifest
     */
    public function validate(array $stackManifest): bool
    {
        $required = ['name', 'version', 'type', 'framework'];

        foreach ($required as $field) {
            if (! isset($stackManifest[$field])) {
                throw new RuntimeException("Stack manifest missing required field: {$field}");
            }
        }

        if (isset($stackManifest['required_services'])) {
            $this->validateServicesStructure($stackManifest['required_services'], 'required_services');
        }

        if (isset($stackManifest['optional_services'])) {
            $this->validateServicesStructure($stackManifest['optional_services'], 'optional_services');
        }

        if (isset($stackManifest['generated_variables'])) {
            $this->validateGeneratedVariables($stackManifest['generated_variables']);
        }

        return true;
    }

    /**
     * Get stack name
     *
     * @param  array<string, mixed>  $stackManifest
     */
    public function getStackName(array $stackManifest): string
    {
        $this->validate($stackManifest);

        return $stackManifest['name'];
    }

    /**
     * Get stack type (php, python, node, etc.)
     *
     * @param  array<string, mixed>  $stackManifest
     */
    public function getStackType(array $stackManifest): string
    {
        $this->validate($stackManifest);

        return $stackManifest['type'];
    }

    /**
     * Get framework name (laravel, wordpress, django, etc.)
     *
     * @param  array<string, mixed>  $stackManifest
     */
    public function getFramework(array $stackManifest): string
    {
        $this->validate($stackManifest);

        return $stackManifest['framework'];
    }

    /**
     * Validate service entries have required structure.
     *
     * @param  array<string, mixed>  $services
     */
    private function validateServicesStructure(array $services, string $sectionName): void
    {
        foreach ($services as $key => $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException("{$sectionName}.{$key} must be an array");
            }

            if (! isset($entry['category']) || ! is_string($entry['category'])) {
                throw new RuntimeException("{$sectionName}.{$key} must have a string 'category' field");
            }

            if (! isset($entry['options']) || ! is_array($entry['options'])) {
                throw new RuntimeException("{$sectionName}.{$key} must have an array 'options' field");
            }
        }
    }

    /**
     * Validate generated_variables entries have valid generators.
     *
     * @param  array<string, mixed>  $variables
     */
    private function validateGeneratedVariables(array $variables): void
    {
        $allowedGenerators = ['secure_random', 'laravel_key'];

        foreach ($variables as $key => $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException("generated_variables.{$key} must be an array");
            }

            if (! isset($entry['generator'])) {
                throw new RuntimeException("generated_variables.{$key} must have a 'generator' field");
            }

            if (! in_array($entry['generator'], $allowedGenerators, true)) {
                throw new RuntimeException(
                    "generated_variables.{$key} has invalid generator '{$entry['generator']}'. Allowed: " . implode(', ', $allowedGenerators)
                );
            }
        }
    }
}
