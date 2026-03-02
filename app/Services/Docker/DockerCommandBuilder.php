<?php

declare(strict_types=1);

namespace App\Services\Docker;

/**
 * Docker Command Builder Service.
 *
 * Centralizes Docker and Docker Compose command building logic
 * to eliminate duplication across multiple services.
 *
 * This service provides a fluent, expressive API for building
 * Docker commands while maintaining security through array-based
 * argument construction (no shell interpolation).
 */
final readonly class DockerCommandBuilder
{
    /**
     * Build a Docker Compose command.
     *
     * @param  array<int, string>  $composeFiles  Compose file paths to include
     * @param  string|null  $projectName  Project name for -p flag
     * @param  string|null  $envFilePath  Path to .env file for --env-file flag
     * @param  array<int, string>  $args  Command arguments (e.g., ['up', '-d'])
     * @return array<int, string>
     */
    public function buildComposeCommand(
        array $composeFiles = [],
        ?string $projectName = null,
        ?string $envFilePath = null,
        array $args = []
    ): array {
        $parts = ['docker', 'compose'];

        // Add compose files in order (base, then overrides)
        foreach ($composeFiles as $file) {
            if (file_exists($file)) {
                $parts[] = '-f';
                $parts[] = $file;
            }
        }

        // Add project name if provided
        if ($projectName !== null) {
            $parts[] = '-p';
            $parts[] = $projectName;
        }

        // Add environment file if provided and exists
        if ($envFilePath !== null && file_exists($envFilePath)) {
            $parts[] = '--env-file';
            $parts[] = $envFilePath;
        }

        // Append command arguments
        return array_merge($parts, $args);
    }

    /**
     * Build a Docker run command.
     *
     * @param  string  $image  Docker image to run
     * @param  string  $workDir  Host working directory to mount
     * @param  string  $command  Command to execute inside container
     * @param  array<string, string>  $env  Environment variables
     * @param  array<string, string>  $volumes  Additional volume mappings (host:container)
     * @param  bool  $interactive  Include --interactive flag
     * @param  bool  $tty  Include --tty flag
     * @param  bool  $remove  Include --rm flag
     * @param  string|null  $network  Docker network to connect to
     * @param  bool  $mapUser  Map host user to container (for non-Windows)
     * @return array<int, string>
     */
    public function buildRunCommand(
        string $image,
        string $workDir,
        string $command = 'sh',
        array $env = [],
        array $volumes = [],
        bool $interactive = true,
        bool $tty = false,
        bool $remove = true,
        ?string $network = null,
        bool $mapUser = true
    ): array {
        $parts = [
            'docker',
            'run',
        ];

        if ($remove) {
            $parts[] = '--rm';
        }

        if ($interactive) {
            $parts[] = '--interactive';
        }

        if ($tty) {
            $parts[] = '--tty';
        }

        // Mount working directory
        $parts[] = '-v';
        $parts[] = "{$workDir}:/app";

        // Set working directory inside container
        $parts[] = '-w';
        $parts[] = '/app';

        // Add network if specified
        if ($network !== null) {
            $parts[] = '--network';
            $parts[] = $network;
        }

        // Add environment variables
        foreach ($env as $key => $value) {
            $parts[] = '-e';
            $parts[] = "{$key}={$value}";
        }

        // Add additional volumes
        foreach ($volumes as $hostPath => $containerPath) {
            $parts[] = '-v';
            $parts[] = "{$hostPath}:{$containerPath}";
        }

        // Map host user to container (non-Windows only)
        if ($mapUser && PHP_OS_FAMILY !== 'Windows') {
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
        $parts[] = $command;

        return $parts;
    }

    /**
     * Build a Docker exec command.
     *
     * @param  string  $container  Container name or ID
     * @param  string  $command  Command to execute inside container
     * @param  array<string, string>  $env  Environment variables
     * @param  bool  $tty  Include -T flag (disable pseudo-TTY)
     * @return array<int, string>
     */
    public function buildExecCommand(
        string $container,
        string $command,
        array $env = [],
        bool $tty = true
    ): array {
        $parts = ['docker', 'exec'];

        if ($tty) {
            $parts[] = '-T'; // Disable pseudo-TTY
        }

        // Add environment variables
        foreach ($env as $key => $value) {
            $parts[] = '-e';
            $parts[] = "{$key}={$value}";
        }

        // Add container and command
        $parts[] = $container;
        $parts[] = 'sh';
        $parts[] = '-c';
        $parts[] = $command;

        return $parts;
    }

    /**
     * Build a Docker network create command.
     *
     * @param  string  $networkName  Name of the network to create
     * @return array<int, string>
     */
    public function buildNetworkCreateCommand(string $networkName): array
    {
        return ['docker', 'network', 'create', $networkName];
    }

    /**
     * Build a Docker network inspect command.
     *
     * @param  string  $networkName  Name of the network to inspect
     * @return array<int, string>
     */
    public function buildNetworkInspectCommand(string $networkName): array
    {
        return ['docker', 'network', 'inspect', $networkName];
    }

    /**
     * Build a Docker image pull command.
     *
     * @param  string  $image  Image name to pull
     * @return array<int, string>
     */
    public function buildImagePullCommand(string $image): array
    {
        return ['docker', 'pull', $image];
    }

    /**
     * Build a Docker image inspect command.
     *
     * @param  string  $image  Image name to inspect
     * @return array<int, string>
     */
    public function buildImageInspectCommand(string $image): array
    {
        return ['docker', 'image', 'inspect', $image];
    }

    /**
     * Build a Docker container inspect command.
     *
     * @param  string  $container  Container name or ID
     * @param  string|null  $format  Format template (optional)
     * @return array<int, string>
     */
    public function buildContainerInspectCommand(string $container, ?string $format = null): array
    {
        $parts = ['docker', 'inspect'];

        if ($format !== null) {
            $parts[] = '--format';
            $parts[] = $format;
        }

        $parts[] = $container;

        return $parts;
    }

    /**
     * Build a Docker info command.
     *
     * @return array<int, string>
     */
    public function buildInfoCommand(): array
    {
        return ['docker', 'info'];
    }
}
