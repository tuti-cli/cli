# implement
Implement a GitHub issue end-to-end: plan → code → tests → commit → PR.

**Usage:** `/implement <issue-number>` · `/implement --worktree <issue-number>`

Invoke `tuti-workflow-master`:
> "GITHUB REPO: owner=tuti-cli repo=cli — use --repo tuti-cli/cli for gh CLI commands. Implement issue #$ARGUMENTS. Pre-check: if status is needs-confirmation, stop and say to run /triage first. If status is rejected, stop and show rejection reason. Otherwise: fetch issue + parent epic + spec files, determine agent squad from type label, generate IMPLEMENTATION PLAN and wait for approval. After approval: (1) If --worktree flag present: create worktree + branch, otherwise work in current directory with new branch; (2) Label in-progress and sync board; (3) Delegate to agent squad; (4) AFTER EVERY FILE EDIT/WRITE: run `composer lint` to auto-fix formatting; (5) BEFORE EVERY COMMIT: run `composer test` (rector + pint + phpstan + pest) and fix any failures; (6) Self-review diff, commit with issue reference, push, create draft PR, mark ready, label review, sync board, post PR link comment."
