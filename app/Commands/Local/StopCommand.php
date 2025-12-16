<?php

declare(strict_types=1);

namespace App\Commands\Local;

use App\Domain\Project\Project;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use App\Services\Project\ProjectStateManagerService;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class StopCommand extends Command
{
    protected $signature = 'local:stop';

    protected $description = 'Stop the local development environment';

    public function handle(
        ProjectDirectoryService $dirService,
        ProjectMetadataService $metaService,
        ProjectStateManagerService $stateManager
    ): int {
        try {
            $root = $dirService->getProjectRoot();
            $config = $metaService->load();
            $project = new Project($root, $config);

            // Sync state first to know if we are actually running
            $stateManager->syncState($project);

            if (! $project->getState()->isRunning()) {
                $this->comment('Project is already stopped.');

                return self::SUCCESS;
            }

            $this->task('Stopping containers', function () use ($stateManager, $project): true {
                $stateManager->stop($project);

                return true;
            });

            $this->info('Project stopped successfully.');

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('Failed to stop project: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
