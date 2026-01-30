<?php

declare(strict_types=1);

namespace App\Services\Docker;

use App\Contracts\DockerExecutionResult;
use App\Contracts\DockerExecutorInterface;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Docker Executor Service.
 *
 * Executes commands inside Docker containers, allowing tuti-cli to run
 * PHP, Composer, and npm commands without requiring them on the host machine.
 *
 * Uses temporary containers that are removed after execution.
 */
final class DockerExecutorService implements DockerExecutorInterface
{
    private const DEFAULT_PHP_IMAGE = 'serversideup/php';
    private const DEFAULT_PHP_VERSION = '8.4';
    private const DEFAULT_NODE_IMAGE = 'node:20-alpine';
    private const DEFAULT_TIMEOUT = 600; // 10 minutes

    public function __construct(
        private readonly string $phpImage = self::DEFAULT_PHP_IMAGE,
        private readonly string $phpVersion = self::DEFAULT_PHP_VERSION,
    ) {}

    public function runComposer(string $command, string $workDir, array $env = []): DockerExecutionResult
    {
        $this->ensureDockerAvailable();
        $this->ensureDirectoryExists($workDir);

        $image = $this->getPhpImage($this->phpVersion);
        $fullCommand = "composer {$command}";

        return $this->exec($image, $fullCommand, $workDir, $env);
    }

    public function runArtisan(string $command, string $projectPath, array $env = []): DockerExecutionResult
    {
        $this->ensureDockerAvailable();

        if (! file_exists($projectPath . '/artisan')) {
            return DockerExecutionResult::failure(
                "No Laravel project found at {$projectPath}",
                1
            );
        }

        $image = $this->getPhpImage($this->phpVersion);
        $fullCommand = "php artisan {$command}";

        // Disable ServerSideUp banner/output
        $env['DISABLE_DEFAULT_CONFIG'] = 'true';

        return $this->exec($image, $fullCommand, $projectPath, $env);
    }

    public function runNpm(string $command, string $workDir, array $env = []): DockerExecutionResult
    {
        $this->ensureDockerAvailable();
        $this->ensureDirectoryExists($workDir);

        $fullCommand = "npm {$command}";

        return $this->exec(self::DEFAULT_NODE_IMAGE, $fullCommand, $workDir, $env);
    }

    public function exec(
        string $image,
        string $command,
        string $workDir,
        array $env = [],
        array $volumes = []
    ): DockerExecutionResult {
        $this->ensureDockerAvailable();

        // Build docker run command
        $dockerCommand = $this->buildDockerCommand($image, $command, $workDir, $env, $volumes);

        $process = Process::timeout(self::DEFAULT_TIMEOUT)->run($dockerCommand);

        return new DockerExecutionResult(
            successful: $process->successful(),
            output: $process->output(),
            errorOutput: $process->errorOutput(),
            exitCode: $process->exitCode() ?? 1,
        );
    }

    public function isDockerAvailable(): bool
    {
        $process = Process::run('docker info');

        return $process->successful();
    }

    public function getPhpImage(string $version = '8.4'): string
    {
        return "{$this->phpImage}:{$version}-cli";
    }

    /**
     * Build the docker run command with all arguments.
     *
     * @param  array<string, string>  $env
     * @param  array<string, string>  $volumes
     */
    private function buildDockerCommand(
        string $image,
        string $command,
        string $workDir,
        array $env,
        array $volumes
    ): string {
        $parts = [
            'docker',
            'run',
            '--rm',                          // Remove container after execution
            '--interactive',                  // Keep STDIN open
            '-v', "\"{$workDir}:/app\"",     // Mount working directory
            '-w', '/app',                    // Set working directory
        ];

        // Add environment variables
        foreach ($env as $key => $value) {
            $parts[] = '-e';
            $parts[] = "\"{$key}={$value}\"";
        }

        // Add additional volumes
        foreach ($volumes as $hostPath => $containerPath) {
            $parts[] = '-v';
            $parts[] = "\"{$hostPath}:{$containerPath}\"";
        }

        // Run as current user to avoid permission issues
        if (PHP_OS_FAMILY !== 'Windows') {
            $uid = getmyuid();
            $gid = getmygid();
            if ($uid !== false && $gid !== false) {
                $parts[] = '--user';
                $parts[] = "{$uid}:{$gid}";
            }
        }

        // Add image and command
        $parts[] = $image;
        $parts[] = 'sh';
        $parts[] = '-c';
        $parts[] = "\"{$command}\"";

        return implode(' ', $parts);
    }

    /**
     * Ensure Docker daemon is available.
     *
     * @throws RuntimeException
     */
    private function ensureDockerAvailable(): void
    {
        if (! $this->isDockerAvailable()) {
            throw new RuntimeException(
                'Docker is not available. Please ensure Docker is installed and running.'
            );
        }
    }

    /**
     * Ensure directory exists, create if necessary.
     */
    private function ensureDirectoryExists(string $path): void
    {
        if (! is_dir($path)) {
            if (! mkdir($path, 0755, true) && ! is_dir($path)) {
                throw new RuntimeException("Failed to create directory: {$path}");
            }
        }
    }

    /**
     * Pull a Docker image if not available locally.
     */
    public function pullImage(string $image): DockerExecutionResult
    {
        $process = Process::timeout(300)->run("docker pull {$image}");

        return new DockerExecutionResult(
            successful: $process->successful(),
            output: $process->output(),
            errorOutput: $process->errorOutput(),
            exitCode: $process->exitCode() ?? 1,
        );
    }

    /**
     * Check if a Docker image exists locally.
     */
    public function imageExists(string $image): bool
    {
        $process = Process::run("docker image inspect {$image}");

        return $process->successful();
    }

    /**
     * Execute a command inside a running container.
     *
     * @param  array<string, string>  $env
     */
    public function execInContainer(
        string $containerName,
        string $command,
        array $env = []
    ): DockerExecutionResult {
        $parts = ['docker', 'exec'];

        foreach ($env as $key => $value) {
            $parts[] = '-e';
            $parts[] = "\"{$key}={$value}\"";
        }

        $parts[] = $containerName;
        $parts[] = 'sh';
        $parts[] = '-c';
        $parts[] = "\"{$command}\"";

        $process = Process::timeout(self::DEFAULT_TIMEOUT)->run(implode(' ', $parts));

        return new DockerExecutionResult(
            successful: $process->successful(),
            output: $process->output(),
            errorOutput: $process->errorOutput(),
            exitCode: $process->exitCode() ?? 1,
        );
    }
}
