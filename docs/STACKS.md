# Stack System Documentation

## Overview

The tuti-cli stack system allows you to quickly scaffold and configure projects with Docker support. Stacks provide predefined configurations for different frameworks (Laravel, WordPress, etc.).

**Key Features:**
- Stacks are stored in separate repositories (e.g., `tuti-cli/laravel-stack`)
- Stacks are cached locally in `~/.tuti/stacks/` for offline use
- Service stubs (`stubs/services/`) are bundled with CLI for docker-compose generation

## Initial Setup

Before using stacks, ensure tuti is properly set up:

```bash
# Run setup to create global ~/.tuti directory
tuti install
```

This creates:
```
~/.tuti/
├── config.json     # Global configuration
├── stacks/         # Cached stack templates
├── cache/          # Temporary files
└── logs/           # Global logs
```

## Stack Management

### `tuti stack:manage`

Manage stack templates:

```bash
# List available stacks
tuti stack:manage list

# Download a stack
tuti stack:manage download laravel

# Update a stack to latest version  
tuti stack:manage update laravel

# Update all stacks
tuti stack:manage update --all

# Clear cache for a stack
tuti stack:manage clear laravel

# Clear all cached stacks
tuti stack:manage clear --all
```

## Available Commands

### `tuti init`

Interactive project initialization with stack selection.

```bash
# Interactive mode - will prompt for stack selection
tuti init

# With stack pre-selected
tuti init --stack=laravel

# Non-interactive with basic initialization
tuti init myproject --no-interaction
```

**Options:**
- `project-name` - Name of your project (optional, will prompt if not provided)
- `--stack` - Pre-select a stack (laravel, etc.)
- `--force` - Force initialization even if `.tuti` exists
- `--env` - Environment (dev, staging, production)
- `--no-interaction` - Run without prompts

### `tuti stack:laravel`

Specialized Laravel stack installation with two modes:

#### Fresh Installation Mode
Creates a new Laravel project with Docker configuration:

```bash
# Interactive mode
tuti stack:laravel

# Non-interactive fresh installation
tuti stack:laravel myapp --mode=fresh --no-interaction

# With specific Laravel version
tuti stack:laravel myapp --mode=fresh --laravel-version=11.0
```

#### Apply to Existing Mode
Adds Docker configuration to an existing Laravel project:

```bash
# In an existing Laravel project directory
tuti stack:laravel --mode=existing

# Non-interactive
tuti stack:laravel myproject --mode=existing --no-interaction
```

**Options:**
- `project-name` - Project name (required for fresh installation)
- `--mode` - Installation mode (fresh, existing)
- `--path` - Path for fresh installation (defaults to current directory)
- `--services` - Pre-select services (databases.postgres, cache.redis, etc.)
- `--env` - Environment (dev, staging, production)
- `--laravel-version` - Specific Laravel version to install
- `--force` - Force initialization
- `--no-interaction` - Run without prompts

### `tuti stack:init`

Generic stack initialization (legacy command):

```bash
tuti stack:init laravel myapp --no-interaction
```

## Architecture

### Stack Sources

Stacks can come from two sources:

1. **Remote Repositories** (recommended)
   - Defined in `stubs/stacks/registry.json`
   - Downloaded on first use to `~/.tuti/stacks/`
   - Can be updated with `tuti stack:manage update`

2. **Local Development** (for contributors)
   - Placed in `stacks/` directory of CLI
   - Takes precedence over remote stacks

### Stack Repository Structure

Each stack repository should contain:

```
laravel-stack/
├── stack.json              # Stack manifest (required)
├── docker/                 # Docker configuration files
│   ├── Dockerfile
│   └── nginx.conf
├── environments/           # Environment templates
│   ├── .env.dev.example
│   ├── .env.staging.example
│   └── .env.prod.example
├── docker-compose.yml      # Base compose template (optional)
├── docker-compose.dev.yml  # Dev overrides (optional)
└── docker-compose.prod.yml # Production overrides (optional)
```

### Service Stubs

Each stack has its own service stubs and registry in `stubs/stacks/{stack}/services/`.

**Stack-specific services structure:**
```
stubs/stacks/{stack}/services/
├── registry.json           # Service definitions for this stack
├── databases/
│   ├── postgres.stub       # Laravel
│   ├── mysql.stub
│   └── mariadb.stub        # WordPress default
├── cache/
│   └── redis.stub
├── cli/
│   └── wpcli.stub          # WordPress only
├── search/
│   ├── meilisearch.stub
│   └── typesense.stub
├── storage/
│   └── minio.stub
├── mail/
│   └── mailpit.stub
└── workers/
    ├── scheduler.stub      # Laravel Scheduler
    └── horizon.stub        # Laravel Horizon
```

Each stack's `registry.json` defines the available services, their configuration, default variables, and dependencies specific to that stack.

### Stack Installers

Each stack has a dedicated installer implementing `StackInstallerInterface`:

```php
interface StackInstallerInterface
{
    public function getIdentifier(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getFramework(): string;
    public function supports(string $stackIdentifier): bool;
    public function detectExistingProject(string $path): bool;
    public function installFresh(string $projectPath, string $projectName, array $options = []): bool;
    public function applyToExisting(string $projectPath, array $options = []): bool;
    public function getStackPath(): string;
    public function getAvailableModes(): array;
}
```

### Adding New Stacks

1. **Create stack repository** (e.g., `tuti-cli/wordpress-stack`)

2. **Add to registry** in `stubs/stacks/registry.json`:
```json
{
    "stacks": {
        "wordpress": {
            "name": "WordPress Stack",
            "description": "WordPress with Docker",
            "repository": "https://github.com/tuti-cli/wordpress-stack.git",
            "branch": "main",
            "framework": "wordpress",
            "type": "php"
        }
    }
}
```

3. **Create installer** in `app/Services/Stack/Installers/`:
```php
final class WordPressStackInstaller implements StackInstallerInterface
{
    // Implementation...
}
```

4. **Register in StackServiceProvider**:
```php
$registry->register($app->make(WordPressStackInstaller::class));
```

5. **Create command** in `app/Commands/Stack/`:
```php
final class WordPressCommand extends Command
{
    protected $signature = 'stack:wordpress';
}
```

## Data Flow

```
User runs: tuti stack:laravel myapp
    ↓
LaravelStackInstaller
    ↓
StackRepositoryService.getStackPath('laravel')
    ↓
Check: ~/.tuti/stacks/laravel-stack exists?
    ├─ Yes → Return path
    └─ No  → git clone from registry → Return path
    ↓
StackInitializationService.initialize()
    ↓
StackComposeBuilderService.buildWithStack()
    ↓
Load service stubs from stubs/services/
    ↓
Apply overrides from stack.json
    ↓
Generate docker-compose.yml in .tuti/
```

## Laravel Worker Services

### Laravel Scheduler

The Scheduler service runs Laravel's task scheduler in a dedicated container.

**Usage:**
```bash
tuti stack:laravel myapp --services=workers.scheduler
```

**Key Features:**
- Uses `php artisan schedule:work` command
- Runs in the same container image as your app
- Graceful shutdown via `SIGTERM`

### Laravel Horizon

Laravel Horizon provides a dashboard and code-driven configuration for your Laravel Redis queues.

**Prerequisites:**
- Redis service is required (added automatically as dependency)
- Laravel Horizon package must be installed in your Laravel app

**Usage:**
```bash
# Add Horizon when creating a new project
tuti stack:laravel myapp --services=cache.redis,workers.horizon

# Horizon depends on Redis - if not specified, Redis will be added automatically
tuti stack:laravel myapp --services=workers.horizon
```

**Key Features:**
- Uses `php artisan horizon` command
- Graceful shutdown via `SIGTERM`
- Health check via `healthcheck-horizon` (built into ServersideUp PHP image)
- Waits for Redis to be healthy before starting
- Dashboard for monitoring queue workers

**Docker Compose Example:**
```yaml
services:
  horizon:
    container_name: myapp_dev_horizon
    build:
      context: ..
      dockerfile: .tuti/docker/Dockerfile
      target: development
    command: ["php", "/var/www/html/artisan", "horizon"]
    stop_signal: SIGTERM
    environment:
      <<: *app-env
    depends_on:
      redis:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "healthcheck-horizon"]
      start_period: 10s
    restart: unless-stopped
```

**Environment Variables:**
| Variable | Default | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | `redis` | Queue driver |
| `HORIZON_PREFIX` | `horizon:` | Redis key prefix for Horizon |
