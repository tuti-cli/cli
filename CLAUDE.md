# tuti-cli

## Repository
- owner: tuti-cli
- repo: cli

## Stack
- framework: laravel-zero
- language: php
- test: composer test
- lint: composer lint

## Where things are
- `tuti`: the entry script. It boots `bootstrap/app.php` and runs the console kernel.
- `app/Commands/`: every `tuti` command, in `Infrastructure/`, `Local/`, and `Stack/`. `config/commands.php` loads each class in this folder, so a new command needs only a new class. Read when you add or change a command.
- `app/Services/`: the business logic: `Docker/`, `Stack/` (with `Installers/`), `Project/`, `Infrastructure/`, `Support/` (env file handlers), `WordPress/`, and more. Read when you change how stacks, Docker calls, project state, or env files work.
- `app/Contracts/`: the 5 service interfaces. `app/Providers/` binds them to classes. Read when you add a service or change a service boundary.
- `app/Domain/`, `app/Infrastructure/`, `app/Enums/`: the project model and value objects, the Docker Compose orchestrator, and the enums.
- `app/Concerns/`: traits for branded console output and project URLs. Read when you change how output looks.
- `stubs/`: templates that tuti copies into user projects: `stacks/laravel/`, `stacks/wordpress/`, `stacks/registry.json`, and `infrastructure/traefik/`. Read when you change a stack template or a Docker service.
- `tests/`: `Feature/Console/` follows `app/Commands/`, and `Unit/Services/` follows `app/Services/`. Fakes are in `Mocks/`, and test stacks are in `Fixtures/`.
- `rules/`: coding, git, and test rules (`base/`), and architecture and stub rules (`project/`). Read before you write code.
- `docs/`: the command reference (`COMMANDS.md`), stacks, troubleshooting, and the release process. Read when a user-facing behaviour or a release step changes.
- `scripts/`: install, uninstall, and GitHub label setup scripts.

## Workflow
- mode: scratch
- version: local

## Non-Negotiable Rules

### Quality
- Run `composer lint` before every commit
- Run `composer test` before every commit
- Never commit failing tests
- Never ship without tests for new code

### Git
- Conventional commits: `<type>(<scope>): <description>`
- Never commit directly to main
- Never force push without explicit approval
- Never commit secrets or credentials

### Workflow
- Plan before code — present plan, wait for approval
- Never create agents for pipeline steps
- Skills = instincts, Agents = workers, Pipelines = steps
- "Does this need to think?" — validation rule for all new components

### Security
- Array syntax for shell execution — never string interpolation
- Use `.env` for config, ensure in `.gitignore`

## Context
- Stack details: .workflow/core/STACK.md
- Product description: .workflow/core/PROJECT.md
- Current plan: .workflow/core/PLAN.md
- Tech debt: .workflow/analysis/TECH-DEBT.md
