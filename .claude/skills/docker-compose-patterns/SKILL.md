---
name: docker-compose-patterns
description: Docker Compose patterns for Tuti CLI stacks. Use when creating or modifying docker-compose.yml, service stubs, Dockerfiles, or any Docker-related configuration. Includes YAML anchors, healthchecks, volume naming, environment variable patterns, and service stub format.
---

# Docker Compose Patterns

Patterns for creating Docker Compose configurations in Tuti CLI stacks.

## Quick Reference

### File Structure
```
stubs/stacks/{stack}/
├── docker-compose.yml       # Base configuration
├── docker-compose.dev.yml   # Development overlay
├── docker/Dockerfile        # Application container
└── services/                # Service stubs
    ├── registry.json
    ├── databases/postgres.stub
    └── cache/redis.stub
```

### Naming Conventions
```yaml
# Container: ${PROJECT_NAME}_${APP_ENV}_{service}
# Network: ${PROJECT_NAME}_${APP_ENV}_network
# Volume: ${PROJECT_NAME}_${APP_ENV}_{volume}_data
```

## YAML Anchors Pattern

### Base Anchors (docker-compose.yml)
```yaml
x-app-env-base: &app-env-base
  PROJECT_NAME: ${PROJECT_NAME:-project}
  APP_ENV: ${APP_ENV:-development}

x-app-build-base: &app-build-base
  context: .
  dockerfile: docker/Dockerfile
  args:
    PHP_VERSION: ${PHP_VERSION:-8.4}

x-common-service: &common-service
  restart: unless-stopped
  networks:
    - app-network
```

### Using Anchors
```yaml
services:
  app:
    <<: *common-service
    build:
      <<: *app-build-base
    environment:
      <<: *app-env-base
      DB_HOST: postgres
      DB_PORT: 5432
```

## Service Stub Format

Stubs use section markers to split into different files:

```yaml
# @section: base     → goes into docker-compose.yml
# @section: dev      → goes into docker-compose.dev.yml
# @section: volumes  → volume definitions
# @section: env      → variables added to .env

# @section: base
postgres:
  image: postgres:16-alpine
  container_name: ${PROJECT_NAME}_${APP_ENV}_postgres
  environment:
    POSTGRES_DB: ${DB_DATABASE}
    POSTGRES_USER: ${DB_USERNAME}
    POSTGRES_PASSWORD: ${DB_PASSWORD}
  volumes:
    - postgres_data:/var/lib/postgresql/data
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME}"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
postgres:
  ports:
    - "${POSTGRES_PORT:-5432}:5432"

# @section: volumes
postgres_data:
  name: ${PROJECT_NAME}_${APP_ENV}_postgres_data
  driver: local

# @section: env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=tuti
DB_USERNAME=tuti
DB_PASSWORD=secret
POSTGRES_PORT=5432
```

## Healthcheck Patterns

### PostgreSQL
```yaml
healthcheck:
  test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME}"]
  interval: 5s
  timeout: 5s
  retries: 5
```

### MySQL/MariaDB
```yaml
healthcheck:
  test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p${DB_PASSWORD}"]
  interval: 5s
  timeout: 5s
  retries: 5
```

### Redis
```yaml
healthcheck:
  test: ["CMD", "redis-cli", "ping"]
  interval: 5s
  timeout: 5s
  retries: 5
```

### Meilisearch
```yaml
healthcheck:
  test: ["CMD", "wget", "--no-verbose", "--spider", "http://localhost:7700/health"]
  interval: 5s
  timeout: 5s
  retries: 5
```

### Mailpit
```yaml
healthcheck:
  test: ["CMD", "wget", "--no-verbose", "--spider", "http://localhost:8025/live"]
  interval: 5s
  timeout: 5s
  retries: 5
```

### MinIO
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
  interval: 5s
  timeout: 5s
  retries: 5
```

## Environment Variables

### Always Use Defaults
```yaml
environment:
  PROJECT_NAME: ${PROJECT_NAME:-project}
  APP_ENV: ${APP_ENV:-development}
  DB_PORT: ${DB_PORT:-5432}
```

### Build-Time Variables (Dockerfile)
```dockerfile
# Use {{VAR}} syntax for stub replacements
ARG PHP_VERSION={{PHP_VERSION}}
FROM php:${PHP_VERSION}-fpm
```

## Volume Patterns

### Named Volumes
```yaml
volumes:
  postgres_data:
    name: ${PROJECT_NAME}_${APP_ENV}_postgres_data
    driver: local
```

### Bind Mounts (Development Only)
```yaml
# In docker-compose.dev.yml only
volumes:
  - ./:/var/www/html
```

## Network Patterns

```yaml
networks:
  app-network:
    name: ${PROJECT_NAME}_${APP_ENV}_network
    driver: bridge
```

## Dockerfile Pattern

```dockerfile
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    postgresql-dev \
    mysql-client

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pdo_mysql \
    gd \
    zip \
    bcmath

# Install Redis extension
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

# Configure non-root user
RUN addgroup -g 1000 tuti && \
    adduser -u 1000 -G tuti -s /bin/sh -D tuti

USER tuti

WORKDIR /var/www/html

HEALTHCHECK --interval=30s --timeout=3s \
  CMD php-fpm-healthcheck || exit 1
```

## Service Registry

`services/registry.json` structure:

```json
{
  "databases": {
    "postgres": {
      "file": "databases/postgres.stub",
      "name": "PostgreSQL",
      "description": "PostgreSQL database server",
      "default": true
    },
    "mysql": {
      "file": "databases/mysql.stub",
      "name": "MySQL",
      "description": "MySQL database server",
      "default": false
    },
    "mariadb": {
      "file": "databases/mariadb.stub",
      "name": "MariaDB",
      "description": "MariaDB database server",
      "default": false
    }
  },
  "cache": {
    "redis": {
      "file": "cache/redis.stub",
      "name": "Redis",
      "description": "Redis cache and session server"
    }
  },
  "search": {
    "meilisearch": {
      "file": "search/meilisearch.stub",
      "name": "Meilisearch",
      "description": "Meilisearch search engine"
    },
    "typesense": {
      "file": "search/typesense.stub",
      "name": "Typesense",
      "description": "Typesense search engine"
    }
  },
  "mail": {
    "mailpit": {
      "file": "mail/mailpit.stub",
      "name": "Mailpit",
      "description": "Mailpit SMTP testing server"
    }
  },
  "storage": {
    "minio": {
      "file": "storage/minio.stub",
      "name": "MinIO",
      "description": "MinIO S3-compatible storage"
    }
  }
}
```

## Complete Service Stub Examples

### PostgreSQL Stub
```yaml
# @section: base
postgres:
  image: postgres:16-alpine
  container_name: ${PROJECT_NAME}_${APP_ENV}_postgres
  environment:
    POSTGRES_DB: ${DB_DATABASE:-tuti}
    POSTGRES_USER: ${DB_USERNAME:-tuti}
    POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
  volumes:
    - postgres_data:/var/lib/postgresql/data
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-tuti}"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
postgres:
  ports:
    - "${POSTGRES_PORT:-5432}:5432"

# @section: volumes
postgres_data:
  name: ${PROJECT_NAME}_${APP_ENV}_postgres_data
  driver: local

# @section: env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=tuti
DB_USERNAME=tuti
DB_PASSWORD=secret
POSTGRES_PORT=5432
```

### Redis Stub
```yaml
# @section: base
redis:
  image: redis:7-alpine
  container_name: ${PROJECT_NAME}_${APP_ENV}_redis
  command: redis-server --appendonly yes
  volumes:
    - redis_data:/data
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
redis:
  ports:
    - "${REDIS_PORT:-6379}:6379"

# @section: volumes
redis_data:
  name: ${PROJECT_NAME}_${APP_ENV}_redis_data
  driver: local

# @section: env
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
```

### Mailpit Stub
```yaml
# @section: base
mailpit:
  image: axllent/mailpit:latest
  container_name: ${PROJECT_NAME}_${APP_ENV}_mailpit
  environment:
    MP_SMTP_BIND_ADDR: 0.0.0.0:1025
    MP_UI_BIND_ADDR: 0.0.0.0:8025
  volumes:
    - mailpit_data:/data
  healthcheck:
    test: ["CMD", "wget", "--no-verbose", "--spider", "http://localhost:8025/live"]
    interval: 5s
    timeout: 5s
    retries: 5
  networks:
    - app-network

# @section: dev
mailpit:
  ports:
    - "${MAILPIT_SMTP_PORT:-1025}:1025"
    - "${MAILPIT_WEB_PORT:-8025}:8025"

# @section: volumes
mailpit_data:
  name: ${PROJECT_NAME}_${APP_ENV}_mailpit_data
  driver: local

# @section: env
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAILPIT_SMTP_PORT=1025
MAILPIT_WEB_PORT=8025
```

## Common Mistakes

| Wrong | Correct |
|-------|---------|
| `container_name: app` | `container_name: ${PROJECT_NAME}_${APP_ENV}_app` |
| No healthcheck | Always include healthcheck |
| `postgres_data:` | `postgres_data:\n  name: ${PROJECT_NAME}_${APP_ENV}_postgres_data` |
| Hardcoded ports | `${PORT:-5432}:5432` |
| Missing defaults | `${VAR:-default}` |

## Service Dependencies

Use `depends_on` with healthcheck conditions:

```yaml
services:
  app:
    depends_on:
      database:
        condition: service_healthy
      redis:
        condition: service_healthy
```
