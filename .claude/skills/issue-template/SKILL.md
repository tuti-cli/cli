---
name: issue-template
description: "Standard GitHub issue template format for the Tuti CLI workflow system. All issues must follow this format for proper workflow processing."
---

# GitHub Issue Template

All issues in the Tuti CLI workflow system must follow this format for proper pipeline routing and execution.

## Required Format

```markdown
## Summary
[What needs to be done — 1-2 sentences maximum]

## Context
[Why this matters, background information, business value]

## Acceptance Criteria
- [ ] Criterion 1
- [ ] Criterion 2
- [ ] Criterion 3

## Technical Notes
[Stack details, constraints, implementation hints, related issues]

## Definition of Done
- [ ] Code written and working
- [ ] Tests written and passing
- [ ] Coverage threshold met (80% overall, 90% new code)
- [ ] Review passed (code + security + performance)
- [ ] Docs updated (CHANGELOG + README + inline)
- [ ] Issue closed with summary comment

<!-- WORKFLOW META -->
workflow_type: feature|bugfix|refactor|task|modernize
project_type: new|existing|legacy
estimated_complexity: small|medium|large
related_issues: #123, #456
```

## Section Guidelines

### Summary
- One to two sentences maximum
- Start with action verb (Add, Fix, Update, Remove, Refactor)
- Be specific about what changes

**Good Examples:**
- "Add user authentication with email/password login"
- "Fix session timeout crash on long-running requests"
- "Refactor configuration handling for better testability"

**Bad Examples:**
- "Improve auth" (too vague)
- "Bug fix" (no context)
- "Update stuff" (not specific)

### Context
- Why is this needed?
- What problem does it solve?
- What is the business impact?
- Any relevant background or history

### Acceptance Criteria
- Specific, measurable conditions
- Each criterion should be testable
- Use checkbox format `- [ ]`
- Cover happy path and edge cases

**Good Examples:**
- "Users can log in with email and password"
- "Failed login attempts are rate limited to 5 per minute"
- "Session expires after 24 hours of inactivity"

### Technical Notes
- Stack considerations (PHP 8.4, Laravel Zero)
- Performance requirements
- Security considerations
- Dependencies on other issues
- Affected files or components
- Implementation hints

### Definition of Done
Use the standard checklist. Do not modify unless project-specific additions are needed.

## WORKFLOW META Section

This section is REQUIRED for workflow routing. Do not remove or modify the format.

### workflow_type

| Value | Description | Pipeline |
|-------|-------------|----------|
| `feature` | New functionality | Feature pipeline with full review |
| `bugfix` | Bug fix | Bug fix pipeline with regression test |
| `refactor` | Code restructuring | Refactor pipeline, preserve behavior |
| `task` | Simple atomic task | Minimal pipeline |
| `modernize` | Legacy migration | Migration pipeline with backward compat |

### project_type

| Value | Description |
|-------|-------------|
| `new` | Fresh project |
| `existing` | Active codebase |
| `legacy` | Legacy/old codebase |

### estimated_complexity

| Value | Criteria | Typical Duration |
|-------|----------|------------------|
| `small` | Single task, <5 files | <1 day |
| `medium` | Multiple tasks, 5-15 files | 1-3 days |
| `large` | Many tasks, >15 files | 3+ days |

### related_issues
- List related issue numbers with # prefix
- Comma-separated
- Include blocking issues, parent epics, dependencies

## Required Labels

Every issue must have:

### Workflow Type Label
- `workflow:feature`
- `workflow:bugfix`
- `workflow:refactor`
- `workflow:modernize`
- `workflow:task`

### Priority Label
- `priority:critical` — Security issues, data loss risk
- `priority:high` — Important features, blocking bugs
- `priority:normal` — Standard backlog items
- `priority:low` — Nice to have improvements

### Status Label
- `status:ready` — Ready to implement
- `status:needs-confirmation` — Requires triage first
- `status:blocked` — Cannot proceed (explain in comments)

## Complete Example

```markdown
## Summary
Add user authentication with email/password login and session management.

## Context
Currently the application has no authentication system. Users cannot log in or access protected resources. This is required for the MVP release and affects all user-facing features.

Business impact: Without authentication, we cannot:
- Track user actions
- Protect sensitive data
- Enable personalized experiences

## Acceptance Criteria
- [ ] Users can register with email and password
- [ ] Users can log in with registered credentials
- [ ] Sessions persist for 24 hours
- [ ] Failed login attempts are rate limited (5 per minute)
- [ ] Users can log out and sessions are invalidated
- [ ] Password reset via email is available

## Technical Notes
- Use Laravel's built-in authentication
- Passwords must be hashed with bcrypt
- Sessions stored in Redis (already configured)
- Rate limiting via Laravel's throttle middleware
- Related to #100 (user profile) and #102 (permissions)

## Definition of Done
- [ ] Code written and working
- [ ] Tests written and passing
- [ ] Coverage threshold met (80% overall, 90% new code)
- [ ] Review passed (code + security + performance)
- [ ] Docs updated (CHANGELOG + README + inline)
- [ ] Issue closed with summary comment

<!-- WORKFLOW META -->
workflow_type: feature
project_type: existing
estimated_complexity: medium
related_issues: #100, #102
```

## Validation Errors

If an issue is missing required sections or labels, the workflow will:

1. Post a comment explaining what is missing
2. NOT proceed with implementation
3. Wait for issue to be updated

Common validation errors:
- Missing `workflow_type` in WORKFLOW META
- Missing acceptance criteria
- Status is `needs-confirmation` (run /triage first)
- Status is `rejected` (issue is closed)
- Blocked by related issue that is still open
