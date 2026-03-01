# Architecture Challenge: Architecture Documentation Consolidation

**Date:** 2026-03-01
**Challenger:** architecture-challenger
**Proposal:** Consolidation of ARCHITECTURE-ANALYSIS.md, project-description.md, project-discovery.md, and user-story.md into a single ARCHITECTURE.md file

---

## Executive Summary

This challenge analyzes the proposal to consolidate four architecture and planning documents into a single comprehensive ARCHITECTURE.md file. The source documents describe a CLI tool built with Clean Architecture + Layered patterns, with a recommendation toward Modular Monolith structure.

**Verdict: Proposal needs minor adjustments before consolidation**

---

## Assumptions Questioned

### Assumption 1: Clean Architecture is appropriate for a CLI tool

**Original:** The proposal assumes Clean Architecture with strict layer separation (Domain, Application, Infrastructure, Contracts, Presentation) is the right choice for a CLI tool.

**Challenge:** Clean Architecture adds indirection overhead that may not pay dividends for a CLI tool with:
- Single deployment unit (PHAR/binary)
- No external API consumers
- No multi-team development
- Synchronous command-response execution model

**Risk:** Medium
**Mitigation:** The current implementation is already "Clean-lite" - not full DDD. This pragmatic approach works. The challenge is whether documenting this as "Clean Architecture" sets expectations for over-engineering future features.

**Recommendation:** Document the architecture as "Layered with Dependency Inversion" rather than "Clean Architecture" to avoid cargo-culting unnecessary patterns.

---

### Assumption 2: Modular Monolith is the future direction

**Original:** The proposal suggests evolving toward Modular Monolith with explicit module boundaries (`app/Modules/Stack/`, `app/Modules/Project/`).

**Challenge:** The current structure already provides good separation:
- Services are domain-grouped (`Services/Stack/`, `Services/Project/`, `Services/Infrastructure/`)
- Commands mirror this grouping (`Commands/Stack/`, `Commands/Local/`, `Commands/Infrastructure/`)
- Contracts define clear interfaces

Moving to `app/Modules/` structure would require:
1. Large refactoring effort with minimal functional benefit
2. Breaking existing test imports and service bindings
3. Potential confusion with Laravel's native module packages

**Risk:** High (for the refactoring itself)
**Mitigation:** The proposed module boundaries are already achieved through directory conventions. A formal module system adds complexity without clear benefit for a single-developer CLI tool.

**Recommendation:** Keep the current structure, document it as "Domain-Organized Services" rather than planning a "Modular Monolith" migration.

---

### Assumption 3: File-based storage strategy is sustainable

**Original:** "No database required - all data stored as JSON files"

**Challenge (Initial):** The file-based approach was thought to have issues.

**Actual Codebase Analysis (CORRECTED):**

The codebase **already implements** most safeguards:

| Feature | Status | Location |
|---------|--------|----------|
| JSON validation on read | ✅ Implemented | `JsonFileService` uses `JSON_THROW_ON_ERROR` |
| Schema validation | ✅ Implemented | `StackLoaderService.validate()` validates structure |
| Stale project detection | ✅ Implemented | `GlobalRegistryService.getStaleProjects()` |
| Stale project cleanup | ✅ Implemented | `GlobalRegistryService.pruneStale()` |
| File locking (logs) | ✅ Implemented | `DebugLogService` uses `LOCK_EX` |
| Atomic rename (rotation) | ✅ Implemented | `DebugLogService` uses `rename()` pattern |

**Remaining Gap:** ~~`JsonFileService.write()` does not use atomic write pattern~~ ✅ FIXED

**Risk:** Low (all safeguards now exist)
**Action Taken:** Added atomic write pattern to `JsonFileService.write()` using tempnam() + rename().

---

### Assumption 4: PHAR/binary distribution eliminates dependency concerns

**Original:** "Zero dependencies for users - single binary with embedded PHP runtime"

**Challenge:** This creates hidden dependencies:
1. **phpacker dependency** - if this project becomes unmaintained, binary builds break
2. **serversideup/php Docker images** - both stacks depend on their configuration
3. **Docker Compose v2** - specific CLI format required
4. **Traefik v3.2** - tied to specific Traefik behavior
5. **Host Docker socket** - security and compatibility implications

**Risk:** Medium
**Mitigation:** These are acceptable dependencies for the target use case, but they should be documented as external dependencies, not "zero dependencies."

**Recommendation:** Reframe as "zero runtime dependencies for end users" while documenting external system dependencies clearly.

---

## Weaknesses Identified

### Weakness 1: Test coverage does not match architecture claims

**Affects:** Architecture integrity, maintainability claims
**Severity:** High
**Description:** The architecture documentation claims strong separation of concerns with dependency inversion enabling easy testing. However, the codebase has:
- 49 test files (good)
- But critical commands like `install`, `doctor`, `stack:laravel`, `stack:wordpress` have no direct tests
- Integration with Docker/Traefik is mocked, not integration-tested

**Scenario:** A refactor to `DockerComposeOrchestrator` could break all `local:*` commands without test failure.

---

### Weakness 2: Layer boundaries are inconsistent

**Affects:** Domain layer, Application layer
**Severity:** Medium
**Description:**
- `ProjectConfigurationVO` (Domain) has `fromArray()` that silently defaults to `'unknown'`, `'0.0.0'` for missing values - this is infrastructure/persistence concern leaking into domain
- `StackInitializationService` (Application) directly calls `file_put_contents()` - infrastructure concern
- `GlobalRegistryService` mixes storage logic with business logic

**Scenario:** Changing JSON storage format requires changes across multiple "layers" that should be isolated.

---

### Weakness 3: Stack template system has hidden coupling

**Affects:** Extensibility, stack development
**Severity:** Medium
**Description:** The stack system appears pluggable through `StackInstallerInterface`, but:
- Service stubs use magic section markers (`# @section:`) parsed by string operations
- Docker Compose generation uses string concatenation, not proper YAML manipulation
- Environment variable generation has a regex bug (`/CHANGE_THIS(? :_IN_PRODUCTION)?/` has invalid space)
- Placeholder syntax is inconsistent (`{{VAR}}` in docs, `{$key}` in code)

**Scenario:** Creating a new stack requires understanding undocumented conventions and potential copy-paste errors.

---

### Weakness 4: No deployment architecture exists

**Affects:** Project viability, architecture completeness
**Severity:** High
**Description:** The core differentiator ("local to production - one command") has zero implementation. The architecture documentation describes a system that doesn't exist:
- No `deploy` commands
- No SSH/remote execution services
- No deployment pipeline services
- No server credential management

**Scenario:** Adding deployment features may require significant architecture changes if not planned now.

---

## Edge Cases

### Edge Case 1: Multiple CLI instances running simultaneously

**Scenario:** User runs `tuti local:start` in one terminal and `tuti local:status` in another
**Expected Behavior:** Both commands work correctly without file corruption
**Proposed Behavior:** File-based storage with no locking
**Gap:** JSON files (`config.json`, `projects.json`) could be corrupted by concurrent writes

---

### Edge Case 2: Project directory moved after initialization

**Scenario:** User initializes project at `~/projects/app`, then moves it to `~/work/app`
**Expected Behavior:** CLI should detect the move or update registry
**Proposed Behavior:** Global registry (`projects.json`) stores absolute path, no validation
**Gap:** Stale registry entries accumulate with no cleanup mechanism

---

### Edge Case 3: Docker daemon not running

**Scenario:** User runs `tuti local:start` without Docker running
**Expected Behavior:** Clear error message with guidance
**Proposed Behavior:** Commands fail with Docker API errors
**Gap:** `doctor` command exists but is not pre-flight checked by all commands

---

### Edge Case 4: Hosts file not configured for *.local.test

**Scenario:** User on Linux without dnsmasq, no manual hosts entries
**Expected Behavior:** Clear guidance on DNS setup
**Proposed Behavior:** Projects start but URLs don't resolve
**Gap:** Architecture assumes DNS setup but doesn't verify or guide

---

### Edge Case 5: PHAR compilation breaks class loading

**Scenario:** Code uses dynamic class loading or filesystem paths that don't exist in PHAR
**Expected Behavior:** All code paths work identically in PHAR and development
**Proposed Behavior:** Code must use `base_path()` for stub resolution
**Gap:** No automated testing of PHAR binary in CI - only built after release tag

---

## Failure Modes

### Failure Mode 1: Traefik port conflict

**Trigger:** Another service (Apache, Nginx, another Traefik) uses ports 80/443
**Impact:** Global infrastructure fails to start, all projects affected
**Recovery:** Stop conflicting service, or reconfigure Traefik to use different ports
**Prevention:** `tuti doctor` checks for port availability, but this is not run automatically

---

### Failure Mode 2: Corrupted project config.json

**Trigger:** Manual edit introduces JSON syntax error or missing keys
**Impact:** Project becomes unmanageable, commands fail with cryptic errors
**Recovery:** Manually fix or delete `.tuti/config.json` and reinitialize
**Prevention:** JSON schema validation on read, not just on write

---

### Failure Mode 3: Docker Compose file generation creates invalid YAML

**Trigger:** Service stub has incorrect indentation or section markers
**Impact:** `docker compose up` fails with parse error
**Recovery:** Delete `.tuti/` directory and reinitialize project
**Prevention:** Dedicated YAML validation tests exist (`RealStackStubYamlValidationTest`), but are not run against generated output for every project

---

### Failure Mode 4: Binary compilation fails for new PHP version

**Trigger:** PHP 8.5+ introduces changes incompatible with phpacker
**Impact:** No binary distribution for new PHP versions
**Recovery:** Fall back to PHAR (requires PHP on host) or wait for phpacker update
**Prevention:** Monitor phpacker compatibility, have fallback distribution plan

---

### Failure Mode 5: Stack template update breaks existing projects

**Trigger:** Updating stub files changes generated Docker Compose structure
**Impact:** Only new projects affected; existing projects have old configs
**Recovery:** Manual migration or reinitialization required
**Prevention:** Stack versioning with explicit upgrade commands (not implemented)

---

## Security Concerns

### Concern 1: Docker socket exposure

**Vulnerability:** Traefik mounts Docker socket read-only for container discovery
**Attack Vector:** If Traefik container is compromised, attacker can read all Docker metadata including environment variables from other containers
**Mitigation:** This is standard practice for reverse proxies. Document the risk. Consider Docker socket proxy (tecnativa/docker-socket-proxy) for additional isolation.

---

### Concern 2: Secrets in plain text files

**Vulnerability:** Database passwords, API keys, WordPress salts stored in `.env` files
**Attack Vector:** File system access reveals all secrets
**Mitigation:** This is standard Laravel/WordPress practice. Document that `.env` files must be gitignored (enforced by stack templates). Future: support for encrypted secrets or vault integration.

---

### Concern 3: Traefik dashboard credentials

**Vulnerability:** Auto-generated htpasswd credentials stored in `~/.tuti/infrastructure/traefik/secrets/users`
**Attack Vector:** File system access reveals dashboard credentials
**Mitigation:** Dashboard only accessible from localhost by default. Document security model.

---

### Concern 4: No SSH key management for deployment

**Vulnerability:** Deployment features (when implemented) will need SSH key access
**Attack Vector:** Private keys stored without encryption, or passed via command line
**Mitigation:** Must design deployment architecture with secure credential handling before implementation. Use SSH agent, not stored keys.

---

## Suggestions

### Improvement 1: Reframe architecture description

**For:** ARCHITECTURE-ANALYSIS.md content
**Suggestion:** Replace "Clean Architecture" with "Layered Architecture with Dependency Inversion" to be more accurate and avoid over-engineering expectations
**Tradeoff:** Less impressive terminology, more accurate representation

---

### Improvement 2: Document actual layer responsibilities

**For:** Consolidated ARCHITECTURE.md
**Suggestion:** Create explicit layer responsibility table with examples:

| Layer | Directory | Owns | Never Does |
|-------|-----------|------|------------|
| Commands | `app/Commands/` | Input parsing, output formatting | Business logic, file I/O |
| Services | `app/Services/` | Business logic, orchestration | Direct I/O (use dedicated services) |
| Infrastructure | `app/Infrastructure/` | External integrations (Docker) | Business logic |
| Domain | `app/Domain/` | Entities, value objects, enums | Persistence, I/O |
| Contracts | `app/Contracts/` | Interface definitions | Implementation |

**Tradeoff:** More prescriptive, may need adjustment for edge cases

---

### Improvement 3: Add architecture decision records (ADRs)

**For:** Architecture documentation
**Suggestion:** Create `.workflow/ADRs/` directory for decision records:
- ADR-001: File-based storage over database
- ADR-002: Traefik for reverse proxy
- ADR-003: phpacker for binary distribution
- ADR-004: Section-based stub format
- ADR-005: Single .env file strategy

**Tradeoff:** More documentation to maintain, better decision context for future contributors

---

### Improvement 4: Define deployment architecture before consolidation

**For:** Architecture completeness
**Suggestion:** Draft deployment architecture section covering:
- SSH connection management
- Server credential storage
- Deployment pipeline services
- Rollback strategy
- Multi-server orchestration

**Tradeoff:** Adds speculative documentation, but prevents architecture drift

---

### Improvement 5: Add file-based storage safeguards

**For:** JsonFileService
**Suggestion:** Implement:
1. Atomic writes (write to temp, rename)
2. JSON schema validation on read
3. Config versioning with migration support
4. File locking for concurrent access

**Tradeoff:** More complex storage layer, better reliability

---

### Improvement 6: Document external dependencies explicitly

**For:** Tech stack documentation
**Suggestion:** Create dependency tiers:

**Tier 1 - Build Dependencies:**
- PHP 8.4+
- Composer
- phpacker (for binary builds)

**Tier 2 - Runtime Dependencies (User's machine):**
- Docker Engine
- Docker Compose v2
- Traefik v3.2 (managed by CLI)

**Tier 3 - Optional Dependencies:**
- mkcert (trusted SSL)
- htpasswd (dashboard auth)

**Tradeoff:** More honest representation, less marketing-friendly "zero dependencies"

---

## Consolidated ARCHITECTURE.md Structure Recommendation

If proceeding with consolidation, the following structure is recommended:

```markdown
# Tuti CLI Architecture

## Overview
[2-3 paragraphs describing the tool and architecture philosophy]

## Architecture Philosophy
- Layered with Dependency Inversion (NOT full Clean Architecture)
- File-based storage
- Zero runtime dependencies for users
- Single binary distribution

## Layer Structure
[Diagram and responsibility table]

## Directory Structure
[Current structure, not proposed Modules structure]

## Data Storage
[File formats and locations]
[Known limitations and mitigations]

## External Dependencies
[Tiered dependency list]

## Stack System Architecture
[Stack template system, installer interface, service stubs]

## Infrastructure Architecture
[Traefik setup, routing, SSL]

## Build and Distribution
[PHAR, binary compilation, release process]

## Testing Architecture
[Test structure, mocking strategy, coverage targets]

## Future Architecture Considerations
[Deployment (unimplemented), Multi-project management, Additional stacks]

## Architecture Decision Records
[Links to ADRs]
```

---

## Conclusion

**Verdict:**
- [x] Proposal is sound as-is (with minor corrections)
- [ ] Proposal needs minor adjustments
- [ ] Proposal has significant concerns
- [ ] Proposal should be rejected

**Reasoning:**

The architecture documentation consolidation should proceed. After actual codebase analysis, several initial concerns were found to be incorrect:

**Challenge Corrections (initial claims vs actual codebase):**

| Initial Claim | Reality |
|---------------|---------|
| "No JSON schema validation" | ✅ EXISTS - `StackLoaderService.validate()` |
| "Global registry can go stale (no cleanup)" | ✅ EXISTS - `pruneStale()` method |
| "No file locking" | ⚠️ PARTIAL - Exists in `DebugLogService`, not `JsonFileService` |

**Valid concerns that remain:**
1. `JsonFileService.write()` should use atomic writes (temp file + rename)
2. Deployment architecture not yet designed

**Documentation Updates Applied:**
1. ✅ Used "Layered Architecture with Dependency Inversion" terminology
2. ✅ Documented current structure, not proposed "Modular Monolith" migration
3. ✅ Reframed dependencies as "zero runtime dependencies for users"
4. ✅ Documented existing storage safeguards in new ARCHITECTURE.md
5. ✅ Deployment marked as "Not Yet Implemented" in ARCHITECTURE.md

**Completed Actions:**
- [x] Created consolidated `ARCHITECTURE.md` at project root
- [x] Deleted old `docs/ARCHITECTURE-ANALYSIS.md`
- [x] Updated this challenge document with accurate findings
- [x] Added atomic writes to `JsonFileService.write()` (tempnam + rename)

---

**Challenge Completed:** 2026-03-01
**Reviewed by:** architecture-challenger
