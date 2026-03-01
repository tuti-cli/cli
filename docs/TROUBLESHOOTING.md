# Troubleshooting Guide

This guide helps you diagnose and resolve common issues with tuti-cli.

## Quick Diagnostics

Before diving into specific issues, run the diagnostic command:

```bash
tuti doctor
```

This checks your system for:
- Docker installation and daemon status
- Docker Compose availability
- Global tuti configuration
- Infrastructure (Traefik) status
- Current project configuration

For automatic fixes where possible:

```bash
tuti doctor --fix
```

---

## Common Docker Issues

### Docker Not Found

**Symptom:** `docker: command not found` or `Docker is not installed`

**Solution:**
1. Install Docker Desktop from [docker.com](https://www.docker.com/products/docker-desktop)
2. On Linux, you may need:
   ```bash
   # Ubuntu/Debian
   sudo apt update && sudo apt install docker.io docker-compose-plugin
   sudo systemctl enable docker
   sudo systemctl start docker
   ```
3. Verify installation:
   ```bash
   docker --version
   ```

### Docker Daemon Not Running

**Symptom:** `Cannot connect to the Docker daemon` or `Docker daemon is not running`

**Solution:**

**macOS/Windows:**
- Open Docker Desktop application
- Wait for the whale icon to stop animating
- Ensure it shows "Docker Desktop is running"

**Linux:**
```bash
sudo systemctl start docker
# Or on some systems:
sudo service docker start
```

**WSL 2:**
- Ensure Docker Desktop has WSL 2 integration enabled
- Check Settings > Resources > WSL Integration

### Permission Denied on Docker Commands

**Symptom:** `permission denied while trying to connect to the Docker daemon socket`

**Solution:**
```bash
# Add your user to the docker group
sudo usermod -aG docker $USER

# Log out and back in, or run:
newgrp docker

# Verify:
docker ps
```

### Docker Compose Version Issues

**Symptom:** Errors about `docker-compose` or compose file version

**Solution:**
tuti-cli requires Docker Compose V2 (plugin-based). Verify:

```bash
docker compose version  # Note: no hyphen
```

If you see `docker: 'compose' is not a docker command`, install the plugin:

```bash
# Ubuntu/Debian
sudo apt install docker-compose-plugin

# Or via Docker Desktop (included by default)
```

### Container Build Failures

**Symptom:** Build fails with errors about packages, extensions, or checksums

**Solutions:**

1. **Clean rebuild:**
   ```bash
   tuti local:rebuild --no-cache
   ```

2. **Disk space issues:**
   ```bash
   docker system prune -a  # Warning: removes unused images
   ```

3. **Network issues (slow downloads):**
   - Check internet connection
   - Try again (transient network failures)
   - Consider using a Docker registry mirror

---

## Port Conflicts

### Port 80 Already in Use

**Symptom:** `Bind for 0.0.0.0:80 failed: port is already allocated`

**Common causes:**
- Local web server (Apache, Nginx)
- Another Docker container using port 80
- Skype, Zoom, or other applications

**Solutions:**

1. **Stop conflicting services:**
   ```bash
   # Apache (Linux/macOS)
   sudo systemctl stop apache2
   # or
   sudo apachectl stop

   # Nginx
   sudo systemctl stop nginx
   ```

2. **Find what's using port 80:**
   ```bash
   # Linux
   sudo lsof -i :80
   sudo netstat -tlnp | grep :80

   # macOS
   lsof -i :80

   # Windows (PowerShell)
   netstat -ano | findstr :80
   ```

### Port 443 Already in Use

**Symptom:** `Bind for 0.0.0.0:443 failed: port is already allocated`

Same solutions as port 80 above. Often both ports are used by the same service.

### Port 3306 Already in Use (MySQL/MariaDB)

**Symptom:** Cannot start database container

**Solutions:**

1. **Stop local MySQL:**
   ```bash
   # Linux
   sudo systemctl stop mysql
   sudo systemctl stop mariadb

   # macOS (Homebrew)
   brew services stop mysql
   ```

2. **Or use a different port** by editing `.tuti/docker-compose.dev.yml`:
   ```yaml
   services:
     database:
       ports:
         - "3307:3306"  # Use 3307 on host
   ```

### Port 5432 Already in Use (PostgreSQL)

**Symptom:** Cannot start Postgres container

**Solutions:**

1. **Stop local PostgreSQL:**
   ```bash
   # Linux
   sudo systemctl stop postgresql

   # macOS (Homebrew)
   brew services stop postgresql
   ```

2. **Or use a different port:**
   ```yaml
   services:
     database:
       ports:
         - "5433:5432"
   ```

### Port 6379 Already in Use (Redis)

**Symptom:** Cannot start Redis container

**Solutions:**

1. **Stop local Redis:**
   ```bash
   # Linux
   sudo systemctl stop redis

   # macOS (Homebrew)
   brew services stop redis
   ```

### Multiple Projects, Same Ports

**Symptom:** Cannot start second project due to port conflicts

**Solution:**
tuti-cli uses Traefik reverse proxy with domain-based routing. Each project gets a unique domain:

- Project `myapp` → `http://myapp.localhost`
- Project `other` → `http://other.localhost`

Only Traefik needs ports 80/443. Project containers don't expose these ports directly.

If you see port conflicts between projects, ensure:
1. Traefik is running: `tuti infra:status`
2. You're using the project name in the URL (not `localhost` directly)

---

## Permission Issues

### Storage Permission Errors (Laravel)

**Symptom:** `The stream or file "/var/www/html/storage/logs/laravel.log" could not be opened`

**Solution:**

The entrypoint script should handle this automatically. If issues persist:

```bash
# Rebuild containers to apply entrypoint changes
tuti local:rebuild --no-cache
```

**Manual fix inside container:**
```bash
docker exec -it myapp_development_app bash
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache
```

### WP-Content Permission Errors (WordPress)

**Symptom:** Cannot upload files, plugin installation fails

**Solution:**
```bash
docker exec -it myblog_development_app bash
chown -R www-data:www-data /var/www/html/wp-content
chmod -R 775 /var/www/html/wp-content
```

### Files Created as Root

**Symptom:** Files in `vendor/` or other directories owned by root, cannot edit

**Cause:** Docker builds run as root by default

**Solution:**
tuti-cli passes `USER_ID` and `GROUP_ID` build args to run as your user. Ensure these are set:

```bash
# Check your .env file
grep USER_ID .env
grep GROUP_ID .env
```

Should show your user/group IDs:
```
USER_ID=1000
GROUP_ID=1000
```

Find your IDs:
```bash
id -u  # USER_ID
id -g  # GROUP_ID
```

If missing, regenerate:
```bash
tuti local:rebuild --no-cache
```

### Script Not Executable

**Symptom:** `permission denied: ./entrypoint-dev.sh`

**Solution:**
```bash
chmod +x .tuti/scripts/*.sh
tuti local:rebuild
```

### Cannot Write to .tuti Directory

**Symptom:** Permission errors when running tuti commands

**Solution:**
```bash
# Fix ownership
sudo chown -R $USER:$USER .tuti

# Fix permissions
chmod -R 775 .tuti
```

---

## Traefik / Infrastructure Issues

### Traefik Not Starting

**Symptom:** `tuti infra:start` fails

**Solutions:**

1. **Check if Traefik container exists:**
   ```bash
   docker ps -a | grep traefik
   ```

2. **View Traefik logs:**
   ```bash
   docker logs tuti_traefik
   ```

3. **Remove and recreate:**
   ```bash
   docker rm -f tuti_traefik
   tuti infra:start
   ```

### Site Not Accessible via .localhost Domain

**Symptom:** `http://myapp.localhost` doesn't work, but container is running

**Checks:**

1. **Traefik is running:**
   ```bash
   tuti infra:status
   ```

2. **Container has Traefik labels:**
   ```bash
   docker inspect myapp_development_app | grep -A 20 Labels
   ```

3. **Network connectivity:**
   ```bash
   # Can you reach Traefik?
   curl -I http://localhost

   # Is container on correct network?
   docker network inspect tuti_proxy
   ```

4. **Browser DNS issues:**
   - Try incognito/private window
   - Clear browser cache
   - Try `http://127.0.0.1:80` (should show Traefik 404)

### Multiple Projects Not Routing Correctly

**Symptom:** One project's URL shows another project

**Solution:**
Restart Traefik to refresh routing:
```bash
tuti infra:restart
```

---

## Stack Initialization Issues

### "Project Already Exists" Error

**Symptom:** `tuti stack:laravel myapp` fails because directory exists

**Solutions:**

1. **Remove existing directory:**
   ```bash
   rm -rf myapp
   tuti stack:laravel myapp
   ```

2. **Or use existing project mode:**
   ```bash
   cd myapp
   tuti stack:laravel --mode=existing
   ```

### "Not a Laravel/WordPress Project" Error

**Symptom:** `--mode=existing` fails validation

**Solution:**
Ensure your project has the required files:

- **Laravel:** `artisan`, `composer.json`
- **WordPress:** `wp-config.php` or `index.php` with WordPress code

### Composer Install Fails in Container

**Symptom:** Dependencies not installing during build

**Solution:**
```bash
# Check build logs
tuti local:rebuild --no-cache

# Or manually in container
docker exec -it myapp_development_app bash
composer install --no-interaction
```

### WP-CLI Setup Fails

**Symptom:** `tuti wp:setup` returns errors

**Common issues:**

1. **Database not ready:**
   ```bash
   # Check database is running
   tuti local:status
   ```

2. **wp-config.php issues:**
   ```bash
   # Check database credentials in wp-config.php
   cat wp-config.php | grep DB_
   ```

3. **Manual WordPress install:**
   ```bash
   # Use container CLI directly
   docker exec -it myblog_development_app wp --allow-root core install \
     --url=http://myblog.localhost \
     --title="My Blog" \
     --admin_user=admin \
     --admin_password=secure123 \
     --admin_email=admin@example.com
   ```

---

## Debug and Logging

### Enable Debug Mode

For detailed logging of all operations:

```bash
tuti debug enable
```

This logs all commands, process outputs, and errors to `~/.tuti/logs/tuti.log`.

### View Debug Logs

```bash
# View recent logs
tuti debug logs

# View last 100 lines
tuti debug logs --lines=100

# View only errors
tuti debug errors

# Filter by level
tuti debug logs --level=error
```

### Check Container Logs

```bash
# All services
tuti local:logs

# Specific service
tuti local:logs app
tuti local:logs database
tuti local:logs redis

# Follow logs in real-time
tuti local:logs --follow
# Or directly:
docker compose -f .tuti/docker-compose.yml -f .tuti/docker-compose.dev.yml logs -f app
```

### Check Container Status

```bash
tuti local:status
```

Shows:
- Container names and states
- Health check status
- Port mappings

### Check Disk Usage

```bash
# Docker disk usage
docker system df

# Detailed breakdown
docker system df -v
```

### Clean Up Docker Resources

```bash
# Remove unused containers, networks, images
docker system prune

# More aggressive (unused images too)
docker system prune -a

# Including volumes (WARNING: deletes data)
docker system prune -a --volumes
```

---

## FAQ

### How do I reset everything and start fresh?

```bash
# Stop and remove all project containers
tuti local:stop
docker compose -f .tuti/docker-compose.yml -f .tuti/docker-compose.dev.yml down -v

# Remove infrastructure
tuti infra:stop
docker rm -f tuti_traefik

# Remove global config (optional)
rm -rf ~/.tuti

# Reinstall
tuti install
tuti infra:start
tuti local:start
```

### How do I access services like Mailpit?

Mailpit runs on port 1025 (SMTP) and 8025 (Web UI). Access the web UI at:

```
http://localhost:8025
```

Or add to your `/etc/hosts`:
```
127.0.0.1 mailpit.localhost
```

Then access via `http://mailpit.localhost:8025`

### How do I run artisan commands?

```bash
# Using docker exec
docker exec -it myapp_development_app php artisan migrate

# Or enter container shell
docker exec -it myapp_development_app bash
php artisan migrate
```

### How do I run composer/npm commands?

```bash
# Composer
docker exec -it myapp_development_app composer install
docker exec -it myapp_development_app composer require package/name

# NPM (if Node.js is installed in container)
docker exec -it myapp_development_app npm install
docker exec -it myapp_development_app npm run dev
```

### How do I connect to the database?

From your host machine:

```bash
# MySQL/MariaDB
mysql -h 127.0.0.1 -P 3306 -u app_user -papp_password app_db

# PostgreSQL
psql -h 127.0.0.1 -p 5432 -U app_user -d app_db
```

Or use a GUI tool (TablePlus, DBeaver, etc.) with:
- Host: `127.0.0.1`
- Port: `3306` (MySQL) or `5432` (PostgreSQL)
- User/Password/Database: Check your `.env` file

### Why is my site slow?

Common causes:

1. **Docker Desktop resource limits** - Increase memory/CPU in Docker Desktop settings
2. **Filesystem performance** - On macOS, ensure your project is in a folder that Docker Desktop has access to
3. **No OPcache** - Ensure PHP OPcache is enabled (default in tuti templates)
4. **Database not optimized** - For development, this is normal

### How do I update tuti-cli?

```bash
# Using the install script
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash

# Or download latest binary
curl -fsSL https://github.com/tuti-cli/cli/releases/latest/download/tuti-linux-x64 -o tuti
chmod +x tuti && sudo mv tuti /usr/local/bin/
```

### How do I switch between PHP versions?

PHP version is defined in the Dockerfile. To change:

1. Edit `.tuti/docker/Dockerfile`
2. Change the base image version:
   ```dockerfile
   # Change from:
   FROM serversideup/php:8.2-fpm-nginx
   # To:
   FROM serversideup/php:8.3-fpm-nginx
   ```
3. Rebuild:
   ```bash
   tuti local:rebuild --no-cache
   ```

### Can I use tuti-cli for production deployments?

tuti-cli is primarily designed for local development. For production:

1. Use the production Docker Compose file:
   ```bash
   docker compose -f .tuti/docker-compose.yml -f .tuti/docker-compose.prod.yml up -d
   ```

2. Ensure all environment variables are properly set
3. Configure proper SSL certificates
4. Review security settings (no debug tools, proper file permissions)

### Where are my database files stored?

Database files are stored in Docker volumes, not in your project directory:

```bash
# List volumes
docker volume ls | grep myapp

# Inspect a volume
docker volume inspect myapp_development_postgres_data
```

To persist data outside Docker volumes, modify `docker-compose.yml` to use bind mounts.

### How do I add custom services?

1. Copy a service stub from `stubs/stacks/laravel/services/`
2. Or manually add to `.tuti/docker-compose.dev.yml`:
   ```yaml
   services:
     myservice:
       image: myservice:latest
       networks:
         - app_network
   ```

---

## Getting Help

If you're still experiencing issues:

1. **Run diagnostics:**
   ```bash
   tuti doctor
   tuti debug enable
   # reproduce the issue
   tuti debug errors
   ```

2. **Check existing issues:**
   https://github.com/tuti-cli/cli/issues

3. **Create a new issue** with:
   - Output of `tuti doctor`
   - Debug logs (`tuti debug logs`)
   - Steps to reproduce
   - Your OS and Docker version
