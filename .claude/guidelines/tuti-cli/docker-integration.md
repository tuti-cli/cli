# Docker Integration

## Overview

Tuti CLI generates and manages Docker environments for local development.

## Key Services

| Service | Purpose |
|---------|---------|
| `DockerComposeService` | Execute docker-compose commands |
| `StackComposeBuilderService` | Generate docker-compose.yml |
| `StackEnvGeneratorService` | Generate .env files |

## Docker Compose Service

```php
final class DockerComposeService
{
    public function start(string $composeFile): bool;
    public function stop(string $composeFile): bool;
    public function restart(string $composeFile): bool;
    public function logs(string $composeFile, ?string $service = null): string;
    public function status(string $composeFile): array;
}
```

## Generation Flow

1. Load base `docker-compose.yml` from stack
2. Merge selected service stubs
3. Apply environment overrides
4. Generate final file to `.tuti/docker-compose.yml`

## Environment Variables

Template (`environments/.env.dev.example`):
```env
APP_ENV=local
DB_HOST=${DB_HOST:-postgres}
DB_PORT=${DB_PORT:-5432}
```

Generated to project `.env` with interpolated values.

## Service Stub Format

```yaml
services:
  postgres:
    image: postgres:17-alpine
    container_name: ${PROJECT_NAME:-app}-postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE:-laravel}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  postgres_data:
```

## Best Practices

- Always include health checks
- Use named volumes for persistence
- Use `${VAR:-default}` syntax
- Store Docker config in `.tuti/`
- Document ports in compose comments
