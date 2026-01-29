---
name: service-stubs
description: Work with service stubs for docker-compose generation.
---

# Service Stubs

## When to Use
Creating, modifying, or registering service stubs.

## Location
`stubs/services/{category}/{service}.stub`

## Registry
`stubs/services/registry.json`

## Adding a Service

### 1. Create Stub File
```bash
touch stubs/services/cache/memcached.stub
```

### 2. Add Content
```yaml
services:
  memcached:
    image: memcached:alpine
    container_name: ${PROJECT_NAME:-laravel}-memcached
    restart: unless-stopped
    networks:
      - app_network
```

### 3. Add to Registry
``` json
{
    "services": {
        "cache.memcached": {
            "name": "Memcached",
            "category": "cache",
            "description": "Memcached caching service",
            "stub": "cache/memcached.stub",
            "env_vars": {
                "MEMCACHED_HOST": "memcached",
                "MEMCACHED_PORT": "11211"
            }
        }
    }
}
```

Naming Conventions

- ID: `{category}.{service}` (e.g., `cache.redis`)
- File: `{category}/{service}.stub` (e.g., `cache/redis.stub`)
- Container: `${PROJECT_NAME:-default}-{service}`

## Best Practices

- Use environment variables
- Include health checks
- Use named volumes for data
- Define networks
- Keep services minimal
