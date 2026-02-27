# workflow:create-issue
Create a well-formed GitHub issue from current context (plan, ADR, patch, or audit finding).

**Usage:**
- `/workflow:create-issue` — Create issue from current context
- `/workflow:create-issue --plan` — Create from .workflow/PLAN.md
- `/workflow:create-issue --adr` — Create from latest ADR
- `/workflow:create-issue --patch <file>` — Create from specific patch
- `/workflow:create-issue --execute` — Create and immediately execute

**Issue Template:**
```markdown
## Summary
[What needs to be done]

## Context
[Why this matters]

## Acceptance Criteria
- [ ] Criterion 1
- [ ] Criterion 2

## Technical Notes
[Stack details, constraints]

## Definition of Done
- [ ] Code written
- [ ] Tests passing
- [ ] Docs updated

<!-- WORKFLOW META -->
workflow_type: feature|bugfix|refactor|task
estimated_complexity: small|medium|large
```

Invoke `issue-creator`:
> "Create a GitHub issue from current workflow context. GITHUB REPO: owner=tuti-cli repo=cli. IF --plan: read .workflow/PLAN.md and extract title, summary, tasks as acceptance criteria. IF --adr: read latest .workflow/ADRs/*.md and create implementation issue. IF --patch: read specified patch file and create bug fix issue. IF no flag: determine context automatically. Apply correct labels (workflow:type, priority, source). Format body to standard template. Create issue via GitHub MCP. Return issue number. IF --execute: immediately invoke /workflow:issue with new issue number."
