# Tuti CLI

**Docker-based environment management for Laravel projects.**

One command to set up local development. Zero PHP installation required.

> **Note:** This project is under active development.

---

## Installation

### Quick Install

```bash
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash
```

This downloads a self-contained binary - **no PHP installation required** on your system.

### Manual Install

**Linux (x64):**
```bash
curl -fsSL https://github.com/tuti-cli/cli/releases/latest/download/tuti-linux-amd64 -o tuti
chmod +x tuti
sudo mv tuti /usr/local/bin/
tuti install
```

**Linux (ARM64):**
```bash
curl -fsSL https://github.com/tuti-cli/cli/releases/latest/download/tuti-linux-arm64 -o tuti
chmod +x tuti
sudo mv tuti /usr/local/bin/
tuti install
```

**macOS (Apple Silicon):**
```bash
curl -fsSL https://github.com/tuti-cli/cli/releases/latest/download/tuti-darwin-arm64 -o tuti
chmod +x tuti
sudo mv tuti /usr/local/bin/
tuti install
```

**macOS (Intel):**
```bash
curl -fsSL https://github.com/tuti-cli/cli/releases/latest/download/tuti-darwin-amd64 -o tuti
chmod +x tuti
sudo mv tuti /usr/local/bin/
tuti install
```

### Verify Installation

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

That's it! PHP runs inside Docker containers, not on your machine.

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

1. **No PHP on your machine** - Tuti is a self-contained binary with embedded PHP runtime
2. **Docker-first** - All development services run in containers
3. **Stack templates** - Pre-configured Docker setups for Laravel, with databases, cache, etc.

---

## Uninstall

```bash
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash -s -- --uninstall
```

Or manually:
```bash
rm /usr/local/bin/tuti
rm -rf ~/.tuti
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

# Build PHAR
make build-phar
```

---

## License

MIT License - see [LICENSE.md](LICENSE.md)
