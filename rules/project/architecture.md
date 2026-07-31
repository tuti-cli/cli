# Project Architecture

## Docker Integration

- Compose generation: **base + overlay** pattern (docker-compose.yml + docker-compose.dev.yml)
- Container naming: `${PROJECT_NAME}_${APP_ENV}_{service}`
- Network naming: `${PROJECT_NAME}_${APP_ENV}_network`
- Volume naming: `${PROJECT_NAME}_${APP_ENV}_{volume}_data`
- YAML anchors for shared config (`x-app-env-base`, `x-app-build-base`, `x-common-service`)
- Always include healthchecks for services
- `${VAR:-default}` syntax in compose files
- `{{VAR}}` syntax for build-time replacements in stubs
- Single `.env` file in project root (shared by Laravel and Docker Compose)
- Docker Compose uses `--env-file ./.env` explicitly
- Service stubs live inside each stack: `stubs/stacks/{stack}/services/`
- Global Traefik infrastructure in `stubs/infrastructure/traefik/`

## Service Stubs (Section-Based Format)

Stubs use `# @section:` markers to split into base, dev, volumes, and env sections:
```yaml
# @section: base     -> docker-compose.yml
# @section: dev      -> docker-compose.dev.yml
# @section: volumes  -> volume definitions
# @section: env      -> variables added to .env
```

## Key Interfaces

- `StackInstallerInterface` — stack installation (installFresh, applyToExisting)
- `OrchestratorInterface` — container lifecycle (start, stop, restart, status, logs)
- `InfrastructureManagerInterface` — global infra (install, start, stop, ensureReady)

## Security

- **ALWAYS** array syntax for `Process::run()` — NEVER string interpolation
- **NEVER** `escapeshellarg()` / `escapeshellcmd()` — array syntax eliminates the need
- Docker commands centralized in `DockerExecutorService` or `DockerComposeOrchestrator`
- Validate file paths with `file_exists()`, `is_dir()` before passing to Process
- Use `Process::path($dir)` for working directory — never `cd` in command strings

## Build

- All code must work when compiled to PHAR/native binary
- Use `base_path()` for stub resolution
- Build via phpacker (see Makefile)
