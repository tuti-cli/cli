# Technical Debt Registry

**Generated:** 2026-03-02
**Source:** .workflow/AUDIT.md
**Total Items:** 23
**Issues Created:** 17

## Summary

| Priority | Count | Total Effort |
|----------|-------|--------------|
| Critical | 0 | 0 days |
| High | 5 | 3.5 days |
| Normal | 10 | 11.5 days |
| Resolved | 2 | - |
| Low | 6 | 3.5 days |

### Category Breakdown

| Category | Critical | High | Normal | Low |
|----------|----------|------|--------|-----|
| Security | 0 | 2 | 3 | 2 |
| Architecture | 0 | 0 | 1 | 1 |
| Code Quality | 0 | 1 | 4 | 2 |
| Testing | 0 | 1 | 2 | 1 |

---

## High Priority (This Sprint)

### DEBT-001: Replace exec() with Process::run() in WpSetupCommand
- **Category:** Security
- **Source:** AUDIT.md#SEC-001
- **Severity:** High
- **Effort:** S (2-4 hours)
- **ROI:** 3.0 (Security / Low Effort)
- **Issue:** #67
- **Description:** Command injection vulnerability via exec() in WpSetupCommand lines 176-178 and 203-220
- **Remediation:** Replace exec() with Process::run(['docker', 'ps', ...]) array syntax
- **Impact:** Security vulnerability - potential injection vector
- **Affected Files:** app/Commands/Stack/WpSetupCommand.php

### DEBT-002: Add domain validation in HostsFileService
- **Category:** Security
- **Source:** AUDIT.md#SEC-002
- **Severity:** High
- **Effort:** S (2-4 hours)
- **ROI:** 2.5 (Security / Low Effort)
- **Issue:** #68
- **Description:** Shell injection possible in HostsFileService via unvalidated domain parameter
- **Remediation:** Add strict domain validation with allowlist pattern (alphanumeric, dots, hyphens only)
- **Impact:** Security vulnerability - potential shell injection via sudo command
- **Affected Files:** app/Services/Support/HostsFileService.php

### DEBT-003: Extract Docker command building to shared service
- **Category:** Code Quality
- **Source:** AUDIT.md#CODE-001
- **Severity:** High
- **Effort:** M (4-16 hours)
- **ROI:** 2.0 (Maintainability / Medium Effort)
- **Issue:** #69
- **Description:** Duplicate buildComposeCommand() logic across Docker services
- **Remediation:** Create DockerCommandBuilder service to centralize command construction
- **Impact:** Code duplication increases maintenance burden and bug surface area
- **Affected Files:** app/Services/Docker/DockerExecutorService.php, app/Services/Infrastructure/GlobalInfrastructureManager.php

### DEBT-004: Add tests for WpSetupCommand
- **Category:** Testing
- **Source:** AUDIT.md#TEST-001
- **Severity:** High
- **Effort:** M (4-16 hours)
- **Effort Factor:** +1 level (no existing tests for security-critical code) = L (1-3 days)
- **ROI:** 2.2 (Quality / Medium Effort)
- **Issue:** #70
- **Description:** Missing test coverage for security-critical WpSetupCommand
- **Remediation:** Create comprehensive feature tests covering happy path, edge cases, and error conditions
- **Impact:** Security-critical command without test coverage increases regression risk
- **Affected Files:** tests/Feature/Console/WpSetupCommandTest.php (new file)

### DEBT-005: Add path traversal protection in stack name handling
- **Category:** Security
- **Source:** AUDIT.md#SEC-004
- **Severity:** High
- **Effort:** S (2-4 hours)
- **ROI:** 2.8 (Security / Low Effort)
- **Issue:** #71
- **Description:** Path traversal vulnerability in StackRepositoryService stack name resolution
- **Remediation:** Validate stackName against allowlist (alphanumeric, dashes, underscores only)
- **Impact:** Could access files outside intended directories via path traversal sequences
- **Affected Files:** app/Services/Stack/StackRepositoryService.php

---

## Normal Priority (Next Sprint)

### DEBT-006: Add directory validation before Docker volume mounts
- **Category:** Security
- **Source:** AUDIT.md#SEC-005
- **Severity:** Medium
- **Effort:** S (2-4 hours)
- **ROI:** 1.8 (Security / Low Effort)
- **Issue:** #72
- **Description:** Volume mount paths constructed from user-provided paths without validation
- **Remediation:** Validate $workDir exists and is a directory using is_dir() before Docker operations
- **Impact:** Potential directory traversal or unexpected behavior with invalid paths
- **Affected Files:** app/Services/Docker/DockerExecutorService.php

### DEBT-007: Document escapeshellarg exception for TTY support
- **Category:** Security
- **Source:** AUDIT.md#SEC-003
- **Severity:** Medium
- **Effort:** XS (<1 hour)
- **ROI:** 1.5 (Documentation / Minimal Effort)
- **Issue:** #73
- **Description:** escapeshellarg usage required for TTY support should be documented as exception
- **Remediation:** Add prominent documentation noting this as a controlled exception to security standards
- **Impact:** Security standard exception without documentation creates confusion and audit findings
- **Affected Files:** app/Services/Docker/DockerExecutorService.php

### DEBT-008: Add development credential warnings
- **Category:** Security
- **Source:** AUDIT.md#SEC-006
- **Severity:** Medium
- **Effort:** S (2-4 hours)
- **ROI:** 1.3 (Security / Low Effort)
- **Issue:** #74
- **Description:** Hardcoded development credentials without warnings
- **Remediation:** Add prominent warnings when development credentials are detected in use
- **Impact:** Risk of deploying development credentials to production
- **Affected Files:** app/Commands/Stack/WpSetupCommand.php, app/Commands/Stack/WordPressCommand.php, app/Services/Docker/DockerExecutorService.php, app/Services/Stack/Installers/LaravelStackInstaller.php

### DEBT-010: Refactor buildComposeCommand() duplication
- **Category:** Architecture
- **Source:** AUDIT.md#ARCH-002
- **Severity:** Medium
- **Effort:** M (4-16 hours)
- **Risk:** High (Core Docker functionality)
- **ROI:** 2.0 (Maintainability / Medium Effort)
- **Issue:** #76
- **Description:** Duplicate command building logic across multiple services
- **Remediation:** Extract to shared DockerCommandBuilder service, update all consumers
- **Impact:** Code duplication increases maintenance burden and bug risk
- **Related:** DEBT-003 (higher priority)
- **Affected Files:** app/Services/Docker/DockerCommandBuilder.php (new), multiple consumer files

### DEBT-011: Refactor appendOptionalServices() method
- **Category:** Code Quality
- **Source:** AUDIT.md#CODE-002
- **Severity:** Medium
- **Effort:** M (4-16 hours)
- **Risk:** Medium (Well-tested area) = S (2-4 hours)
- **ROI:** 1.8 (Maintainability / Low-Medium Effort)
- **Issue:** #77
- **Description:** Long method (~100 lines) in StackInitializationService
- **Remediation:** Extract to dedicated OptionalServicesBuilder service
- **Impact:** High complexity method difficult to test and maintain
- **Affected Files:** app/Services/Stack/StackInitializationService.php

### DEBT-012: Split LaravelStackInstaller into smaller services
- **Category:** Code Quality
- **Source:** AUDIT.md#CODE-003
- **Severity:** Medium
- **Effort:** L (1-3 days)
- **Risk:** High (Core functionality) +1 level = XL (3+ days)
- **ROI:** 1.0 (Maintainability / High Effort)
- **Issue:** #78
- **Description:** Large class (606 lines) violating Single Responsibility Principle
- **Remediation:** Extract into LaravelEnvInstaller, LaravelDatabaseInstaller, LaravelServicesInstaller
- **Impact:** Large class difficult to test, understand, and modify
- **Affected Files:** app/Services/Stack/Installers/LaravelStackInstaller.php, multiple new installer services

### DEBT-013: Move DockerExecutionResult to Value Objects
- **Category:** Architecture
- **Source:** AUDIT.md#ARCH-004
- **Severity:** Medium
- **Effort:** S (2-4 hours)
- **ROI:** 1.5 (Architecture / Low Effort)
- **Issue:** #79
- **Description:** Value object incorrectly placed in Contracts folder
- **Remediation:** Move DockerExecutionResult to app/Domain/ValueObjects/
- **Impact:** Misplaced value object violates domain-driven design principles
- **Affected Files:** app/Domain/ValueObjects/DockerExecutionResult.php (new), update imports

### DEBT-014: Add tests for StackRepositoryService
- **Category:** Testing
- **Source:** AUDIT.md#TEST-002
- **Severity:** Medium
- **Effort:** M (4-16 hours)
- **ROI:** 1.5 (Quality / Medium Effort)
- **Issue:** #80
- **Description:** Missing unit tests for StackRepositoryService
- **Remediation:** Create comprehensive unit tests for all public methods
- **Impact:** Core repository logic without tests increases regression risk
- **Affected Files:** tests/Unit/Services/StackRepositoryServiceTest.php (new file)

### DEBT-015: Add tests for error handling paths
- **Category:** Testing
- **Source:** AUDIT.md#TEST-003
- **Severity:** Medium
- **Effort:** L (1-3 days)
- **ROI:** 1.2 (Quality / High Effort)
- **Issue:** #81
- **Description:** Missing tests for error handling throughout codebase
- **Remediation:** Audit all services and add tests for exception paths
- **Impact:** Error paths often untested, leading to production failures
- **Affected Files:** Multiple test files

---

## Low Priority (Backlog)

### DEBT-016: Standardize JSON decoding with error handling
- **Category:** Security
- **Source:** AUDIT.md#SEC-007
- **Severity:** Low
- **Effort:** S (2-4 hours)
- **ROI:** 1.3 (Quality / Low Effort)
- **Issue:** #82
- **Description:** JSON decoding without JSON_THROW_ON_ERROR flag in multiple locations
- **Remediation:** Add JSON_THROW_ON_ERROR flag consistently across all json_decode() calls
- **Impact:** Inconsistent error handling for JSON parsing
- **Affected Files:** app/Commands/Stack/WpSetupCommand.php, app/Services/Stack/Installers/WordPressStackInstaller.php, app/Services/Stack/Installers/LaravelStackInstaller.php, app/Services/Infrastructure/GlobalInfrastructureManager.php

### DEBT-017: Log shell_exec fallback usage
- **Category:** Security
- **Source:** AUDIT.md#SEC-008
- **Severity:** Low
- **Effort:** XS (<1 hour)
- **ROI:** 2.0 (Observability / Minimal Effort)
- **Issue:** Not Created
- **Description:** shell_exec() for UID/GID detection without logging when used
- **Remediation:** Add debug log message when shell_exec fallback is used
- **Impact:** Hard to debug when fallback path is taken
- **Affected Files:** app/Services/Support/EnvFileService.php

### DEBT-018: Expand StateManager interface with query methods
- **Category:** Architecture
- **Source:** AUDIT.md#ARCH-003
- **Severity:** Low
- **Effort:** S (2-4 hours)
- **ROI:** 1.0 (Architecture / Low Effort)
- **Issue:** Not Created
- **Description:** StateManagerInterface missing state query methods
- **Remediation:** Add getState(), hasState(), and other query methods to interface
- **Impact:** Limited interface reduces flexibility and expressive power
- **Affected Files:** app/Contracts/StateManagerInterface.php, implementations

### DEBT-019: Extract container name patterns to constants
- **Category:** Code Quality
- **Source:** AUDIT.md#CODE-004
- **Severity:** Low
- **Effort:** M (4-16 hours)
- **ROI:** 1.0 (Maintainability / Medium Effort)
- **Issue:** #83
- **Description:** Magic strings for container names throughout codebase
- **Remediation:** Create ContainerNaming service with constants for naming patterns
- **Impact:** Magic strings are error-prone and hard to maintain
- **Affected Files:** app/Services/Docker/ContainerNamingService.php (new), multiple consumer files

### DEBT-020: Consider extracting WP-CLI logic from DockerExecutorService
- **Category:** Code Quality
- **Source:** AUDIT.md#CODE-005
- **Severity:** Low
- **Effort:** L (1-3 days)
- **Risk:** High (Core functionality) +1 level = XL (3+ days)
- **ROI:** 0.8 (Maintainability / High Effort)
- **Issue:** Not Created
- **Description:** Large class (515 lines) with mixed Docker execution and WP-CLI logic
- **Remediation:** Extract WpCliExecutorService for WordPress-specific operations
- **Impact:** Violates Single Responsibility Principle, but impact is limited
- **Affected Files:** app/Services/Docker/DockerExecutorService.php, app/Services/Docker/WpCliExecutorService.php (new)

### DEBT-021: Add WordPress salt API response validation
- **Category:** Security
- **Source:** AUDIT.md#SEC-009
- **Severity:** Low
- **Effort:** S (2-4 hours)
- **ROI:** 1.3 (Security / Low Effort)
- **Issue:** Not Created
- **Description:** WordPress salt API response not validated
- **Remediation:** Add validation for API response structure and content
- **Impact:** Potential security risk if API returns invalid or malicious data
- **Affected Files:** app/Services/Stack/Installers/WordPressStackInstaller.php

---

## Dependency Graph

```
DEBT-001 ──blocks──▶ DEBT-004
DEBT-002 ──relates──▶ DEBT-001
DEBT-003 ──relates──▶ DEBT-010
DEBT-004 ──relates──▶ DEBT-015
DEBT-005 ──relates──▶ DEBT-014
DEBT-010 ──relates──▶ DEBT-003
DEBT-011 ──relates──▶ DEBT-012
DEBT-012 ──relates──▶ DEBT-020
```

---

## Recommendations

### Immediate Actions (This Sprint)
1. **DEBT-001**: Replace exec() with Process::run() in WpSetupCommand (Security)
2. **DEBT-002**: Add domain validation in HostsFileService (Security)
3. **DEBT-005**: Add path traversal protection in stack name handling (Security)
4. **DEBT-004**: Add tests for WpSetupCommand (Testing)
5. **DEBT-003**: Extract Docker command building to shared service (Code Quality)

### Next Sprint
1. **DEBT-006**: Add directory validation before Docker volume mounts (Security)
2. **DEBT-008**: Add development credential warnings (Security)
3. **DEBT-011**: Refactor appendOptionalServices() method (Code Quality)
4. **DEBT-014**: Add tests for StackRepositoryService (Testing)

### Upcoming (Future)
1. **DEBT-010**: Refactor buildComposeCommand() duplication (Architecture)
2. **DEBT-012**: Split LaravelStackInstaller into smaller services (Code Quality)
3. **DEBT-013**: Move DockerExecutionResult to Value Objects (Architecture)
4. **DEBT-015**: Add tests for error handling paths (Testing)

---

## Progress Tracking

| ID | Priority | Type | Effort | Issue | Status |
|----|----------|------|--------|-------|--------|
| DEBT-001 | High | Security | S | #67 | Open |
| DEBT-002 | High | Security | S | #68 | Open |
| DEBT-003 | High | Code Quality | M | #69 | Open |
| DEBT-004 | High | Testing | L | #70 | Open |
| DEBT-005 | High | Security | S | #71 | Open |
| DEBT-006 | Normal | Security | S | #72 | Open |
| DEBT-007 | Normal | Security | XS | #73 | Open |
| DEBT-008 | Normal | Security | S | #74 | Open |
| DEBT-009 | Normal | Architecture | S | #75 | Resolved |
| DEBT-010 | Normal | Architecture | M | #76 | Resolved |
| DEBT-011 | Normal | Code Quality | S | #77 | Open |
| DEBT-012 | Normal | Code Quality | XL | #78 | Open |
| DEBT-013 | Normal | Architecture | S | #79 | Open |
| DEBT-014 | Normal | Testing | M | #80 | Open |
| DEBT-015 | Normal | Testing | L | #81 | Open |
| DEBT-016 | Low | Security | S | #82 | Open |
| DEBT-017 | Low | Security | XS | - | Not Created |
| DEBT-018 | Low | Architecture | S | - | Not Created |
| DEBT-019 | Low | Code Quality | M | #83 | Open |
| DEBT-020 | Low | Code Quality | XL | - | Not Created |
| DEBT-021 | Low | Security | S | - | Not Created |

---

## Mapping Summary

**Total Findings Processed:** 23
**Issues Created:** 17
**Deferred to Later:** 4 (DEBT-017, DEBT-018, DEBT-020, DEBT-021)

### Issues by Priority

| Priority | Count | Issues |
|----------|-------|--------|
| High | 5 | #67, #68, #69, #70, #71 |
| Normal | 8 | #72, #73, #74, #77, #78, #79, #80, #81 |
| Resolved | 2 | #75 (DockerService deleted), #76 (DockerCommandBuilder) |
| Low | 2 | #82, #83 |

### Issues by Category

| Category | Count |
|----------|-------|
| Security | 8 |
| Code Quality | 5 |
| Architecture | 3 |
| Testing | 3 |

---

*Technical debt registry generated: 2026-03-02*
