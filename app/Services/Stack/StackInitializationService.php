<?php

declare(strict_types=1);

namespace App\Services\Stack;

use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use App\Services\Support\EnvFileService;
use App\Services\Support\EnvHandlers\BedrockEnvHandler;
use App\Services\Support\EnvHandlers\LaravelEnvHandler;
use App\Services\Support\EnvHandlers\WordPressEnvHandler;
use Exception;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Service StackInitializationService
 *
 * Handles the business logic for initializing a project from a stack template.
 * This service orchestrates:
 * 1. Directory creation
 * 2. Stack file copying
 * 3. Docker Compose generation
 * 4. Metadata creation
 * 5. Environment file configuration (delegated to stack-specific handlers)
 *
 * Following clean architecture, this service coordinates multiple infrastructure
 * services to complete the stack initialization workflow.
 */
final readonly class StackInitializationService
{
    public function __construct(
        private ProjectDirectoryService $directoryService,
        private ProjectMetadataService $metadataService,
        private StackFilesCopierService $copierService,
        private StackLoaderService $stackLoader,
        private StackStubLoaderService $stubLoader,
        private StackRegistryManagerService $registryManager,
        private EnvFileService $envService,
        private LaravelEnvHandler $laravelEnvHandler,
        private BedrockEnvHandler $bedrockEnvHandler,
        private WordPressEnvHandler $wordPressEnvHandler,
    ) {}

    /**
     * Initialize a project from a stack template.
     *
     * @param  array<int, string>  $selectedServices
     *
     * @throws RuntimeException If initialization fails
     */
    public function initialize(
        string $stackPath,
        string $projectName,
        string $environment,
        array $selectedServices
    ): bool {
        $this->directoryService->setInitializationRoot(getcwd());

        // 0. Load stack-specific service registry
        $this->registryManager->loadForStack($stackPath);

        // 1. Load and validate stack manifest
        $manifest = $this->stackLoader->load($stackPath);
        $this->stackLoader->validate($manifest);

        // 2. Create directory structure
        $this->directoryService->create();
        $this->directoryService->createSubDirectories();

        // 3. Copy stack files (including docker-compose.yml)
        $this->copierService->copyFromStack($stackPath);

        // 4. Create project metadata
        $config = $this->buildStackConfig(
            $manifest,
            $projectName,
            $environment,
            $selectedServices
        );
        $this->metadataService->create($config);

        // 5. Process docker-compose files with variable substitution
        $this->processDockerComposeFiles($projectName);

        // 6. Append optional services to docker-compose.yml
        $this->appendOptionalServices($selectedServices, $projectName, $environment);

        // 7. Configure environment file (delegated to stack-specific handlers)
        $this->configureEnvironmentFile($environment, $projectName, $selectedServices);

        // 8. Validate initialization
        if (! $this->directoryService->validate()) {
            throw new RuntimeException('Stack initialization validation failed');
        }

        return true;
    }

    /**
     * Configure environment file for the project.
     * Delegates to stack-specific handlers based on project type detection.
     *
     * @param  array<int, string>  $selectedServices
     */
    private function configureEnvironmentFile(string $environment, string $projectName, array $selectedServices): void
    {
        $projectRoot = $this->directoryService->getProjectRoot();

        // Detect which database was selected (if any)
        $selectedDatabase = $this->detectSelectedDatabase($selectedServices);

        // Detect project type and delegate to appropriate handler
        if ($this->laravelEnvHandler->detect($projectRoot)) {
            $this->laravelEnvHandler->configure($projectRoot, $projectName, [
                'has_redis' => in_array('cache.redis', $selectedServices, true),
                'php_version' => '8.4',
                'database' => $selectedDatabase,
            ]);

            return;
        }

        if ($this->bedrockEnvHandler->detect($projectRoot)) {
            $this->bedrockEnvHandler->configure($projectRoot, $projectName, [
                'php_version' => '8.3',
                'database' => $selectedDatabase,
            ]);

            return;
        }

        if ($this->wordPressEnvHandler->detect($projectRoot)) {
            $this->wordPressEnvHandler->configure($projectRoot, $projectName);

            return;
        }

        // Fallback: create .env from template for unknown project types
        $this->createEnvFromTemplateFallback($environment, $projectName);
    }

    /**
     * Detect which database service was selected.
     *
     * @param  array<int, string>  $selectedServices
     * @return string|null Database type (postgres, mysql, mariadb) or null if none selected
     */
    private function detectSelectedDatabase(array $selectedServices): ?string
    {
        foreach ($selectedServices as $service) {
            if (str_starts_with($service, 'databases.')) {
                return substr($service, strlen('databases.'));
            }
        }

        return null;
    }

    /**
     * Create .env file from template as fallback for unknown project types.
     */
    private function createEnvFromTemplateFallback(string $environment, string $projectName): void
    {
        $projectRoot = $this->directoryService->getProjectRoot();

        // Check if .env already exists
        if ($this->envService->exists($projectRoot)) {
            // Just append Tuti section
            $this->envService->appendTutiSection($projectRoot, $projectName);

            return;
        }

        // Try to create from template
        $tutiEnvPath = $this->directoryService->getTutiPath('environments');
        $templatePaths = [
            "{$tutiEnvPath}/.env.{$environment}.example",
            "{$tutiEnvPath}/.env.dev.example",
            "{$tutiEnvPath}/.env.example",
        ];

        foreach ($templatePaths as $templatePath) {
            if (file_exists($templatePath)) {
                $this->createEnvFromTemplate($templatePath, $projectRoot, $projectName);

                return;
            }
        }

        // Last resort: create minimal .env
        $this->createMinimalEnvFile($projectRoot, $projectName);
    }

    /**
     * Create .env file from template with project-specific replacements.
     */
    private function createEnvFromTemplate(string $templatePath, string $directory, string $projectName): void
    {
        $content = file_get_contents($templatePath);

        if ($content === false) {
            $this->createMinimalEnvFile($directory, $projectName);

            return;
        }

        if ($projectName !== '') {
            $appDomain = $projectName . '.local.test';
            $userId = $this->envService->read($directory) ? 1000 : 1000; // Placeholder, will be set by EnvFileService
            $groupId = $userId;

            // Use regex patterns to match any default value
            $patterns = [
                '/^PROJECT_NAME=.*$/m' => "PROJECT_NAME={$projectName}",
                '/^APP_DOMAIN=.*$/m' => "APP_DOMAIN={$appDomain}",
                '/^APP_URL=.*$/m' => "APP_URL=https://{$appDomain}",
                '/^APP_NAME=.*$/m' => "APP_NAME={$projectName}",
            ];

            foreach ($patterns as $pattern => $replace) {
                $content = preg_replace($pattern, $replace, (string) $content);
            }
        }

        $this->envService->write($directory, (string) $content);

        // Append Tuti section
        $this->envService->appendTutiSection($directory, $projectName);
    }

    /**
     * Create a minimal .env file when no template is available.
     */
    private function createMinimalEnvFile(string $directory, string $projectName): void
    {
        $appDomain = $projectName . '.local.test';

        $content = <<<ENV
# ============================================================================
# TUTI-CLI DEVELOPMENT ENVIRONMENT
# ============================================================================

# Project Configuration
PROJECT_NAME={$projectName}
APP_ENV=dev
APP_DOMAIN={$appDomain}

# Database Configuration
DB_HOST=database
DB_PORT=3306
DB_DATABASE=wordpress
DB_USERNAME=wordpress
DB_PASSWORD=secret

ENV;

        $this->envService->write($directory, $content);

        // Append Tuti section
        $this->envService->appendTutiSection($directory, $projectName);
    }

    /**
     * Process docker-compose files with variable substitution.
     */
    private function processDockerComposeFiles(string $projectName): void
    {
        $composeFile = tuti_path('docker-compose.yml');

        if (! file_exists($composeFile)) {
            return;
        }

        // Generate APP_DOMAIN from project name
        $appDomain = $projectName . '.local.test';

        // Replace variables in docker-compose.yml
        $content = file_get_contents($composeFile);

        $replacements = [
            '${PROJECT_NAME:-laravel}' => $projectName,
            '${PROJECT_NAME}' => $projectName,
            '${APP_DOMAIN:-app.local.test}' => $appDomain,
            '${APP_DOMAIN}' => $appDomain,
        ];

        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        file_put_contents($composeFile, $content);

        // Also process dev compose file if exists
        $devComposeFile = tuti_path('docker-compose.dev.yml');
        if (file_exists($devComposeFile)) {
            $content = file_get_contents($devComposeFile);
            foreach ($replacements as $search => $replace) {
                $content = str_replace($search, $replace, $content);
            }
            file_put_contents($devComposeFile, $content);
        }
    }

    /**
     * Append optional services to docker-compose.yml based on user selection.
     *
     * @param  array<int, string>  $selectedServices
     */
    private function appendOptionalServices(array $selectedServices, string $projectName, string $environment): void
    {
        $composeFile = tuti_path('docker-compose.yml');
        $devComposeFile = tuti_path('docker-compose.dev.yml');

        if (! file_exists($composeFile)) {
            return;
        }

        // All selected services are optional - the base compose only has the app service
        // No categories are excluded since databases are now optional too
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

            // Skip if service already exists in compose
            if (mb_strpos($content, "  {$serviceName}:") !== false) {
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
            $replacements = [
                'PROJECT_NAME' => $projectName,
                'NETWORK_NAME' => 'app_network',
                'APP_DOMAIN' => "{$projectName}.local.test",
            ];

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
                // Check if stub has sections
                if ($this->stubLoader->hasSections($stubPath)) {
                    // Load base section only
                    $serviceYaml = $this->stubLoader->loadSection($stubPath, 'base', $replacements);
                } else {
                    // Load entire stub (legacy format)
                    $serviceYaml = $this->stubLoader->load($stubPath, $replacements);
                }
                if ($serviceYaml === null) {
                    continue;
                }
                if (mb_trim($serviceYaml) === '') {
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
        $replacements = [
            'PROJECT_NAME' => $projectName,
            'NETWORK_NAME' => 'app_network',
            'APP_DOMAIN' => "{$projectName}.local.test",
        ];

        foreach ($optionalServices as $serviceKey) {
            [$category, $serviceName] = explode('.', $serviceKey);

            // Skip if service already exists in dev compose
            if (mb_strpos($devContent, "  {$serviceName}:") !== false) {
                continue;
            }

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
     * Find the correct insertion point for services (before networks section).
     * Services should be inserted at the end of the services block, before the networks section.
     */
    private function findServicesInsertionPoint(string $content): int
    {
        // Pattern 1: Look for the Networks section header with equals signs
        // The structure is:
        // # =============================================================================
        // # Networks
        // # =============================================================================
        // networks:
        $pattern1 = '/\n# =+\s*\n# Networks/i';
        if (preg_match($pattern1, $content, $matches, PREG_OFFSET_CAPTURE)) {
            // Return position of the newline before the header
            return $matches[0][1];
        }

        // Pattern 2: Look for standalone "networks:" at column 0 (top-level key)
        // Must NOT be indented (no spaces before it)
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
    private function appendVolumesToCompose(string $content, array $volumes): string
    {
        // Build volume entries to add
        $volumesToInsert = '';
        foreach ($volumes as $volumeName => $projectName) {
            // Check if volume already exists
            if (mb_strpos($content, "  {$volumeName}:") === false) {
                $volumesToInsert .= "  {$volumeName}:\n";
                $volumesToInsert .= "    name: {$projectName}_\${APP_ENV:-dev}_{$volumeName}\n";
            }
        }

        if ($volumesToInsert === '') {
            return $content;
        }

        // Check for empty volumes section: "volumes: {}"
        if (preg_match('/\nvolumes:\s*\{\s*\}/', $content)) {
            // Replace empty volumes with populated section
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
    private function indentServiceYaml(string $yaml): string
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

    /**
     * Build configuration structure for stack-based project.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<int, string>  $selectedServices
     * @return array<string, mixed>
     */
    private function buildStackConfig(
        array $manifest,
        string $projectName,
        string $environment,
        array $selectedServices
    ): array {
        return [
            'project' => [
                'name' => $projectName,
                'type' => $this->stackLoader->getStackType($manifest),
                'version' => '1.0.0',
            ],
            'stack' => [
                'name' => $this->stackLoader->getStackName($manifest),
                'version' => $manifest['version'],
            ],
            'environments' => [
                'current' => $environment,
                $environment => [
                    'services' => $this->groupServices($selectedServices),
                ],
            ],
            'initialized_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Group services by category.
     *
     * @param  array<int, string>  $selectedServices
     * @return array<string, array<int, string>>
     */
    private function groupServices(array $selectedServices): array
    {
        $grouped = [];

        foreach ($selectedServices as $serviceKey) {
            [$category, $service] = explode('.', (string) $serviceKey);
            $grouped[$category][] = $service;
        }

        return $grouped;
    }
}
