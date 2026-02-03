# Tuti CLI AI Rules

> Quick reference for AI assistants working on Tuti CLI

## Project Info

| Property | Value |
|----------|-------|
| Framework | Laravel Zero 12.x |
| PHP | 8.4 (strict types required) |
| Purpose | Multi-framework Docker environment management |
| Output | Self-contained binary (phpacker) |
| Tests | Pest |
| Supported | Laravel, WordPress, Next.js (planned), Django (planned) |

## Code Style

```php
declare(strict_types=1);

namespace App\Services;

final class MyService
{
    public function __construct(
        private readonly Dependency $dep,
    ) {}

    public function execute(): Result
    {
        // Implementation
    }
}
```

**Always:**
- `declare(strict_types=1)` at file top
- `final` classes by default
- `private readonly` for injected dependencies
- Constructor injection only
- Return `Command::SUCCESS` or `Command::FAILURE` from commands
- Type hints on all parameters and returns

## Directory Map

| Need to work with... | Look in... |
|---------------------|------------|
| Console commands | `app/Commands/` |
| Business logic | `app/Services/` |
| Interfaces | `app/Contracts/` |
| Service providers | `app/Providers/` |
| Stack templates | `stubs/stacks/` |
| Service stubs | `stubs/services/` |
| Tests | `tests/` |
| Config | `config/` |

## Common Tasks

| Task | Files to modify |
|------|----------------|
| Add command | `app/Commands/{Category}/Command.php` |
| Add service | `app/Services/{Domain}/Service.php` + Provider |
| Add framework stack | `stubs/stacks/registry.json` + Installer + Command |
| Add service stub | `stubs/services/{category}/name.stub` + registry.json |

## Testing

```bash
composer test           # All tests
composer test:unit      # Unit only
composer test:feature   # Feature only
composer pint           # Code formatting
composer phpstan        # Static analysis
```

## Build

```bash
make build-phar         # Build PHAR first
make test-phar          # Test PHAR
make build-binary       # Build native binaries
make test-binary        # Test binary
```

## Key Interfaces

```php
// Stack installer must implement
interface StackInstallerInterface {
    public function getIdentifier(): string;
    public function supports(string $stack): bool;
    public function installFresh(...): bool;
    public function applyToExisting(...): bool;
}
```

## Full Documentation

- [INDEX.md](INDEX.md) - Full guidelines index
- [guidelines/](guidelines/) - Detailed guidelines
- [skills/](skills/) - Domain-specific knowledge
