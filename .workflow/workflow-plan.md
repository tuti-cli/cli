# Workflow Migration Plan — Full v5 Spec Replacement

## Overview

Full replacement of current workflow system with v5 spec, tailored for tuti-cli (PHP/Laravel Zero CLI project).

**Migration Strategy:**
- Rename old files with `--old` prefix
- Create new v5 spec files
- After each phase: **Spec Compliance Check** — compare with v5, document differences and impact
- Delete `--old` files only after everything works

**Branch:** `workflow/v5-migration`

---

## Phase 0 — Backup

### Actions
| Step | Action |
|------|--------|
| 0.1 | Rename `.claude/commands/implement.md` → `--old-implement.md` |
| 0.2 | Rename `.claude/commands/discover.md` → `--old-discover.md` |
| 0.3 | Rename `.claude/commands/triage.md` → `--old-triage.md` |
| 0.4 | Rename `.claude/commands/status.md` → `--old-status.md` |
| 0.5 | Rename `.claude/commands/switch.md` → `--old-switch.md` |
| 0.6 | Rename `.claude/commands/board.md` → `--old-board.md` |
| 0.7 | Rename `.claude/commands/improve-workflow.md` → `--old-improve-workflow.md` |
| 0.8 | Rename `.claude/agents/tuti-workflow-master.md` → `--old-tuti-workflow-master.md` |
| 0.9 | Create `.workflow/` directory structure |
| 0.10 | Create `.workflow/PROJECT.md` (initial) |

### Spec Compliance Check (Phase 0)
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| `.workflow/` directory | ✅ Created | No | — |
| Backup old files | ✅ `--old` prefix | No | — |

**Differences:** None
**Impact:** None

---

## Phase A — Core System

### Actions
| Step | Action |
|------|--------|
| A1 | BUILD `master-orchestrator.md` |
| A2 | BUILD `issue-executor.md` |
| A3 | BUILD `issue-creator.md` |
| A4 | BUILD `issue-closer.md` |
| A5 | IMPORT `workflow-orchestrator.md` |
| A6 | IMPORT `multi-agent-coordinator.md` |
| A7 | IMPORT `git-workflow-manager.md` |
| A8 | Create `.claude/commands/workflow/issue.md` |
| A9 | Create `.claude/commands/workflow/commit.md` |
| A10 | Create `.claude/commands/workflow/create-issue.md` |
| A11 | Create `.claude/commands/agents/install.md` |
| A12 | Create `.claude/commands/agents/search.md` |
| A13 | Create `.claude/commands/agents/list.md` |
| A14 | Create `.claude/commands/agents/remove.md` |
| A15 | Update `CLAUDE.md` with workflow section |
| A16 | Create `skills/workflow-rules.md` |
| A17 | Create `skills/issue-template.md` |

### Spec Compliance Check (Phase A)
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| `master-orchestrator.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `issue-executor.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `issue-creator.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `issue-closer.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `workflow-orchestrator.md` ✅ IMPORT | ✅ Installed from VoltAgent catalog | No | — |
| `multi-agent-coordinator.md` ✅ IMPORT | ✅ Installed from VoltAgent catalog | No | — |
| `git-workflow-manager.md` ✅ IMPORT | ✅ Installed from VoltAgent catalog | No | — |
| `/workflow:issue` command | ✅ Created | No | — |
| `/workflow:commit` command | ✅ Created | No | — |
| `/workflow:create-issue` command | ✅ Created | No | — |
| `/agents:install` command | ✅ Created | No | — |
| `/agents:remove` command | ✅ Created | No | — |
| `/agents:search` command | ✅ Created | No | — |
| `/agents:list` command | ✅ Created | No | — |
| `skills/workflow-rules.md` | ✅ Created | No | — |
| `skills/issue-template.md` | ✅ Created | No | — |
| CLAUDE.md updated | ✅ Updated with v5 workflow section | No | — |

**Differences:** None - all BUILD agents follow VoltAgent styling conventions
**Impact:** None - full v5 spec compliance achieved for Phase A

---

## Phase B — Discovery & Planning

### Actions
| Step | Action |
|------|--------|
| B1 | BUILD `project-analyst.md` |
| B2 | BUILD `description-writer.md` |
| B3 | BUILD `codebase-auditor.md` |
| B4 | BUILD `tech-debt-mapper.md` |
| B5 | BUILD `feature-planner.md` |
| B6 | BUILD `task-decomposer.md` |
| B7 | BUILD `migration-planner.md` |
| B8 | IMPORT `research-analyst.md` |
| B9 | IMPORT `data-researcher.md` |
| B10 | IMPORT `project-manager.md` |
| B11 | IMPORT `context-manager.md` |
| B12 | IMPORT `task-distributor.md` |
| B13 | Create `.claude/commands/workflow/discover.md` |
| B14 | Create `.claude/commands/workflow/audit.md` |
| B15 | Create `.claude/commands/workflow/feature.md` |
| B16 | Create `.claude/commands/workflow/push-plan.md` |
| B17 | Create `skills/audit-checklist.md` |

### Spec Compliance Check (Phase B)
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| `project-analyst.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `description-writer.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `codebase-auditor.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `tech-debt-mapper.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `feature-planner.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `task-decomposer.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `migration-planner.md` ★ BUILD | ✅ Created - follows VoltAgent style | No | — |
| `research-analyst.md` ✅ IMPORT | ✅ Installed from VoltAgent catalog | No | — |
| `data-researcher.md` ✅ IMPORT | ✅ Installed from VoltAgent catalog | No | — |
| `project-manager.md` ✅ IMPORT | ✅ Installed from VoltAgent catalog | No | — |
| `context-manager.md` ✅ IMPORT | ✅ Installed from VoltAgent catalog | No | — |
| `task-distributor.md` ✅ IMPORT | ✅ Installed from VoltAgent catalog | No | — |
| `/workflow:discover` command | ✅ Created | No | — |
| `/workflow:audit` command | ✅ Created | No | — |
| `/workflow:feature` command | ✅ Created | No | — |
| `/workflow:push-plan` command | ✅ Created | No | — |
| `skills/audit-checklist.md` | ✅ Created | No | — |

**Differences:** None - all BUILD agents follow VoltAgent styling conventions
**Impact:** None - full v5 spec compliance achieved for Phase B

---

## Phase C — Testing

### Actions
| Step | Action |
|------|--------|
| C1 | BUILD `test-engineer.md` (Pest-aware) |
| C2 | BUILD `coverage-guardian.md` |
| C3 | IMPORT `test-automator.md` |
| C4 | IMPORT `chaos-engineer.md` |
| C5 | Create `.claude/commands/workflow/test.md` |
| C6 | Create `.claude/commands/workflow/coverage.md` |
| C7 | Create `.claude/commands/workflow/gate.md` |
| C8 | Create `skills/stack/php-laravel-zero.md` |

### Spec Compliance Check (Phase C)
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| `test-engineer.md` ★ BUILD | | | |
| `coverage-guardian.md` ★ BUILD | | | |
| `/workflow:test` command | | | |
| `/workflow:coverage` command | | | |
| `/workflow:gate` command | | | |
| Stack-specific skill file | | | |

**Differences:** *(fill after phase completion)*

**PHP/Laravel Zero Adaptations:**
- Test framework: Pest (not Jest)
- Coverage command: `composer test:coverage`
- Lint command: `composer lint`
- Test paths: `tests/Unit/`, `tests/Feature/`

**Impact:** Adapted for PHP stack — no negative impact, actually better fit

---

## Phase D — Documentation

### Actions
| Step | Action |
|------|--------|
| D1 | BUILD `doc-updater.md` |
| D2 | IMPORT `api-documenter.md` |
| D3 | IMPORT `technical-writer.md` |
| D4 | Create `.claude/commands/workflow/docs.md` |

### Spec Compliance Check (Phase D)
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| `doc-updater.md` ★ BUILD | | | |
| `/workflow:docs` command | | | |

**Differences:** *(fill after phase completion)*

**CLI Tool Adaptations:**
- No API docs (CLI tool, not REST API)
- Doc triggers: CLAUDE.md, README.md, registry files

**Impact:** Simplified for CLI context — appropriate

---

## Phase E — Review & Fix

### Actions
| Step | Action |
|------|--------|
| E1 | IMPORT `debugger.md` |
| E2 | IMPORT `error-coordinator.md` |
| E3 | BUILD `patch-writer.md` |
| E4 | Create `.claude/commands/workflow/bugfix.md` |
| E5 | Create `.claude/commands/workflow/fix.md` |
| E6 | Create `.claude/commands/workflow/review.md` |

### Spec Compliance Check (Phase E)
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| `patch-writer.md` ★ BUILD | | | |
| `/workflow:bugfix` command | | | |
| `/workflow:fix` command | | | |
| `/workflow:review` command | | | |

**Differences:** *(fill after phase completion)*
**Impact:** *(fill after phase completion)*

---

## Phase F — Architecture

### Actions
| Step | Action |
|------|--------|
| F1 | BUILD `architecture-lead.md` |
| F2 | BUILD `architecture-challenger.md` |
| F3 | BUILD `architecture-recorder.md` |
| F4 | IMPORT `knowledge-synthesizer.md` |
| F5 | Create `.claude/commands/arch/brainstorm.md` |
| F6 | Create `.claude/commands/arch/decide.md` |
| F7 | Create `.claude/commands/arch/adr.md` |
| F8 | Create `.claude/commands/arch/review.md` |
| F9 | Create `skills/adr-template.md` |

### Spec Compliance Check (Phase F)
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| `architecture-lead.md` ★ BUILD | | | |
| `architecture-challenger.md` ★ BUILD | | | |
| `architecture-recorder.md` ★ BUILD | | | |
| `/arch:brainstorm` command | | | |
| `/arch:decide` command | | | |
| `/arch:adr` command | | | |
| `/arch:review` command | | | |

**Differences:** *(fill after phase completion)*
**Impact:** *(fill after phase completion)*

---

## Phase G — Self-Improvement

### Actions
| Step | Action |
|------|--------|
| G1 | BUILD `skill-evolver.md` |
| G2 | BUILD `workflow-improver.md` |
| G3 | Create `.claude/commands/workflow/evolve.md` |
| G4 | Create `.claude/commands/workflow/improve.md` |

### Spec Compliance Check (Phase G)
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| `skill-evolver.md` ★ BUILD | | | |
| `workflow-improver.md` ★ BUILD | | | |
| `/workflow:evolve` command | | | |
| `/workflow:improve` command | | | |

**Differences:** *(fill after phase completion)*
**Impact:** *(fill after phase completion)*

---

## Phase H — Additional Imports

### Actions
| Step | Action |
|------|--------|
| H1 | IMPORT `legacy-modernizer.md` |
| H2 | IMPORT `docker-expert.md` |
| H3 | IMPORT `sql-pro.md` |
| H4 | IMPORT `sre-engineer.md` |
| H5 | IMPORT `performance-monitor.md` |
| H6 | Test all imports work correctly |

### Spec Compliance Check (Phase H)
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| All required imports | | | |

**Differences:** *(fill after phase completion)*
**Impact:** *(fill after phase completion)*

---

## Phase I — Cleanup

### Actions
| Step | Action |
|------|--------|
| I1 | **ONLY AFTER ALL TESTS PASS** |
| I2 | Delete `.claude/commands/--old-*.md` |
| I3 | Delete `.claude/agents/--old-*.md` |
| I4 | Update `WORKFLOW.md` to v5 spec |
| I5 | Final commit |

### Final Spec Compliance Check
| v5 Spec Requirement | Our Implementation | Diff? | Impact |
|---------------------|-------------------|-------|--------|
| All commands work | | | |
| All pipelines execute | | | |
| Coverage guardian works | | | |
| Doc-updater works | | | |
| Issue-closer works | | | |

**Final Differences Summary:**
*(fill after all phases complete)*

**Final Impact Assessment:**
*(fill after all phases complete)*

---

## v5 Spec Reference — Expected Structure

### Commands (v5 spec expects)
```
/workflow:discover FILE
/workflow:audit [--legacy]
/workflow:issue 123 [--dry-run]
/workflow:feature
/workflow:bugfix
/workflow:test
/workflow:coverage
/workflow:docs
/workflow:review
/workflow:gate
/workflow:commit
/workflow:fix "description"
/workflow:push-plan [--audit|--features|--phases|--all]
/workflow:create-issue
/workflow:evolve
/workflow:improve [topic] [--add|--fix|--history]
/arch:brainstorm "topic"
/arch:decide "problem"
/arch:adr "title"
/arch:review
/agents:install NAME
/agents:remove NAME
/agents:search QUERY
/agents:list
```

### Agents (v5 spec expects)
**BUILD (20):**
- master-orchestrator, issue-executor, issue-creator, issue-closer, workflow-improver
- project-analyst, codebase-auditor, description-writer, tech-debt-mapper
- architecture-lead, architecture-challenger, architecture-recorder
- feature-planner, task-decomposer, migration-planner
- test-engineer, coverage-guardian
- doc-updater
- patch-writer, skill-evolver

**IMPORT minimum (~28):**
- agent-installer, workflow-orchestrator, multi-agent-coordinator
- context-manager, task-distributor, knowledge-synthesizer
- error-coordinator, agent-organizer, pied-piper
- debugger, error-detective, qa-expert, devops-engineer
- code-reviewer, security-auditor, performance-engineer
- documentation-engineer, api-documenter, technical-writer
- git-workflow-manager
- + stack-specific (php-pro, laravel-specialist for us)

### Pipelines (v5 spec expects)
1. New Project Discovery
2. Existing Project Onboarding
3. Legacy Modernization
4. Feature Implementation
5. Bug Fix
6. Refactor
7. Legacy Modernization Task
8. Architecture Brainstorm
9. Self-Improvement

---

## Estimated Effort

| Phase | Days |
|-------|------|
| 0 — Backup | 0.5 |
| A — Core | 2-3 |
| B — Discovery | 2-3 |
| C — Testing | 1-2 |
| D — Documentation | 1 |
| E — Review & Fix | 1-2 |
| F — Architecture | 1-2 |
| G — Self-Improvement | 1 |
| H — Additional Imports | 1 |
| I — Cleanup | 0.5 |
| **Total** | **12-16 days** |

---

## Rollback Strategy

At any point:
1. Delete new v5 files
2. Remove `--old` prefix from backup files
3. System restored

---

## Progress Tracking

| Phase | Status | Date Completed |
|-------|--------|----------------|
| 0 — Backup | ✅ Complete | 2026-02-27 |
| A — Core | ✅ Complete | 2026-02-27 |
| B — Discovery | ✅ Complete | 2026-02-27 |
| C — Testing | ⏳ Pending | |
| D — Documentation | ⏳ Pending | |
| E — Review & Fix | ⏳ Pending | |
| F — Architecture | ⏳ Pending | |
| G — Self-Improvement | ⏳ Pending | |
| H — Additional Imports | ⏳ Pending | |
| I — Cleanup | ⏳ Pending | |

---

*Generated: 2026-02-27*
*Branch: workflow/v5-migration*
*Project: tuti-cli*
*Stack: PHP 8.4 / Laravel Zero*
