<?php

declare(strict_types=1);

namespace App\Services\Docker;

use Symfony\Component\Process\Process;

use function App\Services\get_project_name;

final readonly class DockerService
{
    private string $composePath;

    private string $projectName;

    public function __construct()
    {
        $this->composePath = tuti_path('docker/docker-compose.yml');
        $this->projectName = get_project_name();
    }

    /**
     * Check if Docker is running
     */
    public function isRunning(): bool
    {
        $process = new Process(['docker', 'info']);
        $process->run();

        return $process->isSuccessful();
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
        $process = $this->createDockerComposeProcess(['ps', '--format', 'json']);
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        $output = $process->getOutput();
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

        $process = $this->createDockerComposeProcess($args);
        $process->setTimeout(null);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
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

        $process = $this->createDockerComposeProcess($args);
        $process->setTimeout(null);

        if ($callback) {
            $process->run($callback);
        } else {
            $process->run(fn ($type, $buffer): string => print_r($buffer, true));
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
        $process = new Process([
            'docker', 'inspect',
            '--format', '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',
            $containerName,
        ]);

        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $ip = mb_trim($process->getOutput());

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
        $process = $this->createDockerComposeProcess($args);
        $process->setTimeout(600);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Create docker-compose process
     */
    private function createDockerComposeProcess(array $args): Process
    {
        $command = ['docker', 'compose'];

        // Add compose file
        $command[] = '-f';
        $command[] = $this->composePath;

        // Add project name
        $command[] = '-p';
        $command[] = $this->projectName;

        // Add environment file
        $envFile = tuti_path('environments/local.env');
        if (file_exists($envFile)) {
            $command[] = '--env-file';
            $command[] = $envFile;
        }

        // Add arguments
        foreach ($args as $arg) {
            $command[] = $arg;
        }

        return new Process($command);
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
        $process = new Process(['lsof', '-i', ":{$port}", '-t']);
        $process->run();

        if ($process->isSuccessful()) {
            $pid = mb_trim($process->getOutput());
            if ($pid !== '') {
                $psProcess = new Process(['ps', '-p', $pid, '-o', 'comm=']);
                $psProcess->run();

                $name = mb_trim($psProcess->getOutput());

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
