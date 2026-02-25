# Command Reference

Complete reference for all Tuti CLI commands.

---

## Table of Contents

- [Global Setup](#global-setup)
  - [tuti install](#tuti-install)
  - [tuti doctor](#tuti-doctor)
- [Infrastructure](#infrastructure)
  - [tuti infra:start](#tutistrastart)
  - [tuti infra:stop](#tutistrastop)
  - [tuti infra:restart](#tutistrarestart)
  - [tuti infra:status](#tutistrastatus)
- [Project Initialization](#project-initialization)
  - [tuti init](#tuti-init)
  - [tuti stack:init](#tuti-stackinit)
  - [tuti stack:laravel](#tuti-stacklaravel)
  - [tuti stack:wordpress](#tuti-stackwordpress)
  - [tuti stack:manage](#tuti-stackmanage)
- [Local Development](#local-development)
  - [tuti local:start](#tuti-localstart)
  - [tuti local:stop](#tuti-localstop)
  - [tuti local:status](#tuti-localstatus)
  - [tuti local:logs](#tuti-locallogs)
  - [tuti local:rebuild](#tuti-localrebuild)
- [WordPress](#wordpress)
  - [tuti wp:setup](#tuti-wpsetup)
- [Utilities](#utilities)
  - [tuti find](#tuti-find)
  - [tuti env:check](#tuti-envcheck)
  - [tuti debug](#tuti-debug)

---

## Global Setup

### tuti install

Set up the global Tuti CLI configuration, directories, and infrastructure.

**Usage**

```bash
tuti install [options]
```

**Description**

Initializes the global `~/.tuti` directory structure, creates configuration files, and sets up the Traefik reverse proxy infrastructure. This should be run once after first installing Tuti CLI.

**Options**

| Option | Description |
|--------|-------------|
| `--force` | Force reinstallation of global directory |
| `--skip-infra` | Skip infrastructure (Traefik) installation |

**Examples**

```bash
# First-time setup
tuti install

# Reinstall everything from scratch
tuti install --force

# Setup without Traefik (useful for CI environments)
tuti install --skip-infra
```

**What It Creates**

- `~/.tuti/` - Global configuration directory
- `~/.tuti/config.json` - Global configuration file
- `~/.tuti/stacks/` - Cached stack templates
- `~/.tuti/logs/` - Log files
- `~/.tuti/infrastructure/` - Traefik configuration

**Related Commands**

- [tuti doctor](#tuti-doctor) - Verify installation
- [tuti infra:status](#tutistrastatus) - Check infrastructure status

---

### tuti doctor

Check system requirements and diagnose issues.

**Usage**

```bash
tuti doctor [options]
```

**Description**

Runs a comprehensive health check of your system, including Docker availability, global configuration, infrastructure status, and project configuration (if in a project directory).

**Options**

| Option | Description |
|--------|-------------|
| `--fix` | Attempt to fix issues automatically |

**Examples**

```bash
# Run system diagnostics
tuti doctor

# Try to fix any found issues
tuti doctor --fix
```

**What It Checks**

1. **Docker** - Installed and daemon running
2. **Docker Compose** - Available (v2)
3. **Global Configuration** - `~/.tuti` directory exists
4. **Infrastructure** - Traefik installed and running
5. **Current Project** - If in a project directory:
   - `.tuti/config.json` exists
   - `docker-compose.yml` exists and is valid
   - `Dockerfile` exists
6. **Debug Mode** - Status and any session errors

**Exit Codes**

- `0` - All checks passed
- `1` - Issues found

**Related Commands**

- [tuti install](#tuti-install) - Initial setup
- [tuti debug](#tuti-debug) - Debug tools

---

## Infrastructure

### tuti infra:start

Start the global Traefik reverse proxy infrastructure.

**Usage**

```bash
tuti infra:start
```

**Description**

Starts the global Traefik reverse proxy that handles routing for all Tuti projects. This infrastructure is shared across all projects and only needs to be running once.

**Options**

This command has no options.

**Examples**

```bash
# Start the infrastructure
tuti infra:start
```

**What Happens**

1. Checks if infrastructure is installed
2. Starts Traefik container if not already running
3. Connects to the `traefik_proxy` Docker network

**After Starting**

- Traefik Dashboard: https://traefik.local.test

**Related Commands**

- [tuti infra:stop](#tutistrastop) - Stop infrastructure
- [tuti infra:status](#tutistrastatus) - Check status

---

### tuti infra:stop

Stop the global infrastructure.

**Usage**

```bash
tuti infra:stop [options]
```

**Description**

Stops the Traefik reverse proxy. This will affect all running Tuti projects since they share this infrastructure.

**Options**

| Option | Description |
|--------|-------------|
| `--force` | Stop without confirmation prompt |

**Examples**

```bash
# Stop with confirmation
tuti infra:stop

# Force stop without prompt
tuti infra:stop --force
```

**Warning**

Stopping infrastructure will make all project URLs inaccessible until it's started again.

**Related Commands**

- [tuti infra:start](#tutistrastart) - Start infrastructure
- [tuti infra:restart](#tutistrarestart) - Restart infrastructure

---

### tuti infra:restart

Restart the global infrastructure.

**Usage**

```bash
tuti infra:restart
```

**Description**

Stops and starts the Traefik reverse proxy. Useful when configuration changes need to be applied.

**Options**

This command has no options.

**Examples**

```bash
tuti infra:restart
```

**Related Commands**

- [tuti infra:start](#tutistrastart) - Start infrastructure
- [tuti infra:stop](#tutistrastop) - Stop infrastructure

---

### tuti infra:status

Show status of the global infrastructure.

**Usage**

```bash
tuti infra:status
```

**Description**

Displays the current state of the Traefik reverse proxy and Docker network configuration.

**Options**

This command has no options.

**Examples**

```bash
tuti infra:status
```

**Output Includes**

- Traefik installation status
- Traefik running status
- Health status
- Installation path
- Docker network status

**Related Commands**

- [tuti infra:start](#tutistrastart) - Start infrastructure
- [tuti infra:stop](#tutistrastop) - Stop infrastructure

---

## Project Initialization

### tuti init

Initialize a new Tuti project.

**Usage**

```bash
tuti init [project-name] [options]
```

**Description**

Creates a `.tuti/` directory in the current project root with the basic structure needed for Tuti. Can optionally delegate to stack-specific commands for framework setup.

**Arguments**

| Argument | Required | Description |
|----------|----------|-------------|
| `project-name` | No | Project name (defaults to current directory name) |

**Options**

| Option | Description |
|--------|-------------|
| `--stack=NAME` | Stack to use (laravel, wordpress, etc.) |
| `--force` | Force initialization even if `.tuti` exists |
| `--env=ENV` | Environment (dev, staging, production) |
| `--no-interaction` | Run non-interactively |

**Examples**

```bash
# Basic initialization (no stack)
tuti init

# Initialize with project name
tuti init myproject

# Initialize and select a stack interactively
tuti init

# Initialize with Laravel stack directly
tuti init myproject --stack=laravel

# Force reinitialize
tuti init --force
```

**What It Creates**

```
.tuti/
  config.json
  docker/
  environments/
  scripts/
```

**Related Commands**

- [tuti stack:laravel](#tuti-stacklaravel) - Laravel-specific setup
- [tuti stack:wordpress](#tuti-stackwordpress) - WordPress-specific setup
- [tuti stack:init](#tuti-stackinit) - Generic stack initialization

---

### tuti stack:init

Initialize a project with a selected stack and services.

**Usage**

```bash
tuti stack:init [stack] [project-name] [options]
```

**Description**

Initializes a project using one of the available stacks (Laravel, WordPress, etc.) with interactive service selection. This is a generic command that works with any installed stack.

**Arguments**

| Argument | Required | Description |
|----------|----------|-------------|
| `stack` | No | Stack name (e.g., laravel, laravel-stack) |
| `project-name` | No | Project name |

**Options**

| Option | Description |
|--------|-------------|
| `--services=*` | Pre-select services (can be used multiple times) |
| `--force` | Force initialization even if `.tuti` exists |
| `--env=ENV` | Environment (dev, staging, production) |
| `--no-interaction` | Run non-interactively |

**Examples**

```bash
# Interactive stack and service selection
tuti stack:init

# Initialize with specific stack
tuti stack:init laravel myproject

# Initialize with pre-selected services
tuti stack:init laravel myproject --services=databases.postgres --services=cache.redis

# Non-interactive (uses defaults)
tuti stack:init laravel myproject --no-interaction
```

**Related Commands**

- [tuti stack:laravel](#tuti-stacklaravel) - Laravel-specific setup
- [tuti stack:wordpress](#tuti-stackwordpress) - WordPress-specific setup
- [tuti stack:manage](#tuti-stackmanage) - Manage stack templates

---

### tuti stack:laravel

Initialize a Laravel project with Docker stack.

**Usage**

```bash
tuti stack:laravel [project-name] [options]
```

**Description**

Creates a new Laravel project or adds Docker configuration to an existing one. Supports multiple installation modes, starter kits, and database options.

**Arguments**

| Argument | Required | Description |
|----------|----------|-------------|
| `project-name` | No | Project name for fresh installation |

**Options**

| Option | Description |
|--------|-------------|
| `--mode=MODE` | Installation mode: `fresh` or `existing` |
| `--path=PATH` | Path for fresh installation |
| `--services=*` | Pre-select services |
| `--laravel-version=VER` | Specific Laravel version |
| `--force` | Force initialization even if `.tuti` exists |
| `--skip-start` | Skip starting containers after installation |
| `--skip-migrate` | Skip database migrations |
| `--env=ENV` | Environment (dev, staging, production) |
| `--no-interaction` | Run non-interactively |

**Interactive Options**

When running interactively, you can choose:

1. **Installation Mode**
   - Fresh installation - Create new Laravel project
   - Existing - Add Docker to current project

2. **Starter Kit** (fresh mode)
   - None (API-only)
   - React
   - Vue
   - Livewire
   - Svelte

3. **Authentication** (with starter kit)
   - Laravel's built-in authentication
   - WorkOS
   - None

4. **Testing Framework**
   - Pest (recommended)
   - PHPUnit

5. **Database**
   - SQLite (recommended for development)
   - PostgreSQL
   - MySQL
   - MariaDB

6. **Optional Services**
   - Redis (cache/sessions)
   - Mailpit (email testing)
   - Meilisearch (search)
   - Minio (S3-compatible storage)
   - Horizon (queue management)

**Examples**

```bash
# Create new Laravel project (interactive)
tuti stack:laravel myapp

# Create with specific services
tuti stack:laravel myapp --services=databases.postgres --services=cache.redis

# Add Docker to existing project
cd my-existing-laravel-app
tuti stack:laravel --mode=existing

# Non-interactive with defaults
tuti stack:laravel myapp --no-interaction

# Skip container startup
tuti stack:laravel myapp --skip-start

# Specific Laravel version
tuti stack:laravel myapp --laravel-version=11
```

**Project Structure After Installation**

```
myapp/
  app/
  bootstrap/
  config/
  database/
  public/
  resources/
  routes/
  storage/
  tests/
  .tuti/
    config.json
    docker/
    docker-compose.yml
    docker-compose.dev.yml
    environments/
```

**Access URLs**

- Application: https://myapp.local.test
- Services depend on selection (e.g., Mailpit, Redis Commander)

**Related Commands**

- [tuti local:start](#tuti-localstart) - Start project
- [tuti local:status](#tuti-localstatus) - Check status
- [tuti stack:wordpress](#tuti-stackwordpress) - WordPress setup

---

### tuti stack:wordpress

Initialize a WordPress project with Docker stack.

**Usage**

```bash
tuti stack:wordpress [project-name] [options]
```

**Description**

Creates a new WordPress project or adds Docker configuration to an existing one. Supports standard WordPress and Bedrock (Roots) installations.

**Arguments**

| Argument | Required | Description |
|----------|----------|-------------|
| `project-name` | No | Project name for fresh installation |

**Options**

| Option | Description |
|--------|-------------|
| `--mode=MODE` | Installation mode: `fresh` or `existing` |
| `--type=TYPE` | Installation type: `standard` or `bedrock` |
| `--path=PATH` | Path for fresh installation |
| `--services=*` | Pre-select services |
| `--wp-version=VER` | Specific WordPress version |
| `--force` | Force initialization even if `.tuti` exists |
| `--env=ENV` | Environment (dev, staging, production) |
| `--no-interaction` | Run non-interactively |

**Installation Types**

- **Standard** - Traditional WordPress structure with `wp-content/`
- **Bedrock** - Modern WordPress boilerplate with proper dependency management

**Examples**

```bash
# Create new WordPress project (interactive)
tuti stack:wordpress myblog

# Create Bedrock project
tuti stack:wordpress myblog --type=bedrock

# Add Docker to existing WordPress
cd my-existing-wordpress-site
tuti stack:wordpress --mode=existing

# With specific services
tuti stack:wordpress myblog --services=databases.mysql --services=cache.redis

# Non-interactive
tuti stack:wordpress myblog --no-interaction
```

**Standard Structure**

```
myblog/
  wp-content/
    themes/
    plugins/
    uploads/
  wp-config.php
  .tuti/
```

**Bedrock Structure**

```
myblog/
  config/
    environments/
  web/
    app/
      themes/
      plugins/
      uploads/
    wp-config.php
  composer.json
  .tuti/
```

**Post-Installation**

After creating the project, run:

```bash
tuti local:start
tuti wp:setup
```

**Related Commands**

- [tuti wp:setup](#tuti-wpsetup) - Auto-install WordPress
- [tuti local:start](#tuti-localstart) - Start project
- [tuti stack:laravel](#tuti-stacklaravel) - Laravel setup

---

### tuti stack:manage

Manage stack templates.

**Usage**

```bash
tuti stack:manage [action] [stack] [options]
```

**Description**

Manage stack templates including listing available stacks, downloading, updating, and clearing cache.

**Arguments**

| Argument | Required | Description |
|----------|----------|-------------|
| `action` | No | Action: `list`, `download`, `update`, `clear` |
| `stack` | No | Stack name for specific actions |

**Options**

| Option | Description |
|--------|-------------|
| `--all` | Apply action to all stacks |

**Actions**

| Action | Description |
|--------|-------------|
| `list` | List all available stacks |
| `download` | Download a stack to local cache |
| `update` | Update a cached stack |
| `clear` | Clear stack cache |

**Examples**

```bash
# List all stacks
tuti stack:manage list

# Download a stack
tuti stack:manage download laravel

# Update all cached stacks
tuti stack:manage update --all

# Clear cache for specific stack
tuti stack:manage clear laravel

# Clear all cached stacks
tuti stack:manage clear --all
```

**Related Commands**

- [tuti stack:init](#tuti-stackinit) - Initialize with stack
- [tuti stack:laravel](#tuti-stacklaravel) - Laravel setup
- [tuti stack:wordpress](#tuti-stackwordpress) - WordPress setup

---

## Local Development

### tuti local:start

Start the local development environment.

**Usage**

```bash
tuti local:start [options]
```

**Description**

Starts all Docker containers for the current project. Automatically ensures infrastructure is running unless `--skip-infra` is used.

**Options**

| Option | Description |
|--------|-------------|
| `--skip-infra` | Skip infrastructure check |
| `--migrate` | Run database migrations after starting |

**Examples**

```bash
# Start project
tuti local:start

# Start without checking infrastructure
tuti local:start --skip-infra

# Start and run migrations
tuti local:start --migrate
```

**What Happens**

1. Verifies `.tuti/` directory exists
2. Checks/starts infrastructure (unless `--skip-infra`)
3. Starts all containers defined in docker-compose files
4. Waits for health checks to pass
5. Displays project URLs

**Prerequisites**

- Must be in a Tuti project directory
- Infrastructure must be installed (run `tuti install` first)

**Related Commands**

- [tuti local:stop](#tuti-localstop) - Stop project
- [tuti local:status](#tuti-localstatus) - Check status
- [tuti local:logs](#tuti-locallogs) - View logs

---

### tuti local:stop

Stop the local development environment.

**Usage**

```bash
tuti local:stop
```

**Description**

Stops all Docker containers for the current project without removing them.

**Options**

This command has no options.

**Examples**

```bash
tuti local:stop
```

**What Happens**

1. Verifies project is running
2. Stops all containers gracefully
3. Preserves container state and volumes

**Note**

This does not remove containers or data. Use `docker compose down -v` to remove volumes.

**Related Commands**

- [tuti local:start](#tuti-localstart) - Start project
- [tuti local:rebuild](#tuti-localrebuild) - Rebuild containers

---

### tuti local:status

Check the status of project services.

**Usage**

```bash
tuti local:status
```

**Description**

Displays status information for all containers in the current project, including state, health, and exposed ports.

**Options**

This command has no options.

**Examples**

```bash
tuti local:status
```

**Output Includes**

- Container names
- Service names
- Running state
- Health status
- Published ports
- Project URLs

**Exit Codes**

- `0` - Command successful
- `1` - Error checking status

**Related Commands**

- [tuti local:start](#tuti-localstart) - Start project
- [tuti local:logs](#tuti-locallogs) - View logs

---

### tuti local:logs

View or follow logs for project services.

**Usage**

```bash
tuti local:logs [service] [options]
```

**Description**

Displays logs from Docker containers. Can show all services or a specific service, with optional live following.

**Arguments**

| Argument | Required | Description |
|----------|----------|-------------|
| `service` | No | Specific service name (e.g., `app`, `database`, `redis`) |

**Options**

| Option | Description |
|--------|-------------|
| `-f`, `--follow` | Follow log output (live streaming) |

**Examples**

```bash
# View all service logs
tuti local:logs

# View specific service logs
tuti local:logs app
tuti local:logs database
tuti local:logs redis

# Follow logs in real-time
tuti local:logs --follow

# Follow specific service
tuti local:logs app -f
```

**Common Services**

| Service | Container Suffix | Description |
|---------|-----------------|-------------|
| `app` | `_app` | Main application |
| `database` | `_database` | Database (MySQL/Postgres) |
| `redis` | `_redis` | Redis cache |
| `mailpit` | `_mailpit` | Mail testing |
| `node` | `_node` | Node.js for builds |

**Related Commands**

- [tuti local:start](#tuti-localstart) - Start project
- [tuti local:status](#tuti-localstatus) - Check status

---

### tuti local:rebuild

Rebuild containers to apply configuration changes.

**Usage**

```bash
tuti local:rebuild [options]
```

**Description**

Rebuilds Docker containers after changes to docker-compose.yml, Dockerfile, or other configuration files. Useful when you modify service configurations.

**Options**

| Option | Description |
|--------|-------------|
| `--no-cache` | Build without using cache |
| `--force` | Force rebuild even if containers are running |
| `-d`, `--detach` | Run build without showing logs |

**Examples**

```bash
# Rebuild with cache
tuti local:rebuild

# Rebuild without cache (clean build)
tuti local:rebuild --no-cache

# Force rebuild running containers
tuti local:rebuild --force

# Rebuild without output
tuti local:rebuild --detach
```

**What Happens**

1. Checks infrastructure status
2. Stops containers (unless `--force`)
3. Rebuilds images (with or without cache)
4. Starts containers
5. Displays project URLs

**When to Use**

- After modifying `docker-compose.yml`
- After modifying `Dockerfile`
- After changing environment variables
- When containers have issues

**Related Commands**

- [tuti local:start](#tuti-localstart) - Start project
- [tuti local:stop](#tuti-localstop) - Stop project
- [tuti local:logs](#tuti-locallogs) - View logs

---

## WordPress

### tuti wp:setup

Complete WordPress installation with dev credentials.

**Usage**

```bash
tuti wp:setup [options]
```

**Description**

Automates WordPress installation using WP-CLI. Creates `wp-config.php`, installs WordPress core, and sets up admin user with development credentials. Must be run after `tuti local:start`.

**Options**

| Option | Description |
|--------|-------------|
| `--force` | Force setup even if WordPress is already installed |

**Prerequisites**

- Must be in a WordPress Tuti project
- Containers must be running (`tuti local:start`)
- Database must be healthy

**Examples**

```bash
# Standard setup
tuti wp:setup

# Force reinstallation
tuti wp:setup --force
```

**Default Credentials**

| Setting | Value |
|---------|-------|
| Admin Username | `admin` |
| Admin Password | `admin` |
| Admin Email | `admin@localhost.test` |

**What Happens**

1. Checks if containers are running
2. Waits for database to be ready
3. Runs WordPress core installation
4. Saves auto-setup configuration

**Customizing Credentials**

Edit `.tuti/auto-setup.json` before running:

```json
{
  "enabled": true,
  "site_url": "https://myproject.local.test",
  "site_title": "My Project",
  "admin_user": "admin",
  "admin_password": "secure-password",
  "admin_email": "admin@example.com"
}
```

**Related Commands**

- [tuti stack:wordpress](#tuti-stackwordpress) - Create WordPress project
- [tuti local:start](#tuti-localstart) - Start containers

---

## Utilities

### tuti find

Find and run Tuti commands interactively.

**Usage**

```bash
tuti find
```

**Description**

Interactive command finder with fuzzy search. Useful for discovering available commands.

**Options**

This command has no options.

**Examples**

```bash
# Start interactive finder
tuti find
```

**How It Works**

1. Displays list of all available commands
2. Type to filter the list
3. Select a command to run it

**Related Commands**

- `tuti list` - List all commands (native Laravel Zero)

---

### tuti env:check

Check environment configuration.

**Usage**

```bash
tuti env:check [options]
```

**Description**

Validates the `.env` file in the current project, checking for required variables and Docker configuration.

**Options**

| Option | Description |
|--------|-------------|
| `--show` | Show all environment variables (sensitive values masked) |

**Examples**

```bash
# Check environment
tuti env:check

# Show all variables
tuti env:check --show
```

**What It Checks**

**Laravel Variables:**
- APP_NAME
- APP_KEY
- APP_ENV
- APP_URL
- DB_CONNECTION
- DB_HOST
- DB_DATABASE
- REDIS_HOST

**Docker Variables:**
- PROJECT_NAME
- APP_DOMAIN
- PHP_VERSION
- BUILD_TARGET

**Security**

Sensitive values (PASSWORD, KEY, SECRET, TOKEN) are automatically masked in output.

**Related Commands**

- [tuti local:start](#tuti-localstart) - Start project
- [tuti debug](#tuti-debug) - Debug tools

---

### tuti debug

Debug tools and log viewer for Tuti CLI.

**Usage**

```bash
tuti debug [action] [options]
```

**Description**

Provides access to debug logging, error viewing, and troubleshooting tools.

**Arguments**

| Argument | Required | Description |
|----------|----------|-------------|
| `action` | No | Action to perform |

**Actions**

| Action | Description |
|--------|-------------|
| `status` | Show debug status (default) |
| `enable` | Enable debug logging |
| `disable` | Disable debug logging |
| `logs` | View recent logs |
| `errors` | View errors only |
| `clear` | Clear all logs |

**Options**

| Option | Description |
|--------|-------------|
| `--lines=N` | Number of log lines to show (default: 50) |
| `--level=LEVEL` | Filter by level (error, warning, info, debug) |
| `--session` | Show only current session logs |

**Examples**

```bash
# Check debug status
tuti debug

# Enable debug logging
tuti debug enable

# View recent logs
tuti debug logs

# View last 100 lines
tuti debug logs --lines=100

# View only errors
tuti debug errors

# View errors from current session
tuti debug logs --level=error --session

# Clear all logs
tuti debug clear

# Disable debug mode
tuti debug disable
```

**Log Location**

Logs are stored at: `~/.tuti/logs/tuti.log`

**When to Use**

- Troubleshooting command failures
- Understanding what commands are doing
- Reporting bugs with detailed logs

**Related Commands**

- [tuti doctor](#tuti-doctor) - System diagnostics
- [tuti env:check](#tuti-envcheck) - Environment check

---

## Environment Variables

### Global Options

These options are available for all commands:

| Option | Description |
|--------|-------------|
| `--env=ENV` | Set environment (dev, staging, production) |
| `--no-interaction` | Run non-interactively |
| `-n` | Alias for `--no-interaction` |
| `-q`, `--quiet` | Suppress output |
| `-v\|vv\|vvv` | Increase verbosity |
| `--version` | Display version |
| `--ansi\|--no-ansi` | Force/disable ANSI output |

### Examples

```bash
# Non-interactive mode
tuti stack:laravel myapp --no-interaction

# Quiet mode
tuti local:start --quiet

# Verbose output
tuti local:start -vvv

# Specific environment
tuti stack:init laravel --env=staging
```

---

## Troubleshooting

### Common Issues

**Command not found**

Ensure Tuti is installed and in your PATH:
```bash
which tuti
tuti --version
```

**Docker not running**

Start Docker Desktop or Docker daemon:
```bash
tuti doctor
```

**Permission denied**

Check file permissions:
```bash
ls -la ~/.tuti
```

**Port conflicts**

Check for port usage:
```bash
docker ps
netstat -tulpn | grep LISTEN
```

### Getting Help

1. Run `tuti doctor` to diagnose issues
2. Enable debug logging: `tuti debug enable`
3. Check logs: `tuti debug errors`
4. View command help: `tuti help <command>`

---

## See Also

- [README.md](../README.md) - Installation and quick start
- [CLAUDE.md](../CLAUDE.md) - Development guidelines
- [CONTRIBUTING.md](../CONTRIBUTING.md) - Contribution guide
