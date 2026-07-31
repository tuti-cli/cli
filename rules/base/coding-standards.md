# Coding Standards

## PHP Standards

- `declare(strict_types=1)` in every file
- All classes `final` — prefer composition over inheritance
- Services `final readonly` — immutable service objects
- Constructor injection only — no property injection, no setters
- Explicit return types and type hints everywhere
- No PHPDoc for type-hinted code
- PSR-12 formatting via Laravel Pint
- Trailing commas in multiline arrays
- Return early, avoid else/elseif

## Comments

- Never comment what code does
- Always comment why — non-obvious decisions only

## Error Handling

- Never silently swallow exceptions
- Never use empty catch blocks
- Return `Command::FAILURE` on errors, never `exit()`
- User-friendly messages via `$this->error()` or `$this->failed()`
- Debug logging via `DebugLogService` (singleton, helper: `tuti_debug()`)

## File Organisation

- One class/interface/enum per file
- File name matches class name
- Keep files under 300 lines

## API Design Principles (Laravel-Style)

1. Read Like English — method calls should read like sentences
2. Expressive Names — use verbs that clearly describe the action
3. Sensible Defaults — works with minimal config, customizable when needed
4. Progressive Disclosure — simple for common cases, powerful for advanced
5. Predictable Behavior — no surprises, follows conventions
6. Self-Documenting Types — types explain the API
