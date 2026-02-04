# Troubleshooting 404 Errors

## Quick Fix

If you're getting 404 errors after configuration changes, follow these steps:

### 1. Check Container Status
```bash
cd /home/yevhenii/www/tuti-cli/laravel-app
docker ps
```

Look for:
- All containers running (app, postgres, redis, mailpit)
- No "(unhealthy)" status
- App container should show "Up" without restart loops

### 2. Check Container Logs
```bash
# Check app container logs
docker logs laravel-app_app

# Look for:
# - ✅ NGINX + PHP-FPM is running correctly
# - ✅ Storage directories configured
# - ❌ Any error messages
```

### 3. Full Rebuild (Most Reliable)
```bash
cd /home/yevhenii/www/tuti-cli/laravel-app

# Complete cleanup and rebuild
docker compose -f .tuti/docker-compose.yml -f .tuti/docker-compose.dev.yml -p laravel-app down -v
docker compose -f .tuti/docker-compose.yml -f .tuti/docker-compose.dev.yml -p laravel-app up -d --build --force-recreate
```

Or use tuti commands:
```bash
# Stop everything
../tuti local:stop

# Rebuild from scratch
../tuti local:rebuild --no-cache

# Check status
../tuti local:status
```

### 4. Verify Network Connectivity
```bash
# Ensure app is on traefik_proxy network
docker network inspect traefik_proxy | grep laravel-app_app

# Should show the app container connected
```

### 5. Check Traefik Routing
```bash
# View Traefik logs
docker logs tuti-traefik

# Look for routing to laravel-app
```

### 6. Test Direct Container Access
```bash
# Access app container directly (bypass Traefik)
docker exec laravel-app_app curl -I http://localhost:8080

# Should return HTTP 200 with Laravel headers
```

## Common 404 Causes After Config Changes

### Cause 1: Containers Not Restarted
**Symptom:** Changed `.env` or `docker-compose.yml` but didn't restart
**Fix:** 
```bash
../tuti local:stop
../tuti local:start
```

### Cause 2: Build Cache Issues
**Symptom:** Dockerfile changes not applied
**Fix:**
```bash
../tuti local:rebuild --no-cache
```

### Cause 3: Network Not Connected
**Symptom:** Traefik can't reach app container
**Fix:**
```bash
# Check networks in docker-compose.dev.yml
# App service MUST have:
networks:
  - traefik_proxy
  - app_network
```

### Cause 4: Unhealthy Dependencies
**Symptom:** App depends on postgres/redis healthcheck but they're failing
**Fix:**
```bash
# Check container health
docker ps

# If redis is unhealthy, check logs
docker logs laravel-app_redis

# Rebuild if needed
../tuti local:rebuild
```

### Cause 5: Volume Mount Issues
**Symptom:** Laravel files not visible in container
**Fix:**
```bash
# Verify volume mount
docker exec laravel-app_app ls -la /var/www/html

# Should show Laravel files (artisan, composer.json, etc.)
```

## Step-by-Step Recovery

If you have persistent 404s, follow this complete recovery:

```bash
cd /home/yevhenii/www/tuti-cli/laravel-app

# 1. Stop everything
docker compose -f .tuti/docker-compose.yml -f .tuti/docker-compose.dev.yml -p laravel-app down

# 2. Remove volumes (WARNING: deletes database data)
docker volume rm laravel-app_postgres_data laravel-app_redis_data || true

# 3. Ensure traefik_proxy network exists
docker network inspect traefik_proxy || docker network create traefik_proxy

# 4. Rebuild completely
docker compose -f .tuti/docker-compose.yml -f .tuti/docker-compose.dev.yml \
  --env-file .env \
  -p laravel-app \
  up -d --build --force-recreate

# 5. Check status
docker ps
docker logs laravel-app_app

# 6. Test
curl -I https://laravel-app.local.test
```

## Verification Checklist

After rebuild, verify:

- [ ] All 4 containers running (app, postgres, redis, mailpit)
- [ ] App container shows "healthy" or no health status
- [ ] Postgres shows "healthy"
- [ ] Redis shows "healthy"
- [ ] No restart loops in `docker ps`
- [ ] App logs show "NGINX + PHP-FPM is running correctly"
- [ ] Network `traefik_proxy` shows app container connected
- [ ] https://laravel-app.local.test returns 200 (not 404)

## Still Getting 404?

Run these diagnostic commands:

```bash
# 1. Check if Laravel is accessible inside container
docker exec laravel-app_app curl http://localhost:8080
# Should return HTML (Laravel welcome page)

# 2. Check if Traefik sees the container
docker logs tuti-traefik 2>&1 | grep laravel-app
# Should show router/service configuration

# 3. Check Traefik labels
docker inspect laravel-app_app | grep traefik
# Should show all Traefik labels

# 4. Test DNS resolution
ping laravel-app.local.test
# Should resolve to 127.0.0.1

# 5. Check certificate
curl -Ik https://laravel-app.local.test
# Should return headers (even with cert warning)
```

## Get Help

If still not working:

1. Run: `../tuti debug errors`
2. Check: `docker logs laravel-app_app`
3. Check: `docker logs tuti-traefik`
4. Verify: All files in this guide

The issue is usually:
- Containers not rebuilt after config changes
- Network connectivity
- Healthcheck failures blocking startup
