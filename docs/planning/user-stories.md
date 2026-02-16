# Tuti CLI - User Stories

**Last Updated:** 2026-02-07

This document contains user stories and acceptance criteria organized by feature domain. Each story follows the format: _As a [role], I want to [action], so that [benefit]_.

Status legend: `[x]` Implemented | `[ ]` Not yet implemented | `[~]` Partially implemented

---

## Table of Contents

1. [Installation & Setup](#1-installation--setup)
2. [Stack Initialization](#2-stack-initialization)
3. [Local Development](#3-local-development)
4. [Infrastructure Management](#4-infrastructure-management)
5. [Environment Management](#5-environment-management)
6. [System Diagnostics](#6-system-diagnostics)
7. [Multi-Project Management](#7-multi-project-management)
8. [Deployment](#8-deployment)
9. [Stack Management](#9-stack-management)
10. [CLI Experience](#10-cli-experience)
11. [WordPress-Specific](#11-wordpress-specific)
12. [CI/CD Integration](#12-cicd-integration)
13. [Database Operations](#13-database-operations)

---

## 1. Installation & Setup

### US-1.1: Install Tuti CLI `[x]`

**As a** developer,
**I want to** install Tuti CLI with a single command,
**so that** I can start using it immediately without manual setup.

**Acceptance Criteria:**
- [ ] Install script auto-detects platform (Linux x64/ARM64, macOS x64/ARM64)
- [ ] Downloads the correct binary for the current platform
- [ ] Places binary in `~/.tuti/bin/` and adds to PATH
- [ ] Works without PHP installed on the host machine
- [ ] Provides clear success/failure output

### US-1.2: Global Setup `[x]`

**As a** developer,
**I want to** run `tuti install` to set up global infrastructure,
**so that** all my projects can share Traefik reverse proxy, SSL, and configuration.

**Acceptance Criteria:**
- [ ] Creates `~/.tuti/` directory structure (config, settings, stacks, cache, logs)
- [ ] Installs and configures Traefik v3.2 reverse proxy
- [ ] Generates SSL certificates (via mkcert if available, otherwise self-signed)
- [ ] Creates Traefik dashboard with auto-generated htpasswd auth
- [ ] Sets up `config.json` with version, telemetry: false, default environment
- [ ] `--force` flag allows re-installation
- [ ] `--skip-infra` flag skips Traefik setup

### US-1.3: Uninstall Tuti CLI `[x]`

**As a** developer,
**I want to** cleanly uninstall Tuti CLI,
**so that** I can remove it without leftover files.

**Acceptance Criteria:**
- [ ] Removes binary from `~/.tuti/bin/`
- [ ] `--purge` flag removes entire `~/.tuti/` directory
- [ ] Provides confirmation before destructive operations

---

## 2. Stack Initialization

### US-2.1: Create New Laravel Project `[x]`

**As a** Laravel developer,
**I want to** run `tuti stack:laravel my-app` to create a new project with Docker environment,
**so that** I have a fully configured development environment in minutes.

**Acceptance Criteria:**
- [ ] Creates new Laravel project via Composer
- [ ] Generates `.tuti/` directory with docker-compose.yml, Dockerfile, scripts
- [ ] Prompts for service selection (database, cache, search, storage, mail, workers)
- [ ] Generates `.env` file with secure auto-generated passwords
- [ ] Configures Traefik labels for `{project}.local.test` domain
- [ ] Supports `--mode=fresh` (new project) and `--mode=existing` (add to existing)
- [ ] Supports `--services=postgres,redis` for non-interactive service selection
- [ ] Registers project in global registry (`~/.tuti/projects.json`)

### US-2.2: Create New WordPress Project `[x]`

**As a** WordPress developer,
**I want to** run `tuti stack:wordpress my-site` to create a new WordPress project,
**so that** I get a Docker-based WordPress environment with best practices.

**Acceptance Criteria:**
- [ ] Creates WordPress project structure
- [ ] Supports Standard (classic) and Bedrock (Composer) project types via `--type=`
- [ ] Generates Docker config with PHP-FPM + Apache (serversideup/php image)
- [ ] Prompts for service selection (database, cache, storage, mail)
- [ ] Auto-generates WordPress salts and keys
- [ ] Auto-includes WP-CLI service
- [ ] Generates `wp-config.php` from template (Standard) or uses Bedrock's config (Bedrock)
- [ ] Configures Traefik routing for `{project}.local.test`

### US-2.3: Add Docker to Existing Project `[x]`

**As a** developer with an existing Laravel/WordPress project,
**I want to** add Tuti CLI Docker configuration to my project,
**so that** I can Dockerize my existing codebase without starting from scratch.

**Acceptance Criteria:**
- [ ] Detects project type from existing files (`artisan` for Laravel, `wp-config.php` for WordPress)
- [ ] Creates `.tuti/` directory without overwriting existing code
- [ ] Generates `.env` variables and merges with existing `.env` file
- [ ] Preserves existing environment variable values during merge
- [ ] Registers project in global registry

---

## 3. Local Development

### US-3.1: Start Local Environment `[x]`

**As a** developer,
**I want to** run `tuti local:start` to start my project's Docker environment,
**so that** I can begin development immediately.

**Acceptance Criteria:**
- [ ] Ensures Traefik infrastructure is running (auto-starts if needed)
- [ ] Runs `docker compose up -d` with base + dev overlay files
- [ ] Uses `--env-file ./.env` for environment variable passing
- [ ] Builds Docker image on first start
- [ ] Displays project URL (`https://{project}.local.test`) on success
- [ ] Shows container status after startup
- [ ] `--skip-infra` flag skips Traefik check

### US-3.2: Stop Local Environment `[x]`

**As a** developer,
**I want to** run `tuti local:stop` to stop my project containers,
**so that** I can free system resources when not working on a project.

**Acceptance Criteria:**
- [ ] Runs `docker compose down` for the project
- [ ] Does not stop Traefik (other projects may be running)
- [ ] Displays confirmation of stopped services

### US-3.3: View Container Logs `[x]`

**As a** developer,
**I want to** run `tuti local:logs` to view my project's container logs,
**so that** I can debug issues in my development environment.

**Acceptance Criteria:**
- [ ] Shows combined logs from all project containers by default
- [ ] `--service=` flag filters to specific service (e.g., `--service=app`)
- [ ] `--tail=` flag limits number of log lines
- [ ] Logs stream in real-time (follows)

### US-3.4: Check Local Status `[x]`

**As a** developer,
**I want to** run `tuti local:status` to see the state of my project containers,
**so that** I know which services are running and their health status.

**Acceptance Criteria:**
- [ ] Lists all project containers with name, status, ports, health
- [ ] Shows project URL and access information
- [ ] Indicates which services are healthy/unhealthy/starting

### US-3.5: Rebuild Containers `[x]`

**As a** developer,
**I want to** run `tuti local:rebuild` to rebuild my Docker containers,
**so that** I can apply Dockerfile changes or fix corrupted containers.

**Acceptance Criteria:**
- [ ] Rebuilds Docker images from Dockerfile
- [ ] `--pull` flag forces pulling latest base images
- [ ] Restarts containers with new images
- [ ] Preserves volume data (databases, uploads)

---

## 4. Infrastructure Management

### US-4.1: Manage Traefik Infrastructure `[x]`

**As a** developer,
**I want to** manage the global Traefik reverse proxy,
**so that** all my projects can use automatic routing and SSL.

**Acceptance Criteria:**
- [ ] `tuti infra:start` starts Traefik if not running
- [ ] `tuti infra:stop` stops Traefik (warns if projects are running)
- [ ] `tuti infra:restart` restarts Traefik
- [ ] `tuti infra:status` shows Traefik status, dashboard URL, and managed routes
- [ ] Traefik handles `*.local.test` wildcard routing
- [ ] Traefik provides HTTPS with auto-generated certificates
- [ ] Traefik dashboard is protected with basic auth

---

## 5. Environment Management

### US-5.1: Check Environment Variables `[x]`

**As a** developer,
**I want to** run `tuti env:check` to verify my environment configuration,
**so that** I can catch missing or incorrect variables before running my app.

**Acceptance Criteria:**
- [ ] Compares project `.env` against expected variables for the stack
- [ ] Highlights missing, empty, or placeholder (`CHANGE_THIS`) values
- [ ] `--show` flag displays all values (masking passwords, keys, secrets, tokens)
- [ ] Suggests fixes for common issues

### US-5.2: Auto-Generated Secure Passwords `[x]`

**As a** developer,
**I want to** have secure passwords auto-generated during stack initialization,
**so that** I never accidentally use default or weak credentials.

**Acceptance Criteria:**
- [ ] All `CHANGE_THIS` placeholders replaced with cryptographically secure values
- [ ] Uses `bin2hex(random_bytes(16))` for 32-char hex passwords
- [ ] Generates unique passwords for each service (database, Redis, MinIO, etc.)
- [ ] WordPress salts and keys auto-generated

---

## 6. System Diagnostics

### US-6.1: Health Check `[x]`

**As a** developer,
**I want to** run `tuti doctor` to diagnose system and project issues,
**so that** I can quickly identify and fix configuration problems.

**Acceptance Criteria:**
- [ ] Checks Docker installation and version
- [ ] Checks Docker Compose v2 availability
- [ ] Validates global Tuti config (`~/.tuti/`)
- [ ] Validates Traefik infrastructure status
- [ ] Validates current project configuration (if in a project directory)
- [ ] Validates Docker Compose syntax for generated files
- [ ] `--fix` flag attempts automatic fixes where possible
- [ ] Clear pass/fail output for each check

### US-6.2: Debug Logging `[x]`

**As a** developer,
**I want to** enable debug logging to troubleshoot CLI issues,
**so that** I can report detailed information when filing bug reports.

**Acceptance Criteria:**
- [ ] `tuti debug` shows current debug status and log location
- [ ] Debug log captures: commands run, Docker output, errors, timing
- [ ] Log rotates at 5MB, keeps 5 files max
- [ ] Log location: `~/.tuti/logs/tuti.log`
- [ ] Helper function `tuti_debug()` available throughout codebase

---

## 7. Multi-Project Management

### US-7.1: List All Projects `[ ]`

**As a** developer managing multiple projects,
**I want to** run `tuti projects:list` to see all my registered projects,
**so that** I can quickly find and switch between them.

**Acceptance Criteria:**
- [ ] Lists all projects from `~/.tuti/projects.json`
- [ ] Shows: project name, path, stack type, status (running/stopped), last accessed
- [ ] Highlights currently active project
- [ ] Marks projects with missing directories (stale entries)
- [ ] Supports filtering by stack type (`--stack=laravel`)

### US-7.2: Check All Projects Status `[ ]`

**As a** developer managing multiple projects,
**I want to** run `tuti projects:status` to see which projects are running,
**so that** I can manage system resources and know what's active.

**Acceptance Criteria:**
- [ ] Queries Docker for container status of each registered project
- [ ] Shows: project name, running containers count, URL, resource usage
- [ ] Summarizes total running containers and resource usage

### US-7.3: Clean Stale Projects `[ ]`

**As a** developer,
**I want to** clean up stale project entries from the global registry,
**so that** my project list stays accurate and manageable.

**Acceptance Criteria:**
- [ ] Detects projects with deleted/moved directories
- [ ] Prompts for confirmation before removing entries
- [ ] `--dry-run` flag shows what would be cleaned without changing anything

---

## 8. Deployment

### US-8.1: Deploy via SSH `[ ]`

**As a** developer,
**I want to** run `tuti deploy staging` to deploy my application to a remote server,
**so that** I can ship code without switching to a separate deployment tool.

**Acceptance Criteria:**
- [ ] Connects to remote server via SSH (key-based auth)
- [ ] Uploads code via rsync or git pull
- [ ] Runs deployment steps (migrations, cache clear, dependency install)
- [ ] Supports rollback on failure
- [ ] Shows deployment progress in real-time
- [ ] Logs deployment to `~/.tuti/logs/`

### US-8.2: Deploy WordPress Theme/Plugin via FTP `[ ]`

**As a** WordPress developer,
**I want to** deploy my theme or plugin to an FTP server,
**so that** I can ship updates to shared hosting environments.

**Acceptance Criteria:**
- [ ] Supports FTP and SFTP protocols
- [ ] Deploys only changed files (diff-based upload)
- [ ] Excludes development files (`.git`, `node_modules`, `.env`)
- [ ] Supports deploying specific paths (theme directory, plugin directory)
- [ ] Shows upload progress and summary

### US-8.3: Configure Deployment Targets `[ ]`

**As a** developer,
**I want to** configure multiple deployment targets (staging, production) in my project config,
**so that** I can deploy to different environments with a single command.

**Acceptance Criteria:**
- [ ] Deployment targets defined in `.tuti/config.json` or `.tuti/deploy.json`
- [ ] Each target specifies: host, user, path, method (ssh/ftp/sftp), branch
- [ ] Supports environment-specific deployment steps
- [ ] `tuti deploy:configure` interactive setup wizard
- [ ] Sensitive data (passwords, keys) stored securely (not in plain text in config)

### US-8.4: Rollback Deployment `[ ]`

**As a** developer,
**I want to** run `tuti deploy:rollback` to revert to the previous deployment,
**so that** I can quickly recover from a broken release.

**Acceptance Criteria:**
- [ ] Keeps N previous releases on server (configurable, default: 5)
- [ ] Switches symlink to previous release directory
- [ ] Runs rollback hooks (cache clear, queue restart)
- [ ] Shows which release is being restored
- [ ] Logs rollback action

---

## 9. Stack Management

### US-9.1: List Available Stacks `[~]`

**As a** developer,
**I want to** see all available stack templates,
**so that** I can choose the right one for my project.

**Acceptance Criteria:**
- [ ] Lists built-in stacks (Laravel, WordPress)
- [ ] Shows: name, description, framework, available services
- [ ] Lists custom/community stacks from configured repositories
- [ ] Shows stack version information

### US-9.2: Manage Stack Services `[~]`

**As a** developer with an initialized project,
**I want to** add or remove services from my stack after initialization,
**so that** I can adjust my Docker environment as project needs change.

**Acceptance Criteria:**
- [ ] `tuti stack:manage add-service` adds a new service to existing compose files
- [ ] `tuti stack:manage remove-service` removes a service and its volumes/env vars
- [ ] Regenerates Docker Compose files with updated services
- [ ] Updates `.env` with new service variables
- [ ] Warns about data loss when removing services with volumes

### US-9.3: Update Stack Templates `[ ]`

**As a** developer,
**I want to** update cached stack templates to their latest versions,
**so that** new projects use the most up-to-date configurations.

**Acceptance Criteria:**
- [ ] `tuti stack:update` pulls latest templates from configured repositories
- [ ] Shows what changed between versions
- [ ] Does not affect already-initialized projects
- [ ] Supports `--stack=laravel` to update specific stacks

---

## 10. CLI Experience

### US-10.1: Interactive Command Finder `[x]`

**As a** developer new to Tuti CLI,
**I want to** run `tuti find` to search for commands interactively,
**so that** I can discover available features without reading documentation.

**Acceptance Criteria:**
- [ ] Fuzzy search across all command names and descriptions
- [ ] Shows command signature, description, and category
- [ ] Allows executing selected command directly
- [ ] Keyboard navigation (arrow keys, enter)

### US-10.2: Branded Output `[x]`

**As a** developer,
**I want to** see clear, visually organized command output,
**so that** I can quickly understand what happened and what to do next.

**Acceptance Criteria:**
- [ ] All commands use `HasBrandedOutput` trait
- [ ] Branded header with command name and Tuti branding
- [ ] Step-by-step progress indicators
- [ ] Color-coded success/failure/warning messages
- [ ] Key-value pair formatting for configuration display
- [ ] Tip boxes for helpful suggestions
- [ ] 5 color themes available (LaravelRed, Gray, Ocean, Vaporwave, Sunset)

### US-10.3: Contextual Help `[ ]`

**As a** developer,
**I want to** see relevant tips and suggestions based on my current context,
**so that** I can learn advanced features organically.

**Acceptance Criteria:**
- [ ] After `stack:laravel`, suggest `local:start` as next step
- [ ] After `local:start` failure, suggest `doctor` for diagnostics
- [ ] Show `--help` quality descriptions for every command
- [ ] Error messages include actionable fix suggestions

---

## 11. WordPress-Specific

### US-11.1: WordPress Auto-Setup `[ ]`

**As a** WordPress developer,
**I want to** run `tuti wp:setup` to complete WordPress installation automatically,
**so that** I don't have to go through the manual install wizard.

**Acceptance Criteria:**
- [ ] Creates WordPress database
- [ ] Runs `wp core install` via WP-CLI with project config values
- [ ] Sets admin user credentials from `.env`
- [ ] Configures permalink structure
- [ ] Installs and activates specified plugins (from config)
- [ ] Sets up object caching if Redis is configured
- [ ] Works for both Standard and Bedrock project types

### US-11.2: WordPress Theme/Plugin Development Mode `[ ]`

**As a** WordPress theme/plugin developer,
**I want to** initialize a project focused on theme or plugin development,
**so that** I get a Docker environment optimized for my workflow.

**Acceptance Criteria:**
- [ ] Theme dev mode: mounts theme directory, includes build tools config
- [ ] Plugin dev mode: mounts plugin directory, includes testing framework
- [ ] WP-CLI commands available for quick testing
- [ ] Hot reload support for theme/plugin files
- [ ] Debug mode enabled (WP_DEBUG, SCRIPT_DEBUG)

---

## 12. CI/CD Integration

### US-12.1: CI Test Pipeline `[ ]`

**As a** contributor,
**I want to** have automated tests run on every pull request,
**so that** code quality is maintained and regressions are caught early.

**Acceptance Criteria:**
- [ ] GitHub Actions workflow runs on PR open/update
- [ ] Runs: Rector (dry-run) -> Pint (dry-run) -> PHPStan -> Pest
- [ ] Reports results as PR checks
- [ ] Fails PR if any check fails
- [ ] Runs on PHP 8.4

### US-12.2: Generate CI Config for User Projects `[ ]`

**As a** developer,
**I want to** run `tuti ci:generate` to create CI/CD pipeline configs for my project,
**so that** I can set up automated testing and deployment quickly.

**Acceptance Criteria:**
- [ ] Generates GitHub Actions workflow for testing
- [ ] Generates deployment workflow that uses `tuti deploy`
- [ ] Configures environment variables and secrets
- [ ] Supports GitLab CI as alternative

---

## 13. Database Operations

### US-13.1: Database Backup `[ ]`

**As a** developer,
**I want to** run `tuti db:backup` to create a database snapshot,
**so that** I can save my current database state before making changes.

**Acceptance Criteria:**
- [ ] Detects database type (PostgreSQL, MySQL, MariaDB) from project config
- [ ] Creates SQL dump in `.tuti/backups/` directory
- [ ] Names backup with timestamp (`backup-2026-02-07-143000.sql`)
- [ ] Supports compression (`--compress` flag)
- [ ] Lists available backups with `tuti db:list`

### US-13.2: Database Restore `[ ]`

**As a** developer,
**I want to** run `tuti db:restore` to restore a database from a backup,
**so that** I can revert to a known good state.

**Acceptance Criteria:**
- [ ] Lists available backups for selection
- [ ] Confirms before overwriting current database
- [ ] Supports restoring specific backup by filename
- [ ] Handles compressed backups automatically

### US-13.3: Database Reset `[ ]`

**As a** developer,
**I want to** run `tuti db:reset` to drop and recreate my database,
**so that** I can start fresh during development.

**Acceptance Criteria:**
- [ ] Drops all tables in the project database
- [ ] Runs migrations (Laravel) or imports fresh (WordPress)
- [ ] Optionally runs seeders (`--seed` flag for Laravel)
- [ ] Requires confirmation (destructive action)

---

## Cross-Cutting Concerns

### Security

- All generated passwords use cryptographically secure random generation
- Sensitive `.env` values masked in `env:check --show` output
- Traefik dashboard protected with htpasswd authentication
- Docker socket mounted read-only for Traefik
- No telemetry data collected or transmitted
- SSH key handling for deployment (planned)

### Performance

- Pest tests run in parallel mode
- Docker Compose operations have 300s timeout (600s for builds)
- Stack templates cached locally for offline use
- Single binary with embedded runtime (~25-50MB)

### Error Handling

- All commands return `Command::SUCCESS` or `Command::FAILURE` (never `exit()`)
- User-friendly error messages with suggested fixes
- Debug logging for detailed troubleshooting
- `tuti doctor` for comprehensive diagnostics
