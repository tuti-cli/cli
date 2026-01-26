# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-01-26

### Added
- Initial release of Tuti CLI
- Stack-based project initialization system
- `tuti init` - Interactive project initialization with stack selection
- `tuti install` - Setup global ~/.tuti directory and configuration
- `tuti stack:laravel` - Laravel project scaffolding with Docker
  - Fresh installation mode (creates new Laravel project)
  - Apply to existing mode (adds Docker to existing Laravel project)
- `tuti stack:init` - Generic stack initialization (legacy)
- `tuti stack:manage` - Stack template management
  - List available stacks
  - Download stacks from remote repositories
  - Update cached stacks
  - Clear stack cache
- Service registry system with universal Docker service stubs
  - PostgreSQL, MySQL, MariaDB databases
  - Redis cache
  - Meilisearch, Typesense search engines
  - MinIO S3-compatible storage
  - Mailpit email testing
- Stack repository system
  - Stacks cached in ~/.tuti/stacks/
  - Auto-download from GitHub repositories
  - Offline support after initial download
- Docker Compose generation from service stubs
- Environment-specific overrides (dev, staging, production)
- Installation script for quick setup
- Comprehensive documentation

### Technical
- Stack installer interface and registry pattern
- Service stub loader with variable replacement
- Global configuration in ~/.tuti/
- Cross-platform support (Linux, macOS, Windows via WSL)
- PHAR packaging for easy distribution

[Unreleased]: https://github.com/tuti-cli/tuti-cli/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/tuti-cli/tuti-cli/releases/tag/v0.1.0
