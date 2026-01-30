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

final class StopCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'local:stop';

    protected $description = 'Stop the local development environment';

    public function handle(
        ProjectDirectoryService $dirService,
        ProjectMetadataService $metaService,
        ProjectStateManagerService $stateManager
    ): int {
        $this->brandedHeader('Local Environment');

        try {
            $root = $dirService->getProjectRoot();
            $config = $metaService->load();
            $project = new Project($root, $config);

            $this->note("Project: {$project->getName()}");

            // Sync state first to know if we are actually running
            $stateManager->syncState($project);

            if (! $project->getState()->isRunning()) {
                $this->skipped('Project is already stopped');

                return self::SUCCESS;
            }

            $this->taskStart('Stopping containers');
            $stateManager->stop($project);
            $this->taskDone('Containers stopped');

            $this->completed('Project stopped successfully');

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->failed('Failed to stop project: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
