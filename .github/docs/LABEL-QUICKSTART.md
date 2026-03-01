# GitHub Labels Quick Start Guide

A quick reference for using labels in the Tuti CLI project.

## Creating an Issue

### Bug Report

When creating a bug report, the issue template automatically adds:
- `type:bug` - Identifies this as a bug
- `status:needs-confirmation` - Needs triage

**Next steps:**
1. Fill out the bug report template completely
2. Add a priority label (`priority:critical`, `priority:high`, `priority:normal`, or `priority:low`)
3. Add any relevant tech labels (`php`, `docker`, etc.)
4. Wait for triage review

### Feature Request

When creating a feature request, the issue template automatically adds:
- `type:enhancement` - Identifies this as an enhancement
- `status:needs-confirmation` - Needs triage

**Next steps:**
1. Fill out the feature request template completely
2. Add a priority label
3. Wait for triage review

## Triage Process

When reviewing a new issue:

1. **Add Type Label** (if not correct):
   - `type:feature` - New feature
   - `type:bug` - Bug fix
   - `type:enhancement` - Improvement to existing feature
   - `type:chore` - Maintenance task
   - `type:security` - Security issue
   - `type:performance` - Performance improvement
   - `type:infra` - Infrastructure/DevOps
   - `type:architecture` - Architectural change
   - `type:docs` - Documentation only
   - `type:test` - Testing related

2. **Add Priority Label**:
   - `priority:critical` - Blocking, needs immediate attention
   - `priority:high` - This sprint
   - `priority:normal` - Backlog
   - `priority:low` - Nice to have

3. **Add Workflow Label** (if accepted):
   - `workflow:feature` - Full feature implementation
   - `workflow:bugfix` - Bug fix with regression test
   - `workflow:refactor` - Code refactor
   - `workflow:modernize` - Legacy migration
   - `workflow:task` - Simple task

4. **Update Status**:
   - If accepted: Change to `status:ready`
   - If rejected: Add `status:rejected`
   - If needs more info: Keep `status:needs-confirmation`

## Working on an Issue

When you start working on an issue:

1. Change status to `status:in-progress`
2. Create a branch following conventions:
   - Feature: `feature/{issue-number}-slug`
   - Bug fix: `fix/{issue-number}-slug`
   - Refactor: `refactor/{issue-number}-slug`
   - Chore: `chore/{issue-number}-slug`

## Submitting a PR

When creating a pull request:

1. Add relevant tech labels (`php`, `docker`, `github-actions`)
2. If this is a breaking change, add `breaking change` label
3. Change issue status to `status:review`
4. Link the issue in your PR description

## Review Process

During review:

- If changes requested: Keep `status:review`
- If approved and merged: Change issue status to `status:done`

## Status Labels Reference

| Status | When to Use | Meaning |
|--------|-------------|---------|
| `status:needs-confirmation` | New issues | Needs triage |
| `status:ready` | After triage | Ready to implement |
| `status:in-progress` | Active work | Someone is working on it |
| `status:review` | PR created | Waiting for review |
| `status:blocked` | Can't proceed | Waiting for something else |
| `status:rejected` | Won't implement | Closed, not accepting |
| `status:done` | Completed | Issue resolved |
| `status:stale` | No activity | Auto-added by stale workflow |
| `status:needs-reproduction` | Bug report | Need reproduction steps |

## Priority Labels Reference

| Priority | When to Use | Response Time |
|----------|-------------|---------------|
| `priority:critical` | Blocking issues | Immediate |
| `priority:high` | Important issues | Current sprint |
| `priority:normal` | Regular issues | Backlog |
| `priority:low` | Nice to have | When time permits |

## Common Label Combinations

### High Priority Bug
```
type:bug
workflow:bugfix
priority:high
status:ready
```

### New Feature
```
type:feature
workflow:feature
priority:normal
status:ready
```

### Quick Task
```
type:chore
workflow:task
priority:low
status:ready
```

### Security Issue
```
type:security
priority:critical
status:needs-confirmation
```

### Documentation Update
```
type:docs
workflow:task
priority:normal
status:ready
```

## Automation Labels

These labels trigger automated actions:

| Label | Action |
|-------|--------|
| `status:needs-reproduction` | Adds comment requesting reproduction steps |
| `status:stale` | Adds stale warning (auto-added by stale workflow) |
| `good first issue` | Adds contributor welcome comment |

## Tech Labels

Use these to categorize the technical domain:

| Label | When to Use |
|-------|-------------|
| `php` | PHP code changes |
| `docker` | Docker configuration |
| `github-actions` | GitHub Actions workflows |
| `dependencies` | Dependency updates |
| `breaking change` | Breaking API/behavior changes |

## Color Guide

Labels are color-coded for quick visual understanding:

- **Red** (`d73a4a`, `e11d21`): Bug, security, critical, blocked
- **Green/Teal** (`0e8a16`, `008672`): Ready, done, enhancement
- **Blue/Cyan** (`1d76db`, `0db7ed`, `0075ca`): In progress, docs, performance, infra
- **Purple** (`7057ff`, `5319e7`, `4f5b93`): Chore, architecture, PHP
- **Yellow** (`fbca04`): Review, test
- **Gray** (`eeeeee`, `cfd3d7`): Stale, low priority

## Need Help?

- See [Label Reference](../../.workflow/LABEL-REFERENCE.md) for complete documentation
- See [Label Sync Summary](./LABEL-SYNC-SUMMARY.md) for implementation details
- Check the [CLAUDE.md](../../CLAUDE.md) for agent auto-selection rules

## Examples

### Example 1: Triage a Bug Report

```
Issue #123: Login crashes on PHP 8.4

Labels after creation:
- type:bug
- status:needs-confirmation

After triage:
- type:bug (keep)
- workflow:bugfix (add)
- priority:high (add)
- status:ready (update from needs-confirmation)
- php (add)
```

### Example 2: Triage a Feature Request

```
Issue #456: Add Redis caching support

Labels after creation:
- type:enhancement
- status:needs-confirmation

After triage:
- type:feature (update from enhancement)
- workflow:feature (add)
- priority:normal (add)
- status:ready (update from needs-confirmation)
```

### Example 3: Issue Lifecycle

```
# Created
status:needs-confirmation

# After triage, accepted
status:ready

# After assignee starts work
status:in-progress

# After PR created
status:review

# After PR merged
status:done
```

## Tips

1. **One type label per issue** - Don't use multiple type labels
2. **One workflow label per issue** - Only if issue is accepted
3. **One priority label per issue** - Always add a priority
4. **One status label per issue** - Status tracks lifecycle
5. **Multiple tech labels OK** - Can have `php`, `docker`, etc.
6. **Keep labels up to date** - Update status as issue progresses
7. **Use automation** - Stale workflow will auto-add `status:stale`
