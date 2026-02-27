# triage
Triage external issues with status: needs-confirmation.

**Usage:** `/triage` or `/triage <N>`

Invoke `tuti-workflow-master`:
> "GITHUB REPO: owner=tuti-cli repo=cli — use --repo tuti-cli/cli for gh CLI commands. Run triage mode for: $ARGUMENTS. If no argument, list all issues with status: needs-confirmation. For each: analyze it and present four options: A=Confirm (add status:confirmed + type + effort, sync board to Confirmed), B=Confirm+Ready (add status:ready + acceptance criteria, sync to Ready), C=Reject (post rejection comment, close issue, sync to Rejected), D=Needs More Info (post comment listing what's missing). Wait for my choice before executing."
