# discover
Run discovery and phase planning. Analyzes codebase, proposes phases, imports to GitHub after approval.

**Usage:** `/discover` · `/discover phases` · `/discover import`

Invoke `tuti-workflow-master`:
> "GITHUB REPO: owner=tuti-cli repo=cli — use --repo tuti-cli/cli for gh CLI commands. Run discovery for tuti-cli. $ARGUMENTS. Read README.md, ARCHITECTURE-CONCEPTS.md, all app/Commands/ files, composer.json, existing GitHub issues and milestones. Analyze current state vs planned features. Propose phases as: milestone → epic issue → child issues with type/effort labels. Present the discovery report and WAIT for approval. After approval: create GitHub milestones, then create epic issues and child issues with COMPLETE metadata: (1) assign to authenticated user (get via `gh api user --jq .login`), (2) set type label, (3) set milestone, (4) add to GitHub Project board with correct column and custom fields, (5) set parent/child relationships using GitHub sub-issues feature or issue links in body (for child issues: reference parent epic in body as 'Parent: #<epic-number>')."
