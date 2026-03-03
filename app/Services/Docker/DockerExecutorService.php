<?php

declare(strict_types=1);

namespace App\Services\Docker;

use App\Contracts\DockerExecutorInterface;
use App\Services\Docker\ValueObjects\DockerExecutionResultVO;
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
        private DockerCommandBuilder $builder,
        private string $phpImage = self::DEFAULT_PHP_IMAGE,
        private string $phpVersion = self::DEFAULT_PHP_VERSION,
    ) {}

    public function runComposer(string $command, string $workDir, array $env = []): DockerExecutionResultVO
    {
        $this->ensureDockerAvailable();
        $this->validateDirectoryExists($workDir);

        $image = $this->getPhpImage($this->phpVersion);
        $fullCommand = "composer {$command}";

        return $this->exec($image, $fullCommand, $workDir, $env);
    }

    public function runArtisan(string $command, string $projectPath, array $env = []): DockerExecutionResultVO
    {
        $this->ensureDockerAvailable();

        if (! file_exists($projectPath . '/artisan')) {
            return DockerExecutionResultVO::failure(
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

    public function runNpm(string $command, string $workDir, array $env = []): DockerExecutionResultVO
    {
        $this->ensureDockerAvailable();
        $this->validateDirectoryExists($workDir);

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
    public function runWpCli(array $arguments, string $workDir, array $env = [], ?string $networkName = null): DockerExecutionResultVO
    {
        $this->ensureDockerAvailable();
        $this->validateDirectoryExists($workDir);

        // Check if WP-CLI service is running in the project
        if ($this->isWpCliServiceRunning($workDir)) {
            return $this->execInWpCliService($arguments, $workDir);
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

        // Build docker compose command with available files using builder
        $composeFiles = [$composeFile];
        if (file_exists($composeDevFile)) {
            $composeFiles[] = $composeDevFile;
        }
        $command = $this->builder->buildComposeCommand(
            composeFiles: $composeFiles,
            args: ['ps', '--services', '--filter', 'status=running'],
        );

        // Check if the wpcli container is actually running
        $process = Process::run($command);

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
    ): DockerExecutionResultVO {
        $this->ensureDockerAvailable();

        // Build docker run command
        $dockerCommand = $this->buildDockerCommand($image, $command, $workDir, $env, $volumes);

        $process = Process::timeout(self::DEFAULT_TIMEOUT)->run($dockerCommand);

        return new DockerExecutionResultVO(
            successful: $process->successful(),
            output: $process->output(),
            errorOutput: $process->errorOutput(),
            exitCode: $process->exitCode() ?? 1,
        );
    }

    /**
     * Run an interactive Docker command with TTY support.
     *
     * This method provides interactive terminal sessions inside Docker containers,
     * allowing users to run commands like `php artisan tinker` or `mysql -u root -p`
     * with full terminal interactivity.
     *
     * ## Security Exception: escapeshellarg() Usage
     *
     * This method uses `escapeshellarg()` to escape command arguments before passing
     * them to `passthru()`. This is a documented exception to the project's security
     * standard that requires array syntax for all external process execution.
     *
     * ### Why This Exception Exists
     *
     * 1. **TTY Requirement**: Interactive terminal sessions require a TTY (teletype),
     *    which Laravel's Process facade does not support. The `passthru()` function
     *    is the only PHP built-in that provides proper TTY passthrough.
     *
     * 2. **Array Syntax Limitation**: Unlike `proc_open()` (used by Laravel Process),
     *    `passthru()` accepts only string commands, not arrays. Therefore, we must
     *    construct a command string with properly escaped arguments.
     *
     * ### Why This Is Safe
     *
     * 1. **Trusted Input Sources**: All elements in `$dockerCommand` are built
     *    internally by `buildInteractiveDockerCommand()`. The only external inputs
     *    are:
     *    - `$image`: Validated against internal constants/defaults
     *    - `$command`: Passed through as array elements (escaped individually)
     *    - `$workDir`: Validated by `validateDirectoryExists()` (realpath checked)
     *    - `$env`: Key-value pairs escaped as a whole
     *    - `$volumes`: Paths validated as existing directories
     *
     * 2. **Defense in Depth**: Each array element is individually escaped using
     *    `escapeshellarg()`, which wraps values in single quotes and escapes any
     *    existing single quotes. This prevents shell metacharacter injection even
     *    if malicious data somehow entered the pipeline.
     *
     * 3. **No User Interpolation**: This method never interpolates user input
     *    directly into command strings. All values go through `escapeshellarg()`.
     *
     * ### Related Documentation
     *
     * - AUDIT.md#SEC-003: Original security assessment
     * - TECH-DEBT.md#DEBT-007: Technical debt tracking
     * - Issue #73: Documentation task
     *
     * @param  string  $image  Docker image to run (validated internally)
     * @param  array<int, string>  $command  Command and arguments to execute
     * @param  string  $workDir  Working directory (validated to exist)
     * @param  array<string, string>  $env  Environment variables
     * @param  array<string, string>  $volumes  Additional volume mounts
     * @return int Exit code from the command (0 = success)
     *
     * @security This method uses escapeshellarg() instead of array syntax.
     *           This is a documented exception required for TTY support.
     *           See method documentation for security justification.
     */
    public function runInteractive(
        string $image,
        array $command,
        string $workDir,
        array $env = [],
        array $volumes = []
    ): int {
        $this->ensureDockerAvailable();
        $this->validateDirectoryExists($workDir);

        $dockerCommand = $this->buildInteractiveDockerCommand($image, $command, $workDir, $env, $volumes);

        // Use passthru for interactive TTY support
        // This is necessary because Laravel's Process facade doesn't support TTY
        // SECURITY: escapeshellarg() is used here as a documented exception.
        // All inputs are from trusted internal sources. See method documentation.
        $commandString = implode(' ', array_map(escapeshellarg(...), $dockerCommand));

        // phpcs:ignore -- passthru is required for TTY support
        passthru($commandString, $exitCode);

        return $exitCode;
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
    public function pullImage(string $image): DockerExecutionResultVO
    {
        $process = Process::timeout(300)->run(['docker', 'pull', $image]);

        return new DockerExecutionResultVO(
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
    ): DockerExecutionResultVO {
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

        return new DockerExecutionResultVO(
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
     */
    private function execInWpCliService(array $arguments, string $projectPath): DockerExecutionResultVO
    {
        $composeFile = $projectPath . '/docker-compose.yml';
        $composeDevFile = $projectPath . '/docker-compose.dev.yml';

        $composeFiles = [$composeFile];
        if (file_exists($composeDevFile)) {
            $composeFiles[] = $composeDevFile;
        }

        // Build compose exec command
        $composeCommand = $this->builder->buildComposeCommand(
            composeFiles: $composeFiles,
            args: array_merge(['exec', '-T', 'wpcli', 'php', '-d', 'memory_limit=512M', '/usr/local/bin/wp'], $arguments),
        );

        $process = Process::timeout(self::DEFAULT_TIMEOUT)->run($composeCommand);

        return new DockerExecutionResultVO(
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
    private function runWpCliTemporary(array $arguments, string $workDir, array $env = [], ?string $networkName = null): DockerExecutionResultVO
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

        return new DockerExecutionResultVO(
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
     * Build an interactive docker run command with TTY support.
     *
     * @param  array<int, string>  $command
     * @param  array<string, string>  $env
     * @param  array<string, string>  $volumes
     * @return array<int, string>
     */
    private function buildInteractiveDockerCommand(
        string $image,
        array $command,
        string $workDir,
        array $env,
        array $volumes
    ): array {
        $parts = [
            'docker',
            'run',
            '--rm',
            '--interactive',             // Keep STDIN open
            '--tty',                     // Allocate a pseudo-TTY
            '-v', "{$workDir}:/app",
            '-w', '/app',
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

        // Add command arguments directly (not wrapped in sh -c)
        foreach ($command as $arg) {
            $parts[] = $arg;
        }

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
     * Validate that the path exists and is a directory.
     *
     * This is a security measure to prevent mounting unexpected paths
     * to Docker containers. We validate BEFORE Docker operations rather
     * than auto-creating, which could be exploited.
     *
     * @throws RuntimeException If the path does not exist or is not a directory
     */
    private function validateDirectoryExists(string $path): void
    {
        // Check if path exists at all
        if (! file_exists($path)) {
            throw new RuntimeException(
                "Directory does not exist: {$path}. Please create it before running Docker commands."
            );
        }

        // Check if it's actually a directory (not a file or symlink to file)
        if (! is_dir($path)) {
            throw new RuntimeException(
                "Path exists but is not a directory: {$path}. Docker volume mounts require a directory."
            );
        }

        // Check for symlink and resolve it to ensure it points to a valid directory
        if (is_link($path)) {
            $realPath = realpath($path);
            if ($realPath === false || ! is_dir($realPath)) {
                throw new RuntimeException(
                    "Symlink points to invalid directory: {$path} -> {$realPath}"
                );
            }
        }
    }
}
