# Architecture Decision Records (ADRs)

This directory contains Architecture Decision Records that document significant architectural decisions.

## Purpose

ADRs capture the context, decision, and consequences of important architectural choices. They provide:
- Historical context for why decisions were made
- Trade-offs considered
- Impact on the system

## Naming Convention

```
NNNN-<slug>.md
```

Examples:
- `0001-docker-compose-pattern.md`
- `0002-service-stub-format.md`
- `0003-container-naming-strategy.md`

## ADR Template

```markdown
# ADR-NNNN: <Title>

## Status
Proposed | Accepted | Deprecated | Superseded

## Context
What is the issue that we're seeing that is motivating this decision?

## Decision
What is the change that we're proposing and/or doing?

## Consequences
What becomes easier or more difficult because of this change?

## Related
- Issue #N (if applicable)
- Related ADRs
```

## When to Create an ADR

- Choosing between multiple architectural approaches
- Introducing a new pattern or convention
- Making a significant change to the codebase structure
- Deciding on third-party integrations

## Related

- `.claude/commands/arch/decide.md` - Command to create ADRs
- `.claude/agents/architecture-recorder.md` - Agent that records decisions
