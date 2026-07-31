# Tuti CLI - User Stories

**Last Updated:** 2026-03-24

Status: `[x]` Implemented | `[ ]` Not yet implemented | `[~]` Partially implemented

---

## 1. Installation & Setup

### US-1.1: Install Tuti CLI `[x]`
**As a** developer, **I want to** install Tuti CLI with a single command, **so that** I can start using it without manual setup.
- Install script auto-detects platform (Linux/macOS x64/ARM64)
- Downloads correct binary, places in `~/.tuti/bin/`, adds to PATH
- Works without PHP installed on host

### US-1.2: Global Setup `[x]`
**As a** developer, **I want to** run `tuti install` to set up global infrastructure, **so that** all projects share Traefik, SSL, and configuration.
- Creates `~/.tuti/` directory structure
- Installs Traefik v3.2, generates SSL certs (mkcert or self-signed)
- Creates dashboard auth, sets up config.json
- Flags: `--force` (re-install), `--skip-infra` (skip Traefik)

### US-1.3: Uninstall `[x]`
**As a** developer, **I want to** cleanly uninstall Tuti CLI.
- Removes binary from `~/.tuti/bin/`
- `--purge` removes entire `~/.tuti/` directory

---

## 2. Stack Initialization

### US-2.1: Create Laravel Project `[x]`
**As a** Laravel developer, **I want to** run `tuti stack:laravel my-app` to create a project with Docker environment.
- Creates Laravel project via Composer
- Generates `.tuti/` with docker-compose, Dockerfile, scripts
- Interactive service selection (database, cache, search, storage, mail, workers)
- Auto-generated `.env` with secure passwords
- Traefik labels for `{project}.local.test`
- Flags: `--mode=fresh|existing`, `--services=postgres,redis`

### US-2.2: Create WordPress Project `[x]`
**As a** WordPress developer, **I want to** run `tuti stack:wordpress my-site` to create a WordPress Docker environment.
- Standard and Bedrock (Composer) via `--type=`
- PHP-FPM + Apache (serversideup/php), service selection
- Auto-generated WordPress salts/keys, WP-CLI auto-included
- Traefik routing for `{project}.local.test`

### US-2.3: Add Docker to Existing Project `[x]`
**As a** developer with an existing project, **I want to** add Tuti Docker config without starting from scratch.
- Detects project type from `artisan` / `wp-config.php`
- Creates `.tuti/` without overwriting code
- Merges with existing `.env`, preserves values

---

## 3. Local Development

### US-3.1: Start Local Environment `[x]`
**As a** developer, **I want to** run `tuti local:start` to start my Docker environment.
- Ensures Traefik running (auto-starts if needed)
- Runs `docker compose up -d` with base + dev overlay
- Displays project URL on success, shows container status

### US-3.2: Stop Local Environment `[x]`
**As a** developer, **I want to** run `tuti local:stop` to free resources.
- Runs `docker compose down`, does not stop Traefik

### US-3.3: View Logs `[x]`
**As a** developer, **I want to** view container logs for debugging.
- Combined logs by default, `--service=` filter, `--tail=` limit

### US-3.4: Check Status `[x]`
**As a** developer, **I want to** see container state and health.
- Lists containers with name, status, ports, health

### US-3.5: Rebuild Containers `[x]`
**As a** developer, **I want to** rebuild Docker images after Dockerfile changes.
- Rebuilds images, `--pull` for latest base images, preserves volumes

---

## 4. Infrastructure Management

### US-4.1: Manage Traefik `[x]`
**As a** developer, **I want to** manage the global reverse proxy.
- `infra:start/stop/restart/status`
- `*.local.test` wildcard routing, HTTPS with auto-certs
- Dashboard protected with basic auth

---

## 5. Environment Management

### US-5.1: Check Environment `[x]`
**As a** developer, **I want to** verify my `.env` configuration before running.
- Compares against expected stack variables
- Highlights missing/empty/placeholder values
- `--show` displays values (masks passwords)

### US-5.2: Secure Passwords `[x]`
**As a** developer, **I want to** have secure passwords auto-generated.
- All `CHANGE_THIS` replaced with `bin2hex(random_bytes(16))`
- Unique per service, WordPress salts auto-generated

---

## 6. System Diagnostics

### US-6.1: Health Check `[x]`
**As a** developer, **I want to** run `tuti doctor` to diagnose issues.
- Checks Docker, Compose, global config, Traefik, project config, compose syntax
- `--fix` attempts automatic fixes

### US-6.2: Debug Logging `[x]`
**As a** developer, **I want to** enable debug logging for troubleshooting.
- Structured logging to `~/.tuti/logs/tuti.log`
- Rotating at 5MB, keeps 5 files

---

## 7. Multi-Project Management

### US-7.1: List Projects `[ ]`
**As a** developer, **I want to** run `tuti projects:list` to see all registered projects.
- Lists from `~/.tuti/projects.json`: name, path, stack, status, last accessed
- Highlights current project, marks stale entries
- Filter: `--stack=laravel`

### US-7.2: Check All Status `[ ]`
**As a** developer, **I want to** see which projects are running.
- Queries Docker per project: container count, URL, resources

### US-7.3: Clean Stale Projects `[ ]`
**As a** developer, **I want to** remove stale entries from the registry.
- Detects deleted/moved projects, confirms before removing
- `--dry-run` for preview

---

## 8. Deployment

### US-8.1: Deploy via SSH `[ ]`
**As a** developer, **I want to** run `tuti deploy staging` to deploy to remote servers.
- SSH key-based auth, rsync or git pull
- Runs migrations, cache clear, dependency install
- Rollback on failure, real-time progress

### US-8.2: Deploy WordPress via FTP `[ ]`
**As a** WordPress developer, **I want to** deploy themes/plugins via FTP/SFTP.
- FTP + SFTP, diff-based upload, excludes dev files
- Deploy specific paths (theme dir, plugin dir)

### US-8.3: Configure Targets `[ ]`
**As a** developer, **I want to** configure deployment targets per environment.
- Targets in `.tuti/config.json` or `.tuti/deploy.json`
- Each: host, user, path, method, branch
- `tuti deploy:configure` interactive wizard

### US-8.4: Rollback `[ ]`
**As a** developer, **I want to** rollback to a previous deployment.
- Keeps N releases (default: 5), switches symlink
- Runs rollback hooks

---

## 9. Stack Management

### US-9.1: List Stacks `[~]`
**As a** developer, **I want to** see available stack templates.
- Built-in stacks, services, versions

### US-9.2: Manage Services `[~]`
**As a** developer, **I want to** add/remove services after initialization.
- Add/remove service, regenerate compose, update .env
- Warns about data loss on removal

### US-9.3: Update Templates `[ ]`
**As a** developer, **I want to** update cached stack templates.
- Pulls latest from repos, shows diff, doesn't affect existing projects

---

## 10. CLI Experience

### US-10.1: Command Finder `[x]`
**As a** developer, **I want to** search commands with `tuti find`.
- Fuzzy search, keyboard navigation, execute from search

### US-10.2: Branded Output `[x]`
**As a** developer, **I want to** see clear, organized output.
- HasBrandedOutput trait, branded headers, progress indicators
- Color-coded messages, 5 themes

### US-10.3: Contextual Help `[ ]`
**As a** developer, **I want to** see relevant tips based on context.
- Suggest next commands after actions
- Actionable error messages

---

## 11. WordPress-Specific

### US-11.1: Auto-Setup `[ ]`
**As a** WordPress developer, **I want to** run `tuti wp:setup` for automatic installation.
- Creates database, runs `wp core install`, sets admin user
- Configures permalinks, enables Redis caching if configured
- Works for Standard and Bedrock

### US-11.2: Theme/Plugin Dev Mode `[ ]`
**As a** WordPress developer, **I want to** develop themes/plugins with optimized setup.
- Mounts theme/plugin directory, build tools, WP-CLI, hot reload, debug mode

---

## 12. CI/CD Integration

### US-12.1: CI Test Pipeline `[x]`
**As a** contributor, **I want to** automated tests on every PR.
- GitHub Actions: Rector -> Pint -> PHPStan -> Pest on PHP 8.4

### US-12.2: Generate CI Config `[ ]`
**As a** developer, **I want to** generate CI/CD configs for my projects.
- GitHub Actions + GitLab CI templates

---

## 13. Database Operations

### US-13.1: Backup `[ ]`
**As a** developer, **I want to** create database snapshots.
- Auto-detects DB type, SQL dump in `.tuti/backups/`, `--compress`

### US-13.2: Restore `[ ]`
**As a** developer, **I want to** restore from backup.
- Lists backups, confirms before overwrite, handles compression

### US-13.3: Reset `[ ]`
**As a** developer, **I want to** drop and recreate the database.
- Drops tables, runs migrations/import, `--seed` for Laravel
