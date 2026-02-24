<?php

declare(strict_types=1);

namespace App\Services\Stack\Installers;

use App\Contracts\DockerExecutorInterface;
use App\Contracts\InfrastructureManagerInterface;
use App\Contracts\StackInstallerInterface;
use App\Services\Stack\StackFilesCopierService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRepositoryService;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Laravel Stack Installer.
 *
 * Handles installation and configuration of Laravel projects with Docker.
 * Supports two modes:
 * - Fresh installation: Creates a new Laravel project with Docker configuration
 * - Apply to existing: Adds Docker configuration to an existing Laravel project
 *
 * Uses Docker to run Composer, so no local PHP/Composer installation is required.
 */
final readonly class LaravelStackInstaller implements StackInstallerInterface
{
    private const string IDENTIFIER = 'laravel';

    private const array SUPPORTED_IDENTIFIERS = [
        'laravel',
        'laravel-stack',
    ];

    /**
     * Starter kit package mappings.
     *
     * Maps starter kit choice to composer package name.
     * 'auth' packages include Laravel's built-in authentication.
     * 'blank' packages are for API-only or custom auth setups.
     */
    private const array STARTER_KITS = [
        'react' => [
            'auth' => 'laravel/react-starter-kit',
            'blank' => 'laravel/blank-react-starter-kit',
        ],
        'vue' => [
            'auth' => 'laravel/vue-starter-kit',
            'blank' => 'laravel/blank-vue-starter-kit',
        ],
        'livewire' => [
            'auth' => 'laravel/livewire-starter-kit',
            'blank' => 'laravel/blank-livewire-starter-kit',
        ],
        'svelte' => [
            'auth' => 'laravel/svelte-starter-kit',
            'blank' => 'laravel/blank-svelte-starter-kit',
        ],
    ];

    public function __construct(
        private StackLoaderService $stackLoader,
        private StackFilesCopierService $copierService,
        private StackRepositoryService $repositoryService,
        private DockerExecutorInterface $dockerExecutor,
        private InfrastructureManagerInterface $infrastructureManager,
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getName(): string
    {
        return 'Laravel Stack';
    }

    public function getDescription(): string
    {
        return 'Production-ready Laravel application stack with Docker';
    }

    public function getFramework(): string
    {
        return 'laravel';
    }

    public function supports(string $stackIdentifier): bool
    {
        return in_array($stackIdentifier, self::SUPPORTED_IDENTIFIERS, true);
    }

    public function detectExistingProject(string $path): bool
    {
        // Check for Laravel-specific files
        $laravelIndicators = [
            $path . '/artisan',
            $path . '/composer.json',
            $path . '/bootstrap/app.php',
        ];

        foreach ($laravelIndicators as $indicator) {
            if (! file_exists($indicator)) {
                return false;
            }
        }

        // Additionally check composer.json for Laravel framework
        $composerPath = $path . '/composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);

            return isset($composer['require']['laravel/framework']);
        }

        return false;
    }

    public function installFresh(string $projectPath, string $projectName, array $options = []): bool
    {
        $this->validateFreshInstallation($projectPath);

        // Ensure infrastructure is ready (Traefik)
        $this->ensureInfrastructureReady();

        // Use composer create-project directly with starter kit packages
        // Laravel's composer scripts handle key:generate and initial migration
        $result = $this->createLaravelProject($projectPath, $options);

        if (! $result) {
            throw new RuntimeException('Failed to create Laravel project');
        }

        // Note: npm install & build is handled AFTER containers start (in LaravelCommand)
        // because the PHP container doesn't have node/npm installed.
        // The node container (which has npm) is used instead.

        return true;
    }

    public function applyToExisting(string $projectPath, array $options = []): bool
    {
        if (! $this->detectExistingProject($projectPath)) {
            throw new RuntimeException('No Laravel project detected in the specified path');
        }

        // Ensure infrastructure is ready (Traefik)
        $this->ensureInfrastructureReady();

        // Copy Docker configuration files from stack
        $stackPath = $this->getStackPath();
        $this->copierService->copyFromStack($stackPath);

        return true;
    }

    public function getStackPath(): string
    {
        return $this->repositoryService->getStackPath('laravel');
    }

    public function getAvailableModes(): array
    {
        return [
            'fresh' => 'Create new Laravel project with Docker configuration',
            'existing' => 'Add Docker configuration to existing Laravel project',
        ];
    }

    /**
     * Get the stack manifest.
     *
     * @return array<string, mixed>
     */
    public function getStackManifest(): array
    {
        return $this->stackLoader->load($this->getStackPath());
    }

    /**
     * Generate Laravel APP_KEY using Docker.
     */
    public function generateAppKey(string $projectPath): ?string
    {
        $result = $this->dockerExecutor->runArtisan('key:generate --show', $projectPath);

        if (! $result->successful) {
            return null;
        }

        // The output may contain Docker/PHP banner text before the actual key
        // We need to extract only the base64 key (format: base64:xxx...)
        $output = $result->output;

        // Look for a line that starts with "base64:"
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = mb_trim($line);
            if (str_starts_with($line, 'base64:')) {
                return $line;
            }
        }

        // Fallback: if no base64: prefix found, return the last non-empty line
        $nonEmptyLines = array_filter($lines, fn ($l): bool => ! in_array(mb_trim($l), ['', '0'], true));
        $lastLine = mb_trim(end($nonEmptyLines));

        // Only return if it looks like a base64 key
        if (str_starts_with($lastLine, 'base64:') || mb_strlen($lastLine) > 40) {
            return $lastLine;
        }

        return null;
    }

    /**
     * Run artisan command in the project.
     */
    public function runArtisan(string $projectPath, string $command): bool
    {
        $result = $this->dockerExecutor->runArtisan($command, $projectPath);

        return $result->successful;
    }

    /**
     * Run composer require to install a package.
     */
    public function runComposerRequire(string $projectPath, string $package): bool
    {
        $result = $this->dockerExecutor->runComposer("require {$package} --no-interaction", $projectPath);

        return $result->successful;
    }

    /**
     * Install npm dependencies using Docker.
     */
    public function installNpmDependencies(string $projectPath): bool
    {
        $result = $this->dockerExecutor->runNpm('install', $projectPath);

        return $result->successful;
    }

    /**
     * Run the Laravel setup script for starter kits.
     *
     * This script handles:
     * - composer install
     * - .env setup & key:generate
     * - database migrations
     * - npm install & npm run build
     */
    public function runSetupScript(string $projectPath): bool
    {
        $result = $this->dockerExecutor->runComposer('run-script setup', $projectPath);

        return $result->successful;
    }

    /**
     * Get the starter kit composer package based on options.
     *
     * @param  array<string, mixed>  $options
     */
    public function getStarterKit(array $options): ?string
    {
        $kit = $options['starter_kit'] ?? 'none';

        if ($kit === 'none') {
            return null;
        }

        // If no auth, use blank starter kits
        if (($options['authentication'] ?? null) === 'none') {
            return self::STARTER_KITS[$kit]['blank'] ?? null;
        }

        return self::STARTER_KITS[$kit]['auth'] ?? null;
    }

    /**
     * Determine the composer package to install.
     *
     * @param  array<string, mixed>  $options
     */
    public function determinePackage(array $options): string
    {
        $kit = $options['starter_kit'] ?? 'none';

        if ($kit === 'none') {
            return 'laravel/laravel';
        }

        $auth = $options['authentication'] ?? 'laravel';

        if ($auth === 'none') {
            return self::STARTER_KITS[$kit]['blank'];
        }

        return self::STARTER_KITS[$kit]['auth'];
    }

    /**
     * Configure the database connection in .env for Docker.
     */
    public function configureDefaultDatabaseConnection(string $directory, string $database, string $projectName): void
    {
        // Skip if SQLite - Laravel handles it
        if ($database === 'databases.sqlite') {
            $this->commentDatabaseConfigurationForSqlite($directory);

            return;
        }

        // Map Tuti database keys to Laravel driver names
        $dbMap = [
            'databases.postgres' => 'pgsql',
            'databases.mysql' => 'mysql',
            'databases.mariadb' => 'mariadb',
        ];

        $driver = $dbMap[$database] ?? $database;

        // Uncomment database configuration
        $this->uncommentDatabaseConfiguration($directory);

        // Update .env for Docker
        $envPath = $directory . '/.env';
        $envExamplePath = $directory . '/.env.example';

        // Update DB_CONNECTION
        $this->pregReplaceInFile('/DB_CONNECTION=.*/', "DB_CONNECTION={$driver}", $envPath);
        $this->pregReplaceInFile('/DB_CONNECTION=.*/', "DB_CONNECTION={$driver}", $envExamplePath);

        // Set Docker values
        $this->replaceInFile('DB_HOST=127.0.0.1', 'DB_HOST=db', $envPath);
        $this->replaceInFile('DB_HOST=127.0.0.1', 'DB_HOST=db', $envExamplePath);

        // Set port based on driver
        $defaultPorts = ['pgsql' => '5432', 'mysql' => '3306', 'mariadb' => '3306'];
        if (isset($defaultPorts[$driver])) {
            $this->replaceInFile('DB_PORT=3306', 'DB_PORT=' . $defaultPorts[$driver], $envPath);
            $this->replaceInFile('DB_PORT=3306', 'DB_PORT=' . $defaultPorts[$driver], $envExamplePath);
        }

        // Set database name (convert hyphens to underscores)
        $dbName = str_replace('-', '_', $projectName);
        $this->replaceInFile('DB_DATABASE=laravel', "DB_DATABASE={$dbName}", $envPath);
        $this->replaceInFile('DB_DATABASE=laravel', "DB_DATABASE={$dbName}", $envExamplePath);

        // Set credentials
        $this->replaceInFile('DB_USERNAME=root', 'DB_USERNAME=tuti', $envPath);
        $this->replaceInFile('DB_USERNAME=root', 'DB_USERNAME=tuti', $envExamplePath);

        $this->replaceInFile('DB_PASSWORD=', 'DB_PASSWORD=secret', $envPath);
        $this->replaceInFile('DB_PASSWORD=', 'DB_PASSWORD=secret', $envExamplePath);
    }

    /**
     * Comment out database configuration for SQLite.
     */
    private function commentDatabaseConfigurationForSqlite(string $directory): void
    {
        $defaults = ['DB_HOST=127.0.0.1', 'DB_PORT=3306', 'DB_DATABASE=laravel', 'DB_USERNAME=root', 'DB_PASSWORD='];

        $commented = array_map(static fn ($d): string => "# {$d}", $defaults);

        $envPath = $directory . '/.env';
        $envExamplePath = $directory . '/.env.example';

        if (file_exists($envPath)) {
            $this->replaceInFile($defaults, $commented, $envPath);
        }

        if (file_exists($envExamplePath)) {
            $this->replaceInFile($defaults, $commented, $envExamplePath);
        }
    }

    /**
     * Uncomment database configuration for non-SQLite databases.
     */
    private function uncommentDatabaseConfiguration(string $directory): void
    {
        $commented = ['# DB_HOST=127.0.0.1', '# DB_PORT=3306', '# DB_DATABASE=laravel', '# DB_USERNAME=root', '# DB_PASSWORD='];

        $uncommented = array_map(static fn ($d): string => mb_substr($d, 2), $commented);

        $envPath = $directory . '/.env';
        $envExamplePath = $directory . '/.env.example';

        if (file_exists($envPath)) {
            $this->replaceInFile($commented, $uncommented, $envPath);
        }

        if (file_exists($envExamplePath)) {
            $this->replaceInFile($commented, $uncommented, $envExamplePath);
        }
    }

    /**
     * Install Pest testing framework with drift conversion.
     */
    public function installPest(string $projectPath): bool
    {
        // Remove PHPUnit and add Pest
        $commands = [
            'remove phpunit/phpunit --dev --no-update',
            'require pestphp/pest pestphp/pest-plugin-laravel --no-update --dev',
        ];

        foreach ($commands as $cmd) {
            $result = $this->dockerExecutor->runComposer($cmd, $projectPath);
            if (! $result->successful) {
                return false;
            }
        }

        // Composer update to apply changes
        $result = $this->dockerExecutor->runComposer('update', $projectPath);
        if (! $result->successful) {
            return false;
        }

        // Initialize Pest - run via PHP image
        $phpImage = $this->dockerExecutor->getPhpImage('8.4');
        $initResult = $this->dockerExecutor->exec(
            $phpImage,
            'php vendor/bin/pest --init',
            $projectPath
        );
        if (! $initResult->successful) {
            return false;
        }

        // Drift conversion - convert PHPUnit tests to Pest
        $driftResult = $this->dockerExecutor->runComposer('require pestphp/pest-plugin-drift --dev', $projectPath);
        if (! $driftResult->successful) {
            return false;
        }

        // Run drift conversion - run via PHP image
        $driftExecResult = $this->dockerExecutor->exec(
            $phpImage,
            'php vendor/bin/pest --drift',
            $projectPath
        );
        // Drift might fail if no tests exist, continue anyway

        // Remove drift plugin
        $removeResult = $this->dockerExecutor->runComposer('remove pestphp/pest-plugin-drift --dev', $projectPath);

        return $removeResult->successful;
    }

    /**
     * Install Laravel Boost.
     */
    public function installBoost(string $projectPath): bool
    {
        $result = $this->dockerExecutor->runComposer('require laravel/boost ^2.0 --dev -W', $projectPath);

        if (! $result->successful) {
            return false;
        }

        $artisanResult = $this->dockerExecutor->runArtisan('boost:install', $projectPath);

        return $artisanResult->successful;
    }

    /**
     * Replace string in file.
     */
    /**
     * Replace string in file.
     *
     * @param  string|array<int, string>  $search
     * @param  string|array<int, string>  $replace
     */
    private function replaceInFile(string|array $search, string|array $replace, string $file): void
    {
        if (! file_exists($file)) {
            return;
        }

        file_put_contents($file, str_replace($search, $replace, file_get_contents($file)));
    }

    /**
     * Replace using regex in file.
     */
    private function pregReplaceInFile(string $pattern, string $replace, string $file): void
    {
        if (! file_exists($file)) {
            return;
        }

        file_put_contents($file, preg_replace($pattern, $replace, file_get_contents($file)));
    }

    /**
     * Ensure the global infrastructure (Traefik) is ready.
     */
    private function ensureInfrastructureReady(): void
    {
        if (! $this->infrastructureManager->ensureReady()) {
            throw new RuntimeException(
                'Failed to start global infrastructure. Please run "tuti install" first.'
            );
        }
    }

    /**
     * Create a new Laravel project using Docker + Composer.
     *
     * @param  array<string, mixed>  $options
     */
    private function createLaravelProject(string $projectPath, array $options): bool
    {
        // Ensure project directory exists
        if (! is_dir($projectPath) && (! mkdir($projectPath, 0755, true) && ! is_dir($projectPath))) {
            throw new RuntimeException("Failed to create directory: {$projectPath}");
        }

        // Build the composer command
        $composerCommand = $this->buildComposerCommand($options);

        // Execute composer create-project via Docker
        $result = $this->dockerExecutor->runComposer($composerCommand, $projectPath);

        if (! $result->successful) {
            throw new RuntimeException(
                'Failed to create Laravel project: ' . $result->errorOutput
            );
        }

        return true;
    }

    /**
     * Build the composer create-project command.
     *
     * @param  array<string, mixed>  $options
     */
    private function buildComposerCommand(array $options): string
    {
        // Determine package based on starter kit and authentication options
        $package = $this->determinePackage($options);
        $version = $options['laravel_version'] ?? null;

        // Handle special versions for Livewire single-file
        if (($options['starter_kit'] ?? null) === 'livewire'
            && ($options['authentication'] ?? null) === 'laravel'
            && ($options['livewire_single_file'] ?? false)) {
            $package = 'laravel/livewire-starter-kit:dev-components';
        }

        // Handle WorkOS authentication
        if (($options['authentication'] ?? null) === 'workos') {
            $package = str_replace('-starter-kit', '-starter-kit:dev-workos', $package);
        }

        $command = "create-project {$package}";

        if ($version !== null && ! str_contains($package, ':')) {
            $command .= ":{$version}";
        }

        // Install in current directory (which is mounted as /app in container)
        $command .= ' .';

        // Add options - Laravel's scripts will handle key:generate and migrate
        $command .= ' --stability=dev';
        $command .= ' --prefer-dist';
        $command .= ' --no-interaction';

        if (! empty($options['no_dev'])) {
            $command .= ' --no-dev';
        }

        return $command;
    }

    /**
     * Validate that fresh installation can proceed.
     */
    private function validateFreshInstallation(string $projectPath): void
    {
        if (is_dir($projectPath) && count(scandir($projectPath)) > 2) {
            throw new RuntimeException(
                "Directory {$projectPath} is not empty. Cannot create fresh Laravel project."
            );
        }

        // Check if Docker is available (instead of local Composer)
        if (! $this->dockerExecutor->isDockerAvailable()) {
            throw new RuntimeException(
                'Docker is not available. Please install Docker to create Laravel projects.'
            );
        }
    }
}
