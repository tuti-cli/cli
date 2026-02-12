<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\HasBrandedOutput;
use App\Contracts\InfrastructureManagerInterface;
use App\Services\Docker\DockerExecutorService;
use Exception;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

final class InstallCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'install
                          {--force : Force reinstallation of global directory}
                          {--skip-infra : Skip infrastructure (Traefik) installation}';

    protected $description = 'Set up tuti CLI global configuration, directories, and infrastructure';

    public function handle(
        InfrastructureManagerInterface $infrastructureManager,
        DockerExecutorService $dockerExecutor
    ): int {
        $this->welcomeBanner();

        try {
            // Step 1: Check Docker availability
            if (! $this->checkDockerAvailable($dockerExecutor)) {
                return self::FAILURE;
            }

            // Step 2: Setup global directory
            $globalPath = $this->setupGlobalDirectory();

            // Step 3: Create global config
            $this->createGlobalConfig($globalPath);

            // Step 4: Setup infrastructure (Traefik)
            if (! $this->option('skip-infra')) {
                $this->setupInfrastructure($infrastructureManager);
            }

            // Step 5: Display success
            $this->displaySuccess($globalPath, $infrastructureManager);

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->failed('Installation failed: ' . $e->getMessage(), [
                'Try running with sudo or check directory permissions',
                'Ensure Docker is running',
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Check if Docker is available and running.
     */
    private function checkDockerAvailable(DockerExecutorService $executor): bool
    {
        $this->section('Checking Prerequisites');

        $isAvailable = $executor->isDockerAvailable();

        if (! $isAvailable) {
            $this->failure('Docker is not available or not running');
            $this->hint('Please install Docker Desktop and ensure it is running');
            $this->hint('Download from: https://www.docker.com/products/docker-desktop');

            return false;
        }

        $this->success('Docker is available');

        return true;
    }

    private function setupGlobalDirectory(): string
    {
        $globalPath = $this->getGlobalTutiPath();

        $this->section('Setting Up Global Directory');
        $this->note("Target: {$globalPath}");

        if (is_dir($globalPath) && ! $this->option('force')) {
            $this->skipped('Global directory already exists');

            return $globalPath;
        }

        // Create main directory
        $this->createDirectory($globalPath);
        $this->created($globalPath);

        // Create subdirectories
        $subdirs = ['stacks', 'cache', 'logs', 'infrastructure'];
        foreach ($subdirs as $subdir) {
            $path = $globalPath . DIRECTORY_SEPARATOR . $subdir;
            $this->createDirectory($path);
            $this->created("{$subdir}/");
        }

        return $globalPath;
    }

    private function createGlobalConfig(string $globalPath): void
    {
        $configPath = $globalPath . DIRECTORY_SEPARATOR . 'config.json';

        if (file_exists($configPath) && ! $this->option('force')) {
            $this->skipped('Config already exists');

            return;
        }

        $config = [
            'version' => '1.0.0',
            'auto_update_stacks' => true,
            'telemetry' => false,
            'default_environment' => 'dev',
            'infrastructure' => [
                'network' => 'traefik_proxy',
                'domain' => 'local.test',
            ],
            'created_at' => date('c'),
        ];

        $result = file_put_contents(
            $configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($result === false) {
            throw new RuntimeException("Failed to create config file: {$configPath}");
        }

        $this->created('config.json');
    }

    /**
     * Setup global infrastructure (Traefik reverse proxy).
     */
    private function setupInfrastructure(InfrastructureManagerInterface $infrastructureManager): void
    {
        $this->section('Setting Up Infrastructure');

        // Check if already installed
        if ($infrastructureManager->isInstalled() && ! $this->option('force')) {
            $this->skipped('Traefik infrastructure already installed');

            // Start if not running
            if (! $infrastructureManager->isRunning()) {
                $this->note('Starting Traefik...');
                spin(
                    fn () => $infrastructureManager->start(),
                    'Starting Traefik reverse proxy...'
                );
                $this->success('Traefik started');
            } else {
                $this->success('Traefik is running');
            }

            return;
        }

        // Ask for confirmation if reinstalling
        if ($infrastructureManager->isInstalled() && $this->option('force')) {
            if (! confirm('This will reinstall the Traefik infrastructure. Continue?', true)) {
                $this->skipped('Infrastructure reinstallation cancelled');

                return;
            }
        }

        // Ensure network exists
        $this->note('Creating Docker network: traefik_proxy');
        $infrastructureManager->ensureNetworkExists();
        $this->success('Docker network ready');

        // Install Traefik
        $this->note('Installing Traefik reverse proxy...');
        spin(
            fn () => $infrastructureManager->install(),
            'Copying Traefik configuration...'
        );
        $this->success('Traefik configuration installed');

        // Start Traefik
        $this->note('Starting Traefik...');
        spin(
            fn () => $infrastructureManager->start(),
            'Starting Traefik containers...'
        );
        $this->success('Traefik started');

        // Display info about /etc/hosts
        $this->displayHostsInfo();
    }

    /**
     * Display information about setting up /etc/hosts.
     */
    private function displayHostsInfo(): void
    {
        $this->newLine();
        $this->note('To access local projects, add these entries to your hosts file:');
        $this->newLine();

        $hostsContent = <<<'HOSTS'
        127.0.0.1 traefik.local.test
        127.0.0.1 *.local.test
        HOSTS;

        $this->line("  <fg=cyan>{$hostsContent}</>");
        $this->newLine();

        if (PHP_OS_FAMILY === 'Windows') {
            $this->hint('Edit: C:\\Windows\\System32\\drivers\\etc\\hosts');
        } else {
            $this->hint('Run: sudo nano /etc/hosts');
        }

        $this->hint('Or use dnsmasq for wildcard support');
    }

    private function createDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Failed to create directory: {$path}");
        }
    }

    private function getGlobalTutiPath(): string
    {
        // Try multiple methods to get home directory
        $home = getenv('HOME');

        if (empty($home)) {
            $home = $_SERVER['HOME'] ?? null;
        }

        if (empty($home)) {
            $home = $_SERVER['USERPROFILE'] ?? null;
        }

        if (empty($home) && function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $userInfo = posix_getpwuid(posix_getuid());
            $home = $userInfo['dir'] ?? null;
        }

        if (empty($home)) {
            $user = getenv('USER') ?: getenv('USERNAME');
            if (! empty($user)) {
                $home = PHP_OS_FAMILY === 'Windows'
                    ? "C:\\Users\\{$user}"
                    : "/home/{$user}";
            }
        }

        if (empty($home)) {
            throw new RuntimeException(
                'Unable to determine home directory. Please set the HOME environment variable.'
            );
        }

        return mb_rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.tuti';
    }

    private function displaySuccess(string $globalPath, InfrastructureManagerInterface $infrastructureManager): void
    {
        $this->section('Installation Summary');

        $status = $infrastructureManager->getStatus();

        $this->box('Global Tuti Directory', [
            'Path' => $globalPath,
            'config.json' => 'global configuration',
            'stacks/' => 'cached stack templates',
            'cache/' => 'temporary files',
            'logs/' => 'global logs',
            'infrastructure/' => 'Traefik proxy',
        ], 60, true);

        $this->newLine();

        $this->box('Infrastructure Status', [
            'Traefik' => $status['traefik']['running'] ? '✅ Running' : '❌ Stopped',
            'Network' => $status['network']['installed'] ? '✅ traefik_proxy' : '❌ Missing',
            'Dashboard' => 'https://traefik.local.test',
        ], 60, true);

        $this->completed('Tuti CLI setup complete!', [
            'Create a Laravel project: tuti stack:laravel myapp',
            'Check infrastructure: tuti infra:status',
            'View all commands: tuti list',
        ]);
    }
}
