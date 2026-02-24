# implement
Implement a GitHub issue end-to-end: plan → worktree → code → tests → commit → PR.

**Usage:** `/implement <issue-number>`

Invoke `tuti-workflow-master`:
> "Implement issue #$ARGUMENTS. Pre-check: if status is needs-confirmation, stop and say to run /triage first. If status is rejected, stop and show rejection reason. Otherwise: fetch issue + parent epic + spec files, determine agent squad from type label, generate IMPLEMENTATION PLAN and wait for approval, then create worktree + branch, label in-progress, sync board, delegate to agent squad, run quality suite (composer test + composer lint + phpstan), self-review diff, commit, push, create draft PR, mark ready, label review, sync board, post PR link comment."
