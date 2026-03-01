# tuti-cli Project Context

## Overview
Multi-framework Docker environment management CLI tool. Builds to self-contained PHAR/native binary.

## Stack
- **Language:** PHP 8.4+
- **Framework:** Laravel Zero 12.x
- **Testing:** Pest (parallel)
- **Static Analysis:** PHPStan (level 5+)
- **Formatting:** Laravel Pint (PSR-12)
- **Refactoring:** Rector
- **Container:** Docker Compose v2
- **Build:** Phpacker (PHAR/binary)

## Architecture
- **Commands:** Grouped by domain (Infrastructure, Local, Stack, Test)
- **Services:** Domain-organized (Context, Docker, Project, Stack, Storage)
- **Stubs:** Template files for stacks and infrastructure
- **Contracts:** Interfaces for core abstractions

## Key Directories
```
app/
├── Commands/         # CLI commands by domain
├── Concerns/         # Traits (HasBrandedOutput, etc.)
├── Contracts/        # Interfaces
├── Domain/           # Value objects
├── Enums/            # PHP enums
├── Infrastructure/   # Implementations
├── Providers/        # Service providers
├── Services/         # Business logic by domain
└── Support/          # Helper functions

stubs/
├── stacks/           # Stack templates (laravel, wordpress)
└── infrastructure/   # Global infrastructure (traefik)
```

## Testing Commands
```bash
composer test              # Full suite: rector + pint + phpstan + pest
composer test:unit         # Pest tests only (parallel)
composer test:types        # PHPStan static analysis
composer test:lint         # Pint format check
composer test:coverage     # Pest with coverage
```

## Build Commands
```bash
make build-phar            # Build PHAR
make build-binary          # All platform binaries
```

## Quality Standards
- `declare(strict_types=1)` everywhere
- All classes `final`
- Services `final readonly`
- Constructor injection only
- Explicit return types
- PSR-12 formatting

## Current Workflow Status
- **Branch:** workflow/v5-migration
- **Phase:** 0 - Backup (pending)
- **Last Updated:** 2026-02-27
