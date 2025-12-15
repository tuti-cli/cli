<?php

declare(strict_types=1);

namespace App\Commands\Stack;

use App\Services\Project\ProjectMetadataService;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackFilesCopierService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRegistryManagerService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class InitCommand extends Command
{
    protected $signature = 'stack:init
                          {stack?  : Stack name (e.g., laravel or laravel-stack)}
                          {project-name? : Project name}
                          {--services=* : Pre-select services}
                          {--env= :  Environment (dev, staging, production)}
                          {--force :  Force initialization even if .tuti exists}';

    protected $description = 'Initialize a new project with selected stack and services';

    public function handle(
        StackRegistryManagerService $registry,
        StackLoaderService          $stackLoader,
        StackComposeBuilderService  $builder,
        ProjectDirectoryService     $directoryManager,
        ProjectMetadataService      $metadata,
        StackFilesCopierService     $copier
    ): int {
        $this->displayHeader();

        try {
            if ($directoryManager->exists() && ! $this->option('force')) {
                $this->error('Project already initialized. ".tuti/" directory already exists in your project root.');
                $this->line('Use --force to reinitialize (this will remove existing configuration)');

                return self::FAILURE;
            }

            if ($directoryManager->exists() && $this->option('force')) {
                $this->warn('Removing existing .tuti directory...');
                $directoryManager->clean();
            }

            $stackPath = $this->getStackPath();

            if ($stackPath === null) {
                $this->error('No stack selected. Exiting.');

                return self::FAILURE;
            }

            $this->info('Using stack: ' . basename($stackPath));
            $this->newLine();

            $manifest = $stackLoader->load($stackPath);
            $stackLoader->validate($manifest);

            $this->displayStackInfo($manifest);

            $projectName = $this->getProjectName();
            $environment = $this->getEnvironment();
            $selectedServices = $this->selectServices($registry, $stackLoader, $manifest);

            if ($selectedServices === []) {
                $this->error('No services selected. Exiting.');

                return self::FAILURE;
            }

            $selectedServices = array_values(array_unique($selectedServices));

            if (! $this->confirmSelection($projectName, $environment, $selectedServices)) {
                $this->warn('Initialization cancelled.');

                return self::SUCCESS;
            }

            $this->initializeProject(
                $directoryManager,
                $copier,
                $metadata,
                $builder,
                $stackPath,
                $stackLoader,
                $manifest,
                $projectName,
                $environment,
                $selectedServices
            );

            $this->displayNextSteps($projectName, $environment);

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error('Initialization failed:  ' . $e->getMessage());

            if ($directoryManager->exists()) {
                $this->newLine();
                $this->warn('Cleaning up partial initialization...');
                $directoryManager->clean();
                $this->info('✓ Cleanup complete');
            }

            return self::FAILURE;
        }
    }

    private function initializeProject(
        ProjectDirectoryService    $directoryManager,
        StackFilesCopierService    $copier,
        ProjectMetadataService     $metadata,
        StackComposeBuilderService $builder,
        string                     $stackPath,
        StackLoaderService         $stackLoader,
        array                      $manifest,
        string                     $projectName,
        string                     $environment,
        array                      $selectedServices
    ): void {
        spin(
            fn (): bool => $directoryManager->initialize(),
            'Creating .tuti directory structure...'
        );
        $this->components->info('✓ .tuti directory created');

        spin(
            fn (): bool => $copier->copyFromStack($stackPath),
            'Copying stack files...'
        );
        $this->components->info('✓ Stack files copied');

        spin(
            function () use ($metadata, $stackLoader, $manifest, $projectName, $environment, $selectedServices): bool {
                $metadata->create([
                    'stack' => $stackLoader->getStackName($manifest),
                    'stack_version' => $manifest['version'],
                    'project_name' => $projectName,
                    'environment' => $environment,
                    'services' => $this->groupServices($selectedServices),
                ]);

                return true;
            },
            'Creating project metadata...'
        );
        $this->components->info('✓ Project metadata created');

        $this->generateDockerCompose($builder, $stackPath, $selectedServices, $projectName, $environment);
    }

    private function groupServices(array $selectedServices): array
    {
        $grouped = [];

        foreach ($selectedServices as $serviceKey) {
            [$category, $service] = explode('.', (string) $serviceKey);
            $grouped[$category][] = $service;
        }

        return $grouped;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('╔════════════════════════════════════════════════════════════╗');
        $this->line('║                                                            ║');
        $this->line('║              🚀 TUTI Stack Initialization                  ║');
        $this->line('║                                                            ║');
        $this->line('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function getStackPath(): ?string
    {
        $stackArg = $this->argument('stack');

        if ($stackArg !== null) {
            $possiblePaths = [
                stack_path($stackArg),
                stack_path("{$stackArg}-stack"),
            ];

            foreach ($possiblePaths as $path) {
                if (is_dir($path) && file_exists($path . '/stack.json')) {
                    return $path;
                }
            }

            if (is_dir($stackArg) && file_exists($stackArg . '/stack.json')) {
                return $stackArg;
            }

            if (! $this->option('no-interaction')) {
                warning("Stack not found:  {$stackArg}");
            }
        }

        if ($this->option('no-interaction')) {
            return null;
        }

        return $this->selectStackInteractively();
    }

    private function selectStackInteractively(): ?string
    {
        $availableStacks = $this->discoverStacks();

        if ($availableStacks === []) {
            $this->warn('No stacks found in:  ' . stack_path());

            $customPath = text(
                label: 'Enter stack name or path:',
                required: true
            );

            $possiblePaths = [
                stack_path($customPath),
                stack_path("{$customPath}-stack"),
                $customPath,
            ];

            foreach ($possiblePaths as $path) {
                if (is_dir($path) && file_exists($path . '/stack.json')) {
                    return $path;
                }
            }

            $this->error('Invalid stack path');

            return null;
        }

        $options = [];

        foreach ($availableStacks as $path) {
            $name = basename((string) $path);
            $options[$name] = $name;
        }

        $selected = select(
            label: 'Select a stack:',
            options: $options,
        );

        return $availableStacks[array_search($selected, array_keys($options), true)];
    }

    private function discoverStacks(): array
    {
        $stacksDir = stack_path();

        if (! is_dir($stacksDir)) {
            return [];
        }

        $stacks = [];
        $directories = glob($stacksDir . '/*-stack', GLOB_ONLYDIR);

        if ($directories === false) {
            return [];
        }

        foreach ($directories as $dir) {
            if (file_exists($dir . '/stack.json')) {
                $stacks[] = $dir;
            }
        }

        return $stacks;
    }

    private function displayStackInfo(array $manifest): void
    {
        $this->info('Stack:  ' . $manifest['name']);
        $this->line('  Type: ' . $manifest['type']);
        $this->line('  Framework: ' . $manifest['framework']);
        $this->line('  Description: ' . $manifest['description']);
        $this->newLine();
    }

    private function getProjectName(): string
    {
        $projectName = $this->argument('project-name');

        if ($projectName !== null) {
            return $projectName;
        }

        if ($this->option('no-interaction')) {
            return 'myapp';
        }

        return text(
            label: 'Project name:',
            default: 'myapp',
            required: true,
            validate: fn (string $value): ?string => preg_match('/^[a-z0-9_-]+$/', $value)
                ? null
                : 'Project name must contain only lowercase letters, numbers, hyphens, and underscores'
        );
    }

    private function getEnvironment(): string
    {
        $envOption = $this->option('env');

        if ($envOption !== null && in_array($envOption, ['dev', 'staging', 'production'], true)) {
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

    private function selectServices(
        StackRegistryManagerService $registry,
        StackLoaderService          $stackLoader,
        array                       $manifest
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

        if ($optionalChoices === []) {
            return $defaults;
        }

        $selectedOptional = multiselect(
            label: 'Select optional services:',
            options: $optionalChoices,
            default: $optionalDefaults,
        );

        return array_merge($defaults, $selectedOptional);
    }

    private function confirmSelection(string $projectName, string $environment, array $selectedServices): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        $this->info('Configuration Summary:');
        $this->line("  Project:  {$projectName}");
        $this->line("  Environment: {$environment}");
        $this->line('  Services: ');

        foreach ($selectedServices as $service) {
            $this->line("    - {$service}");
        }

        $this->newLine();

        return confirm('Proceed with initialization?', true);
    }

    private function generateDockerCompose(
        StackComposeBuilderService $builder,
        string $stackPath,
        array $selectedServices,
        string $projectName,
        string $environment
    ): void {
        spin(
            function () use ($builder, $stackPath, $selectedServices, $projectName, $environment): bool {
                $projectConfig = ['PROJECT_NAME' => $projectName];

                $compose = $builder->buildWithStack(
                    $stackPath,
                    $selectedServices,
                    $projectConfig,
                    $environment
                );

                $outputPath = tuti_path('docker-compose.yml');
                $builder->writeToFile($compose, $outputPath);

                return true;
            },
            'Generating docker-compose.yml...'
        );

        $this->components->info('✓ docker-compose.yml generated');
    }

    private function displayNextSteps(string $projectName, string $environment): void
    {
        $this->newLine();
        $this->components->info('✅ Stack initialized successfully!');
        $this->newLine();

        $this->info('Next Steps:');
        $this->line('  1.Review stack configuration in .tuti/');
        $this->line('  2.Generate environment variables');
        $this->line('  3.Deploy the stack: ');
        $this->newLine();

        if ($environment === 'dev') {
            $this->line('     docker compose -f .tuti/docker-compose.yml up -d');
        } else {
            $this->line("     docker stack deploy -c .tuti/docker-compose.yml {$projectName}_{$environment}");
        }

        $this->newLine();
    }
}
