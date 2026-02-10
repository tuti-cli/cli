# Tuti CLI

> Multi-framework Docker environment management tool built with Laravel Zero.
> Builds to a self-contained PHAR/native binary via phpacker.

## Token Optimization Rules

**IMPORTANT: Follow these rules to minimize token usage:**

1. **Never read**: `vendor/`, `builds/`, `.git/`, `composer.lock`, `*.phar`, `*.sqlite`
2. **Use grep first**: Before reading files, use grep to find exact locations
3. **Read targeted**: Read only specific line ranges, not entire files
4. **Skip docs**: Don't read `.claude/docs/*` unless specifically asked
5. **Trust CLAUDE.md**: This file contains all patterns - don't re-read source for conventions
6. **Batch edits**: Group multiple changes per file into single edit operations
7. **No redundant context**: Don't re-read files already in conversation

**Quick Reference Commands:**
```bash
composer test:unit          # Run tests (use for validation)
composer test:types         # PHPStan check
./vendor/bin/pest --filter "test name"  # Run single test
```

## Tech Stack

| Dependency | Version | Purpose |
|------------|---------|---------|
| PHP | 8.4+ | Runtime |
| Laravel Zero | 12.x | CLI Framework |
| Pest | Latest | Testing (parallel) |
| PHPStan | Level 5+ | Static analysis |
| Laravel Pint | Latest | Code formatting (PSR-12) |
| Rector | Latest | Automated refactoring |
| Docker Compose | v2 | Container orchestration |
| Phpacker | Latest | Binary compilation |

## PHP Standards

- `declare(strict_types=1)` in every file
- All classes `final` -- prefer composition over inheritance
- Services `final readonly` -- immutable service objects
- Constructor injection only -- no property injection, no setters
- Explicit return types and type hints everywhere
- No PHPDoc for type-hinted code
- PSR-12 formatting via Laravel Pint
- Trailing commas in multiline arrays
- Return early, avoid else/elseif

## Class Patterns

```php
// Service pattern
final readonly class MyService
{
    public function __construct(
        private SomeInterface $dependency,
    ) {}
}

// Command pattern -- all commands use HasBrandedOutput trait
final class MyCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'category:action {argument?} {--option=default}';
    protected $description = 'What it does';

    public function __construct(private readonly MyService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->brandedHeader('Feature Name');
        // Return Command::SUCCESS or Command::FAILURE, never exit()
    }
}
```

## Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Class | PascalCase | `StackInitializationService` |
| Method | camelCase | `getStackPath()` |
| Variable | camelCase | `$stackName` |
| Constant | UPPER_SNAKE | `MAX_RETRIES` |
| Interface | PascalCase + Interface | `StackInstallerInterface` |
| Command signature | `category:action` | `stack:laravel`, `local:start` |

## Directory Structure

```
app/
├── Commands/              # Console commands grouped by domain
│   ├── Infrastructure/   # infra:start, infra:stop, infra:restart, infra:status
│   ├── Local/            # local:start, local:stop, local:logs, local:rebuild, local:status
│   ├── Stack/            # stack:laravel, stack:wordpress, stack:init, stack:manage, wp:setup
│   └── Test/             # Testing/debugging commands
├── Concerns/             # Traits: HasBrandedOutput, BuildsProjectUrls
├── Contracts/            # Interfaces: StackInstallerInterface, OrchestratorInterface,
│                         #   DockerExecutorInterface, InfrastructureManagerInterface, StateManagerInterface
├── Domain/               # Domain models, value objects (ProjectConfigurationVO)
├── Enums/                # PHP enums (Theme)
├── Infrastructure/       # Implementations (DockerComposeOrchestrator)
├── Providers/            # AppServiceProvider, StackServiceProvider,
│                         #   ProjectServiceProvider, DotenvServiceProvider
├── Services/
│   ├── Context/          # WorkingDirectoryService
│   ├── Debug/            # DebugLogService (singleton)
│   ├── Docker/           # DockerExecutorService, DockerService
│   ├── Global/           # GlobalRegistryService, GlobalSettingsService
│   ├── Infrastructure/   # GlobalInfrastructureManager
│   ├── Project/          # ProjectDirectoryService, ProjectInitializationService,
│   │                     #   ProjectMetadataService, ProjectStateManagerService
│   ├── Stack/            # StackComposeBuilderService, StackEnvGeneratorService,
│   │   │                 #   StackFilesCopierService, StackInitializationService,
│   │   │                 #   StackInstallerRegistry, StackLoaderService,
│   │   │                 #   StackRegistryManagerService, StackRepositoryService,
│   │   │                 #   StackStubLoaderService
│   │   └── Installers/   # LaravelStackInstaller, WordPressStackInstaller
│   └── Storage/          # JsonFileService
└── Support/              # Helper functions (helpers.php)

stubs/
├── stacks/
│   ├── registry.json              # Stack definitions (laravel, wordpress)
│   ├── laravel/                   # Laravel stack template
│   │   ├── stack.json
│   │   ├── docker-compose.yml
│   │   ├── docker-compose.dev.yml
│   │   ├── docker/Dockerfile
│   │   ├── environments/.env.dev.example
│   │   ├── scripts/entrypoint-dev.sh
│   │   └── services/              # Laravel-specific service stubs + registry.json
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
│       └── services/              # WordPress-specific service stubs + registry.json
│           ├── databases/ (mysql, mariadb)
│           ├── cache/ (redis)
│           ├── cli/ (wpcli)
│           ├── mail/ (mailpit)
│           └── storage/ (minio)
└── infrastructure/
    └── traefik/                   # Global Traefik reverse proxy

tests/
├── Feature/
│   ├── Concerns/          # Test helpers (CreatesHelperTestEnvironment, etc.)
│   └── Console/           # Command tests (FindCommandTest, etc.)
├── Unit/Services/         # Service unit tests
├── Mocks/                 # FakeDockerOrchestrator, etc.
├── Pest.php               # Pest config
└── TestCase.php           # Base test case
```

## Docker Integration

- Compose generation uses **base + overlay** pattern (docker-compose.yml + docker-compose.dev.yml)
- Container naming: `${PROJECT_NAME}_${APP_ENV}_{service}`
- Network naming: `${PROJECT_NAME}_${APP_ENV}_network`
- Volume naming: `${PROJECT_NAME}_${APP_ENV}_{volume}_data`
- YAML anchors for shared config (`x-app-env-base`, `x-app-build-base`, `x-common-service`)
- Always include healthchecks for services
- Use `${VAR:-default}` syntax in compose files
- `{{VAR}}` syntax for build-time replacements in stubs
- Single `.env` file in project root (shared by Laravel and Docker Compose)
- Docker Compose uses `--env-file ./.env` explicitly
- Service stubs live inside each stack: `stubs/stacks/{stack}/services/`
- Global Traefik infrastructure in `stubs/infrastructure/traefik/`

## Service Stubs (Section-Based Format)

Stubs use `# @section:` markers to split into base, dev, volumes, and env sections:

```yaml
# @section: base     → goes into docker-compose.yml
# @section: dev      → goes into docker-compose.dev.yml
# @section: volumes  → volume definitions
# @section: env      → variables added to .env
```

## Console Output (HasBrandedOutput)

All commands use the `HasBrandedOutput` trait from `App\Concerns\HasBrandedOutput`.
Key methods: `brandedHeader()`, `step()`, `success()`, `failure()`, `created()`,
`modified()`, `completed()`, `failed()`, `section()`, `keyValue()`, `tipBox()`, `warningBox()`.
Available themes: `LaravelRed`, `Gray`, `Ocean`, `Vaporwave`, `Sunset` (from `App\Enums\Theme`).
See `.claude/docs/console-display.md` for full method reference.

## Testing

- Framework: Pest (parallel execution)
- Use `describe()` blocks for organization
- Test names: descriptive (`it('creates .tuti directory with correct structure')`)
- Mock services via `$this->app->instance()`
- Test helpers in `tests/Feature/Concerns/` (CreatesHelperTestEnvironment, CreatesLocalProjectEnvironment, CreatesTestStackEnvironment)
- Mock orchestrator: `tests/Mocks/FakeDockerOrchestrator.php`
- Command tests: `$this->artisan('command')->assertExitCode(Command::SUCCESS)`
- Coverage targets: Commands >80%, Services >90%, Helpers >95%

```bash
composer test              # All: rector + pint + phpstan + pest
composer test:unit         # Pest tests only (parallel)
composer test:types        # PHPStan static analysis
composer test:lint         # Pint format check (dry-run)
composer test:refactor     # Rector check (dry-run)
composer test:coverage     # Pest with coverage
composer lint              # Fix formatting with Pint
composer refactor          # Fix code with Rector
```

## Build Process

```bash
make build-phar            # Build PHAR
make test-phar             # Test PHAR works
make build-binary          # All platform binaries (phpacker)
make build-binary-linux    # Linux only
make build-binary-mac      # macOS only
make test-binary           # Test binary without PHP
make install-local         # Install to ~/.tuti/bin
make uninstall-local       # Remove from ~/.tuti/bin
make release-auto V=x.y.z  # Automated release
```

All code must work when compiled to PHAR/native binary. Use `base_path()` for stub resolution.

## Key Interfaces (app/Contracts/)

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

interface OrchestratorInterface {
    public function start(): void;
    public function stop(): void;
    public function restart(): void;
    public function status(): array;
    public function logs(?string $service = null): string;
}

interface InfrastructureManagerInterface {
    public function isInstalled(): bool;
    public function isRunning(): bool;
    public function install(): void;
    public function start(): void;
    public function stop(): void;
    public function ensureReady(): void;
}
```

## Common Tasks

| Task | Files to modify |
|------|----------------|
| Add CLI command | `app/Commands/{Category}/Command.php` |
| Add service class | `app/Services/{Domain}/Service.php` + bind in `app/Providers/AppServiceProvider.php` |
| Add framework stack | `stubs/stacks/{name}/` + `app/Services/Stack/Installers/{Name}StackInstaller.php` + `app/Commands/Stack/{Name}Command.php` + `stubs/stacks/registry.json` + register in `app/Providers/StackServiceProvider.php` |
| Add service stub | `stubs/stacks/{stack}/services/{category}/{name}.stub` + `stubs/stacks/{stack}/services/registry.json` |

## Error Handling

- Return `Command::FAILURE` on errors, never `exit()`
- User-friendly messages via `$this->error()` or `$this->failed()`
- Debug logging via `DebugLogService` (singleton, helper: `tuti_debug()`)
- Log location: `~/.tuti/logs/tuti.log`

## Additional Docs

Detailed guidelines available in `.claude/docs/`:
- `architecture.md` - Full architecture details
- `coding-standards.md` - Detailed coding rules
- `console-display.md` - HasBrandedOutput full method reference
- `testing-guide.md` - Comprehensive testing patterns and templates
- `docker-integration.md` - Docker patterns, Traefik, troubleshooting
- `debug-system.md` - Debug logging system
- `environment-variables.md` - Single .env file strategy
- `stack-system.md` - Stack system internals
- `commands.md` - Laravel Zero command patterns
- `testing.md` - Pest testing patterns
- `tuti-cli.md` - Project-specific guidelines (permissions, volumes, health checks)
