# Tuti CLI - Architecture & Concepts

## 🎯 Core Concept

Tuti CLI works in **two modes**:

1. **Global Mode** - Manage multiple projects from anywhere
2. **Project Mode** - Work within a specific project directory

### How It Works

```bash
# Anywhere on your system
tuti projects:list          # See all registered projects
tuti switch my-app          # Switch to a project
tuti dashboard              # Overview of all projects

# Inside a project directory
cd ~/projects/my-laravel-app
tuti init                   # Initialize project
tuti local:start            # Start local environment
tuti deploy production      # Deploy this project
```

---

## 📂 Global Configuration Structure

**Location:** `~/.tuti/` (user's home directory)

```
~/.tuti/
├── config.json                 # Global configuration
├── projects/                   # Registry of all projects
│   ├── my-laravel-app.json
│   ├── my-react-app.json
│   └── my-api.json
├── cache/                      # Cached data
│   ├── docker-images.json
│   └── port-allocations.json
├── secrets/                    # Encrypted secrets vault
│   ├── master.key
│   └── vault.enc
└── logs/                       # Global logs
    ├── deployments.log
    └── errors.log
```

### `~/.tuti/config.json`

```json
{
  "version": "1.0.0",
  "default_project": "my-laravel-app",
  "settings": {
    "auto_start_docker": true,
    "confirm_production_deploy": true,
    "log_level": "info",
    "theme": "dark"
  },
  "docker": {
    "network": "tuti-network",
    "port_range": {
      "start": 3000,
      "end": 9000
    }
  },
  "projects": [
    {
      "name": "my-laravel-app",
      "path": "/Users/you/projects/my-laravel-app",
      "type": "laravel",
      "status": "active",
      "last_accessed": "2025-10-11T10:30:00Z"
    },
    {
      "name": "my-react-app",
      "path": "/Users/you/projects/my-react-app",
      "type": "node",
      "status": "inactive",
      "last_accessed": "2025-10-09T15:20:00Z"
    }
  ]
}
```

---

## 📁 Project Configuration Structure

**Location:** `{project-root}/.tuti/`

```
my-laravel-app/
├── .tuti/                      # Tuti configuration (gitignored)
│   ├── config.yml              # Project configuration
│   ├── .state                  # Current state (running/stopped)
│   │
│   ├── environments/           # Environment configurations
│   │   ├── local.env
│   │   ├── staging.env
│   │   └── production.env
│   │
│   ├── docker/                 # Docker configurations
│   │   ├── docker-compose.yml
│   │   ├── Dockerfile.app
│   │   ├── nginx/
│   │   │   └── default.conf
│   │   └── php/
│   │       └── custom.ini
│   │
│   ├── deploy/                 # Deployment configurations
│   │   ├── staging.yml
│   │   ├── production.yml
│   │   └── hooks/
│   │       ├── pre-deploy.sh
│   │       └── post-deploy.sh
│   │
│   ├── backups/                # Local backups
│   │   ├── database/
│   │   │   ├── 2025-10-11-db.sql
│   │   │   └── 2025-10-10-db.sql
│   │   └── env/
│   │       └── production-2025-10-11.env.backup
│   │
│   ├── history/                # Deployment history
│   │   └── deployments.json
│   │
│   └── cache/                  # Temporary cache
│       ├── docker-status.json
│       └── last-deploy.json
│
├── .tuti.yml                   # Project manifest (committed to git)
├── .gitignore                  # Add .tuti/ (except .tuti.yml)
└── ... (your project files)
```

---

## 🔧 `.tuti.yml` - Project Manifest

**This file IS committed to git** - it's the project configuration template.

```yaml
# .tuti.yml
project:
  name: my-laravel-app
  type: laravel
  version: 1.0.0
  description: My awesome Laravel application

# Local development configuration
local:
  # Docker services
  services:
    app:
      image: php:8.3-fpm-alpine
      working_dir: /var/www
      volumes:
        - ./:/var/www
      ports:
        - "${APP_PORT:-8000}:8000"
    
    mysql:
      image: mysql:8.0
      environment:
        MYSQL_ROOT_PASSWORD: root
        MYSQL_DATABASE: "${DB_DATABASE:-laravel}"
      ports:
        - "${DB_PORT:-3306}:3306"
      volumes:
        - mysql-data:/var/lib/mysql
    
    redis:
      image: redis:7-alpine
      ports:
        - "${REDIS_PORT:-6379}:6379"
    
    mailhog:
      image: mailhog/mailhog
      ports:
        - "1025:1025"
        - "8025:8025"
  
  # Volumes
  volumes:
    - mysql-data
    - redis-data
  
  # Setup scripts (run on first start)
  setup:
    - composer install
    - php artisan key:generate
    - php artisan migrate
    - php artisan db:seed
  
  # Custom commands
  commands:
    install: composer install
    migrate: php artisan migrate
    seed: php artisan db:seed
    test: php artisan test

# Environment templates
environments:
  local:
    required:
      - APP_NAME
      - APP_ENV
      - APP_KEY
      - DB_CONNECTION
      - DB_HOST
      - DB_PORT
      - DB_DATABASE
    
  staging:
    required:
      - APP_NAME
      - APP_ENV
      - APP_KEY
      - DB_CONNECTION
      - DB_HOST
      - DB_DATABASE
      - DB_USERNAME
      - DB_PASSWORD
    
  production:
    required:
      - APP_NAME
      - APP_ENV
      - APP_KEY
      - DB_CONNECTION
      - DB_HOST
      - DB_DATABASE
      - DB_USERNAME
      - DB_PASSWORD
      - MAIL_HOST
      - MAIL_USERNAME
      - MAIL_PASSWORD

# Deployment configuration
deploy:
  staging:
    type: ssh
    host: staging.example.com
    user: deploy
    path: /var/www/staging
    branch: develop
    
    # Pre-deployment checks
    checks:
      - git_status
      - tests
      - lint
    
    # Deployment steps
    steps:
      - backup
      - pull_code
      - install_dependencies
      - run_migrations
      - build_assets
      - clear_cache
      - restart_services
    
    # Post-deployment
    post_deploy:
      - health_check
      - notify_team
  
  production:
    type: ssh
    host: production.example.com
    user: deploy
    path: /var/www/production
    branch: main
    
    # Require approval
    require_approval: true
    
    # Zero-downtime deployment
    strategy: blue-green
    
    checks:
      - git_status
      - tests
      - lint
      - security_audit
    
    steps:
      - backup
      - pull_code
      - install_dependencies
      - run_migrations
      - build_assets
      - clear_cache
      - restart_services
    
    post_deploy:
      - health_check
      - smoke_tests
      - notify_team
      - slack_notification

# Multi-app configuration (optional)
apps:
  - name: api
    path: ./api
    type: laravel
    
  - name: frontend
    path: ./frontend
    type: node
    depends_on:
      - api
```

---

## 🚀 `tuti init` Command Flow

### Step 1: Detection & Analysis

```bash
$ cd my-laravel-app
$ tuti init
```

**What happens:**

1. **Check if already initialized**
   ```
   ❌ If .tuti/ exists → warn user
   ✅ If not → continue
   ```

2. **Detect project type**
   - Check for `composer.json` → PHP project
   - Check for `laravel/framework` → Laravel
   - Check for `package.json` → Node.js
   - Check for `requirements.txt` → Python
   
3. **Analyze project structure**
   - Find `.env` file
   - Detect database (MySQL, PostgreSQL, etc.)
   - Detect caching (Redis, Memcached)
   - Detect queue system

### Step 2: Interactive Setup

```
╭────────────────────────────────────────────────╮
│           🚀 TUTI CLI INITIALIZATION           │
╰────────────────────────────────────────────────╯

✓ Detected: Laravel 11.x project

┌─────────────────────────────────────────────────┐
│ PROJECT CONFIGURATION                           │
└─────────────────────────────────────────────────┘

Project name: my-laravel-app
Description: My awesome Laravel app

┌─────────────────────────────────────────────────┐
│ LOCAL ENVIRONMENT SERVICES                      │
└─────────────────────────────────────────────────┘

Select services to include (space to select):
  [x] MySQL 8.0
  [x] Redis 7
  [x] Mailhog
  [ ] PostgreSQL 15
  [ ] Elasticsearch
  [ ] RabbitMQ

┌─────────────────────────────────────────────────┐
│ PORT CONFIGURATION                              │
└─────────────────────────────────────────────────┘

✓ Port 3306 available for MySQL
✓ Port 6379 available for Redis
✓ Port 8025 available for Mailhog

┌─────────────────────────────────────────────────┐
│ REMOTE ENVIRONMENTS                             │
└─────────────────────────────────────────────────┘

Configure staging environment now? (Y/n): y

Staging host: staging.example.com
Staging user: deploy
Staging path: /var/www/staging
SSH key path: ~/.ssh/id_rsa

Configure production environment now? (y/N): n
```

### Step 3: Generate Files

```
→ Creating .tuti directory ✓
→ Generating .tuti.yml ✓
→ Creating environments/ ✓
→ Copying .env to local.env ✓
→ Creating staging.env template ✓
→ Creating production.env template ✓
→ Generating docker-compose.yml ✓
→ Generating Dockerfile ✓
→ Generating nginx config ✓
→ Creating deployment configs ✓
→ Updating .gitignore ✓
→ Registering project globally ✓

╭────────────────────────────────────────────────╮
│         ✓ INITIALIZATION COMPLETE!             │
╰────────────────────────────────────────────────╯

Next steps:
  1. Start local: tuti local:start
  2. Edit config: tuti env:edit local
  3. View status: tuti status
```

### Step 4: Global Registration

Tuti adds project to `~/.tuti/config.json`:

```json
{
  "projects": [
    {
      "name": "my-laravel-app",
      "path": "/Users/you/projects/my-laravel-app",
      "type": "laravel",
      "status": "initialized",
      "created_at": "2025-10-11T10:30:00Z",
      "ports": {
        "mysql": 3306,
        "redis": 6379,
        "mailhog": 8025
      }
    }
  ]
}
```

---

## 🔐 Environment Variables Management

### Structure

```
.tuti/environments/
├── local.env           # Local development
├── staging.env         # Staging server
├── production.env      # Production server
└── .env.template       # Template for new environments
```

### Example: `local.env`

```env
# Application
APP_NAME="My Laravel App"
APP_ENV=local
APP_KEY=base64:xxxxx
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=root

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
```

### Sensitive Data Handling

Sensitive variables (passwords, API keys) are:
1. **Encrypted** in `.tuti/secrets/vault.enc`
2. **Referenced** in environment files with placeholders
3. **Injected** at runtime

Example:

```env
# staging.env
DB_PASSWORD={secret:staging_db_password}
AWS_SECRET={secret:staging_aws_secret}
```

Encrypted vault:
```json
{
  "staging_db_password": "encrypted_value_here",
  "staging_aws_secret": "encrypted_value_here"
}
```

---

## 🐳 Docker Configuration

### Generated `docker-compose.yml`

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: .tuti/docker/Dockerfile.app
    container_name: ${PROJECT_NAME}_app
    working_dir: /var/www
    volumes:
      - ./:/var/www
    networks:
      - tuti-network
    environment:
      - CONTAINER_ROLE=app
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    container_name: ${PROJECT_NAME}_mysql
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
    ports:
      - "${DB_PORT:-3306}:3306"
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - tuti-network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 3s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: ${PROJECT_NAME}_redis
    ports:
      - "${REDIS_PORT:-6379}:6379"
    volumes:
      - redis-data:/var/lib/redis
    networks:
      - tuti-network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5

  mailhog:
    image: mailhog/mailhog
    container_name: ${PROJECT_NAME}_mailhog
    ports:
      - "1025:1025"
      - "8025:8025"
    networks:
      - tuti-network

networks:
  tuti-network:
    driver: bridge
    name: ${PROJECT_NAME}_network

volumes:
  mysql-data:
    name: ${PROJECT_NAME}_mysql_data
  redis-data:
    name: ${PROJECT_NAME}_redis_data
```

---

## 🔄 State Management

### `.tuti/.state`

Tracks current project state:

```json
{
  "status": "running",
  "started_at": "2025-10-11T10:35:00Z",
  "services": {
    "app": {
      "status": "running",
      "container_id": "abc123",
      "ports": {
        "8000": "8000"
      }
    },
    "mysql": {
      "status": "running",
      "container_id": "def456",
      "ports": {
        "3306": "3306"
      },
      "health": "healthy"
    },
    "redis": {
      "status": "running",
      "container_id": "ghi789",
      "ports": {
        "6379": "6379"
      },
      "health": "healthy"
    }
  },
  "environment": "local",
  "last_deploy": null
}
```

---

## 📊 Deployment History

### `.tuti/history/deployments.json`

```json
{
  "deployments": [
    {
      "id": "deploy-20251011-103000",
      "environment": "production",
      "status": "success",
      "started_at": "2025-10-11T10:30:00Z",
      "completed_at": "2025-10-11T10:32:34Z",
      "duration": "2m 34s",
      "commit": "a3b4c5d",
      "branch": "main",
      "deployed_by": "john@example.com",
      "changes": {
        "files": 12,
        "additions": 234,
        "deletions": 45
      },
      "steps": [
        {
          "name": "backup",
          "status": "success",
          "duration": "10s"
        },
        {
          "name": "pull_code",
          "status": "success",
          "duration": "15s"
        },
        {
          "name": "install_dependencies",
          "status": "success",
          "duration": "45s"
        }
      ]
    }
  ]
}
```

---

## 🎯 Key Concepts Summary

### 1. **Two-Level Configuration**
- **Global** (`~/.tuti/`): All projects, settings, secrets
- **Project** (`.tuti/`): Project-specific config

### 2. **Committed vs Ignored**
- **Committed** (`.tuti.yml`): Project template, everyone uses
- **Ignored** (`.tuti/`): Local state, environment values, secrets

### 3. **Environment Hierarchy**
```
.tuti.yml (template)
  ↓
.tuti/environments/local.env (local values)
  ↓
.tuti/secrets/vault.enc (encrypted secrets)
  ↓
Runtime (merged configuration)
```

### 4. **Port Management**
- Global registry prevents conflicts
- Auto-increment when ports are taken
- Per-project allocation stored globally

### 5. **State Tracking**
- Current status (running/stopped)
- Service health
- Last deployment
- Docker container IDs
