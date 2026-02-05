---
name: wordpress-stack
description: WordPress stack installation and configuration
globs:
  - stubs/stacks/wordpress/**
  - app/Services/Stack/Installers/WordPressStackInstaller.php
  - app/Commands/Stack/WordPressCommand.php
---

# WordPress Stack Skill

## When to Use
- Installing new WordPress projects (Standard or Bedrock)
- Adding Docker configuration to existing WordPress projects
- Configuring WordPress with MariaDB or MySQL
- Understanding WordPress Docker architecture

## Installation Types

### Standard WordPress
- Traditional WordPress file structure
- Uses WP-CLI to download core files
- Classic wp-config.php with environment variables
- Full compatibility with all plugins

### Bedrock (Roots)
- Modern WordPress development
- Composer-based dependency management
- 12-factor app methodology
- Enhanced security configuration
- Structured directory layout (`web/`, `config/`)

## Stack Structure

```
stubs/stacks/wordpress/
├── stack.json                    # Stack manifest
├── docker-compose.yml            # Base configuration
├── docker-compose.dev.yml        # Development overlay
├── docker/
│   └── Dockerfile               # Multi-stage Dockerfile
├── environments/
│   └── .env.dev.example         # Development env template
├── scripts/
│   └── entrypoint-dev.sh        # Development entrypoint
├── services/
│   ├── mariadb.stub             # MariaDB configuration
│   └── mysql.stub               # MySQL configuration
└── templates/
    └── wp-config.php            # WordPress config template
```

## Key Configuration

### PHP Image
Uses `serversideup/php:8.3-fpm-apache` - Apache is recommended for WordPress due to:
- .htaccess compatibility
- Maximum plugin compatibility
- Built-in security configurations

### Database Options
- **MariaDB** (default) - MySQL-compatible, recommended
- **MySQL** - Oracle MySQL

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `WP_DEBUG` | Enable debug mode | `false` |
| `WP_TABLE_PREFIX` | Database table prefix | `wp_` |
| `WP_AUTH_KEY` | Authentication key | Generated |
| `WP_SECURE_AUTH_KEY` | Secure auth key | Generated |
| `WP_LOGGED_IN_KEY` | Logged-in key | Generated |
| `WP_NONCE_KEY` | Nonce key | Generated |

## Command Usage

```bash
# Fresh installation (interactive)
tuti stack:wordpress

# Fresh installation (non-interactive)
tuti stack:wordpress my-wp-site --mode=fresh --type=standard

# Bedrock installation
tuti stack:wordpress my-wp-site --mode=fresh --type=bedrock

# Apply to existing WordPress
tuti stack:wordpress --mode=existing
```

## Related Skills

- `docker-compose-generation` - For modifying compose files
- `environment-overlays` - For adding staging/production
- `service-stubs` - For adding new services

## Best Practices

1. **Use MariaDB** - Better performance, fully MySQL-compatible
2. **Generate salts** - Always use unique salts per installation
3. **Environment variables** - Never hardcode secrets in wp-config.php
4. **Apache over NGINX** - Maximum WordPress plugin compatibility
5. **Disable file editing** - Security best practice in Docker
