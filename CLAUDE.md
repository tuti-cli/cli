# Tuti CLI

> Multi-framework Docker environment management tool built with Laravel Zero.
> Builds to a self-contained PHAR/native binary via phpacker.

## Token Optimization Rules

**IMPORTANT: Follow these rules to minimize token usage:**

1. **Never read**: `vendor/`, `builds/`, `.git/`, `composer.lock`, `*.phar`, `*.sqlite`
2. **Use grep first**: Before reading files, use grep to find exact locations
3. **Read targeted**: Read only specific line ranges, not entire files

5. **Trust CLAUDE.md**: This file contains all patterns - don't re-read source for conventions
6. **Batch edits**: Group multiple changes per file into single edit operations
7. **No redundant context**: Don't re-read files already in conversation

**Quick Reference Commands:**
```bash
docker compose exec -T app composer test:unit          # Run tests (use for validation)
docker compose exec -T app composer test:types         # PHPStan check
docker compose exec -T app ./vendor/bin/pest --filter "test name"  # Run single test
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
docker compose exec -T app composer test              # All: rector + pint + phpstan + pest
docker compose exec -T app composer test:unit         # Pest tests only (parallel)
docker compose exec -T app composer test:types        # PHPStan static analysis
docker compose exec -T app composer test:lint         # Pint format check (dry-run)
docker compose exec -T app composer test:refactor     # Rector check (dry-run)
docker compose exec -T app composer test:coverage     # Pest with coverage
docker compose exec -T app composer lint              # Fix formatting with Pint
docker compose exec -T app composer refactor          # Fix code with Rector
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

## Security

- **ALWAYS use array syntax** for `Process::run()` — NEVER string interpolation
  - Safe: `Process::run(['docker', 'info'])`
  - Unsafe: `Process::run("docker info {$arg}")`
  - Unsafe: `Process::run(implode(' ', $parts))`
- **NEVER** use `escapeshellarg()` / `escapeshellcmd()` — array syntax eliminates the need
- **NEVER** interpolate variables into shell command strings
- All external process execution MUST go through array syntax for shell injection prevention
- Docker commands: centralize in `DockerService` / `DockerExecutorService` — no direct `Process::run(['docker', ...])` in commands or other services
- Validate file paths exist before passing to Process (use `file_exists()`, `is_dir()`)
- Use `Process::path($dir)` for working directory — never `cd` in command strings

## .claude Configuration

### Directory Structure
```
.claude/
├── agents/              # Autonomous workers for complex tasks
│   └── agent-creator/   # Builder for creating new agents
└── skills/              # Knowledge and patterns
```

### Agents (Autonomous Workers)

| Agent | Purpose | When to Use |
|-------|---------|-------------|
| `stack-architect` | Creates complete stack templates | New framework stacks |
| `feature-architect` | Architecture decisions | Planning new features |
| `test-engineer` | Achieves test coverage | Writing comprehensive tests |
| `code-auditor` | Review and analysis | Code quality, security |
| `deployment-engineer` | CI/CD and infrastructure | Deployment pipelines |

### Skills (Knowledge & Patterns)

| Skill | Purpose | When to Use |
|-------|---------|-------------|
| `php-zero-patterns` | Laravel Zero + PHP 8.4 patterns | Writing PHP code |
| `docker-compose-patterns` | Docker Compose configurations | Docker files, service stubs |
| `debugging-guide` | Debugging strategies | Troubleshooting issues |
| `documentation-writer` | Writing documentation | README, docs, comments |
| `write-tests` | Pest testing patterns | Writing tests |
| `skill-creator` | Creating new skills | Building skills |

### Agents vs Skills

**Use Agents when:**
- Task is complex and multi-step
- Want autonomous execution
- Task needs its own tool set
- Example: "Create a new Drupal stack with Redis and Postgres"

**Use Skills when:**
- Providing knowledge and patterns
- User wants to drive the work
- Need reference material
- Example: "Help me understand the service stub format"

### Creating New Agents
```bash
python .claude/agents/agent-creator/scripts/init_agent.py my-agent --path .claude/agents
python .claude/agents/agent-creator/scripts/validate_agent.py .claude/agents/my-agent.md
python .claude/agents/agent-creator/scripts/package_agent.py .claude/agents/my-agent.md
```

### Creating New Skills
```bash
python .claude/skills/skill-creator/scripts/init_skill.py my-skill --path .claude/skills
python .claude/skills/skill-creator/scripts/quick_validate.py .claude/skills/my-skill
python .claude/skills/skill-creator/scripts/package_skill.py .claude/skills/my-skill
```

## Documentation Requirements

**IMPORTANT: When adding new features or making changes to the codebase, you MUST:**

1. **Update CLAUDE.md** if the change affects:
   - Directory structure
   - Naming conventions
   - PHP standards or patterns
   - Key interfaces
   - Common tasks table
   - Build process
   - Docker integration patterns

2. **Update related documentation** in `.claude/skills/` if the change affects:
   - PHP patterns → `php-zero-patterns/SKILL.md`
   - Docker configurations → `docker-compose-patterns/SKILL.md`
   - Testing patterns → `write-tests/SKILL.md`
   - Debugging procedures → `debugging-guide/SKILL.md`

3. **Update command descriptions** if adding/modifying commands:
   - Update `$description` property in command class
   - Update relevant `.claude/commands/` file if exists

4. **Update registry files** when adding:
   - New stacks → `stubs/stacks/registry.json`
   - New service stubs → `stubs/stacks/{stack}/services/registry.json`

**Documentation checklist for new features:**
- [ ] CLAUDE.md updated (if structural/pattern change)
- [ ] Related skill file updated (if pattern change)
- [ ] Command description clear and complete
- [ ] Registry files updated (if applicable)
- [ ] Tests include documentation of expected behavior


