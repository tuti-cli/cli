# Tuti CLI - Business Discovery

**Date:** 2026-02-06 (original) | 2026-03-24 (consolidated)
**Author:** Stubbornweb
**Project:** Tuti CLI
**Status:** Frozen snapshot - do not edit after initial creation

---

## 1. Business Overview

**What is Tuti CLI?**

Tuti CLI is a unified environment management and deployment tool for web developers. It replaces separate tools (Lando/DDEV for local, Deployer/Envoyer for production) with a single, zero-dependency binary that manages the full lifecycle from local development to production deployment.

**One command. Zero config. From local to production.**

- **Industry:** Web Developer / DevOps / SaaS Software
- **Team:** Solo developer, open to contributors
- **Revenue model:** Open source (MIT) with planned commercial support/Pro features
- **Geographic:** Remote / Distributed
- **Started:** Late 2025

---

## 2. The Problem

Developers juggle 5-10 separate tools to develop and ship a single application.

**Core pain points:**

1. Context switching friction between local dev tools and deployment tools
2. No environment parity guarantees (local != staging != production)
3. Manual port management conflicts across multiple projects
4. Complex multi-project management (cd into each, remember status)
5. No unified multi-app deployment orchestration
6. Poor terminal UI/UX across existing tools
7. Environment drift over time — different PHP/Node versions, extensions, OS quirks
8. Hidden, undocumented deployment knowledge (bash scripts, CI YAML, tribal knowledge)
9. Inconsistent secrets/config handling across environments
10. Poor rollback and recovery ergonomics
11. CI/CD pipelines become the "real" deployment tool — too complex, can't test locally
12. Multi-project cognitive overload (different ports, commands, deployment steps per project)
13. Toolchain fragmentation increases maintenance cost
14. Local != production performance characteristics
15. No single mental model from dev to prod

**Impact:**

- ~8-12 hours/week wasted on environment management per developer
- "Works on my machine" issues persist
- New team members can't safely deploy without tribal knowledge
- Tool fatigue — learning 5+ different CLI tools

---

## 3. Current Workflow

**How developers currently work (the problem being solved):**

1. Choose local dev tool (Lando, DDEV, Docker Compose, etc.)
2. Configure local environment (docker-compose.yml, .lando.yml, etc.)
3. Manually manage ports if running multiple projects
4. Develop application
5. Switch to completely different tool for deployment (Deployer, Envoyer, custom scripts)
6. Configure deployment separately (deployer.php, env configs, etc.)
7. Hope local and production environments match
8. Debug production issues that didn't happen locally

**Development process on tuti-cli itself:**

1. Write PHP code — services in `app/Services/`, commands in `app/Commands/`, contracts in `app/Contracts/`. All `declare(strict_types=1)`, `final`/`final readonly`, constructor injection only.
2. Run `composer test` — Rector -> Pint -> PHPStan -> Pest (parallel).
3. Manual testing inside Docker: `make up && make shell`, then `php tuti <command>`.
4. Build: `make build-phar` -> `make test-phar` -> `make build-binary` (phpacker, 4 platforms). Release: `make release-auto V=x.y.z` -> push tag -> GitHub Actions builds all binaries.

**Bottlenecks:**

- Deployment features not yet implemented (biggest gap)
- Limited test coverage (~5 test files for 15+ commands)
- No CI test pipeline on PRs
- Dev/test commands ship in production binary
- Global registry not exposed via CLI commands
- `stack:manage` is minimal
- `wp:setup` is placeholder

**People involved:** Solo developer (lead), potential contributors

**Tools in use:** PHP 8.4, Laravel Zero 12.x, Pest, PHPStan, Pint, Rector, Docker Compose v2, phpacker, Symfony Process/YAML, GitHub Actions

---

## 4. Desired Outcome

**Success looks like:**

```bash
# Day 1: New project in 30 seconds
tuti stack:laravel my-app && tuti local:start

# Week 1: Deploy to staging
tuti deploy staging

# Month 1: Manage 5 projects
tuti projects:list

# Month 6: Production with confidence
tuti deploy production
```

**Quantifiable goals:**

- 70% reduction in local setup time (30 min -> 5 min)
- ~80% reduction in "works on my machine" issues
- 1,000+ active developers using Tuti CLI
- 1k+ GitHub stars
- Replace Lando/DDEV/Deployer for target users

**Benefits:**

- Fewer commands and tools to remember
- Faster project onboarding
- One consistent workflow from local to production
- Safer rollbacks and repeatable deployments
- Fewer environment-related bugs reaching production
- Less frustration with tooling

---

## 5. Constraints

### Timeline

- **Target:** 1-3 months for usable core (slightly flexible)
- **Approach:** Simple, usable core first (create projects, run local, basic deploy), then iterate

### Technical constraints

- Requires Docker + Docker Compose v2 (`docker compose`)
- Must support Linux, macOS, Windows (WSL2)
- Must be single binary with zero dependencies (~25-50MB via phpacker + PHP 8.4 runtime)
- No database — file-based storage only (JSON configs, .env, YAML)
- Runtime state derived from Docker, never persisted
- Traefik v3.2 requires ports 80/443 on host
- `*.local.test` domains require `/etc/hosts` entries or dnsmasq
- Symfony Process for shell execution (300s timeout, 600s for builds)
- All code must work when compiled to PHAR
- PHP 8.4 minimum (`readonly` classes, `enum`, `match`, `mb_trim`)

### Compliance

- MIT License (open source)
- Telemetry disabled by default (`telemetry: false`)
- No usage data collected or transmitted

### Budget

- ___

### Must-have (MVP)

**Already implemented:**
1. Local environment management (start, stop, logs, status, rebuild)
2. Stack templates (Laravel 10 services, WordPress 5 services)
3. Traefik reverse proxy (auto-install, SSL, multi-project routing)
4. Environment variable management (single .env, auto-generated passwords)
5. Docker Compose generation (section-based stubs, YAML anchors)
6. System health checks (`tuti doctor`)
7. Debug logging system
8. Interactive command finder (`tuti find`)

**Not yet implemented (required for v1.0):**
9. Basic SSH deployment
10. Multi-project management commands
11. CI test pipeline
12. Exclude dev commands from production binary

### Nice-to-have

- Multi-app deployment orchestration
- Environment templates for staging/production
- Database snapshot/restore
- Next.js, Django, Nuxt.js, Rails stacks
- Import from Lando/DDEV configs
- Encrypted environment variables at rest
- WordPress auto-setup completion

---

## 6. Users & Stakeholders

**Primary users (daily):**
- PHP/Laravel developers
- WordPress developers (Standard + Bedrock)
- Full-stack developers
- Solo developers and small teams (1-10 people)
- Skill level: Intermediate to Advanced

**Secondary users (occasional):**
- Junior developers learning deployment
- Project managers checking status
- Agency developers managing multiple client projects
- Open source maintainers wanting reproducible dev environments

**Technical requirements for users:**
- Comfortable with CLI
- Basic Docker concepts (containers, volumes, networks)
- Familiarity with .env files

**Decision-makers:** Solo developer, community feedback influences priorities

---

## 7. Automation & Efficiency

**What Tuti CLI automates for users:**
- Docker environment setup and configuration
- Port allocation and conflict resolution (Traefik)
- Environment variable sync (single .env, secure password generation)
- Docker Compose file generation from stack templates
- SSL certificate setup (mkcert or self-signed)
- File permission fixes for Docker volumes
- Database/cache/search service configuration
- System health diagnostics

**Time savings for users:**

| Feature | Manual Time | With Tuti | Saved |
|---------|------------|-----------|-------|
| New project Docker setup | 1-4 hours | 2 minutes | 1-4 hrs |
| Port conflict debugging | 30 min/occurrence | 0 (Traefik) | 30 min |
| .env configuration | 30-60 min/env | Auto-generated | 30-60 min |
| Service selection | 1-2 hrs writing compose | Interactive, auto-generated | 1-2 hrs |
| SSL setup | 30-60 min/project | Auto via Traefik | 30-60 min |
| Multi-project switching | 15 min/switch | `tuti local:start` | 15 min |

**Estimated savings:** 5-10 hours/week per developer

**Not yet automated (opportunities):**
- Running tests on PRs (no CI test workflow)
- Documentation generation from code
- Cross-platform integration testing
- Binary smoke tests across all platforms in CI

---

## 8. Data & Integration

**Data managed:**

| Data | Format | Location |
|------|--------|----------|
| Global config | JSON | `~/.tuti/config.json` |
| User settings | JSON | `~/.tuti/settings.json` |
| Project registry | JSON | `~/.tuti/projects.json` |
| Project metadata | JSON | `{project}/.tuti/config.json` |
| Stack manifests | JSON | `stubs/stacks/{stack}/stack.json` |
| Docker Compose | YAML | `{project}/.tuti/docker-compose.yml` |
| Environment vars | .env | `{project}/.env` |
| Service stubs | YAML (custom sections) | `stubs/stacks/{stack}/services/**/*.stub` |
| Debug logs | Text (rotating 5MB x 5) | `~/.tuti/logs/tuti.log` |
| Runtime state | In-memory (from Docker) | `docker compose ps --format json` |

**Key design decision:** No database — purely file-based. Runtime state derived from Docker, never persisted.

**Current integrations:**
- Docker Engine / Docker Compose v2 (via Symfony Process)
- Traefik v3.2 (via Docker labels)
- serversideup/php Docker images (base images)
- Laravel projects (detects `artisan`)
- WordPress projects (Standard + Bedrock)
- Git (stack template repositories)
- mkcert / OpenSSL (SSL certificates)

**Future integrations:**
- SSH (remote deployment)
- CI/CD platforms (GitHub Actions, GitLab CI)
- Hosting providers (AWS, DigitalOcean, Forge, Vapor)
- Node.js/Python/Ruby project stacks

**Data quality concerns:**
1. Two placeholder systems coexist (`{{VAR}}` build-time, `${VAR:-default}` runtime) — risk of silent substitution failures
2. No JSON schema validation for config files — falls back to defaults silently
3. Global registry can go stale if projects are moved/deleted
4. Docker Compose YAML generation is string-based — risks indentation errors
5. No config migration system between versions

---

## 9. Risks & Concerns

**Technical risks:**

1. **No deployment implementation** — core differentiator has zero code. Without it, tuti-cli competes directly with Lando/DDEV on their turf.
2. **Test coverage gap** — ~5 test files for 15+ commands and 13+ services. Refactors could break features silently.
3. **Single developer bottleneck** — 27 commands, 13+ services, 2 stacks, build infrastructure. Bus factor of 1.
4. **Docker socket security** — Traefik mounts `/var/run/docker.sock` (read-only). If compromised, attacker reads all Docker metadata.
5. **Platform-specific issues** — HOME directory detection has 4+ fallback methods, suggesting real cross-platform issues encountered.
6. **Regex bug in StackEnvGeneratorService** — `CHANGE_THIS_IN_PRODUCTION` placeholders may not be replaced due to invalid non-capturing group pattern.

**Adoption barriers:**
- Traefik requires ports 80/443 — conflicts with Apache/Nginx on host
- `/etc/hosts` entries required for `*.local.test` — not automatic
- ~25-50MB binary size
- Docker Compose v2 required (some users on v1)

**What could cause failure:**
- Taking 2+ years to reach MVP
- Bugs making it worse than existing tools
- Abandonment due to lack of time/motivation
- Well-funded competitor launching similar tool
- phpacker, serversideup/php, or Docker Compose v2 breaking changes

---

## 10. Success Metrics

**Adoption:**
- GitHub stars: 1k+
- Weekly active installations: ___
- Community contributors: ___

**Quality (measurable today):**
- Test coverage: Commands >80%, Services >90%, Helpers >95%
- PHPStan level 5+ with zero errors
- Zero security vulnerabilities in generated configs
- Bug reports vs. feature requests ratio

**Milestones:**

| Timeframe | Target |
|-----------|--------|
| Month 1 | Deploy 1 real Laravel project, 5 beta testers, no critical bugs |
| Month 3 | 50+ stars, 10+ community members, self-service docs |
| Month 6 | 500+ stars, mentioned in Laravel community, first external PRs |
| Year 1 | 5,000+ stars, recommended in Laravel docs, sustainable community |

**Failure criteria:**
- < 100 active users after 1 year
- More bugs reported than problems solved
- Unmaintainable technical complexity
- Market moves to cloud IDEs making local dev irrelevant

---

## 11. Additional Context

- This scratches the developer's own itch — needed daily
- Planned as portfolio piece and potential business (commercial support, managed hosting)
- Connections in Laravel community for promotion
- Heavy AI-assisted development (Claude Code + GitHub Copilot configured)
- Comprehensive display system (HasBrandedOutput, 50+ methods, 5 themes) is a UX differentiator
- Two production-ready stacks with pluggable architecture for adding more

**Inspiration:**
- Lando (great local dev, no deployment)
- Spin (modern local + deployment)
- Deployer (solid deployment, no local)
- Vercel CLI / Railway CLI (beautiful UX)
- Laravel Valet (minimal config)

---

## 12. Priority Assessment

- **Urgency:** 9/10
- **Importance:** 9/10

**If unsolved in 6 months:**
- Continue tool fragmentation frustration
- Competitors may capture the market
- Miss opportunity in Laravel community

**If unsolved in 1 year:**
- Significant opportunity cost
- Someone else builds this

**Worst case:**
- "Someone else builds this and I'm a user, not the creator"
- Continue wasting 10 hours/week on tool management
