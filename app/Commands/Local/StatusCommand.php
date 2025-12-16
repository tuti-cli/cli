<?php

declare(strict_types=1);

namespace App\Commands\Local;

use App\Contracts\OrchestratorInterface;
use App\Domain\Project\Project;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class StatusCommand extends Command
{
    protected $signature = 'local:status';

    protected $description = 'Check the status of project services';

    public function handle(
        ProjectDirectoryService $dirService,
        ProjectMetadataService $metaService,
        OrchestratorInterface $orchestrator
    ): int {
        try {
            $root = $dirService->getProjectRoot();
            $config = $metaService->load();
            $project = new Project($root, $config);

            $this->info("Project: <comment>{$project->getName()}</comment>");

            $services = $orchestrator->status($project);

            if ($services === []) {
                $this->warn('No running services found.');

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($services as $service) {
                // Docker compose v2 output structure varies, but generally has Name, State, Status
                $rows[] = [
                    $service['Name'] ?? '?',
                    $service['Service'] ?? '?',
                    $service['State'] ?? '?',
                    $service['Status'] ?? '?',
                    $service['Publishers'] ?? ($service['Ports'] ?? ''),
                ];
            }

            $this->table(
                ['Container', 'Service', 'State', 'Status', 'Ports'],
                $rows
            );

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('Failed to check status: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
