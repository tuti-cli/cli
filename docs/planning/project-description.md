# Tuti CLI - Project Description

**Version:** 0.x (Pre-release, Active Development)
**License:** MIT
**Repository:** https://github.com/tuti-cli/cli
**Started:** Late 2025

---

## What is Tuti CLI?

Tuti CLI is a unified environment management and deployment tool for web developers. It replaces the need for multiple separate tools (Lando/DDEV for local development, Deployer/Envoyer for deployment) with a single, zero-dependency binary that manages the entire lifecycle from local development to production deployment.

**One command. Zero config. From local to production.**

```bash
# Create a new Laravel project with Docker environment
tuti stack:laravel my-app

# Start local development (Traefik handles routing, SSL, ports)
tuti local:start

# Deploy to production (planned)
tuti deploy production
```

---

## The Problem

Developers currently juggle 5-10 different tools to develop and ship a single application:

| Concern | Current Tools | With Tuti CLI |
|---------|---------------|---------------|
| Local dev environment | Lando, DDEV, Sail, Spin | `tuti local:start` |
| Docker Compose config | Manual YAML writing | Auto-generated from templates |
| Port management | Manual tracking, conflicts | Traefik reverse proxy (automatic) |
| SSL for local dev | mkcert + manual config | Automatic via Traefik |
| .env configuration | Manual per-environment | Auto-generated with secure passwords |
| Multi-project switching | `cd` + remember status | `tuti projects:list` (planned) |
| Deployment | Deployer, Envoyer, scripts | `tuti deploy` (planned) |
| Environment parity | Hope and prayer | Same configs, guaranteed parity |

This fragmentation causes:
- Context switching friction between tools
- Environment drift (local != staging != production)
- Hidden, undocumented deployment knowledge
- Port conflicts when running multiple projects
- 8-12 hours/week wasted on environment management

---

## Key Features

### Implemented

- **Stack Templates** - Pre-configured Docker environments for Laravel and WordPress with interactive service selection (databases, cache, search, storage, mail, workers)
- **Docker Compose Generation** - Section-based stub system that generates base + dev overlay compose files with YAML anchors and healthchecks
- **Traefik Reverse Proxy** - Global infrastructure providing automatic SSL, `*.local.test` domain routing, and zero port conflicts across multiple projects
- **Environment Management** - Single `.env` file strategy shared by framework and Docker, with auto-generated cryptographically secure passwords
- **Local Lifecycle Commands** - `start`, `stop`, `logs`, `status`, `rebuild` for managing project containers
- **Infrastructure Management** - `infra:start`, `infra:stop`, `infra:restart`, `infra:status` for the global Traefik proxy
- **System Health Checks** - `tuti doctor` validates Docker, Compose, config, infrastructure, and project setup
- **Debug Logging** - Structured rotating logs with enable/disable/clear commands
- **Interactive Command Finder** - `tuti find` with fuzzy search across all available commands
- **Beautiful CLI UX** - Branded output system with 50+ UI methods, 5 color themes, powered by Laravel Prompts

### Planned

- **Remote Deployment** - SSH-based deployment to staging/production servers
- **Multi-Project Management** - List, switch, and monitor all registered projects
- **Additional Stacks** - Next.js, Django, Nuxt.js, Rails support
- **CI/CD Templates** - Pre-configured GitHub Actions / GitLab CI pipelines
- **Database Backup/Restore** - Snapshot and restore persistent volumes

---

## Architecture

### Tech Stack

| Component | Technology | Purpose |
|-----------|------------|---------|
| Runtime | PHP 8.4+ | Language |
| Framework | Laravel Zero 12.x | CLI framework |
| Testing | Pest (parallel) | Unit/feature tests |
| Analysis | PHPStan (level 5+) | Static type checking |
| Formatting | Laravel Pint | PSR-12 code style |
| Refactoring | Rector | Automated code upgrades |
| Containers | Docker Compose v2 | Container orchestration |
| Proxy | Traefik v3.2 | Reverse proxy, SSL, routing |
| Distribution | phpacker | Binary compilation (embeds PHP 8.4 runtime) |

### Design Principles

- **All classes `final`** - Composition over inheritance
- **Services `final readonly`** - Immutable service objects
- **Constructor injection only** - No property injection, no setters
- **`declare(strict_types=1)`** - Everywhere, no exceptions
- **File-based storage** - JSON configs, no database dependency
- **Runtime state from Docker** - Container status queried live, never persisted
- **Zero dependencies for users** - Single binary with embedded PHP runtime (~25-50MB)

### High-Level Architecture

```
User
 |
 +-- tuti install          --> Global setup (Traefik, configs, certs)
 +-- tuti stack:laravel    --> Stack initialization pipeline:
 |                              StackLoaderService (parse stack.json)
 |                              StackFilesCopierService (copy templates)
 |                              StackComposeBuilderService (generate YAML)
 |                              StackEnvGeneratorService (generate .env)
 |                              ProjectMetadataService (save config.json)
 +-- tuti local:start      --> Docker orchestration:
 |                              ProjectStateManagerService (load config)
 |                              GlobalInfrastructureManager (ensure Traefik)
 |                              DockerComposeOrchestrator (docker compose up)
 +-- tuti doctor           --> Health checks (Docker, Compose, Traefik, project)
 +-- tuti infra:*          --> Infrastructure management (Traefik lifecycle)

Storage:
 ~/.tuti/                  --> Global: config, settings, registry, logs, Traefik
 {project}/.tuti/          --> Project: config, docker-compose, Dockerfile, scripts
 {project}/.env            --> Shared environment variables (framework + Docker)
```

### Data Storage

All data is file-based (no database):

| Data | Format | Location |
|------|--------|----------|
| Global config | JSON | `~/.tuti/config.json` |
| User settings | JSON | `~/.tuti/settings.json` |
| Project registry | JSON | `~/.tuti/projects.json` |
| Project metadata | JSON | `{project}/.tuti/config.json` |
| Docker config | YAML | `{project}/.tuti/docker-compose.yml` |
| Environment vars | .env | `{project}/.env` |
| Debug logs | Text | `~/.tuti/logs/tuti.log` (rotating, 5MB x 5) |
| Stack templates | JSON/YAML/Stubs | Embedded in binary via `base_path()` |

---

## Supported Stacks

### Laravel Stack (10 services)

| Category | Services |
|----------|----------|
| Databases | PostgreSQL 17, MySQL 8.4, MariaDB 11.4 |
| Cache | Redis 7 |
| Search | Meilisearch 1.11, Typesense 27.1 |
| Storage | MinIO (S3-compatible) |
| Mail | Mailpit |
| Workers | Scheduler, Horizon (requires Redis) |

### WordPress Stack (5 services)

| Category | Services |
|----------|----------|
| Databases | MariaDB 11.4 (default), MySQL 8.4 |
| Cache | Redis 7 |
| Storage | MinIO (S3-compatible) |
| Mail | Mailpit |
| CLI | WP-CLI (auto-included) |

Both stacks support Standard and Bedrock (Composer) project structures for WordPress, and fresh creation or existing project setup for Laravel.

---

## Target Audience

**Primary users:**
- PHP/Laravel developers
- WordPress developers (Standard and Bedrock)
- Full-stack developers managing frontend + backend
- Solo developers and small teams (1-10 people)

**Technical requirements for users:**
- Comfortable with command line interfaces
- Basic Docker concepts (containers, volumes, networks)
- Familiarity with environment variables and `.env` files

---

## Platform Support

| Platform | Binary | Status |
|----------|--------|--------|
| Linux x64 | `tuti-linux-x64` | Supported |
| Linux ARM64 | `tuti-linux-arm64` | Supported |
| macOS x64 | `tuti-macos-x64` | Supported |
| macOS ARM64 | `tuti-macos-arm64` | Supported |
| Windows | WSL2 (via Linux binary) | Supported |

Installation is a single command:

```bash
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash
```

---

## Project Status

Tuti CLI is in **active pre-release development**. Local development features are functional and usable. Deployment features are planned for upcoming phases.

See:
- [`docs/phases/`](phases/) - Development roadmap and phase breakdown
- [`docs/user-stories.md`](user-story.md) - Feature requirements and acceptance criteria
- [`docs/tuti-cli-discovery.md`](project-discovery.md) - Full business discovery document

---

## Contributing

The project follows strict PHP standards:
- All code must pass `composer test` (Rector + Pint + PHPStan + Pest)
- Classes are `final`, services are `final readonly`
- Constructor injection only, explicit types everywhere
- PSR-12 formatting, `declare(strict_types=1)` in every file

See [`CLAUDE.md`](../CLAUDE.md) for complete coding standards, directory structure, and development guidelines.

---

## Competitive Landscape

| Tool | Local Dev | Deployment | Multi-Project | CLI UX | Zero Dependencies |
|------|-----------|------------|---------------|--------|-------------------|
| **Tuti CLI** | Yes | Planned | Planned | Modern (branded) | Yes (single binary) |
| Lando | Yes | No | Limited | Basic | No (requires Docker) |
| DDEV | Yes | No | Limited | Basic | No (requires Docker) |
| Spin | Yes | Yes | No | Modern | No (requires Docker + PHP) |
| Laravel Sail | Yes | No | No | Basic | No (requires Docker + PHP) |
| Deployer | No | Yes | No | Basic | No (requires PHP) |
| Envoyer | No | Yes (SaaS) | Yes | Web UI | N/A (SaaS) |

Tuti CLI's differentiators:
1. **Unified local + deployment** in a single tool
2. **Zero dependencies** - single binary with embedded PHP runtime
3. **Modern CLI UX** - branded output, themes, interactive prompts
4. **Multi-framework** - Laravel, WordPress, and more planned
5. **Traefik-based routing** - no port conflicts, automatic SSL

---

**Last Updated:** 2026-02-07
