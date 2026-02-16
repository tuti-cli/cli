---
name: documentation-writer
description: Guide for writing clear, consistent documentation for Tuti CLI. Use when creating README files, command documentation, API docs, inline code comments, or any project documentation. Follows established patterns and ensures documentation stays synchronized with code.
---

# Documentation Writer

Patterns and guidelines for writing documentation in Tuti CLI.

## Quick Reference

### README Structure
```markdown
# Feature Name

Brief description of what this feature does.

## Installation

Steps to install or enable the feature.

## Usage

```bash
tuti command:action [arguments] [options]
```

## Examples

Practical examples with expected output.

## Configuration

Environment variables and config options.

## Troubleshooting

Common issues and solutions.
```

### Command Documentation
```markdown
## `category:action`

Description of what the command does.

### Usage
```bash
tuti category:action <argument> [--option=value]
```

### Arguments
| Argument | Description | Required |
|----------|-------------|----------|
| `argument` | Description | Yes |

### Options
| Option | Default | Description |
|--------|---------|-------------|
| `--option` | `default` | Description |

### Examples
```bash
# Basic usage
tuti category:action value

# With options
tuti category:action value --option=custom
```
```

## Documentation Types

### 1. README Files
Project or feature-level documentation.

**Location**: Root of feature directory or project root.

**When to update**:
- New features added
- Breaking changes introduced
- Installation process changes
- Configuration options change

### 2. Command Documentation
CLI command reference.

**Location**: 
- Inline in command class (`$description` property)
- `.claude/docs/commands.md` for comprehensive reference
- README for major features

**Required elements**:
- Command signature
- Description
- Arguments and options
- Usage examples
- Exit codes (if non-standard)

### 3. API Documentation
Service interfaces and contracts.

**Location**: 
- PHPDoc for complex interfaces
- `.claude/docs/architecture.md` for system overview

**When to write**:
- New interfaces added
- Contract behavior changes
- Complex service interactions

### 4. Code Comments
Inline documentation in code.

**Rules**:
- Document WHY, not WHAT
- No PHPDoc for type-hinted code
- Document complex algorithms
- Document business decisions

## Writing Style

### Voice and Tone
- **Clear and direct**: No jargon without explanation
- **Active voice**: "Run the command" not "The command should be run"
- **Present tense**: "This command starts" not "This command will start"
- **Second person**: "You can configure" not "Users can configure"

### Formatting Standards

#### Code Blocks
Always specify language:
````markdown
```bash
tuti local:start
```

```php
final readonly class MyService
{
    // ...
}
```

```yaml
services:
  app:
    image: php:8.4-fpm
```
````

#### Tables
Use for structured data:
```markdown
| Option | Default | Description |
|--------|---------|-------------|
| `--env` | `development` | Environment name |
```

#### Lists
Use hyphens for unordered, numbers for ordered:
```markdown
- First item
- Second item

1. Step one
2. Step two
```

#### Emphasis
- **Bold** for UI elements, commands, important terms
- `Code` for file names, code, commands
- *Italic* for emphasis (rare)

## Documentation Patterns

### Command Description Pattern
```php
protected $description = 'Starts the local development environment. ' .
    'Use --rebuild to rebuild containers, --detach to run in background.';
```

### Interface Documentation Pattern
```php
interface StackInstallerInterface
{
    /**
     * Install a fresh stack at the given path.
     *
     * @param string $path Target directory path
     * @param string $name Project name
     * @param array<string, mixed> $options Installation options
     * @return bool True if installation succeeded
     */
    public function installFresh(string $path, string $name, array $options): bool;
}
```

### README Example Pattern
```markdown
# Laravel Stack

Provides a complete Laravel development environment with Docker.

## Quick Start

```bash
tuti stack:laravel my-project
cd my-project
tuti local:start
```

## Included Services

- **app**: PHP 8.4 FPM container
- **postgres**: PostgreSQL 16 database
- **redis**: Redis cache server
- **mailpit**: Email testing tool

## Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `PHP_VERSION` | `8.4` | PHP version to use |
| `DB_CONNECTION` | `pgsql` | Database driver |
| `DB_DATABASE` | `tuti` | Database name |

## Adding Services

```bash
# Add MySQL
tuti stack:manage --add-service=mysql

# Add Meilisearch
tuti stack:manage --add-service=meilisearch
```

## Troubleshooting

### Port Conflicts
If ports are in use, modify the `.env` file:
```env
POSTGRES_PORT=5433
REDIS_PORT=6380
```
```

## CLAUDE.md Documentation

The main CLAUDE.md should be updated when:
- New patterns are established
- New file locations become important
- New validation commands are added
- Key interfaces change

### CLAUDE.md Structure
```markdown
## Section Title

Brief intro to this section.

### Subsection

Details here.

| Column 1 | Column 2 |
|----------|----------|
| Value 1  | Value 2  |

```bash
# Command example
command here
```
```

## Keeping Docs in Sync

### Triggers for Documentation Update

| Code Change | Documentation to Update |
|-------------|------------------------|
| New command | Command docs, README, CLAUDE.md |
| New service | Architecture docs, CLAUDE.md |
| Interface change | API docs, CLAUDE.md |
| Config change | Configuration docs, README |
| Breaking change | README, CHANGELOG |
| Bug fix | Rarely needs docs |

### Documentation Checklist

When adding a new feature:
- [ ] Command `$description` is clear and complete
- [ ] README updated with usage examples
- [ ] CLAUDE.md updated if patterns change
- [ ] Configuration options documented
- [ ] Environment variables documented

## Common Mistakes

| Mistake | Correction |
|---------|------------|
| Outdated examples | Test all examples regularly |
| Missing prerequisites | List all requirements upfront |
| Assume knowledge | Explain domain-specific terms |
| Too much detail | Link to detailed docs, keep summary |
| No examples | Always include practical examples |
| Outdated version numbers | Use "latest" or update regularly |

## Quick Reference

### File Locations
| Type | Location |
|------|----------|
| Main Claude docs | `.claude/CLAUDE.md` |
| Detailed docs | `.claude/docs/*.md` |
| Command reference | `.claude/docs/commands.md` |
| Architecture | `.claude/docs/architecture.md` |
| Memory | `.claude/MEMORY.md` |

### Validation Commands
```bash
# Verify command descriptions
grep -r "protected \$description" app/Commands/

# Check README files
find . -name "README.md" -type f
```
