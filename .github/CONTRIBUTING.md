# Contributing to Tuti CLI

Thank you for considering contributing to Tuti CLI! This document provides guidelines and instructions for contributing.

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to the project maintainers.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues. When you create a bug report, include as many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples**
- **Describe the behavior you observed and expected**
- **Include screenshots or animated GIFs if helpful**
- **Include your environment details** (OS, PHP version, Tuti CLI version)

Use the [Bug Report Template](.github/ISSUE_TEMPLATE/bug_report.md).

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion:

- **Use a clear and descriptive title**
- **Provide a step-by-step description of the suggested enhancement**
- **Provide specific examples to demonstrate the steps**
- **Describe the current behavior and expected behavior**
- **Explain why this enhancement would be useful**

Use the [Feature Request Template](.github/ISSUE_TEMPLATE/feature_request.md).

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Make your changes** following our coding standards
3. **Add tests** for your changes
4. **Ensure the test suite passes**
5. **Update documentation** if needed
6. **Submit a pull request**

## Development Setup

### Prerequisites

- PHP 8.4+
- Composer
- Docker & Docker Compose
- Make (optional)

### Getting Started

```bash
# Clone your fork
git clone https://github.com/your-username/tuti-cli.git
cd tuti-cli

# Install dependencies
composer install

# Run tests
composer test

# Run specific test suites
composer test:unit     # Pest tests
composer test:types    # PHPStan
composer test:lint     # Pint (dry-run)
composer test:refactor # Rector (dry-run)
```

### Using Docker

```bash
# Start development environment
docker compose up -d

# Run tests in container
docker compose exec -T app composer test

# Run specific tests
docker compose exec -T app ./vendor/bin/pest --filter "test name"
```

## Coding Standards

### PHP Standards

We follow strict PHP coding standards:

- **`declare(strict_types=1)`** in every file
- **All classes `final`** — prefer composition over inheritance
- **Services `final readonly`** — immutable service objects
- **Constructor injection only** — no property injection, no setters
- **Explicit return types and type hints everywhere**
- **PSR-12 formatting** via Laravel Pint
- **No PHPDoc for type-hinted code**
- **Return early, avoid else/elseif**

### Class Patterns

```php
<?php

declare(strict_types=1);

namespace App\Services\Domain;

final readonly class MyService
{
    public function __construct(
        private SomeInterface $dependency,
    ) {}

    public function doSomething(): Result
    {
        // Implementation
    }
}
```

### Command Pattern

All commands use the `HasBrandedOutput` trait:

```php
<?php

declare(strict_types=1);

namespace App\Commands\Category;

use App\Concerns\HasBrandedOutput;
use LaravelZero\Framework\Commands\Command;

final class MyCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'category:action {argument?} {--option=default}';
    protected $description = 'What it does';

    public function handle(): int
    {
        $this->brandedHeader('Feature Name');

        // Return Command::SUCCESS or Command::FAILURE
        return Command::SUCCESS;
    }
}
```

### Security

**ALWAYS use array syntax for `Process::run()` — NEVER string interpolation:**

```php
// ✅ Safe
Process::run(['docker', 'info']);

// ❌ Unsafe - shell injection risk
Process::run("docker info {$arg}");
```

## Testing

### Test Structure

- Tests located in `tests/Feature/Console/`
- One test file per command: `{Category}{Command}Test.php`
- Use `describe()` blocks for organization

### Writing Tests

```php
<?php

declare(strict_types=1);

use Tests\TestCase;
use App\Services\Domain\MyService;

beforeEach(function () {
    $this->service = app(MyService::class);
});

describe('MyService', function () {
    it('does something correctly', function () {
        // Arrange
        $input = 'test';

        // Act
        $result = $this->service->doSomething($input);

        // Assert
        expect($result)->toBeTrue();
    });
});
```

### Running Tests

```bash
composer test              # All: rector + pint + phpstan + pest
composer test:unit         # Pest tests only (parallel)
composer test:types        # PHPStan static analysis
composer test:lint         # Pint format check (dry-run)
composer test:refactor     # Rector check (dry-run)
composer test:coverage     # Pest with coverage
```

## Commit Messages

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>

[optional body]

[optional footer(s)]
```

### Types

- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation changes
- `style` - Code style (formatting)
- `refactor` - Code refactoring
- `test` - Adding/updating tests
- `chore` - Maintenance tasks
- `perf` - Performance improvements
- `ci` - CI/CD changes

### Examples

```
feat(local): add port conflict detection
fix(docker): resolve container naming issue
docs(readme): update installation instructions
test(local): add tests for start command
```

## Branch Strategy

- `main` - Stable releases only
- `develop` - Active development (if used)
- `feature/*` - New features (e.g., `feature/redis-support`)
- `fix/*` - Bug fixes (e.g., `fix/port-conflict`)

## Pull Request Checklist

Before submitting your PR, ensure:

- [ ] Code follows PSR-12 standards
- [ ] All tests pass (`composer test`)
- [ ] New code has appropriate test coverage
- [ ] Documentation is updated if needed
- [ ] Commit messages follow Conventional Commits
- [ ] PR title follows Conventional Commits

## Recognition

Contributors are recognized in our release notes. Thank you for making Tuti CLI better!

---

Questions? Feel free to open a [Discussion](https://github.com/tuti-cli/cli/discussions) or ask in your PR.
