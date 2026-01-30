<?php

declare(strict_types=1);

namespace App\Commands\Local;

use App\Concerns\HasBrandedOutput;
use App\Contracts\OrchestratorInterface;
use App\Domain\Project\Project;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class StatusCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'local:status';

    protected $description = 'Check the status of project services';

    public function handle(
        ProjectDirectoryService $dirService,
        ProjectMetadataService $metaService,
        OrchestratorInterface $orchestrator
    ): int {
        $this->brandedHeader('Service Status');

        try {
            $root = $dirService->getProjectRoot();
            $config = $metaService->load();
            $project = new Project($root, $config);

            $this->labeledValue('Project', $project->getName());

            $services = $orchestrator->status($project);

            if ($services === []) {
                $this->warning('No running services found');
                $this->hint("Run 'tuti local:start' to start services");

                return self::SUCCESS;
            }

            $this->section('Services');

            $rows = [];
            foreach ($services as $service) {
                $state = $service['State'] ?? '?';
                $stateDisplay = $state === 'running'
                    ? $this->badgeSuccess('RUNNING')
                    : $this->badgeError(mb_strtoupper($state));

                $rows[] = [
                    $service['Name'] ?? '?',
                    $service['Service'] ?? '?',
                    $stateDisplay,
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
            $this->failed('Failed to check status: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
