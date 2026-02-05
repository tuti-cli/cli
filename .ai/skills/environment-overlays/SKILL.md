---
name: environment-overlays
description: Create and manage Docker Compose environment overlays (dev, staging, production)
globs:
  - stubs/stacks/*/docker-compose.yml
  - stubs/stacks/*/docker-compose.*.yml
  - stubs/stacks/*/environments/*.env.*.example
---

# Environment Overlays Skill

## When to Use
- Adding new environment overlays (staging, production)
- Creating environment-specific .env files
- Modifying environment-specific Docker Compose configurations

## Architecture Overview

The Docker Compose architecture follows the **base + overlay** pattern:

```
stubs/stacks/{stack}/
├── docker-compose.yml           # Base (stable config, YAML anchors)
├── docker-compose.dev.yml       # Development overlay
├── docker-compose.stage.yml     # Staging overlay
├── docker-compose.prod.yml      # Production overlay
└── environments/
    ├── .env.dev.example         # Development environment template
    ├── .env.stage.example       # Staging environment template
    └── .env.prod.example        # Production environment template
```

## Base File Principles (`docker-compose.yml`)

The base file should contain ONLY:

1. **YAML Anchors** - Reusable configuration blocks
2. **Stable Service Definitions** - Services that exist in all environments
3. **Shared Networks & Volumes** - Base network/volume definitions
4. **NO environment-specific values** - No APP_ENV, APP_DEBUG, ports, etc.

### YAML Anchor Naming Convention

```yaml
# Environment variables - Base (shared across all environments)
x-app-env-base: &app-env-base
  # Only STABLE values that don't change per environment

# Build configuration - Base
x-app-build-base: &app-build-base
  context: ..
  dockerfile: .tuti/docker/Dockerfile

# Common service configuration
x-common-service: &common-service
  restart: unless-stopped
  networks:
    - app_network
```

## Environment Overlay Principles

Each overlay file should contain ONLY:

1. **Environment-specific YAML anchors** (e.g., `x-app-env-dev`)
2. **Overrides for base services** (build targets, volumes, labels)
3. **Environment-only services** (mailpit for dev, monitoring for prod)
4. **Port exposures** (only expose ports needed for that environment)

### Overlay Anchor Naming Convention

```yaml
# Development
x-app-env-dev: &app-env-dev
  APP_ENV: local
  APP_DEBUG: "true"
  ...

# Staging
x-app-env-stage: &app-env-stage
  APP_ENV: staging
  APP_DEBUG: "false"
  ...

# Production
x-app-env-prod: &app-env-prod
  APP_ENV: production
  APP_DEBUG: "false"
  ...
```

## Creating a New Environment Overlay

### Step 1: Create the Docker Compose overlay file

```yaml
# =============================================================================
# Laravel Stack - {Environment} Environment Override
# =============================================================================
# Usage: docker compose -f docker-compose.yml -f docker-compose.{env}.yml up
# =============================================================================

x-app-env-{env}: &app-env-{env}
  APP_ENV: {environment_value}
  APP_DEBUG: "{debug_value}"
  APP_URL: https://${APP_DOMAIN:-app.{env}.example.com}
  # ... environment-specific overrides

services:
  app:
    build:
      target: {build_target}
    container_name: ${PROJECT_NAME:-laravel}_${APP_ENV:-{env}}_app
    environment:
      <<: *app-env-{env}
    # ... environment-specific configuration
```

### Step 2: Create the .env template

Create `environments/.env.{env}.example` with:

```dotenv
# ============================================================================
# 🐳 TUTI-CLI {ENVIRONMENT} ENVIRONMENT
# ============================================================================

# Project Configuration
PROJECT_NAME=laravel
APP_ENV={env}
APP_DOMAIN=app.{env}.example.com

# Docker Build Configuration
BUILD_TARGET={build_target}

# Service Versions
POSTGRES_VERSION=17
REDIS_VERSION=7

# ... environment-specific variables
```

### Step 3: Update stack.json (if needed)

Ensure the environment is defined in `stack.json`:

```json
{
  "environments": {
    "{env}": {
      "app_replicas": 2,
      "features": {
        "hot_reload": false,
        "debug": false,
        "ssl": true
      }
    }
  }
}
```

## Environment Comparison Matrix

| Setting | Development | Staging | Production |
|---------|-------------|---------|------------|
| `APP_ENV` | local | staging | production |
| `APP_DEBUG` | true | false | false |
| `build.target` | development | production | production |
| `PHP_OPCACHE_ENABLE` | 0 | 1 | 1 |
| `LOG_LEVEL` | debug | warning | error |
| `TELESCOPE_ENABLED` | true | false | false |
| `AUTORUN_ENABLED` | false | true | true |
| `volumes` | mounted | none | none |
| External ports | exposed | limited | none |
| Mailpit | yes | no | no |
| SSL | Traefik dev cert | real cert | real cert |

## Commands to Use

```bash
# Development
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Staging
docker compose -f docker-compose.yml -f docker-compose.stage.yml up -d

# Production
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

## Best Practices (2026)

1. **Never duplicate** - If a value is in base, don't repeat it in overlay
2. **Environment variables for secrets** - Use `${VAR}` syntax, never hardcode
3. **Health checks always** - All services must have health checks
4. **Named volumes** - Use environment-specific volume names
5. **Resource limits** - Production should have CPU/memory limits
6. **Logging drivers** - Configure appropriate log drivers per environment
7. **Deploy configuration** - Production should use `deploy:` for replicas/resources

## Staging Template Example

```yaml
# =============================================================================
# Laravel Stack - Staging Environment Override
# =============================================================================

x-app-env-stage: &app-env-stage
  APP_ENV: staging
  APP_DEBUG: "false"
  APP_URL: https://${APP_DOMAIN:-app.staging.example.com}
  LOG_LEVEL: warning
  TELESCOPE_ENABLED: "false"
  PHP_OPCACHE_ENABLE: "1"
  AUTORUN_ENABLED: "true"

services:
  app:
    build:
      target: production
    container_name: ${PROJECT_NAME:-laravel}_staging_app
    environment:
      <<: *app-env-stage
    deploy:
      replicas: 2
      resources:
        limits:
          cpus: '1'
          memory: 1G
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.${PROJECT_NAME:-laravel}.rule=Host(`${APP_DOMAIN:-app.staging.example.com}`)"
      - "traefik.http.routers.${PROJECT_NAME:-laravel}.entrypoints=websecure"
      - "traefik.http.routers.${PROJECT_NAME:-laravel}.tls=true"
      - "traefik.http.routers.${PROJECT_NAME:-laravel}.tls.certresolver=letsencrypt"

  postgres:
    container_name: ${PROJECT_NAME:-laravel}_staging_postgres
    # No external ports in staging

  redis:
    container_name: ${PROJECT_NAME:-laravel}_staging_redis
    # No external ports in staging
```

## Production Template Example

```yaml
# =============================================================================
# Laravel Stack - Production Environment Override
# =============================================================================

x-app-env-prod: &app-env-prod
  APP_ENV: production
  APP_DEBUG: "false"
  APP_URL: https://${APP_DOMAIN:-app.example.com}
  LOG_LEVEL: error
  TELESCOPE_ENABLED: "false"
  PHP_OPCACHE_ENABLE: "1"
  PHP_OPCACHE_VALIDATE_TIMESTAMPS: "0"
  AUTORUN_ENABLED: "true"
  AUTORUN_LARAVEL_STORAGE_LINK: "true"

services:
  app:
    build:
      target: production
    container_name: ${PROJECT_NAME:-laravel}_production_app
    environment:
      <<: *app-env-prod
    deploy:
      replicas: 3
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '0.5'
          memory: 512M
      update_config:
        parallelism: 1
        delay: 10s
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.${PROJECT_NAME:-laravel}.rule=Host(`${APP_DOMAIN:-app.example.com}`)"
      - "traefik.http.routers.${PROJECT_NAME:-laravel}.entrypoints=websecure"
      - "traefik.http.routers.${PROJECT_NAME:-laravel}.tls=true"
      - "traefik.http.routers.${PROJECT_NAME:-laravel}.tls.certresolver=letsencrypt"
      - "traefik.http.routers.${PROJECT_NAME:-laravel}.middlewares=secure-headers@file"

  postgres:
    container_name: ${PROJECT_NAME:-laravel}_production_postgres
    deploy:
      resources:
        limits:
          cpus: '1'
          memory: 1G

  redis:
    container_name: ${PROJECT_NAME:-laravel}_production_redis
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 512M
```
