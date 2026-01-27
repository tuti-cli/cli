<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectInitializationService;
use App\Services\Stack\StackInstallerRegistry;
use LaravelZero\Framework\Commands\Command;
use Throwable;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

final class InitCommand extends Command
{
    // already defined in base Command class
    // {--env=dev :   Environment (dev, staging, production)}
    // {--no-interaction :   Run in non-interactive mode}
    protected $signature = 'init
                          {project-name?  : Project name}
                          {--stack= : Stack to use (laravel, etc.)}
                          {--force :  Force initialization even if .tuti exists}';

    protected $description = 'Initialize a new Tuti project';

    public function handle(
        ProjectDirectoryService $directoryService,
        ProjectInitializationService $initService,
        StackInstallerRegistry $installerRegistry
    ): int {
        $this->displayHeader();

        try {
            // Check if user wants to use a stack
            $selectedStack = $this->getStackSelection($installerRegistry);

            if ($selectedStack !== null) {
                // Delegate to the appropriate stack command
                return $this->delegateToStackCommand($selectedStack);
            }

            // Continue with basic initialization (no stack)
            return $this->runBasicInitialization($directoryService, $initService);

        } catch (Throwable $e) {
            $this->error('Initialization failed: ' . $e->getMessage());

            if ($directoryService->exists()) {
                $this->newLine();
                $this->warn('Cleaning up partial initialization...');
                $directoryService->clean();
                $this->info('✓ Cleanup complete');
            }

            return self::FAILURE;
        }
    }

    private function getStackSelection(StackInstallerRegistry $installerRegistry): ?string
    {
        // Check if stack was provided as option
        $stackOption = $this->option('stack');

        if ($stackOption !== null) {
            if ($installerRegistry->has($stackOption)) {
                return $stackOption;
            }

            $this->warn("Unknown stack: {$stackOption}");
        }

        if ($this->option('no-interaction')) {
            return null;
        }

        // Get available stacks
        $availableStacks = $installerRegistry->getAvailableStacks();

        if (empty($availableStacks)) {
            return null;
        }

        // Build options for selection
        $options = [
            'none' => '📦 Basic initialization (no stack, just .tuti/ structure)',
        ];

        foreach ($availableStacks as $identifier => $stack) {
            $options[$identifier] = "🚀 {$stack['name']} - {$stack['description']}";
        }

        $selected = select(
            label: 'Select initialization type:',
            options: $options,
            default: 'none'
        );

        return $selected === 'none' ? null : $selected;
    }

    private function delegateToStackCommand(string $stack): int
    {
        $commandMap = [
            'laravel' => 'stack:laravel',
            'laravel-stack' => 'stack:laravel',
        ];

        $command = $commandMap[$stack] ?? "stack:{$stack}";

        // Build arguments to pass through
        $arguments = [];

        if ($this->argument('project-name')) {
            $arguments['project-name'] = $this->argument('project-name');
        }

        if ($this->option('force')) {
            $arguments['--force'] = true;
        }

        if ($this->option('env')) {
            $arguments['--env'] = $this->option('env');
        }

        if ($this->option('no-interaction')) {
            $arguments['--no-interaction'] = true;
        }

        $this->info("Delegating to {$command}...");
        $this->newLine();

        return $this->call($command, $arguments);
    }

    private function runBasicInitialization(
        ProjectDirectoryService $directoryService,
        ProjectInitializationService $initService
    ): int {
        // 1. Pre-flight checks
        if ($directoryService->exists() && ! $this->option('force')) {
            $this->error('Project already initialized. ".tuti/" directory already exists.');
            $this->line('Use --force to reinitialize (this will remove existing configuration)');

            return self::FAILURE;
        }

        if ($directoryService->exists() && $this->option('force')) {
            $this->warn('Removing existing .tuti directory...');
            $directoryService->clean();
        }

        // 2. Gather user input
        $projectName = $this->getProjectName();
        $environment = $this->getEnvironment();

        // 3. Delegate to business logic service
        spin(
            fn (): bool => $initService->initialize($projectName, $environment),
            'Initializing project...'
        );

        $this->components->info('✓ Project initialized');

        $this->displayNextSteps();

        return self::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║                                                          ║');
        $this->line('║              🚀 TUTI Project Initialization              ║');
        $this->line('║                                                          ║');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function getProjectName(): string
    {
        $projectName = $this->argument('project-name');

        if ($projectName !== null) {
            return $projectName;
        }

        if ($this->option('no-interaction')) {
            return basename(getcwd());
        }

        return text(
            label: 'Project name:',
            default: basename(getcwd()),
            required: true,
            validate: fn (string $value): ?string => preg_match('/^[a-z0-9_-]+$/', $value)
                ? null
                : 'Project name must contain only lowercase letters, numbers, hyphens, and underscores'
        );
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

    private function displayNextSteps(): void
    {
        $this->newLine();
        $this->components->info('✅ Project initialized successfully!');
        $this->newLine();
        $this->info('Next Steps:');
        $this->line('  1. Place your docker-compose.yml in .tuti/docker/');
        $this->line('  2.Start your environment:  tuti local: start');
        $this->line('  3. Or use a stack template: tuti stack:init laravel');
        $this->newLine();
        $this->comment('📂 Project structure created: ');
        $this->line('  .tuti/');
        $this->line('  ├── config.json      ✓');
        $this->line('  ├── docker/          ✓');
        $this->line('  ├── environments/    ✓');
        $this->line('  └── scripts/         ✓');
        $this->newLine();
    }
}
