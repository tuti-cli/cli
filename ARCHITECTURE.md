# Tuti CLI Architecture

> **Version:** 0.x (Pre-release)
> **Last Updated:** 2026-03-01

---

## Overview

Tuti CLI is a unified environment management and deployment tool for web developers. It manages the entire lifecycle from local Docker development to production deployment with a single, self-contained binary.

**Core Philosophy:**
- One command, zero config, from local to production
- Zero runtime dependencies for end users (single binary with embedded PHP)
- File-based storage (no database required)
- Layered architecture with dependency inversion

---

## Architecture Style

### Layered Architecture with Dependency Inversion

The project uses a pragmatic layered architecture, not full Clean Architecture. This approach provides:

- Clear separation of concerns
- Testable service layer
- Dependency inversion through interfaces
- Simplicity appropriate for a CLI tool

```
┌─────────────────────────────────────────────────────────────┐
│  Commands/ (Presentation Layer)                              │
│  • Input parsing, output formatting                          │
│  • Uses HasBrandedOutput trait for consistent UX             │
│  ┌───────────────────────────────────────────────────────┐  │
│  │  Services/ (Application Layer)                         │  │
│  │  • Business logic, orchestration                       │  │
│  │  • Never does direct I/O (uses dedicated services)     │  │
│  │  ┌─────────────────────────────────────────────────┐   │  │
│  │  │  Domain/ (Domain Layer)                          │   │  │
│  │  │  • Entities, Value Objects, Enums                │   │  │
│  │  │  • No persistence or I/O concerns                │   │  │
│  │  └─────────────────────────────────────────────────┘   │  │
│  └───────────────────────────────────────────────────────┘  │
│  Infrastructure/ (Infrastructure Layer)                      │
│  • External integrations (Docker, filesystem)                │
│  • Implements Contracts interfaces                           │
│  Contracts/ (Interface Definitions)                          │
│  • Defines boundaries between layers                         │
└─────────────────────────────────────────────────────────────┘
```

### Layer Responsibilities

| Layer | Directory | Owns | Never Does |
|-------|-----------|------|------------|
| **Commands** | `app/Commands/` | Input parsing, output formatting, user interaction | Business logic, direct file I/O |
| **Services** | `app/Services/` | Business logic, orchestration, validation | Direct Docker/ filesystem operations (use dedicated services) |
| **Infrastructure** | `app/Infrastructure/` | External system integrations | Business logic |
| **Domain** | `app/Domain/` | Entities, value objects, enums, state | Persistence, I/O, external dependencies |
| **Contracts** | `app/Contracts/` | Interface definitions | Implementation |

---

## Directory Structure

```
app/
├── Commands/              # CLI entry points (Presentation Layer)
│   ├── Infrastructure/   # infra:start, infra:stop, infra:restart, infra:status
│   ├── Local/            # local:start, local:stop, local:logs, local:rebuild, local:status
│   ├── Stack/            # stack:laravel, stack:wordpress, stack:init, stack:manage, wp:setup
│   ├── Debug/            # debug command
│   ├── Env/              # env:check command
│   └── Test/             # Testing/debugging commands (dev-only)
│
├── Concerns/             # Traits: HasBrandedOutput, BuildsProjectUrls
│
├── Contracts/            # Interfaces (Dependency Inversion)
│   ├── StackInstallerInterface.php
│   ├── OrchestratorInterface.php
│   ├── DockerExecutorInterface.php
│   ├── InfrastructureManagerInterface.php
│   └── StateManagerInterface.php
│
├── Domain/               # Domain Layer
│   └── Project/
│       ├── Project.php                    # Project entity
│       ├── Enums/ProjectStateEnum.php     # State enumeration
│       └── ValueObjects/
│           └── ProjectConfigurationVO.php # Configuration value object
│
├── Enums/                # Global enums (Theme, etc.)
│
├── Infrastructure/       # Infrastructure Layer
│   └── DockerComposeOrchestrator.php      # Docker orchestration implementation
│
├── Providers/            # Laravel service providers
│   ├── AppServiceProvider.php
│   ├── StackServiceProvider.php
│   └── ProjectServiceProvider.php
│
├── Services/             # Application Layer (organized by domain)
│   ├── Context/          # WorkingDirectoryService
│   ├── Debug/            # DebugLogService (singleton)
│   ├── Docker/           # DockerExecutorService, DockerCommandBuilder
│   ├── Global/           # GlobalRegistryService, GlobalSettingsService
│   ├── Infrastructure/   # GlobalInfrastructureManager
│   ├── Project/          # ProjectDirectoryService, ProjectInitializationService,
│   │                     #   ProjectMetadataService, ProjectStateManagerService
│   ├── Stack/            # StackComposeBuilderService, StackEnvGeneratorService,
│   │                     #   StackFilesCopierService, StackInitializationService,
│   │                     #   StackInstallerRegistry, StackLoaderService,
│   │                     #   StackRegistryManagerService, StackRepositoryService,
│   │                     #   StackStubLoaderService
│   │   └── Installers/   # LaravelStackInstaller, WordPressStackInstaller
│   ├── Storage/          # JsonFileService, EnvFileService
│   └── Support/          # Cross-cutting services
│
└── Support/              # Helper functions (helpers.php)

stubs/                    # Templates embedded in binary
├── stacks/
│   ├── registry.json              # Stack definitions (laravel, wordpress)
│   ├── laravel/                   # Laravel stack template
│   │   ├── stack.json
│   │   ├── docker-compose.yml
│   │   ├── docker-compose.dev.yml
│   │   ├── docker/Dockerfile
│   │   ├── environments/.env.dev.example
│   │   ├── scripts/entrypoint-dev.sh
│   │   └── services/              # Service stubs + registry.json
│   │       ├── databases/ (postgres, mysql, mariadb)
│   │       ├── cache/ (redis)
│   │       ├── search/ (meilisearch, typesense)
│   │       ├── storage/ (minio)
│   │       ├── mail/ (mailpit)
│   │       └── workers/ (scheduler, horizon)
│   └── wordpress/                 # WordPress stack template
│       ├── stack.json
│       ├── docker-compose.yml
│       ├── docker-compose.dev.yml
│       ├── docker/Dockerfile
│       ├── templates/wp-config.php
│       ├── environments/.env.dev.example
│       ├── scripts/entrypoint-dev.sh
│       └── services/              # WordPress service stubs
│           ├── databases/ (mysql, mariadb)
│           ├── cache/ (redis)
│           ├── cli/ (wpcli)
│           ├── mail/ (mailpit)
│           └── storage/ (minio)
│
└── infrastructure/
    └── traefik/                   # Global Traefik reverse proxy template

tests/
├── Feature/
│   ├── Concerns/          # Test helpers
│   └── Console/           # Command tests
├── Unit/Services/         # Service unit tests
├── Mocks/                 # FakeDockerOrchestrator, etc.
├── Pest.php               # Pest configuration
└── TestCase.php           # Base test case
```

---

## Tech Stack

| Component | Technology | Purpose |
|-----------|------------|---------|
| **Runtime** | PHP 8.4+ | Language |
| **Framework** | Laravel Zero 12.x | CLI framework |
| **Testing** | Pest (parallel) | Unit/feature tests |
| **Static Analysis** | PHPStan Level 5+ | Type checking |
| **Formatting** | Laravel Pint | PSR-12 code style |
| **Refactoring** | Rector | Automated code upgrades |
| **Containers** | Docker Compose v2 | Container orchestration |
| **Proxy** | Traefik v3.2 | Reverse proxy, SSL, routing |
| **Distribution** | phpacker | Binary compilation (embeds PHP runtime) |

---

## Dependencies

### Tier 1: Build Dependencies

Required for development and building the binary:

| Dependency | Purpose |
|------------|---------|
| PHP 8.4+ | Runtime |
| Composer | Package management |
| phpacker | Binary compilation |

### Tier 2: Runtime Dependencies

Required on user's machine:

| Dependency | Purpose | Managed By |
|------------|---------|------------|
| Docker Engine | Container runtime | User installs |
| Docker Compose v2 | Container orchestration | User installs |
| Traefik v3.2 | Reverse proxy | CLI manages |

### Tier 3: Optional Dependencies

Enhance functionality but not required:

| Dependency | Purpose |
|------------|---------|
| mkcert | Trusted SSL certificates |
| htpasswd | Traefik dashboard auth |

---

## Data Storage

All data is file-based (no database). This design choice provides:
- Zero additional dependencies
- Human-readable configuration
- Easy backup and version control
- Simple debugging

### Storage Locations

```
~/.tuti/                           # Global Tuti directory
├── config.json                    # Global CLI configuration
├── settings.json                  # User preferences
├── projects.json                  # Project registry
├── bin/
│   └── tuti                       # Installed binary
├── stacks/                        # Cached stack templates
├── cache/                         # Temporary files
├── logs/
│   └── tuti.log                   # Debug log (rotating, 5MB x 5)
└── infrastructure/
    └── traefik/
        ├── docker-compose.yml     # Traefik config
        ├── .env                   # Traefik environment
        ├── dynamic/tls.yml        # TLS configuration
        ├── certs/                 # SSL certificates
        └── secrets/users          # Dashboard auth

{project}/.tuti/                   # Project-specific
├── config.json                    # Project metadata
├── docker-compose.yml             # Generated base compose
├── docker-compose.dev.yml         # Development overlay
├── docker/Dockerfile              # Custom Dockerfile
├── environments/.env.dev.example  # Environment template
└── scripts/entrypoint-dev.sh      # Permission fixer

{project}/.env                     # Shared environment (Laravel + Docker)
```

### File Formats

| Data | Format | Service |
|------|--------|---------|
| Global config | JSON | `InstallCommand` |
| Global settings | JSON | `GlobalSettingsService` |
| Project registry | JSON | `GlobalRegistryService` |
| Project config | JSON | `ProjectMetadataService` |
| Stack manifests | JSON | `StackLoaderService` |
| Service registries | JSON | `StackRegistryManagerService` |
| Docker Compose | YAML | `StackComposeBuilderService` |
| Environment vars | .env | `StackEnvGeneratorService` |
| Debug logs | Text (structured) | `DebugLogService` |

### Storage Safeguards

| Feature | Status | Location |
|---------|--------|----------|
| JSON validation on read | ✅ Implemented | `JsonFileService` (JSON_THROW_ON_ERROR) |
| Schema validation | ✅ Implemented | `StackLoaderService.validate()` |
| Stale project detection | ✅ Implemented | `GlobalRegistryService.getStaleProjects()` |
| Stale project cleanup | ✅ Implemented | `GlobalRegistryService.pruneStale()` |
| File locking (logs) | ✅ Implemented | `DebugLogService` (LOCK_EX) |
| Atomic file rotation | ✅ Implemented | `DebugLogService` (rename pattern) |
| Atomic writes (JSON) | ✅ Implemented | `JsonFileService.write()` (tempnam + rename) |

---

## Stack System Architecture

### Stack Template Structure

Each stack is a self-contained template:

```
stubs/stacks/{stack-name}/
├── stack.json              # Manifest: name, version, services, variables
├── docker-compose.yml      # Base compose template
├── docker-compose.dev.yml  # Development overlay
├── docker/Dockerfile       # Application container
├── environments/           # Environment templates
├── scripts/                # Entrypoint scripts
├── templates/              # Framework-specific templates (e.g., wp-config.php)
└── services/               # Pluggable service stubs
    ├── registry.json       # Available services
    ├── databases/          # Database service stubs
    ├── cache/              # Cache service stubs
    └── ...
```

### Stack Installer Interface

```php
interface StackInstallerInterface {
    public function getIdentifier(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getFramework(): string;
    public function supports(string $stack): bool;
    public function installFresh(string $path, string $name, array $options): bool;
    public function applyToExisting(string $path, array $options): bool;
}
```

### Service Stub Format

Service stubs use section markers for compose generation:

```yaml
# @section: base      → Goes into docker-compose.yml
# @section: dev       → Goes into docker-compose.dev.yml
# @section: volumes   → Volume definitions
# @section: env       → Environment variable templates
```

### Placeholder Syntax

| Syntax | Purpose | Replaced By |
|--------|---------|-------------|
| `{{VAR}}` | Build-time replacement | `StackFilesCopierService` |
| `${VAR:-default}` | Runtime substitution | Docker Compose |

---

## Infrastructure Architecture

### Traefik Reverse Proxy

Global infrastructure managed by `GlobalInfrastructureManager`:

```
┌─────────────────────────────────────────────────────────────┐
│  Traefik v3.2 (Global)                                      │
│  • Ports: 80, 443                                           │
│  • Dashboard: :8080 (htpasswd protected)                    │
│  • Auto SSL via mkcert or self-signed                       │
│  • Wildcard routing: *.local.test                           │
└─────────────────────────────────────────────────────────────┘
          │
          ├──▶ project-a.local.test  →  project-a containers
          ├──▶ project-b.local.test  →  project-b containers
          └──▶ project-c.local.test  →  project-c containers
```

### Docker Compose Pattern

Projects use **base + overlay** pattern:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

- `docker-compose.yml` - Base services (app, database)
- `docker-compose.dev.yml` - Development additions (mail, volumes)

### Container Naming Convention

```
${PROJECT_NAME}_${APP_ENV}_{service}
${PROJECT_NAME}_${APP_ENV}_network
${PROJECT_NAME}_${APP_ENV}_{volume}_data
```

---

## Build & Distribution

### PHAR/Binary Compilation

```bash
make build-phar        # Build PHAR archive
make build-binary      # Build native binaries (phpacker)
make test-phar         # Test PHAR works
make test-binary       # Test binary without PHP
```

### Supported Platforms

| Platform | Binary | Status |
|----------|--------|--------|
| Linux x64 | `tuti-linux-x64` | ✅ Supported |
| Linux ARM64 | `tuti-linux-arm64` | ✅ Supported |
| macOS x64 | `tuti-macos-x64` | ✅ Supported |
| macOS ARM64 | `tuti-macos-arm64` | ✅ Supported |
| Windows | WSL2 (via Linux binary) | ✅ Supported |

### Release Process

```bash
make release-auto V=x.y.z  # Bump version, build, test, tag
git push --tags            # Triggers GitHub Actions release workflow
```

---

## Testing Architecture

### Test Structure

```
tests/
├── Feature/
│   ├── Concerns/          # Test helpers
│   │   ├── CreatesHelperTestEnvironment.php
│   │   ├── CreatesLocalProjectEnvironment.php
│   │   └── CreatesTestStackEnvironment.php
│   └── Console/           # Command tests
│       ├── FindCommandTest.php
│       └── ...
├── Unit/Services/         # Service unit tests
│   ├── Stack/
│   ├── Global/
│   └── ...
├── Mocks/                 # Test doubles
│   └── FakeDockerOrchestrator.php
├── Pest.php
└── TestCase.php
```

### Coverage Targets

| Layer | Target |
|-------|--------|
| Commands | >80% |
| Services | >90% |
| Helpers | >95% |

### Test Commands

```bash
composer test              # All: rector + pint + phpstan + pest
composer test:unit         # Pest tests only (parallel)
composer test:types        # PHPStan static analysis
composer test:lint         # Pint format check (dry-run)
composer test:refactor     # Rector check (dry-run)
composer test:coverage     # Pest with coverage
```

---

## Key Interfaces

### OrchestratorInterface

```php
interface OrchestratorInterface {
    public function start(): void;
    public function stop(): void;
    public function restart(): void;
    public function status(): array;
    public function logs(?string $service = null): string;
}
```

### InfrastructureManagerInterface

```php
interface InfrastructureManagerInterface {
    public function isInstalled(): bool;
    public function isRunning(): bool;
    public function install(): void;
    public function start(): void;
    public function stop(): void;
    public function ensureReady(): void;
}
```

---

## Security

### Process Execution

All external command execution uses array syntax to prevent shell injection:

```php
// ✅ Safe - array syntax
Process::run(['docker', 'compose', 'up', '-d']);

// ❌ Unsafe - string interpolation (NEVER use)
Process::run("docker compose up -d {$arg}");
```

### Secrets Handling

| Concern | Implementation |
|---------|---------------|
| Password generation | `bin2hex(random_bytes(16))` - cryptographically secure |
| Sensitive value masking | `env:check --show` masks PASSWORD, KEY, SECRET, TOKEN |
| .env files | Standard Laravel practice, must be gitignored |
| Docker socket | Mounted read-only for Traefik |
| Traefik dashboard | Protected by htpasswd basic auth |
| Telemetry | Disabled by default, no data collected |

---

## Future Architecture Considerations

### Deployment (Not Yet Implemented)

Planned architecture for remote deployment:

```
app/Services/Deployment/
├── DeploymentService.php
├── SshExecutorService.php
├── FtpUploaderService.php
├── ReleaseManagerService.php
└── RollbackService.php

app/Commands/Deploy/
├── DeployCommand.php
├── DeployConfigureCommand.php
└── DeployRollbackCommand.php
```

### Multi-Project Management (Partially Implemented)

Backend services exist, CLI commands planned:

```
app/Commands/Projects/
├── ProjectsListCommand.php
├── ProjectsStatusCommand.php
└── ProjectsCleanCommand.php
```

### Additional Stacks (Planned)

- Next.js (Node.js)
- Nuxt.js (Node.js)
- Django (Python)
- Laravel + (React, Vue, etc.)
- Go
- Shopify
- Symfony
---

## Architecture Decision Records

Key architectural decisions are documented in `.workflow/ADRs/`:

| ADR | Title | Status |
|-----|-------|--------|
| ADR-001 | File-based storage over database | ✅ Adopted |
| ADR-002 | Traefik for reverse proxy | ✅ Adopted |
| ADR-003 | phpacker for binary distribution | ✅ Adopted |
| ADR-004 | Section-based stub format | ✅ Adopted |
| ADR-005 | Single .env file strategy | ✅ Adopted |

---

## References

- [CLAUDE.md](CLAUDE.md) - Development conventions and coding standards
- [docs/planning/project-description.md](docs/planning/project-description.md) - Project overview
- [docs/planning/user-story.md](docs/planning/user-story.md) - Feature requirements
- [docs/planning/project-discovery.md](docs/planning/project-discovery.md) - Business discovery
