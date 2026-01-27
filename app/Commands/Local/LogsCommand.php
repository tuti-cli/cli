<?php

declare(strict_types=1);

namespace App\Commands\Local;

use App\Contracts\OrchestratorInterface;
use App\Domain\Project\Project;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use LaravelZero\Framework\Commands\Command;
use Throwable;

final class LogsCommand extends Command
{
    protected $signature = 'local:logs
                            {service? : Optional specific service name}
                            {--f|follow : Follow log output}';

    protected $description = 'View or follow logs for the project services';

    public function handle(
        ProjectDirectoryService $dirService,
        ProjectMetadataService $metaService,
        OrchestratorInterface $orchestrator
    ): int {
        try {
            $root = $dirService->getProjectRoot();
            $config = $metaService->load();
            $project = new Project($root, $config);

            $service = $this->argument('service');
            $follow = $this->option('follow');

            $this->info('Fetching logs for ' . ($service ?? 'all services') . '...');

            // This will stream output directly to stdout
            $orchestrator->logs($project, $service, $follow);

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('Failed to retrieve logs: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
