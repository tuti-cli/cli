# Tuti CLI

[![Tests](https://github.com/tuti-cli/cli/actions/workflows/tests.yml/badge.svg)](https://github.com/tuti-cli/cli/actions/workflows/tests.yml)
[![Latest Release](https://img.shields.io/github/v/release/tuti-cli/cli?label=version)](https://github.com/tuti-cli/cli/releases/latest)
[![License](https://img.shields.io/github/license/tuti-cli/cli)](LICENSE.md)

**Multi-framework Docker environment management.** One command to set up local development with zero dependencies.

Works with **Laravel** and **WordPress**. More frameworks coming soon.

> **Note:** This project is under active development.

---

## Why Tuti?

- **Self-contained binary** - No PHP, no Composer, no dependencies. Download and run.
- **Docker-first** - All services run in containers. Your host system stays clean.
- **Multi-framework** - Laravel, WordPress, and more with consistent commands.
- **Production-ready configs** - Pre-configured Docker setups with databases, cache, search, and queues.
- **One command setup** - `tuti stack:laravel myapp` and you're ready to code.

---

## Installation

### Quick Install (Recommended)

```bash
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash
```

Downloads a self-contained binary (~60MB) with embedded PHP runtime. Works on Linux, macOS, and WSL.

**Options:**

```bash
# Install specific version
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | TUTI_VERSION=0.2.0 bash

# Custom install location
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | TUTI_INSTALL_DIR=/usr/local/bin bash
```

### Manual Download

Download the binary for your platform:

| Platform | Architecture | Download |
|----------|--------------|----------|
| Linux | x64 | `tuti-linux-x64` |
| Linux | ARM | `tuti-linux-arm` |
| macOS | Apple Silicon | `tuti-mac-arm` |
| macOS | Intel | `tuti-mac-x64` |

**Linux (x64):**
```bash
curl -fsSL https://github.com/tuti-cli/cli/releases/latest/download/tuti-linux-x64 -o tuti
chmod +x tuti && sudo mv tuti /usr/local/bin/
```

**Linux (ARM):**
```bash
curl -fsSL https://github.com/tuti-cli/cli/releases/latest/download/tuti-linux-arm -o tuti
chmod +x tuti && sudo mv tuti /usr/local/bin/
```

**macOS (Apple Silicon):**
```bash
curl -fsSL https://github.com/tuti-cli/cli/releases/latest/download/tuti-mac-arm -o tuti
chmod +x tuti && sudo mv tuti /usr/local/bin/
```

**macOS (Intel):**
```bash
curl -fsSL https://github.com/tuti-cli/cli/releases/latest/download/tuti-mac-x64 -o tuti
chmod +x tuti && sudo mv tuti /usr/local/bin/
```

### Verify Installation

```bash
tuti --version
```

---

## Quick Start

Get up and running in under 5 minutes.

### Laravel

**Step 1: Create project** (30 seconds)
```bash
tuti stack:laravel myapp
cd myapp
```

**Step 2: Initialize global infrastructure** (1 minute)
```bash
tuti infra:start
```

**Step 3: Start development environment** (2 minutes)
```bash
tuti local:start
```

Your app is now running at `http://myapp.localhost`

### WordPress

**Step 1: Create project** (30 seconds)
```bash
tuti stack:wordpress myblog
cd myblog
```

**Step 2: Initialize global infrastructure** (1 minute)
```bash
tuti infra:start
```

**Step 3: Start development environment** (2 minutes)
```bash
tuti local:start
```

**Step 4: (Optional) Auto-install WordPress** (1 minute)
```bash
tuti wp:setup
```

Your WordPress site is now running at `http://myblog.localhost`

### Add Docker to Existing Project

Already have a Laravel or WordPress project? Add Docker in seconds:

```bash
cd my-existing-laravel-app
tuti stack:laravel --mode=existing

# Or for WordPress
cd my-existing-wordpress-site
tuti stack:wordpress --mode=existing
```

---

## System Requirements

### Required

| Requirement | Minimum Version | Notes |
|-------------|-----------------|-------|
| Docker | 20.10+ | Docker Engine or Docker Desktop |
| Docker Compose | v2 | Usually bundled with Docker |

### Supported Platforms

| Platform | Support Level | Notes |
|----------|---------------|-------|
| Linux (x64) | Full | Ubuntu, Debian, Fedora, Arch |
| Linux (ARM) | Full | Raspberry Pi, ARM servers |
| macOS (Apple Silicon) | Full | M1/M2/M3 Macs |
| macOS (Intel) | Full | Intel-based Macs |
| WSL 2 | Full | Windows Subsystem for Linux |
| Windows (native) | Not supported | Use WSL 2 |

### Check Your System

Run the diagnostic command to verify your setup:

```bash
tuti doctor
```

This checks Docker, Docker Compose, and your system configuration.

---

## Commands Reference

### Global Setup

| Command | Description |
|---------|-------------|
| `tuti install` | Initialize global `~/.tuti` directory |
| `tuti doctor` | Check system requirements and diagnose issues |
| `tuti doctor --fix` | Attempt to fix issues automatically |

### Infrastructure

| Command | Description |
|---------|-------------|
| `tuti infra:start` | Start global Traefik reverse proxy |
| `tuti infra:stop` | Stop global infrastructure |
| `tuti infra:restart` | Restart global infrastructure |
| `tuti infra:status` | Check infrastructure status |

### Project Stacks

| Command | Description |
|---------|-------------|
| `tuti stack:laravel [name]` | Create new Laravel project with Docker |
| `tuti stack:laravel --mode=existing` | Add Docker to existing Laravel project |
| `tuti stack:wordpress [name]` | Create new WordPress project with Docker |
| `tuti stack:wordpress --mode=existing` | Add Docker to existing WordPress project |
| `tuti stack:init` | Initialize project from current directory |
| `tuti stack:manage` | Manage stack templates |

### Local Development

| Command | Description |
|---------|-------------|
| `tuti local:start` | Start project containers |
| `tuti local:stop` | Stop project containers |
| `tuti local:restart` | Restart project containers |
| `tuti local:status` | Show container status |
| `tuti local:logs` | View container logs |
| `tuti local:logs app` | View logs for specific service |
| `tuti local:rebuild` | Rebuild containers after config changes |

### WordPress

| Command | Description |
|---------|-------------|
| `tuti wp:setup` | Auto-install WordPress (WP-CLI) |

---

## Example Workflows

### Starting a New Laravel Project

```bash
# 1. Create the project
tuti stack:laravel shop

# 2. Navigate to project
cd shop

# 3. Start global infrastructure (first time only)
tuti infra:start

# 4. Start development
tuti local:start

# 5. Open in browser
# http://shop.localhost
```

### Adding Services to Your Project

Edit `docker-compose.dev.yml` in your project to add services like Redis, Postgres, or Meilisearch. Stack templates include pre-configured service stubs.

### WordPress Development with Auto-Install

```bash
# 1. Create WordPress project
tuti stack:wordpress client-site

# 2. Navigate and start
cd client-site
tuti infra:start  # if not running
tuti local:start

# 3. Auto-install WordPress (creates wp-config.php, installs core)
tuti wp:setup

# 4. Open in browser
# http://client-site.localhost
```

---

## How It Works

1. **Self-contained binary** - Built with phpacker, includes embedded PHP runtime. No PHP installation needed on your machine.

2. **Global infrastructure** - A single Traefik reverse proxy handles all projects. Run `tuti infra:start` once and all your projects share it.

3. **Stack templates** - Pre-configured Docker Compose files for each framework. Includes databases, cache, queues, and search services.

4. **Project isolation** - Each project gets its own Docker network and volumes. Projects don't interfere with each other.

5. **Development workflow** - `local:start`, `local:stop`, `local:logs` work the same across all frameworks.

---

## Uninstall

```bash
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/uninstall.sh | bash
```

Remove everything including data:
```bash
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/uninstall.sh | bash -s -- --purge
```

Or manually:
```bash
rm ~/.tuti/bin/tuti            # or /usr/local/bin/tuti
rm -rf ~/.tuti                 # config, logs, cache
```

---

## Development

Want to contribute or build from source?

```bash
# Clone and setup
git clone https://github.com/tuti-cli/cli.git
cd cli
composer install

# Run in Docker
make up
make shell
php tuti --version

# Build PHAR (required before binary)
make build-phar
make test-phar

# Build native binaries (uses phpacker)
make build-binary

# Or build for specific platform
make build-binary-linux
make build-binary-mac

# Test binary locally (no PHP required)
make test-binary

# Install locally for testing
make install-local
~/.tuti/bin/tuti --version
```

See [CLAUDE.md](CLAUDE.md) for development guidelines and architecture patterns.

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## License

MIT License - see [LICENSE.md](LICENSE.md)
