<?php

declare(strict_types=1);

namespace App\Commands\Stack;

use App\Concerns\HasBrandedOutput;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Stack\Installers\LaravelStackInstaller;
use App\Services\Stack\StackInitializationService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRegistryManagerService;
use LaravelZero\Framework\Commands\Command;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

final class LaravelCommand extends Command
{
    use HasBrandedOutput;
    protected $signature = 'stack:laravel
                          {project-name? : Project name for fresh installation}
                          {--mode= : Installation mode (fresh, existing)}
                          {--path= : Path for fresh installation (defaults to current directory)}
                          {--services=* : Pre-select services}
                          {--laravel-version= : Specific Laravel version to install}
                          {--force : Force initialization even if .tuti exists}';

    protected $description = 'Initialize a Laravel project with Docker stack';

    public function handle(
        LaravelStackInstaller $installer,
        StackRegistryManagerService $registry,
        StackLoaderService $stackLoader,
        ProjectDirectoryService $directoryService,
        StackInitializationService $initService
    ): int {
        $this->brandedHeader('Laravel Stack Installation');

        try {
            // 1. Determine installation mode
            $mode = $this->getInstallationMode($installer);

            if ($mode === null) {
                $this->failure('Installation cancelled.');

                return self::FAILURE;
            }

            // 2. Pre-flight checks
            if (! $this->preFlightChecks($directoryService, $mode)) {
                return self::FAILURE;
            }

            // 3. Gather configuration
            $config = $this->gatherConfiguration($installer, $registry, $stackLoader, $mode);

            if ($config === null) {
                $this->failure('Configuration cancelled.');

                return self::FAILURE;
            }

            // 4. Confirm before proceeding
            if (! $this->confirmConfiguration($config)) {
                $this->warning('Installation cancelled.');

                return self::SUCCESS;
            }

            // 5. Execute installation
            $this->executeInstallation($installer, $initService, $config);

            $this->displayNextSteps($config);

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->failed('Installation failed: ' . $e->getMessage(), [
                'Ensure Docker is running',
                'Check your internet connection',
            ]);

            if ($directoryService->exists()) {
                $this->newLine();
                $this->warning('Cleaning up partial initialization...');
                $directoryService->clean();
                $this->success('Cleanup complete');
            }

            return self::FAILURE;
        }
    }

    private function getInstallationMode(LaravelStackInstaller $installer): ?string
    {
        $modeOption = $this->option('mode');

        if ($modeOption !== null && in_array($modeOption, ['fresh', 'existing'], true)) {
            return $modeOption;
        }

        // Detect if there's an existing Laravel project
        $hasExistingProject = $installer->detectExistingProject(getcwd());

        if ($this->option('no-interaction')) {
            return $hasExistingProject ? 'existing' : 'fresh';
        }

        // Build options based on detection
        $options = [];

        if ($hasExistingProject) {
            $options['existing'] = '📁 Apply Docker configuration to this existing Laravel project';
            $options['fresh'] = '✨ Create a new Laravel project in a subdirectory';

            $this->success('Existing Laravel project detected in current directory');
            $this->newLine();
        } else {
            $options['fresh'] = '✨ Create a new Laravel project with Docker configuration';
            $options['existing'] = '📁 Apply Docker configuration to existing project (specify path)';
        }

        return select(
            label: 'What would you like to do?',
            options: $options,
            default: $hasExistingProject ? 'existing' : 'fresh'
        );
    }

    private function preFlightChecks(ProjectDirectoryService $directoryService, string $mode): bool
    {
        if ($directoryService->exists() && ! $this->option('force')) {
            $this->failure('Project already initialized. ".tuti/" directory already exists.');
            $this->hint('Use --force to reinitialize (this will remove existing configuration)');

            return false;
        }

        if ($directoryService->exists() && $this->option('force')) {
            $this->warning('Removing existing .tuti directory...');
            $directoryService->clean();
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function gatherConfiguration(
        LaravelStackInstaller $installer,
        StackRegistryManagerService $registry,
        StackLoaderService $stackLoader,
        string $mode
    ): ?array {
        $config = [
            'mode' => $mode,
            'environment' => $this->getEnvironment(),
            'stack_path' => $installer->getStackPath(),
        ];

        if ($mode === 'fresh') {
            $config['project_name'] = $this->getProjectName();
            $config['project_path'] = $this->getProjectPath($config['project_name']);
            $config['laravel_version'] = $this->option('laravel-version');
        } else {
            $config['project_name'] = $this->getProjectName(basename(getcwd()));
            $config['project_path'] = getcwd();
        }

        // Load stack manifest and select services
        $manifest = $stackLoader->load($config['stack_path']);
        $stackLoader->validate($manifest);

        $this->displayStackInfo($manifest);

        $config['selected_services'] = $this->selectServices($registry, $stackLoader, $manifest);

        if (empty($config['selected_services'])) {
            return null;
        }

        $config['manifest'] = $manifest;

        return $config;
    }

    private function getProjectName(string $default = 'laravel-app'): string
    {
        $projectName = $this->argument('project-name');

        if ($projectName !== null) {
            return $projectName;
        }

        if ($this->option('no-interaction')) {
            return $default;
        }

        return text(
            label: 'Project name:',
            default: $default,
            required: true,
            validate: fn (string $value): ?string => preg_match('/^[a-z0-9_-]+$/', $value)
                ? null
                : 'Project name must contain only lowercase letters, numbers, hyphens, and underscores'
        );
    }

    private function getProjectPath(string $projectName): string
    {
        $pathOption = $this->option('path');

        if ($pathOption !== null) {
            return mb_rtrim($pathOption, '/') . '/' . $projectName;
        }

        return getcwd() . '/' . $projectName;
    }

    private function getEnvironment(): string
    {
        $envOption = $this->option('env');

        if (in_array($envOption, ['dev', 'staging', 'production'], true)) {
            return $envOption;
        }

        if ($this->option('no-interaction')) {
            return 'dev';
        }

        return select(
            label: 'Select environment:',
            options: [
                'dev' => 'Development',
                'staging' => 'Staging',
                'production' => 'Production',
            ],
            default: 'dev'
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function displayStackInfo(array $manifest): void
    {
        $this->box('Stack Info', [
            'Name' => $manifest['name'],
            'Type' => $manifest['type'],
            'Framework' => $manifest['framework'],
            'Description' => $manifest['description'],
        ], 60, true);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, string>
     */
    private function selectServices(
        StackRegistryManagerService $registry,
        StackLoaderService $stackLoader,
        array $manifest
    ): array {
        $preSelected = $this->option('services');

        if (! empty($preSelected)) {
            return $preSelected;
        }

        if ($this->option('no-interaction')) {
            return $stackLoader->getDefaultServices($manifest);
        }

        $defaults = [];
        $required = $stackLoader->getRequiredServices($manifest);

        foreach ($required as $key => $config) {
            $category = $config['category'];
            $serviceOptions = $config['options'];
            $defaultOption = $config['default'];

            if (count($serviceOptions) === 1) {
                $defaults[] = "{$category}.{$serviceOptions[0]}";

                continue;
            }

            $selected = select(
                label: $config['prompt'] ?? "Select {$key}:",
                options: array_combine(
                    $serviceOptions,
                    array_map(
                        fn (string $service): string => $registry->getService($category, $service)['name'],
                        $serviceOptions
                    )
                ),
                default: $defaultOption
            );

            $defaults[] = "{$category}.{$selected}";
        }

        $optional = $stackLoader->getOptionalServices($manifest);
        $optionalChoices = [];
        $optionalDefaults = [];

        foreach ($optional as $config) {
            $category = $config['category'];
            $serviceOptions = $config['options'];

            foreach ($serviceOptions as $service) {
                $serviceConfig = $registry->getService($category, $service);
                $serviceKey = "{$category}.{$service}";
                $optionalChoices[$serviceKey] = "{$serviceConfig['name']} - {$serviceConfig['description']}";

                if (($config['default'] ?? null) === $service) {
                    $optionalDefaults[] = $serviceKey;
                }
            }
        }

        if ($optionalChoices !== []) {
            $selectedOptional = multiselect(
                label: 'Select optional services:',
                options: $optionalChoices,
                default: $optionalDefaults,
            );

            $defaults = array_merge($defaults, $selectedOptional);
        }

        return array_values(array_unique($defaults));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function confirmConfiguration(array $config): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        $this->section('Configuration Summary');

        $modeLabel = $config['mode'] === 'fresh' ? '✨ Fresh installation' : '📁 Apply to existing';
        $this->keyValue('Mode', $modeLabel);
        $this->keyValue('Project', $config['project_name']);
        $this->keyValue('Path', $config['project_path']);
        $this->keyValue('Environment', $config['environment']);

        $this->header('Services');
        foreach ($config['selected_services'] as $service) {
            $this->bullet($service);
        }

        $this->newLine();

        return confirm('Proceed with installation?', true);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function executeInstallation(
        LaravelStackInstaller $installer,
        StackInitializationService $initService,
        array $config
    ): void {
        if ($config['mode'] === 'fresh') {
            // First, create the Laravel project via Docker
            $this->note('Creating Laravel project via Docker...');
            $this->hint('This may take a few minutes on first run (downloading PHP image)');

            spin(
                function () use ($installer, $config): void {
                    $options = [];

                    if ($config['laravel_version'] !== null) {
                        $options['laravel_version'] = $config['laravel_version'];
                    }

                    $options['prefer_dist'] = true;

                    $installer->installFresh(
                        $config['project_path'],
                        $config['project_name'],
                        $options
                    );
                },
                'Creating Laravel project (composer create-project via Docker)...'
            );

            $this->success('Laravel project created');

            // Change to the new project directory for stack initialization
            chdir($config['project_path']);
        }

        // Initialize the stack (Docker configuration)
        spin(
            fn (): bool => $initService->initialize(
                $config['stack_path'],
                $config['project_name'],
                $config['environment'],
                $config['selected_services']
            ),
            'Applying Docker stack configuration...'
        );

        $this->success('Stack initialized');

        // Generate APP_KEY if fresh installation
        if ($config['mode'] === 'fresh') {
            $this->note('Generating APP_KEY via Docker...');
            $appKey = $installer->generateAppKey($config['project_path']);

            if ($appKey !== null && str_starts_with($appKey, 'base64:')) {
                $this->success('APP_KEY generated successfully');
                $this->line('  ' . substr($appKey, 0, 30) . '...');

                // Update .env file with the key
                $this->updateEnvFile($config['project_path'], 'APP_KEY', $appKey);
            } else {
                $this->warning('Could not generate APP_KEY automatically');
                $this->hint('Run manually: cd ' . $config['project_name'] . ' && php artisan key:generate');
            }
        }
    }

    /**
     * Update a value in the .env file.
     */
    private function updateEnvFile(string $projectPath, string $key, string $value): void
    {
        $envPath = $projectPath . '/.env';

        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);
        $content = preg_replace(
            "/^{$key}=.*$/m",
            "{$key}={$value}",
            $content
        );

        file_put_contents($envPath, $content);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function displayNextSteps(array $config): void
    {
        $this->section('Project Structure');

        if ($config['mode'] === 'fresh') {
            $this->bullet("{$config['project_name']}/", 'cyan');
            $this->subItem('app/ (Laravel application)');
        }

        $this->bullet('.tuti/', 'cyan');
        $this->subItem('config.json');
        $this->subItem('docker/');
        $this->subItem('docker-compose.yml');
        $this->subItem('environments/');

        // Generate the project URL
        $projectDomain = $config['project_name'] . '.local.test';

        $nextSteps = [];

        if ($config['mode'] === 'fresh') {
            $nextSteps[] = "cd {$config['project_name']}";
        }

        $nextSteps = array_merge($nextSteps, [
            'Add to /etc/hosts: 127.0.0.1 ' . $projectDomain,
            'tuti local:start',
            'Visit: https://' . $projectDomain,
        ]);

        $this->completed('Laravel stack installed successfully!', $nextSteps);

        $this->newLine();
        $this->box('Project URLs', [
            'Application' => "https://{$projectDomain}",
            'Mailpit' => "https://mail.{$projectDomain}",
            'Traefik Dashboard' => 'https://traefik.local.test',
        ], 60, true);

        $this->hint('Use "tuti local:status" to check running services');
    }
}
