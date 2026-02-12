<?php

declare(strict_types=1);

namespace App\Commands\Stack;

use App\Concerns\HasBrandedOutput;
use App\Services\Stack\Installers\WordPressStackInstaller;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\spin;

/**
 * WordPress auto-setup command.
 *
 * Runs WordPress installation using WP-CLI with pre-configured dev credentials.
 * Must be run after containers are started with `tuti local:start`.
 */
final class WpSetupCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'wp:setup
                          {--force : Force setup even if WordPress is already installed}';

    protected $description = 'Complete WordPress installation with dev credentials (run after local:start)';

    public function handle(WordPressStackInstaller $installer): int
    {
        $this->brandedHeader('WordPress Auto-Setup');

        $projectPath = getcwd();
        $configPath = $projectPath . '/.tuti/config.json';
        $autoSetupPath = $projectPath . '/.tuti/auto-setup.json';

        // Check if we're in a WordPress project
        if (! file_exists($configPath)) {
            $this->failure('Not a tuti project. Run this command from a WordPress project directory.');

            return self::FAILURE;
        }

        // Load project config
        $projectConfig = json_decode(file_get_contents($configPath), true);
        $projectName = $projectConfig['project']['name'] ?? basename($projectPath);

        // Load auto-setup config or create defaults
        if (file_exists($autoSetupPath)) {
            $autoSetup = json_decode(file_get_contents($autoSetupPath), true);
        } else {
            // Generate defaults based on project name
            $autoSetup = [
                'enabled' => true,
                'site_url' => "https://{$projectName}.local.test",
                'site_title' => ucfirst(str_replace(['-', '_'], ' ', $projectName)),
                'admin_user' => 'admin',
                'admin_password' => 'admin',
                'admin_email' => 'admin@localhost.test',
            ];
        }

        // Check if containers are running
        $this->note('Checking if containers are running...');

        if (! $this->areContainersRunning($projectPath)) {
            $this->failure('Containers are not running.');
            $this->hint('Start containers first with: tuti local:start');

            return self::FAILURE;
        }

        $this->success('Containers are running');

        // Check if WordPress is already installed
        if (! $this->option('force')) {
            $checkCommand = 'core is-installed';
            if ($installer->runWpCli($projectPath, $checkCommand)) {
                $this->warning('WordPress is already installed.');
                $this->hint('Use --force to reinstall, or visit the site directly.');

                $this->newLine();
                $this->box('Access Your Site', [
                    'Site URL' => $autoSetup['site_url'],
                    'Admin URL' => $autoSetup['site_url'] . '/wp-admin',
                    'Username' => $autoSetup['admin_user'],
                    'Password' => $autoSetup['admin_password'],
                ], 55, true);

                return self::SUCCESS;
            }
        }

        // Wait for database to be ready
        $this->note('Waiting for database to be ready...');

        $dbReady = spin(
            fn (): bool => $this->waitForDatabase($projectPath),
            'Checking database connection...'
        );

        if (! $dbReady) {
            $this->failure('Database is not ready. Please try again in a moment.');

            return self::FAILURE;
        }

        $this->success('Database is ready');

        // Run WordPress installation
        $this->note('Installing WordPress...');

        $wpInstallCommand = sprintf(
            'core install --url="%s" --title="%s" --admin_user="%s" --admin_password="%s" --admin_email="%s" --skip-email',
            $autoSetup['site_url'],
            $autoSetup['site_title'],
            $autoSetup['admin_user'],
            $autoSetup['admin_password'],
            $autoSetup['admin_email']
        );

        $result = spin(
            fn (): bool => $installer->runWpCli($projectPath, $wpInstallCommand),
            'Running WordPress installation...'
        );

        if (! $result) {
            $this->failure('WordPress installation failed.');
            $this->hint('Check container logs with: docker logs <container_name>');

            return self::FAILURE;
        }

        $this->success('WordPress installed successfully!');

        // Mark auto-setup as completed
        $autoSetup['completed'] = true;
        $autoSetup['completed_at'] = date('c');
        file_put_contents($autoSetupPath, json_encode($autoSetup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->newLine();
        $this->box('Dev Admin Credentials', [
            'Site URL' => $autoSetup['site_url'],
            'Admin URL' => $autoSetup['site_url'] . '/wp-admin',
            'Username' => $autoSetup['admin_user'],
            'Password' => $autoSetup['admin_password'],
            'Email' => $autoSetup['admin_email'],
        ], 55, true);

        $this->completed('WordPress is ready!', [
            'Visit: ' . $autoSetup['site_url'],
            'Login: ' . $autoSetup['site_url'] . '/wp-admin',
        ]);

        return self::SUCCESS;
    }

    /**
     * Check if Docker containers are running.
     */
    private function areContainersRunning(string $projectPath): bool
    {
        // Get project name from config
        $projectName = basename($projectPath);
        $configPath = $projectPath . '/.tuti/config.json';

        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $projectName = $config['project']['name'] ?? $projectName;
        }

        // Check if app container is running
        $containerName = "{$projectName}_dev_app";
        $command = sprintf('docker ps --filter "name=%s" --filter "status=running" -q 2>/dev/null', $containerName);

        exec($command, $output, $exitCode);

        return $exitCode === 0 && $output !== [];
    }

    /**
     * Wait for database to be ready.
     */
    private function waitForDatabase(string $projectPath): bool
    {
        // Get project name from config
        $projectName = basename($projectPath);
        $configPath = $projectPath . '/.tuti/config.json';

        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $projectName = $config['project']['name'] ?? $projectName;
        }

        $containerName = "{$projectName}_dev_database";
        $maxAttempts = 30;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            // First try: check if container is running AND healthy
            $healthyCommand = sprintf(
                'docker ps --filter "name=%s" --filter "health=healthy" --filter "status=running" -q 2>/dev/null',
                $containerName
            );

            exec($healthyCommand, $healthyOutput, $healthyExitCode);

            if ($healthyExitCode === 0 && $healthyOutput !== []) {
                return true;
            }

            // Fallback: just check if container is running (some images don't have healthcheck)
            $runningCommand = sprintf(
                'docker ps --filter "name=%s" --filter "status=running" -q 2>/dev/null',
                $containerName
            );

            exec($runningCommand, $runningOutput, $runningExitCode);

            // If container is running but not healthy yet, wait more
            // If container is running and has been for a while, assume it's ready
            if ($runningExitCode === 0 && $runningOutput !== [] && $attempt >= 10) {
                return true;
            }

            // Clear outputs for next iteration
            $healthyOutput = [];
            $runningOutput = [];
            $attempt++;
            usleep(500000); // 0.5 seconds
        }

        return false;
    }
}
