# tuti-cli Guidelines

## Docker & Laravel Integration

### File Permissions in Development

When using Docker with mounted volumes, file permissions can be tricky. Here's how tuti-cli handles it:

#### Problem
- Laravel needs to write to `storage/` and `bootstrap/cache/` directories
- When mounting host directories as volumes, the container user (www-data) may not have write permissions
- Different host systems have different user IDs (1000 on Ubuntu, 1001 on some systems)

#### Solution
tuti-cli uses a custom entrypoint script (`docker/entrypoint-dev.sh`) that:
1. Runs as `root` before the main application starts
2. Creates all required Laravel storage directories if they don't exist
3. Sets proper ownership (`www-data:www-data`) on all storage directories
4. Sets permissions to `775` for directories
5. Then switches to the `www-data` user and starts the application

#### Implementation Details

**Dockerfile (Development Stage)**:
```dockerfile
# Copy custom entrypoint script to ServerSideUp's entrypoint.d directory
# Scripts here run before S6 services start (executed in numerical order)
COPY --chmod=755 .tuti/scripts/entrypoint-dev.sh /etc/entrypoint.d/50-laravel-permissions.sh
```

**Note:** 
- Build context is project root (`context: ..`), so paths must include `.tuti/` prefix
- Script is copied to `/etc/entrypoint.d/` using ServerSideUp's entrypoint pattern
- Prefix `50-` ensures it runs after default scripts (10-20) but before custom scripts (90+)
- Script must `exit 0` to allow S6 Overlay to continue startup

**Entrypoint Script** (`.tuti/scripts/entrypoint-dev.sh`):
- Written in `/bin/sh` (not bash) for Alpine/Debian compatibility
- Runs as root, fixes permissions, then exits
- Does NOT use `exec` - just runs and exits with `exit 0`
- S6 Overlay handles starting nginx + PHP-FPM services
- Ensures these directories exist and are writable:
  - `storage/framework/cache`
  - `storage/framework/sessions`
  - `storage/framework/views`
  - `storage/logs`
  - `storage/app/public`
  - `bootstrap/cache`

### Traefik Integration

#### Router Configuration
All Laravel projects must explicitly link routers to services:

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.docker.network=traefik_proxy"
  
  # Router configuration
  - "traefik.http.routers.${PROJECT_NAME}.rule=Host(`${APP_DOMAIN}`)"
  - "traefik.http.routers.${PROJECT_NAME}.entrypoints=websecure"
  - "traefik.http.routers.${PROJECT_NAME}.tls=true"
  - "traefik.http.routers.${PROJECT_NAME}.service=${PROJECT_NAME}"  # ← Important!
  
  # Service configuration
  - "traefik.http.services.${PROJECT_NAME}.loadbalancer.server.port=8080"
```

**Why the explicit service link is needed:**
- Traefik v3 requires explicit service references when using custom service names
- Without `traefik.http.routers.X.service=Y`, Traefik may not route traffic correctly
- This prevents 404 errors from Traefik

#### Network Requirements
- All app containers must be on the `traefik_proxy` network
- Explicitly declare networks in `docker-compose.dev.yml`:
```yaml
services:
  app:
    networks:
      - traefik_proxy  # For Traefik routing
      - app_network    # For internal services (DB, Redis)
```

### Volume Mounts

**DO NOT** use named volumes for `vendor` and `node_modules` in development:

❌ **Wrong:**
```yaml
volumes:
  - ..:/var/www/html:cached
  - vendor:/var/www/html/vendor
  - node_modules:/var/www/html/node_modules
```

✅ **Correct:**
```yaml
volumes:
  - ..:/var/www/html:cached
```

**Why:** Named volumes create empty directories that override the host's `vendor` and `node_modules`, causing Laravel to fail with "class not found" errors.

### Health Checks

For future implementation, health checks should:
1. Not modify Laravel code
2. Use a standalone PHP file in `.tuti/` or similar
3. Not depend on Laravel routing
4. Return JSON: `{"status": "ok"}`

Example health check file location: `.tuti/health.php`

### User ID Mapping

tuti-cli automatically detects the host user's UID/GID and passes them to the container:

```yaml
build:
  args:
    USER_ID: ${DOCKER_USER_ID:-1000}
    GROUP_ID: ${DOCKER_GROUP_ID:-1000}
```

The Dockerfile uses ServerSideUp's built-in function:
```dockerfile
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} && \
    docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID}
```

This ensures file permissions match between host and container.

## Debug System

### Debug Logging
- All major operations should log to the debug service
- Use structured logging with context
- Log at appropriate levels: debug, info, warning, error

### Error Handling
- Catch exceptions at the command level
- Log full stack traces to debug service
- Show user-friendly messages in the CLI
- Provide hints for common issues

## Commands

### local:rebuild

Rebuilds containers to apply any configuration changes made to:
- Dockerfile
- docker-compose.yml files
- entrypoint scripts
- Other Docker configuration

**Usage:**
```bash
# Basic rebuild (shows build logs)
tuti local:rebuild

# Rebuild without cache (for major changes)
tuti local:rebuild --no-cache

# Rebuild quietly without showing build logs
tuti local:rebuild --detach

# Rebuild quietly with shorthand flag
tuti local:rebuild -d

# Force rebuild without stopping containers first
tuti local:rebuild --force
```

**What it does:**
1. Checks infrastructure is running
2. Stops current containers (unless `--force` is used)
3. Rebuilds container images
4. Pulls latest base images
5. Starts containers with new configuration

**When to use:**
- After modifying Dockerfile
- After changing entrypoint scripts
- After updating docker-compose configurations
- When troubleshooting container issues
- After pulling updates from git that include Docker changes

**Options:**
- `--no-cache` - Build from scratch without using Docker's build cache (slower but ensures clean build)
- `--detach` or `-d` - Run build without showing logs (quieter output)
- `--force` - Rebuild without stopping containers first (not recommended for most cases)

## Best Practices

1. **Always use explicit service references in Traefik labels**
2. **Never use named volumes for vendor/node_modules in development**
3. **Always declare networks explicitly in override files**
4. **Use entrypoint scripts to fix permissions, not Dockerfile RUN commands**
5. **Keep Laravel code clean - don't add Docker-specific routes**
6. **Use environment variables for all configuration**
7. **Auto-detect user IDs instead of hardcoding**

### Common Issues

### 404 from Traefik
- **Cause:** Missing `traefik.http.routers.X.service=Y` label
- **Fix:** Add explicit service reference to router labels

### Permission Denied Errors
- **Cause:** Container user doesn't have write access to storage
- **Fix:** Entrypoint script automatically fixes this on container start

### Class Not Found Errors
- **Cause:** Named volumes overriding host vendor directory
- **Fix:** Remove vendor/node_modules named volumes from docker-compose.dev.yml

### Redis Authentication Error (NOAUTH)
- **Cause:** `REDIS_PASSWORD=null` is treated as the string "null", not empty
- **Fix:** Use `REDIS_PASSWORD=` (empty) for no authentication in development
- **Note:** The syntax `${REDIS_PASSWORD:-null}` defaults to STRING "null", use `${REDIS_PASSWORD:-}` for empty default

**Correct configuration for development (no password):**
```dotenv
# .env file
REDIS_PASSWORD=
```

```yaml
# docker-compose.yml
environment:
  REDIS_PASSWORD: ${REDIS_PASSWORD:-}  # Empty default, not "null"
```
