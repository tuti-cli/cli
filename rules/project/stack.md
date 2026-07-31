# Stack Configuration

## Stack

- **Framework:** Laravel Zero 12.x
- **Language:** PHP 8.4+
- **Test runner:** Pest (parallel) + PHPStan (level 5+)
- **Lint:** Laravel Pint (PSR-12)
- **Refactor:** Rector
- **Build:** Phpacker (PHAR + native binary)
- **Orchestration:** Docker Compose v2

## Key Packages

| Package | Purpose |
|---------|---------|
| `laravel-zero/framework` | CLI framework |
| `symfony/yaml` | YAML generation for docker-compose |
| `illuminate/database` | Database support |
| `pestphp/pest` | Testing |
| `larastan/larastan` | PHPStan for Laravel |
| `laravel/pint` | Code formatting |
| `rector/rector` | Automated refactoring |
| `phpacker/phpacker` | Binary compilation |

## Quality Commands

```bash
docker compose exec -T app composer test              # All: rector + pint + phpstan + pest
docker compose exec -T app composer test:unit         # Pest tests only (parallel)
docker compose exec -T app composer test:types        # PHPStan static analysis
docker compose exec -T app composer test:lint         # Pint format check (dry-run)
docker compose exec -T app composer test:refactor     # Rector check (dry-run)
docker compose exec -T app composer test:coverage     # Pest with coverage
docker compose exec -T app composer lint              # Fix formatting with Pint
docker compose exec -T app composer refactor          # Fix code with Rector
```
