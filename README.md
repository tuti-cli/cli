# Tuti CLI

[![Tests](https://github.com/tuti-cli/cli/actions/workflows/tests.yml/badge.svg)](https://github.com/tuti-cli/cli/actions/workflows/tests.yml)

**Docker-based environment management for Laravel projects.**

One command to set up local development. **Zero dependencies required.**

> **Note:** This project is under active development.

---

## Installation

### Quick Install

```bash
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash
```

No PHP, no dependencies. Downloads a self-contained binary (~60MB) with embedded PHP runtime.

Options:

```bash
# Install specific version
curl -fsSL ... | TUTI_VERSION=0.2.0 bash

# Custom install location
curl -fsSL ... | TUTI_INSTALL_DIR=/usr/local/bin bash
```

### Manual Install

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

### Verify

```bash
tuti --version
```

---

## Quick Start

```bash
# Create new Laravel project with Docker
tuti stack:laravel myapp

# Or add Docker to existing Laravel project
cd my-existing-laravel-app
tuti stack:laravel --mode=existing

# Start local environment
tuti local:start
```

---

## Requirements

- **Docker** - Required for running containers
- **curl** or **wget** - For installation

That's it! The tuti binary includes everything needed to run.

---

## Commands

```bash
tuti install           # Set up global ~/.tuti directory
tuti stack:laravel     # Initialize Laravel project with Docker
tuti stack:manage      # Manage stack templates
tuti local:start       # Start Docker environment
tuti local:stop        # Stop environment
```

---

## How It Works

1. **Truly self-contained** - Binary includes embedded PHP runtime (built with phpacker)
2. **Docker-first** - All development services run in containers
3. **Stack templates** - Pre-configured Docker setups for Laravel, with databases, cache, etc.
4. **Single executable** - Just download and run, no extraction needed

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

```bash
git clone https://github.com/tuti-cli/cli.git
cd cli
composer install

# Run in Docker
make up
make shell
php tuti --version

# Build PHAR (required first)
make build-phar
make test-phar

# Build binaries for all platforms (uses phpacker)
make build-binary

# Or build for specific platform
make build-binary-linux
make build-binary-mac

# Test binary locally (no PHP required!)
make test-binary

# Install locally to test
make install-local
~/.tuti/bin/tuti --version

# Release (after testing passes)
make version-bump V=0.1.0
git add . && git commit -m "Release v0.1.0"
git tag -a v0.1.0 -m "Release v0.1.0"
git push origin main --tags
```

---

## License

MIT License - see [LICENSE.md](LICENSE.md)
