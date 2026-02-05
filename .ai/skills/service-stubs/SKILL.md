---
name: service-stubs
description: Create and manage service stubs for docker-compose
globs:
  - stubs/services/**
---

# Service Stubs Skill

## When to Use
- Adding new service stubs
- Modifying existing service configurations

## Structure

```
stubs/services/
├── registry.json
├── databases/
│   ├── postgres.stub
│   ├── mysql.stub
│   └── mariadb.stub
├── cache/
│   └── redis.stub
├── search/
│   ├── meilisearch.stub
│   └── typesense.stub
├── storage/
│   └── minio.stub
├── mail/
│   └── mailpit.stub
└── workers/
    ├── horizon.stub
    └── scheduler.stub
```

## Section-Based Stub Format (2026)

All stubs now use a unified section-based format:

```yaml
# @section: base
# Base service configuration (used in docker-compose.yml)
servicename:
  image: image:${VERSION:-tag}
  container_name: {{PROJECT_NAME}}_${APP_ENV:-dev}_servicename
  environment:
    VAR: ${VAR:-default}
  volumes:
    - servicename_data:/data
  networks:
    - {{NETWORK_NAME}}
  healthcheck:
    test: ["CMD", "healthcheck-command"]
    interval: 10s
    timeout: 5s
    retries: 5
  restart: unless-stopped

# @section: dev
# Development overrides (port exposures, etc.)
servicename:
  container_name: {{PROJECT_NAME}}_${APP_ENV:-dev}_servicename
  ports:
    - "${EXTERNAL_PORT:-1234}:1234"

# @section: volumes
# Volume definitions
servicename_data:
  name: {{PROJECT_NAME}}_${APP_ENV:-dev}_servicename_data

# @section: env
# Environment variables to add to .env
VAR_HOST=servicename
VAR_PORT=1234
VAR_VERSION=latest
```

## Sections Explained

| Section | Purpose | Used In |
|---------|---------|---------|
| `base` | Core service definition | docker-compose.yml |
| `dev` | Development overrides (ports, debug) | docker-compose.dev.yml |
| `prod` | Production overrides (resources, replicas) | docker-compose.prod.yml |
| `volumes` | Volume definitions | docker-compose.yml volumes section |
| `env` | Environment variables | .env file |

## Placeholder Syntax

| Syntax | Description | Example |
|--------|-------------|---------|
| `{{VAR}}` | Replaced at build time | `{{PROJECT_NAME}}` → `myapp` |
| `${VAR:-default}` | Docker Compose variable | `${APP_ENV:-dev}` |

## Add New Service

### 1. Create Stub File

`stubs/services/cache/memcached.stub`:
```yaml
# @section: base
memcached:
  image: memcached:${MEMCACHED_VERSION:-alpine}
  container_name: {{PROJECT_NAME}}_${APP_ENV:-dev}_memcached
  networks:
    - {{NETWORK_NAME}}
  healthcheck:
    test: ["CMD", "echo", "stats"]
    interval: 10s
    timeout: 5s
    retries: 5
  restart: unless-stopped

# @section: volumes
# No volumes needed for memcached

# @section: env
MEMCACHED_HOST=memcached
MEMCACHED_PORT=11211
MEMCACHED_VERSION=alpine
```

### 2. Register in Registry

`stubs/services/registry.json`:
```json
{
    "services": {
        "cache": {
            "memcached": {
                "name": "Memcached",
                "description": "In-memory caching",
                "stub": "cache/memcached.stub",
                "compatible_with": ["laravel", "wordpress"],
                "volumes": [],
                "ports": {
                    "internal": 11211,
                    "external": null
                },
                "default_variables": {
                    "MEMCACHED_HOST": "memcached",
                    "MEMCACHED_PORT": "11211"
                },
                "required_variables": [],
                "optional_variables": {}
            }
        }
    }
}
```

## Naming Conventions

| Type | Format | Example |
|------|--------|---------|
| Registry Key | `category.service` | `cache.redis` |
| Stub File | `category/service.stub` | `cache/redis.stub` |
| Container | `{{PROJECT_NAME}}_${APP_ENV}_service` | `myapp_dev_redis` |
| Volume | `{{PROJECT_NAME}}_${APP_ENV}_service_data` | `myapp_dev_redis_data` |

## Registry Schema

```json
{
    "name": "Service Name",
    "description": "Description",
    "stub": "path/to/file.stub",
    "compatible_with": ["laravel", "wordpress"],
    "volumes": ["volume_name"],
    "ports": { "internal": 6379, "external": null },
    "default_variables": {},
    "required_variables": [],
    "optional_variables": {}
}
```

## Best Practices

- Always include healthcheck for all services
- Use `${VAR:-default}` syntax for Docker Compose variables
- Use `{{VAR}}` syntax for build-time replacements
- Include `restart: unless-stopped` for all services
- Define volumes in the `volumes` section
- Add all required env vars in the `env` section

- Include health checks
- Use environment variables with defaults
- Use named volumes for data
- Keep services minimal and focused
- Test with multiple stacks
