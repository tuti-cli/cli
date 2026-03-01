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
- [ ] Read .workflow/PROJECT.md for workflow specifics
- [ ] Read ALL .workflow/patches/ for historical lessons
- [ ] Read relevant .workflow/ADRs/ for architecture decisions
- [ ] Verify issue has required labels and sections

## Pipeline Execution

### Sequential Stages

```
SETUP → IMPLEMENT → REVIEW → QUALITY → COMMIT → PR → CLOSE
```

### Stage Rules

**SETUP:**
- Create branch with correct naming
- Update status label to in-progress
- Sync project board

**IMPLEMENT:**
- Use selected agent squad
- Follow project coding standards
- Track progress in feature file

**REVIEW:**
- Spawn review agents in parallel
- Address blocking issues
- Document non-blocking issues

**QUALITY GATE:**
- Run `composer lint && composer test`
- Fix all failures before proceeding
- Max 3 retries for test failures

**COMMIT:**
- Self-review the diff
- Use conventional commit format
- Include issue reference

**PR:**
- Create draft PR first
- Add comprehensive description
- Mark ready after checks pass
- Update issue status to review

**CLOSE:**
- Post summary comment
- Close issue
- Sync project board

## Error Handling

### Test Failures
1. First failure: Back to implementation
2. Second failure: Back to implementation with additional context
3. Third failure: STOP, post detailed error, wait for human

### Existing Test Breaks
- STOP IMMEDIATELY
- Do NOT commit
- Post: "⚠️ Pipeline blocked: existing tests broken"
- Fix before proceeding

### Lint Failures
- Fix all issues
- Re-run lint
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
- `.workflow/PROJECT.md`

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
