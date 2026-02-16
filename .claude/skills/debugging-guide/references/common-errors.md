# Common Errors Reference

Quick reference for common errors encountered in Tuti CLI development.

## Docker Errors

### "Cannot connect to the Docker daemon"
**Cause**: Docker daemon not running or permission issue.

**Solution**:
```bash
# Start Docker daemon
sudo systemctl start docker  # Linux
open -a Docker               # macOS

# Fix permissions (Linux)
sudo usermod -aG docker $USER
newgrp docker
```

### "port is already allocated"
**Cause**: Another container or service using the same port.

**Solution**:
```bash
# Find what's using the port
lsof -i :8080
# or
netstat -tulpn | grep 8080

# Stop conflicting container
docker compose down
docker rm -f $(docker ps -aq --filter "publish=8080")

# Or change port in .env
SERVICE_PORT=8081
```

### "no such file or directory" in container
**Cause**: Volume mount path doesn't exist or is incorrect.

**Solution**:
```bash
# Check volume mounts
docker inspect {container} | grep -A 10 "Mounts"

# Ensure path exists before mounting
mkdir -p ./storage

# Rebuild with fresh volumes
docker compose down -v
docker compose up -d --build
```

### "permission denied" in container
**Cause**: File ownership mismatch between host and container.

**Solution**:
```bash
# Fix ownership from container
docker compose exec -u root app chown -R www-data:www-data /var/www/html/storage

# Or fix from host
sudo chown -R $USER:$USER ./storage
chmod -R 775 ./storage
```

## PHP/Laravel Errors

### "Class not found"
**Cause**: Autoloader out of sync.

**Solution**:
```bash
# Regenerate autoloader
composer dump-autoload -o

# Clear all caches
php artisan clear-compiled
php artisan cache:clear
php artisan config:clear
```

### "Target class [X] does not exist"
**Cause**: Service not registered or namespace mismatch.

**Solution**:
```php
// Check service provider registration
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(YourService::class);
}

// Verify namespace matches directory
namespace App\Services\Domain;  // Must match app/Services/Domain/
```

### "Interface not instantiable"
**Cause**: Interface not bound to implementation.

**Solution**:
```php
// In service provider
public function register(): void
{
    $this->app->bind(
        \App\Contracts\YourInterface::class,
        \App\Services\YourImplementation::class
    );
}
```

### "No space left on device"
**Cause**: Docker images/volumes consuming disk space.

**Solution**:
```bash
# Clean up Docker
docker system prune -a --volumes

# Check disk usage
docker system df
df -h
```

## Command Errors

### "Command not found"
**Cause**: Command not registered or typo.

**Solution**:
```bash
# List all commands
php tuti list

# Check command registration in:
# app/Console/Kernel.php
# app/Providers/AppServiceProvider.php

# Clear cache
php artisan clear-compiled
```

### "Return value must be of type int, none returned"
**Cause**: Command handle() method missing return statement.

**Solution**:
```php
public function handle(): int
{
    // ... logic ...
    
    return Command::SUCCESS;  // Always return!
}
```

### Process::run() fails silently
**Cause**: Not checking process result or using wrong syntax.

**Solution**:
```php
// Use array syntax (not string interpolation!)
$result = Process::run(['docker', 'info']);

// Check result
if ($result->failed()) {
    throw new RuntimeException(
        "Command failed: " . $result->errorOutput()
    );
}

// Debug output
tuti_debug('Process result', [
    'exit_code' => $result->exitCode(),
    'output' => $result->output(),
    'error' => $result->errorOutput(),
]);
```

## Database Errors

### "SQLSTATE[HY000] [2002] Connection refused"
**Cause**: Database not ready or wrong host.

**Solution**:
```bash
# Check database container
docker compose ps
docker compose logs postgres

# Wait for database
docker compose exec app php artisan db:wait

# Verify host in .env
DB_HOST=postgres  # Use service name, not localhost
DB_PORT=5432
```

### "SQLSTATE[08006] [7] FATAL: password authentication failed"
**Cause**: Wrong credentials.

**Solution**:
```bash
# Check .env matches docker-compose.yml
cat .env | grep DB_

# Reset database password
docker compose exec postgres psql -U postgres -c "ALTER USER tuti PASSWORD 'secret';"
```

### "database exists" error
**Cause**: Database already created.

**Solution**:
```bash
# Drop and recreate
docker compose exec postgres psql -U postgres -c "DROP DATABASE tuti;"
docker compose exec postgres psql -U postgres -c "CREATE DATABASE tuti;"

# Or use fresh volumes
docker compose down -v
docker compose up -d
```

## Test Errors

### "Unable to create mock" in Pest
**Cause**: Interface not properly bound or mock configured wrong.

**Solution**:
```php
// Correct mock pattern
beforeEach(function (): void {
    $this->mock = mock(YourInterface::class);
    $this->app->instance(YourInterface::class, $this->mock);
});
```

### Process::fake() not catching calls
**Cause**: Pattern doesn't match actual command.

**Solution**:
```php
// Use wildcards carefully
Process::fake([
    '*' => Process::result(output: 'OK'),  // Matches everything
    '*docker*compose*' => Process::result(output: 'OK'),  // Specific
]);

// Debug what command was run
Process::assertRan(function ($command) {
    dump($command);  // See actual command
    return true;
});
```

### Tests pass locally but fail in CI
**Cause**: Environment differences.

**Solution**:
```bash
# Ensure consistent environment
- Use Docker for all environments
- Check .env.ci vs .env.testing
- Verify PHP version matches
- Check extension availability
```

## Stack Errors

### "Stack not found"
**Cause**: Stack not registered.

**Solution**:
```bash
# Check registry
cat stubs/stacks/registry.json

# Verify stack.json exists
cat stubs/stacks/{stack}/stack.json

# Check installer class registered
grep -r "StackInstaller" app/Providers/
```

### "Service stub not applied"
**Cause**: Stub format incorrect or service not registered.

**Solution**:
```bash
# Check stub format has correct sections
grep "@section" stubs/stacks/{stack}/services/{service}.stub

# Verify registry entry
cat stubs/stacks/{stack}/services/registry.json
```

## Environment Errors

### ".env file not found"
**Cause**: Missing environment file.

**Solution**:
```bash
# Copy example
cp stubs/stacks/laravel/environments/.env.dev.example .env

# Generate new
php tuti stack:init
```

### Environment variables not expanding
**Cause**: Wrong syntax in docker-compose.yml.

**Solution**:
```yaml
# Correct syntax
environment:
  VAR: ${VAR:-default}     # With default
  VAR: ${VAR}              # Without default (not recommended)

# Wrong
environment:
  VAR: $VAR                # Missing braces
  VAR: ${VAR-default}      # Wrong syntax for default
```

## Quick Reference

| Error Type | First Check |
|------------|-------------|
| Docker | `docker info` and `docker compose logs` |
| PHP | `composer dump-autoload` |
| Database | `docker compose ps` and `.env` credentials |
| Command | `php tuti list` and registration |
| Test | Mock bindings and Process patterns |
| Stack | `registry.json` and `stack.json` |