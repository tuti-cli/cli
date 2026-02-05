---
name: docker-compose-generation
description: Generate and modify docker-compose files for Tuti CLI
globs:
  - app/Services/Stack/StackComposeBuilderService.php
  - stubs/stacks/*/docker-compose.yml
  - stubs/stacks/*/docker-compose.*.yml
  - stubs/services/**/*.stub
---

# Docker Compose Generation Skill

## When to Use
- Generating docker-compose configurations
- Modifying service stubs
- Understanding compose merging
- Working with base + overlay architecture

## Architecture (2026 Best Practices)

Docker Compose files follow **base + overlay** pattern:

```
docker-compose.yml        # Base - stable config, YAML anchors, shared services
docker-compose.dev.yml    # Development overlay
docker-compose.stage.yml  # Staging overlay  
docker-compose.prod.yml   # Production overlay
```

### YAML Anchors in Base File

```yaml
# Base environment (stable values only)
x-app-env-base: &app-env-base
  APP_NAME: ${APP_NAME:-Laravel}
  DB_HOST: postgres
  # NO APP_ENV, APP_DEBUG, etc.

# Base build configuration
x-app-build-base: &app-build-base
  context: ..
  dockerfile: .tuti/docker/Dockerfile

# Common service configuration
x-common-service: &common-service
  restart: unless-stopped
  networks:
    - app_network
```

### Using Anchors in Overlay Files

```yaml
# Development-specific environment
x-app-env-dev: &app-env-dev
  APP_ENV: local
  APP_DEBUG: "true"

services:
  app:
    environment:
      <<: *app-env-dev
```

## Key Service

`StackComposeBuilderService` - Merges stack templates with service stubs

## Service Stub Template

```yaml
services:
  service_name:
    image: image:tag
    container_name: ${PROJECT_NAME:-app}_${APP_ENV:-dev}_service
    <<: *common-service
    environment:
      VAR: ${ENV_VAR:-default}
    volumes:
      - volume_name:/path
    healthcheck:
      test: ["CMD", "command"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  volume_name:
    name: ${PROJECT_NAME:-app}_${APP_ENV:-dev}_volume_name
```

## Merge Strategy

1. Load base compose from stack template
2. Merge environment overlay (dev/stage/prod)
3. Merge each selected service stub
4. Combine volumes, networks
5. Apply environment overrides
6. Output to `.tuti/docker-compose.yml` and `.tuti/docker-compose.{env}.yml`

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
  interval: 10s
  timeout: 5s
  retries: 5

# Redis
healthcheck:
  test: ["CMD", "redis-cli", "ping"]
  interval: 10s
  timeout: 3s
  retries: 5

# MySQL
healthcheck:
  test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
  interval: 10s
  timeout: 5s
  retries: 5
```

## Best Practices

- **Base file**: Only stable, unchanging configuration
- **Overlay files**: Only environment-specific overrides
- Include health checks for all services
- Use named volumes with environment prefix
- Define explicit networks
- Use semantic container naming: `${PROJECT_NAME}_${APP_ENV}_{service}`
- Provide sensible defaults with `:-` syntax
- No port exposures in production (use Traefik)

## Related Skills

- See `environment-overlays/SKILL.md` for creating new environments
