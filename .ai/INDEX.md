# Tuti CLI - AI Guidelines Index

> AI-assisted development guidelines for Tuti CLI (Laravel Zero)

## Quick Reference

| Category | Path | Purpose |
|----------|------|---------|
| Core Architecture | `guidelines/core/` | Project structure, coding standards |
| Laravel Zero | `guidelines/laravel-zero/` | Framework-specific patterns |
| Skills | `skills/` | On-demand domain knowledge |

## Guidelines

### Core (`guidelines/core/`)
- **[architecture.md](guidelines/core/architecture.md)** - Project structure and patterns
- **[coding-standards.md](guidelines/core/coding-standards.md)** - PHP/Laravel conventions

### Laravel Zero (`guidelines/laravel-zero/`)
- **[commands.md](guidelines/laravel-zero/commands.md)** - Console command development  
- **[testing.md](guidelines/laravel-zero/testing.md)** - Testing with Pest

### Tuti CLI (`guidelines/tuti-cli/`)
- **[stack-system.md](guidelines/tuti-cli/stack-system.md)** - Stack architecture
- **[docker-integration.md](guidelines/tuti-cli/docker-integration.md)** - Docker management
- **[console-display.md](guidelines/tuti-cli/console-display.md)** - Console UI components

## Skills

Activate for specific tasks:

| Skill | When to Use |
|-------|-------------|
| [stack-management](skills/stack-management/SKILL.md) | Creating/modifying stacks |
| [docker-compose-generation](skills/docker-compose-generation/SKILL.md) | Generating docker-compose files |
| [service-stubs](skills/service-stubs/SKILL.md) | Adding service stubs |
| [laravel-zero-commands](skills/laravel-zero-commands/SKILL.md) | Developing commands |
| [phar-binary](skills/phar-binary/SKILL.md) | Building PHAR/binaries |

## Usage Examples

```
# Adding a new framework stack
@AI: Use stack-management skill to add Next.js stack with Node.js runtime.

# Creating framework-specific command  
@AI: Follow laravel-zero-commands skill to create stack:wordpress command.

# Adding universal service
@AI: Use service-stubs skill to add Elasticsearch that works with all frameworks.
```

## Resources

- [Laravel Docs](https://laravel.com/docs)
- [Laravel Zero Docs](https://laravel-zero.com)
- [Project README](../README.md)
- [CONTRIBUTING](../CONTRIBUTING.md)
