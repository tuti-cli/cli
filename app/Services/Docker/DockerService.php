<?php

declare(strict_types=1);

namespace App\Services\Docker;

use Illuminate\Support\Facades\Process;

final readonly class DockerService
{
    public function __construct(
        private string $composePath,
        private string $projectName,
        private ?string $envFilePath = null,
    ) {}

    /**
     * Check if Docker is running
     */
    public function isRunning(): bool
    {
        return Process::run(['docker', 'info'])->successful();
    }

    /**
     * Start all services
     */
    public function start(): bool
    {
        return $this->runDockerCompose(['up', '-d']);
    }

    /**
     * Stop all services
     */
    public function stop(): bool
    {
        return $this->runDockerCompose(['down']);
    }

    /**
     * Restart specific service or all services
     */
    public function restart(?string $service = null): bool
    {
        $args = ['restart'];

        if ($service) {
            $args[] = $service;
        }

        return $this->runDockerCompose($args);
    }

    /**
     * Pull Docker images
     */
    public function pullImages(): bool
    {
        return $this->runDockerCompose(['pull']);
    }

    /**
     * Get status of all services
     */
    public function getStatus(): array
    {
        $command = $this->buildComposeCommand(['ps', '--format', 'json']);
        $process = Process::run($command);

        if (! $process->successful()) {
            return [];
        }

        $output = $process->output();
        $services = [];

        // Docker Compose v2 returns NDJSON (newline-delimited JSON)
        foreach (explode("\n", mb_trim($output)) as $line) {
            if ($line === '') {
                continue;
            }
            if ($line === '0') {
                continue;
            }
            $service = json_decode($line, true);
            if ($service) {
                $services[] = [
                    'name' => $service['Service'] ?? $service['Name'] ?? 'unknown',
                    'status' => $service['State'] ?? 'unknown',
                    'ports' => $this->parsePorts($service['Publishers'] ?? []),
                    'health' => $service['Health'] ?? 'unknown',
                ];
            }
        }

        return $services;
    }

    /**
     * Execute command in container
     */
    public function exec(string $service, string $command, bool $tty = false): array
    {
        $args = ['exec'];

        if (! $tty) {
            $args[] = '-T';
        }

        $args[] = $service;
        $args[] = 'sh';
        $args[] = '-c';
        $args[] = $command;

        $composeCommand = $this->buildComposeCommand($args);
        $process = Process::timeout(0)->run($composeCommand);

        return [
            'success' => $process->successful(),
            'output' => $process->output(),
            'error' => $process->errorOutput(),
            'exit_code' => $process->exitCode(),
        ];
    }

    /**
     * Stream logs from service
     */
    public function logs(string $service, bool $follow = false, ?callable $callback = null): void
    {
        $args = ['logs'];

        if ($follow) {
            $args[] = '--follow';
        }

        $args[] = $service;

        $composeCommand = $this->buildComposeCommand($args);

        if ($callback) {
            Process::timeout(0)->run($composeCommand, $callback);
        } else {
            Process::timeout(0)->run($composeCommand, function ($type, $buffer): void {
                echo $buffer;
            });
        }
    }

    /**
     * Check port conflicts
     */
    public function checkPortConflicts(array $ports): array
    {
        $conflicts = [];

        foreach ($ports as $service => $port) {
            if ($this->isPortInUse($port)) {
                $usedBy = $this->getPortOwner($port);
                $conflicts[$port] = [
                    'service' => $service,
                    'used_by' => $usedBy,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Get container IP address
     */
    public function getContainerIp(string $containerName): ?string
    {
        $process = Process::run([
            'docker',
            'inspect',
            '--format',
            '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',
            $containerName,
        ]);

        if (! $process->successful()) {
            return null;
        }

        $ip = mb_trim($process->output());

        return $ip !== '' ? $ip : null;
    }

    /**
     * Check if service is healthy
     */
    public function isServiceHealthy(string $service): bool
    {
        $status = $this->getServiceStatus($service);

        return $status['health'] === 'healthy' || $status['status'] === 'running';
    }

    /**
     * Get specific service status
     */
    public function getServiceStatus(string $service): array
    {
        $allServices = $this->getStatus();

        foreach ($allServices as $s) {
            if ($s['name'] === $service) {
                return $s;
            }
        }

        return ['status' => 'not_found', 'health' => 'unknown'];
    }

    /**
     * Build services
     */
    public function build(?string $service = null): bool
    {
        $args = ['build'];

        if ($service) {
            $args[] = $service;
        }

        return $this->runDockerCompose($args);
    }

    /**
     * Remove all containers and volumes
     */
    public function destroy(): bool
    {
        return $this->runDockerCompose(['down', '-v']);
    }

    /**
     * Run docker-compose command
     */
    private function runDockerCompose(array $args): bool
    {
        $command = $this->buildComposeCommand($args);

        return Process::timeout(600)->run($command)->successful();
    }

    /**
     * Build docker compose command string
     */
    private function buildComposeCommand(array $args): array
    {
        $parts = ['docker', 'compose'];

        // Add compose file
        $parts[] = '-f';
        $parts[] = $this->composePath;

        // Add project name
        $parts[] = '-p';
        $parts[] = $this->projectName;

        // Add environment file
        if ($this->envFilePath !== null && file_exists($this->envFilePath)) {
            $parts[] = '--env-file';
            $parts[] = $this->envFilePath;
        }

        // Add arguments
        foreach ($args as $arg) {
            $parts[] = $arg;
        }

        return $parts;
    }

    /**
     * Check if port is in use
     */
    private function isPortInUse(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);

        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        return false;
    }

    /**
     * Get process using port
     */
    private function getPortOwner(int $port): string
    {
        if (is_windows()) {
            return 'unknown';
        }

        // Linux/Mac
        $process = Process::run(['lsof', '-i', ":{$port}", '-t']);

        if ($process->successful()) {
            $pid = mb_trim($process->output());
            if ($pid !== '') {
                $psProcess = Process::run(['ps', '-p', $pid, '-o', 'comm=']);

                $name = mb_trim($psProcess->output());

                return $name !== '' ? $name : 'unknown';
            }
        }

        return 'unknown';
    }

    /**
     * Parse port mappings
     */
    private function parsePorts(array $publishers): array
    {
        $ports = [];

        foreach ($publishers as $publisher) {
            if (isset($publisher['PublishedPort'], $publisher['TargetPort'])) {
                $ports[] = "{$publisher['PublishedPort']}:{$publisher['TargetPort']}";
            }
        }

        return $ports;
    }
}
