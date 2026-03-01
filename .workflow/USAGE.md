# Tuti CLI V5 Workflow System - Usage Guide

> Complete guide to using the AI-powered development workflow system

## Table of Contents

1. [Quick Start](#quick-start)
2. [The Pipeline](#the-pipeline)
3. [Command Reference](#command-reference)
4. [Common Workflows](#common-workflows)
5. [Agent Auto-Selection](#agent-auto-selection)
6. [Quality Gates](#quality-gates)
7. [File Locations](#file-locations)
8. [Tips & Best Practices](#tips--best-practices)
9. [Troubleshooting](#troubleshooting)

---

## Quick Start

### Essential Commands

| Command | Purpose | When to Use |
|---------|---------|-------------|
| `/workflow:issue <N>` | Execute GitHub issue through pipeline | Starting any issue |
| `/workflow:commit` | Create conventional commit | After completing work |
| `/workflow:test` | Run/write tests | Testing your code |
| `/workflow:gate` | Run all quality gates | Before committing |
| `/workflow:issue <N> --dry-run` | Preview plan without executing | Before starting work |

### Getting Help

- Each command has built-in documentation in `.claude/commands/`
- Ask `/oracle <question>` for Claude Code help
- Check `CLAUDE.md` for project-specific conventions

---

## The Pipeline

Every issue flows through this **sequential 7-stage pipeline**:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           V5 WORKFLOW PIPELINE                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────┐   ┌──────────┐   ┌────────┐   ┌─────────┐   ┌────────┐   ┌────┐  │
│  │SETUP │──▶│IMPLEMENT │──▶│ REVIEW │──▶│ QUALITY │──▶│ COMMIT │──▶│ PR │  │
│  └──────┘   └──────────┘   └────────┘   └─────────┘   └────────┘   └────┘  │
│                                                      │                       │
│                                                      ▼                       │
│                                                  ┌────────┐                  │
│                                                  │ CLOSE  │                  │
│                                                  └────────┘                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Stage Details

| Stage | What Happens | Key Actions |
|-------|--------------|-------------|
| **SETUP** | Context loading, agent selection | Read CLAUDE.md, PROJECT.md, patches/, ADRs/ |
| **IMPLEMENT** | Code changes | Write code, create files, modify logic |
| **REVIEW** | Code review | code-reviewer, security-auditor agents |
| **QUALITY** | Quality gates | lint → types → tests → coverage |
| **COMMIT** | Conventional commit | Format: `type(scope): subject (#N)` |
| **PR** | Pull request | Create PR with description |
| **CLOSE** | Issue closure | Post summary, close issue |

### Pre-Flight Checks (Automatic)

Before any pipeline execution, the system reads:

1. **CLAUDE.md** - Project context, conventions, patterns
2. **.workflow/PROJECT.md** - Workflow-specific rules
3. **.workflow/patches/** - Lessons learned from past bug fixes
4. **.workflow/ADRs/** - Architecture Decision Records

---

## Command Reference

### Daily Development Commands

#### `/workflow:issue <N>`

Execute a GitHub issue through the complete pipeline.

**Syntax:**
```
/workflow:issue 42                    # Execute issue #42
/workflow:issue 42 --dry-run          # Preview plan only
/workflow:issue 42 --worktree         # Use isolated worktree
/workflow:issue 42 --quick            # Skip review stage
```

**What it does:**
1. Fetches issue from GitHub (tuti-cli/cli)
2. Validates requirements and labels
3. Selects appropriate agents based on type
4. Generates implementation plan
5. Executes pipeline stages
6. Creates PR and closes issue

**When to use:** Starting work on any GitHub issue

---

#### `/workflow:commit`

Create a conventional commit with proper formatting.

**Syntax:**
```
/workflow:commit                      # Interactive commit
/workflow:commit "fix bug"            # With message
/workflow:commit --pr                 # Create PR after commit
```

**Commit Format:**
```
<type>(<scope>): <subject> (#N)

Types: feat, fix, docs, style, refactor, test, chore
Example: feat(local): add port conflict detection (#42)
```

**When to use:** After completing work on an issue

---

#### `/workflow:test`

Run tests and optionally write missing tests.

**Syntax:**
```
/workflow:test                        # Run all tests
/workflow:test --filter "test name"   # Run specific test
/workflow:test --coverage             # With coverage report
/workflow:test --write                # Write missing tests
```

**When to use:** During development, before commits

---

#### `/workflow:gate`

Run all quality gates (lint → types → tests → coverage).

**Syntax:**
```
/workflow:gate                        # Run all gates
/workflow:gate --quick                # Skip coverage gate
/workflow:gate --fix                  # Auto-fix lint issues
```

**Gates run in order:**
1. `composer lint` (Laravel Pint)
2. `composer test:types` (PHPStan)
3. `composer test:unit` (Pest)
4. Coverage check (if not --quick)

**When to use:** Before every commit

---

### Planning & Discovery Commands

#### `/workflow:discover <file>`

Analyze a new project for technology stack and setup workflow.

**Syntax:**
```
/workflow:discover README.md          # Analyze from README
/workflow:discover notes.md           # Analyze from notes
```

**What it does:**
1. Detects technology stack (PHP, JS, etc.)
2. Identifies issue provider (GitHub, GitLab)
3. Creates/updates CLAUDE.md
4. Creates .workflow/PROJECT.md
5. Recommends agents to install

**When to use:** Onboarding a new project

---

#### `/workflow:audit`

Deep analysis of codebase for architecture, dependencies, quality.

**Syntax:**
```
/workflow:audit                       # Standard audit
/workflow:audit --legacy              # Legacy codebase audit
```

**What it does:**
1. Architecture analysis
2. Dependency audit
3. Code quality review
4. Security scan
5. Test coverage analysis
6. Documentation gaps

**Outputs:**
- `.workflow/AUDIT.md` - Full audit report
- `.workflow/TECH-DEBT.md` - Technical debt items
- GitHub issues for each finding

**When to use:** Regular codebase health checks, legacy migrations

---

#### `/workflow:feature`

Plan and execute feature implementations.

**Syntax:**
```
/workflow:feature                     # Interactive feature planning
/workflow:feature 42                  # Execute feature from issue
/workflow:feature --plan-only         # Create plan only
```

**What it does:**
1. Breaks feature into atomic tasks
2. Creates `.workflow/features/feature-N.md`
3. Assigns agents to tasks
4. Sets up commit checkpoints

**When to use:** Planning new features before implementation

---

#### `/workflow:push-plan`

Push workflow artifacts as GitHub issues.

**Syntax:**
```
/workflow:push-plan --audit           # Push AUDIT.md findings
/workflow:push-plan --features        # Push feature plans
/workflow:push-plan --phases          # Push migration phases
/workflow:push-plan --all             # Push everything
```

**When to use:** After running audit or planning features

---

### Bug Fix Commands

#### `/workflow:bugfix <N>`

Full bug fix pipeline with regression testing.

**Syntax:**
```
/workflow:bugfix 42                   # Standard bug fix
/workflow:bugfix 42 --hotfix          # Emergency hotfix
```

**What it does:**
1. Reproduces issue
2. Identifies root cause
3. Implements fix
4. Writes regression test
5. Documents in `.workflow/patches/`
6. Creates PR

**When to use:** Fixing reported bugs (issues with `type:bug` label)

---

#### `/workflow:fix`

Quick fixes without full pipeline (typos, simple corrections).

**Syntax:**
```
/workflow:fix "typo in README"        # Simple fix
/workflow:fix --patch fix.md          # Apply from patch file
```

**Still requires:**
- Quality gates
- Conventional commit

**When to use:** Minor corrections, typos, small improvements

---

### Architecture Commands

#### `/arch:brainstorm "<question>"`

Start architecture brainstorming session.

**Syntax:**
```
/arch:brainstorm "how to handle authentication?"
/arch:brainstorm "database migration strategy"
```

**What it does:**
1. Proposes 2-3 options with tradeoffs
2. Identifies risks and considerations
3. Outputs to `.workflow/proposals/`

**When to use:** Planning significant architectural changes

---

#### `/arch:decide "<decision>"`

Record architecture decisions as ADRs.

**Syntax:**
```
/arch:decide "use PostgreSQL for primary database"
/arch:decide --from-proposal proposal.md
/arch:decide --accept proposal.md
```

**What it does:**
1. Creates `.workflow/ADRs/NNNN-decision-name.md`
2. Documents context, decision, consequences
3. Links related issues

**When to use:** After architecture decisions are made

---

#### `/arch:challenge`

Challenge architecture proposals as devil's advocate.

**Syntax:**
```
/arch:challenge                       # Challenge latest proposal
/arch:challenge proposal.md           # Challenge specific proposal
```

**What it does:**
1. Identifies weaknesses
2. Finds edge cases
3. Proposes alternatives
4. Outputs to `.workflow/challenges/`

**When to use:** Reviewing architecture proposals

---

### Agent Management Commands

#### `/agents:install <name>`

Install agents from VoltAgent catalog.

**Syntax:**
```
/agents:install docker-expert         # Install locally
/agents:install docker-expert --global # Install globally
```

**Source:** [awesome-claude-code-subagents](https://github.com/VoltAgent/awesome-claude-code-subagents)

**When to use:** Need specialized agent capabilities

---

#### `/agents:list`

List all installed agents.

**Syntax:**
```
/agents:list                          # All agents
/agents:list --local                  # Local only
/agents:list --global                 # Global only
```

**When to use:** Checking available agents

---

#### `/agents:search <query>`

Search agents by name or description.

**Syntax:**
```
/agents:search docker                 # Search by keyword
/agents:search php --category dev     # Filter by category
```

**Categories:** core-development, infrastructure, quality-assurance, security, documentation, architecture, planning, deployment, maintenance, research-analysis

**When to use:** Finding agents for specific needs

---

#### `/agents:remove <name>`

Remove installed agents.

**Syntax:**
```
/agents:remove docker-expert          # Remove local
/agents:remove docker-expert --global # Remove global
/agents:remove master-orchestrator --force  # Remove protected
```

**Protected agents** (require --force):
- master-orchestrator
- issue-executor
- issue-creator
- issue-closer
- agent-installer
- workflow-orchestrator

**When to use:** Cleaning up unused agents

---

### Documentation Commands

#### `/workflow:docs`

Update documentation after implementation.

**Syntax:**
```
/workflow:docs                        # Update related docs
/workflow:docs --all                  # Update all docs
/workflow:docs --changelog            # Update CHANGELOG.md
/workflow:docs --readme               # Update README.md
```

**When to use:** After completing features/fixes

---

#### `/workflow:review`

Run code review with specialist agents.

**Syntax:**
```
/workflow:review                      # Standard review
/workflow:review --security           # Security-focused
/workflow:review --performance        # Performance-focused
/workflow:review --full               # All specialists
```

**When to use:** Before PR creation

---

### System Evolution Commands

#### `/workflow:evolve`

Improve agent definitions from accumulated patches.

**Syntax:**
```
/workflow:evolve                      # Process all patches
/workflow:evolve --recent             # Last 7 days only
/workflow:evolve --agent php-pro      # Specific agent
/workflow:evolve --dry-run            # Preview changes
```

**What it does:**
1. Reads patches from `.workflow/patches/`
2. Identifies recurring patterns
3. Updates agent checklists and rules
4. Makes workflow smarter over time

**When to use:** Periodically (weekly recommended)

---

#### `/workflow:improve`

Analyze and improve the workflow system itself.

**Syntax:**
```
/workflow:improve                     # General analysis
/workflow:improve --metrics           # Show performance data
/workflow:improve --apply             # Apply improvements
/workflow:improve "specific suggestion"
```

**When to use:** Optimizing workflow efficiency

---

## Common Workflows

### Starting a New Feature

```bash
# 1. Create issue on GitHub with label: type:feature
# 2. Preview the plan
/workflow:issue 42 --dry-run

# 3. Execute with worktree isolation
/workflow:issue 42 --worktree

# 4. Review generated plan, implement changes
# 5. Run quality gates
/workflow:gate

# 6. Create commit with PR
/workflow:commit --pr
```

---

### Fixing a Bug

```bash
# 1. Create issue with label: type:bug
# 2. Run bug fix pipeline
/workflow:bugfix 42

# 3. System will:
#    - Reproduce issue
#    - Find root cause
#    - Implement fix
#    - Write regression test
#    - Document in .workflow/patches/
#    - Create PR
```

---

### Quick Fix (Typos, Small Changes)

```bash
# 1. Make your changes
# 2. Run quick fix command
/workflow:fix "fix typo in README"

# 3. System handles commit automatically
```

---

### Planning Architecture

```bash
# 1. Start brainstorming
/arch:brainstorm "how to implement caching?"

# 2. Review proposals in .workflow/proposals/
# 3. Challenge if needed
/arch:challenge proposal-001.md

# 4. Make decision
/arch:decide "use Redis for caching"

# 5. ADR created in .workflow/ADRs/
```

---

### Auditing Codebase

```bash
# 1. Run comprehensive audit
/workflow:audit

# 2. Review results
#    - .workflow/AUDIT.md
#    - .workflow/TECH-DEBT.md

# 3. Create issues from findings
/workflow:push-plan --audit

# 4. GitHub issues created for each item
```

---

### Managing Agents

```bash
# 1. Search for needed agent
/agents:search docker

# 2. Install it
/agents:install docker-expert

# 3. List all installed
/agents:list

# 4. Remove if not needed
/agents:remove docker-expert
```

---

## Agent Auto-Selection

The workflow automatically selects the best agents based on issue labels and keywords.

### By Issue Type Label

| Label | Primary Agent | Secondary Agents |
|-------|---------------|------------------|
| `type:feature` | cli-developer | php-pro, laravel-specialist |
| `type:bug` | error-detective | code-reviewer, qa-expert |
| `type:chore` | refactoring-specialist | code-reviewer |
| `type:security` | security-auditor | code-reviewer |
| `type:performance` | performance-engineer | refactoring-specialist |
| `type:infra` | devops-engineer | deployment-engineer, build-engineer |
| `type:architecture` | architect-reviewer | refactoring-specialist |
| `type:docs` | documentation-engineer | - |
| `type:test` | qa-expert | php-pro |

### By Keywords (Auto-Enhanced)

These keywords in issue title/body add extra agents:

| Keywords | Adds Agent |
|----------|------------|
| docker, compose, container | devops-engineer |
| test, coverage, pest | qa-expert |
| refactor, clean, restructure | refactoring-specialist |
| security, vulnerability | security-auditor |
| performance, slow, optimize | performance-engineer |
| docs, documentation | documentation-engineer |
| database, migration, sql | database-administrator |
| deploy, release, ci/cd | deployment-engineer |
| dependency, composer, package | dependency-manager |

### Protected Agents

These core agents cannot be removed without `--force`:

1. **master-orchestrator** - Brain of workflow
2. **issue-executor** - Issue pipeline trigger
3. **issue-creator** - Creates GitHub issues
4. **issue-closer** - Closes issues with summary
5. **agent-installer** - Installs from catalog
6. **workflow-orchestrator** - Base orchestration

---

## Quality Gates

### Mandatory Gates (No Bypass)

Before every commit, these MUST pass:

```bash
composer lint && composer test
```

### Gate Details

| Gate | Command | What It Checks |
|------|---------|----------------|
| **Lint** | `composer lint` | PSR-12 formatting (Laravel Pint) |
| **Types** | `composer test:types` | PHPStan level 5+ |
| **Tests** | `composer test:unit` | Pest tests (parallel) |
| **Coverage** | `composer test:coverage` | Coverage thresholds |

### Coverage Thresholds

| Category | Threshold |
|----------|-----------|
| **Overall** | 80% |
| **New Code** | 90% |
| **Services** | 90% |
| **Commands** | 80% |

### Running All Gates

```bash
# Full gate check
/workflow:gate

# Quick (skip coverage)
/workflow:gate --quick

# Auto-fix lint issues
/workflow:gate --fix
```

---

## File Locations

### Workflow Directory Structure

```
.workflow/
├── PROJECT.md           # Project workflow configuration
├── USAGE.md             # This file
├── AUDIT.md             # Audit results (after /workflow:audit)
├── TECH-DEBT.md         # Technical debt tracking
├── patches/             # Bug fix documentation
│   └── YYYY-MM-DD-*.md  # Patch files by date
├── ADRs/                # Architecture Decision Records
│   └── NNNN-*.md        # Numbered ADR files
├── features/            # Feature plans
│   └── feature-N.md     # Per-issue feature plans
├── proposals/           # Architecture proposals
│   └── proposal-*.md    # Brainstorm proposals
└── challenges/          # Architecture challenges
    └── challenge-*.md   # Devil's advocate reviews
```

### Key Files

| File | Created By | Purpose |
|------|------------|---------|
| `PROJECT.md` | `/workflow:discover` | Workflow-specific rules |
| `AUDIT.md` | `/workflow:audit` | Full audit report |
| `TECH-DEBT.md` | `/workflow:audit` | Technical debt items |
| `patches/*.md` | `/workflow:bugfix` | Bug fix documentation |
| `ADRs/*.md` | `/arch:decide` | Architecture decisions |
| `features/*.md` | `/workflow:feature` | Feature implementation plans |

---

## Tips & Best Practices

### Before Starting Work

1. **Always use `--dry-run` first** for complex operations
   ```bash
   /workflow:issue 42 --dry-run
   ```

2. **Check issue labels** for correct agent selection
   - Use `type:feature`, `type:bug`, `type:chore`, etc.

3. **Review existing patches** for similar issues
   - Check `.workflow/patches/` for lessons learned

### During Implementation

1. **Use worktrees** for parallel work
   ```bash
   /workflow:issue 42 --worktree
   ```

2. **Run gates frequently**
   ```bash
   /workflow:gate --quick
   ```

3. **Write tests as you go**
   ```bash
   /workflow:test --write
   ```

### Before Committing

1. **Run full quality gates**
   ```bash
   /workflow:gate
   ```

2. **Review generated plan** matches your changes

3. **Use conventional commit format**
   ```bash
   /workflow:commit "feat(local): add feature"
   ```

### Periodic Maintenance

1. **Evolve agents weekly**
   ```bash
   /workflow:evolve --recent
   ```

2. **Audit codebase monthly**
   ```bash
   /workflow:audit
   ```

3. **Review tech debt regularly**
   ```bash
   /workflow:push-plan --audit
   ```

---

## Troubleshooting

### Issue: Pipeline fails at quality gate

**Solution:**
```bash
# Check which gate failed
/workflow:gate

# Fix lint issues automatically
/workflow:gate --fix

# Run specific test
/workflow:test --filter "test name"
```

---

### Issue: Wrong agent selected

**Solution:**
1. Check issue has correct type label
2. Add keyword hints to issue body
3. Use `--dry-run` to preview agent selection

---

### Issue: Cannot remove agent

**Solution:**
```bash
# Protected agents require --force
/agents:remove master-orchestrator --force
```

---

### Issue: Workflow files not created

**Solution:**
1. Ensure `.workflow/` directory exists
2. Check write permissions
3. Run `/workflow:discover` first

---

### Issue: GitHub authentication fails

**Solution:**
1. Check `gh auth status`
2. Ensure repo access: `tuti-cli/cli`
3. Verify GITHUB_TOKEN if using MCP

---

### Issue: Tests fail unexpectedly

**Solution:**
```bash
# Run single test to debug
docker compose exec -T app ./vendor/bin/pest --filter "test name"

# Check for environment issues
docker compose exec -T app composer test:types
```

---

## GitHub Repository Info

- **Owner:** tuti-cli
- **Repo:** cli
- **Full:** tuti-cli/cli
- **gh CLI:** Always use `--repo tuti-cli/cli`
- **GitHub MCP:** Always use `owner="tuti-cli" repo="cli"`

---

## Related Documentation

- `CLAUDE.md` - Project conventions and patterns
- `.workflow/PROJECT.md` - Workflow-specific configuration
- `.claude/agents/` - Agent definitions
- `.claude/commands/` - Command definitions
- `.claude/skills/` - Skill definitions

---

*Generated: 2026-02-27*
*Version: v5*
*Project: tuti-cli*
