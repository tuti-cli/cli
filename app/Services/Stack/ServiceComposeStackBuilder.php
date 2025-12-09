<?php

declare(strict_types=1);

namespace App\Services\Stack;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final readonly class ServiceComposeStackBuilder
{
    public function __construct(
        private ServiceRegistryJsonReader $registry,
        private ServiceStubLoader         $stubLoader
    ) {}

    /**
     * Build a docker-compose.yml from selected services
     *
     * @param array<int, string> $selectedServices Array of service keys (e.g., ['databases. postgres', 'cache.redis'])
     * @param array<string, string> $projectConfig Project configuration
     * @param string $environment Environment (dev, staging, production)
     * @return array<string, mixed> Composed docker-compose array
     */
    public function build(
        array $selectedServices,
        array $projectConfig,
        string $environment = 'dev'
    ): array {
        // Start with base structure
        $compose = $this->getBaseStructure($projectConfig);

        // Add each selected service
        foreach ($selectedServices as $serviceKey) {
            $compose = $this->addService($compose, $serviceKey, $projectConfig, $environment);
        }

        return $compose;
    }

    /**
     * Get base docker-compose structure
     *
     * @param array<string, string> $projectConfig
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
     * @param array<string, mixed> $compose Current compose configuration
     * @param string $serviceKey Service key (e.g., 'databases.postgres')
     * @param array<string, string> $projectConfig Project configuration
     * @param string $environment Environment
     * @return array<string, mixed> Updated compose configuration
     */
    private function addService(
        array $compose,
        string $serviceKey,
        array $projectConfig,
        string $environment
    ): array {
        [$category, $serviceName] = $this->parseServiceKey($serviceKey);

        // Get service configuration from registry
        $serviceConfig = $this->registry->getService($category, $serviceName);

        // Load service stub
        $stubPath = $this->registry->getServiceStubPath($category, $serviceName);

        // Prepare replacements
        $replacements = $this->prepareReplacements($serviceConfig, $projectConfig, $environment);

        // Load and process stub
        $serviceYaml = $this->stubLoader->load($stubPath, $replacements);

        // Parse YAML
        /** @var array<string, mixed> $serviceParsed */
        $serviceParsed = Yaml::parse($serviceYaml);

        // Merge service into compose
        $compose['services'] = array_merge($compose['services'], $serviceParsed);

        // Add volumes if needed
        if (!  empty($serviceConfig['volumes'])) {
            $compose = $this->addVolumes($compose, $serviceConfig['volumes'], $projectConfig);
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
     * @param array<string, mixed> $serviceConfig
     * @param array<string, string> $projectConfig
     * @param string $environment
     * @return array<string, string>
     */
    private function prepareReplacements(
        array $serviceConfig,
        array $projectConfig,
        string $environment
    ): array {
        $projectName = $projectConfig['PROJECT_NAME'] ?? 'app';

        $replacements = [
            'NETWORK_NAME' => "{$projectName}_network",
            'PROJECT_NAME' => $projectName,
            'ENVIRONMENT' => $environment,
        ];

        // Add default variables from service config
        if (isset($serviceConfig['default_variables'])) {
            /** @var array<string, string> $defaultVars */
            $defaultVars = $serviceConfig['default_variables'];
            $replacements = array_merge($replacements, $defaultVars);
        }

        // Add environment-specific deploy config
        $replacements['DEPLOY_CONFIG'] = $this->getDeployConfig($environment);

        // Add Redis-specific configurations
        $replacements = $this->addRedisReplacements($replacements, $environment);

        return $replacements;
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
        cpus: '0.5'
        memory: 512M
YAML,
            'production' => <<<'YAML'
deploy:
    replicas:  1
    placement:
      constraints:
        - node.role == manager
    restart_policy:
      condition: on-failure
      delay: 5s
      max_attempts: 3
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
     * Add Redis-specific replacements based on environment
     *
     * @param array<string, string> $replacements
     * @return array<string, string>
     */
    private function addRedisReplacements(array $replacements, string $environment): array
    {
        // Default Redis configurations
        $replacements['REDIS_APPEND_ONLY'] = '--appendonly yes';
        $replacements['REDIS_MAX_MEMORY'] = '--maxmemory 256mb';
        $replacements['REDIS_EVICTION_POLICY'] = '--maxmemory-policy allkeys-lru';
        $replacements['REDIS_PASSWORD_CONFIG'] = '${REDIS_PASSWORD: +--requirepass $REDIS_PASSWORD}';

        // Environment-specific overrides
        if ($environment === 'production') {
            $replacements['REDIS_MAX_MEMORY'] = '--maxmemory 512mb';
        }

        return $replacements;
    }

    /**
     * Add volumes to compose configuration
     *
     * @param array<string, mixed> $compose
     * @param array<int, string> $volumes
     * @param array<string, string> $projectConfig
     * @return array<string, mixed>
     */
    private function addVolumes(array $compose, array $volumes, array $projectConfig): array
    {
        $projectName = $projectConfig['PROJECT_NAME'] ?? 'app';

        foreach ($volumes as $volume) {
            if (!  isset($compose['volumes'][$volume])) {
                $compose['volumes'][$volume] = [
                    'driver' => 'local',
                    'name' => "{$projectName}_{$volume}",
                ];
            }
        }

        return $compose;
    }

    /**
     * Convert compose array to YAML string
     *
     * @param array<string, mixed> $compose
     * @return string
     */
    public function toYaml(array $compose): string
    {
        return Yaml::dump($compose, 10, 2, Yaml::DUMP_OBJECT_AS_MAP);
    }

    /**
     * Write compose configuration to file
     *
     * @param array<string, mixed> $compose
     * @param string $outputPath
     * @return void
     */
    public function writeToFile(array $compose, string $outputPath): void
    {
        $yaml = $this->toYaml($compose);

        $result = file_put_contents($outputPath, $yaml);

        if ($result === false) {
            throw new RuntimeException("Failed to write compose file to: {$outputPath}");
        }
    }
}
