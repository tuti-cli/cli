Work on the Laravel stack: $ARGUMENTS

## Context -- read these files first

1. `stubs/stacks/laravel/stack.json` - stack manifest (PHP 8.4, fpm-nginx, environments)
2. `app/Services/Stack/Installers/LaravelStackInstaller.php` - installer implementation
3. `app/Commands/Stack/LaravelCommand.php` - main command (stack:laravel)
4. `stubs/stacks/laravel/docker-compose.yml` + `docker-compose.dev.yml` - compose config
5. `stubs/stacks/laravel/docker/Dockerfile` - serversideup/php:8.4-fpm-nginx image
6. `stubs/stacks/laravel/services/registry.json` - available services
7. `stubs/stacks/laravel/environments/.env.dev.example` - env template
8. `stubs/stacks/laravel/scripts/entrypoint-dev.sh` - dev entrypoint

## Stack file structure

```
stubs/stacks/laravel/
├── stack.json
├── docker-compose.yml
├── docker-compose.dev.yml
├── docker/Dockerfile                # serversideup/php:8.4-fpm-nginx
├── environments/.env.dev.example
├── scripts/entrypoint-dev.sh
└── services/
    ├── registry.json
    ├── databases/postgres.stub      # PostgreSQL (default)
    ├── databases/mysql.stub         # MySQL
    ├── databases/mariadb.stub       # MariaDB
    ├── cache/redis.stub
    ├── search/meilisearch.stub
    ├── search/typesense.stub
    ├── storage/minio.stub
    ├── mail/mailpit.stub
    ├── workers/scheduler.stub       # Laravel Scheduler
    └── workers/horizon.stub         # Laravel Horizon (requires Redis)
```

## Key details

- PHP image: `serversideup/php:8.4-fpm-nginx` (variants: fpm-nginx, fpm-apache, cli)
- Database: PostgreSQL (default), MySQL, or MariaDB
- Auto-generates: `APP_KEY` (laravel_key), `DB_PASSWORD` (secure_random), `REDIS_PASSWORD` (secure_random_or_null)
- Worker services (Horizon, Scheduler) build their own images from same Dockerfile
- Horizon depends on Redis being present

## Command usage

```bash
tuti stack:laravel                                        # Interactive
tuti stack:laravel my-app --mode=fresh                    # Fresh project
tuti stack:laravel my-app --mode=fresh --php=8.4          # Specific PHP version
tuti stack:laravel --mode=existing                        # Add to existing project
tuti stack:laravel my-app --mode=fresh --services=redis,meilisearch
```
