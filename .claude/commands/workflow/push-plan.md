# workflow:push-plan
Push workflow artifacts to GitHub as issues. Creates issues from audit findings, feature plans, or migration phases.

**Usage:**
- `/workflow:push-plan --audit` — Push all TECH-DEBT.md items
- `/workflow:push-plan --features` — Push all .workflow/features/ plans
- `/workflow:push-plan --phases` — Push migration phases
- `/workflow:push-plan --all` — Push everything pending

**What Gets Pushed:**

| Flag | Source | Creates |
|------|--------|---------|
| `--audit` | TECH-DEBT.md | One issue per debt item |
| `--features` | features/*.md | One issue per feature |
| `--phases` | migration-*.md | One issue per phase |
| `--all` | All above | All issues |

**Issue Format:**
Each issue includes:
- Summary from artifact
- Context and background
- Acceptance criteria
- Technical notes
- WORKFLOW META section

**Labels Applied:**
- `workflow:refactor` (audit findings)
- `workflow:feature` (features)
- `workflow:modernize` (migration phases)
- Priority from severity/effort
- `source:audit|plan|architecture`

Invoke `issue-creator` in batch mode:
> "Push workflow artifacts to GitHub issues. GITHUB REPO: owner=tuti-cli repo=cli. Parse flags from '$ARGUMENTS'. **IF --audit:** read .workflow/TECH-DEBT.md and create one issue per debt item with workflow:refactor label, priority mapped from severity, and source:audit label. **IF --features:** glob .workflow/features/*.md and create issue for each with workflow:feature label. **IF --phases:** read migration plan and create issue per phase with workflow:modernize label. **IF --all:** execute all three. Apply correct labels, link related issues, and return list of created issue numbers."
