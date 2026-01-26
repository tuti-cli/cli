<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

final class InstallCommand extends Command
{
    protected $signature = 'install
                          {--force : Force reinstallation of global directory}';

    protected $description = 'Set up tuti CLI global configuration and directories';

    public function handle(): int
    {
        $this->displayHeader();

        try {
            $globalPath = $this->setupGlobalDirectory();
            $this->createGlobalConfig($globalPath);
            $this->displaySuccess($globalPath);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Installation failed: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Please try running with sudo or check directory permissions.');

            return self::FAILURE;
        }
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════╗');
        $this->line('║                                                          ║');
        $this->line('║                 🚀 Tuti CLI Setup                        ║');
        $this->line('║                                                          ║');
        $this->line('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function setupGlobalDirectory(): string
    {
        $globalPath = $this->getGlobalTutiPath();

        $this->info("Setting up global directory: {$globalPath}");

        if (is_dir($globalPath) && ! $this->option('force')) {
            $this->line('  Global directory already exists.');

            return $globalPath;
        }

        // Create main directory
        $this->createDirectory($globalPath);
        $this->line('  ✓ Created: ' . $globalPath);

        // Create subdirectories
        $subdirs = ['stacks', 'cache', 'logs'];
        foreach ($subdirs as $subdir) {
            $path = $globalPath . DIRECTORY_SEPARATOR . $subdir;
            $this->createDirectory($path);
            $this->line("  ✓ Created: {$subdir}/");
        }

        return $globalPath;
    }

    private function createGlobalConfig(string $globalPath): void
    {
        $configPath = $globalPath . DIRECTORY_SEPARATOR . 'config.json';

        if (file_exists($configPath) && ! $this->option('force')) {
            $this->line('  Config already exists.');

            return;
        }

        $config = [
            'version' => '1.0.0',
            'auto_update_stacks' => true,
            'telemetry' => false,
            'default_environment' => 'dev',
            'created_at' => date('c'),
        ];

        $result = file_put_contents(
            $configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($result === false) {
            throw new \RuntimeException("Failed to create config file: {$configPath}");
        }

        $this->line('  ✓ Created: config.json');
    }

    private function createDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new \RuntimeException("Failed to create directory: {$path}");
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
            throw new \RuntimeException(
                'Unable to determine home directory. Please set the HOME environment variable.'
            );
        }

        return rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.tuti';
    }

    private function displaySuccess(string $globalPath): void
    {
        $this->newLine();
        $this->components->info('✅ Tuti CLI setup complete!');
        $this->newLine();

        $this->info('Global directory structure:');
        $this->line("  {$globalPath}/");
        $this->line('  ├── config.json     (global configuration)');
        $this->line('  ├── stacks/         (cached stack templates)');
        $this->line('  ├── cache/          (temporary files)');
        $this->line('  └── logs/           (global logs)');
        $this->newLine();

        $this->info('Next steps:');
        $this->line('  1. Initialize a new project:  tuti init');
        $this->line('  2. Or use Laravel stack:      tuti stack:laravel myapp');
        $this->newLine();
    }
}
