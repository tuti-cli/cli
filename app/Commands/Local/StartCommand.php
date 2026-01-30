<?php

declare(strict_types=1);

namespace App\Commands\Local;

use App\Concerns\HasBrandedOutput;
use App\Domain\Project\Project;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use App\Services\Project\ProjectStateManagerService;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class StartCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'local:start';

    protected $description = 'Start the local development environment';

    public function handle(
        ProjectDirectoryService $dirService,
        ProjectMetadataService $metaService,
        ProjectStateManagerService $stateManager
    ): int {
        $this->brandedHeader('Local Environment');

        try {
            // 1. Resolve Project Context
            $root = $dirService->getProjectRoot();
            $config = $metaService->load();
            $project = new Project($root, $config);

            $this->note("Project: {$project->getName()}");

            // 2. Delegate to Business Logic (State Manager)
            $this->taskStart('Starting containers');
            $stateManager->start($project);
            $this->taskDone('Containers started');

            $this->completed('Project is running!', [
                "Run 'tuti local:status' to see details",
                "Run 'tuti local:logs' to view logs",
            ]);

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->failed('Failed to start project: ' . $e->getMessage(), [
                'Ensure Docker is running',
                'Check .tuti/docker-compose.yml exists',
            ]);

            return self::FAILURE;
        }
    }
}
