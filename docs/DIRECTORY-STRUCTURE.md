# tuti-cli Stack Directory Structure

## Stack Template Structure

Located at: `stubs/stacks/laravel/`

```
stubs/stacks/laravel/
├── docker/
│   ├── Dockerfile              # Multi-stage Dockerfile (base, development, production)
│   └── .gitkeep
├── environments/
│   ├── .env.dev.example        # Development environment variables
│   ├── .env.staging.example    # Staging environment variables
│   └── .env.prod.example       # Production environment variables
├── scripts/
│   └── entrypoint-dev.sh       # Development entrypoint (fixes permissions)
├── docker-compose.yml          # Base compose configuration
├── docker-compose.dev.yml      # Development overrides
├── docker-compose.staging.yml  # Staging overrides (optional)
├── docker-compose.prod.yml     # Production overrides (optional)
└── stack.json                  # Stack metadata
```

## Project Structure After Installation

After running `tuti stack:laravel`, the `.tuti` directory is created:

```
your-laravel-project/
├── .tuti/
│   ├── docker/
│   │   ├── Dockerfile          # Copied from stack template
│   │   └── .gitkeep
│   ├── environments/
│   │   ├── .env.dev.example
│   │   ├── .env.staging.example
│   │   └── .env.prod.example
│   ├── scripts/
│   │   └── entrypoint-dev.sh   # Copied from stack template (automatically chmod +x)
│   ├── docker-compose.yml      # Base configuration
│   ├── docker-compose.dev.yml  # Development overrides
│   ├── config.json             # Project metadata
│   └── stack.json              # Stack metadata
├── .env                        # Project environment file
├── artisan
├── composer.json
└── ... (Laravel files)
```

## File Purposes

### docker/Dockerfile
Multi-stage Dockerfile with:
- **base** - Common configuration, PHP extensions, dependencies
- **development** - Dev tools, Node.js, debug settings, custom entrypoint
- **vendor** - Composer dependencies for production
- **production** - Optimized for production with caching

### scripts/entrypoint-dev.sh
Development entrypoint script that:
- Runs as root before S6 services start
- Creates required Laravel directories
- Sets proper permissions (775 for directories)
- Sets ownership (www-data:www-data)
- Exits successfully (S6 continues startup)

**Uses ServerSideUp's entrypoint.d pattern:**
- Script is copied to `/etc/entrypoint.d/50-laravel-permissions.sh`
- Runs in numerical order (50 = after defaults, before custom)
- Must be written in `/bin/sh` (not bash)
- Must `exit 0` to allow startup to continue
- S6 Overlay then starts nginx + PHP-FPM services

**Automatically made executable** by `StackFilesCopierService::makeScriptsExecutable()`

### environments/*.example
Environment variable templates for different environments:
- `.env.dev.example` - Local development settings
- `.env.staging.example` - Staging server settings
- `.env.prod.example` - Production server settings

These are merged with Laravel's `.env.example` during installation.

### docker-compose.yml
Base configuration shared across all environments:
- Service definitions (app, postgres, redis)
- Network configuration
- Volume definitions
- Base environment variables
- Health checks (for postgres, redis)

### docker-compose.dev.yml
Development-specific overrides:
- Build arguments (USER_ID, GROUP_ID)
- Volume mounts (for hot reload)
- Traefik labels (for local routing)
- Development environment variables
- Port mappings (for direct access)
- Additional services (mailpit)

## How Files are Copied

The `StackFilesCopierService` handles file copying:

1. **Directories copied** (entire contents):
   - `docker/` → `.tuti/docker/`
   - `environments/` → `.tuti/environments/`
   - `scripts/` → `.tuti/scripts/`

2. **Individual files copied**:
   - `stack.json` → `.tuti/stack.json`
   - `docker-compose.yml` → `.tuti/docker-compose.yml`
   - `docker-compose.dev.yml` → `.tuti/docker-compose.dev.yml`
   - `docker-compose.staging.yml` → `.tuti/docker-compose.staging.yml` (if exists)
   - `docker-compose.prod.yml` → `.tuti/docker-compose.prod.yml` (if exists)

3. **Post-copy actions**:
   - All `*.sh` files in `scripts/` are made executable (`chmod 755`)
   - `config.json` is generated with project metadata
   - `.env` file is created/updated with Docker-specific variables

## Build Context

When Docker builds the image, the build context is the **project root**:

```yaml
# In docker-compose.yml
services:
  app:
    build:
      context: ..              # Project root (parent of .tuti)
      dockerfile: .tuti/docker/Dockerfile
```

This means in the Dockerfile:
```dockerfile
# Paths are relative to project root
COPY .tuti/scripts/entrypoint-dev.sh /usr/local/bin/entrypoint-dev.sh
# Build context is project root, so we need .tuti/ prefix

COPY composer.json composer.lock ./
# These are at project root, no prefix needed
```

## Adding New Scripts

To add a new script to the stack:

1. **Create script** in `stubs/stacks/laravel/scripts/`:
   ```bash
   touch stubs/stacks/laravel/scripts/my-script.sh
   chmod +x stubs/stacks/laravel/scripts/my-script.sh
   ```

2. **Script is automatically**:
   - Copied to `.tuti/scripts/` during installation
   - Made executable by `makeScriptsExecutable()`

3. **Use in Dockerfile**:
   ```dockerfile
   # Copy to entrypoint.d directory (ServerSideUp pattern)
   COPY --chmod=755 .tuti/scripts/my-script.sh /etc/entrypoint.d/60-my-script.sh
   ```

   **Important:** 
   - Include `.tuti/` prefix since build context is project root
   - Copy to `/etc/entrypoint.d/` directory
   - Use numerical prefix (10-90) to control execution order
   - Script must `exit 0` to continue startup

## Directory Permissions

After copying:
- **Directories**: `0755` (rwxr-xr-x)
- **Regular files**: Preserve source permissions
- **Shell scripts** (*.sh): `0755` (rwxr-xr-x) - enforced by `makeScriptsExecutable()`

## Best Practices

1. ✅ Keep scripts in `scripts/` directory
2. ✅ Keep Dockerfile in `docker/` directory
3. ✅ Use `.example` suffix for environment templates
4. ✅ Make scripts self-contained and idempotent
5. ✅ Add comments explaining what each script does
6. ✅ Test scripts in isolation before adding to Dockerfile
7. ✅ Use `set -e` in scripts to fail fast on errors

## Troubleshooting

### Script not executable in container
**Cause**: Script wasn't made executable before copying
**Fix**: Scripts are automatically made executable by `makeScriptsExecutable()`, but ensure source file has correct permissions

### Script not found during build
**Cause**: Wrong path in COPY command
**Fix**: Use `.tuti/scripts/` prefix since build context is project root
```dockerfile
# ✅ Correct (build context is project root)
COPY --chmod=755 .tuti/scripts/entrypoint-dev.sh /usr/local/bin/

# ❌ Wrong (missing .tuti prefix)
COPY --chmod=755 scripts/entrypoint-dev.sh /usr/local/bin/
```

### Changes to scripts not applied
**Cause**: Docker is using cached layer
**Fix**: Rebuild without cache
```bash
tuti local:rebuild --no-cache
```
