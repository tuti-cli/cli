# implement
Implement a GitHub issue end-to-end with sequential pipeline: plan → code → review → test → PR.

**Usage:**
- `/implement <N>` — Full sequential pipeline (default)
- `/implement --worktree <N>` — Full pipeline in isolated worktree
- `/implement --quick <N>` — Quick mode: skip review stage, minimal checks

Invoke `tuti-workflow-master`:
> "GITHUB REPO: owner=tuti-cli repo=cli — use --repo tuti-cli/cli for gh CLI commands. Implement issue #$ARGUMENTS. Check for flags: --worktree, --quick. Pre-check: if status is needs-confirmation, stop and say to run /triage first. If status is rejected, stop and show rejection reason. Otherwise: fetch issue + parent epic + spec files, determine agent squad from type label, generate IMPLEMENTATION PLAN with SEQUENTIAL STAGES and wait for approval. After approval, execute pipeline:

**IF --quick flag:**
1. Create branch (or worktree + branch if --worktree)
2. Label in-progress, sync board
3. Implement (primary agent only)
4. Run `composer lint && composer test`
5. Commit, push, create PR

**ELSE (default sequential pipeline):**
1. **SETUP:** Create branch (or worktree + branch if --worktree), label in-progress, sync board
2. **IMPLEMENT:** Primary agent implements code
3. **REVIEW:** code-reviewer reviews changes (concurrent with test if possible)
4. **QUALITY GATE:** Run `composer lint && composer test`, fix any failures
5. **COMMIT:** Self-review diff, commit with issue reference
6. **PR:** Push, create draft PR, mark ready, label review, sync board, post PR link comment"
