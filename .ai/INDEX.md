# Tuti CLI - AI Guidelines

> Start with [RULES.md](RULES.md) for quick reference | [GOVERNANCE.md](GOVERNANCE.md) for adding files

## Structure

```
.ai/
├── RULES.md          # Quick reference + governance rules
├── INDEX.md          # This file
├── guidelines/       # Permanent coding/architecture docs
│   ├── core/         # PHP, architecture patterns
│   ├── laravel-zero/ # Framework patterns
│   └── tuti-cli/     # Project-specific patterns
└── skills/           # Reusable how-to guides
    ├── stack-management/
    ├── docker-compose-generation/
    └── service-stubs/
```

## When to Add Files

| Scenario | Action |
|----------|--------|
| New coding pattern used 3+ times | Add to `guidelines/` |
| New skill (repeatable task) | Add to `skills/{name}/SKILL.md` |
| One-time implementation | **Don't add - just commit code** |
| Feature summary | **Don't add - use CHANGELOG.md** |

## Skills Index

| Skill | Use for |
|-------|---------|
| [stack-management](skills/stack-management/SKILL.md) | Add/modify framework stacks |
| [docker-compose-generation](skills/docker-compose-generation/SKILL.md) | Generate docker-compose files |
| [service-stubs](skills/service-stubs/SKILL.md) | Add services (redis, mysql, etc) |
| [laravel-zero-commands](skills/laravel-zero-commands/SKILL.md) | Create CLI commands |
| [phar-binary](skills/phar-binary/SKILL.md) | Build PHAR/native binaries |
