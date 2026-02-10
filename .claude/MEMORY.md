# Claude Memory - tuti-cli Project Context

## Project Type
- PHP CLI application using Laravel Zero framework
- Builds to PHAR and native binaries

## Key Files to Know

### Entry Points
- `tuti` - Main executable
- `bootstrap/app.php` - Application bootstrap
- `app/Providers/AppServiceProvider.php` - Service bindings

### Most Edited Files
- `app/Commands/*` - CLI commands
- `app/Services/*` - Business logic
- `tests/Unit/*` - Unit tests
- `tests/Feature/*` - Feature tests

### Configuration
- `composer.json` - Dependencies
- `phpstan.neon.dist` - Static analysis config
- `pint.json` - Code style config
- `rector.php` - Refactoring rules

## Common Patterns Lookup

| Need | Location |
|------|----------|
| Command example | `app/Commands/Local/StartCommand.php` |
| Service example | `app/Services/Docker/DockerService.php` |
| Test example | `tests/Unit/Services/Docker/DockerServiceTest.php` |
| Interface example | `app/Contracts/OrchestratorInterface.php` |
| Stack installer | `app/Services/Stack/Installers/LaravelStackInstaller.php` |

## Quick Validation

```bash
docker compose exec -T app composer test:unit   # Run all tests
docker compose exec -T app composer test:types  # PHPStan check
docker compose exec -T app composer lint        # Fix code style
```
