# discover
Run discovery and phase planning. Analyzes codebase, proposes phases, imports to GitHub after approval.

**Usage:** `/discover` · `/discover phases` · `/discover import`

Invoke `tuti-workflow-master`:
> "GITHUB REPO: owner=tuti-cli repo=cli — use --repo tuti-cli/cli for gh CLI commands. Run discovery for tuti-cli. $ARGUMENTS. Read README.md, ARCHITECTURE-CONCEPTS.md, all app/Commands/ files, composer.json, existing GitHub issues and milestones. Analyze current state vs planned features. Propose phases as: milestone → epic issue → child issues with type/effort labels. Present the discovery report and WAIT for approval. After approval: create GitHub milestones, epic issues, child issues, add all to GitHub Project board with correct column and custom fields."
