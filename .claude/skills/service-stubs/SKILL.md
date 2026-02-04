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
└── mail/
    └── mailpit.stub
```

## Add New Service

### 1. Create Stub File

`stubs/services/cache/memcached.stub`:
```yaml
services:
  memcached:
    image: memcached:alpine
    container_name: ${PROJECT_NAME:-app}-memcached
    restart: unless-stopped
    networks:
      - app_network
    healthcheck:
      test: ["CMD", "echo", "stats"]
      interval: 10s
      timeout: 5s
      retries: 5
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
                "default_variables": {
                    "MEMCACHED_HOST": "memcached"
                }
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
| Container | `${PROJECT_NAME}-service` | `myapp-redis` |

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
    "health_check": {}
}
```

## Best Practices

- Include health checks
- Use environment variables with defaults
- Use named volumes for data
- Keep services minimal and focused
- Test with multiple stacks
