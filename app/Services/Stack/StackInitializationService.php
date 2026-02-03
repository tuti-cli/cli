<?php

declare(strict_types=1);

namespace App\Services\Stack;

use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use RuntimeException;

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
        private StackLoaderService $stackLoader
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
        $this->processDockerComposeFiles($projectName, $environment);

        // 6. Copy environment file template with project-specific values (for docker-compose)
        $this->copyEnvironmentFile($stackPath, $environment, $projectName);

        // 7. Update Laravel project's .env with Docker service settings
        $this->updateLaravelEnv($projectName);

        // 8. Validate initialization
        if (! $this->directoryService->validate()) {
            throw new RuntimeException('Stack initialization validation failed');
        }

        return true;
    }

    /**
     * Update Laravel project's .env file with Docker service connection settings.
     */
    private function updateLaravelEnv(string $projectName): void
    {
        $projectRoot = $this->directoryService->getProjectRoot();
        $laravelEnv = $projectRoot . '/.env';

        if (! file_exists($laravelEnv)) {
            return;
        }

        $content = file_get_contents($laravelEnv);

        // Update database settings to use Docker postgres
        $replacements = [
            '/^DB_CONNECTION=.*$/m' => 'DB_CONNECTION=pgsql',
            '/^DB_HOST=.*$/m' => 'DB_HOST=postgres',
            '/^DB_PORT=.*$/m' => 'DB_PORT=5432',
            '/^DB_DATABASE=.*$/m' => 'DB_DATABASE=laravel',
            '/^DB_USERNAME=.*$/m' => 'DB_USERNAME=laravel',
            '/^DB_PASSWORD=.*$/m' => 'DB_PASSWORD=secret',
            // Update Redis settings
            '/^REDIS_HOST=.*$/m' => 'REDIS_HOST=redis',
            // Update cache/session to use Redis
            '/^CACHE_STORE=.*$/m' => 'CACHE_STORE=redis',
            '/^SESSION_DRIVER=.*$/m' => 'SESSION_DRIVER=redis',
            '/^QUEUE_CONNECTION=.*$/m' => 'QUEUE_CONNECTION=redis',
            // Update app URL
            '/^APP_URL=.*$/m' => "APP_URL=https://{$projectName}.local.test",
        ];

        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        file_put_contents($laravelEnv, $content);
    }

    /**
     * Process docker-compose files with variable substitution.
     */
    private function processDockerComposeFiles(string $projectName, string $environment): void
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
     * Add tuti-specific environment variables to Laravel's .env file.
     */
    private function copyEnvironmentFile(string $stackPath, string $environment, string $projectName = ''): void
    {
        $projectRoot = $this->directoryService->getProjectRoot();
        $laravelEnv = $projectRoot . '/.env';

        // Check if Laravel .env exists (it should after composer create-project)
        if (! file_exists($laravelEnv)) {
            // If not, copy the full template
            $this->copyFullEnvTemplate($stackPath, $environment, $projectName);
            return;
        }

        // Laravel .env exists, append tuti-specific variables
        $this->appendTutiVariablesToEnv($laravelEnv, $projectName);
    }

    /**
     * Copy full .env template when Laravel .env doesn't exist.
     */
    private function copyFullEnvTemplate(string $stackPath, string $environment, string $projectName): void
    {
        $envTemplates = [
            "{$stackPath}/environments/.env.{$environment}.example",
            "{$stackPath}/environments/.env.dev.example",
            "{$stackPath}/environments/.env.example",
        ];

        $projectRoot = $this->directoryService->getProjectRoot();
        $targetEnv = $projectRoot . '/.env';

        foreach ($envTemplates as $template) {
            if (file_exists($template)) {
                $content = file_get_contents($template);

                // Substitute project-specific variables
                if ($projectName !== '') {
                    $appDomain = $projectName . '.local.test';
                    $userId = $this->getCurrentUserId();
                    $groupId = $this->getCurrentGroupId();

                    $replacements = [
                        'PROJECT_NAME=laravel' => "PROJECT_NAME={$projectName}",
                        'APP_DOMAIN=app.local.test' => "APP_DOMAIN={$appDomain}",
                        'APP_URL=https://app.local.test' => "APP_URL=https://{$appDomain}",
                        'APP_NAME=Laravel' => "APP_NAME={$projectName}",
                        'DOCKER_USER_ID=1000' => "DOCKER_USER_ID={$userId}",
                        'DOCKER_GROUP_ID=1000' => "DOCKER_GROUP_ID={$groupId}",
                    ];

                    foreach ($replacements as $search => $replace) {
                        $content = str_replace($search, $replace, $content);
                    }
                }

                file_put_contents($targetEnv, $content);
                return;
            }
        }
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
        if ($output !== null && is_numeric(trim($output))) {
            return (int) trim($output);
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
        if ($output !== null && is_numeric(trim($output))) {
            return (int) trim($output);
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

                if ($output !== false && trim($output) !== '') {
                    return trim($output);
                }
            }
        }

        // Method 2: shell_exec
        $output = @shell_exec($command . ' 2>/dev/null');
        if ($output !== null && trim($output) !== '') {
            return trim($output);
        }

        // Method 3: exec
        if (function_exists('exec')) {
            $result = [];
            @exec($command . ' 2>/dev/null', $result, $returnCode);
            if ($returnCode === 0 && ! empty($result)) {
                return trim($result[0]);
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
            $content = preg_replace($pattern, $replacement, $content);
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
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Update Redis configuration for Docker container
        $content = preg_replace('/^[\s]*#?[\s]*REDIS_HOST=.*/m', 'REDIS_HOST=redis', $content);

        // Update Mail configuration for Mailpit in Docker
        $content = preg_replace('/^[\s]*MAIL_HOST=.*/m', 'MAIL_HOST=mailpit', $content);
        $content = preg_replace('/^[\s]*MAIL_PORT=.*/m', 'MAIL_PORT=1025', $content);
        $content = preg_replace('/^[\s]*MAIL_MAILER=.*/m', 'MAIL_MAILER=smtp', $content);

        return $content;
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
