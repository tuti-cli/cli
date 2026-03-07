# workflow:commit

> Create a conventional commit with proper formatting and optional PR.

**Usage:**
- `/workflow:commit` — Interactive commit with diff review
- `/workflow:commit "message"` — Commit with specified message
- `/workflow:commit --pr` — Commit and create PR

**When to use:**
- Ready to commit current changes
- Want to create PR after commit
- Need help writing conventional commit message

**Related commands:**
- `/workflow:issue` — Full pipeline for GitHub issue
- `/workflow:pr` — Just create PR (if commit exists)
- `/arch:decide` — Document architecture before commit

**Commit Format:**
```
<type>(<scope>): <subject> (#N)

[optional body]
```

**Types:** `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

**Examples:**
- `feat(auth): add user login flow (#123)`
- `fix(api): resolve timeout crash (#456)`
- `refactor(config): simplify env handling (#789)`

**Quality Gates (run before commit):**
- `composer lint` — Must pass
- `composer test` — Must pass

Invoke `git-workflow-manager`:
> "Create a conventional commit for this repository. GITHUB REPO: owner=tuti-cli repo=cli. First run `composer lint && composer test` to ensure quality gates pass. Then review the staged changes with `git diff --staged`. Generate a commit message following Conventional Commits format with type(scope): subject. Include issue reference if related to an open issue. IF --pr flag: after commit, push to origin and create PR via gh CLI. Confirm commit success."
