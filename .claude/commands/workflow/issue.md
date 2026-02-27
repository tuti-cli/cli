# workflow:issue
Execute a GitHub issue through the complete workflow pipeline: setup → implement → review → test → commit → PR → close.

**Usage:**
- `/workflow:issue <N>` — Full sequential pipeline (default)
- `/workflow:issue <N> --dry-run` — Show plan without executing
- `/workflow:issue <N> --worktree` — Full pipeline in isolated worktree
- `/workflow:issue <N> --quick` — Quick mode: skip review stage, minimal checks

**Pipeline Stages:**
1. SETUP — Create branch, label in-progress, sync board
2. IMPLEMENT — Primary agent implements code
3. REVIEW — code-reviewer + specialists review changes
4. QUALITY GATE — Run `composer lint && composer test`
5. COMMIT — Self-review diff, commit with issue reference
6. PR — Push, create draft PR, mark ready, label review
7. CLOSE — Post summary, close issue

**Agent Squad:**
Automatically selected based on issue type label and keywords.

| Type Label | Primary | Secondary |
|------------|---------|-----------|
| `type:feature` | cli-developer | php-pro, laravel-specialist |
| `type:bug` | error-detective | code-reviewer, qa-expert |
| `type:chore` | refactoring-specialist | code-reviewer |
| `type:security` | security-auditor | code-reviewer |
| `type:performance` | performance-engineer | refactoring-specialist |

Invoke `issue-executor` then `master-orchestrator`:
> "GITHUB REPO: owner=tuti-cli repo=cli. Execute issue #$ARGUMENTS. Check for --dry-run, --worktree, --quick flags. **IF --dry-run:** read issue, validate requirements, form agent squad, present implementation plan WITHOUT executing. **ELSE:** invoke issue-executor to fetch and validate issue, enrich context from .workflow/patches/ and .workflow/ADRs/, then hand off to master-orchestrator for pipeline execution. IF --worktree: create isolated worktree at .claude/worktrees/<N>-slug/. IF --quick: skip review stage, run minimal quality checks. After successful PR merge, invoke issue-closer to post summary and close issue."
