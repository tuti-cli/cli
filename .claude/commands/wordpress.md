Work on the WordPress stack: $ARGUMENTS

## Context -- read these files first

1. `stubs/stacks/wordpress/stack.json` - stack manifest (types: standard, bedrock)
2. `app/Services/Stack/Installers/WordPressStackInstaller.php` - installer implementation
3. `app/Commands/Stack/WordPressCommand.php` - main command (stack:wordpress)
4. `app/Commands/Stack/WpSetupCommand.php` - auto-setup command (wp:setup)
5. `stubs/stacks/wordpress/docker-compose.yml` + `docker-compose.dev.yml` - compose config
6. `stubs/stacks/wordpress/docker/Dockerfile` - PHP 8.3 FPM-Apache image
7. `stubs/stacks/wordpress/templates/wp-config.php` - config template
8. `stubs/stacks/wordpress/services/registry.json` - available services

## Installation types

**Standard WordPress** - Traditional file structure, WP-CLI download, classic wp-config.php
**Bedrock (Roots)** - Composer-based, 12-factor methodology, `web/` + `config/` layout

## Stack file structure

```
stubs/stacks/wordpress/
├── stack.json
├── docker-compose.yml
├── docker-compose.dev.yml
├── docker/Dockerfile               # serversideup/php:8.3-fpm-apache
├── templates/wp-config.php
├── environments/.env.dev.example
├── scripts/entrypoint-dev.sh
└── services/
    ├── registry.json
    ├── databases/mysql.stub         # MySQL
    ├── databases/mariadb.stub       # MariaDB (default, recommended)
    ├── cache/redis.stub
    ├── cli/wpcli.stub               # WP-CLI container
    ├── mail/mailpit.stub
    └── storage/minio.stub
```

## Key details

- PHP image: `serversideup/php:8.3-fpm-apache` (Apache for .htaccess + plugin compatibility)
- Database: MariaDB (default) or MySQL
- Security salts: Auto-generated per installation (WP_AUTH_KEY, WP_SECURE_AUTH_KEY, WP_LOGGED_IN_KEY, WP_NONCE_KEY + their salts)
- WP-CLI included as separate container via `cli/wpcli.stub`
- Disable file editing in Docker: `DISALLOW_FILE_EDIT`

## Command usage

```bash
tuti stack:wordpress                                     # Interactive
tuti stack:wordpress my-site --mode=fresh --type=standard
tuti stack:wordpress my-site --mode=fresh --type=bedrock
tuti stack:wordpress --mode=existing                     # Add to existing project
tuti wp:setup                                            # Auto-setup after containers start
tuti wp:setup --force                                    # Force re-setup
```
