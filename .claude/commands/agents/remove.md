# agents:remove

> Remove an installed agent from local or global directory.

**Usage:**
- `/agents:remove <name>` — Remove agent (searches local first, then global)
- `/agents:remove <name> --local` — Remove from .claude/agents/ only
- `/agents:remove <name> --global` — Remove from ~/.claude/agents/ only

**When to use:**
- Removing unused agents
- Cleaning up after project completion
- Replacing with different agent

**Related commands:**
- `/agents:list` — See installed agents first
- `/agents:install` — Install replacement agent
- `/agents:search` — Find alternatives

**Examples:**
- `/agents:remove wordpress-master` — Remove WordPress agent
- `/agents:remove python-pro --global` — Remove global Python agent

**Protection:** Protected agents cannot be removed without --force flag.

Invoke `agent-installer`:
> "Remove agent '$ARGUMENTS' from installed agents. Check for --local or --global flag. Search for agent file in appropriate directory. IF agent is in protected list (master-orchestrator, issue-executor, issue-creator, issue-closer, agent-installer): require --force flag to remove. ELSE: delete the agent file. Confirm removal and report success. List remaining agents in same category if applicable."
