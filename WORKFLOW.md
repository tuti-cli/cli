# Tuti CLI — Development Workflow

## Quick Reference

| Command | What it does |
|---|---|
| `/triage [N]` | Triage external issues (needs-confirmation) |
| `/discover` | Codebase analysis → phases → GitHub import |
| `/implement <N>` | Implement issue (plan → code → PR) |
| `/implement --worktree <N>` | Implement issue in isolated worktree |
| `/status` | Dashboard: milestones, in-progress, review, worktrees |
| `/switch [N]` | List or switch to issue worktrees |
| `/board setup\|sync\|view` | Manage GitHub Projects kanban board |
| `/improve-workflow "..."` | Improve this workflow system |

## Issue Lifecycle

```
External issue (needs-confirmation) → /triage → confirmed/ready
Discovery issue → /discover → ready
Manual issue → create with acceptance criteria → ready
    ↓
/implement <N> [--worktree]
    ↓
Plan mode (your approval required)
    ↓
Branch created (worktree only if --worktree flag)
    ↓
Agent squad implements
    ↓
After each edit: composer lint (auto-fix)
    ↓
Before each commit: composer test (all checks)
    ↓
Commit → Push → Draft PR → Ready PR
    ↓
You merge → issue auto-closes
```

## Status Labels & Board Columns

| Label | Board Column |
|---|---|
| `status: needs-confirmation` | 🔶 Inbox |
| `status: confirmed` | ✅ Confirmed |
| `status: rejected` | ❌ Rejected |
| `status: ready` | 📋 Ready |
| `status: in-progress` | 🔨 In Progress |
| `status: blocked` | 🚫 Blocked |
| `status: review` | 👀 In Review |
| *(closed)* | ✅ Done |

## Type Labels (drives agent selection)

| Label | Primary Agent |
|---|---|
| `type: feature` | cli-developer |
| `type: bug` | error-detective |
| `type: chore` | refactoring-specialist |
| `type: security` | security-auditor |
| `type: performance` | performance-engineer |
| `type: infra` | devops-engineer |
| `type: architecture` | architect-reviewer |
| `type: docs` | documentation-engineer |
| `type: epic` | NOT implemented directly |

## Conventions

**Branch naming:** `feature/<N>-slug` · `bug/<N>-slug` · `hotfix/<N>-slug` · `chore/<N>-slug` · `security/<N>-slug`

**Commits:** `feat(local): description (#N)` · `fix(deploy): description (#N)`
Scopes: `local` `deploy` `env` `projects` `config` `core` `build` `commands` `workflow`

**Quality gates (all must pass before commit):**
```bash
composer test
composer lint
./vendor/bin/phpstan analyse
```

**Automatic quality checks:**
- After EVERY file edit/write: `composer lint` (auto-fixes formatting)
- Before EVERY commit: `composer test` (all checks must pass)
- This prevents CI failures and ensures consistent code quality

## Improving the Workflow

```bash
/improve-workflow "what you want to change"
```

Automatic flow:
1. Enters plan mode and presents improvement plan
2. After approval → creates issue (type:chore, status:ready)
3. Auto-calls `/implement` on the new issue

Full spec: `.claude/agents/tuti-workflow-master.md`
