---
name: docker-compose-generation
description: Generate and modify docker-compose files for Tuti CLI
globs:
  - app/Services/Stack/StackComposeBuilderService.php
  - stubs/services/**/*.stub
---

# Docker Compose Generation Skill

## When to Use
- Generating docker-compose configurations
- Modifying service stubs
- Understanding compose merging

## Key Service

`StackComposeBuilderService` - Merges stack templates with service stubs

## Service Stub Template

```yaml
services:
  service_name:
    image: image:tag
    container_name: ${PROJECT_NAME:-app}-service
    restart: unless-stopped
    environment:
      VAR: ${ENV_VAR:-default}
    volumes:
      - volume_name:/path
    networks:
      - app_network
    healthcheck:
      test: ["CMD", "command"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  volume_name:
```

## Merge Strategy

1. Load base compose from stack template
2. Merge each selected service stub
3. Combine volumes, networks
4. Apply environment overrides
5. Output to `.tuti/docker-compose.yml`

## Environment Variables

Always use default syntax:
```yaml
environment:
  DB_HOST: ${DB_HOST:-postgres}
  DB_PORT: ${DB_PORT:-5432}
```

## Health Check Examples

```yaml
# PostgreSQL
healthcheck:
  test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME}"]

# Redis
healthcheck:
  test: ["CMD", "redis-cli", "ping"]

# MySQL
healthcheck:
  test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
```

## Best Practices

- Include health checks for all services
- Use named volumes for data persistence
- Define explicit networks
- Use semantic container naming
- Provide sensible defaults
