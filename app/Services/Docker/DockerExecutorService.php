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
final readonly class DockerExecutorService implements DockerExecutorInterface
{
    private const string DEFAULT_PHP_IMAGE = 'serversideup/php';

    private const string DEFAULT_PHP_VERSION = '8.4';

    private const string DEFAULT_NODE_IMAGE = 'node:20-alpine';

    private const int DEFAULT_TIMEOUT = 600; // 10 minutes

    public function __construct(
        private string $phpImage = self::DEFAULT_PHP_IMAGE,
        private string $phpVersion = self::DEFAULT_PHP_VERSION,
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

    /**
     * Run WP-CLI command inside a Docker container.
     *
     * Uses project's WP-CLI service if available, otherwise falls back to temporary container.
     *
     * @param  array<int, string>  $arguments  The WP-CLI command arguments (e.g., ['core', 'download', '--version=6.4'])
     * @param  string  $workDir  The working directory to mount
     * @param  array<string, string>  $env  Environment variables
     * @param  string|null  $networkName  Optional Docker network to connect to (for database access)
     */
    public function runWpCli(array $arguments, string $workDir, array $env = [], ?string $networkName = null): DockerExecutionResult
    {
        $this->ensureDockerAvailable();
        $this->ensureDirectoryExists($workDir);

        // Check if WP-CLI service is running in the project
        if ($this->isWpCliServiceRunning($workDir)) {
            return $this->execInWpCliService($arguments, $workDir, $env);
        }

        // Fall back to temporary container
        return $this->runWpCliTemporary($arguments, $workDir, $env, $networkName);
    }

    /**
     * Check if WP-CLI service is running in the project.
     *
     * Looks for docker-compose.yml with wpcli service definition and checks if container is running.
     */
    public function isWpCliServiceRunning(string $projectPath): bool
    {
        $composeFile = $projectPath . '/docker-compose.yml';
        $composeDevFile = $projectPath . '/docker-compose.dev.yml';

        // Check if docker-compose.yml exists
        if (! file_exists($composeFile)) {
            return false;
        }

        // Check if wpcli service is defined in compose file(s)
        $composeContent = file_get_contents($composeFile);
        if ($composeContent === false || ! str_contains($composeContent, 'wpcli:')) {
            // Also check dev compose file
            if (file_exists($composeDevFile)) {
                $devContent = file_get_contents($composeDevFile);
                if ($devContent === false || ! str_contains($devContent, 'wpcli:')) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Build docker compose command with available files
        $parts = ['docker', 'compose', '-f', $composeFile];
        if (file_exists($composeDevFile)) {
            $parts[] = '-f';
            $parts[] = $composeDevFile;
        }
        $parts = array_merge($parts, ['ps', '--services', '--filter', 'status=running']);

        // Check if the wpcli container is actually running
        $process = Process::run($parts);

        if (! $process->successful()) {
            return false;
        }

        return str_contains($process->output(), 'wpcli');
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
        $process = Process::run(['docker', 'info']);

        return $process->successful();
    }

    public function getPhpImage(string $version = '8.4'): string
    {
        return "{$this->phpImage}:{$version}-cli";
    }

    /**
     * Pull a Docker image if not available locally.
     */
    public function pullImage(string $image): DockerExecutionResult
    {
        $process = Process::timeout(300)->run(['docker', 'pull', $image]);

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
        $process = Process::run(['docker', 'image', 'inspect', $image]);

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
            $parts[] = "{$key}={$value}";
        }

        $parts[] = $containerName;
        $parts[] = 'sh';
        $parts[] = '-c';
        $parts[] = $command;

        $process = Process::timeout(self::DEFAULT_TIMEOUT)->run($parts);

        return new DockerExecutionResult(
            successful: $process->successful(),
            output: $process->output(),
            errorOutput: $process->errorOutput(),
            exitCode: $process->exitCode() ?? 1,
        );
    }

    /**
     * Execute WP-CLI command in the project's WP-CLI service container.
     *
     * @param  array<int, string>  $arguments  WP-CLI command arguments
     * @param  array<string, string>  $env  Environment variables
     */
    private function execInWpCliService(array $arguments, string $projectPath, array $env = []): DockerExecutionResult
    {
        $composeFile = $projectPath . '/docker-compose.yml';
        $composeDevFile = $projectPath . '/docker-compose.dev.yml';

        $parts = ['docker', 'compose', '-f', $composeFile];

        // Add dev compose file if it exists
        if (file_exists($composeDevFile)) {
            $parts[] = '-f';
            $parts[] = $composeDevFile;
        }

        $parts[] = 'exec';
        $parts[] = '-T'; // Disable pseudo-TTY

        // Add environment variables
        foreach ($env as $key => $value) {
            $parts[] = '-e';
            $parts[] = "{$key}={$value}";
        }

        $parts[] = 'wpcli';
        // Use PHP with increased memory limit, then wp binary with safe array arguments
        $parts[] = 'php';
        $parts[] = '-d';
        $parts[] = 'memory_limit=512M';
        $parts[] = '/usr/local/bin/wp';
        // Append all WP-CLI arguments as separate array elements (safe from injection)
        foreach ($arguments as $arg) {
            $parts[] = $arg;
        }

        $process = Process::timeout(self::DEFAULT_TIMEOUT)->run($parts);

        return new DockerExecutionResult(
            successful: $process->successful(),
            output: $process->output(),
            errorOutput: $process->errorOutput(),
            exitCode: $process->exitCode() ?? 1,
        );
    }

    /**
     * Run WP-CLI command in a temporary Docker container.
     *
     * Used when project's WP-CLI service is not available.
     *
     * @param  array<int, string>  $arguments  WP-CLI command arguments
     * @param  array<string, string>  $env  Environment variables
     */
    private function runWpCliTemporary(array $arguments, string $workDir, array $env = [], ?string $networkName = null): DockerExecutionResult
    {
        $image = 'wordpress:cli-2-php8.3';

        // Set default environment variables for WordPress
        $env = array_merge([
            'WORDPRESS_DB_HOST' => 'database',
            'WORDPRESS_DB_NAME' => 'wordpress',
            'WORDPRESS_DB_USER' => 'wordpress',
            'WORDPRESS_DB_PASSWORD' => 'secret',
            'WP_CLI_CACHE_DIR' => '/tmp/.wp-cli/cache',
        ], $env);

        // Build command parts safely using array syntax (no string interpolation)
        $parts = [
            'docker', 'run', '--rm', '-i',
            '-v', "{$workDir}:/var/www/html",
            '-w', '/var/www/html',
        ];

        // Add network if specified (for connecting to running containers like database)
        if ($networkName !== null) {
            $parts[] = '--network';
            $parts[] = $networkName;
        }

        // Add environment variables
        foreach ($env as $key => $value) {
            $parts[] = '-e';
            $parts[] = "{$key}={$value}";
        }

        // Add user mapping for file permissions (non-Windows only)
        if (PHP_OS_FAMILY !== 'Windows') {
            $uid = getmyuid();
            $gid = getmygid();
            if ($uid !== false && $gid !== false) {
                $parts[] = '--user';
                $parts[] = "{$uid}:{$gid}";
            }
        }

        // Add image and WP-CLI command with safe array arguments
        $parts[] = $image;
        $parts[] = 'php';
        $parts[] = '-d';
        $parts[] = 'memory_limit=512M';
        $parts[] = '/usr/local/bin/wp';
        // Append all WP-CLI arguments as separate array elements (safe from injection)
        foreach ($arguments as $arg) {
            $parts[] = $arg;
        }

        $process = Process::timeout(self::DEFAULT_TIMEOUT)->run($parts);

        return new DockerExecutionResult(
            successful: $process->successful(),
            output: $process->output(),
            errorOutput: $process->errorOutput(),
            exitCode: $process->exitCode() ?? 1,
        );
    }

    /**
     * Build the docker run command with all arguments.
     *
     * @param  array<string, string>  $env
     * @param  array<string, string>  $volumes
     * @return array<int, string>
     */
    private function buildDockerCommand(
        string $image,
        string $command,
        string $workDir,
        array $env,
        array $volumes
    ): array {
        $parts = [
            'docker',
            'run',
            '--rm',                      // Remove container after execution
            '--interactive',             // Keep STDIN open
            '-v', "{$workDir}:/app",     // Mount working directory
            '-w', '/app',               // Set working directory
        ];

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
        $parts[] = $command;

        return $parts;
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
        if (! is_dir($path) && (! mkdir($path, 0755, true) && ! is_dir($path))) {
            throw new RuntimeException("Failed to create directory: {$path}");
        }
    }
}
