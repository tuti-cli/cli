# Tuti CLI Architecture

## Overview

**Tuti CLI** - Docker-based environment management tool for web development projects.

| Property | Value |
|----------|-------|
| Framework | Laravel Zero 12.x |
| PHP | 8.4 |
| Output | Self-contained binary (phpacker) |
| Purpose | Multi-framework local Docker environments |
| Supported Stacks | Laravel, WordPress, Next.js, Django (future) |

## Directory Structure

```
app/
├── Commands/           # Console commands
│   ├── Local/         # Environment: start, stop, logs
│   ├── Stack/         # Stack: laravel, wordpress, nextjs, etc.
│   └── Test/          # Testing commands
├── Contracts/         # Interfaces
├── Domain/            # Domain models, value objects
├── Infrastructure/    # External integrations
├── Providers/         # Service providers
├── Services/          # Business logic
│   ├── Context/       # Context management
│   ├── Docker/        # Docker integration
│   ├── Global/        # ~/.tuti/ management
│   ├── Project/       # .tuti/ management
│   ├── Stack/         # Multi-framework stack system
│   └── Storage/       # Storage operations
├── Support/           # Helpers
└── Traits/            # Shared traits

stubs/
├── stacks/            # Multi-framework stack registry
│   ├── laravel/       # Laravel stack templates
│   ├── wordpress/     # WordPress stack templates  
│   ├── nextjs/        # Next.js stack templates
│   └── registry.json  # Available stacks
└── services/          # Universal service stubs
    ├── databases/     # PostgreSQL, MySQL, etc.
    ├── cache/         # Redis, Memcached
    ├── search/        # Meilisearch, Elasticsearch
    └── mail/          # Mailpit, etc.
```

## Modes

### Global (`~/.tuti/`)
- `settings.json` - User settings
- `projects.json` - Registered projects
- `stacks/` - Downloaded stack templates
- `logs/` - CLI logs
- `cache/` - Cached data

### Project (`.tuti/`)
- `config.json` - Project config
- `docker-compose.yml` - Generated compose file
- `.env` - Environment variables

## Key Patterns

### Service Layer
```php
final class StackInitializationService
{
    public function __construct(
        private readonly StackRepositoryService $stackRepository,
        private readonly StackComposeBuilderService $composeBuilder,
    ) {}

    public function initialize(array $options): bool
    {
        // Single responsibility, constructor injection
    }
}
```

### Contract-Based Design
```php
interface StackInstallerInterface
{
    public function getIdentifier(): string;
    public function supports(string $stackIdentifier): bool;
    public function installFresh(string $path, string $name, array $options): bool;
    public function applyToExisting(string $path, array $options): bool;
}
```

## Key Services

| Service | Responsibility |
|---------|----------------|
| `StackRepositoryService` | Download/cache multi-framework stacks |
| `StackInitializationService` | Initialize projects (any framework) |
| `StackComposeBuilderService` | Generate framework-specific docker-compose.yml |
| `StackStubLoaderService` | Load universal service stubs |
| `StackInstallerRegistry` | Manage framework-specific installers |
| `DockerComposeService` | Docker operations |

## Framework Support

### Current
- **Laravel** - Full PHP web framework
- **WordPress** - CMS with PHP/MySQL stack

### Planned
- **Next.js** - React framework with Node.js
- **Django** - Python web framework
- **Nuxt.js** - Vue.js framework
- **Rails** - Ruby web framework

Each framework has its own:
- Stack installer (`LaravelStackInstaller`, `WordPressStackInstaller`, etc.)
- Command (`stack:laravel`, `stack:wordpress`, etc.)
- Stack template repository
- Framework-specific Docker configurations
