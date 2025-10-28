<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Process\Process;

final readonly class DockerService
{
    private string $composePath;

    public function __construct()
    {
        $this->composePath = getcwd().'/.tuti/docker/docker-compose.yml';
    }

    public function isRunning(): bool
    {
        $process = new Process(['docker', 'info']);
        $process->run();

        return $process->isSuccessful();
    }

    public function checkPortConflicts(): array
    {
        /*
         * TODO: Implementation for port checking
         */
        return [];
    }

    public function pullImages(): bool
    {
        return $this->runCommand(['docker', 'compose', 'pull']);
    }

    public function start(): bool
    {
        return $this->runCommand(['docker', 'compose', 'up', '-d']);
    }

    public function stop(): bool
    {
        return $this->runCommand(['docker', 'compose', 'down']);
    }

    public function exec(string $service, string $command): bool
    {
        return $this->runCommand([
            'docker', 'compose', 'exec', '-T', $service,
            'sh', '-c', $command,
        ]);
    }

    private function runCommand(array $command): bool
    {
        $process = new Process($command);
        $process->setWorkingDirectory(dirname($this->composePath));
        $process->setTimeout(300);
        $process->run();

        return $process->isSuccessful();
    }
}
