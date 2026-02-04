# Tuti CLI - AI Assistant Context

> You are an expert in Laravel Zero, PHP 8.4, Docker, and CLI development.
> This is a multi-framework Docker environment management tool that builds to a self-contained binary.

---

## 🎯 Core Principles

- Write concise, technical responses with accurate PHP/Laravel Zero examples
- Prioritize SOLID principles and clean architecture
- Design for scalability - system manages multiple framework stacks (Laravel, WordPress, Next.js, Django)
- Prefer iteration and modularization over duplication
- Use consistent, descriptive names for variables, methods, and classes
- All code must work when compiled to PHAR/native binary

## 🔧 Dependencies & Environment

| Dependency | Version | Purpose |
|------------|---------|---------|
| PHP | 8.4+ | Runtime |
| Laravel Zero | 12.x | CLI Framework |
| Pest | Latest | Testing |
| PHPStan | Level 5+ | Static analysis |
| Docker Compose | v2 | Container orchestration |
| Phpacker | Latest | Binary compilation |

## 📐 PHP & Laravel Zero Standards

### Strict Typing (Required)
```php
<?php

declare(strict_types=1);

namespace App\Services\Stack;

final readonly class LaravelInstaller implements StackInstallerInterface
{
    public function __construct(
        private DockerExecutorInterface $docker,
        private StateManagerInterface $state,
    ) {}

    public function install(string $projectName): bool
    {
        // Implementation
    }
}
```

### Class Design Rules
- **All classes `final`** - Prevent inheritance, prefer composition
- **Services `final readonly`** - Immutable service objects
- **Constructor injection only** - No property injection or setters
- **Explicit return types** - Always declare return types
- **Type hints everywhere** - Parameters, properties, returns

### Command Pattern
```php
final class StackLaravelCommand extends Command
{
    protected $signature = 'stack:laravel {name} {--mode=}';
    protected $description = 'Install Laravel stack';

    public function handle(LaravelInstaller $installer): int
    {
        // Use method injection for dependencies
        return $installer->install($this->argument('name'))
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
```

### Error Handling
- Use custom exceptions: `App\Exceptions\{Domain}Exception`
- Log errors via Laravel's logging facade
- Return `Command::FAILURE` on errors, never `exit()`
- Provide user-friendly error messages via `$this->error()`

## 📁 Directory Structure

| Directory | Purpose | Example |
|-----------|---------|---------|
| `app/Commands/` | CLI commands grouped by domain | `Stack/LaravelCommand.php` |
| `app/Services/` | Business logic | `Stack/LaravelInstaller.php` |
| `app/Contracts/` | Interfaces | `StackInstallerInterface.php` |
| `app/Providers/` | Service providers | `StackServiceProvider.php` |
| `stubs/stacks/` | Framework templates | `laravel/`, `wordpress/` |
| `stubs/services/` | Optional services | `databases/mysql.stub` |
| `tests/` | Pest tests | `Feature/`, `Unit/` |

## 🐳 Docker Integration Patterns

### Compose Generation
- Use YAML anchors for shared config (`x-app-env`)
- Network naming: `${PROJECT_NAME}_${APP_ENV}_network`
- Container naming: `${PROJECT_NAME}_${APP_ENV}_{service}`
- Always include healthchecks for services

### Service Stubs
```yaml
# stubs/services/workers/scheduler.stub
scheduler:
  container_name: ${PROJECT_NAME}_${APP_ENV}_scheduler
  build:
    context: ..
    dockerfile: .tuti/docker/Dockerfile
    target: ${TARGET:-development}
  command: ["php", "/var/www/html/artisan", "schedule:work"]
  networks:
    - ${PROJECT_NAME}_${APP_ENV}_network
```

## ✅ Testing Standards

```php
// Pest test example
it('installs laravel stack', function () {
    $installer = app(LaravelInstaller::class);
    
    expect($installer->install('test-app'))->toBeTrue()
        ->and(file_exists('test-app/.tuti'))->toBeTrue();
});
```

**Commands:**
```bash
composer test           # All tests
composer test:unit      # Unit only
composer test:feature   # Feature only
composer pint           # Code formatting
composer phpstan        # Static analysis
```

## 🏗️ Build Process

```bash
make build-phar         # Build PHAR first
make test-phar          # Test PHAR works
make build-binary       # Compile native binaries
make test-binary        # Test binary
```

**Binary considerations:**
- No runtime file access outside project directory
- Stubs embedded in PHAR/binary
- Use `base_path()` for stub resolution

## 🔑 Key Interfaces

```php
interface StackInstallerInterface {
    public function getIdentifier(): string;
    public function supports(string $stack): bool;
    public function installFresh(string $name, array $options): bool;
    public function applyToExisting(string $path, array $options): bool;
}

interface DockerExecutorInterface {
    public function compose(string $path, array $args): ProcessResult;
    public function exec(string $container, array $command): ProcessResult;
}
```

## 📋 Common Tasks

| Task | Files to modify |
|------|----------------|
| Add CLI command | `app/Commands/{Category}/Command.php` + register in provider |
| Add service class | `app/Services/{Domain}/Service.php` + bind in provider |
| Add framework stack | `stubs/stacks/{name}/` + Installer + Command |
| Add optional service | `stubs/services/{category}/name.stub` + registry.json |

---

## 📂 Folder Governance (.ai/)

### ✅ Allowed

| Type | Path | Description |
|------|------|-------------|
| Rules | `RULES.md` | This file - AI context |
| Index | `INDEX.md` | Navigation |
| Guidelines | `guidelines/**/*.md` | Coding patterns |
| Skills | `skills/*/SKILL.md` | Reusable task guides |

### ❌ Not Allowed

| Type | Reason |
|------|--------|
| Implementation summaries | Use CHANGELOG.md |
| Feature docs | Put in `docs/` |
| Session logs | Temporary |
| Quick references | Temporary |

### Decision Tree

```
Need to document something?
├─ One-time implementation → CHANGELOG.md
├─ User-facing feature → docs/*.md
├─ Coding pattern (3+ uses) → guidelines/**/*.md
├─ Repeatable AI task → skills/*/SKILL.md
└─ Project context → RULES.md (this file)
```
