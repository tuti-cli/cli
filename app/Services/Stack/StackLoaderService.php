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
    /**
     * Load a stack manifest from a stack. json file
     *
     * @param string $stackPath Path to the stack directory
     * @return array<string, mixed>
     */
    public function load(string $stackPath): array
    {
        $manifestPath = rtrim($stackPath, '/') . '/stack.json';

        if (!  file_exists($manifestPath)) {
            throw new RuntimeException("Stack manifest not found at: {$manifestPath}");
        }

        $content = file_get_contents($manifestPath);

        if ($content === false) {
            throw new RuntimeException("Failed to read stack manifest: {$manifestPath}");
        }

        /** @var array<string, mixed> */
        return json_decode(
            json:  $content,
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );
    }

    /**
     * Get service overrides for a specific service
     *
     * @param array<string, mixed> $stackManifest
     * @param string $serviceKey Service key (e.g., 'cache.redis')
     * @return array<string, mixed>
     */
    public function getServiceOverrides(array $stackManifest, string $serviceKey): array
    {
        return $stackManifest['service_overrides'][$serviceKey] ?? [];
    }

    /**
     * Get environment-specific overrides for a service
     *
     * @param array<string, mixed> $stackManifest
     * @param string $serviceKey
     * @param string $environment
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
     * @param array<string, mixed> $stackManifest
     * @return array<string, array<string, mixed>>
     */
    public function getRequiredServices(array $stackManifest): array
    {
        return $stackManifest['required_services'] ?? [];
    }

    /**
     * Get optional services from stack manifest
     *
     * @param array<string, mixed> $stackManifest
     * @return array<string, array<string, mixed>>
     */
    public function getOptionalServices(array $stackManifest): array
    {
        return $stackManifest['optional_services'] ?? [];
    }

    /**
     * Get default services (required + default optional)
     *
     * @param array<string, mixed> $stackManifest
     * @return array<int, string>
     */
    public function getDefaultServices(array $stackManifest): array
    {
        $services = [];

        // Add required services (use defaults)
        foreach ($this->getRequiredServices($stackManifest) as $key => $config) {
            $category = $config['category'];
            $default = $config['default'];
            $services[] = "{$category}.{$default}";
        }

        // Add optional services that have defaults
        foreach ($this->getOptionalServices($stackManifest) as $key => $config) {
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
     * @param array<string, mixed> $stackManifest
     * @return bool
     */
    public function validate(array $stackManifest): bool
    {
        $required = ['name', 'version', 'type', 'framework'];

        foreach ($required as $field) {
            if (! isset($stackManifest[$field])) {
                throw new RuntimeException("Stack manifest missing required field: {$field}");
            }
        }

        return true;
    }

    /**
     * Get stack name
     *
     * @param array<string, mixed> $stackManifest
     */
    public function getStackName(array $stackManifest): string
    {
        return $stackManifest['name'];
    }

    /**
     * Get stack type (php, python, node, etc.)
     *
     * @param array<string, mixed> $stackManifest
     */
    public function getStackType(array $stackManifest): string
    {
        return $stackManifest['type'];
    }

    /**
     * Get framework name (laravel, wordpress, django, etc.)
     *
     * @param array<string, mixed> $stackManifest
     */
    public function getFramework(array $stackManifest): string
    {
        return $stackManifest['framework'];
    }
}
