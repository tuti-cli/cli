<?php

declare(strict_types=1);

namespace App\Commands\Local;

use App\Domain\Project\Project;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use App\Services\Project\ProjectStateManagerService;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class StartCommand extends Command
{
    protected $signature = 'local:start';

    protected $description = 'Start the local development environment';

    public function handle(
        ProjectDirectoryService $dirService,
        ProjectMetadataService $metaService,
        ProjectStateManagerService $stateManager
    ): int {
        $this->info('Starting local environment...');

        try {
            // 1. Resolve Project Context
            // Future improvement: Move this resolution to a ServiceProvider
            $root = $dirService->getProjectRoot();
            $config = $metaService->load();

            $project = new Project($root, $config);

            // 2. Delegate to Business Logic (State Manager)
            $this->task("Starting containers for {$project->getName()}", function () use ($stateManager, $project): true {
                $stateManager->start($project);

                return true;
            });

            $this->info('Project is running! 🚀');
            $this->comment("Run 'tuti local:status' to see details.");

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('Failed to start project: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
