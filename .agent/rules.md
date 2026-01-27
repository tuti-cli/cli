# AI Context & Rules

This file defines the context, coding style, and architectural rules for the **Tuti CLI** project. The AI Assistant (Antigravity) should prioritized these rules when generating code or answering questions.

## 1. Project Overview
**Tuti CLI** is a PHP-based command-line tool designed to manage the entire development lifecycle (initialization, local development, deployment) for projects.
- **Core Language**: PHP (Target version aligned with `composer.json`, likely 8.2+)
- **Key Dependencies**: Laravel Components (Zero/Console), Docker, Deployer.

## 2. Coding Style & Conventions
These rules mirror the configuration in `pint.json` and general best practices for this project.

### strict_types
**ALWAYS** enable strict typing in every PHP file.
```php
declare(strict_types=1);
```

### Class Definitions
- **Final by Default**: All classes should be `final` unless explicitly designed for inheritance (e.g., abstract base classes).
- **Readonly Classes**: Use `readonly` classes where applicable (immutable DTOs, value objects).

### Visibility & Access
- **Private by Default**: Properties and methods should be `private` unless `protected` or `public` is strictly necessary.
- **Typed Properties**: Always type properties.
- **Constructor Injection**: Prefer constructor injection for dependencies.

### Control Structures
- **No `else` / `elseif`**: Return early to avoid nesting and unnecessary `else` blocks (`no_superfluous_elseif`, `no_useless_else`).
- **Trailing Commas**: Use trailing commas in multiline arrays and parameter lists.

### Formatting
- **Spacing**: Follow PSR-12/Laravel standards (handled by Pint).
- **Imports**: Group imports (Classes, Functions, Constants).

## 3. Architecture Guidelines
Refer to [ARCHITECTURE-CONCEPTS.md](ARCHITECTURE-CONCEPTS.md) for detailed design patterns.

- **Modes**: The CLI operates in Global Mode (`~/.tuti/`) and Project Mode (`.tuti/`).
- **Configuration**:
    - Global: `~/.tuti/settings.json`, `~/.tuti/projects.json`
    - Project: `.tuti/config.json`
- **Output**: Use the styling components for CLI output (colors, tables, spinners) to maintain a premium feel.

## 4. User Preferences (Custom)
*Add your specific preferences below. The AI will read this section.*

- [x] Preferred Test Framework: Pest
- [x] Documentation Style: Type Hints (No PHPDoc unless necessary)
- [ ] Error Handling: Exceptions vs Result Types?

---
*Note: This file is read by the AI to align with your project's standards.*
