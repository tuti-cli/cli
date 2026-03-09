<?php

declare(strict_types=1);

namespace App\Services\Stack;

use App\Enums\ContainerNamingEnum;
use Exception;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * OptionalServicesBuilder handles appending optional services to Docker Compose files.
 *
 * This service is responsible for:
 * - Loading service stubs from the registry
 * - Building service YAML with variable replacements
 * - Appending services to docker-compose.yml and docker-compose.dev.yml
 * - Managing volumes for services
 */
final readonly class OptionalServicesBuilder
{
    public function __construct(
        private StackRegistryManagerService $registryManager,
        private StackStubLoaderService $stubLoader,
    ) {}

    /**
     * Append optional services to docker-compose.yml based on user selection.
     *
     * @param  array<int, string>  $selectedServices  Service keys (e.g., ['databases.postgres', 'cache.redis'])
     * @param  string  $projectName  Project name for variable replacements
     * @param  string  $environment  Environment (dev, staging, production)
     * @param  string  $composeFile  Path to docker-compose.yml
     * @param  string  $devComposeFile  Path to docker-compose.dev.yml
     */
    public function appendServices(
        array $selectedServices,
        string $projectName,
        string $environment,
        string $composeFile,
        string $devComposeFile
    ): void {
        if (! file_exists($composeFile)) {
            return;
        }

        // All selected services are optional - the base compose only has the app service
        $optionalServices = $selectedServices;

        if ($optionalServices === []) {
            return;
        }

        $content = file_get_contents($composeFile);

        // Find insertion point: look for "# ====...Networks" or just "networks:" section
        $insertionPoint = $this->findServicesInsertionPoint($content);

        $servicesToAppend = '';
        $volumesToAdd = [];

        foreach ($optionalServices as $serviceKey) {
            [$category, $serviceName] = explode('.', $serviceKey);

            // Check if service exists in registry
            if (! $this->registryManager->hasService($category, $serviceName)) {
                continue;
            }

            // Get service config from registry
            $serviceConfig = $this->registryManager->getService($category, $serviceName);

            // Get stub path and load it
            $stubPath = $this->registryManager->getServiceStubPath($category, $serviceName);

            if (! file_exists($stubPath)) {
                continue;
            }

            // Build replacements: base + service defaults
            $replacements = $this->buildReplacements($projectName);

            // Add default variables from service registry
            if (isset($serviceConfig['default_variables'])) {
                $replacements = array_merge($replacements, $serviceConfig['default_variables']);
            }

            // Add environment-specific variables
            if ($serviceName === 'redis') {
                $replacements['REDIS_MAX_MEMORY'] = match ($environment) {
                    'dev' => '256mb',
                    'staging' => '512mb',
                    'production' => '1024mb',
                    default => '256mb',
                };
            }

            try {
                $serviceYaml = $this->loadServiceYaml($stubPath, $replacements);

                if ($serviceYaml === null) {
                    continue;
                }

                // Extract actual service name from stub YAML
                // The registry key (e.g., 'mariadb') may differ from the actual service name (e.g., 'database')
                $actualServiceName = $this->extractServiceName($serviceYaml);

                if ($actualServiceName === null) {
                    continue;
                }

                // Skip if service already exists in compose (using actual service name from stub)
                if (mb_strpos($content, "  {$actualServiceName}:") !== false) {
                    continue;
                }

                // Indent and add service YAML
                $indentedYaml = $this->indentServiceYaml($serviceYaml);
                $servicesToAppend .= "\n" . $indentedYaml;

                // Collect volumes from service config
                if (! empty($serviceConfig['volumes'])) {
                    foreach ($serviceConfig['volumes'] as $volume) {
                        $volumesToAdd[$volume] = $projectName;
                    }
                }
            } catch (Exception) {
                // Skip if stub loading fails - service won't be added
                continue;
            }
        }

        if ($servicesToAppend === '') {
            return;
        }

        // Insert services before networks section
        $newContent = mb_substr($content, 0, $insertionPoint) . $servicesToAppend . "\n" . mb_substr($content, $insertionPoint);

        // Add volumes if any
        if ($volumesToAdd !== []) {
            $newContent = $this->appendVolumesToCompose($newContent, $volumesToAdd);
        }

        $this->validateYaml($newContent, $composeFile);
        file_put_contents($composeFile, $newContent);

        // Also append dev sections to docker-compose.dev.yml
        $this->appendDevSectionsToDevCompose($optionalServices, $projectName, $devComposeFile);
    }

    /**
     * Build base replacements for variable substitution.
     *
     * @return array<string, string>
     */
    public function buildReplacements(string $projectName): array
    {
        return [
            'PROJECT_NAME' => $projectName,
            'NETWORK_NAME' => ContainerNamingEnum::DEFAULT_NETWORK,
            'APP_DOMAIN' => "{$projectName}.local.test",
        ];
    }

    /**
     * Find the correct insertion point for services (before networks section).
     * Services should be inserted at the end of the services block, before the networks section.
     */
    public function findServicesInsertionPoint(string $content): int
    {
        // Pattern 1: Look for the Networks section header with equals signs
        $pattern1 = '/\n# =+\s*\n# Networks/i';
        if (preg_match($pattern1, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $matches[0][1];
        }

        // Pattern 2: Look for standalone "networks:" at column 0 (top-level key)
        $pattern2 = '/\nnetworks:\s*$/m';
        if (preg_match($pattern2, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $matches[0][1];
        }

        // Pattern 3: Find the volumes section as fallback (insert before volumes if no networks)
        $pattern3 = '/\n# =+\s*\n# Volumes/i';
        if (preg_match($pattern3, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $matches[0][1];
        }

        // Pattern 4: Look for volumes: key directly
        $pattern4 = '/\nvolumes:\s*$/m';
        if (preg_match($pattern4, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $matches[0][1];
        }

        // Last resort: append at the end of file
        return mb_strlen($content);
    }

    /**
     * Append volumes to the volumes section of docker-compose.yml
     *
     * @param  array<string, string>  $volumes  Map of volume name => project name
     */
    public function appendVolumesToCompose(string $content, array $volumes): string
    {
        // Build volume entries to add
        $volumesToInsert = '';
        foreach ($volumes as $volumeName => $projectName) {
            // Check if volume already exists
            if (mb_strpos($content, "  {$volumeName}:") === false) {
                $volumesToInsert .= "  {$volumeName}:\n";
                $volumesToInsert .= '    name: ' . ContainerNamingEnum::Volume->withEnvVar($projectName, $volumeName) . "\n";
            }
        }

        if ($volumesToInsert === '') {
            return $content;
        }

        // Check for empty volumes section: "volumes: {}"
        if (preg_match('/\nvolumes:\s*\{\s*\}/', $content)) {
            $volumeSection = "volumes:\n" . $volumesToInsert;

            return preg_replace('/\nvolumes:\s*\{\s*\}/', "\n" . $volumeSection, $content);
        }

        // Find the volumes section (non-empty)
        $volumesPos = mb_strpos($content, "\nvolumes:");
        if ($volumesPos === false) {
            // No volumes section, add it at the end
            $volumeSection = "\nvolumes:\n" . $volumesToInsert;

            return $content . $volumeSection;
        }

        // Append to existing volumes section at end of file
        return mb_rtrim($content) . "\n" . $volumesToInsert;
    }

    /**
     * Indent service YAML to match docker-compose.yml structure.
     * Services in docker-compose are at 2-space indent under 'services:'.
     */
    public function indentServiceYaml(string $yaml): string
    {
        $lines = explode("\n", mb_trim($yaml));
        $result = [];

        foreach ($lines as $line) {
            if ($line === '') {
                $result[] = '';

                continue;
            }

            // Add 2-space indent for all lines
            $result[] = '  ' . $line;
        }

        return implode("\n", $result);
    }

    /**
     * Load service YAML from stub file.
     *
     * @param  array<string, string>  $replacements
     */
    private function loadServiceYaml(string $stubPath, array $replacements): ?string
    {
        // Check if stub has sections
        if ($this->stubLoader->hasSections($stubPath)) {
            // Load base section only
            $serviceYaml = $this->stubLoader->loadSection($stubPath, 'base', $replacements);
        } else {
            // Load entire stub (legacy format)
            $serviceYaml = $this->stubLoader->load($stubPath, $replacements);
        }

        if ($serviceYaml === null || mb_trim($serviceYaml) === '') {
            return null;
        }

        return $serviceYaml;
    }

    /**
     * Append dev sections of optional services to docker-compose.dev.yml.
     *
     * @param  array<int, string>  $optionalServices
     */
    private function appendDevSectionsToDevCompose(array $optionalServices, string $projectName, string $devComposeFile): void
    {
        if (! file_exists($devComposeFile)) {
            return;
        }

        $devContent = file_get_contents($devComposeFile);
        $devServicesToAppend = '';

        // Build replacements for dev section
        $replacements = $this->buildReplacements($projectName);

        foreach ($optionalServices as $serviceKey) {
            [$category, $serviceName] = explode('.', $serviceKey);

            // Check if service exists in registry
            if (! $this->registryManager->hasService($category, $serviceName)) {
                continue;
            }

            // Get stub path and load dev section
            $stubPath = $this->registryManager->getServiceStubPath($category, $serviceName);

            try {
                if ($this->stubLoader->hasSections($stubPath)) {
                    $devSection = $this->stubLoader->loadSection($stubPath, 'dev', $replacements);

                    if ($devSection !== null && mb_trim($devSection) !== '') {
                        // Extract actual service name from dev section
                        $actualServiceName = $this->extractServiceName($devSection);

                        if ($actualServiceName === null) {
                            continue;
                        }

                        // Skip if service already exists in dev compose (using actual service name)
                        if (mb_strpos($devContent, "  {$actualServiceName}:") !== false) {
                            continue;
                        }

                        $indentedYaml = $this->indentServiceYaml($devSection);
                        $devServicesToAppend .= "\n" . $indentedYaml;
                    }
                }
            } catch (Exception) {
                // Skip if dev section loading fails
                continue;
            }
        }

        if ($devServicesToAppend === '') {
            return;
        }

        // Find insertion point in dev compose (before networks or at end of services)
        $devInsertionPoint = $this->findServicesInsertionPoint($devContent);

        $devContent = mb_substr($devContent, 0, $devInsertionPoint) . $devServicesToAppend . "\n" . mb_substr($devContent, $devInsertionPoint);

        $this->validateYaml($devContent, $devComposeFile);
        file_put_contents($devComposeFile, $devContent);
    }

    /**
     * Extract the service name from a YAML stub.
     * Returns the first top-level key in the YAML, which represents the actual service name.
     *
     * @return string|null The service name, or null if YAML is invalid or empty
     */
    private function extractServiceName(string $yaml): ?string
    {
        if (mb_trim($yaml) === '') {
            return null;
        }

        try {
            $parsed = Yaml::parse($yaml);

            if (! is_array($parsed) || $parsed === []) {
                return null;
            }

            // Return the first top-level key (the actual service name)
            $keys = array_keys($parsed);

            return $keys[0];
        } catch (ParseException) {
            return null;
        }
    }

    /**
     * Validate that generated YAML content is parseable.
     */
    private function validateYaml(string $content, string $filePath): void
    {
        try {
            Yaml::parse($content);
        } catch (ParseException $e) {
            throw new RuntimeException("Generated invalid YAML for {$filePath}: {$e->getMessage()}", $e->getCode(), $e);
        }
    }
}
