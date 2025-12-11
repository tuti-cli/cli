<?php

declare(strict_types=1);

namespace App\Commands\Stack;

use App\Services\Stack\StackComposeBuilderService;
use App\Services\Stack\StackRegistryReaderService;
use App\Services\Stack\StackLoaderService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class InitCommand extends Command
{
    protected $signature = 'stack:init
                          {stack?  : Stack name (e.g., laravel or laravel-stack)}
                          {project-name? : Project name}
                          {--services=* : Pre-select services}
                          {--env= : Environment (dev, staging, production)}
                          {--no-interact : Run without prompts}';

    protected $description = 'Initialize a new project with selected stack and services';

    public function handle(
        StackRegistryReaderService $registry,
        StackLoaderService         $stackLoader,
        StackComposeBuilderService $builder
    ): int {
        $this->displayHeader();

        try {
            // Step 1: Get stack path
            $stackPath = $this->getStackPath();

            if ($stackPath === null) {
                error('No stack selected.  Exiting.');

                return self::FAILURE;
            }

            $this->line('Using stack: ' . basename($stackPath));
            $this->newLine();

            // Step 2: Load stack manifest
            $manifest = $stackLoader->load($stackPath);
            $stackLoader->validate($manifest);

            $this->displayStackInfo($manifest);

            // Step 3: Get project name
            $projectName = $this->getProjectName();

            // Step 4: Get environment
            $environment = $this->getEnvironment();

            // Step 5: Select services
            $selectedServices = $this->selectServices($registry, $stackLoader, $manifest);

            if (empty($selectedServices)) {
                error('No services selected.  Exiting.');

                return self:: FAILURE;
            }

            // Step 6: Confirm selection
            if (! $this->confirmSelection($projectName, $environment, $selectedServices)) {
                warning('Initialization cancelled.');

                return self::SUCCESS;
            }

            // Step 7: Generate docker-compose.yml
            $this->generateDockerCompose(
                $builder,
                $stackPath,
                $selectedServices,
                $projectName,
                $environment
            );

            // Step 8: Display next steps
            $this->displayNextSteps($projectName, $environment);

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            error('Initialization failed: ' . $e->getMessage());

            return self:: FAILURE;
        }
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
            // Try as short name (e.g., "laravel") or full name (e.g., "laravel-stack")
            $possiblePaths = [
                stack_path($stackArg),                    // e.g., laravel-stack
                stack_path("{$stackArg}-stack"),          // e.g., laravel → laravel-stack
            ];

            foreach ($possiblePaths as $path) {
                if (is_dir($path) && file_exists($path .  '/stack.json')) {
                    return $path;
                }
            }

            // Check if it's an absolute path
            if (is_dir($stackArg) && file_exists($stackArg . '/stack.json')) {
                return $stackArg;
            }

            warning("Stack not found: {$stackArg}");

            if ($this->option('no-interaction')) {
                return null;
            }
        }

        if ($this->option('no-interaction')) {
            return null;
        }

        // Interactive:  Discover and select
        return $this->selectStackInteractively();
    }

    private function selectStackInteractively(): ?string
    {
        $availableStacks = $this->discoverStacks();

        if (empty($availableStacks)) {
            warning('No stacks found in:  ' . stack_path());

            $customPath = text(
                label:  'Enter stack name or path: ',
                required: true
            );

            // Try to resolve custom path
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

            error('Invalid stack path');

            return null;
        }

        // Show only stack names (not full paths)
        $options = [];
        foreach ($availableStacks as $path) {
            $name = basename($path);
            $options[$name] = $name;
        }

        $selected = select(
            label: 'Select a stack:',
            options: $options,
        );

        return $availableStacks[array_search($selected, array_keys($options), true)];
    }

    /**
     * @return array<int, string>
     */
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

    /**
     * @param array<string, mixed> $manifest
     */
    private function displayStackInfo(array $manifest): void
    {
        info('Stack:  ' . $manifest['name']);
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
            validate: fn (string $value): ? string => preg_match('/^[a-z0-9_-]+$/', $value)
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

    /**
     * @param array<string, mixed> $manifest
     * @return array<int, string>
     */
    private function selectServices(
        StackRegistryReaderService $registry,
        StackLoaderService         $stackLoader,
        array                      $manifest
    ): array {
        $preSelected = $this->option('services');

        if (!  empty($preSelected)) {
            return $preSelected;
        }

        if ($this->option('no-interaction')) {
            return $stackLoader->getDefaultServices($manifest);
        }

        // Build service selection options
        $options = [];
        $defaults = [];

        // Required services
        $required = $stackLoader->getRequiredServices($manifest);
        foreach ($required as $key => $config) {
            $category = $config['category'];
            $serviceOptions = $config['options'];
            $defaultOption = $config['default'];

            if (count($serviceOptions) === 1) {
                // Only one option - auto-select
                $defaults[] = "{$category}.{$serviceOptions[0]}";
                continue;
            }

            // Multiple options - let user choose
            $selected = select(
                label: $config['prompt'] ??  "Select {$key}:",
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

        // Optional services
        $optional = $stackLoader->getOptionalServices($manifest);
        foreach ($optional as $key => $config) {
            $category = $config['category'];
            $serviceOptions = $config['options'];

            foreach ($serviceOptions as $service) {
                $serviceConfig = $registry->getService($category, $service);
                $serviceKey = "{$category}.{$service}";
                $options[$serviceKey] = "{$serviceConfig['name']} - {$serviceConfig['description']}";

                if ($config['default'] === $service) {
                    $defaults[] = $serviceKey;
                }
            }
        }

        if (empty($options)) {
            return $defaults;
        }

        // Let user select optional services
        $selected = multiselect(
            label: 'Select optional services:',
            options: $options,
            default: $defaults,
        );

        return array_merge($defaults, $selected);
    }

    /**
     * @param array<int, string> $selectedServices
     */
    private function confirmSelection(
        string $projectName,
        string $environment,
        array $selectedServices
    ): bool {
        if ($this->option('no-interaction')) {
            return true;
        }

        info('Configuration Summary:');
        $this->line("  Project: {$projectName}");
        $this->line("  Environment: {$environment}");
        $this->line('  Services: ');
        foreach ($selectedServices as $service) {
            $this->line("    - {$service}");
        }
        $this->newLine();

        return confirm('Proceed with initialization?', true);
    }

    /**
     * @param array<int, string> $selectedServices
     */
    private function generateDockerCompose(
        StackComposeBuilderService $builder,
        string                     $stackPath,
        array                      $selectedServices,
        string                     $projectName,
        string                     $environment
    ): void {
        info('Generating docker-compose.yml...');

        $projectConfig = [
            'PROJECT_NAME' => $projectName,
        ];

        $compose = $builder->buildWithStack(
            $stackPath,
            $selectedServices,
            $projectConfig,
            $environment
        );

        $outputPath = tuti_path() . '/docker-compose.yml';
        $builder->writeToFile($compose, $outputPath);

        $this->components->info("✓ Generated: {$outputPath}");
    }

    private function displayNextSteps(string $projectName, string $environment): void
    {
        $this->newLine();
        $this->components->info('✅ Stack initialized successfully!');
        $this->newLine();

        info('Next Steps:');
        $this->line('  1. Review and update environment variables in .env');
        $this->line('  2. Generate secure passwords for databases');
        $this->line('  3. Deploy the stack: ');
        $this->newLine();

        if ($environment === 'dev') {
            $this->line('     docker compose up -d');
        } else {
            $this->line("     docker stack deploy -c docker-compose.yml {$projectName}_{$environment}");
        }

        $this->newLine();
    }
}
