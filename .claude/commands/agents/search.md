# agents:search
Search for agents in the awesome-claude-code-subagents catalog by name or description.

**Usage:**
- `/agents:search <query>` — Search agents by keyword
- `/agents:search <query> --category <cat>` — Search within category

**Examples:**
- `/agents:search php` — Find PHP-related agents
- `/agents:search docker --category infra` — Find Docker agents in infra category
- `/agents:search test` — Find testing-related agents

**Categories:**
- 01-core-development
- 02-language-specialists
- 03-infrastructure
- 04-quality-security
- 05-data-ai
- 06-developer-experience
- 07-specialized-domains
- 08-business-product
- 09-meta-orchestration
- 10-research-analysis

Invoke `agent-installer`:
> "Search for agents matching '$ARGUMENTS' in the awesome-claude-code-subagents catalog. Fetch the README.md from https://raw.githubusercontent.com/VoltAgent/awesome-claude-code-subagents/main/README.md. Parse agent listings and filter by query. Present results in a table with agent name, description, and category. IF --category flag: filter results to specified category only. Show up to 20 results."
