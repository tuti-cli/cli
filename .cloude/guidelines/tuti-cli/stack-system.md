# Stack System

## Overview

Stacks provide pre-configured Docker setups for different web development frameworks and technologies.

**Supported Frameworks:**
- Laravel (PHP)
- WordPress (PHP/CMS) - planned
- Next.js (React/Node.js) - planned
- Django (Python) - planned

## Stack Sources

| Source | Location | Priority |
|--------|----------|----------|
| Remote | Downloaded to `~/.tuti/stacks/` | Default |
| Local | `stacks/` in repo (dev only) | Higher |

## Stack Structure

```
laravel-stack/
├── stack.json              # Manifest
├── docker/
│   ├── Dockerfile
│   └── nginx.conf
├── environments/
│   ├── .env.dev.example
│   └── .env.prod.example
├── docker-compose.yml      # Base
├── docker-compose.dev.yml  # Dev overrides
└── docker-compose.prod.yml # Prod overrides
```

## Registry

`stubs/stacks/registry.json`:
```json
{
    "stacks": {
        "laravel": {
            "name": "Laravel Stack",
            "repository": "https://github.com/tuti-cli/laravel-stack.git",
            "framework": "laravel",
            "type": "php"
        },
        "wordpress": {
            "name": "WordPress Stack", 
            "repository": "https://github.com/tuti-cli/wordpress-stack.git",
            "framework": "wordpress",
            "type": "php"
        },
        "nextjs": {
            "name": "Next.js Stack",
            "repository": "https://github.com/tuti-cli/nextjs-stack.git", 
            "framework": "nextjs",
            "type": "nodejs"
        }
    }
}
```

## Installer Interface

```php
interface StackInstallerInterface
{
    public function getIdentifier(): string;
    public function getName(): string;
    public function supports(string $stack): bool;
    public function detectExistingProject(string $path): bool;
    public function installFresh(string $path, string $name, array $options): bool;
    public function applyToExisting(string $path, array $options): bool;
    public function getAvailableModes(): array;
}
```

## Key Services

| Service | Purpose |
|---------|---------|
| `StackRepositoryService` | Download/update stacks |
| `StackInitializationService` | Initialize projects |
| `StackComposeBuilderService` | Generate docker-compose |
| `StackStubLoaderService` | Load service stubs |
| `StackInstallerRegistry` | Manage installers |

## Adding New Stack

1. Create stack repository
2. Add to `stubs/stacks/registry.json`
3. Create installer in `app/Services/Stack/Installers/`
4. Register in `StackServiceProvider`
5. Create command in `app/Commands/Stack/`

## Service Stubs

Located in `stubs/services/`:
- `databases/` - PostgreSQL, MySQL, MariaDB
- `cache/` - Redis, Memcached
- `search/` - Meilisearch, Typesense, Elasticsearch
- `storage/` - MinIO, S3-compatible
- `mail/` - Mailpit, MailHog
- `monitoring/` - Grafana, Prometheus

Service stubs are universal templates that work across all frameworks (Laravel, WordPress, Next.js, etc.).
