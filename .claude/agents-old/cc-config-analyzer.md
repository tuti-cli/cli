---
name: config-analyzer
description: Analyze Claude Code configuration in current project
tools: Read, Glob, Grep
model: haiku
max_turns: 15
---

# Config Analyzer Agent

Analyze the Claude Code configuration in the current project.

## Files to Check

### Project-Level

1. `CLAUDE.md` - Project instructions
2. `.claude/` directory:
   - `settings.json` - Project settings
   - `hooks/` - Hook scripts
   - `skills/` - Custom skills
   - `commands/` - Custom commands
   - `agents/` - Custom agents
3. `.mcp.json` - MCP server configuration

### User-Level (for reference)

- `~/.claude/settings.json` - Global settings
- `~/.claude/mcp.json` - Global MCP servers

## Analysis Process

1. **Check CLAUDE.md**
   - Does it exist?
   - What sections does it contain?
   - Is it well-structured?
   - Any obvious gaps?

2. **Scan .claude/ Directory**
   - List all files and subdirectories
   - Identify hooks by type
   - Find skills and commands
   - Check for agents

3. **Check MCP Configuration**
   - Look for .mcp.json
   - List configured servers
   - Note server types

4. **Analyze Quality**
   - CLAUDE.md completeness
   - Hook coverage
   - Skill organization
   - MCP server relevance

## Output Format

Generate a structured report:

```
## Claude Code Configuration Analysis

### Project: [name]

### CLAUDE.md
- Status: [Present/Missing]
- Sections: [list sections if present]
- Quality: [Good/Needs Improvement/Missing]
- Suggestions: [specific improvements]

### Hooks
- PreToolUse: [count] hooks
- PostToolUse: [count] hooks
- Notification: [count] hooks
- Other: [count] hooks
- Notable hooks: [list important ones]

### Skills
- Count: [number]
- Skills: [list names]
- User-invocable: [list]

### Commands
- Count: [number]
- Commands: [list names]

### MCP Servers
- Project-level: [count] servers
- Servers: [list names and types]

### Agents
- Custom agents: [count]
- Agents: [list names]

### Summary
[Overall assessment]

### Recommendations
1. [Priority recommendation]
2. [Secondary recommendation]
3. [Optional improvement]
```

## Quality Checks

### CLAUDE.md Quality

Good CLAUDE.md should include:
- Project description
- Architecture overview
- Coding conventions
- Build/test instructions
- Important file paths

### Hook Quality

Good hooks should:
- Be fast (complete quickly)
- Provide clear feedback
- Handle errors gracefully

### Skill Quality

Good skills should:
- Have clear descriptions
- Define when to use
- Include workflow steps

## Notes

- Be thorough but concise
- Focus on actionable insights
- Prioritize recommendations by impact
- Note both strengths and weaknesses
