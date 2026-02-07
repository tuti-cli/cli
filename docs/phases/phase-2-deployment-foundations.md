# Phase 2: Deployment Foundations

**Timeline:** 4-6 weeks
**Status:** Not Started
**Depends On:** Phase 1 (MVP: Local Development)
**Goal:** Implement basic deployment capabilities so developers can ship code from local to remote servers using a single tool.

---

## Why This Phase

Deployment is Tuti CLI's core differentiator. Without it, Tuti CLI is just another local dev tool competing with Lando, DDEV, and Sail. This phase closes the biggest gap between the vision ("local to production - one command") and reality.

---

## Scope

### 2.1 Deployment Configuration (Priority: Critical)

Define how users configure deployment targets in their projects.

**Deliverables:**
- [ ] `.tuti/deploy.json` configuration schema for deployment targets
  ```json
  {
    "targets": {
      "staging": {
        "method": "ssh",
        "host": "staging.example.com",
        "user": "deploy",
        "path": "/var/www/my-app",
        "branch": "develop",
        "php": "8.4",
        "hooks": {
          "before": [],
          "after": ["php artisan migrate --force", "php artisan config:cache"]
        }
      },
      "production": {
        "method": "ssh",
        "host": "prod.example.com",
        "user": "deploy",
        "path": "/var/www/my-app",
        "branch": "main",
        "keep_releases": 5
      }
    }
  }
  ```
- [ ] `tuti deploy:configure` - Interactive wizard to set up deployment targets
- [ ] Validate deployment config on save (host reachable, SSH key exists, path writable)
- [ ] Support for environment-specific `.env` files on remote servers

**User Stories:** US-8.3 (Configure Deployment Targets)

### 2.2 SSH Deployment (Priority: Critical)

Core deployment mechanism for staging and production servers.

**Deliverables:**
- [ ] `tuti deploy {target}` command (e.g., `tuti deploy staging`)
- [ ] Deployment steps:
  1. Validate deployment config and SSH connectivity
  2. Create new release directory on remote (`releases/{timestamp}/`)
  3. Upload code via `rsync` or `git clone/pull`
  4. Install dependencies (`composer install --no-dev`)
  5. Run before-hooks (migrations, etc.)
  6. Symlink shared directories (storage, uploads, .env)
  7. Switch current symlink to new release
  8. Run after-hooks (cache clear, queue restart)
  9. Clean up old releases (keep N configurable)
- [ ] Real-time deployment progress output with step indicators
- [ ] Deployment log saved locally and on remote
- [ ] Dry-run mode (`--dry-run`) to preview deployment steps without executing
- [ ] SSH key-based authentication (no password prompts)

**User Stories:** US-8.1 (Deploy via SSH)

### 2.3 FTP/SFTP Deployment (Priority: High)

Essential for WordPress developers using shared hosting.

**Deliverables:**
- [ ] `tuti deploy {target}` with `"method": "ftp"` or `"method": "sftp"`
- [ ] Diff-based upload (only changed files since last deployment)
- [ ] File tracking via local manifest (`.tuti/deploy-manifest.json`)
- [ ] Configurable exclude patterns (`.git`, `node_modules`, `.env`, `tests/`)
- [ ] Path scoping: deploy entire project or specific subdirectory (e.g., `wp-content/themes/my-theme`)
- [ ] Upload progress bar with file count and size
- [ ] SFTP preferred over FTP (warn if using plain FTP)

**User Stories:** US-8.2 (Deploy WordPress Theme/Plugin via FTP)

### 2.4 Rollback (Priority: High)

Safety net for failed deployments.

**Deliverables:**
- [ ] `tuti deploy:rollback {target}` command
- [ ] Lists available releases with timestamps
- [ ] Switches current symlink to previous release
- [ ] Runs rollback hooks (defined in deploy config)
- [ ] Supports rolling back to specific release (`--release={timestamp}`)
- [ ] Works for SSH deployments (FTP rollback is manual - warns user)

**User Stories:** US-8.4 (Rollback Deployment)

### 2.5 Deployment Status (Priority: Medium)

Visibility into deployment state.

**Deliverables:**
- [ ] `tuti deploy:status {target}` shows current release info on remote
- [ ] `tuti deploy:history {target}` shows deployment history (date, commit, user)
- [ ] `tuti deploy:releases {target}` lists releases on remote with sizes

---

## Architecture Decisions

### Release Directory Structure (SSH)

```
/var/www/my-app/
├── current -> releases/20260207_143000/   # Symlink to active release
├── releases/
│   ├── 20260207_143000/                   # Latest release
│   ├── 20260206_120000/                   # Previous release
│   └── 20260205_090000/                   # Older release
└── shared/
    ├── storage/                            # Persisted across releases (Laravel)
    ├── .env                                # Environment variables
    └── uploads/                            # WordPress uploads
```

### New Service Classes

| Class | Responsibility |
|-------|---------------|
| `DeploymentService` | Orchestrates deployment pipeline |
| `SshConnectionService` | SSH connection management (Symfony Process + ssh/rsync) |
| `FtpConnectionService` | FTP/SFTP file uploads (PHP FTP extension or phpseclib) |
| `ReleaseManagerService` | Release creation, symlink switching, cleanup |
| `DeploymentConfigService` | Reads/validates deploy.json |

### New Commands

| Command | Signature |
|---------|-----------|
| `deploy` | `tuti deploy {target} [--dry-run] [--branch=]` |
| `deploy:configure` | `tuti deploy:configure` |
| `deploy:rollback` | `tuti deploy:rollback {target} [--release=]` |
| `deploy:status` | `tuti deploy:status {target}` |
| `deploy:history` | `tuti deploy:history {target} [--limit=10]` |
| `deploy:releases` | `tuti deploy:releases {target}` |

---

## Success Criteria

- [ ] `tuti deploy staging` successfully deploys a Laravel app to a remote server
- [ ] `tuti deploy production` deploys with zero-downtime symlink switching
- [ ] `tuti deploy:rollback staging` reverts to previous release within 10 seconds
- [ ] FTP deployment uploads only changed files for a WordPress theme
- [ ] Deployment progress visible in real-time with step indicators
- [ ] Dry-run mode accurately previews all deployment steps
- [ ] All deployment commands have test coverage >80%
- [ ] Deployment works from compiled PHAR/binary

---

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| SSH/rsync not available on all platforms | Deployment fails on some systems | Detect tools in `tuti doctor`, provide alternatives |
| FTP extension not compiled into phpacker binary | FTP deployment broken | Test FTP in PHAR early; use phpseclib as pure PHP fallback |
| Remote server permissions vary wildly | Deployment fails with permission errors | Document requirements; add `deploy:check` validation command |
| Large projects take too long to rsync | Bad UX | Support git-based deployment as alternative; show progress |

---

## Definition of Done

Phase 2 is complete when:
1. A Laravel app can be deployed to a remote server via SSH with `tuti deploy staging`
2. A WordPress theme can be deployed to shared hosting via SFTP with `tuti deploy production`
3. `tuti deploy:rollback` successfully reverts SSH deployments
4. All deployment commands have tests passing in CI
5. Deployment works from compiled binary (not just `php tuti`)
6. Documentation covers deployment setup, configuration, and troubleshooting

---

## Milestone: v1.0.0

Completion of Phase 2 marks the **v1.0.0 release** - the first version that delivers on the core promise of "local development to production deployment in one tool."
