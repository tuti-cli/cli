<?php

declare(strict_types=1);

namespace App\Commands\Stack;

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
        $this->displayHeader();

        try {
            // 1. Determine installation mode
            $mode = $this->getInstallationMode($installer);

            if ($mode === null) {
                $this->error('Installation cancelled.');

                return self::FAILURE;
            }

            // 2. Pre-flight checks
            if (! $this->preFlightChecks($directoryService, $mode)) {
                return self::FAILURE;
            }

            // 3. Gather configuration
            $config = $this->gatherConfiguration($installer, $registry, $stackLoader, $mode);

            if ($config === null) {
                $this->error('Configuration cancelled.');

                return self::FAILURE;
            }

            // 4. Confirm before proceeding
            if (! $this->confirmConfiguration($config)) {
                $this->warn('Installation cancelled.');

                return self::SUCCESS;
            }

            // 5. Execute installation
            $this->executeInstallation($installer, $initService, $config);

            $this->displayNextSteps($config);

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('Installation failed: ' . $e->getMessage());

            if ($directoryService->exists()) {
                $this->newLine();
                $this->warn('Cleaning up partial initialization...');
                $directoryService->clean();
                $this->info('✓ Cleanup complete');
            }

            return self::FAILURE;
        }
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║                                                          ║');
        $this->line('║           🚀 Laravel Stack Installation                  ║');
        $this->line('║                                                          ║');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
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

            $this->info('✓ Existing Laravel project detected in current directory');
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
            $this->error('Project already initialized. ".tuti/" directory already exists.');
            $this->line('Use --force to reinitialize (this will remove existing configuration)');

            return false;
        }

        if ($directoryService->exists() && $this->option('force')) {
            $this->warn('Removing existing .tuti directory...');
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
        $this->info('Stack: ' . $manifest['name']);
        $this->line('  Type: ' . $manifest['type']);
        $this->line('  Framework: ' . $manifest['framework']);
        $this->line('  Description: ' . $manifest['description']);
        $this->newLine();
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

        $this->newLine();
        $this->info('📋 Configuration Summary:');
        $this->line('  Mode: ' . ($config['mode'] === 'fresh' ? '✨ Fresh installation' : '📁 Apply to existing'));
        $this->line("  Project: {$config['project_name']}");
        $this->line("  Path: {$config['project_path']}");
        $this->line("  Environment: {$config['environment']}");
        $this->line('  Services:');

        foreach ($config['selected_services'] as $service) {
            $this->line("    - {$service}");
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
            // First, create the Laravel project
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
                'Creating Laravel project...'
            );

            $this->components->info('✓ Laravel project created');

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

        $this->components->info('✓ Stack initialized');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function displayNextSteps(array $config): void
    {
        $this->newLine();
        $this->components->info('✅ Laravel stack installed successfully!');
        $this->newLine();

        $this->info('📂 Project structure:');

        if ($config['mode'] === 'fresh') {
            $this->line("  {$config['project_name']}/");
            $this->line('  ├── app/           (Laravel application)');
        }

        $this->line('  ├── .tuti/         (Tuti configuration)');
        $this->line('  │   ├── config.json');
        $this->line('  │   ├── docker/');
        $this->line('  │   ├── docker-compose.yml');
        $this->line('  │   └── environments/');
        $this->newLine();

        $this->info('🚀 Next Steps:');
        $this->line('  1. Review configuration in .tuti/');
        $this->line('  2. Configure environment variables in .tuti/environments/');
        $this->line('  3. Start your development environment:');
        $this->newLine();

        if ($config['mode'] === 'fresh') {
            $this->line("     cd {$config['project_name']}");
        }

        $this->line('     tuti local:start');
        $this->newLine();

        $this->comment('💡 Tip: Use "tuti local:status" to check running services');
        $this->newLine();
    }
}
