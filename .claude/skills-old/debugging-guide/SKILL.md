---
name: debugging-guide
description: Guide for debugging Tuti CLI issues including Docker problems, service failures, command errors, and configuration issues. Use when troubleshooting bugs, investigating failures, or diagnosing unexpected behavior in the CLI or Docker environment.
---

# Debugging Guide

Strategies and patterns for debugging issues in Tuti CLI.

## Quick Diagnosis

### 1. Check Docker Status
```bash
docker info                          # Docker daemon running?
docker compose ls                    # Active projects
docker ps -a                         # All containers
docker network ls                    # Networks
```

### 2. Check Tuti Infrastructure
```bash
php tuti infra:status                # Global infrastructure status
php tuti local:status                # Project status
php tuti local:logs                  # Recent logs
```

### 3. Check Logs
```bash
# Tuti debug logs
cat ~/.tuti/logs/tuti.log

# Docker logs
docker compose logs app              # App container
docker compose logs postgres         # Database
docker compose logs --tail=100       # Last 100 lines
```

## Common Issues

### Docker Issues

#### Container Won't Start
```bash
# Check container status
docker compose ps

# Check logs
docker compose logs {service}

# Check healthcheck
docker inspect {container} --format='{{.State.Health.Status}}'

# Force rebuild
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

#### Port Already in Use
```bash
# Find process using port
lsof -i :8080                        # Linux/Mac
netstat -ano | findstr :8080         # Windows

# Change port in .env
SERVICE_PORT=8081
```

#### Permission Denied
```bash
# Fix Docker permissions (Linux)
sudo usermod -aG docker $USER

# Fix file permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

### Command Issues

#### Command Not Found
- Check spelling: `php tuti list` to see all commands
- Check registration in `app/Providers/AppServiceProvider.php`
- Clear cache: `php artisan clear-compiled`

#### Command Fails Silently
```bash
# Enable debug logging
TUTI_DEBUG=true php tuti command:name

# Check debug log
cat ~/.tuti/logs/tuti.log | tail -50
```

#### Wrong Exit Code
- Check all return paths use `Command::SUCCESS` or `Command::FAILURE`
- Never use `exit()` in commands
- Check for unhandled exceptions

### Service Issues

#### Service Not Injected
- Check binding in `app/Providers/AppServiceProvider.php`
- Check constructor injection is used
- Verify interface is bound to implementation

#### Process Fails
```bash
# Check Process command (use array syntax!)
Process::run(['docker', 'info']);    # Correct
Process::run("docker info {$var}");  # WRONG - shell injection risk

# Check working directory
Process::path($directory)->run(['command']);
```

### Configuration Issues

#### Environment Variables Not Loading
- Check `.env` file exists in project root
- Check format: `KEY=value` (no spaces around `=`)
- Check Docker Compose uses `--env-file ./.env`
- Verify variable is referenced correctly: `${VAR:-default}`

#### Docker Compose Variable Substitution
```yaml
# Correct
container_name: ${PROJECT_NAME:-project}_${APP_ENV:-dev}_app

# Wrong (missing default)
container_name: ${PROJECT_NAME}_${APP_ENV}_app
```

## Debugging Workflow

### Step 1: Reproduce
- Document exact steps to reproduce
- Note expected vs actual behavior
- Check if issue is consistent or intermittent

### Step 2: Isolate
- Minimize the reproduction case
- Remove variables one at a time
- Test in isolation if possible

### Step 3: Investigate
```bash
# Enable debug mode
export TUTI_DEBUG=true

# Verbose Docker output
docker compose up --verbose

# Check all logs
docker compose logs --tail=500

# Inspect container state
docker inspect {container}
```

### Step 4: Fix
- Make minimal changes
- Test fix thoroughly
- Add test case if applicable
- Document the fix

### Step 5: Verify
- Run full test suite
- Test in clean environment
- Check for regression

## Debug Tools

### DebugLogService
```php
// Add debug logging
tuti_debug('Processing file', ['path' => $path, 'exists' => file_exists($path)]);

// Check logs
// ~/.tuti/logs/tuti.log
```

### Process Debugging
```php
// Log commands before running
tuti_debug('Running command', ['cmd' => $command]);

// Check output
$result = Process::run($command);
tuti_debug('Command output', [
    'exit_code' => $result->exitCode(),
    'output' => $result->output(),
    'error' => $result->errorOutput(),
]);
```

### Docker Debugging
```bash
# Interactive shell in container
docker compose exec app sh

# Run command as root
docker compose exec -u root app sh

# Check container networking
docker compose exec app ping postgres

# Check mounted volumes
docker compose exec app ls -la /var/www/html
```

## Error Patterns

### "Class not found"
- Run `composer dump-autoload`
- Check namespace matches directory structure
- Verify class name matches file name

### "Interface not instantiable"
- Check service provider binding
- Verify interface is bound to concrete class
- Check for circular dependencies

### "Permission denied" (storage)
```bash
docker compose exec -u root app chown -R www-data:www-data storage
docker compose exec -u root app chmod -R 775 storage
```

### "Connection refused"
- Check service is running: `docker compose ps`
- Check healthcheck status
- Verify network configuration
- Check service name matches in configuration

### "Variable not set"
- Check `.env` file exists
- Verify variable name (case-sensitive)
- Check for hidden characters in `.env`

## Common Stack Issues

### Laravel Stack

#### App Container Issues
```bash
# Rebuild app container
docker compose build app --no-cache
docker compose up -d app

# Check PHP-FPM
docker compose exec app php-fpm -t

# Check Laravel logs
docker compose exec app cat storage/logs/laravel.log
```

#### Database Connection
```bash
# Test connection
docker compose exec app php artisan tinker
>>> DB::connection()->getPdo();

# Check credentials in .env
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=tuti
DB_USERNAME=tuti
DB_PASSWORD=secret
```

### WordPress Stack

#### WP-CLI Issues
```bash
# Check wp-config.php
docker compose exec app cat wp-config.php

# Test database
docker compose exec app wp db check --allow-root
```

## Quick Reference

### Log Locations
| Log | Location |
|-----|----------|
| Tuti debug | `~/.tuti/logs/tuti.log` |
| Laravel | `storage/logs/laravel.log` |
| Docker | `docker compose logs {service}` |
| PHP-FPM | Container stdout/stderr |

### Healthcheck Commands
| Service | Command |
|---------|---------|
| PostgreSQL | `pg_isready -U $USER` |
| MySQL | `mysqladmin ping -h localhost` |
| Redis | `redis-cli ping` |
| PHP-FPM | `php-fpm-healthcheck` |

### Common Ports
| Service | Port |
|---------|------|
| HTTP | 80 |
| HTTPS | 443 |
| PHP-FPM | 9000 |
| PostgreSQL | 5432 |
| MySQL | 3306 |
| Redis | 6379 |
| Mailpit SMTP | 1025 |
| Mailpit Web | 8025 |
| Minio API | 9000 |
| Minio Console | 9001 |
| Meilisearch | 7700 |