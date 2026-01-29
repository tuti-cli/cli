---
name: docker-compose-generation
description: Generate docker-compose files using service stubs and stack templates.
---

# Docker Compose Generation

## When to Use
Generating or modifying docker-compose files for Tuti CLI stacks.

## Key Components

**StackComposeBuilderService**: Merges stack templates with service stubs
**StackStubLoaderService**: Loads service stubs
**Service Stubs**: YAML fragments for services

## Service Stub Format

```yaml
services:
  service_name:
    image: image:tag
    container_name: ${PROJECT_NAME:-default}-service
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

networks:
  app_network:
    driver: bridge
```

Merging Strategy

1. Load base compose from stack
2. Merge service stubs by service name
3. Apply environment overrides
4. Deep merge arrays

## Environment Variables

Use `${VAR_NAME:default}` syntax:

``` yaml
environment:
  DB_HOST: ${DB_HOST:-postgres}
  DB_PORT: ${DB_PORT:-5432}
  DB_DATABASE: ${DB_DATABASE:-laravel}
```

Best Practices

- Include health checks
- Use named volumes
- Define networks explicitly
- Provide sensible defaults
- Use semantic naming
