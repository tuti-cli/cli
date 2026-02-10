<?php

declare(strict_types=1);

namespace App\Contracts;

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
     * @return DockerExecutionResult
     */
    public function runComposer(string $command, string $workDir, array $env = []): DockerExecutionResult;

    /**
     * Run a PHP artisan command inside a Docker container.
     *
     * @param  string  $command  The artisan command to run (e.g., "key:generate --show")
     * @param  string  $projectPath  Path to the Laravel project
     * @param  array<string, string>  $env  Environment variables
     * @return DockerExecutionResult
     */
    public function runArtisan(string $command, string $projectPath, array $env = []): DockerExecutionResult;

    /**
     * Run an npm command inside a Docker container.
     *
     * @param  string  $command  The npm command to run (e.g., "install")
     * @param  string  $workDir  The working directory to mount
     * @param  array<string, string>  $env  Environment variables
     * @return DockerExecutionResult
     */
    public function runNpm(string $command, string $workDir, array $env = []): DockerExecutionResult;

    /**
     * Run a WP-CLI command inside a Docker container.
     *
     * @param  string  $command  The WP-CLI command to run (e.g., "core download")
     * @param  string  $workDir  The working directory to mount
     * @param  array<string, string>  $env  Environment variables
     * @return DockerExecutionResult
     */
    public function runWpCli(string $command, string $workDir, array $env = []): DockerExecutionResult;

    /**
     * Run a generic command inside a Docker container.
     *
     * @param  string  $image  Docker image to use
     * @param  string  $command  Command to execute
     * @param  string  $workDir  Working directory to mount
     * @param  array<string, string>  $env  Environment variables
     * @param  array<string, string>  $volumes  Additional volume mounts (host => container)
     * @return DockerExecutionResult
     */
    public function exec(
        string $image,
        string $command,
        string $workDir,
        array $env = [],
        array $volumes = []
    ): DockerExecutionResult;

    /**
     * Check if Docker daemon is running.
     */
    public function isDockerAvailable(): bool;

    /**
     * Get the PHP image to use for commands.
     */
    public function getPhpImage(string $version = '8.4'): string;
}

/**
 * Value object for Docker execution results.
 */
final readonly class DockerExecutionResult
{
    public function __construct(
        public bool $successful,
        public string $output,
        public string $errorOutput,
        public int $exitCode,
    ) {}

    public static function success(string $output): self
    {
        return new self(
            successful: true,
            output: $output,
            errorOutput: '',
            exitCode: 0,
        );
    }

    public static function failure(string $errorOutput, int $exitCode = 1): self
    {
        return new self(
            successful: false,
            output: '',
            errorOutput: $errorOutput,
            exitCode: $exitCode,
        );
    }
}
