# Git Conventions

## Conventional Commits

Format:
```
<type>(<scope>): <subject>
```

### Types

| Type | When |
|------|------|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `style` | Formatting, whitespace |
| `refactor` | Code restructure, no behaviour change |
| `test` | Adding/updating tests |
| `chore` | Maintenance, dependencies |
| `perf` | Performance improvement |
| `ci` | CI/CD configuration |

### Rules
- Subject line: max 72 characters
- Imperative mood: "add user" not "added user"
- Issue reference `(#N)` required in issues mode

## Branch Naming

| Work Type | Pattern |
|-----------|---------|
| Feature | `feature/<N>-slug` |
| Bug fix | `fix/<N>-slug` |
| Refactor | `refactor/<N>-slug` |
| Chore | `chore/<N>-slug` |
| Hotfix | `hotfix/<N>-slug` |

### Rules
- Branch from `main` — never from another branch
- Pull latest before creating branch
- Issue number required in issues mode

## Commit Behaviour
- Never create commits automatically — only when explicitly requested
- Never push to remote without explicit user request
- Never force push without explicit approval
- Always show diff to let user review before committing

## Pull Requests

### Before PR
- All tests pass locally
- Quality gates pass (lint, types, tests)
- No debug code left in
- Self-reviewed diff

### PR Description
- Never mention AI tools in PR title or body
- Never include change statistics
- Focus on what changed and why
- Link to issue (`Closes #N`)

### Rules
- Create as draft first, then mark ready
- Never force-push to open PR

## Never Commit
```
.env, .env.*, *.key, storage/logs/,
node_modules/, vendor/, *.local, .DS_Store
```
