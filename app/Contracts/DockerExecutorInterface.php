<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Docker\ValueObjects\DockerExecutionResultVO;

/**
 * Interface for executing commands inside Docker containers.
 *
 * This allows running PHP, Composer, npm, and other commands
 * without requiring them to be installed on the host machine.
 */
interface DockerExecutorInterface
{
    /**
     * Run a composer command inside a Docker container.
     *
     * @param  string  $command  The composer command to run (e.g., "create-project laravel/laravel .")
     * @param  string  $workDir  The working directory to mount
     * @param  array<string, string>  $env  Environment variables
     */
    public function runComposer(string $command, string $workDir, array $env = []): DockerExecutionResultVO;

    /**
     * Run a PHP artisan command inside a Docker container.
     *
     * @param  string  $command  The artisan command to run (e.g., "key:generate --show")
     * @param  string  $projectPath  Path to the Laravel project
     * @param  array<string, string>  $env  Environment variables
     */
    public function runArtisan(string $command, string $projectPath, array $env = []): DockerExecutionResultVO;

    /**
     * Run an npm command inside a Docker container.
     *
     * @param  string  $command  The npm command to run (e.g., "install")
     * @param  string  $workDir  The working directory to mount
     * @param  array<string, string>  $env  Environment variables
     */
    public function runNpm(string $command, string $workDir, array $env = []): DockerExecutionResultVO;

    /**
     * Run a WP-CLI command inside a Docker container.
     *
     * @param  array<int, string>  $arguments  The WP-CLI command arguments (e.g., ['core', 'download', '--version=6.4'])
     * @param  string  $workDir  The working directory to mount
     * @param  array<string, string>  $env  Environment variables
     * @param  string|null  $networkName  Optional Docker network to connect to (for database access)
     */
    public function runWpCli(array $arguments, string $workDir, array $env = [], ?string $networkName = null): DockerExecutionResultVO;

    /**
     * Run a generic command inside a Docker container.
     *
     * @param  string  $image  Docker image to use
     * @param  string  $command  Command to execute
     * @param  string  $workDir  Working directory to mount
     * @param  array<string, string>  $env  Environment variables
     * @param  array<string, string>  $volumes  Additional volume mounts (host => container)
     */
    public function exec(
        string $image,
        string $command,
        string $workDir,
        array $env = [],
        array $volumes = []
    ): DockerExecutionResultVO;

    /**
     * Run an interactive command inside a Docker container with TTY support.
     *
     * This method passes through STDIN/STDOUT/STDERR for interactive prompts.
     * Use for commands like `laravel new` that require user interaction.
     *
     * @param  string  $image  Docker image to use
     * @param  array<int, string>  $command  Command and arguments as array
     * @param  string  $workDir  Working directory to mount
     * @param  array<string, string>  $env  Environment variables
     * @param  array<string, string>  $volumes  Additional volume mounts (host => container)
     * @return int Exit code from the command
     */
    public function runInteractive(
        string $image,
        array $command,
        string $workDir,
        array $env = [],
        array $volumes = []
    ): int;

    /**
     * Check if Docker daemon is running.
     */
    public function isDockerAvailable(): bool;

    /**
     * Get the PHP image to use for commands.
     */
    public function getPhpImage(string $version = '8.4'): string;
}
