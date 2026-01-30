<?php

declare(strict_types=1);

namespace App\Infrastructure\Docker;

use App\Contracts\OrchestratorInterface;
use App\Domain\Project\Project;
use App\Services\Debug\DebugLogService;
use Symfony\Component\Process\Process;

/**
 * Class DockerComposeOrchestrator
 *
 * This is the implementation of OrchestratorInterface for Docker Compose.
 * It translates logical commands (start, stop) into physical shell commands
 * (docker compose up, docker compose down).
 *
 * It acts as the bridge between our domain world (Project objects) and the
 * external system world (OS processes).
 */
final class DockerComposeOrchestrator implements OrchestratorInterface
{
    private DebugLogService $debug;

    public function __construct()
    {
        $this->debug = DebugLogService::getInstance();
        $this->debug->setContext('docker:orchestrator');
    }

    /**
     * Start the project containers.
     * Runs `docker compose up -d`
     */
    public function start(Project $project): bool
    {
        $this->debug->info('Starting project containers', ['project' => $project->getName()]);

        // Check for docker-compose.yml in .tuti directory
        $composePath = $project->path . '/.tuti/docker-compose.yml';
        $this->debug->debug('Checking compose file', ['path' => $composePath, 'exists' => file_exists($composePath)]);

        if (! file_exists($composePath)) {
            // Fallback to old location
            $composePath = $project->path . '/.tuti/docker/docker-compose.yml';
            $this->debug->debug('Trying fallback path', ['path' => $composePath, 'exists' => file_exists($composePath)]);

            if (! file_exists($composePath)) {
                $this->debug->error('No docker-compose.yml found', [
                    'tried_paths' => [
                        $project->path . '/.tuti/docker-compose.yml',
                        $project->path . '/.tuti/docker/docker-compose.yml',
                    ],
                ]);
                return false;
            }
        }

        $process = $this->createProcess($project, ['up', '-d', '--remove-orphans', '--build']);

        $this->debug->command('docker compose up', [
            'command' => $process->getCommandLine(),
            'working_dir' => $process->getWorkingDirectory(),
        ]);

        $process->run();

        $this->debug->processOutput(
            'docker compose up',
            $process->getOutput(),
            $process->getErrorOutput(),
            $process->getExitCode() ?? -1
        );

        if (! $process->isSuccessful()) {
            $this->debug->error('Failed to start containers', [
                'exit_code' => $process->getExitCode(),
                'error' => $process->getErrorOutput(),
            ]);
        } else {
            $this->debug->info('Containers started successfully');
        }

        return $process->isSuccessful();
    }

    /**
     * Stop the project containers.
     * Runs `docker compose down`
     */
    public function stop(Project $project): bool
    {
        $this->debug->info('Stopping project containers', ['project' => $project->getName()]);

        $process = $this->createProcess($project, ['down']);

        $this->debug->command('docker compose down', [
            'command' => $process->getCommandLine(),
        ]);

        $process->run();

        $this->debug->processOutput(
            'docker compose down',
            $process->getOutput(),
            $process->getErrorOutput(),
            $process->getExitCode() ?? -1
        );

        return $process->isSuccessful();
    }

    /**
     * Restart services.
     */
    public function restart(Project $project, ?string $service = null): bool
    {
        $this->debug->info('Restarting containers', ['project' => $project->getName(), 'service' => $service]);

        $args = ['restart'];
        if ($service) {
            $args[] = $service;
        }

        $process = $this->createProcess($project, $args);
        $process->run();

        $this->debug->processOutput(
            'docker compose restart',
            $process->getOutput(),
            $process->getErrorOutput(),
            $process->getExitCode() ?? -1
        );

        return $process->isSuccessful();
    }

    /**
     * Get status of services.
     * Uses `docker compose ps --format json` to get structured data.
     */
    public function status(Project $project): array
    {
        $process = $this->createProcess($project, ['ps', '--format', 'json']);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->debug->warning('Failed to get container status', [
                'error' => $process->getErrorOutput(),
            ]);
            return [];
        }

        $output = $process->getOutput();
        $services = [];

        // Parse NDJSON (NewLine Delimited JSON) from docker compose v2
        foreach (explode("\n", mb_trim($output)) as $line) {
            if ($line === '' || $line === '0') {
                continue;
            }
            $data = json_decode($line, true);
            if ($data) {
                $services[] = $data;
            }
        }

        $this->debug->debug('Container status retrieved', ['count' => count($services)]);

        return $services;
    }

    /**
     * Stream logs.
     * This method writes directly to STDOUT as it runs.
     */
    public function logs(Project $project, ?string $service = null, bool $follow = false): void
    {
        $args = ['logs'];
        if ($follow) {
            $args[] = '-f';
        }
        if ($service) {
            $args[] = $service;
        }

        $process = $this->createProcess($project, $args);
        $process->setTimeout(null);

        $process->run(function ($type, $buffer): void {
            echo $buffer;
        });
    }

    /**
     * Get the last error message from failed operations.
     */
    public function getLastError(): ?string
    {
        $errors = $this->debug->getErrors();
        if (empty($errors)) {
            return null;
        }

        $lastError = end($errors);
        return $lastError['message'] . (isset($lastError['data']['error']) ? ': ' . $lastError['data']['error'] : '');
    }

    /**
     * Helper to create a Symfony Process for docker compose customized for the project.
     */
    private function createProcess(Project $project, array $args): Process
    {
        // Base command
        $command = ['docker', 'compose'];

        // Determine compose file paths
        $tutiPath = $project->path . '/.tuti';
        $mainCompose = $tutiPath . '/docker-compose.yml';
        $devCompose = $tutiPath . '/docker-compose.dev.yml';

        // Fallback to old location if new doesn't exist
        if (! file_exists($mainCompose)) {
            $mainCompose = $tutiPath . '/docker/docker-compose.yml';
        }

        // Add main config file
        $command[] = '-f';
        $command[] = $mainCompose;

        // Add dev override if exists (for local development)
        if (file_exists($devCompose)) {
            $command[] = '-f';
            $command[] = $devCompose;
        }

        // Add project name context
        $command[] = '-p';
        $command[] = $project->config->name;

        // Append actual action arguments (up, down, etc.)
        $command = array_merge($command, $args);

        $this->debug->debug('Creating process', [
            'command' => implode(' ', $command),
            'compose_files' => [
                'main' => $mainCompose,
                'dev' => file_exists($devCompose) ? $devCompose : null,
            ],
        ]);

        $process = new Process($command);
        $process->setTimeout(300); // 5 minute default timeout

        // Set working directory to .tuti for relative paths in compose files
        $process->setWorkingDirectory($tutiPath);

        return $process;
    }
}
