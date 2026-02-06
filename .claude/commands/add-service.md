Add a new Docker service stub to tuti-cli: $ARGUMENTS

## Context -- read these files first

1. Pick the target stack's service registry (one or both):
   - `stubs/stacks/laravel/services/registry.json`
   - `stubs/stacks/wordpress/services/registry.json`
2. Read an existing stub as reference:
   - `stubs/stacks/laravel/services/cache/redis.stub` (simple service)
   - `stubs/stacks/laravel/services/databases/postgres.stub` (service with volumes)
   - `stubs/stacks/laravel/services/workers/horizon.stub` (worker service)
3. `app/Services/Stack/StackStubLoaderService.php` - how stubs are loaded
4. `app/Services/Stack/StackComposeBuilderService.php` - how stubs are merged into compose

## Steps

1. **Create stub file** at `stubs/stacks/{stack}/services/{category}/{name}.stub`:

   ```yaml
   # @section: base
   {service}:
     image: {image}:${VERSION:-tag}
     container_name: {{PROJECT_NAME}}_${APP_ENV:-dev}_{service}
     networks:
       - {{NETWORK_NAME}}
     healthcheck:
       test: ["CMD", "healthcheck-command"]
       interval: 10s
       timeout: 5s
       retries: 5
     restart: unless-stopped

   # @section: dev
   {service}:
     container_name: {{PROJECT_NAME}}_${APP_ENV:-dev}_{service}
     ports:
       - "${EXTERNAL_PORT:-port}:port"

   # @section: volumes
   {service}_data:
     name: {{PROJECT_NAME}}_${APP_ENV:-dev}_{service}_data

   # @section: env
   {SERVICE}_HOST={service}
   {SERVICE}_PORT=port
   ```

2. **Register** in `stubs/stacks/{stack}/services/registry.json` -- follow the exact schema used by existing entries in that file.

3. **If the service applies to multiple stacks**, create it in each stack's services directory separately (stubs are per-stack, not global).

## Rules

- Always include healthcheck
- Use `${VAR:-default}` for Docker Compose runtime variables
- Use `{{VAR}}` for build-time replacements (PROJECT_NAME, NETWORK_NAME)
- Include `restart: unless-stopped`
- Use `# @section:` markers (base, dev, prod, volumes, env)
- Container name pattern: `{{PROJECT_NAME}}_${APP_ENV:-dev}_{service}`
- Volume name pattern: `{{PROJECT_NAME}}_${APP_ENV:-dev}_{service}_data`
