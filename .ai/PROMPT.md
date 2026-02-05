# Quick Start Prompt

Copy-paste this at the start of each new GitHub Copilot chat:

---

```
@workspace Read .ai/RULES.md and use it as context for this Laravel Zero CLI project (tuti-cli).

Key rules:
- PHP 8.4, Laravel Zero 12.x, Pest, PHPStan
- All classes `final`, services `final readonly`
- `declare(strict_types=1)` required
- Constructor injection only
- Docker naming: ${PROJECT_NAME}_${APP_ENV}_{service}
- Stubs in stubs/stacks/ and stubs/services/
- Must work when compiled to PHAR/binary
```

---

## Shorter Version

```
@workspace Use .ai/RULES.md context. PHP 8.4, Laravel Zero, final classes, strict types, Docker CLI tool.
```

## Task-Specific Prompts

### Adding a new service stub
```
@workspace Use .ai/RULES.md and .ai/skills/service-stubs/SKILL.md to add a new {service_name} service.
```

### Adding a new CLI command
```
@workspace Use .ai/RULES.md and .ai/skills/laravel-zero-commands/SKILL.md to create {command_name} command.
```

### Working with stacks
```
@workspace Use .ai/RULES.md and .ai/skills/stack-management/SKILL.md to modify {stack_name} stack.
```

### Docker compose generation
```
@workspace Use .ai/RULES.md and .ai/skills/docker-compose-generation/SKILL.md for docker-compose work.
```

### Adding new environment overlays (staging, production)
```
@workspace Use .ai/RULES.md and .ai/skills/environment-overlays/SKILL.md to create {env} environment overlay.
```
