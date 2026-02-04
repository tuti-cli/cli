# Applying Configuration Changes

When you make changes to Docker configuration files in your tuti-cli project, you need to rebuild the containers to apply those changes.

## When to Rebuild

Rebuild your containers after modifying:

- **Dockerfile** (`.tuti/docker/Dockerfile`)
  - Changing base image version
  - Adding/removing PHP extensions
  - Installing system packages
  - Modifying build stages
  - Updating entrypoint scripts

- **docker-compose.yml** files
  - Changing environment variables
  - Modifying service configuration
  - Adding/removing services
  - Updating volume mounts
  - Changing network configuration

- **Entrypoint scripts** (`.tuti/scripts/entrypoint-dev.sh`)
  - Permission fixes
  - Startup scripts
  - Environment setup

- **After git pull** that includes Docker configuration changes

## How to Rebuild

### Basic Rebuild
```bash
cd /path/to/your/project
tuti local:rebuild
```

This will:
1. Stop current containers
2. Rebuild images using Docker cache
3. Pull latest base images
4. Start containers with new configuration

### Clean Rebuild (Recommended for Major Changes)
```bash
tuti local:rebuild --no-cache
```

Use `--no-cache` when:
- Troubleshooting build issues
- After updating base image versions
- When cached layers might be corrupted
- After major Dockerfile changes

**Note:** This is slower but ensures a completely fresh build.

### Quiet Rebuild (Without Build Logs)
```bash
tuti local:rebuild --detach
# or shorthand
tuti local:rebuild -d
```

Use `--detach` when:
- You want quieter output
- Running in scripts/automation
- You don't need to see detailed build progress

**Note:** Build still runs, just without streaming output to terminal.

### Force Rebuild (Advanced)
```bash
tuti local:rebuild --force
```

Rebuilds without stopping containers first. **Not recommended** for normal use.

## Example: Fixing Permission Issues

After adding the new entrypoint script to fix Laravel storage permissions:

```bash
cd /home/yevhenii/www/tuti-cli/laravel-app

# Rebuild with clean slate
tuti local:rebuild --no-cache
```

The new entrypoint script will be copied into the image and will run on every container start.

## Troubleshooting

### Build Fails
```bash
# Try a clean rebuild
tuti local:rebuild --no-cache

# Check logs for specific errors
tuti debug errors
```

### Containers Won't Start After Rebuild
```bash
# Check container logs
tuti local:logs

# Check status
tuti local:status

# View detailed errors
tuti debug errors
```

### Changes Not Applied
- Ensure you're in the correct project directory
- Verify the files you changed are in `.tuti/` directory
- Try `--no-cache` flag
- Check if Dockerfile COPY commands are correct

## Quick Reference

| Command | When to Use |
|---------|-------------|
| `tuti local:rebuild` | Normal changes to Docker config (shows build logs) |
| `tuti local:rebuild --no-cache` | Major changes, troubleshooting |
| `tuti local:rebuild -d` | Quiet rebuild without logs |
| `tuti local:rebuild --force` | Advanced use only |
| `tuti local:stop` then `tuti local:start` | When rebuild isn't needed |

## Best Practices

1. **Commit before rebuild** - In case something goes wrong
2. **Use --no-cache for major changes** - Ensures clean state
3. **Check logs after rebuild** - Verify everything started correctly
4. **Test the application** - Don't assume rebuild = working app
5. **Document custom changes** - For future reference

## Alternative: Manual Docker Commands

If you prefer direct Docker commands:

```bash
cd /path/to/project/.tuti

# Stop containers
docker compose -f docker-compose.yml -f docker-compose.dev.yml -p project-name down

# Rebuild
docker compose -f docker-compose.yml -f docker-compose.dev.yml -p project-name build --no-cache --pull

# Start
docker compose -f docker-compose.yml -f docker-compose.dev.yml -p project-name up -d
```

But `tuti local:rebuild` is simpler and handles all the details for you!
