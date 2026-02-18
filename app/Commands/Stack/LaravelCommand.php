<?php

declare(strict_types=1);

namespace App\Commands\Stack;

use App\Concerns\BuildsProjectUrls;
use App\Concerns\HasBrandedOutput;
use App\Contracts\InfrastructureManagerInterface;
use App\Domain\Project\Project;
use App\Services\Project\ProjectDirectoryService;
use App\Services\Project\ProjectMetadataService;
use App\Services\Project\ProjectStateManagerService;
use App\Services\Stack\Installers\LaravelStackInstaller;
use App\Services\Stack\StackInitializationService;
use App\Services\Stack\StackLoaderService;
use App\Services\Stack\StackRegistryManagerService;
use App\Services\Support\HostsFileService;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

final class LaravelCommand extends Command
{
    use BuildsProjectUrls;
    use HasBrandedOutput;

    protected $signature = 'stack:laravel
                          {project-name? : Project name for fresh installation}
                          {--mode= : Installation mode (fresh, existing)}
                          {--path= : Path for fresh installation (defaults to current directory)}
                          {--services=* : Pre-select services}
                          {--laravel-version= : Specific Laravel version to install}
                          {--force : Force initialization even if .tuti exists}
                          {--skip-start : Skip starting containers after installation}
                          {--skip-migrate : Skip database migrations}';

    protected $description = 'Initialize a Laravel project with Docker stack';

    public function handle(
        LaravelStackInstaller $installer,
        StackRegistryManagerService $registry,
        StackLoaderService $stackLoader,
        ProjectDirectoryService $directoryService,
        StackInitializationService $initService,
        ProjectMetadataService $metaService,
        ProjectStateManagerService $stateManager,
        InfrastructureManagerInterface $infrastructureManager,
        HostsFileService $hostsFileService
    ): int {
        $this->brandedHeader('Laravel Stack Installation');

        try {
            $mode = $this->getInstallationMode($installer);

            if (! $this->preFlightChecks($directoryService)) {
                return self::FAILURE;
            }

            $config = $this->gatherConfiguration($installer, $registry, $stackLoader, $mode);

            if ($config === null) {
                $this->failure('Configuration cancelled.');

                return self::FAILURE;
            }

            if (! $this->confirmConfiguration($config)) {
                $this->warning('Installation cancelled.');

                return self::SUCCESS;
            }

            $hostsAdded = $this->executeInstallation($installer, $initService, $metaService, $stateManager, $infrastructureManager, $hostsFileService, $config);
            $this->displayNextSteps($config, $hostsAdded);

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->failed('Installation failed: ' . $e->getMessage(), [
                'Ensure Docker is running',
                'Check your internet connection',
            ]);

            if ($directoryService->exists()) {
                $this->newLine();
                $this->warning('Cleaning up partial initialization...');
                $directoryService->clean();
                $this->success('Cleanup complete');
            }

            return self::FAILURE;
        }
    }

    private function getInstallationMode(LaravelStackInstaller $installer): string
    {
        $modeOption = $this->option('mode');

        if ($modeOption !== null && in_array($modeOption, ['fresh', 'existing'], true)) {
            return $modeOption;
        }

        $hasExistingProject = $installer->detectExistingProject(getcwd());

        if ($this->option('no-interaction')) {
            return $hasExistingProject ? 'existing' : 'fresh';
        }

        $options = [];

        if ($hasExistingProject) {
            $options['existing'] = '📁 Apply Docker configuration to this existing Laravel project';
            $options['fresh'] = '✨  Create a new Laravel project in a subdirectory';
            $this->success('Existing Laravel project detected in current directory');
            $this->newLine();
        } else {
            $options['fresh'] = '✨  Create a new Laravel project with Docker configuration';
            $options['existing'] = '📁 Apply Docker configuration to existing project (specify path)';
        }

        return select(
            label: 'What would you like to do?',
            options: $options,
            default: $hasExistingProject ? 'existing' : 'fresh'
        );
    }

    private function preFlightChecks(ProjectDirectoryService $directoryService): bool
    {
        if ($directoryService->exists() && ! $this->option('force')) {
            $this->failure('Project already initialized. ".tuti/" directory already exists.');
            $this->hint('Use --force to reinitialize (this will remove existing configuration)');

            return false;
        }

        if ($directoryService->exists() && $this->option('force')) {
            $this->warning('Removing existing .tuti directory...');
            $directoryService->clean();
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function gatherConfiguration(
        LaravelStackInstaller $installer,
        StackRegistryManagerService $registry,
        StackLoaderService $stackLoader,
        string $mode
    ): ?array {
        $config = [
            'mode' => $mode,
            'environment' => $this->getEnvironment(),
            'stack_path' => $installer->getStackPath(),
        ];

        if ($mode === 'fresh') {
            $config['project_name'] = $this->getProjectName();
            $config['project_path'] = $this->getProjectPath($config['project_name']);
            $config['laravel_version'] = $this->option('laravel-version');

            // Gather Laravel-specific options (starter kit, testing, etc.)
            $config['laravel_options'] = $this->gatherLaravelOptions();
        } else {
            $config['project_name'] = $this->getProjectName(basename(getcwd()));
            $config['project_path'] = getcwd();
        }

        // Load stack-specific service registry
        $registry->loadForStack($config['stack_path']);

        $manifest = $stackLoader->load($config['stack_path']);
        $stackLoader->validate($manifest);

        $this->displayStackInfo($manifest);

        // Pass Laravel options to service selection for database defaults
        $config['selected_services'] = $this->selectServices(
            $registry,
            $stackLoader,
            $manifest,
            $config['laravel_options'] ?? []
        );

        if (empty($config['selected_services'])) {
            return null;
        }

        $config['manifest'] = $manifest;

        return $config;
    }

    private function getProjectName(string $default = 'laravel-app'): string
    {
        $projectName = $this->argument('project-name');

        if ($projectName !== null) {
            return $projectName;
        }

        if ($this->option('no-interaction')) {
            return $default;
        }

        return text(
            label: 'Project name:',
            default: $default,
            required: true,
            validate: fn (string $value): ?string => preg_match('/^[a-z0-9_-]+$/', $value)
                ? null
                : 'Project name must contain only lowercase letters, numbers, hyphens, and underscores'
        );
    }

    private function getProjectPath(string $projectName): string
    {
        $pathOption = $this->option('path');

        if ($pathOption !== null) {
            return mb_rtrim($pathOption, '/') . '/' . $projectName;
        }

        return getcwd() . '/' . $projectName;
    }

    private function getEnvironment(): string
    {
        $envOption = $this->option('env');

        if (in_array($envOption, ['dev', 'staging', 'production'], true)) {
            return $envOption;
        }

        if ($this->option('no-interaction')) {
            return 'dev';
        }

        return select(
            label: 'Select environment:',
            options: [
                'dev' => 'Development',
                'staging' => 'Staging',
                'production' => 'Production',
            ],
            default: 'dev'
        );
    }

    /**
     * Gather Laravel-specific options for fresh installations.
     *
     * Prompts for starter kit, authentication, testing framework, boost, and package manager.
     *
     * @return array<string, mixed>
     */
    private function gatherLaravelOptions(): array
    {
        if ($this->option('no-interaction')) {
            return [
                'starter_kit' => 'none',
                'testing' => 'pest',
                'boost' => false,
                'package_manager' => 'npm',
            ];
        }

        $options = [];

        // Starter kit selection
        $options['starter_kit'] = select(
            label: 'Which starter kit would you like to install?',
            options: [
                'none' => 'None (API-only)',
                'react' => 'React',
                'vue' => 'Vue',
                'livewire' => 'Livewire',
                'svelte' => 'Svelte',
            ],
            default: 'none'
        );

        // Authentication - only if starter kit is selected
        if ($options['starter_kit'] !== 'none') {
            $options['authentication'] = select(
                label: 'Which authentication provider?',
                options: [
                    'laravel' => "Laravel's built-in authentication",
                    'workos' => 'WorkOS',
                    'none' => 'No authentication',
                ],
                default: 'laravel'
            );

            // Livewire single-file option
            if ($options['starter_kit'] === 'livewire' && $options['authentication'] === 'laravel') {
                $options['livewire_single_file'] = confirm(
                    label: 'Use single-file Livewire components?',
                    default: true
                );
            }
        }

        // Testing framework
        $options['testing'] = select(
            label: 'Which testing framework?',
            options: [
                'pest' => 'Pest',
                'phpunit' => 'PHPUnit',
            ],
            default: 'pest'
        );

        // Laravel Boost
        $options['boost'] = confirm(
            label: 'Install Laravel Boost?',
            default: false,
            hint: 'Provides performance optimizations and developer experience improvements'
        );

        // Package manager
        $options['package_manager'] = select(
            label: 'Which Node package manager?',
            options: [
                'npm' => 'npm',
                'pnpm' => 'pnpm',
                'bun' => 'Bun',
            ],
            default: $this->detectPackageManager()
        );

        return $options;
    }

    /**
     * Detect the preferred package manager from lock files in current directory.
     */
    private function detectPackageManager(): string
    {
        $cwd = getcwd();

        if (file_exists($cwd . '/bun.lockb')) {
            return 'bun';
        }

        if (file_exists($cwd . '/pnpm-lock.yaml')) {
            return 'pnpm';
        }

        return 'npm';
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function displayStackInfo(array $manifest): void
    {
        $this->box('Stack Info', [
            'Name' => $manifest['name'],
            'Type' => $manifest['type'],
            'Framework' => $manifest['framework'],
            'Description' => $manifest['description'],
        ], 60, true);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $laravelOptions
     * @return array<int, string>
     */
    private function selectServices(
        StackRegistryManagerService $registry,
        StackLoaderService $stackLoader,
        array $manifest,
        array $laravelOptions = []
    ): array {
        $preSelected = $this->option('services');

        if (! empty($preSelected)) {
            return $preSelected;
        }

        if ($this->option('no-interaction')) {
            return $stackLoader->getDefaultServices($manifest);
        }

        $defaults = [];
        $required = $stackLoader->getRequiredServices($manifest);

        // Special handling for database selection in Laravel
        // SQLite is the default and simplest option
        $selectedDatabase = $this->selectDatabase($laravelOptions);

        foreach ($required as $key => $config) {
            $category = $config['category'];

            // Skip database category - we already handled it
            if ($category === 'databases') {
                // Add the selected database (including SQLite for consistency)
                $defaults[] = $selectedDatabase;

                continue;
            }

            $serviceOptions = $config['options'];
            $defaultOption = $config['default'];

            if (count($serviceOptions) === 1) {
                $defaults[] = "{$category}.{$serviceOptions[0]}";

                continue;
            }

            $selected = select(
                label: $config['prompt'] ?? "Select {$key}:",
                options: array_combine(
                    $serviceOptions,
                    array_map(
                        fn (string $service): string => $registry->getService($category, $service)['name'],
                        $serviceOptions
                    )
                ),
                default: $defaultOption
            );

            $defaults[] = "{$category}.{$selected}";
        }

        // Show optional services (Redis, Mailpit, etc.) - always show regardless of database choice
        $optional = $stackLoader->getOptionalServices($manifest);
        $optionalChoices = [];
        $optionalDefaults = [];

        foreach ($optional as $config) {
            $category = $config['category'];
            $serviceOptions = $config['options'];

            foreach ($serviceOptions as $service) {
                $serviceConfig = $registry->getService($category, $service);
                $serviceKey = "{$category}.{$service}";
                $optionalChoices[$serviceKey] = "{$serviceConfig['name']} - {$serviceConfig['description']}";

                if (($config['default'] ?? null) === $service) {
                    $optionalDefaults[] = $serviceKey;
                }
            }
        }

        if ($optionalChoices !== []) {
            $selectedOptional = multiselect(
                label: 'Select optional services:',
                options: $optionalChoices,
                default: $optionalDefaults,
            );

            $defaults = array_merge($defaults, $selectedOptional);
        }

        // Add node service if starter kit is selected (for frontend builds)
        $starterKit = $laravelOptions['starter_kit'] ?? 'none';
        if ($starterKit !== 'none') {
            $defaults[] = 'node.node';
        }

        return array_values(array_unique($defaults));
    }

    /**
     * Select database type with SQLite as the recommended default.
     *
     * @param  array<string, mixed>  $laravelOptions
     */
    private function selectDatabase(array $laravelOptions): string
    {
        if ($this->option('no-interaction')) {
            return 'databases.sqlite';
        }

        return select(
            label: 'Which database will your application use?',
            options: [
                'databases.sqlite' => 'SQLite (Recommended for development)',
                'databases.postgres' => 'PostgreSQL',
                'databases.mysql' => 'MySQL',
                'databases.mariadb' => 'MariaDB',
            ],
            default: 'databases.sqlite',
            hint: 'SQLite is the simplest option - no extra container needed'
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function confirmConfiguration(array $config): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        $this->section('Configuration Summary');

        $modeLabel = $config['mode'] === 'fresh' ? '✨ Fresh installation' : '📁 Apply to existing';
        $this->keyValue('Mode', $modeLabel);
        $this->keyValue('Project', $config['project_name']);
        $this->keyValue('Path', $config['project_path']);
        $this->keyValue('Environment', $config['environment']);

        $this->header('Services');
        foreach ($config['selected_services'] as $service) {
            $this->bullet($service);
        }

        $this->newLine();

        return confirm('Proceed with installation?', true);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return bool Whether hosts file entry was added
     */
    private function executeInstallation(
        LaravelStackInstaller $installer,
        StackInitializationService $initService,
        ProjectMetadataService $metaService,
        ProjectStateManagerService $stateManager,
        InfrastructureManagerInterface $infrastructureManager,
        HostsFileService $hostsFileService,
        array $config
    ): bool {
        $hostsAdded = false;
        $laravelOptions = $config['laravel_options'] ?? [];

        if ($config['mode'] === 'fresh') {
            $this->note('Creating Laravel project...');
            $this->hint('This may take a few minutes on first run (downloading PHP image)');
            $this->newLine();

            // Extract database selection from services
            $selectedDatabase = $this->extractDatabaseFromServices($config['selected_services']);

            // Build options including starter kit configuration
            $options = [
                'interactive' => false, // We're using composer create-project, not Laravel installer
                'no_interaction' => true,
                'prefer_dist' => true,
                'database' => $selectedDatabase,
                // Pass Laravel options to installer
                'starter_kit' => $laravelOptions['starter_kit'] ?? 'none',
                'authentication' => $laravelOptions['authentication'] ?? 'laravel',
                'livewire_single_file' => $laravelOptions['livewire_single_file'] ?? false,
            ];

            if ($config['laravel_version'] !== null) {
                $options['laravel_version'] = $config['laravel_version'];
            }

            // Create Laravel project - Laravel's scripts handle key:generate and migrate
            spin(
                fn (): bool => $installer->installFresh(
                    $config['project_path'],
                    $config['project_name'],
                    $options
                ),
                'Creating Laravel project...'
            );

            $this->success('Laravel project created');

            // Install Pest if selected
            if (($laravelOptions['testing'] ?? 'phpunit') === 'pest') {
                $this->note('Installing Pest...');
                spin(
                    fn (): bool => $installer->installPest($config['project_path']),
                    'Installing Pest with drift conversion...'
                );
                $this->success('Pest installed');
            }

            // Install Boost if selected
            if ($laravelOptions['boost'] ?? false) {
                $this->note('Installing Laravel Boost...');
                spin(
                    fn (): bool => $installer->installBoost($config['project_path']),
                    'Installing Laravel Boost...'
                );
                $this->success('Laravel Boost installed');
            }

            // Configure .env for Docker database (if not SQLite)
            if ($selectedDatabase !== null && $selectedDatabase !== 'databases.sqlite') {
                $this->note('Configuring Docker database...');
                $installer->configureDefaultDatabaseConnection(
                    $config['project_path'],
                    $selectedDatabase,
                    $config['project_name']
                );
                $this->success('Database configured');
            }

            // Install additional packages if needed (e.g., Horizon)
            $this->installRequiredPackages($installer, $config);

            chdir($config['project_path']);
        }

        // Initialize the stack (Docker configuration)
        spin(
            fn (): bool => $initService->initialize(
                $config['stack_path'],
                $config['project_name'],
                $config['environment'],
                $config['selected_services']
            ),
            'Applying Docker stack configuration...'
        );

        $this->success('Stack initialized');

        // Configure .env for selected services (Redis, etc.)
        $this->configureEnvForServices($config);

        // Start containers and run migrations (unless skipped)
        if (! $this->option('skip-start')) {
            $hostsAdded = $this->startContainersAndMigrate(
                $config,
                $metaService,
                $stateManager,
                $infrastructureManager,
                $hostsFileService,
                $laravelOptions
            );
        }

        return $hostsAdded;
    }

    /**
     * Extract database service key from selected services.
     *
     * @param  array<int, string>  $selectedServices
     */
    private function extractDatabaseFromServices(array $selectedServices): ?string
    {
        foreach ($selectedServices as $service) {
            if (str_starts_with($service, 'databases.')) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Start containers and run migrations after installation.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $laravelOptions
     * @return bool Whether hosts file entry was added
     */
    private function startContainersAndMigrate(
        array $config,
        ProjectMetadataService $metaService,
        ProjectStateManagerService $stateManager,
        InfrastructureManagerInterface $infrastructureManager,
        HostsFileService $hostsFileService,
        array $laravelOptions = []
    ): bool {
        $this->section('Starting Project');

        // Ensure infrastructure is ready
        $this->note('Checking infrastructure...');
        if (! $infrastructureManager->isRunning()) {
            spin(
                fn (): bool => $infrastructureManager->ensureReady(),
                'Starting Traefik infrastructure...'
            );
        }
        $this->success('Infrastructure ready');

        // Create project object
        $projectRoot = $config['project_path'];
        $projectConfig = $metaService->load();
        $project = new Project($projectRoot, $projectConfig);

        // Start containers
        $this->note('Starting containers...');
        try {
            $stateManager->start($project);
            $this->success('Containers started');
        } catch (Throwable $e) {
            $this->warning('Failed to start containers: ' . $e->getMessage());
            $this->hint('Run "tuti local:start" manually to start the project');

            return false;
        }

        // Run migrations (unless skipped) - only if not using SQLite (SQLite already migrated during create-project)
        $selectedDatabase = $this->extractDatabaseFromServices($config['selected_services']);
        if (! $this->option('skip-migrate') && $selectedDatabase !== null && $selectedDatabase !== 'databases.sqlite') {
            $this->runMigrations($projectRoot, $config['project_name']);
        }

        // Run npm install/build if starter kit was selected
        $starterKit = $laravelOptions['starter_kit'] ?? 'none';
        if ($starterKit !== 'none') {
            $this->note('Installing npm dependencies...');
            $packageManager = $laravelOptions['package_manager'] ?? 'npm';
            $this->runNpmBuild($projectRoot, $config['project_name'], $packageManager);
        }

        // Handle hosts file management
        return $this->handleHostsFile($hostsFileService, $config['project_name']);
    }

    /**
     * Run npm install and build using the node container.
     */
    private function runNpmBuild(string $projectRoot, string $projectName, string $packageManager): void
    {
        // Install pnpm or bun if selected (runs in node container)
        if ($packageManager !== 'npm') {
            Process::path($projectRoot)->run([
                'docker',
                'compose',
                'run',
                '--rm',
                'node',
                'sh',
                '-c',
                "npm install -g {$packageManager}",
            ]);
        }

        // Run npm install using docker compose run node
        $installCmd = $packageManager === 'npm' ? 'npm install' : "{$packageManager} install";
        $installResult = Process::path($projectRoot)->timeout(300)->run([
            'docker',
            'compose',
            'run',
            '--rm',
            'node',
            'sh',
            '-c',
            $installCmd,
        ]);

        if ($installResult->successful()) {
            $this->success('npm dependencies installed');
        } else {
            $this->warning('npm install had issues');
            $this->hint("Run manually: docker compose run --rm node {$installCmd}");

            return;
        }

        // Run build using docker compose run node
        $buildCmd = $packageManager === 'npm' ? 'npm run build' : "{$packageManager} run build";
        $buildResult = Process::path($projectRoot)->timeout(300)->run([
            'docker',
            'compose',
            'run',
            '--rm',
            'node',
            'sh',
            '-c',
            $buildCmd,
        ]);

        if ($buildResult->successful()) {
            $this->success('Assets built');
        } else {
            $this->warning('npm build had issues');
            $this->hint("Run manually: docker compose run --rm node {$buildCmd}");
        }
    }

    /**
     * Handle adding project domain to /etc/hosts.
     *
     * @return bool Whether hosts file entry was added (or already exists)
     */
    private function handleHostsFile(HostsFileService $hostsFileService, string $projectName): bool
    {
        $domain = $projectName . '.local.test';

        // Check if entry already exists
        if ($hostsFileService->entryExists($domain)) {
            $this->success("Domain {$domain} already in /etc/hosts");

            return true;
        }

        // Check if we can potentially modify hosts file
        if (! $hostsFileService->canModifyHosts()) {
            $this->warning('Cannot modify /etc/hosts automatically');
            $this->hint('Add manually: ' . $hostsFileService->buildEntry($domain));

            return false;
        }

        // Ask user if they want to add to hosts file
        if ($this->option('no-interaction')) {
            return false;
        }

        $this->newLine();
        $addHosts = confirm(
            label: "Add https://{$domain} to /etc/hosts automatically?",
            hint: 'This requires sudo privileges'
        );

        if (! $addHosts) {
            $this->hint('Add manually: ' . $hostsFileService->buildEntry($domain));

            return false;
        }

        // Try to add entry
        if ($hostsFileService->addEntry($domain)) {
            $this->success("Domain {$domain} added to /etc/hosts");

            return true;
        }

        $this->warning('Failed to add domain to /etc/hosts (sudo required)');
        $this->hint('Add manually: ' . $hostsFileService->buildEntry($domain));

        return false;
    }

    /**
     * Run database migrations for Laravel projects.
     */
    private function runMigrations(string $projectRoot, string $projectName): void
    {
        if (! file_exists($projectRoot . '/artisan')) {
            return;
        }

        $this->note('Running database migrations...');

        // Container name: {project}_{env}_app
        $containerName = "{$projectName}_dev_app";

        // Run migrations
        $result = Process::timeout(120)->run([
            'docker',
            'exec',
            $containerName,
            'php',
            'artisan',
            'migrate',
            '--force',
        ]);

        if ($result->successful()) {
            $this->success('Database migrations completed');
        } else {
            $this->warning('Migrations had issues');
            $this->hint('Run manually: docker exec ' . $containerName . ' php artisan migrate');
        }
    }

    /**
     * Install required packages for selected services.
     *
     * @param  array<string, mixed>  $config
     */
    private function installRequiredPackages(LaravelStackInstaller $installer, array $config): void
    {
        $packages = $this->getRequiredPackages($config['selected_services']);

        foreach ($packages as $package => $artisanCommand) {
            $this->note("Installing {$package}...");

            spin(
                fn (): bool => $installer->runComposerRequire($config['project_path'], $package),
                "Installing {$package}..."
            );

            $this->success("Installed {$package}");

            if ($artisanCommand !== null) {
                spin(
                    fn (): bool => $installer->runArtisan($config['project_path'], $artisanCommand),
                    "Running php artisan {$artisanCommand}..."
                );
            }
        }
    }

    /**
     * Get required packages for selected services.
     *
     * @param  array<int, string>  $selectedServices
     * @return array<string, string|null>
     */
    private function getRequiredPackages(array $selectedServices): array
    {
        $packages = [];

        foreach ($selectedServices as $serviceKey) {
            [$category, $serviceName] = explode('.', $serviceKey);

            if ($category === 'workers' && $serviceName === 'horizon') {
                $packages['laravel/horizon'] = 'horizon:install';
            }
            // Add more packages here as needed
        }

        return $packages;
    }

    /**
     * Configure .env for selected services.
     *
     * @param  array<string, mixed>  $config
     */
    private function configureEnvForServices(array $config): void
    {
        $projectPath = $config['project_path'];
        $envPath = $projectPath . '/.env';

        if (! file_exists($envPath)) {
            return;
        }

        foreach ($config['selected_services'] as $serviceKey) {
            [$category, $serviceName] = explode('.', (string) $serviceKey);

            // Configure for Horizon
            if ($category === 'workers' && $serviceName === 'horizon') {
                $this->updateEnvValue($projectPath, 'QUEUE_CONNECTION', 'redis');
                $this->updateEnvValue($projectPath, 'REDIS_HOST', 'redis');
                $this->updateEnvValue($projectPath, 'REDIS_PORT', '6379');
            }

            // Configure for Redis cache
            if ($category === 'cache' && $serviceName === 'redis') {
                $this->updateEnvValue($projectPath, 'CACHE_STORE', 'redis');
                $this->updateEnvValue($projectPath, 'SESSION_DRIVER', 'redis');
                $this->updateEnvValue($projectPath, 'REDIS_HOST', 'redis');
            }
        }
    }

    /**
     * Update or add a value in the .env file.
     */
    private function updateEnvValue(string $projectPath, string $key, string $value): void
    {
        $envPath = $projectPath . '/.env';

        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        if (preg_match("/^{$key}=/m", $content)) {
            $content = preg_replace("/^{$key}=.*$/m", "{$key}={$value}", $content);
        } else {
            $content = mb_rtrim($content) . "\n{$key}={$value}\n";
        }

        file_put_contents($envPath, $content);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function displayNextSteps(array $config, bool $hostsAdded): void
    {
        $this->section('Project Structure');

        if ($config['mode'] === 'fresh') {
            $this->bullet("{$config['project_name']}/", 'cyan');
            $this->subItem('app/ (Laravel application)');
        }

        $this->bullet('.tuti/', 'cyan');
        $this->subItem('config.json');
        $this->subItem('docker/');
        $this->subItem('docker-compose.yml');
        $this->subItem('environments/');

        $projectDomain = $config['project_name'] . '.local.test';
        $isRunning = ! $this->option('skip-start');

        // Build next steps based on whether project is running and hosts were added
        $nextSteps = [];

        if ($config['mode'] === 'fresh') {
            $nextSteps[] = "cd {$config['project_name']}";
        }

        if (! $isRunning) {
            $nextSteps[] = 'tuti local:start';
        }

        // Only show hosts step if not already added
        if (! $hostsAdded) {
            $nextSteps[] = 'Add to /etc/hosts: 127.0.0.1 ' . $projectDomain;
        }

        $nextSteps[] = 'Visit: https://' . $projectDomain;

        if ($isRunning) {
            $this->completed('Laravel stack installed and running!', $nextSteps);
        } else {
            $this->completed('Laravel stack installed successfully!', $nextSteps);
        }

        // Build dynamic URLs based on selected services
        $urls = $this->buildProjectUrlsFromServices($config['selected_services'], $projectDomain);

        $this->newLine();
        $this->box('Project URLs', $urls, 60, true);

        if ($isRunning) {
            $this->hint('Use "tuti local:status" to check running services');
        } else {
            $this->hint('Run "tuti local:start" to start the project');
        }
    }
}
