# Phase 1: MVP - Local Development Polish

**Timeline:** 4-6 weeks
**Status:** In Progress
**Goal:** Make local development features production-ready with comprehensive testing, CI pipeline, and bug fixes.

---

## Why This Phase First

The local development features are functional but lack test coverage, CI validation, and several quality improvements needed before public release. Deployment (Phase 2) depends on a solid, well-tested local foundation. Releasing a buggy MVP would damage credibility.

---

## Scope

### 1.1 Test Coverage (Priority: Critical)

Increase test coverage from ~5 test files to comprehensive coverage across all core commands and services.

**Deliverables:**
- [x] Unit tests for all services in `app/Services/` (target: >90% coverage)
  - [x] `StackComposeBuilderService` - Compose generation with various service combinations (28 tests)
  - [x] `StackEnvGeneratorService` - Env file generation, secure password replacement
  - [x] `StackFilesCopierService` - Template file copying and substitution (21 tests)
  - [x] `StackLoaderService` - Stack.json parsing and validation
  - [x] `StackStubLoaderService` - Section-based stub parsing
  - [x] `DockerExecutorService` - Docker command building (26 tests)
  - [x] `GlobalRegistryService` - Project registration, listing, cleanup (20 tests)
  - [x] `GlobalSettingsService` - Settings read/write, dot-notation access (16 tests)
  - [x] `ProjectMetadataService` - Project config CRUD (17 tests)
  - [x] `ProjectStateManagerService` - Docker status querying (19 tests)
  - [x] `GlobalInfrastructureManager` - Traefik lifecycle (23 tests)
  - [x] `WorkingDirectoryService` - Project detection, path resolution
  - [x] `DockerService` - Docker Compose wrapper (33 tests, refactored for testability)
  - [x] `ProjectDirectoryService` - Project directory management (14 tests)
- [ ] Feature tests for all user-facing commands (target: >80% coverage)
  - [x] `install` command
  - [x] `doctor` command
  - [ ] `stack:laravel` command
  - [ ] `stack:wordpress` command
  - [ ] `local:start`, `local:stop`, `local:status`, `local:logs`, `local:rebuild`
  - [x] `infra:start`, `infra:stop`, `infra:restart`, `infra:status`
  - [x] `env:check` command
  - [x] `init` command
- [ ] Validate generated Docker Compose files are syntactically valid YAML
- [ ] Test all service stub combinations produce valid compose output

**User Stories:** US-6.1 (Health Check), US-2.1 (Laravel Stack), US-2.2 (WordPress Stack)

### 1.2 CI/CD Pipeline (Priority: Critical)

Set up automated testing on every pull request to prevent regressions.

**Deliverables:**
- [ ] GitHub Actions workflow: `.github/workflows/tests.yml`
  - [ ] Triggers on PR open/update/push to main
  - [ ] Runs: Rector (dry-run) -> Pint (dry-run) -> PHPStan -> Pest
  - [ ] PHP 8.4 environment
  - [ ] Caches Composer dependencies
  - [ ] Reports results as PR checks
- [ ] Branch protection rules on `main` requiring passing checks
- [ ] Badge in README showing CI status

**User Stories:** US-12.1 (CI Test Pipeline)

### 1.3 Bug Fixes (Priority: High)

Fix known issues discovered during codebase analysis.

**Deliverables:**
- [x] Fix `StackEnvGeneratorService.generateSecureValues()` regex bug - space in `CHANGE_THIS(? :_IN_PRODUCTION)?` pattern makes non-capturing group invalid
- [x] Fix Redis password `null` string issue - ensure `REDIS_PASSWORD=` (empty) is used, not `REDIS_PASSWORD=null`
- [x] Validate Docker Compose YAML output for indentation correctness
- [x] Add JSON schema validation for `config.json`, `stack.json`, `registry.json` files
- [x] Handle stale entries in `GlobalRegistryService.projects.json` (detect moved/deleted directories)

### 1.4 Remove Dev Commands from Production (Priority: High)

Development/test commands should not ship in the production binary.

**Deliverables:**
- [ ] Remove or gate `test:registry`, `test:compose-builder`, `test:stack-loader`, `test:tuti-directory`, `test:stack-overrides` commands from production builds
- [ ] Remove or gate `validate:quick` command
- [ ] Remove or gate `ui:showcase` command
- [ ] Configure `box.json` exclusions or use `APP_ENV` check in command registration

### 1.5 Documentation (Priority: Medium)

Ensure users can self-onboard.

**Deliverables:**
- [ ] README.md with installation, quick start, feature overview
- [ ] Command reference documentation (auto-generated or manual)
- [ ] Contributing guide (CONTRIBUTING.md)
- [ ] Troubleshooting section in docs

### 1.6 WordPress Auto-Setup (Priority: Medium)

Complete the `wp:setup` placeholder command.

**Deliverables:**
- [ ] Create database via WP-CLI
- [ ] Run `wp core install` with config values from `.env`
- [ ] Set up admin user
- [ ] Configure permalink structure
- [ ] Enable Redis object caching if Redis service is present
- [ ] Support both Standard and Bedrock project types

**User Stories:** US-11.1 (WordPress Auto-Setup)

---

## Success Criteria

- [ ] All `composer test` stages pass (Rector, Pint, PHPStan, Pest)
- [ ] Test coverage: Commands >80%, Services >90%
- [ ] CI pipeline runs on every PR and blocks merging on failure
- [ ] No dev/test commands in production binary
- [x] Known bugs fixed (regex, Redis, YAML validation)
- [ ] A new user can install and create a Laravel/WordPress project following the README
- [ ] `tuti doctor` catches all common configuration issues

---

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Writing comprehensive tests takes longer than estimated | Delays Phase 2 | Prioritize critical path tests (stack init, local start/stop) |
| Fixing YAML generation bugs reveals deeper architecture issues | Scope creep | Time-box bug fixes; document issues for later if not critical |
| Dev command removal breaks PHAR compilation | Build failure | Test PHAR build in CI after changes |

---

## Definition of Done

Phase 1 is complete when:
1. CI pipeline is green on `main` branch
2. Test suite has >80% command coverage and >90% service coverage
3. `tuti stack:laravel my-app && tuti local:start` works end-to-end on a clean system
4. `tuti stack:wordpress my-site && tuti local:start` works end-to-end on a clean system
5. `make build-phar && make test-phar` succeeds without dev commands
6. README provides clear installation and quick-start instructions
