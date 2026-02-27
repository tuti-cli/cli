# Tuti CLI — Development Workflow

## Quick Reference

| Command | What it does |
|---|---|
| `/triage [N]` | Triage external issues (needs-confirmation) |
| `/discover` | Codebase analysis → phases → GitHub import |
| `/implement <N>` | Sequential pipeline: setup → code → review → test → PR |
| `/implement --worktree <N>` | Same pipeline in isolated worktree |
| `/implement --quick <N>` | Quick mode: skip review, minimal checks |
| `/status` | Dashboard: milestones, in-progress, review, worktrees |
| `/switch [N]` | List or switch to issue worktrees |
| `/board setup\|sync\|view` | Manage GitHub Projects kanban board |
| `/improve-workflow "..."` | Improve workflow (full flow: issue + PR) |
| `/improve-workflow --no-issue "..."` | Improve workflow (quick: no issue, no commits) |

## Issue Lifecycle

```
External issue (needs-confirmation) → /triage → confirmed/ready
Discovery issue → /discover → ready
Manual issue → create with acceptance criteria → ready
    ↓
/implement <N> [--worktree] [--quick]
    ↓
Plan mode (your approval required)
    ↓
┌─────────────────────────────────────────────────────────────┐
│                  SEQUENTIAL PIPELINE                         │
├─────────────────────────────────────────────────────────────┤
│  1. SETUP     → Create branch, label in-progress, sync board │
│  2. IMPLEMENT → Primary agent writes code                    │
│  3. REVIEW    → code-reviewer checks changes                 │
│  4. QUALITY   → composer lint && composer test               │
│  5. COMMIT    → Self-review, commit with issue reference     │
│  6. PR        → Push, draft PR, ready, label review          │
└─────────────────────────────────────────────────────────────┘
    ↓
You merge → issue auto-closes
```

**Quick mode (`--quick`):** Skips step 3 (review), use for trivial changes.

## Status Labels & Board Columns

| Label | Board Column |
|---|---|
| `status: needs-confirmation` | 🔶 Inbox |
| `status: confirmed` | ✅ Confirmed |
| `status: rejected` | ❌ Rejected |
| `status: ready` | 📋 Ready |
| `status: in-progress` | 🔨 In Progress |
| `status: blocked` | 🚫 Blocked |
| `status: review` | 👀 In Review |
| *(closed)* | ✅ Done |

## Type Labels (drives agent selection)

| Label | Primary Agent |
|---|---|
| `type: feature` | cli-developer |
| `type: bug` | error-detective |
| `type: chore` | refactoring-specialist |
| `type: security` | security-auditor |
| `type: performance` | performance-engineer |
| `type: infra` | devops-engineer |
| `type: architecture` | architect-reviewer |
| `type: docs` | documentation-engineer |
| `type: test` | qa-expert |
| `type: epic` | NOT implemented directly |

## Agent Selection Rules

### Primary Agent Selection (by type label)

| Type Label | Primary Agent | Secondary Agent(s) |
|---|---|---|
| `type: feature` | cli-developer | php-pro, laravel-specialist |
| `type: bug` | error-detective | code-reviewer, qa-expert |
| `type: chore` | refactoring-specialist | code-reviewer |
| `type: security` | security-auditor | code-reviewer |
| `type: performance` | performance-engineer | refactoring-specialist |
| `type: infra` | devops-engineer | deployment-engineer, build-engineer |
| `type: architecture` | architect-reviewer | refactoring-specialist |
| `type: docs` | documentation-engineer | - |
| `type: test` | qa-expert | php-pro |
| `type: epic` | NOT implemented directly | Split into sub-issues |

### Task-Based Agent Enhancement

When issue body or title contains specific keywords, add supplementary agents:

| Keywords | Additional Agent |
|---|---|
| docker, compose, container | devops-engineer |
| test, coverage, pest | qa-expert |
| refactor, clean, restructure | refactoring-specialist |
| security, vulnerability, audit | security-auditor |
| performance, slow, optimize | performance-engineer |
| docs, documentation, readme | documentation-engineer |
| database, migration, sql | database-administrator |
| deploy, release, ci/cd | deployment-engineer |
| dependency, composer, package | dependency-manager |

### Agent Squad Formation Rules

1. **Always include:** Primary agent (from type label)
2. **Add secondary agents:** Based on type label mapping
3. **Add task-based agents:** Based on keyword detection in issue content
4. **De-duplicate:** Each agent appears only once in squad
5. **Order matters:** Agents are invoked in list order

## Conventions

**Branch naming:** `feature/<N>-slug` · `bug/<N>-slug` · `hotfix/<N>-slug` · `chore/<N>-slug` · `security/<N>-slug`

**Commits:** `feat(local): description (#N)` · `fix(deploy): description (#N)`
Scopes: `local` `deploy` `env` `projects` `config` `core` `build` `commands` `workflow`

**Quality gates (all must pass before commit):**
```bash
composer lint && composer test
```

This runs:
- **lint:** Pint (formatting) + Rector (refactoring) — auto-fixes
- **test:** PHPStan (static analysis) + Pest (unit tests)

**When checks run:**
- **Before commit** (Quality Gate stage): Full lint + test suite
- **CI/CD:** Same checks run in GitHub Actions

## Improving the Workflow

### Full Flow
```bash
/improve-workflow "what you want to change"
```
Creates issue → implements → commits → creates PR.

### Quick Inline Mode
```bash
/improve-workflow --no-issue "quick fix"
```
Plan → approve → edit files in current branch. No issue, no commits. Use for small tweaks.

## Workflow Files

All these files must stay synchronized:

| File | Purpose |
|------|---------|
| `.claude/agents/tuti-workflow-master.md` | Agent definition and flows |
| `WORKFLOW.md` | This documentation |
| `.claude/commands/*.md` | Command definitions |
| `.claude/settings.json` | Permissions and hooks |
| `CLAUDE.md` | Agent/skill documentation section |

Always use `/improve-workflow` to make changes — it ensures all files stay in sync.

Full spec: `.claude/agents/tuti-workflow-master.md`

## Roadmap

### Completed

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | GitHub-centric workflow with agent selection | ✅ Done |
| 2 | Sequential pipeline (setup → code → review → test → PR) | ✅ Done |

### Planned

| Phase | Description | When |
|-------|-------------|------|
| 3 | Self-improvement loop (`/fix` → patch → `/evolve`) | After 20-30 repeated patterns |
| 4 | Expand agent library (from awesome-subagents) | When more specialists needed |
| 5 | Provider abstraction (Linear, ClickUp, Jira) | Only if real friction emerges |

### Design Philosophy

**Stay simple until real pain emerges.** Build based on actual friction, not anticipated friction.

- **Phase 3** waits until you have 20+ patches showing repeated mistake patterns
- **Phase 5** (provider abstraction) is deferred indefinitely — the GitHub-centric approach works well
- Agent selection via type labels + keywords is sufficient for current scale

### References

- [Azure AI Agent Orchestration Patterns](https://learn.microsoft.com/en-us/azure/architecture/ai-ml/guide/ai-agent-design-patterns) — Sequential, Concurrent, Group Chat, Handoff, Magentic patterns
- [VoltAgent/awesome-claude-code-subagents](https://github.com/VoltAgent/awesome-claude-code-subagents) — 127+ pre-built agents across 10 categories:
  - **Core Development**: api-designer, backend-developer, frontend-developer, fullstack-developer, graphql-architect
  - **Languages**: php-pro, laravel-specialist, typescript-pro, python-pro, golang-pro, rust-engineer
  - **Infrastructure**: docker-expert, kubernetes-specialist, terraform-engineer, sre-engineer
  - **Quality & Security**: code-reviewer, security-auditor, qa-expert, performance-engineer
  - **Dev Experience**: cli-developer, build-engineer, dependency-manager, documentation-engineer
  - **Orchestration**: multi-agent-coordinator, workflow-orchestrator, task-distributor
  - **Research**: research-analyst, competitive-analyst, trend-analyst
- [lee-to/ai-factory](https://github.com/lee-to/ai-factory) — Self-improvement loop inspiration

**When adding new agents:** Use the `agent-installer` agent to browse and install from the catalog, or check [awesome-claude-code-subagents](https://github.com/VoltAgent/awesome-claude-code-subagents) directly. Use `/improve-workflow` to integrate new agents into the workflow.
