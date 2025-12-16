<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectInitializationService;
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
                          {--force :  Force initialization even if .tuti exists}';

    protected $description = 'Initialize a new Tuti project';

    public function handle(
        ProjectDirectoryService $directoryService,
        ProjectInitializationService $initService
    ): int {
        $this->displayHeader();

        try {
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

            $this->displayNextSteps($projectName);

            return self::SUCCESS;

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

    private function displayNextSteps(string $projectName): void
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
