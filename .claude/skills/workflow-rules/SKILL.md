---
name: workflow-rules
description: "Global rules that all workflow agents must follow. Ensures consistency, quality, and proper process across all pipeline executions."
---

# Workflow Rules

These rules apply to all agents in the Tuti CLI workflow system.

## Core Principles

### 1. Plan Before Code
- Always present a plan first
- Wait for explicit approval before implementing
- Never write code without user consent

### 2. Quality Gates Are Mandatory
- Tests MUST pass before any commit
- Lint MUST pass before any commit
- Coverage thresholds MUST be met
- No bypassing quality gates

### 3. Documentation Is Required
- Update CHANGELOG.md for user-visible changes
- Update README.md for usage changes
- Add inline docs for new functions/methods
- No commit without doc updates

### 4. Conventional Commits Only
```
<type>(<scope>): <subject> (#N)
```
Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

### 5. Issue Closure Required
- Every completed issue must be closed with summary
- Summary must include: what was done, tests added, docs updated
- No issue left open after PR merge

## Pre-Flight Checklist

Before any implementation:

- [ ] Read CLAUDE.md for project context
- [ ] **Selective patch loading** via INDEX.md (load only relevant categories)
- [ ] Read relevant .workflow/ADRs/ for architecture decisions
- [ ] Verify issue has required labels and sections
- [ ] **Auto-label if missing** (AskUserQuestion to confirm)

## Context Caching (Selective Patch Loading)

**Load patches efficiently:**
1. Read `.workflow/patches/INDEX.md` first
2. Identify relevant categories from issue keywords
3. Load only patches in matching categories
4. Full load only if INDEX is stale (>24h old)

**Category Keywords:**
- **docker**: docker, container, compose, volume
- **testing**: test, coverage, pest, phpunit
- **security**: security, vulnerability, injection
- **php**: php, laravel, class, method, service
- **workflow**: workflow, pipeline, agent, command

## Pipeline Execution

### Sequential Stages

```
SETUP → IMPLEMENT → REVIEW → QUALITY → COMMIT → PR → CLOSE
```

### Stage Rules

**SETUP:**
- Validate current branch (AskUserQuestion if not on main)
- Create branch with correct naming
- Update status label to in-progress
- Sync project board

**IMPLEMENT:**
- Use selected agent squad
- Follow project coding standards
- Track progress in feature file

**REVIEW & QUALITY (PARALLEL):**
- Spawn code-reviewer agent in background
- Run quality gates in foreground simultaneously
- Wait for both to complete
- Merge results before proceeding
- Block if either fails

**Parallel Execution Flow:**
```
IMPLEMENT done
    │
    ├─────────────────┐
    │                 │
    ▼                 ▼
 REVIEW            QUALITY
    │                 │
    └─────────────────┘
              │
              ▼
         COMMIT
```
- Run quality gates based on change type (see Tiered Quality Gates below)
- Fix all failures before proceeding
- Max 3 retries for test failures

### Tiered Quality Gates

Quality gates are adjusted based on the type of changes being made:

| Change Type | Lint | Tests | Coverage | When to Use |
|-------------|------|-------|----------|-------------|
| docs only | ✓ | ✗ | ✗ | Only `.md` files changed |
| config only | ✓ | ✗ | ✗ | Only config files (`.json`, `.yaml`, `.xml`) |
| refactor | ✓ | ✓ | ✓ (maintain) | Behavior-preserving code changes |
| feature/fix | ✓ | ✓ | ✓ (90% new) | New features or bug fixes |
| security | ✓ | ✓ | ✓ (95% affected) | Security-related changes |

**Determining Change Type:**
1. Check if only `.md` files changed → docs only
2. Check if only config files changed → config only
3. Check issue labels for `type:security` → security
4. Check issue labels for `type:refactor` → refactor
5. Default → feature/fix

**COMMIT:**
- Self-review the diff
- **AskUserQuestion: Review changes?** (Approve all / Review each file / Cancel)
- **If per-file: AskUserQuestion per file** (Keep / Discard / Edit manually)
- Use conventional commit format
- **AskUserQuestion: Create commit?** (Yes / Edit message / Cancel)
- Include issue reference

**PR:**
- Create draft PR first
- Add comprehensive description
- Mark ready after checks pass
- Update issue status to review

**CLOSE:**
- Post summary comment
- Close issue
- Cleanup workflow artifacts (patches, features, state)
- Update TECH-DEBT.md (remove resolved items)
- Sync project board

## Error Handling

### Smart Retry Logic

**Identify failure type and apply appropriate strategy:**

| Failure Type | Detection | Strategy |
|--------------|-----------|----------|
| Flaky test | Intermittent, random failures | Retry 2x with different seed |
| Lint error | Syntax, parse, Pint errors | `composer lint`, retry once |
| Refactor error | Rector errors | `composer refactor`, retry once |
| Type error | PHPStan, type mismatch | No retry, needs human |
| Timeout | "exceeded", "timeout" | Increase timeout, retry once |
| Dependency | "not found", "not installed" | Clear cache, retry once |
| Logic error | Assertion failed | Back to implementation |

**Max Retries by Type:**
- Flaky: 2
- Lint: 1 (auto-fix)
- Refactor: 1 (auto-fix)
- Timeout: 1
- Dependency: 1
- Type/Logic: 0 (escalate immediately)

### Test Failures
1. **First failure:** Identify type, apply smart retry
2. **After retries exhausted:** Back to implementation
3. **Still failing:** STOP, post detailed error, wait for human

### Existing Test Breaks
- STOP IMMEDIATELY
- Do NOT commit
- Post: "⚠️ Pipeline blocked: existing tests broken"
- Fix before proceeding

### Quality Gates

**`composer test` runs all checks:**
```bash
composer test:refactor  # Rector (auto-fix)
composer test:lint       # Pint (auto-fix)
composer test:types       # PHPStan (no auto-fix)
composer test:unit        # Pest tests
```

**Auto-fix available for:**
- **Lint:** `composer lint` — runs Pint with auto-fix
- **Refactor:** `composer refactor` — runs Rector with auto-fix

### Lint Failures
- Run `composer lint` (auto-fixes with Pint)
- Re-run lint check
- Proceed when clean

### Refactor Failures
- Run `composer refactor` (auto-fixes with Rector)
- Re-run refactor check
- Proceed when clean

## Agent Communication

### Progress Updates
Post on GitHub issue:
```markdown
**Stage: {STAGE_NAME}**

**Progress:** {description}
**Next:** {next_stage}

{additional_details}
```

### Error Reports
Post on GitHub issue:
```markdown
⚠️ **Pipeline Blocked**

**Stage:** {stage}
**Error:** {error_message}
**Action Required:** {what_to_do}
```

## File Synchronization

Keep these files synchronized:
- `.claude/agents/master-orchestrator.md`
- `.claude/agents/issue-executor.md`
- `.claude/agents/issue-creator.md`
- `.claude/agents/issue-closer.md`
- `.claude/commands/workflow/*.md`
- `CLAUDE.md` (.claude Configuration section)

## Protected Agents

These agents cannot be removed without `--force`:
- master-orchestrator
- issue-executor
- issue-creator
- issue-closer
- agent-installer
- workflow-orchestrator

## Label System

### Workflow Type
- `workflow:feature` — New feature
- `workflow:bugfix` — Bug fix
- `workflow:refactor` — Refactoring
- `workflow:modernize` — Legacy migration
- `workflow:task` — Simple task

### Priority
- `priority:critical` — Immediate
- `priority:high` — This sprint
- `priority:normal` — Backlog
- `priority:low` — Nice to have

### Status
- `status:ready` — Can implement
- `status:in-progress` — Being worked
- `status:review` — PR exists
- `status:done` — Completed
- `status:blocked` — Cannot proceed
- `status:needs-confirmation` — Requires triage
- `status:rejected` — Closed

## GitHub Repository

Always specify repository explicitly:
- **gh CLI:** `--repo tuti-cli/cli`
- **GitHub MCP:** `owner="tuti-cli" repo="cli"`

## Security

### Process Execution
- Always use array syntax for Process::run()
- Never interpolate variables into shell commands
- Validate file paths before Process execution

### Secrets
- Never commit secrets
- Use .env for configuration
- Include in .gitignore

## Testing Standards

### Commands
```bash
composer test              # All: rector + pint + phpstan + pest
composer test:unit         # Pest tests only
composer test:types        # PHPStan
composer test:lint         # Pint check
composer test:coverage     # Coverage report
```

### Coverage Thresholds
- Overall: 80%
- New code: 90%
- Critical paths: 95%

### Critical Paths (always 95%)
- Authentication
- Data mutation (create/update/delete)
- Payment processing
- Anything marked `// @critical`
