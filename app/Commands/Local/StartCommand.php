<?php

declare(strict_types=1);

namespace App\Commands\Local;

use App\Concerns\BuildsProjectUrls;
use App\Concerns\HasBrandedOutput;
use App\Contracts\InfrastructureManagerInterface;
use App\Domain\Project\Project;
use App\Services\Debug\DebugLogService;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use App\Services\Project\ProjectStateManagerService;
use LaravelZero\Framework\Commands\Command;
use Throwable;

use function Laravel\Prompts\spin;

final class StartCommand extends Command
{
    use BuildsProjectUrls;
    use HasBrandedOutput;

    protected $signature = 'local:start
                          {--skip-infra : Skip infrastructure check}';

    protected $description = 'Start the local development environment';

    public function handle(
        ProjectDirectoryService $dirService,
        ProjectMetadataService $metaService,
        ProjectStateManagerService $stateManager,
        InfrastructureManagerInterface $infrastructureManager,
        DebugLogService $debugService
    ): int {
        $this->brandedHeader('Local Environment');

        $debugService->setContext('local:start');
        $debugService->info('Starting local environment');

        try {
            // 0. Check if project is initialized
            if (! $dirService->exists()) {
                $debugService->error('No .tuti directory found');
                $this->failure('No tuti project found in current directory');
                $this->hint('Run "tuti stack:laravel" to create a new project');

                return self::FAILURE;
            }

            $debugService->debug('.tuti directory found', ['path' => $dirService->getTutiPath()]);

            // 1. Ensure infrastructure is ready
            if (! $this->option('skip-infra')) {
                $this->note('Checking infrastructure...');
                $debugService->debug('Checking infrastructure');

                if (! $infrastructureManager->isRunning()) {
                    spin(
                        fn () => $infrastructureManager->ensureReady(),
                        'Starting Traefik infrastructure...'
                    );
                    $this->success('Infrastructure ready');
                } else {
                    $this->success('Infrastructure is running');
                }
            }

            // 2. Resolve Project Context
            $root = $dirService->getProjectRoot();
            $debugService->debug('Project root', ['path' => $root]);

            $config = $metaService->load();
            $debugService->debug('Project config loaded', ['name' => $config->name]);

            $project = new Project($root, $config);

            $projectName = $project->getName();
            $this->note("Project: {$projectName}");

            // 3. Check compose files exist
            $composeFile = $root . '/.tuti/docker-compose.yml';
            $devComposeFile = $root . '/.tuti/docker-compose.dev.yml';

            $debugService->debug('Compose files check', [
                'main' => $composeFile,
                'main_exists' => file_exists($composeFile),
                'dev' => $devComposeFile,
                'dev_exists' => file_exists($devComposeFile),
            ]);

            if (! file_exists($composeFile)) {
                $debugService->error('docker-compose.yml not found', ['path' => $composeFile]);
                $this->failure('docker-compose.yml not found at: ' . $composeFile);
                $this->hint('Run "tuti stack:laravel" to reinitialize the project');

                return self::FAILURE;
            }

            // 4. Delegate to Business Logic (State Manager)
            $this->note('Starting containers (this may take a while on first run)...');

            $startResult = false;
            try {
                $stateManager->start($project);
                $startResult = true;
            } catch (Throwable $startError) {
                $debugService->error('State manager failed', ['error' => $startError->getMessage()]);
                throw $startError;
            }

            $this->success('Containers started');

            // 5. Display access URLs based on selected services
            $projectDomain = $projectName . '.local.test';
            $urls = $this->buildProjectUrls($config, $projectDomain);

            $this->newLine();
            $this->box('Project URLs', $urls, 60, true);

            $this->completed('Project is running!', [
                "Visit: https://{$projectDomain}",
                "Run 'tuti local:status' to see container details",
                "Run 'tuti local:logs' to view logs",
            ]);

            $debugService->info('Project started successfully');

            return self::SUCCESS;

        } catch (Throwable $e) {
            $debugService->error('Failed to start project', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->failed('Failed to start project: ' . $e->getMessage(), [
                'Ensure Docker is running',
                'Check .tuti/docker-compose.yml exists',
                'Run "tuti debug errors" to see detailed error info',
                'Run "tuti debug enable" then retry for more logging',
            ]);

            // Show recent errors from debug service
            $errors = $debugService->getErrors();
            if (count($errors) > 0) {
                $this->newLine();
                $this->warning('Recent errors:');

                $recentErrors = array_slice($errors, -3);
                foreach ($recentErrors as $error) {
                    $this->line("  <fg=red>[{$error['context']}] {$error['message']}</>");
                    if (isset($error['data']['error'])) {
                        $errorLines = explode("\n", (string) $error['data']['error']);
                        foreach (array_slice($errorLines, 0, 3) as $line) {
                            if (! empty(mb_trim($line))) {
                                $this->line("    <fg=yellow>{$line}</>");
                            }
                        }
                    }
                }
            }

            return self::FAILURE;
        }
    }
}
