# Phase 4: Ecosystem Expansion

**Timeline:** 6-8 weeks
**Status:** Not Started
**Depends On:** Phase 2 (Deployment Foundations), Phase 3 (Multi-Project & DX)
**Goal:** Expand Tuti CLI beyond Laravel/WordPress with new stacks, database operations, CI/CD templates, and enhanced stack management.

---

## Why This Phase

With local development polished (Phase 1), deployment working (Phase 2), and multi-project management in place (Phase 3), it's time to broaden the tool's appeal. Adding Next.js/Django stacks attracts new user segments, while database operations and CI/CD templates deepen value for existing users.

---

## Scope

### 4.1 Next.js Stack (Priority: High)

First non-PHP stack, proving the multi-framework architecture works.

**Deliverables:**
- [ ] `stubs/stacks/nextjs/` stack template
  - `stack.json` with Node.js version, services, environments
  - `docker-compose.yml` base config (Node.js container)
  - `docker-compose.dev.yml` development overlay (hot reload, volume mounts)
  - `docker/Dockerfile` multi-stage (development, production)
  - `environments/.env.dev.example`
  - `scripts/entrypoint-dev.sh`
- [ ] `NextjsStackInstaller` implementing `StackInstallerInterface`
- [ ] `tuti stack:nextjs {name}` command
  - Fresh mode: `npx create-next-app` + Docker setup
  - Existing mode: Add Docker to existing Next.js project
  - Service selection: PostgreSQL/MySQL, Redis, MinIO
- [ ] Register in `stubs/stacks/registry.json` and `StackServiceProvider`
- [ ] Service stubs for Next.js-relevant services (databases, cache, storage)
- [ ] Tests for installer and command

### 4.2 Django Stack (Priority: Medium)

First Python stack, demonstrating true language-agnostic design.

**Deliverables:**
- [ ] `stubs/stacks/django/` stack template
  - `stack.json` with Python version, services, environments
  - `docker-compose.yml` base config (Python/Gunicorn container)
  - `docker-compose.dev.yml` development overlay (auto-reload, debug)
  - `docker/Dockerfile` multi-stage
  - `environments/.env.dev.example`
  - `scripts/entrypoint-dev.sh`
- [ ] `DjangoStackInstaller` implementing `StackInstallerInterface`
- [ ] `tuti stack:django {name}` command
  - Fresh mode: `django-admin startproject` + Docker setup
  - Existing mode: Add Docker to existing Django project
  - Service selection: PostgreSQL/MySQL, Redis, Celery, MinIO, Mailpit
- [ ] Register in `stubs/stacks/registry.json` and `StackServiceProvider`
- [ ] Tests for installer and command

### 4.3 Database Operations (Priority: High)

Essential developer workflow commands that work across all stacks.

**Deliverables:**
- [ ] `tuti db:backup` - Create database dump
  - Auto-detect database type from project config (PostgreSQL, MySQL, MariaDB)
  - Execute `pg_dump` / `mysqldump` inside running container
  - Save to `.tuti/backups/{timestamp}.sql` (or `.sql.gz` with `--compress`)
  - Support naming backups (`--name=before-migration`)
- [ ] `tuti db:restore {file?}` - Restore from backup
  - List available backups for interactive selection
  - Confirm before overwriting current database
  - Handle both plain and compressed dumps
- [ ] `tuti db:list` - List available backups with dates and sizes
- [ ] `tuti db:reset` - Drop and recreate database
  - Laravel: runs `artisan migrate:fresh [--seed]`
  - WordPress: drops tables, reimports if backup available
  - Requires confirmation (destructive)
- [ ] `tuti db:shell` - Open interactive database CLI
  - Opens `psql` / `mysql` shell inside database container
  - Pre-configured with correct credentials

**User Stories:** US-13.1 (Database Backup), US-13.2 (Database Restore), US-13.3 (Database Reset)

### 4.4 CI/CD Template Generation (Priority: Medium)

Help users set up automated pipelines for their projects.

**Deliverables:**
- [ ] `tuti ci:generate` - Generate CI/CD pipeline configs
  - Interactive: choose platform (GitHub Actions, GitLab CI)
  - Generate test workflow (lint, test, analyze)
  - Generate deployment workflow (using `tuti deploy`)
  - Framework-aware steps (Laravel migrations, WordPress theme build)
- [ ] Templates stored in `stubs/ci/`
  - `github-actions-test.yml.stub`
  - `github-actions-deploy.yml.stub`
  - `gitlab-ci-test.yml.stub`
  - `gitlab-ci-deploy.yml.stub`
- [ ] Configure secrets/variables needed in CI

**User Stories:** US-12.2 (Generate CI Config)

### 4.5 Enhanced Stack Management (Priority: Medium)

Complete the `stack:manage` command.

**Deliverables:**
- [ ] `tuti stack:manage add-service` - Add service to existing project
  - Interactive service selection from stack's service registry
  - Append service stub sections to existing compose files
  - Add new environment variables to `.env`
  - Show diff of changes before applying
- [ ] `tuti stack:manage remove-service` - Remove service
  - List currently configured services
  - Remove from compose files and `.env`
  - Warn about data loss (volumes)
  - Optionally remove Docker volumes (`--with-data`)
- [ ] `tuti stack:manage list` - Show current project services
  - Display all configured services with status
- [ ] `tuti stack:update` - Update cached stack templates
  - Pull latest from configured repositories
  - Show changelog/diff
  - Does not affect initialized projects

**User Stories:** US-9.2 (Manage Stack Services), US-9.3 (Update Stack Templates)

---

## New Service Classes

| Class | Responsibility |
|-------|---------------|
| `DatabaseBackupService` | Database dump/restore orchestration |
| `CiTemplateService` | CI/CD config generation from stubs |
| `NextjsStackInstaller` | Next.js project initialization |
| `DjangoStackInstaller` | Django project initialization |

## New Commands

| Command | Signature |
|---------|-----------|
| `stack:nextjs` | `tuti stack:nextjs {name?} [--mode=] [--services=*]` |
| `stack:django` | `tuti stack:django {name?} [--mode=] [--services=*]` |
| `db:backup` | `tuti db:backup [--compress] [--name=]` |
| `db:restore` | `tuti db:restore {file?}` |
| `db:list` | `tuti db:list` |
| `db:reset` | `tuti db:reset [--seed] [--force]` |
| `db:shell` | `tuti db:shell` |
| `ci:generate` | `tuti ci:generate [--platform=]` |
| `stack:manage` | `tuti stack:manage {action} [--service=]` |
| `stack:update` | `tuti stack:update [--stack=]` |

---

## Success Criteria

- [ ] `tuti stack:nextjs my-app && tuti local:start` creates working Next.js Docker environment
- [ ] `tuti stack:django my-app && tuti local:start` creates working Django Docker environment
- [ ] `tuti db:backup && tuti db:restore` roundtrips data correctly for all database types
- [ ] `tuti ci:generate` produces working GitHub Actions workflows
- [ ] `tuti stack:manage add-service` correctly updates compose files and `.env`
- [ ] All new stacks and commands have test coverage >80%
- [ ] PHAR/binary builds include new stubs and work correctly

---

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Non-PHP stacks need different Docker images | Stack architecture may not be generic enough | Review architecture before implementing; may need stack-specific base image logic |
| `pg_dump`/`mysqldump` not available in app containers | Database commands fail | Run dumps inside database containers, not app containers |
| CI template maintenance burden | Templates become outdated | Version templates; test generated configs in CI |
| Django/Next.js ecosystems have different conventions | Stubs don't match user expectations | Research best practices; get community feedback before finalizing |

---

## Definition of Done

Phase 4 is complete when:
1. At least one non-PHP stack (Next.js) works end-to-end
2. Database backup/restore works for PostgreSQL, MySQL, and MariaDB
3. CI template generation produces valid, working pipeline configs
4. Stack service management (add/remove) modifies compose files correctly
5. All features work from compiled binary
6. Documentation covers new stacks, database commands, and CI setup
