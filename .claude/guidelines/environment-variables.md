# Environment Variables Strategy for tuti-cli

## Overview
tuti-cli uses a **single `.env` file** approach for both Laravel and Docker Compose configuration.

## Location
```
myapp/
├── .env                 # ✅ Single source of truth
├── artisan
├── composer.json
└── .tuti/
    ├── docker-compose.yml
    └── docker-compose.dev.yml
```

## Why Single `.env` File?

### ✅ Advantages
1. **Single Source of Truth** - One file to manage all environment variables
2. **No Confusion** - Developers don't need to edit multiple .env files
3. **Laravel Compatible** - Laravel reads it naturally from project root
4. **Docker Compose Compatible** - We pass `--env-file` explicitly
5. **Easy to Understand** - Clear sections separate Laravel and Docker configs

### ❌ Alternatives Considered (and why we didn't use them)

#### Separate `.tuti/.env` file
- ❌ Confusion: Which .env to edit?
- ❌ Duplication: DB_PASSWORD in two places
- ❌ Sync issues: Changes in one don't reflect in other

#### Multiple env files with includes
- ❌ Complex: Hard to understand for new developers
- ❌ Not portable: Different behavior on different systems

## File Structure

The `.env` file is divided into two clear sections:

### Section 1: Laravel Application (top)
```dotenv
# ============================================================================
# Laravel Application Environment
# ============================================================================
APP_NAME=myapp
APP_ENV=local
APP_KEY=base64:xxx...
APP_DEBUG=true
APP_URL=https://myapp.local.test

DB_CONNECTION=pgsql
DB_HOST=postgres          # ← Docker service name
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

REDIS_HOST=redis          # ← Docker service name
REDIS_PORT=6379

MAIL_HOST=mailpit         # ← Docker service name
MAIL_PORT=1025
```

### Section 2: tuti-cli Docker Configuration (bottom)
```dotenv
# ============================================================================
# 🐳 TUTI-CLI DOCKER CONFIGURATION
# ============================================================================
PROJECT_NAME=myapp
APP_DOMAIN=myapp.local.test

PHP_VERSION=8.4
PHP_VARIANT=fpm-nginx
BUILD_TARGET=development

DOCKER_USER_ID=1000
DOCKER_GROUP_ID=1000
```

## How It Works

### 1. Fresh Installation Flow

```bash
tuti stack:laravel myapp
```

1. ✅ Create Laravel project with `composer create-project`
   - Laravel creates `.env` from `.env.example`
   
2. ✅ Update Laravel's `.env`:
   - Set `DB_HOST=postgres` (Docker service name)
   - Set `REDIS_HOST=redis` (Docker service name)
   - Set `APP_URL=https://myapp.local.test`
   
3. ✅ Append tuti-specific variables:
   - Add Docker build configuration
   - Add project metadata

4. ✅ Docker Compose reads `.env`:
   - Pass `--env-file /path/to/myapp/.env`
   - Both Laravel and Docker use same variables

### 2. Docker Compose Integration

```yaml
# docker-compose.yml
services:
  app:
    build:
      args:
        PHP_VERSION: ${PHP_VERSION:-8.4}    # ← From .env
    environment:
      APP_NAME: ${APP_NAME}                 # ← From .env
      DB_HOST: postgres                     # ← Docker service name
      DB_PASSWORD: ${DB_PASSWORD}           # ← From .env
```

Command executed:
```bash
docker compose \
  -f .tuti/docker-compose.yml \
  -f .tuti/docker-compose.dev.yml \
  --env-file ./.env \              # ← Explicit env file
  -p myapp \
  up -d
```

## Variable Ownership

### Laravel Variables
These are standard Laravel env vars that Laravel reads:
- `APP_*` - Application settings
- `DB_*` - Database configuration
- `CACHE_*` - Cache configuration
- `MAIL_*` - Mail configuration
- etc.

### Docker Compose Variables
These are used by Docker Compose for container setup:
- `PROJECT_NAME` - Container naming
- `APP_DOMAIN` - Traefik routing
- `PHP_VERSION` - Build args
- `BUILD_TARGET` - development/production
- `DOCKER_USER_ID` - File permissions

### Shared Variables
Some variables are used by BOTH:
- `DB_PASSWORD` - Laravel connects to DB, Docker creates DB
- `APP_KEY` - Laravel uses it, we might need it for artisan commands
- `APP_URL` - Laravel generates URLs, we use for routing

## Implementation Details

### StackInitializationService

```php
// 1. Check if Laravel .env exists
if (file_exists($projectRoot . '/.env')) {
    // Laravel already created .env
    
    // 2. Update DB/Redis hosts to Docker service names
    updateLaravelEnv($projectName);
    
    // 3. Append tuti-specific variables
    appendTutiVariablesToEnv($envPath, $projectName);
} else {
    // No .env exists, copy full template
    copyFullEnvTemplate($stackPath, $environment, $projectName);
}
```

### DockerComposeOrchestrator

```php
$command = ['docker', 'compose'];
$command[] = '-f';
$command[] = '.tuti/docker-compose.yml';

// Explicitly pass env file from project root
$command[] = '--env-file';
$command[] = $project->path . '/.env';

$command[] = 'up';
$command[] = '-d';
```

## Developer Experience

### Editing Variables

```bash
# Developer wants to change database password
cd myapp

# Edit the SINGLE .env file
nano .env

# Change:
DB_PASSWORD=newsecret

# Restart containers to apply
tuti local:stop
tuti local:start
```

### Adding Custom Variables

Developers can add their own variables anywhere in the file:

```dotenv
# Custom variables
MY_CUSTOM_API_KEY=abc123
FEATURE_FLAG_X=true
```

Both Laravel and Docker Compose will have access to them.

## Troubleshooting

### Variables Not Loading

```bash
# 1. Check .env exists
ls -la myapp/.env

# 2. Check Docker Compose is using it
tuti debug enable
tuti local:start
tuti debug logs | grep "env_file"

# Should show:
# env_file: /path/to/myapp/.env
```

### Different Values in Containers

```bash
# Check what env vars container sees
docker exec myapp_app env | grep DB_PASSWORD

# Should match your .env file
```

### Permission Issues

```bash
# Make sure .env is readable
chmod 644 myapp/.env
```

## Best Practices

### ✅ DO
- Edit the single `.env` in project root
- Keep tuti section at the bottom
- Commit `.env.example` to git
- Add `.env` to `.gitignore`

### ❌ DON'T
- Create separate `.tuti/.env`
- Hardcode values in docker-compose.yml
- Put secrets in docker-compose.yml
- Duplicate variables between files

## Future Enhancements

### Possible Improvements
1. **Validation Command**: `tuti env:validate` to check for required vars
2. **Sync Command**: `tuti env:sync` to ensure consistency
3. **Encryption**: Support for encrypted env vars
4. **Templates**: Environment-specific templates (.env.staging, .env.prod)

## Migration Guide

### From Old Setup (if you had `.tuti/.env`)

```bash
# 1. Copy tuti variables from .tuti/.env
cat .tuti/.env | grep -E "(PROJECT_NAME|PHP_VERSION|DOCKER_USER)" > /tmp/tuti-vars

# 2. Append to project .env
cat /tmp/tuti-vars >> .env

# 3. Remove old .tuti/.env
rm .tuti/.env

# 4. Test
tuti local:start
```
