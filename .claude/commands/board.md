# board
Manage the GitHub Projects kanban board.

**Usage:** `/board setup` · `/board sync` · `/board view`

Invoke `tuti-workflow-master`:
> "GITHUB REPO: owner=tuti-cli repo=cli — use --repo tuti-cli/cli for gh CLI commands. Run GitHub Projects board mode: $ARGUMENTS. SETUP: create/configure board with 8 columns (Inbox/Confirmed/Ready/In Progress/Blocked/In Review/Done/Rejected) and custom fields (Phase text, Effort single-select S/M/L/XL, Type single-select). SYNC: fetch all open issues, move each to correct column based on status label, set Phase from milestone, Effort/Type from labels. VIEW: list issues grouped by column with counts, highlight Inbox count and Blocked count."
