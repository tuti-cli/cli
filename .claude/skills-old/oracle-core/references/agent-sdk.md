# Claude Agent SDK Guide

Guide to building custom agents using the Claude Agent SDK.

## Overview

The Claude Agent SDK enables building specialized AI agents that can use Claude's capabilities with custom tool configurations and behaviors.

## Agent Types

### Built-in Agent Types

1. **Bash** - Command execution specialist
2. **general-purpose** - Multi-step research and tasks
3. **Explore** - Codebase exploration
4. **Plan** - Implementation planning
5. **claude-code-guide** - Claude Code expert
6. **code-simplifier** - Code refinement

### Defining Custom Agents

Custom agents are defined in markdown files with specific frontmatter:

```markdown
---
name: my-agent
description: Agent description here
tools: Read, Grep, Glob, Bash
model: sonnet
---

# Agent Instructions

Your agent instructions go here...
```

## Agent File Structure

### Location

- Global: `~/.claude/agents/my-agent.md`
- Project: `.claude/agents/my-agent.md`
- Plugin: `~/.claude/plugin-name/agents/my-agent.md`

### Frontmatter Fields

| Field | Required | Description |
|-------|----------|-------------|
| `name` | No | Agent identifier (defaults to filename) |
| `description` | No | Brief description of agent purpose |
| `tools` | No | Comma-separated list of allowed tools |
| `model` | No | Model to use (haiku, sonnet, opus) |
| `max_turns` | No | Maximum conversation turns |

### Available Tools

**File Operations:**
- `Read` - Read file contents
- `Write` - Create/overwrite files
- `Edit` - Make targeted edits
- `Glob` - Pattern-based file search
- `Grep` - Content search

**Execution:**
- `Bash` - Shell command execution
- `Task` - Launch subagents

**Web:**
- `WebSearch` - Search the web
- `WebFetch` - Fetch web content

**Notebook:**
- `NotebookEdit` - Edit Jupyter notebooks

**User Interaction:**
- `AskUserQuestion` - Ask questions
- `ExitPlanMode` - Exit planning mode

**Task Management:**
- `TaskCreate`, `TaskGet`, `TaskUpdate`, `TaskList`

## Creating Agents

### Simple Agent

```markdown
---
description: Analyze code complexity
tools: Read, Grep, Glob
---

You are a code complexity analyzer. Your job is to:

1. Read the specified files
2. Analyze cyclomatic complexity
3. Identify complex functions
4. Suggest refactoring opportunities

Provide a structured report with complexity scores.
```

### Agent with Subagents

```markdown
---
description: Full project analysis
tools: Read, Grep, Glob, Task
---

You are a project analyzer. Use subagents for specialized tasks:

1. Use Explore agent to understand project structure
2. Analyze code patterns and architecture
3. Use general-purpose agent for research

Combine findings into a comprehensive report.
```

## Invoking Agents

### Via Task Tool

```json
{
  "subagent_type": "my-agent",
  "prompt": "Analyze the authentication module",
  "description": "Analyze auth module"
}
```

### Agent Parameters

```typescript
{
  subagent_type: string,      // Agent type name
  prompt: string,             // Task description
  description?: string,       // Short task summary (3-5 words)
  model?: 'haiku' | 'sonnet' | 'opus',
  resume?: string,            // Resume previous agent session
  run_in_background?: boolean,
  max_turns?: number
}
```

## Patterns

### Research Agent

```markdown
---
description: Research specialized topics
tools: WebSearch, WebFetch, Read
model: sonnet
---

Research the given topic thoroughly:

1. Search for relevant information
2. Fetch and read key sources
3. Synthesize findings
4. Provide citations

Be thorough and accurate.
```

### Code Review Agent

```markdown
---
description: Review code for issues
tools: Read, Grep, Glob
---

Review code for:

1. Security vulnerabilities (OWASP Top 10)
2. Code smells and anti-patterns
3. Performance issues
4. Best practice violations

Provide specific, actionable feedback with file:line references.
```

### Planning Agent

```markdown
---
description: Plan implementation strategies
tools: Read, Grep, Glob, AskUserQuestion
model: sonnet
---

Create implementation plans:

1. Understand requirements
2. Explore existing codebase
3. Identify integration points
4. Design solution architecture
5. Create step-by-step plan
6. Consider edge cases and error handling

Present plans with clear steps and dependencies.
```

## Best Practices

### 1. Specific Tool Lists

```markdown
# Good - specific tools
tools: Read, Grep, Glob

# Avoid - overly broad access
tools: *
```

### 2. Clear Instructions

```markdown
# Good
You are a security scanner. Check for:
- SQL injection vulnerabilities
- XSS vulnerabilities
- CSRF protection

Report findings with severity levels.

# Bad
You check for security issues.
```

### 3. Appropriate Model Selection

- Use `haiku` for quick, simple tasks
- Use `sonnet` for balanced performance
- Use `opus` for complex reasoning

### 4. Limit Turns for Focused Tasks

```markdown
---
max_turns: 5
---
```

### 5. Handle Errors Gracefully

Include instructions for handling:
- Missing files
- Search failures
- Unexpected formats

## Communication

### Agent to Main Context

Agents return results via the Task tool:

```json
{
  "status": "completed",
  "result": "Analysis complete. Found 3 issues..."
}
```

### Progress Updates

For long-running agents, provide periodic updates in output.

## Testing Agents

1. Test with various input types
2. Verify tool restrictions work
3. Check error handling
4. Validate output format
5. Test edge cases

## Example: Complete Agent

```markdown
---
name: api-analyzer
description: Analyze REST API implementations
tools: Read, Grep, Glob, WebFetch
model: sonnet
max_turns: 20
---

# API Analyzer Agent

Analyze REST API implementations for best practices.

## Process

1. **Discovery**
   - Find all API endpoint definitions
   - Identify routing configuration
   - Map endpoint structure

2. **Analysis**
   - Check HTTP method usage
   - Verify status code conventions
   - Analyze request/response formats
   - Check authentication patterns

3. **Report**
   - Summary of endpoints found
   - Best practice compliance score
   - Specific issues with recommendations
   - Security considerations

## Output Format

Provide a structured report:
- Executive summary
- Endpoint inventory
- Issues by category
- Recommendations prioritized by impact
```
