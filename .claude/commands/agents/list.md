# agents:list

> List all installed agents with their status.

**Usage:**
- `/agents:list` — List all installed agents
- `/agents:list --local` — List local agents only (.claude/agents/)
- `/agents:list --global` — List global agents only (~/.claude/agents/)

**When to use:**
- Checking what agents are available
- Verifying agent installation
- Managing agent inventory

**Related commands:**
- `/agents:search` — Find new agents
- `/agents:install` — Install an agent
- `/agents:remove` — Remove an agent

**Output Format:**
```
| Agent | Location | Description |
|-------|----------|-------------|
| php-pro | local | PHP 8.4+ development expert |
| laravel-specialist | local | Laravel 10+ framework expert |
| code-reviewer | global | Code quality guardian |
```

Invoke `agent-installer`:
> "List all installed Claude Code agents. Glob .claude/agents/*.md for local agents. Glob ~/.claude/agents/*.md for global agents. IF --local: show only local. IF --global: show only global. ELSE: show both. For each agent, extract name and description from YAML frontmatter. Present in a formatted table with location indicator. Also show total count of agents."
