<?php

declare(strict_types=1);

namespace App\Infrastructure\Docker;

use App\Contracts\OrchestratorInterface;
use App\Domain\Project\Project;
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
    /**
     * Start the project containers.
     * Runs `docker compose up -d`
     */
    public function start(Project $project): bool
    {
        // We assume the docker-compose.yml is in the standard location
        $composePath = $project->path . '/.tuti/docker/docker-compose.yml';

        if (! file_exists($composePath)) {
            // In a real app we might throw a specific exception here
            return false;
        }

        $process = $this->createProcess($project, ['up', '-d', '--remove-orphans']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Stop the project containers.
     * Runs `docker compose down`
     */
    public function stop(Project $project): bool
    {
        $process = $this->createProcess($project, ['down']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Restart services.
     */
    public function restart(Project $project, ?string $service = null): bool
    {
        $args = ['restart'];
        if ($service) {
            $args[] = $service;
        }

        $process = $this->createProcess($project, $args);
        $process->run();

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
            return [];
        }

        $output = $process->getOutput();
        $services = [];

        // Parse NDJSON (NewLine Delimited JSON) from docker compose v2
        foreach (explode("\n", mb_trim($output)) as $line) {
            if (empty($line)) {
                continue;
            }
            $data = json_decode($line, true);
            if ($data) {
                $services[] = $data;
            }
        }

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

        // Timeout must be null for streaming logs
        $process->setTimeout(null);

        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
    }

    /**
     * Helper to create a Symfony Process for docker compose customized for the project.
     */
    private function createProcess(Project $project, array $args): Process
    {
        // Base command
        $command = ['docker', 'compose'];

        // Add config file path
        $command[] = '-f';
        $command[] = $project->path . '/.tuti/docker/docker-compose.yml';

        // Add project name context
        $command[] = '-p';
        $command[] = $project->config->name;

        // Append actual action arguments (up, down, etc.)
        $command = array_merge($command, $args);

        $process = new Process($command);
        $process->setTimeout(300); // 5 minute default timeout

        return $process;
    }
}
