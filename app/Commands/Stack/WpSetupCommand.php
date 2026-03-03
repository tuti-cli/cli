<?php

declare(strict_types=1);

namespace App\Commands\Stack;

use App\Concerns\HasBrandedOutput;
use App\Services\Security\CredentialValidationService;
use App\Services\Stack\Installers\WordPressStackInstaller;
use Illuminate\Support\Facades\Process;
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

    public function handle(
        WordPressStackInstaller $installer,
        CredentialValidationService $credentialValidator,
    ): int {
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
            $checkArguments = ['core', 'is-installed'];
            if ($installer->runWpCli($projectPath, $checkArguments)) {
                $this->warning('WordPress is already installed.');
                $this->hint('Use --force to reinstall, or visit the site directly.');

                $this->newLine();
                $this->box('Access Your Site', [
                    'Site URL' => $autoSetup['site_url'],
                    'Admin URL' => $autoSetup['site_url'] . '/wp-admin',
                    'Username' => $autoSetup['admin_user'],
                    'Password' => $autoSetup['admin_password'],
                ], 55, true);

                $this->displayCredentialWarning($credentialValidator, $autoSetup);

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

        // Use array syntax for safe command execution (no shell injection)
        $wpInstallArguments = [
            'core',
            'install',
            "--url={$autoSetup['site_url']}",
            "--title={$autoSetup['site_title']}",
            "--admin_user={$autoSetup['admin_user']}",
            "--admin_password={$autoSetup['admin_password']}",
            "--admin_email={$autoSetup['admin_email']}",
            '--skip-email',
        ];

        $result = spin(
            fn (): bool => $installer->runWpCli($projectPath, $wpInstallArguments),
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

        $this->displayCredentialWarning($credentialValidator, $autoSetup);

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

        $process = Process::run([
            'docker',
            'ps',
            '--filter', "name={$containerName}",
            '--filter', 'status=running',
            '-q',
        ]);

        return $process->successful() && mb_trim($process->output()) !== '';
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
            $healthyProcess = Process::run([
                'docker',
                'ps',
                '--filter', "name={$containerName}",
                '--filter', 'health=healthy',
                '--filter', 'status=running',
                '-q',
            ]);

            if ($healthyProcess->successful() && mb_trim($healthyProcess->output()) !== '') {
                return true;
            }

            // Fallback: just check if container is running (some images don't have healthcheck)
            $runningProcess = Process::run([
                'docker',
                'ps',
                '--filter', "name={$containerName}",
                '--filter', 'status=running',
                '-q',
            ]);

            // If container is running but not healthy yet, wait more
            // If container is running and has been for a while, assume it's ready
            if ($runningProcess->successful() && mb_trim($runningProcess->output()) !== '' && $attempt >= 10) {
                return true;
            }

            $attempt++;
            usleep(500000); // 0.5 seconds
        }

        return false;
    }

    /**
     * Display warning if development credentials are detected.
     *
     * @param  array<string, mixed>  $autoSetup
     */
    private function displayCredentialWarning(CredentialValidationService $validator, array $autoSetup): void
    {
        $credentials = [
            'admin_user' => $autoSetup['admin_user'] ?? '',
            'admin_password' => $autoSetup['admin_password'] ?? '',
        ];

        $result = $validator->validateCredentials($credentials);

        if ($result['has_issues']) {
            $warning = $validator->formatWarning($result['issues']);
            $this->warningBox($warning['title'], $warning['lines']);
        }
    }
}
