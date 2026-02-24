# Documentation Templates

Reusable templates for common documentation needs in Tuti CLI.

## README Template

```markdown
# [Feature Name]

[Brief one-line description of what this feature does]

## Overview

[2-3 sentences explaining the feature's purpose and benefits]

## Quick Start

```bash
# Installation/activation command
tuti command:name [arguments]

# Verify it works
tuti command:name --verify
```

## Installation

### Prerequisites
- [Requirement 1]
- [Requirement 2]

### Steps
1. [Step one]
2. [Step two]
3. [Step three]

## Usage

### Basic Usage
```bash
tuti command:action <required-argument>
```

### With Options
```bash
tuti command:action <argument> --option=value
```

## Commands

| Command | Description |
|---------|-------------|
| `command:action` | Brief description |
| `command:other` | Brief description |

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `VARIABLE_NAME` | `default_value` | What it controls |

### Configuration File

[If applicable, describe configuration file format]

## Examples

### Example 1: [Scenario]
```bash
# Description of what this accomplishes
tuti command:action value --option=custom
```

**Expected Output:**
```
Output here
```

### Example 2: [Another Scenario]
[Another example]

## Troubleshooting

### [Common Issue 1]
**Problem:** [Description]

**Solution:**
```bash
# Fix command
```

### [Common Issue 2]
**Problem:** [Description]

**Solution:** [Steps to fix]

## Related Documentation

- [Link to related doc 1]
- [Link to related doc 2]

## Changelog

### [Version] - [Date]
- [Change 1]
- [Change 2]
```

## Command Reference Template

```markdown
# Command Reference

Complete reference for all Tuti CLI commands.

## Infrastructure Commands

### `infra:start`

Starts the global Docker infrastructure (Traefik reverse proxy).

**Usage:**
```bash
tuti infra:start [options]
```

**Options:**
| Option | Default | Description |
|--------|---------|-------------|
| `--detach` | `false` | Run in background |
| `--force` | `false` | Force restart if running |

**Examples:**
```bash
# Start infrastructure
tuti infra:start

# Start in background
tuti infra:start --detach
```

**Exit Codes:**
- `0`: Success
- `1`: Failed to start

---

### `infra:stop`

Stops the global Docker infrastructure.

**Usage:**
```bash
tuti infra:stop [options]
```

**Options:**
| Option | Default | Description |
|--------|---------|-------------|
| `--clean` | `false` | Remove containers and volumes |

**Examples:**
```bash
# Stop infrastructure
tuti infra:stop

# Stop and clean up
tuti infra:stop --clean
```

---

## Local Commands

### `local:start`

Starts the local development environment for a project.

**Usage:**
```bash
tuti local:start [options]
```

**Arguments:**
| Argument | Required | Description |
|----------|----------|-------------|
| (none) | - | Uses current directory |

**Options:**
| Option | Default | Description |
|--------|---------|-------------|
| `--rebuild` | `false` | Rebuild containers |
| `--detach` | `false` | Run in background |
| `--services` | `all` | Comma-separated services to start |

**Examples:**
```bash
# Start all services
tuti local:start

# Rebuild and start
tuti local:start --rebuild

# Start specific services
tuti local:start --services=app,postgres
```

**Exit Codes:**
- `0`: Success
- `1`: Failed to start
- `2`: Configuration error
```

## API Documentation Template

```markdown
# [Interface/Service Name]

[Brief description of what this interface provides]

## Interface Definition

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

interface ExampleInterface
{
    /**
     * Brief description of what this method does.
     *
     * @param string $parameter Description of parameter
     * @return bool Description of return value
     * @throws ExceptionType When condition occurs
     */
    public function methodName(string $parameter): bool;
}
```

## Methods

### `methodName`

[Detailed description of what this method does and when to use it]

**Signature:**
```php
public function methodName(string $parameter): bool
```

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$parameter` | `string` | Description of what this parameter controls |

**Return Value:**
- `true` when [condition]
- `false` when [condition]

**Exceptions:**
| Exception | Condition |
|-----------|-----------|
| `InvalidArgumentException` | When parameter is empty |
| `RuntimeException` | When operation fails |

**Example Usage:**
```php
$service = app(ExampleInterface::class);

if ($service->methodName('value')) {
    // Success case
}
```

**Implementation Notes:**
- [Important detail about implementation]
- [Thread safety considerations]
- [Performance characteristics]

## Implementations

### `ConcreteImplementation`

[Description of this specific implementation]

**When to use:** [Guidance on when this implementation is appropriate]

**Differences from interface:**
- [Any deviations or extensions]
```

## CHANGELOG Template

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- [New feature]

### Changed
- [Change in existing functionality]

### Deprecated
- [Soon-to-be removed feature]

### Removed
- [Removed feature]

### Fixed
- [Bug fix]

### Security
- [Security fix]

## [1.0.0] - 2024-01-15

### Added
- Initial release
- Stack management for Laravel and WordPress
- Docker Compose orchestration
- Global Traefik infrastructure

### Changed
- Nothing (initial release)

### Fixed
- Nothing (initial release)

## [0.9.0] - 2024-01-01

### Added
- Beta release features

[Unreleased]: https://github.com/user/repo/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/user/repo/releases/tag/v1.0.0
```

## Architecture Decision Record (ADR) Template

```markdown
# ADR-[NUMBER]: [Decision Title]

## Status

[Proposed | Accepted | Deprecated | Superseded]

## Context

[Describe the context and problem statement]

## Decision

[Describe the decision that was made]

## Consequences

### Positive
- [Benefit 1]
- [Benefit 2]

### Negative
- [Drawback 1]
- [Drawback 2]

### Neutral
- [Side effect that's neither good nor bad]

## Alternatives Considered

### Alternative 1: [Name]
[Description and why it wasn't chosen]

### Alternative 2: [Name]
[Description and why it wasn't chosen]

## Implementation Notes

[Any specific notes about how to implement this decision]

## References

- [Link to relevant discussion]
- [Link to relevant documentation]
```

## Troubleshooting Guide Template

```markdown
# Troubleshooting Guide

Common issues and their solutions for [feature/component].

## Quick Diagnostics

```bash
# Check if feature is working
tuti status --feature=name

# View recent logs
tuti logs --tail=100
```

## Common Issues

### [Issue Category 1]

#### Problem: [Specific Issue]
**Symptoms:**
- [Observable behavior 1]
- [Observable behavior 2]

**Cause:** [Root cause explanation]

**Solution:**
1. [Step 1]
2. [Step 2]
3. [Step 3]

```bash
# Command to fix
tuti fix --issue=specific
```

**Verification:**
```bash
# Command to verify fix worked
tuti verify --issue=specific
```

---

#### Problem: [Another Issue]
[Same format as above]

### [Issue Category 2]

[Same format as above]

## Error Messages

| Error Message | Cause | Solution |
|---------------|-------|----------|
| `Error: something failed` | [Why this happens] | [How to fix] |
| `Error: another error` | [Why this happens] | [How to fix] |

## Getting Help

If you can't resolve an issue:

1. Check the [full documentation](link)
2. Search [existing issues](link)
3. Ask in [community chat](link)
4. Open a [new issue](link) with:
   - Full error message
   - Steps to reproduce
   - Your environment (`tuti info`)