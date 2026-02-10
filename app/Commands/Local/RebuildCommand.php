<?php

declare(strict_types=1);

namespace App\Commands\Local;

use App\Concerns\HasBrandedOutput;
use App\Contracts\InfrastructureManagerInterface;
use App\Services\Debug\DebugLogService;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use Throwable;

use function Laravel\Prompts\spin;

final class RebuildCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'local:rebuild
                          {--no-cache : Build without using cache}
                          {--force : Force rebuild even if containers are running}
                          {--d|detach : Run build without showing logs}';

    protected $description = 'Rebuild containers to apply configuration changes';

    public function handle(
        ProjectDirectoryService $dirService,
        ProjectMetadataService $metaService,
        InfrastructureManagerInterface $infrastructureManager,
        DebugLogService $debugService
    ): int {
        $this->brandedHeader('Rebuild Containers');

        $this->newLine();
        $debugService->setContext('local:rebuild');
        $debugService->info('Starting container rebuild');

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

            // 2. Resolve Project Context
            $root = $dirService->getProjectRoot();
            $debugService->debug('Project root', ['path' => $root]);

            $config = $metaService->load();
            $debugService->debug('Project config loaded', ['name' => $config->name]);

            $projectName = $config->name;
            $this->note("Project: {$projectName}");

            // 3. Check compose files exist
            $tutiPath = $root . '/.tuti';
            $composeFile = $tutiPath . '/docker-compose.yml';
            $devComposeFile = $tutiPath . '/docker-compose.dev.yml';
            $envFile = $root . '/.env';

            $debugService->debug('Compose files check', [
                'main' => $composeFile,
                'main_exists' => file_exists($composeFile),
                'dev' => $devComposeFile,
                'dev_exists' => file_exists($devComposeFile),
                'env' => $envFile,
                'env_exists' => file_exists($envFile),
            ]);

            if (! file_exists($composeFile)) {
                $debugService->error('docker-compose.yml not found', ['path' => $composeFile]);
                $this->failure('docker-compose.yml not found at: ' . $composeFile);
                $this->hint('Run "tuti stack:laravel" to reinitialize the project');

                return self::FAILURE;
            }

            // 4. Build docker compose command
            $command = ['docker', 'compose'];

            // Add compose files
            $command[] = '-f';
            $command[] = $composeFile;

            if (file_exists($devComposeFile)) {
                $command[] = '-f';
                $command[] = $devComposeFile;
            }

            // Add env file
            if (file_exists($envFile)) {
                $command[] = '--env-file';
                $command[] = $envFile;
            }

            // Add project name
            $command[] = '-p';
            $command[] = $projectName;

            // 5. Stop containers if running (unless --force is used)
            if (! $this->option('force')) {
                $this->note('Stopping containers...');

                $downCommand = array_merge($command, ['down']);
                $debugService->command('docker compose down', ['command' => implode(' ', $downCommand)]);

                $result = Process::path($tutiPath)->run($downCommand);

                if (! $result->successful()) {
                    $this->warning('Failed to stop containers, continuing anyway...');
                    $debugService->warning('Failed to stop containers', [
                        'error' => $result->errorOutput(),
                    ]);
                }
            }

            // 6. Build containers
            $buildCommand = array_merge($command, ['build']);

            if ($this->option('no-cache')) {
                $buildCommand[] = '--no-cache';
                $this->note('Building containers without cache (this may take a while)...');
            } else {
                $this->note('Building containers...');
            }

            // Add --pull to get latest base images
            $buildCommand[] = '--pull';

            $debugService->command('docker compose build', ['command' => implode(' ', $buildCommand)]);

            // Show or hide build output based on --detach flag
            if ($this->option('detach')) {
                // Run quietly without streaming output
                $result = Process::path($tutiPath)
                    ->timeout(600) // 10 minutes for build
                    ->run($buildCommand);
            } else {
                // Stream output to user (default behavior)
                $result = Process::path($tutiPath)
                    ->timeout(600) // 10 minutes for build
                    ->run($buildCommand, function ($type, $buffer) {
                        // Stream output to user
                        echo $buffer;
                    });
            }

            if (! $result->successful()) {
                $debugService->error('Build failed', [
                    'exit_code' => $result->exitCode(),
                    'error' => $result->errorOutput(),
                ]);

                $this->failed('Failed to build containers', [
                    'Check the error output above',
                    'Ensure Docker is running',
                    'Try with --no-cache flag',
                ]);

                return self::FAILURE;
            }

            $this->success('Containers built successfully');

            // 7. Start containers
            $this->note('Starting containers...');

            $upCommand = array_merge($command, ['up', '-d']);
            $debugService->command('docker compose up', ['command' => implode(' ', $upCommand)]);

            $result = Process::path($tutiPath)->run($upCommand);

            if (! $result->successful()) {
                $debugService->error('Failed to start containers', [
                    'exit_code' => $result->exitCode(),
                    'error' => $result->errorOutput(),
                ]);

                $this->failed('Failed to start containers', [
                    'Containers were built but failed to start',
                    'Run "tuti local:logs" to see container logs',
                    'Run "tuti debug errors" for detailed error info',
                ]);

                return self::FAILURE;
            }

            $this->success('Containers started');

            // 8. Display success message
            $projectDomain = $projectName . '.local.test';

            $this->newLine();
            $this->box('Rebuild Complete', [
                'Containers have been rebuilt and restarted',
                'All configuration changes have been applied',
            ], 60, true);

            $this->completed('Project is running!', [
                "Visit: https://{$projectDomain}",
                "Run 'tuti local:status' to see container details",
                "Run 'tuti local:logs' to view logs",
            ]);

            $debugService->info('Rebuild completed successfully');

            return self::SUCCESS;

        } catch (Throwable $e) {
            $debugService->error('Rebuild failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->failed('Failed to rebuild containers: ' . $e->getMessage(), [
                'Ensure Docker is running',
                'Check .tuti/docker-compose.yml exists',
                'Run "tuti debug errors" to see detailed error info',
                'Try with --no-cache flag if build is failing',
            ]);

            return self::FAILURE;
        }
    }
}
