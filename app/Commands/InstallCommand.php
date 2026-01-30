<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\HasBrandedOutput;
use Exception;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

final class InstallCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'install
                          {--force : Force reinstallation of global directory}';

    protected $description = 'Set up tuti CLI global configuration and directories';

    public function handle(): int
    {
        $this->welcomeBanner();

        try {
            $globalPath = $this->setupGlobalDirectory();
            $this->createGlobalConfig($globalPath);
            $this->displaySuccess($globalPath);

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->failed('Installation failed: ' . $e->getMessage(), [
                'Try running with sudo or check directory permissions',
            ]);

            return self::FAILURE;
        }
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
        $subdirs = ['stacks', 'cache', 'logs'];
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

    private function displaySuccess(string $globalPath): void
    {
        $this->section('Directory Structure');

        $this->box('Global Tuti Directory', [
            'Path' => $globalPath,
            'config.json' => 'global configuration',
            'stacks/' => 'cached stack templates',
            'cache/' => 'temporary files',
            'logs/' => 'global logs',
        ], 60, true);

        $this->completed('Tuti CLI setup complete!', [
            'Initialize a new project: tuti init',
            'Or use Laravel stack: tuti stack:laravel myapp',
        ]);
    }
}
