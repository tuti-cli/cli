# GitHub Label Reference for v5 Workflow

This document describes all GitHub labels used in the Tuti CLI workflow system.

## Quick Reference

| Label | Purpose | Color |
|-------|---------|-------|
| **Type Labels** | |
| `type:feature` | New feature implementation | Green |
| `type:bug` | Bug fix | Red |
| `type:enhancement` | Feature enhancement or improvement | Cyan |
| `type:chore` | Maintenance task | Purple |
| `type:security` | Security vulnerability or fix | Red |
| `type:performance` | Performance optimization | Blue |
| `type:infra` | Infrastructure or DevOps change | Cyan |
| `type:architecture` | Architectural change or decision | Purple |
| `type:docs` | Documentation only change | Blue |
| `type:test` | Test coverage or testing change | Yellow |
| **Workflow Labels** | |
| `workflow:feature` | Feature implementation pipeline | Green |
| `workflow:bugfix` | Bug fix pipeline | Red |
| `workflow:refactor` | Refactor pipeline | Blue |
| `workflow:modernize` | Legacy migration pipeline | Purple |
| `workflow:task` | Simple task pipeline | Purple |
| **Priority Labels** | |
| `priority:critical` | Critical priority - execute immediately | Red |
| `priority:high` | High priority - this sprint | Dark Red |
| `priority:normal` | Normal priority - backlog | Teal |
| `priority:low` | Low priority - nice to have | Gray |
| **Status Labels** | |
| `status:ready` | Ready to implement | Green |
| `status:in-progress` | Work is in progress | Blue |
| `status:review` | PR exists, ready for review | Yellow |
| `status:blocked` | Blocked by something else | Red |
| `status:needs-confirmation` | Needs triage/confirmation | Lavender |
| `status:rejected` | Closed, will not implement | Dark Red |
| `status:done` | Completed and closed | Teal |
| `status:stale` | No recent activity | Gray |
| **Additional Labels** | |
| `good first issue` | Good for newcomers | Purple |
| `help wanted` | Extra attention is needed | Teal |
| `breaking change` | Breaking change requiring major version bump | Red |
| `dependencies` | Pull requests that update a dependency file | Blue |
| `php` | PHP related changes | Purple |
| `docker` | Docker related changes | Cyan |
| `github-actions` | GitHub Actions related changes | Blue |

## Label Groups

### Type Labels (10)

These labels determine which agents are selected for the work:

| Label | Primary Agent | Secondary Agents |
|-------|---------------|------------------|
| `type:feature` | cli-developer | php-pro, laravel-specialist |
| `type:bug` | error-detective | code-reviewer, qa-expert |
| `type:chore` | refactoring-specialist | code-reviewer |
| `type:security` | security-auditor | code-reviewer |
| `type:performance` | performance-engineer | refactoring-specialist |
| `type:infra` | devops-engineer | deployment-engineer, build-engineer |
| `type:architecture` | architect-reviewer | refactoring-specialist |
| `type:docs` | documentation-engineer | - |
| `type:test` | qa-expert | php-pro |
| `type:enhancement` | cli-developer | php-pro |

### Workflow Labels (5)

These labels determine which pipeline is used:

| Label | Pipeline | Description |
|-------|----------|-------------|
| `workflow:feature` | Feature Pipeline | Full implementation with review |
| `workflow:bugfix` | Bug Fix Pipeline | Fix + regression test + patch |
| `workflow:refactor` | Refactor Pipeline | Behavior-preserving changes |
| `workflow:modernize` | Legacy Pipeline | Migration with backward compat |
| `workflow:task` | Task Pipeline | Simple atomic task, minimal overhead |

### Priority Labels (4)

These labels determine execution order:

| Label | Priority Level | When to Execute |
|-------|----------------|-----------------|
| `priority:critical` | Immediate | Right now, blocking other work |
| `priority:high` | This Sprint | Within current sprint |
| `priority:normal` | Backlog | When time permits |
| `priority:low` | Nice to Have | If time allows |

### Status Labels (8)

These labels track issue lifecycle:

| Label | Current State | Next Action |
|-------|----------------|-------------|
| `status:ready` | Ready to work | Assign and start |
| `status:in-progress` | Being worked on | Continue work |
| `status:review` | PR created | Review and merge |
| `status:blocked` | Cannot proceed | Unblock or reject |
| `status:needs-confirmation` | Needs triage | Confirm requirements |
| `status:rejected` | Will not implement | Archive |
| `status:done` | Completed | Move to Done |
| `status:stale` | No activity | Update or close |

## Label Combinations

### Valid Issue Label Combinations

**New Feature:**
```
type:feature
workflow:feature
priority:normal
status:ready
```

**Bug Fix:**
```
type:bug
workflow:bugfix
priority:high
status:ready
```

**Quick Task:**
```
type:chore
workflow:task
priority:low
status:ready
```

**Security Issue:**
```
type:security
workflow:bugfix
priority:critical
status:ready
```

**Enhancement Request:**
```
type:enhancement
status:needs-confirmation
```

### Issue Creation Flow

1. Issue created with `status:needs-confirmation`
2. Triage adds appropriate `type:` and `priority:` labels
3. Triage adds `workflow:` label (unless `status:rejected`)
4. Status changed to `status:ready`
5. Assignee starts work → `status:in-progress`
6. PR created → `status:review`
7. PR merged → `status:done`

## Automation Triggers

The following labels trigger GitHub Actions:

| Label | Trigger | Action |
|-------|---------|--------|
| `status:needs reproduction` | Issue labeled | Adds comment requesting reproduction |
| `status:stale` | Issue labeled | Adds stale warning, schedules close |
| `good first issue` | Issue labeled | Adds contributor welcome comment |

## Color Guide

- **Red**: Critical, breaking, blocking (high attention needed)
- **Dark Red**: High priority, rejected (urgent but not blocking)
- **Green**: Ready, done, feature (positive state)
- **Teal**: Normal, help wanted, done (standard state)
- **Blue**: Infra, docs, github-actions (infrastructure changes)
- **Cyan**: Enhancement, infra, docker (improvements)
- **Purple**: Chore, architecture, good first issue (planning/structural)
- **Yellow**: Review, test (needs attention/verification)
- **Lavender**: Needs confirmation (awaiting input)
- **Gray**: Low, stale, old (lower priority)

## Migration Notes

If you see issues with old labels, update them:

| Old Label | New Label |
|-----------|-----------|
| `bug` | `type:bug` |
| `enhancement` | `type:enhancement` |
| `documentation` | `type:docs` |
| `priority: high` | `priority:high` |
| `priority: low` | `priority:low` |
| `status: needs triage` | `status:needs-confirmation` |
| `status: needs reproduction` | `status:needs reproduction` (keeping for automation) |

## See Also

- [Label Sync Analysis](./LABEL-SYNC-ANALYSIS.md) - Analysis of label changes
- [CLAUDE.md](../CLAUDE.md) - Agent auto-selection rules
- [master-orchestrator.md](../.claude/agents/master-orchestrator.md) - Pipeline routing
- [issue-executor.md](../.claude/agents/issue-executor.md) - Label validation
