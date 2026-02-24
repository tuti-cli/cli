---
name: oracle-core
description: Claude Code expert knowledge base for CLI, MCP, Agent SDK, hooks, and best practices. Use when answering questions about Claude Code features.
---

# Oracle Core Skill

This skill provides comprehensive knowledge about Claude Code for the Oracle command.

## Overview

Claude Code is Anthropic's official CLI tool for Claude. It enables AI-assisted software development with features including:

- Interactive coding sessions with Claude
- Custom skills and slash commands
- MCP (Model Context Protocol) server integration
- Hooks for workflow automation
- Agent SDK for building custom agents
- IDE integrations
- Worktrees for parallel development

## Current Version Support

### Claude Models

| Model | Description | Alternative |
|-------|-------------|-------------|
| Claude Opus 4.6 | Most capable, complex tasks | GLM-5 |
| Claude Sonnet 4.6 | Balanced performance | GLM-4.7 |
| Claude Haiku 4.5 | Fast, lightweight | - |

### Platform Support

- Linux, macOS, Windows (WSL)
- **Node.js**: 18+ recommended
- **Updated**: 2026-02-24

---

## CLI Reference

### Essential Commands

```bash
claude                           # Start interactive session
claude -p "prompt"               # Single prompt mode
claude --continue                # Continue last session
claude --resume <id>             # Resume specific session
claude --dangerously-skip-permissions  # Auto-approve tools
claude --print                   # Print mode (non-interactive)
claude --help                    # Show help
claude --version                 # Show version
```

### Key Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+C` | Cancel current input/action |
| `Ctrl+D` | Exit Claude Code |
| `!` | Run shell command |
| `/` | Invoke slash command/skill |
| `#` | Add memory to CLAUDE.md |
| `@` | Mention/attach file |

### Environment Variables

- `ANTHROPIC_API_KEY` - API key for Claude
- `CLAUDE_CODE_MAX_TOKENS` - Max output tokens
- `CLAUDE_CODE_MODEL` - Default model override

---

## Hooks

Hooks automate workflows at specific points in Claude Code execution.

### Hook Types

| Hook | When It Runs |
|------|--------------|
| PreToolUse | Before tool execution |
| PostToolUse | After tool execution |
| Notification | On notifications |
| Stop | On session stop |
| PreCompact | Before context compaction |

### Hook Configuration

Hooks are configured in `.claude/settings.json`:

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Bash",
        "hooks": ["~/.claude/hooks/pre-bash.sh"]
      }
    ],
    "PostToolUse": [
      {
        "matcher": "Write",
        "hooks": ["~/.claude/hooks/post-write.sh"]
      }
    ]
  }
}
```

### Best Practices

- Keep hooks fast (< 2 seconds)
- Use matchers to target specific tools
- Return JSON for blocking decisions
- Log hook activity for debugging
- Handle errors gracefully

---

## MCP (Model Context Protocol)

MCP servers extend Claude Code with external tools and data sources.

### Server Types

| Type | Transport | Use Case |
|------|-----------|----------|
| stdio | stdin/stdout | Local tools, simple setup |
| http | HTTP requests | Remote services |
| sse | Server-Sent Events | Real-time updates |

### Configuration

MCP servers are configured in `.mcp.json`:

```json
{
  "mcpServers": {
    "my-server": {
      "command": "node",
      "args": ["path/to/server.js"],
      "env": {
        "API_KEY": "your-key"
      }
    }
  }
}
```

### Tool Definition Pattern

```javascript
server.tool(
  "tool_name",
  "Tool description",
  {
    param1: z.string().describe("Parameter description"),
    param2: z.number().optional()
  },
  async (params) => {
    // Implementation
    return { content: [{ type: "text", text: "Result" }] };
  }
);
```

### Best Practices

- Validate all inputs with Zod schemas
- Handle errors and return meaningful messages
- Keep tool descriptions clear and concise
- Use resources for large data, tools for actions
- Implement proper authentication for remote servers

---

## Skills

Skills encapsulate reusable workflows and domain knowledge.

### Structure

```
~/.claude/skills/my-skill/
├── SKILL.md          # Main skill definition
└── references/       # Optional reference files
```

### SKILL.md Format

```markdown
---
name: my-skill
description: What this skill does. Used for auto-invocation.
---

# Skill Title

Detailed instructions for the skill...

## Sections
- As needed for organization
```

### Best Practices

- Write clear, specific descriptions for auto-invocation
- Keep skills focused on single domain/purpose
- Use references for large supporting documentation
- Include examples in the skill content
- Test auto-invocation with realistic queries

---

## Agent SDK

Build custom agents with fine-grained control over behavior.

### Agent Types

| Type | Description |
|------|-------------|
| Bash | Command execution |
| general-purpose | Research and multi-step tasks |
| Explore | Fast codebase exploration |
| Plan | Implementation planning |
| code-simplifier | Code refinement |

### Key Concepts

- **Tools**: What the agent can use
- **Model**: Sonnet, Opus, or Haiku
- **Isolation**: Worktree or session isolation
- **Max Turns**: API round-trip limit

### Best Practices

- Choose the smallest capable model
- Limit tools to what's needed
- Use isolation for parallel work
- Set appropriate max_turns
- Handle agent results asynchronously

---

## CLAUDE.md

Project-specific instructions for Claude.

### Best Practices

```markdown
# Project Name

Brief description of the project.

## Tech Stack
- Language, framework, key dependencies

## Commands
- Build: `npm run build`
- Test: `npm test`
- Lint: `npm run lint`

## Architecture
Key patterns and structure.

## Conventions
- Code style preferences
- Naming conventions
- Patterns to follow/avoid

## Important Files
- Paths to key configuration files
```

### Tips

- Keep it concise (< 200 lines recommended)
- Be specific about preferences
- Include context for AI assistance
- Update as project evolves
- Use for team-wide conventions

---

## Worktrees

Parallel development with isolated working directories.

### Use Cases

- Running multiple agents simultaneously
- Isolating feature development
- Testing without affecting main branch

### Commands

```bash
# Create worktree
git worktree add ../feature-branch feature-branch

# List worktrees
git worktree list

# Remove worktree
git worktree remove ../feature-branch
```

### Claude Code Integration

- Use `EnterWorktree` tool for session isolation
- Claude manages worktrees in `.claude/worktrees/`
- Each worktree has its own `.claude/` context

---

## Essential Files

| File | Purpose |
|------|---------|
| `CLAUDE.md` | Project instructions for Claude |
| `.claude/settings.json` | User/global settings |
| `.claude/hooks/` | Hook scripts |
| `.claude/skills/` | Custom skills |
| `.claude/keybindings.json` | Keyboard customization |
| `.mcp.json` | MCP server configuration |

---

## Getting Help

- `/help` - Built-in help command
- `/cc:oracle <question>` - Ask Oracle anything
- GitHub Issues: https://github.com/anthropics/claude-code/issues
- Documentation: https://docs.anthropic.com/claude-code
