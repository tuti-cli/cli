# Phase 3: Multi-Project Management & Developer Experience

**Timeline:** 3-4 weeks
**Status:** Not Started
**Depends On:** Phase 1 (MVP: Local Development)
**Goal:** Expose multi-project management capabilities to users and improve the overall developer experience.

---

## Why This Phase

The `GlobalRegistryService` backend already tracks projects, but there are no CLI commands to access this data. Developers managing multiple projects (common for freelancers and agencies) need to list, switch, and monitor projects efficiently. This phase also addresses UX improvements discovered during Phase 1 and 2.

---

## Scope

### 3.1 Multi-Project Commands (Priority: Critical)

Surface the existing project registry through user-facing commands.

**Deliverables:**
- [ ] `tuti projects:list` - List all registered projects
  - Shows: name, path, stack type, status (running/stopped), last accessed
  - Highlights currently active project (based on cwd)
  - Marks stale entries (directory moved/deleted)
  - Supports `--stack=laravel` filtering
  - Supports `--running` to show only active projects
- [ ] `tuti projects:status` - Dashboard view of all projects
  - Queries Docker for each project's container status
  - Shows: running container count, URL, memory/CPU usage
  - Summarizes total resource usage across all projects
- [ ] `tuti projects:clean` - Remove stale registry entries
  - Detects projects with missing directories
  - Interactive confirmation for each removal
  - `--dry-run` flag for preview
  - `--force` flag to skip confirmation
- [ ] `tuti projects:open {name}` - Open a project in the browser
  - Resolves project URL from registry and config
  - Opens `https://{project}.local.test` in default browser

**User Stories:** US-7.1 (List Projects), US-7.2 (Project Status), US-7.3 (Clean Stale Projects)

### 3.2 Port Conflict Detection (Priority: High)

Make port management active instead of passive.

**Deliverables:**
- [ ] Call `DockerService.checkPortConflicts()` before `local:start`
  - Check if any project service ports conflict with running containers
  - Warn user and suggest resolution (stop conflicting project or use Traefik-only access)
- [ ] `tuti projects:ports` - Show port allocations across all projects
  - List all registered projects with their allocated ports
  - Highlight conflicts
- [ ] Document that Traefik eliminates HTTP/HTTPS port conflicts but direct service ports (database:5432, Redis:6379) may still conflict across projects

**User Stories:** Related to US-3.1 (Start Local Environment)

### 3.3 Config Migration System (Priority: High)

Prevent breakage when config schema evolves between versions.

**Deliverables:**
- [ ] `ConfigMigrationService` that detects config version and applies migrations
- [ ] Migration registry mapping version ranges to migration callbacks
- [ ] Auto-migrate on any command that reads config (transparent to user)
- [ ] Backup old config before migration (`config.json.bak`)
- [ ] Log migration actions to debug log

### 3.4 Contextual Tips & Suggestions (Priority: Medium)

Guide users through workflows with smart suggestions.

**Deliverables:**
- [ ] After `stack:laravel` success -> suggest `tuti local:start`
- [ ] After `local:start` failure -> suggest `tuti doctor`
- [ ] After `local:start` success -> show project URL and `tuti local:logs` tip
- [ ] After `install` success -> suggest `tuti stack:laravel my-app`
- [ ] When running command outside a project directory -> suggest `tuti init` or `tuti stack:*`
- [ ] Tips use `tipBox()` from `HasBrandedOutput` for consistent formatting

**User Stories:** US-10.3 (Contextual Help)

### 3.5 Shell Completions (Priority: Medium)

Tab completion for commands and arguments.

**Deliverables:**
- [ ] Bash completion script (`tuti --completion bash`)
- [ ] Zsh completion script (`tuti --completion zsh`)
- [ ] Fish completion script (`tuti --completion fish`)
- [ ] Complete command names, subcommands, `--option` flags
- [ ] Complete project names for `projects:open` command
- [ ] Install instructions in docs

### 3.6 Self-Update Command (Priority: Low)

Keep Tuti CLI up to date.

**Deliverables:**
- [ ] `tuti self-update` checks GitHub releases for latest version
- [ ] Downloads and replaces current binary
- [ ] Shows changelog/release notes
- [ ] `--check` flag to check without updating
- [ ] Verify binary checksum after download

---

## New Service Classes

| Class | Responsibility |
|-------|---------------|
| `ProjectDashboardService` | Aggregates status across all projects |
| `PortConflictService` | Detects port conflicts across running projects |
| `ConfigMigrationService` | Handles config schema evolution |
| `SelfUpdateService` | Checks and applies binary updates |

## New Commands

| Command | Signature |
|---------|-----------|
| `projects:list` | `tuti projects:list [--stack=] [--running]` |
| `projects:status` | `tuti projects:status` |
| `projects:clean` | `tuti projects:clean [--dry-run] [--force]` |
| `projects:open` | `tuti projects:open {name?}` |
| `projects:ports` | `tuti projects:ports` |
| `self-update` | `tuti self-update [--check]` |

---

## Success Criteria

- [ ] `tuti projects:list` shows all registered projects with accurate status
- [ ] `tuti projects:status` displays real-time Docker status for all projects
- [ ] `tuti projects:clean` removes stale entries after confirmation
- [ ] Port conflicts detected and warned before `local:start`
- [ ] Config migration handles at least one schema change transparently
- [ ] Contextual tips appear after key commands
- [ ] Shell completions work for bash and zsh
- [ ] All new commands have test coverage >80%

---

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Querying Docker for all projects is slow | `projects:status` takes too long | Cache status briefly; show spinner; query in parallel |
| Config migration corrupts user data | Projects break on update | Always backup before migration; test with real project configs |
| Self-update replaces binary while running | Undefined behavior | Write new binary to temp file, then atomic rename |
| Shell completions differ across OS versions | Broken completion on some systems | Test on Linux + macOS; provide manual install docs |

---

## Definition of Done

Phase 3 is complete when:
1. Developers can list, monitor, and clean up all projects from one place
2. Port conflicts are detected before starting containers
3. Config files survive version upgrades via auto-migration
4. Contextual tips guide users through common workflows
5. All new commands pass CI with >80% test coverage
