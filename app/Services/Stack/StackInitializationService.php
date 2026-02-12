<?php

declare(strict_types=1);

namespace App\Services\Stack;

use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
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
        private StackRegistryManagerService $registryManager
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

        // 7. Copy environment file template with project-specific values (for docker-compose)
        $this->copyEnvironmentFile($environment, $projectName);

        // 8. Update project's .env with Docker service settings (Laravel-specific)
        // Only apply for Laravel projects (detected by artisan file)
        $this->updateProjectEnv($projectName, $selectedServices);

        // 9. Validate initialization
        if (! $this->directoryService->validate()) {
            throw new RuntimeException('Stack initialization validation failed');
        }

        return true;
    }

    /**
     * Update project's .env file with Docker service connection settings.
     * Only applies Laravel-specific settings if it's a Laravel project.
     *
     * @param  array<int, string>  $selectedServices
     */
    private function updateProjectEnv(string $projectName, array $selectedServices): void
    {
        $projectRoot = $this->directoryService->getProjectRoot();
        $envFile = $projectRoot . '/.env';

        if (! file_exists($envFile)) {
            return;
        }

        // Detect if this is a Laravel project (has artisan file)
        $isLaravel = file_exists($projectRoot . '/artisan');

        if (! $isLaravel) {
            // For non-Laravel projects (WordPress), don't override database settings
            // The .env template already has correct values
            return;
        }

        // Laravel-specific .env updates
        $content = file_get_contents($envFile);

        // Check if Redis is selected
        $hasRedis = in_array('cache.redis', $selectedServices, true);

        // Update database settings to use Docker postgres (Laravel default)
        $replacements = [
            '/^DB_CONNECTION=.*$/m' => 'DB_CONNECTION=pgsql',
            '/^DB_HOST=.*$/m' => 'DB_HOST=postgres',
            '/^DB_PORT=.*$/m' => 'DB_PORT=5432',
            '/^DB_DATABASE=.*$/m' => 'DB_DATABASE=laravel',
            '/^DB_USERNAME=.*$/m' => 'DB_USERNAME=laravel',
            '/^DB_PASSWORD=.*$/m' => 'DB_PASSWORD=secret',
            // Update app URL
            '/^APP_URL=.*$/m' => "APP_URL=https://{$projectName}.local.test",
        ];

        // Add Redis settings only if Redis is selected
        if ($hasRedis) {
            $replacements['/^REDIS_HOST=.*$/m'] = 'REDIS_HOST=redis';
            $replacements['/^REDIS_PASSWORD=.*$/m'] = 'REDIS_PASSWORD=';
            $replacements['/^CACHE_STORE=.*$/m'] = 'CACHE_STORE=redis';
            $replacements['/^SESSION_DRIVER=.*$/m'] = 'SESSION_DRIVER=redis';
            $replacements['/^QUEUE_CONNECTION=.*$/m'] = 'QUEUE_CONNECTION=redis';
        }

        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        file_put_contents($envFile, $content);
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

        // Categories that are already in the base docker-compose.yml
        $baseCategories = ['databases'];

        // Filter to only optional services (cache, mail, workers, search, storage, etc.)
        $optionalServices = array_filter($selectedServices, function (string $serviceKey) use ($baseCategories): bool {
            [$category] = explode('.', $serviceKey);

            return ! in_array($category, $baseCategories, true);
        });

        if ($optionalServices === []) {
            return;
        }

        $content = file_get_contents($composeFile);

        // Find insertion point: look for "# ====...Networks" or just "networks:" section
        $insertionPoint = $this->findServicesInsertionPoint($content);

        if ($insertionPoint === false) {
            return;
        }

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

        if ($devInsertionPoint === false) {
            // Append at end of file
            $devContent = mb_rtrim($devContent) . "\n" . $devServicesToAppend . "\n";
        } else {
            $devContent = mb_substr($devContent, 0, $devInsertionPoint) . $devServicesToAppend . "\n" . mb_substr($devContent, $devInsertionPoint);
        }

        $this->validateYaml($devContent, $devComposeFile);
        file_put_contents($devComposeFile, $devContent);
    }

    /**
     * Find the correct insertion point for services (before networks section).
     * Services should be inserted at the end of the services block, before the networks section.
     */
    private function findServicesInsertionPoint(string $content): int|false
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
        // Find the volumes section
        $volumesPos = mb_strpos($content, "\nvolumes:");
        if ($volumesPos === false) {
            // No volumes section, add it at the end
            $volumeSection = "\nvolumes:\n";
            foreach ($volumes as $volumeName => $projectName) {
                $volumeSection .= "  {$volumeName}:\n";
                $volumeSection .= "    name: {$projectName}_\${APP_ENV:-dev}_{$volumeName}\n";
            }

            return $content . $volumeSection;

        }

        // Find end of file and add new volumes
        $volumesToInsert = '';
        foreach ($volumes as $volumeName => $projectName) {
            // Check if volume already exists
            if (mb_strpos($content, "  {$volumeName}:") === false) {
                $volumesToInsert .= "  {$volumeName}:\n";
                $volumesToInsert .= "    name: {$projectName}_\${APP_ENV:-dev}_{$volumeName}\n";
            }
        }

        if ($volumesToInsert !== '') {
            return mb_rtrim($content) . "\n" . $volumesToInsert;
        }

        return $content;
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
     * Copy environment file template to project root as .env
     */
    private function copyEnvironmentFile(string $environment, string $projectName = ''): void
    {
        $projectRoot = $this->directoryService->getProjectRoot();
        $targetEnv = $projectRoot . '/.env';

        // For Laravel, .env already exists after composer create-project
        // Just update it with Docker-specific variables
        if (file_exists($targetEnv)) {
            $this->appendTutiVariablesToEnv($targetEnv, $projectName);

            return;
        }

        // For WordPress and other stacks, create .env from template
        // First, check the copied template in .tuti/environments/
        $tutiEnvPath = $this->directoryService->getTutiPath('environments');
        $templatePaths = [
            "{$tutiEnvPath}/.env.{$environment}.example",
            "{$tutiEnvPath}/.env.dev.example",
            "{$tutiEnvPath}/.env.example",
        ];

        foreach ($templatePaths as $template) {
            if (file_exists($template)) {
                $this->createEnvFromTemplate($template, $targetEnv, $projectName);

                return;
            }
        }

        // Fallback: create minimal .env file
        $this->createMinimalEnvFile($targetEnv, $projectName);
    }

    /**
     * Create .env file from template with project-specific replacements.
     */
    private function createEnvFromTemplate(string $templatePath, string $targetPath, string $projectName): void
    {
        $content = file_get_contents($templatePath);

        if ($content === false) {
            $this->createMinimalEnvFile($targetPath, $projectName);

            return;
        }

        if ($projectName !== '') {
            $appDomain = $projectName . '.local.test';
            $userId = $this->getCurrentUserId();
            $groupId = $this->getCurrentGroupId();

            // Use regex patterns to match any default value
            $patterns = [
                '/^PROJECT_NAME=.*$/m' => "PROJECT_NAME={$projectName}",
                '/^APP_DOMAIN=.*$/m' => "APP_DOMAIN={$appDomain}",
                '/^APP_URL=.*$/m' => "APP_URL=https://{$appDomain}",
                '/^APP_NAME=.*$/m' => "APP_NAME={$projectName}",
                '/^DOCKER_USER_ID=.*$/m' => "DOCKER_USER_ID={$userId}",
                '/^DOCKER_GROUP_ID=.*$/m' => "DOCKER_GROUP_ID={$groupId}",
            ];

            foreach ($patterns as $pattern => $replace) {
                $content = preg_replace($pattern, $replace, (string) $content);
            }
        }

        file_put_contents($targetPath, $content);
    }

    /**
     * Create a minimal .env file when no template is available.
     */
    private function createMinimalEnvFile(string $targetEnv, string $projectName): void
    {
        $appDomain = $projectName . '.local.test';
        $userId = $this->getCurrentUserId();
        $groupId = $this->getCurrentGroupId();

        $content = <<<ENV
# ============================================================================
# TUTI-CLI DEVELOPMENT ENVIRONMENT
# ============================================================================

# Project Configuration
PROJECT_NAME={$projectName}
APP_ENV=dev
APP_DOMAIN={$appDomain}

# Docker Build Configuration
DOCKER_USER_ID={$userId}
DOCKER_GROUP_ID={$groupId}

# Database Configuration
DB_HOST=database
DB_PORT=3306
DB_DATABASE=wordpress
DB_USERNAME=wordpress
DB_PASSWORD=secret

ENV;

        file_put_contents($targetEnv, $content);
    }

    /**
     * Append tuti-specific variables to existing Laravel .env.
     */
    private function appendTutiVariablesToEnv(string $envPath, string $projectName): void
    {
        $content = file_get_contents($envPath);
        $appDomain = $projectName . '.local.test';

        // First, update Docker-specific service hostnames in existing variables
        $content = $this->updateDockerServiceVariables($content, $appDomain);

        // Check if tuti section already exists
        if (str_contains($content, '# TUTI-CLI DOCKER CONFIGURATION')) {
            file_put_contents($envPath, $content);

            return; // Already has tuti variables, just save updated content
        }

        // Get current user's UID and GID for Docker file permissions
        $userId = $this->getCurrentUserId();
        $groupId = $this->getCurrentGroupId();

        // Prepare tuti-specific variables
        $tutiVars = <<<EOT


# ============================================================================
# 🐳 TUTI-CLI DOCKER CONFIGURATION
# ============================================================================
# The following variables are used by Docker Compose for container setup.
# These are managed by tuti-cli and should not be changed manually unless
# you know what you're doing.
# ============================================================================

# ----------------------------------------------------------------------------
# Project Configuration
# ----------------------------------------------------------------------------
PROJECT_NAME={$projectName}
APP_DOMAIN={$appDomain}

# ----------------------------------------------------------------------------
# Docker Build Configuration
# ----------------------------------------------------------------------------
PHP_VERSION=8.4
PHP_VARIANT=fpm-nginx
BUILD_TARGET=development

# Docker User/Group IDs (auto-detected from current user)
DOCKER_USER_ID={$userId}
DOCKER_GROUP_ID={$groupId}
EOT;

        // Append to the file
        file_put_contents($envPath, $content . $tutiVars);
    }

    /**
     * Get current user's UID.
     */
    private function getCurrentUserId(): int
    {
        // First try shell command (most reliable for WSL and Linux)
        $output = $this->executeShellCommand('id -u');
        if ($output !== null && is_numeric(mb_trim($output))) {
            return (int) mb_trim($output);
        }

        // Fallback to posix_getuid() (available on Unix systems)
        if (function_exists('posix_getuid')) {
            $uid = posix_getuid();
            if ($uid > 0) {
                return $uid;
            }
        }

        // Default fallback for Windows or if detection fails
        return 1000;
    }

    /**
     * Get current user's GID.
     */
    private function getCurrentGroupId(): int
    {
        // First try shell command (most reliable for WSL and Linux)
        $output = $this->executeShellCommand('id -g');
        if ($output !== null && is_numeric(mb_trim($output))) {
            return (int) mb_trim($output);
        }

        // Fallback to posix_getgid() (available on Unix systems)
        if (function_exists('posix_getgid')) {
            $gid = posix_getgid();
            if ($gid > 0) {
                return $gid;
            }
        }

        // Default fallback for Windows or if detection fails
        return 1000;
    }

    /**
     * Execute a shell command and return output.
     */
    private function executeShellCommand(string $command): ?string
    {
        // Try different execution methods for compatibility
        $output = null;

        // Method 1: proc_open (most reliable)
        if (function_exists('proc_open')) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($command, $descriptors, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                if ($output !== false && mb_trim($output) !== '') {
                    return mb_trim($output);
                }
            }
        }

        // Method 2: shell_exec
        $output = @shell_exec($command . ' 2>/dev/null');
        if ($output !== null && mb_trim($output) !== '') {
            return mb_trim($output);
        }

        // Method 3: exec
        if (function_exists('exec')) {
            $result = [];
            @exec($command . ' 2>/dev/null', $result, $returnCode);
            if ($returnCode === 0 && $result !== []) {
                return mb_trim($result[0]);
            }
        }

        return null;
    }

    /**
     * Update environment variables to use Docker service names.
     */
    private function updateDockerServiceVariables(string $content, string $appDomain): string
    {
        // Update APP_URL to use local domain
        $content = preg_replace(
            '/^APP_URL=.*/m',
            "APP_URL=https://{$appDomain}",
            $content
        );

        // Update database configuration for PostgreSQL Docker container
        // Handle commented, uncommented, and malformed variables
        $dbReplacements = [
            // Handle commented variables with optional spaces (common in fresh Laravel installs)
            '/^[\s]*#[\s]*DB_HOST=.*/m' => 'DB_HOST=postgres',
            '/^[\s]*#[\s]*DB_PORT=.*/m' => 'DB_PORT=5432',
            '/^[\s]*#[\s]*DB_DATABASE=.*/m' => 'DB_DATABASE=laravel',
            '/^[\s]*#[\s]*DB_USERNAME=.*/m' => 'DB_USERNAME=laravel',
            '/^[\s]*#[\s]*DB_PASSWORD=.*/m' => 'DB_PASSWORD=secret',
        ];

        foreach ($dbReplacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, (string) $content);
        }

        // Handle existing uncommented variables (with possible leading spaces)
        $dbUpdates = [
            '/^[\s]*DB_HOST=.*/m' => 'DB_HOST=postgres',
            '/^[\s]*DB_PORT=.*/m' => 'DB_PORT=5432',
            '/^[\s]*DB_DATABASE=.*/m' => 'DB_DATABASE=laravel',
            '/^[\s]*DB_USERNAME=.*/m' => 'DB_USERNAME=laravel',
            '/^[\s]*DB_PASSWORD=.*/m' => 'DB_PASSWORD=secret',
        ];

        foreach ($dbUpdates as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, (string) $content);
        }

        // Update Redis configuration for Docker container
        $content = preg_replace('/^[\s]*#?[\s]*REDIS_HOST=.*/m', 'REDIS_HOST=redis', (string) $content);
        $content = preg_replace('/^[\s]*#?[\s]*REDIS_PASSWORD=.*/m', 'REDIS_PASSWORD=', (string) $content);

        // Update Mail configuration for Mailpit in Docker
        $content = preg_replace('/^[\s]*MAIL_HOST=.*/m', 'MAIL_HOST=mailpit', (string) $content);
        $content = preg_replace('/^[\s]*MAIL_PORT=.*/m', 'MAIL_PORT=1025', (string) $content);

        return preg_replace('/^[\s]*MAIL_MAILER=.*/m', 'MAIL_MAILER=smtp', (string) $content);
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
