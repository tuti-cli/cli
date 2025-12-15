<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final readonly class StackComposeBuilderService
{
    public function __construct(
        private StackRegistryManagerService $registry,
        private StackStubLoaderService      $stubLoader,
        private StackLoaderService          $stackLoader
    ) {}

    /**
     * Build a docker-compose.yml from selected services with stack overrides
     *
     * @param  string  $stackPath  Path to stack directory
     * @param  array<int, string>  $selectedServices  Array of service keys
     * @param  array<string, string>  $projectConfig  Project configuration
     * @param  string  $environment  Environment (dev, staging, production)
     * @return array<string, mixed> Composed docker-compose array
     */
    public function buildWithStack(
        string $stackPath,
        array $selectedServices,
        array $projectConfig,
        string $environment = 'dev'
    ): array {
        // Load stack manifest
        $stackManifest = $this->stackLoader->load($stackPath);

        // Validate manifest
        $this->stackLoader->validate($stackManifest);

        // Build with stack overrides
        return $this->build($selectedServices, $projectConfig, $environment, $stackManifest);
    }

    /**
     * Build a docker-compose.yml from selected services
     *
     * @param  array<int, string>  $selectedServices  Array of service keys
     * @param  array<string, string>  $projectConfig  Project configuration
     * @param  string  $environment  Environment (dev, staging, production)
     * @param  array<string, mixed>|null  $stackManifest  Optional stack manifest for overrides
     * @return array<string, mixed> Composed docker-compose array
     */
    public function build(
        array $selectedServices,
        array $projectConfig,
        string $environment = 'dev',
        ?array $stackManifest = null
    ): array {
        // Start with base structure
        $compose = $this->getBaseStructure($projectConfig);

        // Add each selected service
        foreach ($selectedServices as $serviceKey) {
            $compose = $this->addService(
                $compose,
                $serviceKey,
                $projectConfig,
                $environment,
                $stackManifest
            );
        }

        return $compose;
    }

    /**
     * Convert compose array to YAML string
     *
     * @param  array<string, mixed>  $compose
     */
    public function toYaml(array $compose): string
    {
        return Yaml::dump($compose, 10, 2, Yaml::DUMP_OBJECT_AS_MAP);
    }

    /**
     * Write compose configuration to file
     *
     * @param  array<string, mixed>  $compose
     */
    public function writeToFile(array $compose, string $outputPath): void
    {
        $yaml = $this->toYaml($compose);

        $result = file_put_contents($outputPath, $yaml);

        if ($result === false) {
            throw new RuntimeException("Failed to write compose file to:  {$outputPath}");
        }
    }

    /**
     * Get base docker-compose structure
     *
     * @param  array<string, string>  $projectConfig
     * @return array<string, mixed>
     */
    private function getBaseStructure(array $projectConfig): array
    {
        $projectName = $projectConfig['PROJECT_NAME'] ?? 'app';

        return [
            'services' => [],
            'networks' => [
                "{$projectName}_network" => [
                    'driver' => 'bridge',
                ],
            ],
            'volumes' => [],
        ];
    }

    /**
     * Add a service to the compose configuration
     *
     * @param  array<string, mixed>  $compose  Current compose configuration
     * @param  string  $serviceKey  Service key (e.g., 'databases.postgres')
     * @param  array<string, string>  $projectConfig  Project configuration
     * @param  string  $environment  Environment
     * @param  array<string, mixed>|null  $stackManifest  Stack manifest
     * @return array<string, mixed> Updated compose configuration
     */
    private function addService(
        array $compose,
        string $serviceKey,
        array $projectConfig,
        string $environment,
        ?array $stackManifest = null
    ): array {
        [$category, $serviceName] = $this->parseServiceKey($serviceKey);

        // Get service configuration from registry
        $serviceConfig = $this->registry->getService($category, $serviceName);

        // Get stack overrides if available
        $stackOverrides = $stackManifest
            ? $this->stackLoader->getServiceOverrides($stackManifest, $serviceKey)
            : [];

        $environmentOverrides = $stackManifest
            ? $this->stackLoader->getEnvironmentOverrides($stackManifest, $serviceKey, $environment)
            : [];

        // Load service stub
        $stubPath = $this->registry->getServiceStubPath($category, $serviceName);

        // Prepare replacements (base + stack + environment)
        $replacements = $this->prepareReplacements(
            $serviceKey,
            $serviceConfig,
            $projectConfig,
            $environment,
            $stackOverrides,
            $environmentOverrides
        );

        // Load and process stub
        $serviceYaml = $this->stubLoader->load($stubPath, $replacements);

        // Parse YAML
        /** @var array<string, mixed>|null $serviceParsed */
        $serviceParsed = Yaml::parse($serviceYaml);

        if ($serviceParsed === null) {
            throw new RuntimeException(
                "Failed to parse YAML for service {$serviceKey}. Check stub file and replacements."
            );
        }

        // Apply resource overrides to the parsed service
        if (! empty($environmentOverrides['resources'])) {
            $serviceParsed = $this->applyResourceOverrides(
                $serviceParsed,
                $serviceName,
                $environmentOverrides['resources']
            );
        }

        // Apply to deploy overrides
        if (! empty($environmentOverrides['deploy'])) {
            $serviceParsed = $this->applyDeployOverrides(
                $serviceParsed,
                $serviceName,
                $environmentOverrides['deploy']
            );
        }

        // Merge service into compose
        $compose['services'] = array_merge($compose['services'], $serviceParsed);

        // Add volumes if needed
        if (! empty($serviceConfig['volumes'])) {
            return $this->addVolumes($compose, $serviceConfig['volumes'], $projectConfig);
        }

        return $compose;
    }

    /**
     * Parse service key into category and service name
     *
     * @return array{0: string, 1: string}
     */
    private function parseServiceKey(string $serviceKey): array
    {
        $parts = explode('.', $serviceKey);

        if (count($parts) !== 2) {
            throw new RuntimeException(
                "Invalid service key format: {$serviceKey}. Expected format: 'category.service'"
            );
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Prepare placeholder replacements for stub
     *
     * @param  string  $serviceKey  Service key (e.g., 'cache.redis')
     * @param  array<string, mixed>  $serviceConfig
     * @param  array<string, string>  $projectConfig
     * @param  array<string, mixed>  $stackOverrides
     * @param  array<string, mixed>  $environmentOverrides
     * @return array<string, string>
     */
    private function prepareReplacements(
        string $serviceKey,
        array $serviceConfig,
        array $projectConfig,
        string $environment,
        array $stackOverrides = [],
        array $environmentOverrides = []
    ): array {
        $projectName = $projectConfig['PROJECT_NAME'] ?? 'app';
        [$category, $serviceName] = $this->parseServiceKey($serviceKey);

        $replacements = [
            'NETWORK_NAME' => "{$projectName}_network",
            'PROJECT_NAME' => $projectName,
            'ENVIRONMENT' => $environment,
        ];

        // Layer 1: Base service defaults from registry
        if (isset($serviceConfig['default_variables'])) {
            /** @var array<string, string> $defaultVars */
            $defaultVars = $serviceConfig['default_variables'];
            $replacements = array_merge($replacements, $defaultVars);
        }

        // Layer 2: Stack-level overrides
        if (isset($stackOverrides['variables'])) {
            /** @var array<string, string> $stackVars */
            $stackVars = $stackOverrides['variables'];
            $replacements = array_merge($replacements, $stackVars);
        }

        // Layer 3: Environment-specific overrides
        if (isset($environmentOverrides['variables'])) {
            /** @var array<string, string> $envVars */
            $envVars = $environmentOverrides['variables'];
            $replacements = array_merge($replacements, $envVars);
        }

        // Add service-specific configurations based on service name
        if ($serviceName === 'redis') {
            $replacements = $this->addRedisReplacements($replacements, $environment, $stackOverrides);
        }

        // Add environment-specific deploy config
        $replacements['DEPLOY_CONFIG'] = $this->getDeployConfig($environment);

        return $replacements;
    }

    /**
     * Add Redis-specific replacements based on environment
     *
     * @param  array<string, string>  $replacements
     * @param  array<string, mixed>  $stackOverrides
     * @return array<string, string>
     */
    private function addRedisReplacements(
        array $replacements,
        string $environment,
        array $stackOverrides = []
    ): array {
        // Check if stack provides Redis config (Laravel style)
        if (isset($stackOverrides['variables'])) {
            // Stack provides its own Redis configuration - already merged
            // But ensure command parts are set if missing
            if (! isset($replacements['REDIS_APPEND_ONLY'])) {
                $replacements['REDIS_APPEND_ONLY'] = '--appendonly yes';
            }
            if (! isset($replacements['REDIS_EVICTION_POLICY'])) {
                $replacements['REDIS_EVICTION_POLICY'] = '--maxmemory-policy allkeys-lru';
            }
            if (! isset($replacements['REDIS_PASSWORD_CONFIG'])) {
                $replacements['REDIS_PASSWORD_CONFIG'] = '${REDIS_PASSWORD: +--requirepass $REDIS_PASSWORD}';
            }
        } else {
            // No stack overrides - use defaults
            $replacements['REDIS_APPEND_ONLY'] = '--appendonly yes';
            $replacements['REDIS_EVICTION_POLICY'] = '--maxmemory-policy allkeys-lru';
            $replacements['REDIS_PASSWORD_CONFIG'] = '${REDIS_PASSWORD:+--requirepass $REDIS_PASSWORD}';
        }

        // Set memory based on environment (can be overridden by stack)
        if (! isset($replacements['REDIS_MAX_MEMORY'])) {
            $replacements['REDIS_MAX_MEMORY'] = match ($environment) {
                'dev' => '--maxmemory 256mb',
                'staging' => '--maxmemory 512mb',
                'production' => '--maxmemory 1024mb',
                default => '--maxmemory 256mb',
            };
        }

        return $replacements;
    }

    /**
     * Apply resource overrides to service configuration
     *
     * @param  array<string, mixed>  $serviceParsed
     * @param  array<string, mixed>  $resources
     * @return array<string, mixed>
     */
    private function applyResourceOverrides(
        array $serviceParsed,
        string $serviceName,
        array $resources
    ): array {
        if (! isset($serviceParsed[$serviceName]['deploy'])) {
            $serviceParsed[$serviceName]['deploy'] = [];
        }

        if (! isset($serviceParsed[$serviceName]['deploy']['resources'])) {
            $serviceParsed[$serviceName]['deploy']['resources'] = [];
        }

        // Deep merge resources without array_merge_recursive to avoid array conversion
        $serviceParsed[$serviceName]['deploy']['resources'] = $this->deepMerge(
            $serviceParsed[$serviceName]['deploy']['resources'],
            $resources
        );

        return $serviceParsed;
    }

    /**
     * Apply deploy overrides to service configuration
     *
     * @param  array<string, mixed>  $serviceParsed
     * @param  array<string, mixed>  $deploy
     * @return array<string, mixed>
     */
    private function applyDeployOverrides(
        array $serviceParsed,
        string $serviceName,
        array $deploy
    ): array {
        if (! isset($serviceParsed[$serviceName]['deploy'])) {
            $serviceParsed[$serviceName]['deploy'] = [];
        }

        // Deep merge deploy config
        $serviceParsed[$serviceName]['deploy'] = $this->deepMerge(
            $serviceParsed[$serviceName]['deploy'],
            $deploy
        );

        return $serviceParsed;
    }

    /**
     * Deep merge arrays without converting scalar values to arrays
     *
     * @param  array<string, mixed>  $array1
     * @param  array<string, mixed>  $array2
     * @return array<string, mixed>
     */
    private function deepMerge(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                // Both are arrays - merge recursively
                $merged[$key] = $this->deepMerge($merged[$key], $value);
            } else {
                // Override with new value (don't convert to array)
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Get deploy configuration based on environment
     */
    private function getDeployConfig(string $environment): string
    {
        return match ($environment) {
            'dev' => <<<'YAML'
deploy:
    replicas: 1
    restart_policy:
      condition: on-failure
      delay: 5s
      max_attempts: 3
YAML,
            'staging' => <<<'YAML'
deploy:
    replicas: 2
    restart_policy:
      condition: on-failure
      delay: 5s
      max_attempts: 3
    resources:
      limits:
        cpus: '1'
        memory: 1024M
      reservations:
        cpus:  '0.5'
        memory: 512M
YAML,
            'production' => <<<'YAML'
deploy:
    replicas:  1
    placement:
      constraints:
        - node.role == manager
    restart_policy:
      condition:  on-failure
      delay: 5s
      max_attempts:  3
    resources:
      limits:
        cpus: '2'
        memory: 2048M
      reservations:
        cpus: '1'
        memory: 1024M
YAML,
            default => '',
        };
    }

    /**
     * Add volumes to compose configuration
     *
     * @param  array<string, mixed>  $compose
     * @param  array<int, string>  $volumes
     * @param  array<string, string>  $projectConfig
     * @return array<string, mixed>
     */
    private function addVolumes(array $compose, array $volumes, array $projectConfig): array
    {
        $projectName = $projectConfig['PROJECT_NAME'] ?? 'app';

        foreach ($volumes as $volume) {
            if (! isset($compose['volumes'][$volume])) {
                $compose['volumes'][$volume] = [
                    'driver' => 'local',
                    'name' => "{$projectName}_{$volume}",
                ];
            }
        }

        return $compose;
    }
}
