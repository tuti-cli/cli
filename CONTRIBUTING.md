# Contributing to Tuti CLI

Thank you for your interest in contributing to Tuti CLI! This document provides guidelines and instructions for contributing to the project.

## Table of Contents

- [Development Environment Setup](#development-environment-setup)
- [Project Structure](#project-structure)
- [Code Style Guidelines](#code-style-guidelines)
- [Static Analysis](#static-analysis)
- [Testing](#testing)
- [Commit Message Conventions](#commit-message-conventions)
- [Branch Strategy](#branch-strategy)
- [Pull Request Guidelines](#pull-request-guidelines)
- [Development Workflow](#development-workflow)
- [Getting Help](#getting-help)

---

## Development Environment Setup

### Prerequisites

| Requirement | Minimum Version | Notes |
|-------------|-----------------|-------|
| Docker | 20.10+ | Docker Engine or Docker Desktop |
| Docker Compose | v2 | Usually bundled with Docker |
| Git | 2.x | Version control |

### Clone and Initial Setup

```bash
# Clone the repository
git clone https://github.com/tuti-cli/cli.git
cd cli

# Start Docker containers and install dependencies
make install

# Access container shell
make shell

# Verify installation
php tuti --version
```

### Useful Makefile Commands

| Command | Description |
|---------|-------------|
| `make install` | Start Docker and install dependencies |
| `make up` | Start Docker containers |
| `make down` | Stop Docker containers |
| `make shell` | Access container shell |
| `make logs` | View container logs |
| `make c <cmd>` | Run any composer command (e.g., `make c test:unit`) |

---

## Project Structure

```
app/
├── Commands/              # Console commands grouped by domain
│   ├── Infrastructure/   # infra:start, infra:stop, infra:status
│   ├── Local/            # local:start, local:stop, local:logs
│   ├── Stack/            # stack:laravel, stack:wordpress, stack:init
│   └── Test/             # Testing/debugging commands
├── Concerns/             # Traits (HasBrandedOutput, etc.)
├── Contracts/            # Interfaces
├── Domain/               # Domain models, value objects
├── Enums/                # PHP enums (Theme, etc.)
├── Infrastructure/       # Implementations
├── Providers/            # Service providers
├── Services/             # Business logic services
└── Support/              # Helper functions

stubs/
├── stacks/               # Stack templates (laravel, wordpress)
│   └── {stack}/services/ # Service stubs (databases, cache, etc.)
└── infrastructure/       # Global infrastructure (Traefik)

tests/
├── Feature/              # Feature/Integration tests
│   ├── Concerns/         # Test helpers
│   └── Console/          # Command tests
├── Unit/                 # Unit tests
├── Mocks/                # Test mocks
└── Pest.php              # Pest configuration
```

---

## Code Style Guidelines

### PHP Standards

All PHP code must follow these standards:

```php
<?php

declare(strict_types=1);

namespace App\Services\Example;

final readonly class ExampleService
{
    public function __construct(
        private SomeDependency $dependency,
    ) {}

    public function doSomething(string $input): string
    {
        if (empty($input)) {
            return '';
        }

        return $this->dependency->process($input);
    }
}
```

### Key Rules

| Rule | Description |
|------|-------------|
| `declare(strict_types=1)` | Required in every PHP file |
| `final` | All classes must be `final` |
| `final readonly` | Services should be immutable |
| Constructor injection | No property injection, no setters |
| Return types | Explicit return types on all methods |
| Type hints | Full type declarations everywhere |
| No PHPDoc | Skip PHPDoc for type-hinted code |
| PSR-12 | Formatting via Laravel Pint |
| Return early | Avoid `else`/`elseif` |

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Class | PascalCase | `StackInitializationService` |
| Method | camelCase | `getStackPath()` |
| Variable | camelCase | `$stackName` |
| Constant | UPPER_SNAKE | `MAX_RETRIES` |
| Interface | PascalCase + Interface | `StackInstallerInterface` |
| Command signature | `category:action` | `stack:laravel`, `local:start` |

---

## Static Analysis

### PHPStan

The project uses PHPStan at level 5+ for static analysis:

```bash
# Run static analysis
make c test:types

# Or directly
docker compose exec -T app composer test:types
```

### Rector

Rector is used for automated refactoring:

```bash
# Check what would be changed (dry-run)
make c test:refactor

# Apply fixes
make refactor
```

---

## Testing

### Test Framework

The project uses **Pest** with parallel execution for testing.

### Running Tests

```bash
# Run all quality checks (refactor + lint + types + tests)
make c test

# Run only Pest tests
make c test:unit

# Run tests with coverage
make c test:coverage

# Run a specific test
docker compose exec -T app ./vendor/bin/pest --filter "test_name"
```

### Test Commands Summary

| Command | Description |
|---------|-------------|
| `make c test` | Run all checks (refactor, lint, types, unit) |
| `make c test:unit` | Run Pest tests (parallel) |
| `make c test:types` | PHPStan static analysis |
| `make c test:lint` | Pint format check (dry-run) |
| `make c test:refactor` | Rector check (dry-run) |
| `make c test:coverage` | Pest with coverage report |
| `make lint` | Fix formatting with Pint |
| `make refactor` | Fix code with Rector |

### Test Organization

- **Location**: `tests/Feature/Console/` for command tests
- **Naming**: `{Category}{Command}Test.php` (e.g., `InfraStartCommandTest.php`)
- **Structure**: Use `describe()` blocks for organization
- **Test names**: Descriptive (e.g., `it('creates .tuti directory with correct structure')`)

### Coverage Targets

| Component | Target |
|-----------|--------|
| Commands | >80% |
| Services | >90% |
| Helpers | >95% |

### Example Test

```php
<?php

declare(strict_types=1);

use Tests\TestCase;
use App\Services\MyService;

describe('MyService', function () {
    it('does something correctly', function () {
        $service = app(MyService::class);

        $result = $service->doSomething('input');

        expect($result)->toBe('expected output');
    });
});
```

---

## Commit Message Conventions

This project follows **Conventional Commits** format:

### Format

```
type(scope): subject

[optional body]
```

### Types

| Type | Description |
|------|-------------|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation changes |
| `style` | Code style (formatting, semicolons) |
| `refactor` | Code refactoring |
| `test` | Adding or updating tests |
| `chore` | Maintenance tasks |

### Examples

```
feat(local): add port conflict detection
fix(docker): resolve container naming issue
docs(readme): update installation instructions
test(stack): add tests for Laravel stack installer
chore(deps): update composer dependencies
```

---

## Branch Strategy

### Branch Types

| Branch | Purpose |
|--------|---------|
| `main` | Stable releases only |
| `develop` | Active development |
| `feature/*` | New features (e.g., `feature/redis-support`) |
| `fix/*` | Bug fixes (e.g., `fix/port-conflict`) |
| `docs/*` | Documentation updates (e.g., `docs/api-reference`) |

### Creating a Branch

```bash
# For a new feature
git checkout -b feature/my-new-feature

# For a bug fix
git checkout -b fix/some-bug
```

---

## Pull Request Guidelines

### Before Submitting

1. **Run all tests**: `make c test`
2. **Fix formatting**: `make lint`
3. **Fix refactoring**: `make refactor`
4. **Update documentation** if needed

### PR Format

```markdown
## Summary
- Brief description of changes
- Why these changes were made

## Test plan
- [ ] Unit tests added/updated
- [ ] All tests pass locally
- [ ] Manual testing completed

## Screenshots (if applicable)
```

### Review Requirements

- All CI checks must pass (GitHub Actions)
- Code must follow project conventions
- New code should have appropriate test coverage
- Breaking changes must be documented

---

## Development Workflow

### Daily Development

```bash
# Start development environment
make up

# Access container shell
make shell

# Make your changes...

# Run tests
make c test

# Fix formatting
make lint
```

### Building

```bash
# Build PHAR (required before binary)
make build-phar

# Test PHAR works
make test-phar

# Build native binaries (all platforms)
make build-binary

# Build for specific platform
make build-binary-linux
make build-binary-mac

# Install locally for testing
make install-local
~/.tuti/bin/tuti --version
```

### Quality Gates

All code must pass these checks before merging:

1. **Rector** - No refactoring suggestions
2. **Pint** - PSR-12 formatting
3. **PHPStan** - Static analysis passes
4. **Pest** - All tests pass

These run automatically in CI via GitHub Actions.

---

## Security

### Process Execution

**IMPORTANT**: All external process execution must use array syntax to prevent shell injection:

```php
// ✅ Safe - Array syntax
Process::run(['docker', 'info']);

// ❌ Unsafe - String interpolation
Process::run("docker info {$arg}");

// ❌ Unsafe - Imploded string
Process::run(implode(' ', $parts));
```

- **Never** interpolate variables into shell command strings
- **Never** use `escapeshellarg()` / `escapeshellcmd()` - array syntax handles escaping
- Docker commands should go through `DockerService` / `DockerExecutorService`

---

## Getting Help

- **Issues**: [GitHub Issues](https://github.com/tuti-cli/cli/issues)
- **Discussions**: [GitHub Discussions](https://github.com/tuti-cli/cli/discussions)

When reporting issues, please include:

1. Your operating system and version
2. Docker and Docker Compose versions (`docker --version`, `docker compose version`)
3. Steps to reproduce the issue
4. Expected vs actual behavior
5. Any relevant logs or error messages

---

## License

By contributing to Tuti CLI, you agree that your contributions will be licensed under the [MIT License](LICENSE.md).

Thank you for contributing!
