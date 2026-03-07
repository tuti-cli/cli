# agents:install

> Install an agent from the VoltAgent catalog.

**Usage:**
- `/agents:install <name>` — Install agent locally (default)
- `/agents:install <name> --global` — Install to ~/.claude/agents/
- `/agents:install <name> --local` — Install to .claude/agents/

**When to use:**
- Need a specialist agent for a specific task
- Want to extend capabilities with domain experts
- Installing workflow agents (php-pro, docker-expert, etc.)

**Related commands:**
- `/agents:search` — Find available agents
- `/agents:list` — See installed agents
- `/agents:remove` — Remove an agent

**Examples:**
- `/agents:install php-pro` — Install PHP specialist locally
- `/agents:install laravel-specialist --global` — Install globally
- `/agents:install docker-expert` — Install Docker expert

**Source:** https://github.com/VoltAgent/awesome-claude-code-subagents

Invoke `agent-installer`:
> "Install agent '$ARGUMENTS' from the awesome-claude-code-subagents catalog. Check for --global or --local flag (default: local). Fetch agent file from https://raw.githubusercontent.com/VoltAgent/awesome-claude-code-subagents/main/categories/{category}/{name}.md. Save to appropriate directory (local: .claude/agents/, global: ~/.claude/agents/). Verify installation by reading the file. Report success with agent name, description, and location."
