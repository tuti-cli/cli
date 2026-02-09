<?php

declare(strict_types=1);

namespace App\Infrastructure\Docker;

use App\Contracts\OrchestratorInterface;
use App\Domain\Project\Project;
use App\Services\Debug\DebugLogService;
use Illuminate\Support\Facades\Process;

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

        $command = $this->buildComposeCommand($project, ['up', '-d', '--build', '--remove-orphans']);
        $tutiPath = $project->path . '/.tuti';

        $this->debug->command('docker compose up', [
            'command' => $command,
            'working_dir' => $tutiPath,
        ]);

        $result = Process::path($tutiPath)->timeout(300)->run($command);

        $this->debug->processOutput(
            'docker compose up',
            $result->output(),
            $result->errorOutput(),
            $result->exitCode() ?? -1
        );

        if (! $result->successful()) {
            $this->debug->error('Failed to start containers', [
                'exit_code' => $result->exitCode(),
                'error' => $result->errorOutput(),
            ]);
        } else {
            $this->debug->info('Containers started successfully');
        }

        return $result->successful();
    }

    /**
     * Stop the project containers.
     * Runs `docker compose down`
     */
    public function stop(Project $project): bool
    {
        $this->debug->info('Stopping project containers', ['project' => $project->getName()]);

        $command = $this->buildComposeCommand($project, ['down']);
        $tutiPath = $project->path . '/.tuti';

        $this->debug->command('docker compose down', [
            'command' => $command,
        ]);

        $result = Process::path($tutiPath)->timeout(300)->run($command);

        $this->debug->processOutput(
            'docker compose down',
            $result->output(),
            $result->errorOutput(),
            $result->exitCode() ?? -1
        );

        return $result->successful();
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

        $command = $this->buildComposeCommand($project, $args);
        $tutiPath = $project->path . '/.tuti';

        $result = Process::path($tutiPath)->timeout(300)->run($command);

        $this->debug->processOutput(
            'docker compose restart',
            $result->output(),
            $result->errorOutput(),
            $result->exitCode() ?? -1
        );

        return $result->successful();
    }

    /**
     * Get status of services.
     * Uses `docker compose ps --format json` to get structured data.
     */
    public function status(Project $project): array
    {
        $command = $this->buildComposeCommand($project, ['ps', '--format', 'json']);
        $tutiPath = $project->path . '/.tuti';

        $result = Process::path($tutiPath)->timeout(300)->run($command);

        if (! $result->successful()) {
            $this->debug->warning('Failed to get container status', [
                'error' => $result->errorOutput(),
            ]);
            return [];
        }

        $output = $result->output();
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

        $command = $this->buildComposeCommand($project, $args);
        $tutiPath = $project->path . '/.tuti';

        Process::path($tutiPath)->timeout(0)->run($command, function ($type, $buffer): void {
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
     * Build docker compose command string for the project.
     */
    private function buildComposeCommand(Project $project, array $args): string
    {
        $parts = ['docker', 'compose'];

        // Determine compose file paths
        $tutiPath = $project->path . '/.tuti';
        $mainCompose = $tutiPath . '/docker-compose.yml';
        $devCompose = $tutiPath . '/docker-compose.dev.yml';
        $projectEnv = $project->path . '/.env';

        // Fallback to old location if new doesn't exist
        if (! file_exists($mainCompose)) {
            $mainCompose = $tutiPath . '/docker/docker-compose.yml';
        }

        // Add main config file
        $parts[] = '-f';
        $parts[] = $mainCompose;

        // Add dev override if exists (for local development)
        if (file_exists($devCompose)) {
            $parts[] = '-f';
            $parts[] = $devCompose;
        }

        // Explicitly specify env file from project root
        if (file_exists($projectEnv)) {
            $parts[] = '--env-file';
            $parts[] = $projectEnv;
        }

        // Add project name context
        $parts[] = '-p';
        $parts[] = $project->config->name;

        // Append actual action arguments (up, down, etc.)
        $parts = array_merge($parts, $args);

        $this->debug->debug('Creating process', [
            'command' => implode(' ', $parts),
            'compose_files' => [
                'main' => $mainCompose,
                'dev' => file_exists($devCompose) ? $devCompose : null,
            ],
            'env_file' => file_exists($projectEnv) ? $projectEnv : 'not found',
        ]);

        return implode(' ', $parts);
    }
}
