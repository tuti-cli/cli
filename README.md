# Tuti CLI

**An environment management and deployment tool that gets out of your way. ****

From local Docker to production deployment. One command. Zero config.

> **Requires Docker**

---

## ⚡️ Installation

### Via Composer [work in progress]
```bash
composer global require tuti-cli/cli
```

### Download Binary (Recommended) [work in progress]
Single executable with zero dependencies - no PHP installation required:

```bash
# macOS / Linux / Windows + WSL2
curl -sS https://tuti-cli.dev/install | bash

# Or download directly
wget https://github.com/tuti-cli/cli/releases/latest/download/tuti
chmod +x tuti
sudo mv tuti /usr/local/bin/
```
---

## 🚀 Quick Start

```bash
# Initialize your project
cd my-laravel-app
tuti init

# Start local environment
tuti local:start

# Deploy to production
tuti deploy production
```

---

## ✨ Future Features for Implementation

- [ ] **Unified Local + Remote Management**
Problem with existing tools:

Lando/DDEV/Spin = Local dev ONLY
You still need separate tools for deployment (Deployer, Envoyer, custom scripts)
No environment parity guarantees

Our Solution:

```
# Same tool, from local to production
$ env-tool local:start              # Start local environment
$ env-tool deploy staging           # Deploy to staging
$ env-tool deploy production        # Deploy to production

# Environment sync
$ env-tool env:sync staging         # Pull staging config to local
$ env-tool env:compare local production  # See differences
```
- [ ] **Multi-Project Intelligence**
Problem: Context switching is painful

```
# With Lando/DDEV
$ cd ~/projects/app1
$ lando start
$ cd ~/projects/app2
$ lando start
$ cd ~/projects/app3
$ lando start
# Need to remember which project needs what
```

Our Solution:
```
# From anywhere, manage everything
$ env-tool projects:list

╭────────────────────────────────────────────────────────────╮
│ Active Projects                                             │
├──────────────────┬─────────┬──────────┬────────────────────┤
│ Project          │ Status  │ Location │ Last Activity      │
├──────────────────┼─────────┼──────────┼────────────────────┤
│ 🟢 my-laravel-app │ Running │ Local    │ 2 mins ago         │
│ 🔵 frontend-react │ Stopped │ Local    │ 2 hours ago        │
│ 🟢 api-service    │ Running │ Local    │ Active now         │
│ 🟡 staging-app    │ Deployed│ Staging  │ Deployed 2d ago    │
│ 🔴 old-project    │ Error   │ Local    │ Port conflict      │
╰──────────────────┴─────────┴──────────┴────────────────────╯

# Quick actions from anywhere
$ env-tool switch my-laravel-app    # Switch context
$ env-tool start frontend-react     # Start specific project
$ env-tool logs api-service         # View logs
$ env-tool deploy my-laravel-app production  # Deploy
```

- [ ] **Intelligent Port Management**
      
Problem with Lando/DDEV:
```
# You manually configure ports
# Conflicts happen frequently
# Need to track which ports are used
```

Our Solution:

```
$ env-tool local:start

⚠ Port 3306 (MySQL) is already in use by 'old-project'

Options:
  1. Stop 'old-project' and use 3306
  2. Use alternative port 3307
  3. View all port allocations
  
Your choice: 2

✓ MySQL configured on port 3307
✓ Updated .env: DB_PORT=3307
```

Automatic port allocation:

```
{
  "port_management": {
    "strategy": "auto",
    "base_ports": {
      "mysql": 3306,
      "redis": 6379,
      "postgres": 5432
    },
    "allocation": {
      "my-laravel-app": {
        "mysql": 3306,
        "redis": 6379
      },
      "frontend-react": {
        "mysql": 3307,  // Auto-incremented
        "redis": 6380
      }
    }
  }
}
```

 - [ ] Multi-App Deployment Orchestration
       
Use Case: You have:

Laravel API backend
React frontend
Admin dashboard
Worker services

Problem with current tools: No tool handles this. You need:

Separate deployment scripts
Manual coordination
Dependency management yourself

Our Solution:
```
$ env-tool deploy:multi production

╭─────────────────────────────────────────╮
│ Multi-App Deployment                     │
├─────────────────────────────────────────┤
│ Select apps to deploy (space to select) │
│                                          │
│ [✓] api-backend                          │
│ [✓] frontend                             │
│ [✓] admin-dashboard                      │
│ [ ] worker-service (no changes)          │
╰─────────────────────────────────────────╯

Detected dependencies:
  frontend → api-backend (API calls)
  admin-dashboard → api-backend (API calls)

Deployment order:
  1. api-backend (others depend on it)
  2. frontend & admin-dashboard (parallel)

Continue? (y/N): y

⚡ Deploying api-backend...
  ✓ Tests passed
  ✓ Deployed successfully

⚡ Deploying frontend & admin-dashboard in parallel...
  ⠋ frontend: Building assets...
  ⠋ admin-dashboard: Running migrations...
  ✓ frontend: Deployed
  ✓ admin-dashboard: Deployed

✓ Multi-app deployment complete!
```

- [ ] Environment Templates & Blueprints
      
Problem: Setting up new projects is tedious
Our Solution:

```
$ env-tool project:create --from-template

Available templates:
  1. Laravel API (PHP 8.3, MySQL, Redis, Queue)
  2. Laravel + Vue (Full-stack SPA)
  3. Laravel Microservice (Minimal)
  4. React SPA (Vite + Tailwind)
  5. Static Site (Nginx)
  6. Custom...

Select template: 1

Project name: my-new-api
Git repository: git@github.com:user/my-new-api.git

✓ Created project structure
✓ Generated Docker configs
✓ Created environments (local, staging, production)
✓ Initialized git repository
✓ Installed dependencies
✓ Generated application key
✓ Ready to code!

Next steps:
  $ env-tool switch my-new-api
  $ env-tool local:start
```

- [ ]  Smart Environment Variables

Problem: Managing environment variables across multiple apps is chaos
Our Solution:

```
$ env-tool env:wizard

Let's configure your environments...

Q: Database credentials
  → Same for all environments? (y/N): n
  
  Local:
    DB_HOST: mysql (auto-filled from Docker)
    DB_PORT: 3306 (auto-detected)
    DB_DATABASE: my_app
    DB_USERNAME: root
    DB_PASSWORD: [generate random] ✓
  
  Staging:
    DB_HOST: staging-db.internal
    DB_PORT: 3306
    DB_DATABASE: staging_my_app
    DB_USERNAME: staging_user
    DB_PASSWORD: [use existing secret] ✓
    
Q: External services (Stripe, AWS, etc.)
  → Share across all environments? (Y/n): n
  
  Staging:
    STRIPE_KEY: sk_test_... (test mode)
  
  Production:
    STRIPE_KEY: sk_live_... (live mode) [encrypted]

✓ Configuration complete!
✓ Validated all environments
✓ Encrypted sensitive data
```







---

## 📖 Commands

```bash
# Local Development
tuti local:start        # Start Docker environment
tuti local:stop         # Stop environment
tuti local:logs         # Stream logs
tuti local:shell        # SSH into container

# Environments
tuti env:list           # List all environments
tuti env:edit           # Interactive editor
tuti env:compare        # Diff environments

# Deployment
tuti deploy <env>       # Deploy to environment
tuti deploy:rollback    # Undo deployment
tuti deploy:history     # View history

# Dashboard
tuti dashboard          # Live status overview
```

---

## 🎨 Why Tuti?

**Beautiful UI** - Built with Termwind and Laravel Prompts, every command feels modern  
**Intelligent Defaults** - Auto-detects Laravel, Node.js, or generic PHP  
**Single Binary** - Download and run. No dependencies  
**Multi-Project** - Switch projects instantly with zero conflicts

---

## 🛠️ Development

### Building from Source

```bash
git clone https://github.com/tuti-cli/cli.git
cd cli
composer install

# Run locally
php tuti

# Build PHAR
composer build

# Build binary
composer build:binary
```

### Build Configuration

The project uses PHPacker to create standalone binaries:

```bash
# Install PHPacker
composer require phpacker/phpacker --dev

# Build for current platform
vendor/bin/phpacker build

# Build for specific platform
vendor/bin/phpacker build --target=linux-x64
vendor/bin/phpacker build --target=macos-arm64
```

Available targets:
- `linux-x64` - Linux (64-bit)
- `linux-arm64` - Linux ARM (64-bit)
- `macos-x64` - macOS Intel (64-bit)
- `macos-arm64` - macOS Apple Silicon

Binaries are output to `builds/` directory.

---

## 🤝 Contributing

We welcome contributions! See [CONTRIBUTING.md](CONTRIBUTING.md).

```bash
composer install
composer test
composer lint
```

---

## 📝 License

MIT License - see [LICENSE.md](LICENSE.md)

---

**[Documentation](https://tuti-cli.dev)** · **[Releases](https://github.com/tuti-cli/cli/releases)** · **[Discord](https://discord.gg/tuti-cli)**
