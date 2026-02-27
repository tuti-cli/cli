# workflow:discover
Analyze a new project or discovery document to detect stack, identify provider, and set up workflow configuration. Entry point for new project onboarding.

**Usage:**
- `/workflow:discover <file>` — Analyze discovery document
- `/workflow:discover` — Analyze existing codebase in current directory

**Pipeline:**
1. Read discovery document or analyze codebase
2. Detect technology stack
3. Identify issue provider (GitHub, Linear, etc.)
4. Recommend agents to install
5. Update CLAUDE.md with configuration
6. Create .workflow/PROJECT.md
7. Install recommended agents

**Output:**
- Updated CLAUDE.md with stack/provider info
- .workflow/PROJECT.md with full project documentation
- List of recommended agents to install

Invoke `project-analyst` then `description-writer`:
> "Analyze the project for workflow setup. GITHUB REPO: owner=tuti-cli repo=cli. IF file argument provided: read discovery document from '$ARGUMENTS'. ELSE: analyze existing codebase in current directory. Detect technology stack (languages, frameworks, databases), identify issue tracking provider, and recommend specific agents to install from the awesome-claude-code-subagents catalog. Update CLAUDE.md with detected configuration. Then invoke description-writer to create comprehensive .workflow/PROJECT.md with architecture, conventions, and testing config. Finally, present recommended agent list for installation via /agents:install."
