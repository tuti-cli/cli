<?php

declare(strict_types=1);

namespace App\Services\Stack;

use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use App\Services\Support\EnvFileService;
use App\Services\Support\EnvHandlers\BedrockEnvHandler;
use App\Services\Support\EnvHandlers\LaravelEnvHandler;
use App\Services\Support\EnvHandlers\WordPressEnvHandler;
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
        private StackRegistryManagerService $registryManager,
        private EnvFileService $envService,
        private LaravelEnvHandler $laravelEnvHandler,
        private BedrockEnvHandler $bedrockEnvHandler,
        private WordPressEnvHandler $wordPressEnvHandler,
        private OptionalServicesBuilder $optionalServicesBuilder,
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
        $this->optionalServicesBuilder->appendServices(
            $selectedServices,
            $projectName,
            $environment,
            tuti_path('docker-compose.yml'),
            tuti_path('docker-compose.dev.yml')
        );

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
                return mb_substr($service, mb_strlen('databases.'));
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
            $userId = $this->envService->read($directory) !== '' && $this->envService->read($directory) !== '0' ? 1000 : 1000; // Placeholder, will be set by EnvFileService
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
