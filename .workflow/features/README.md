# Feature Implementation Plans

This directory contains feature implementation plans that track progress during workflow execution.

## Purpose

Feature files track:
- Task breakdown for implementation
- Progress during execution
- Dependencies between tasks
- Commit checkpoints

## Naming Convention

```
feature-<NUMBER>.md
```

Examples:
- `feature-123.md`
- `feature-456.md`

## Feature Plan Template

```markdown
# Feature #<NUMBER>: <Title>

**Issue:** #<NUMBER>
**Branch:** feature/<NUMBER>-<slug>
**Started:** YYYY-MM-DD

## Overview
Brief description of what this feature does.

## Tasks

### Phase 1: Setup
- [ ] Task 1
- [ ] Task 2

### Phase 2: Implementation
- [ ] Task 3
- [ ] Task 4

### Phase 3: Testing
- [ ] Task 5
- [ ] Task 6

## Progress Log

### YYYY-MM-DD
- Completed: Task 1, Task 2
- Next: Task 3

## Commit Checkpoints
- `abc1234` - Initial setup
- `def5678` - Core implementation
```

## Lifecycle

1. **Created:** When `/workflow:issue` starts implementation phase
2. **Updated:** As tasks are completed during execution
3. **Deleted:** Automatically removed when the linked issue is closed

## Related

- `.claude/agents/master-orchestrator.md` - Creates and updates feature files
- `.claude/agents/feature-planner.md` - Plans feature implementations
