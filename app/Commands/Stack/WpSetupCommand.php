<?php

declare(strict_types=1);

namespace App\Commands\Stack;

use App\Concerns\HasBrandedOutput;
use App\Enums\ContainerNamingEnum;
use App\Enums\MultisiteModeEnum;
use App\Enums\WordPressTypeEnum;
use App\Services\Security\CredentialValidationService;
use App\Services\Stack\Installers\WordPressStackInstaller;
use App\Services\WordPress\WordPressSetupService;
use Illuminate\Support\Facades\Process;
use JsonException;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

/**
 * WordPress auto-setup command.
 *
 * Runs WordPress installation using WP-CLI with pre-configured dev credentials.
 * Supports multiple installation types (Standard, Bedrock) and multisite modes.
 * Must be run after containers are started with `tuti local:start`.
 *
 * @example
 *   # Interactive setup (default)
 *   tuti wp:setup
 *
 *   # Non-interactive with defaults
 *   tuti wp:setup --no-interactive
 *
 *   # Non-interactive with specific options
 *   tuti wp:setup --no-interactive --type=bedrock
 *   tuti wp:setup --no-interactive --multisite=subdomain
 *   tuti wp:setup --no-interactive --type=bedrock --multisite=subdirectory
 *
 *   # Force reinstall
 *   tuti wp:setup --force
 */
final class WpSetupCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'wp:setup
        {--type=single : Installation type (single, bedrock)}
        {--multisite=none : Multisite mode (none, subdomain, subdirectory)}
        {--wp-version=latest : WordPress version}
        {--no-interactive : Skip prompts, use defaults}
        {--force : Force setup even if WordPress is already installed}';

    protected $description = 'Complete WordPress installation with dev credentials (run after local:start)';

    public function handle(
        WordPressStackInstaller $installer,
        CredentialValidationService $credentialValidator,
        WordPressSetupService $setupService,
    ): int {
        $this->brandedHeader('WordPress Setup');

        $projectPath = getcwd();
        $configPath = $projectPath . '/.tuti/config.json';
        $autoSetupPath = $projectPath . '/.tuti/auto-setup.json';

        // Check if we're in a WordPress project
        if (! file_exists($configPath)) {
            $this->failure('Not a tuti project. Run this command from a WordPress project directory.');

            return self::FAILURE;
        }

        // Load project config
        try {
            $projectConfig = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException("Invalid JSON in config: {$configPath}");
        }
        $projectName = $projectConfig['project']['name'] ?? basename($projectPath);

        // Load auto-setup config or create defaults
        $autoSetup = $this->loadAutoSetupConfig($autoSetupPath, $projectName);

        // Gather configuration (interactive or from flags)
        $options = $this->gatherConfiguration($autoSetup);

        // Merge type, multisite, version into options
        $options = array_merge($options, [
            'site_url' => $autoSetup['site_url'],
            'site_title' => $autoSetup['site_title'],
            'admin_user' => $autoSetup['admin_user'],
            'admin_password' => $autoSetup['admin_password'],
            'admin_email' => $autoSetup['admin_email'],
        ]);

        // Display selected configuration in non-interactive mode
        if ($this->option('no-interactive')) {
            $this->displayNonInteractiveConfig($options);
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

        // Configure multisite if needed
        $multisiteMode = MultisiteModeEnum::tryFrom($options['multisite'] ?? 'none') ?? MultisiteModeEnum::NONE;
        if ($multisiteMode !== MultisiteModeEnum::NONE) {
            $this->configureMultisite($projectPath, $multisiteMode, $installer, $autoSetup);
        }

        // Save configuration to .tuti/config.json
        $this->saveWordPressConfig($configPath, $projectConfig, $options);

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
            'Type' => WordPressTypeEnum::tryFrom($options['type'] ?? 'single')?->label() ?? 'Standard WordPress',
            'Multisite' => $multisiteMode->label(),
        ], 55, true);

        $this->displayCredentialWarning($credentialValidator, $autoSetup);

        $this->completed('WordPress is ready!', [
            'Visit: ' . $autoSetup['site_url'],
            'Login: ' . $autoSetup['site_url'] . '/wp-admin',
        ]);

        return self::SUCCESS;
    }

    /**
     * Load auto-setup configuration or create defaults.
     *
     * @param  string  $autoSetupPath  Path to auto-setup.json
     * @param  string  $projectName  Project name for defaults
     * @return array<string, mixed> Auto-setup configuration
     */
    private function loadAutoSetupConfig(string $autoSetupPath, string $projectName): array
    {
        if (file_exists($autoSetupPath)) {
            try {
                return json_decode(file_get_contents($autoSetupPath), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException("Invalid JSON in auto-setup config: {$autoSetupPath}");
            }
        }

        // Generate defaults based on project name
        return [
            'enabled' => true,
            'site_url' => "https://{$projectName}.local.test",
            'site_title' => ucfirst(str_replace(['-', '_'], ' ', $projectName)),
            'admin_user' => 'admin',
            'admin_password' => 'admin',
            'admin_email' => 'admin@localhost.test',
        ];
    }

    /**
     * Gather configuration from interactive prompts or flags.
     *
     * @param  array<string, mixed>  $autoSetup  Auto-setup configuration
     * @return array<string, mixed> Gathered options
     */
    private function gatherConfiguration(array $autoSetup): array
    {
        $noInteractive = $this->option('no-interactive');

        if ($noInteractive) {
            return [
                'type' => $this->option('type') ?? 'single',
                'multisite' => $this->option('multisite') ?? 'none',
                'version' => $this->option('wp-version') ?? 'latest',
            ];
        }

        // Interactive prompts
        $this->newLine();
        $this->note('Configure your WordPress installation');

        // Installation type
        $typeChoice = select(
            'Select installation type:',
            [
                'single' => 'Standard WordPress (default)',
                'bedrock' => 'Bedrock (Composer-based)',
            ],
            default: $this->option('type') ?? 'single'
        );

        // Multisite mode
        $multisiteChoice = select(
            'Select multisite mode:',
            [
                'none' => 'None (single site)',
                'subdirectory' => 'Multisite (Subdirectories)',
                'subdomain' => 'Multisite (Subdomains)',
            ],
            default: $this->option('multisite') ?? 'none'
        );

        // Validate Bedrock + multisite combination
        if ($typeChoice === 'bedrock' && $multisiteChoice !== 'none') {
            $this->warning('Bedrock multisite support is not yet available.');
            $multisiteChoice = 'none';
        }

        return [
            'type' => $typeChoice,
            'multisite' => $multisiteChoice,
            'version' => $this->option('wp-version') ?? 'latest',
        ];
    }

    /**
     * Display configuration in non-interactive mode.
     *
     * @param  array<string, mixed>  $options  Configuration options
     */
    private function displayNonInteractiveConfig(array $options): void
    {
        $type = WordPressTypeEnum::tryFrom($options['type'] ?? 'single') ?? WordPressTypeEnum::SINGLE;
        $multisite = MultisiteModeEnum::tryFrom($options['multisite'] ?? 'none') ?? MultisiteModeEnum::NONE;

        $this->note('Using non-interactive mode');
        $this->keyValue('Type', $type->label());
        $this->keyValue('Multisite', $multisite->label());
        $this->keyValue('Version', $options['version'] ?? 'latest');
    }

    /**
     * Configure multisite for WordPress.
     *
     * @param  string  $projectPath  Project directory path
     * @param  MultisiteModeEnum  $mode  Multisite mode
     * @param  WordPressStackInstaller  $installer  WordPress installer
     * @param  array<string, mixed>  $autoSetup  Auto-setup configuration
     */
    private function configureMultisite(
        string $projectPath,
        MultisiteModeEnum $mode,
        WordPressStackInstaller $installer,
        array $autoSetup
    ): void {
        $this->note('Configuring multisite...');

        // Run multisite conversion via WP-CLI
        $multisiteArgs = ['core', 'multisite-convert'];

        if ($mode === MultisiteModeEnum::SUBDOMAIN) {
            $multisiteArgs[] = '--subdomains';
        }

        $result = spin(
            fn (): bool => $installer->runWpCli($projectPath, $multisiteArgs),
            'Converting to multisite...'
        );

        if ($result) {
            $this->success('Multisite configured successfully');
        } else {
            $this->warning('Multisite conversion may require manual configuration');
        }
    }

    /**
     * Save WordPress configuration to .tuti/config.json.
     *
     * @param  string  $configPath  Path to config.json
     * @param  array<string, mixed>  $projectConfig  Existing project config
     * @param  array<string, mixed>  $options  WordPress options
     */
    private function saveWordPressConfig(string $configPath, array $projectConfig, array $options): void
    {
        $projectConfig['wordpress'] = [
            'type' => $options['type'] ?? 'single',
            'multisite' => $options['multisite'] ?? 'none',
            'version' => $options['version'] ?? 'latest',
        ];

        file_put_contents($configPath, json_encode($projectConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->success('Configuration saved to .tuti/config.json');
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
            try {
                $config = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
                $projectName = $config['project']['name'] ?? $projectName;
            } catch (JsonException) {
                // Use directory name as fallback if config is invalid
            }
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
            try {
                $config = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
                $projectName = $config['project']['name'] ?? $projectName;
            } catch (JsonException) {
                // Use directory name as fallback if config is invalid
            }
        }

        $containerName = ContainerNamingEnum::Container->name($projectName, 'database');
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
