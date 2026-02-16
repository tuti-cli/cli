# Agent Patterns

This document describes common patterns for creating effective agents in Tuti CLI.

## Agent Types

### 1. Architect Agents

**Purpose**: Design and planning, making architectural decisions.

**Characteristics**:
- Focuses on analysis and design
- Provides recommendations and blueprints
- May not write code directly
- Often uses sequential thinking for complex decisions

**Example Structure**:
```markdown
# Stack Architect

**Role**: Designs and plans new stack templates

## Workflow
1. Analyze requirements
2. Design stack structure
3. Plan service integrations
4. Create implementation blueprint
5. Hand off to builder agent or user

## Output
- Stack design document
- Service list with configurations
- Implementation checklist
```

### 2. Builder Agents

**Purpose**: Implementation, creating files, writing code.

**Characteristics**:
- Focuses on execution and implementation
- Creates and modifies files
- Follows established patterns
- Validates work with tests

**Example Structure**:
```markdown
# Feature Builder

**Role**: Implements new features following established patterns

## Workflow
1. Understand requirements
2. Identify existing patterns
3. Implement feature
4. Write tests
5. Validate with test suite

## Output
- Implemented feature
- Test coverage
- Documentation updates
```

### 3. Auditor Agents

**Purpose**: Review, analysis, quality assurance.

**Characteristics**:
- Focuses on finding issues
- Analyzes code quality
- Suggests improvements
- Does not modify files directly

**Example Structure**:
```markdown
# Code Auditor

**Role**: Reviews code for quality, security, and best practices

## Workflow
1. Scan target files
2. Check coding standards
3. Identify potential issues
4. Generate report

## Output
- Issues found (categorized by severity)
- Recommendations
- Suggested fixes
```

### 4. Engineer Agents

**Purpose**: Domain-specific implementation with deep expertise.

**Characteristics**:
- Deep knowledge in specific domain
- Handles complex domain-specific tasks
- Makes domain-appropriate decisions
- Full implementation capability

**Example Structure**:
```markdown
# Test Engineer

**Role**: Achieves test coverage targets with comprehensive tests

## Workflow
1. Analyze target code
2. Identify untested paths
3. Write comprehensive tests
4. Run test suite
5. Iterate until coverage target met

## Output
- Test files
- Coverage report
- Summary of tested scenarios
```

## Tool Selection Guide

### Minimal Tool Set (Read-only analysis)
```
tools: [Read, Grep, Glob, LS]
```
Use for: Auditors, Analysts

### Standard Tool Set (File operations)
```
tools: [Read, Write, Edit, MultiEdit, Grep, Glob, Bash, LS]
```
Use for: Builders, Engineers

### Full Tool Set (Including research)
```
tools: [Read, Write, Edit, MultiEdit, Grep, Glob, Bash, LS, WebSearch, WebFetch, Task]
```
Use for: Architects, Complex workflows

### With MCP Integration
```
tools: [Read, Write, Edit, MultiEdit, Grep, Glob, Bash, LS, Task, mcp__context7__*]
```
Use for: Research-heavy tasks

## Model Selection Guide

| Task Type | Recommended Model | Reason |
|-----------|------------------|--------|
| Complex architecture decisions | `glm-5` | Better reasoning |
| Multi-step planning | `glm-5` | Better planning |
| Simple file operations | `glm-4` | Faster execution |
| Routine refactoring | `glm-4` | Adequate for patterns |
| Test writing | `glm-4` | Follows templates well |

## Boundary Patterns

### Clear Scope Definition
```markdown
## Boundaries

**DO:**
- Create files within `app/Services/` directory
- Follow existing naming conventions
- Write tests for new services

**DO NOT:**
- Modify files outside designated directories
- Change interfaces without approval
- Skip writing tests

**HAND BACK TO USER:**
- When multiple valid approaches exist
- When requirements are ambiguous
- When breaking changes might be needed
```

### Handoff Pattern
```markdown
## Handoff Protocol

When task is complete or blocked:
1. Summarize what was accomplished
2. List files created/modified
3. Note any decisions made
4. Describe next steps or blockers
5. Ask user for input if needed
```

## Common Anti-Patterns

### Too Broad Scope
```markdown
**Role**: Handles everything related to the project
```
**Problem**: Agent will be unfocused and unpredictable.

### Focused Scope
```markdown
**Role**: Creates new stack templates with Docker Compose configurations
```

### Missing Boundaries
```markdown
## Workflow
1. Do the task
2. Complete it
```
**Problem**: No guidance on what "complete" means.

### Clear Deliverables
```markdown
## Expected Deliverables
- [ ] Created stack.json
- [ ] Created docker-compose.yml
- [ ] Created Dockerfile
- [ ] Created .env.dev.example
- [ ] Registered in stacks/registry.json
```

### Wrong Tool Set
```markdown
tools: [Read, Write, Bash, WebSearch, WebFetch, Task, mcp__*]
```
**Problem**: Too many tools for a focused agent.

### Appropriate Tool Set
```markdown
tools: [Read, Write, Edit, Grep, Glob, LS, Bash]
```

## Tuti CLI Specific Patterns

### Working with Docker Commands
All Docker commands must use array syntax:
```bash
# Correct
docker compose exec -T app composer test:unit

# In agents, always validate before running
```

### File Location Reference
| Type | Location |
|------|----------|
| Commands | `app/Commands/{Category}/` |
| Services | `app/Services/{Domain}/` |
| Tests | `tests/Unit/`, `tests/Feature/` |
| Stacks | `stubs/stacks/{stack}/` |
| Service Stubs | `stubs/stacks/{stack}/services/` |

### Validation Commands
```bash
docker compose exec -T app composer test:unit   # Run tests
docker compose exec -T app composer test:types  # PHPStan check
docker compose exec -T app composer lint        # Fix code style
docker compose exec -T app composer test        # Full test suite
```

## Agent Description Best Practices

The description in frontmatter determines WHEN the agent is triggered. Write clear, comprehensive descriptions.

### Good Description Examples

```markdown
description: Creates complete stack templates autonomously. Use when adding a new framework stack (Laravel, WordPress, Drupal, etc.) with Docker Compose configurations, service stubs, and all required files. Handles the entire stack creation process from analysis to registration.
```

```markdown
description: Reviews code for quality, security vulnerabilities, and best practices compliance. Use when you need a thorough code audit, before merging PRs, or when refactoring existing code. Provides detailed reports with severity ratings and suggested fixes.
```

### Bad Description Examples

```markdown
description: Helps with code.  # Too vague
```

```markdown
description: An agent that does things related to Docker and PHP and testing and stuff.  # Unfocused
```

## Workflow Patterns

### Sequential Workflow
For tasks with clear steps:
```markdown
## Workflow

### 1. Analysis Phase
[Detailed instructions]

### 2. Planning Phase
[Detailed instructions]

### 3. Execution Phase
[Detailed instructions]

### 4. Validation Phase
[Detailed instructions]
```

### Decision-Based Workflow
For tasks with branching logic:
```markdown
## Workflow

1. **Determine task type:**
   - New feature? → Follow "New Feature" workflow
   - Bug fix? → Follow "Bug Fix" workflow
   - Refactor? → Follow "Refactor" workflow

2. **New Feature Workflow:**
   [steps]

3. **Bug Fix Workflow:**
   [steps]
```
