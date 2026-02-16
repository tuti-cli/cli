---
name: agent-creator
description: Guide for creating effective subagents. This skill should be used when users want to create a new agent (or update an existing agent) that can autonomously execute complex, multi-step tasks with its own tool set and model assignment.
---

# Agent Creator

This skill provides guidance for creating effective subagents.

## About Agents

Subagents are autonomous workers with their own tool set, model assignment, and thinking process. Unlike skills (which provide knowledge), agents can independently execute complex workflows, make decisions, and complete tasks end-to-end.

### What Agents Provide

1. **Autonomous execution** - Completes multi-step tasks without hand-holding
2. **Specialized tooling** - Has its own curated tool set for the domain
3. **Model selection** - Can use different models for different tasks
4. **Isolated context** - Works independently without cluttering main conversation

## Agents vs Skills

| Aspect | Skills | Agents |
|--------|--------|--------|
| **Purpose** | Provide knowledge & patterns | Execute tasks autonomously |
| **Control** | User drives the work | Agent drives the work |
| **Context** | Loaded into current conversation | Has isolated context |
| **Best for** | Guidelines, reference, patterns | Complex multi-step workflows |

**Use Skills when:**
- Providing domain knowledge and patterns
- User wants to drive the work
- Need reference material and guidelines

**Use Agents when:**
- Task is complex and multi-step
- Want autonomous execution
- Need specialized tool set
- Want to parallelize work

## Core Principles

### 1. Clear Scope Definition

Agents need crystal-clear boundaries. Define:
- What the agent SHOULD do
- What the agent should NOT do
- When to hand back to user

### 2. Appropriate Tool Selection

Give agents only the tools they need:

| Tools | Use Case |
|-------|----------|
| `Read, Write, Edit, MultiEdit` | File operations |
| `Grep, Glob, LS` | Code exploration |
| `Bash` | Command execution |
| `WebSearch, WebFetch` | Research |
| `Task` | Delegating to sub-agents |
| `mcp__*` | MCP integrations |

### 3. Model Selection

| Model | Best For |
|-------|----------|
| `glm-5` | Complex reasoning, architecture, planning |
| `glm-4.7` | Fast execution, simple tasks |

### 4. Structured Output

Agents should provide clear deliverables:
- Summary of what was done
- Files created/modified
- Next steps for user

## Anatomy of an Agent

```
agent-name.md
├── YAML frontmatter (required)
│   ├── name: agent-identifier
│   ├── description: When and why to use this agent
│   ├── tools: List of available tools
│   └── model: Model assignment (glm-5, glm-4)
└── Markdown body (required)
    ├── Role definition
    ├── Expertise areas
    ├── Key capabilities
    ├── Workflow/guidelines
    └── Expected deliverables
```

## Agent Creation Process

### Step 1: Define the Agent's Purpose

Ask yourself:
- What specific problem does this agent solve?
- What tasks should it handle autonomously?
- What are the boundaries (what should it NOT do)?

### Step 2: Choose Tools and Model

Select tools based on what the agent needs:
- File work → Read, Write, Edit, MultiEdit
- Code exploration → Grep, Glob, LS
- Execution → Bash
- Research → WebSearch, WebFetch
- Complex workflows → Task (for sub-delegation)

### Step 3: Write the Agent Definition

Use the template below to create the agent file.

### Step 4: Test and Iterate

1. Run the agent on a sample task
2. Check if it stays in scope
3. Verify output quality
4. Refine guidelines as needed

## Agent Template

```markdown
---
name: agent-name
description: [Clear description of when to use this agent. Include specific triggers and scenarios.]
tools: [Read, Write, Edit, MultiEdit, Grep, Glob, Bash, LS, Task]
model: glm-5
---

# Agent Name

**Role**: [One-line description of the agent's role]

**Expertise**: [List of expertise areas]

**Key Capabilities**:
- [Capability 1]: [Description]
- [Capability 2]: [Description]
- [Capability 3]: [Description]

## Core Development Philosophy

[Include relevant principles from project - see references/agent-patterns.md]

## Workflow

### 1. [First Step]
[Detailed instructions]

### 2. [Second Step]
[Detailed instructions]

### 3. [Third Step]
[Detailed instructions]

## Expected Deliverables

When complete, provide:
- [ ] [Deliverable 1]
- [ ] [Deliverable 2]
- [ ] [Deliverable 3]

## Boundaries

**DO:**
- [What the agent should do]

**DO NOT:**
- [What the agent should NOT do]

**HAND BACK TO USER:**
- [When to ask for user input or clarification]
```

## Common Agent Patterns

See `references/agent-patterns.md` for:
- Architect agents (design-focused)
- Builder agents (implementation-focused)
- Auditor agents (review-focused)
- Engineer agents (domain-specific)

## Scripts

This agent-creator includes helper scripts:

### init_agent.py
```bash
python .claude/agent-creator/scripts/init_agent.py my-agent --path .claude/agents
```
Creates a new agent file from template with all required sections.

### validate_agent.py
```bash
python .claude/agent-creator/scripts/validate_agent.py .claude/agents/my-agent.md
```
Validates agent structure, frontmatter, and required sections.

### package_agent.py
```bash
python .claude/agent-creator/scripts/package_agent.py .claude/agents/my-agent.md
```
Packages agent into a distributable .agent file.