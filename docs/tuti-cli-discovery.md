# Business Discovery Template - TUTI CLI

**Date:** 2026-02-06
**Completed by:** Stubbornweb
**Business/Project Name:** Tuti CLI

---

## 1. BUSINESS OVERVIEW

### 1.1 What is your business/project?

Tuti CLI is an environment management and deployment tool for developers. It unifies local Docker development, and production and staging deployment into a single command-line tool. From local development to production - one command, zero config. We're solving the problem of developers needing multiple separate tools (Lando/DDEV for local, Deployer/Envoyer for production) by providing an all-in-one solution.

---

### 1.2 What industry are you in?

Web Developer / DevOps / SaaS Software

---

### 1.3 Business size and team

- **Number of employees:** Solo project potential contributors
- **Annual revenue (optional):** Open source and Commercial plans
- **Geographic location(s):** Remote and Distributed
- **Years in operation:** Started working on it end of 2025

---

## 2. THE PROBLEM

### 2.1 What specific problem are you trying to solve?

Developers currently need separate tools for local development (Lando, DDEV, Spin) and deployment (Deployer, Envoyer, custom scripts). This creates:
1. Context switching friction between tools and environments
2. No environment parity guarantees between local and production and staging
3. Manual port management conflicts when running multiple projects
4. Complex multi-project management (need to cd into each project, remember status)
5. No unified multi-app deployment orchestration for local staging and production environments
6. No good UI/UX terminal experience and list of commands
7. Environment drift over time
 - Local Docker configs, staging servers, and production servers slowly diverge
 - Different PHP/Node versions, extensions, system packages, OS quirks
 - “Works on my machine” still exists because tools don’t enforce parity
 - No single source of truth for environment definitions

8. Hidden, undocumented deployment knowledge
 - Deployment logic lives in:
 - Bash scripts
 - CI YAML
 - Someone’s head
 - New team members can’t safely deploy without tribal knowledge

9. Inconsistent secrets & config handling
 - .env locally, platform secrets in prod, CI secrets somewhere else
 - Different naming conventions per tool
 - Easy to accidentally deploy with missing or wrong config
 - No unified lifecycle for secrets across environments

10. Poor rollback & recovery ergonomics

 - Rollbacks are often:
   - Manual
   - Scripted differently per project
   - Not tested until production breaks
 - Local tools don’t simulate rollback paths
 - One CLI could guarantee deploy = rollbackable

11. CI/CD pipelines become the “real” deployment tool

 - Developers must understand GitHub Actions / GitLab CI internals just to ship code
 - CI config becomes more complex than the app itself
 - Local testing of deployment logic is painful or impossible
 - Tuti CLI allows local-first deployment validation
 - 
12. Multi-project cognitive overload

- Each project uses:
  - Different ports
  - Different commands
  - Different deployment steps
- Developers context-switch constantly:

``` text
Is this project using Lando or DDEV?
Which PHP version?
How do I deploy this one again?
```

 - No standard lifecycle for environments
 - Creating staging / preview environments is:
   - Slow
   - Manual
   - Error-prone
 - Destroying environments is often forgotten → cost + security risk

13. Toolchain fragmentation increases maintenance cost

- Each tool updates independently
- Breaking changes ripple unpredictably
- Version compatibility hell (Docker, Compose, CLI tools)
- One tool = one upgrade surface

14. Local ≠ production performance characteristics

- Developers don’t notice:
    - Volume mounts vs real disks
    - Cache differences
    - Queue / worker behavior
    - Network latency
- Bugs only appear after deploy
- Shared runtime definitions reduce surprises

15. No single mental model from dev → prod

- Local: “docker up”
- Staging: “ssh + script”
- Prod: “CI magic”
- Developers never see the full lifecycle

Current workflow forces developers to juggle 5-10 different tools just to develop and ship a single application, which can lead to increased development time and errors, as well as increased complexity and potential for errors. This can also lead to increased costs and decreased efficiency, as developers may need to spend more time troubleshooting and debugging issues that arise from using multiple tools.

---

### 2.2 How is this problem affecting your business today?

**Impact on revenue:**
I am losing potential customers/users loosing time spending time on cofings for docker based environments because the tool isn't ready yet and not being able to provide a seamless experience for developers.

**Impact on time/productivity:**
Developers waste significant time:
- Context switching between tools
- Debugging port conflicts
- Managing separate deployment processes
- Managing multiple environments
- Manually coordinating multi-app deployments
I spend about 2 hours daily on this pain points and releted to them.

**Impact on team morale:**
It is frustrating for me personally to manage and configure every time all required environments and tools, for contributors, and for potential users who are asking for features I can't ship fast becouse don't have one good tool. It is frustrating because it is a waste of time and energy, and it is frustrating because it is a barrier to entry for potential users.

**Impact on customers:**
Potential users (developers) are stuck with:
- Fragmented workflows
- Tool fatigue (learning 5+ different CLI tools)
- Unreliable local-to-production or local-to-production parity or local-to-staging-to-production parity
Cline are not released yet so we are not able to provide a seamless experience for developers but I can relate to my previous experience with similar tools and manual configuration. Where I spent a lot of time configuring and managing environments, and it was frustrating because it was a waste of time and energy, and it was frustrating because it was a barrier to entry for developers who don't know how to configure and manage environments and DevOps.

**Impact on growth:**
Nothing blocks growth it's not relised yet and it's under active development.

---

### 2.3 What have you tried so far to solve this problem?

- "Built the core CLI structure using Laravel Zero"
- "Implemented basic Docker management commands"
- "Created laravel and wordpress stacks"
- "Created port allocation system"
- "Researched competing tools (Lando, DDEV, Deployer) to understand gaps"
- "Started building multi-project management features"

---

### 2.4 Why didn't previous solutions work?

Existing tools in market don't fully solve the problem because:
- Lando/DDEV: Only handle local dev, no deployment
- Deployer/Envoyer: Only handle deployment, no local dev
- Combining both: Creates fragmented workflows and no parity guarantees

**Specific technical and design challenges encountered during tuti-cli development:**

1. **File permissions in Docker volumes** - Container user (www-data) doesn't have write permissions to host-mounted volumes. Required building custom entrypoint scripts (`entrypoint-dev.sh`) that run as root to fix storage directory ownership before switching to the app user. This is a universal Docker pain point that tuti-cli now solves automatically.

2. **Traefik routing complexity** - Getting 404 errors from Traefik when projects don't declare explicit service references in their Docker labels. The solution required adding `traefik.http.routers.X.service=Y` labels explicitly -- something most developers miss when configuring Traefik manually. Tuti-cli generates these labels automatically.

3. **Vendor/node_modules in named volumes** - Using Docker named volumes for `vendor/` and `node_modules/` causes "class not found" errors because the named volume overrides the host mount. The solution: never use named volumes for dependency directories, just mount the project root with `:cached` flag.

4. **Redis password as string "null"** - Setting `REDIS_PASSWORD=null` in .env is treated as the literal string "null" by Docker, not as empty. Required using `REDIS_PASSWORD=` (empty) with `${REDIS_PASSWORD:-}` syntax in compose files.

5. **Environment variable sharing** - Docker Compose and Laravel both need .env variables but in different formats. Solved with a single .env file strategy where both systems share the same file, with clear section separation and explicit `--env-file ./.env` passing to Docker Compose.

6. **Multi-framework compose generation** - Each framework (Laravel, WordPress) needs different Docker Compose configurations, but services (databases, cache, etc.) should be reusable. Solved with a section-based stub format (`# @section: base`, `# @section: dev`, `# @section: volumes`, `# @section: env`) that splits each service into framework-agnostic sections.

7. **Binary compilation challenges** - PHAR files require PHP on the host, defeating the "zero dependencies" goal. Required integrating phpacker to embed the PHP 8.4 runtime into the binary itself, producing ~25-50MB self-contained executables for 4 platforms.

---

## 3. CURRENT WORKFLOW

### 3.1 Describe your current process (step-by-step)

**How developers currently work (the problem you're solving):**

**Step 1:** Choose local dev tool (Lando, DDEV, Docker Compose, etc.)

**Step 2:** Configure local environment (docker-compose.yml, .lando.yml, etc.)

**Step 3:** Manually manage ports if running multiple projects

**Step 4:** Develop application

**Step 5:** Switch to completely different tool for deployment (Deployer, Envoyer, custom scripts)

**Step 6:** Configure deployment separately (deployer.php, env configs, etc.)

**Step 7:** Hope local and production environments match

**Step 8:** Debug production issues that didn't happen locally

**Step 9:** Uncosistent files for project especialy for wordpress custome themes or plugins

**Step 10:** Hard to deploy to different providers or FTP



**Your current development process on tuti-cli:**

**Step 1:** Write PHP code -- create/modify services in `app/Services/`, commands in `app/Commands/`, contracts in `app/Contracts/`. All classes use `declare(strict_types=1)`, `final` or `final readonly`, constructor injection only.

**Step 2:** Run `composer test` which executes 4 stages sequentially: Rector (automated refactoring dry-run) -> Pint (PSR-12 formatting check) -> PHPStan (static analysis level 5+) -> Pest (unit/feature tests in parallel).

**Step 3:** Test manually inside Docker: `make up && make shell`, then run `php tuti <command>` to test against real Docker Compose generation, stack initialization, and infrastructure management.

**Step 4:** Build PHAR with `make build-phar`, test it with `make test-phar`, then build native binaries for all platforms with `make build-binary` (uses phpacker to embed PHP 8.4 runtime). For releases: `make release-auto V=x.y.z` bumps version in `config/app.php`, builds PHAR, tests it, creates git tag. Pushing the tag to GitHub triggers the `release.yml` workflow which builds all platform binaries and creates a GitHub Release with auto-generated notes and download links.

---

### 3.2 Who is involved in this process?

- "Me (Lead Developer) - writing core functionality, architecture decisions, testing"
- "Contributors (X people) - submitting PRs for features Y and Z"

---

### 3.3 What tools/systems do you currently use?

Development:
- PHP 8.4 / Composer for building the CLI
- Laravel Zero 12.x framework
- PHPacker 0.6.4+ for building self-contained binaries
- Docker / Docker Compose v2 for container management
- Git/GitHub for version control
- Symfony Process component for shell command execution
- Symfony YAML for parsing compose files
- vlucas/phpdotenv for .env file handling

Testing:
- Pest 3.8.4/4.2.0 for unit and feature tests (parallel execution)
- PHPStan level 5+ for static analysis
- Laravel Pint for PSR-12 code formatting
- Rector for automated refactoring
- FakeDockerOrchestrator mock for testing without Docker
- Custom test helpers: CreatesHelperTestEnvironment, CreatesLocalProjectEnvironment, CreatesTestStackEnvironment

Distribution:
- GitHub Releases with auto-generated notes
- Bash install script (`scripts/install.sh`) that auto-detects platform (Linux/macOS, x64/ARM)
- Bash uninstall script (`scripts/uninstall.sh`) with optional `--purge` flag
- GitHub Actions workflow (`release.yml`) triggered by `v*` tags

---

### 3.4 How much time does this process take?

**Your current development time:**
- **Per day:** 6 hours
- **Per week:** 40 hours
- **Per month:** 120 hours

**Target user's time savings (estimated from implemented features):**
- Current workflow: ~8-12 hours/week managing Docker configs, env files, port conflicts, separate deployment tools
- With tuti-cli: ~2-3 hours/week (initial setup minutes, then just `tuti local:start/stop`)
- Time saved: ~6-9 hours/week per developer

**Breakdown of time saved by feature:**
| Feature | Current Manual Time | With Tuti CLI | Saved |
|---------|-------------------|---------------|-------|
| New project Docker setup | 1-4 hours | 2 minutes (`tuti stack:laravel myapp`) | 1-4 hrs |
| Port conflict debugging | 30 min/occurrence | 0 (Traefik handles routing) | 30 min |
| .env configuration | 30-60 min per env | Auto-generated with secure passwords | 30-60 min |
| Service selection (DB, cache, etc.) | 1-2 hours writing compose | Interactive selection, auto-generated | 1-2 hrs |
| SSL setup for local dev | 30-60 min per project | Auto via Traefik + mkcert | 30-60 min |
| Multi-project switching | 15 min/switch (cd, status check) | `tuti local:start` from any project | 15 min |

---

### 3.5 What are the bottlenecks in your current workflow?

**Your Answer:**

Based on codebase analysis, the actual current bottlenecks are:

1. **Deployment features not yet implemented** - The codebase has no deployment commands (`deploy:*`). Local development is solid (15+ commands), but remote deployment to staging/production servers is entirely absent. This is the biggest gap between the vision ("local to production - one command") and reality.

2. **Limited test coverage** - Only 5 test files exist: `FindCommandTest`, `ProjectInitializationService`, `StackInitializationService`, `StackRegistryManagerServiceTest`, plus 3 test helpers and 1 mock. Many core commands (`local:start`, `local:stop`, `install`, `doctor`, all `infra:*`) have no automated tests. The test infrastructure (helpers, mocks, Pest config) is well-designed but underutilized.

3. **No CI test pipeline** - The only GitHub Actions workflow is `release.yml` for building binaries. There is no CI workflow that runs `composer test` on pull requests. Code can be merged without tests passing.

4. **4 test/debug commands in production binary** - Commands like `test:registry`, `test:compose-builder`, `test:stack-loader`, `test:tuti-directory`, `test:stack-overrides`, `validate:quick`, and `ui:showcase` are development tools that will ship in the production binary. These need to be excluded from PHAR builds or gated behind a dev flag.

5. **Port management is passive, not active** - `DockerService.checkPortConflicts()` method exists but is not called before starting containers. Port conflicts are resolved architecturally through Traefik (all projects on ports 80/443), but direct database/redis port access across multiple running projects would still conflict.

6. **Global registry (`projects.json`) not exposed via commands** - `GlobalRegistryService` can register and list projects, but there's no CLI command to view registered projects (`projects:list`), switch between them, or check their status across the board. Multi-project management is partially implemented in the backend but not accessible to users.

7. **`stack:manage` command is minimal** - Listed as a command but lacks full functionality for managing stack templates (update, remove, list remote, compare versions).

8. **No WordPress auto-setup completion** - `wp:setup` command exists as a placeholder but doesn't complete the WordPress installation after Docker containers start (database creation, wp-config, admin user setup).

---

## 4. DESIRED OUTCOME

### 4.1 What would success look like?

**Your Answer:**
Success is when a developer can:

```bash
# Day 1: Start new Laravel project
$ tuti init my-app
$ tuti local:start
# Everything just works - local environment running in 30 seconds

# Week 1: Deploy to staging
$ tuti deploy staging
# One command deploys, no separate deployment tool needed

# Month 1: Managing 5 projects
$ tuti projects:list
# See all projects, their status, switch between them instantly

# Month 6: Production deployment with confidence
$ tuti deploy production
# Same tool, same config, guaranteed environment parity
```

Tuti-cli becomes the default choice for Laravel/PHP developers, recommended in Laravel docs, used by 10,000+ developers, replacing the need for 3-5 separate tools.

---

### 4.2 What specific results do you want to achieve?

**Quantifiable goals:**

- **Reduce time by:** 70% time reduction in local setup (from 30 mins to 5 mins for new project)
- **Increase revenue by:** Open-core model with paid Pro / Enterprise features and commercial support plans
- **Reduce errors by:** ~80% reduction in “works on my machine” issues through enforced environment parity from local to production
- **Serve more customers:** 1,000+ active developers using Tuti CLI
- **Other metrics:**
  - GitHub stars: 1k+
  - Weekly active installations: 30-50+
  - Community contributors: 2-3 people
  - Replace Lando, Spin, DDEV, Deployer competing tools for users

---

### 4.3 How will this solution make your day-to-day better?

### Personal productivity & focus

- Fewer commands and tools to remember across projects  
- Faster project onboarding with zero setup friction  
- Less time debugging environment-specific issues  
- Reduced context switching between local, staging, and production  
- More time spent writing code instead of managing infrastructure  

### Mental load & confidence

- Clear, predictable deployment workflows  
- Increased confidence when deploying to staging or production  
- Reduced anxiety around breaking production  
- Less reliance on tribal knowledge or “that one person who knows deploys”  
- Easier to reason about system behavior across environments  

### Workflow & operations

- One consistent workflow from local development to production  
- Fewer custom scripts to maintain and debug  
- Safer rollbacks and repeatable deployments  
- Easier environment resets and clean rebuilds  
- Simplified multi-project management from a single CLI  

### Team collaboration & onboarding

- Faster onboarding for new developers  
- Shared environment definitions across the team  
- Clear, documented deployment processes  
- Less “it works for me” friction in PR reviews  
- Reduced back-and-forth caused by mismatched environments  

### Code quality & reliability

- Fewer environment-related bugs reaching production  
- More reliable testing and staging validation  
- Earlier detection of configuration issues  
- Better parity between local testing and real-world behavior  

### Morale & satisfaction

- Less frustration with tooling  
- Fewer late-night hotfixes caused by deployment issues  
- Increased sense of control over the development lifecycle  
- Improved trust in tooling and team processes  
- Higher overall developer satisfaction  

---

### 4.4 How will this solution benefit your customers?

Customers will:

- Save 5–10 hours per week previously spent configuring local environments, fixing deployment issues, and managing multiple tools  
- Trust that local, staging, and production environments behave the same, eliminating “works on my machine” problems  
- Manage multiple projects effortlessly without manual port configuration or environment collisions  
- Deploy complex, multi-application systems with a single command, reducing operational overhead  
- Avoid port conflicts entirely, even when running many projects in parallel  
- Reduce tool fatigue by replacing multiple specialized tools with one consistent CLI  
- Onboard new developers faster, with no complex setup or undocumented scripts  
- Operate with greater confidence, knowing deployments are predictable, repeatable, and reversible  

Developer experience is further improved through:

- A modern, intuitive CLI interface built with Laravel Prompts  
- Clear visual feedback for actions, progress, and system state  
- Structured output for deployments, environment status, and errors  
- Consistent, branded command output that improves clarity and reduces mistakes  
- Save 5-10 hours per week managing local environments and deployments
- Have confidence in local-to-production parity
- Manage multiple projects effortlessly
- Deploy multi-app systems with one command
- Never worry about port conflicts again
- Reduce tool fatigue (learn one CLI instead of five)
- Get beautiful, modern CLI UX (built with Laravel Prompts and HasBrandedOutput trait providing 50+ UI methods across 13 categories: branding, status, actions, progress, files, sections, dividers, callout boxes, key-value, panels, badges, text formatting, and final messages with 5 color themes)

---

## 5. CONSTRAINTS & REQUIREMENTS

### 5.1 Timeline

- **When do you need this solved by?**  
  Within **1–3 months**

- **Is this deadline flexible?**  
  Yes — slightly

- **Why this timeline?**  
  The goal is to start using Tuti CLI as soon as possible by delivering a **simple, usable core first**, then iterating toward more advanced features.

  The initial phase focuses on:
  - Creating new Laravel or WordPress projects  
  - Running local development environments  
  - Performing basic deployments (e.g. deploying a WordPress theme or application to an FTP server)

  This approach enables immediate real-world usage and validation, while more complex features—such as multi-environment orchestration, advanced deployment strategies, and team workflows—are added incrementally in later phases.

---

### 5.3 Technical constraints

Must-have technical requirements:
- Requires Docker and Docker Compose v2 (uses `docker compose` not `docker-compose`)
- Must support Linux, macOS, Windows (WSL2)
- Must support multiple platforms for binary builds (linux-x64, linux-arm64, macos-x64, macos-arm64)
- Must integrate with existing Laravel/Wordpress/PHP projects (detects `artisan`, `composer.json`, `.git`)
- Must be a single binary with zero dependencies (no PHP installation required for users) -- achieved via phpacker embedding PHP 8.4 runtime
- Binary size ~25-50MB per platform (acceptable for self-contained runtime)

**Actual technical constraints from codebase:**
- **No database required** -- all data stored as JSON files (`config.json`, `settings.json`, `projects.json`) and .env files. No SQLite, no external database.
- **File-based state** -- project state (running/stopped) is derived from Docker at runtime via `docker compose ps --format json`, not persisted to disk. This means state is always accurate but requires Docker to be running.
- **Traefik v3.2 dependency** -- global reverse proxy requires ports 80 and 443 on the host. Only one Traefik instance can run. This conflicts with any other service using those ports.
- **`*.local.test` domain convention** -- requires `/etc/hosts` entries or dnsmasq for wildcard DNS. Not automatic on all platforms.
- **Symfony Process for shell execution** -- all Docker commands run via `Symfony\Component\Process`, with 300-second (5 min) default timeout for compose operations and 600-second (10 min) for builds.
- **PHAR compilation compatibility** -- all code must work when compiled to PHAR. Paths resolved via `base_path()` for stub files. No dynamic class loading from filesystem after compilation.
- **PHP 8.4 minimum** -- uses modern PHP features (`readonly` classes, `enum`, `match`, named arguments, `mb_trim`). Cannot target older PHP versions.

---

### 5.4 Compliance or regulatory requirements

**Your Answer:**
- Open source project (MIT License)

**Security implementation in current codebase:**
- **Credential generation:** Passwords for databases, Redis, MinIO, and WordPress salts are auto-generated using `bin2hex(random_bytes(16))` (32-char hex strings) -- cryptographically secure. `CHANGE_THIS` placeholders in env templates are replaced automatically.
- **Sensitive value masking:** `EnvCommand` masks values containing `PASSWORD`, `KEY`, `SECRET`, `TOKEN` when displaying environment variables (`env:check --show`).
- **Docker socket exposure:** Traefik mounts Docker socket as read-only (`/var/run/docker.sock:ro`) for container discovery. This is standard practice but grants container read access to all Docker metadata.
- **SSL certificates:** Auto-generated via `mkcert` (if available) or self-signed OpenSSL certificates. Stored in `~/.tuti/infrastructure/traefik/certs/`. Self-signed certs will trigger browser warnings.
- **Traefik dashboard auth:** Protected by htpasswd basic auth with auto-generated password stored in `~/.tuti/infrastructure/traefik/.env` and `secrets/users` file.
- **.env files contain secrets** -- database passwords, API keys, WordPress salts are stored in plain text in project `.env` file. Standard practice for Laravel projects, but no encryption at rest.
- **No SSH key management** -- currently not implemented. Deployment features (when built) will need to handle SSH keys, server credentials, and API tokens securely.
- **No secrets vault integration** -- no HashiCorp Vault, AWS Secrets Manager, or similar. All secrets in .env files.
- **Data privacy:** Tuti-cli stores `telemetry: false` in global config by default. No usage data is collected or transmitted.

---

### 5.5 Must-have features vs. Nice-to-have features

**Must-have (critical - MVP features):**

**Already implemented:**
1. Local environment management via Docker Compose (base + overlay pattern) -- `local:start`, `local:stop`, `local:logs`, `local:status`, `local:rebuild`
2. Stack templates -- Laravel/WordPress stack (PostgreSQL/MySQL/MariaDB, Redis, Meilisearch, Typesense, MinIO, Mailpit, Scheduler, Horizon) and WordPress stack (Standard + Bedrock, MariaDB/MySQL, Redis, MinIO, Mailpit, WP-CLI)
3. Traefik reverse proxy infrastructure -- auto-install, SSL, multi-project routing via `*.local.test` domains
4. Environment variable management -- single .env file strategy shared by Laravel + Docker, auto-generation with secure passwords
5. Docker Compose generation -- section-based stub system (`# @section: base/dev/volumes/env`), YAML anchors for shared config
6. System health checks -- `tuti doctor` validates Docker, Docker Compose, global config, infrastructure, current project config, compose syntax
7. Debug logging system -- structured logging to `~/.tuti/logs/tuti.log` with levels (error/warning/info/debug/command/process), enable/disable/clear commands
8. Interactive command finder -- `tuti find` with fuzzy search across all commands

**Not yet implemented (required for v1.0):**
9. Basic deployment to remote servers (SSH-based) -- no deployment commands exist in the codebase
10. Multi-project management commands -- `GlobalRegistryService` backend exists but no `projects:list`, `projects:status`, or `projects:switch` commands
11. CI test pipeline -- no GitHub Actions workflow for running tests on PRs
12. Exclude dev/test commands from production binary -- `test:*`, `validate:*`, `ui:showcase` should not ship

**Nice-to-have (would be great but can wait):**
1. Multi-app deployment orchestration (deploy microservices together)
2. Environment templates/blueprints for staging and production (overlay files exist in stubs but aren't used by commands yet)
3. Interactive dashboard UI
4. Database snapshot/restore
5. Advanced monitoring and log filtering/export
6. Next.js stack support (React/Node.js)
7. Django stack support (Python)
8. Nuxt.js stack support (Vue.js)
9. Rails stack support (Ruby)
10. Import from Lando/DDEV configs (migration tool)
11. Encrypted environment variables at rest
12. Active port conflict resolution (not just detection)
13. `stack:manage` command for updating/removing stack templates
14. WordPress auto-setup completion (`wp:setup` is currently a placeholder)

---

## 6. USERS & STAKEHOLDERS

### 6.1 Who will use this solution?

**Primary users (daily usage):**
- PHP/Laravel developers (especially Laravel ecosystem)
- WordPress developers (Standard and Bedrock workflows)
- Full-stack developers managing frontend + backend
- DevOps engineers managing multiple environments
- Solo developers / small teams (1-10 people)
- Technical skill level: Intermediate to Advanced

**Secondary users (occasional usage):**
- Junior developers learning deployment
- Project managers checking deployment status
- Agency developers managing multiple client projects (multi-project registry)
- Open source maintainers who want reproducible dev environments for contributors

**User technical skill level:**
Intermediate to Advanced - users must be comfortable with:
- Command line interfaces
- Docker concepts (containers, volumes, networks)
- SSH and deployment workflows
- Environment variables and .env files

**Additional context:**
- Target is Laravel ecosystem first (primary stack), WordPress second (already implemented), then broader PHP and other frameworks
- Users are frustrated with existing tool complexity -- tuti-cli provides branded, consistent UI via HasBrandedOutput with 50+ output methods
- Users want modern, beautiful CLI UX like what Vercel/Railway provide -- tuti-cli uses Laravel Prompts for interactive input (suggest, confirm, spin) and themed output (5 color themes: LaravelRed, Gray, Ocean, Vaporwave, Sunset)

---

### 6.2 Who are the decision-makers?

- "Me - I make all technical and product decisions"
- "Community feedback heavily influences priorities"
- "Potential sponsors/investors if pursuing funding"
- "Or: Open to community governance if project grows"

---

### 6.3 Who will be impacted by this change?

**Directly impacted:**
- Developers currently using Lando/DDEV/Spin/Deployer (will switch to tuti-cli)
- New developers entering the Laravel ecosystem (will use tuti-cli as default)

**Indirectly impacted:**
- Hosting providers (may integrate with tuti-cli)
- CI/CD services (may need to support tuti-cli deployment format)
- Laravel ecosystem (better DX could attract more developers)
- serversideup/php Docker image maintainers (tuti-cli depends on their images for both Laravel and WordPress stacks)

---

## 7. AUTOMATION & EFFICIENCY

### 7.1 What tasks do you want to automate?

**For your users (what Tuti CLI automates):**

- Docker environment setup and configuration  
- Port allocation and conflict resolution (via Traefik reverse proxy)  
- Environment variable synchronization (single `.env` file, auto-generated secure passwords)  
- Docker Compose file generation from stack templates with service selection  
- SSL certificate setup for local development (mkcert or self-signed)  
- File permission fixes for Docker volumes (entrypoint scripts)  
- Database/cache/search service configuration (interactive selection, auto-wired)  
- System health diagnostics (`tuti doctor`)  

**Additional automation for enhanced workflows:**

- Creation of new Laravel or WordPress projects with boilerplate setup  and more framework-specific configurations
- Automatic deployment of themes, plugins, or entire apps to FTP/SFTP servers  and other providers
- Environment lifecycle management (start, stop, reset, destroy) for multiple projects  
- Rollback and versioned deployment support for safe updates  
- Multi-project orchestration (run multiple apps with shared or separate stacks)  
- Pre-configured CI/CD pipeline templates (GitHub Actions, GitLab CI)  
- Backup and restore automation for databases and persistent volumes  
- Telemetry-free usage reporting for debugging and system insights  

> These automation tasks aim to minimize manual setup, reduce configuration errors, and provide a **single, consistent CLI workflow** from local development to production deployment.


**For you (tuti-cli development automation) -- what's already automated:**
- **Testing pipeline:** `composer test` runs Rector -> Pint -> PHPStan -> Pest sequentially, Pest runs in parallel mode
- **Binary builds for 4+ platforms:** `make build-binary` creates Linux (x64, arm64) + macOS (x64, arm64) binaries in one command via phpacker
- **Release workflow:** `make release-auto V=x.y.z` bumps version, builds PHAR, tests it, creates git tag. GitHub Actions then builds all binaries and publishes a GitHub Release with auto-generated notes.
- **Install script:** `scripts/install.sh` auto-detects platform, downloads correct binary, sets up PATH
- **Code quality:** Rector auto-refactoring, Pint auto-formatting, PHPStan static analysis

**Not yet automated (opportunities):**
- Running tests on PRs (no CI test workflow exists)
- Documentation generation from code (PHPDoc, command help text)
- Changelog generation from commits
- Cross-platform integration testing (testing actual Docker operations)
- Binary smoke tests across all platforms in CI
- Automated end-to-end CLI tests, simulating user commands in temporary environments  
- Docker image builds for CI or testing the CLI in isolated containers  
- Automated changelog categorization (e.g., feat/fix/docs) with semantic-release approach  
- Automated release notes translation or documentation publishing  
- Performance benchmarking for commands (measure execution time, memory usage)  
- Automated platform compatibility validation (test commands across Linux, macOS, and Windows)  
- Automatic telemetry validation (ensure privacy config works, no accidental data leaks)  
- Automated tagging of commands / help text consistency (ensures CLI docs are accurate)  


---

### 7.2 How much time could automation save?

**For your users:**
- **Per day:** 1-2 hours saved (no manual Docker config, deployment scripts, port management)
- **Per week:** 5-10 hours saved across team
- **Per month:** 20-40 hours saved = $1,000-$2,000 value at $50/hour

**For you (development process):**
- **Testing:** `composer test` runs all 4 stages in one command -- saves ~10-15 min/run vs running each tool separately. Pest parallel execution saves ~30-50% on test time vs sequential.
- **Building:** `make build-binary` creates all platform binaries in one step -- saves ~20-30 min vs building each platform manually. The Makefile abstracts all phpacker flags and paths.
- **Releasing:** `make release-auto V=x.y.z` + `git push --tags` automates the entire release from version bump to GitHub Release creation -- saves ~1-2 hours per release vs manual steps (version bump, PHAR build, test, tag, push, create release, upload 6 files, write notes).
- **Missing automation cost:** No CI test pipeline means bugs can reach main branch. Adding a PR test workflow would catch issues earlier, saving debug time estimated at ~2-5 hours/week.

---

### 7.3 What manual processes are error-prone?

**Your Answer:**
**For users (problems tuti-cli solves):**
- Manual port configuration leads to conflicts (happens frequently)
- Manual deployment scripts have typos and break production
- Environment variable mismatches between local/staging/production
- Forgetting deployment steps (migrations, cache clearing, etc.)
- Docker Compose YAML syntax errors (indentation, missing colons)
- Incorrect Traefik labels causing 404 errors (missing explicit service references)
- File permissions wrong after Docker volume mounts (www-data vs host user)
- Redis password set to string "null" instead of empty

**For tuti-cli development -- actual risks found in codebase:**
- **No test gate on PRs** -- code can be merged without passing tests. This is the highest-risk manual process.
- **Version bumping** -- `make version-bump` modifies `config/app.php` with sed, which could fail silently on unusual version strings. The `release-auto` target doesn't run tests before building.
- **Platform-specific binary testing** -- only the current platform's binary is tested locally (`make test-binary` detects your OS). Other platforms are only tested in CI after the release tag is pushed -- if they fail, the release is broken.
- **Dev commands in production** -- `test:*`, `validate:*`, `ui:showcase` commands will be included in PHAR/binary builds unless explicitly excluded. Need to configure `box.json` or remove them from production.
- **Stack stub changes** -- modifying a service stub (e.g., `postgres.stub`) affects all future project initializations. No automated test verifies that generated Docker Compose files are valid after stub changes.

---

## 8. DATA & INTEGRATION

### 8.1 What data do you work with?

**Your Answer:**
Tuti-cli manages the following data with these exact formats and structures:

| Data Type | Format | Service Class | Description |
|-----------|--------|---------------|-------------|
| Global config | JSON | `InstallCommand` (direct write) | `~/.tuti/config.json` -- version, telemetry, default env, infrastructure settings |
| Global settings | JSON | `GlobalSettingsService` | `~/.tuti/settings.json` -- user preferences (dot-notation access) |
| Project registry | JSON | `GlobalRegistryService` | `~/.tuti/projects.json` -- all known projects with paths, ports, last accessed |
| Project config | JSON | `ProjectMetadataService` | `.tuti/config.json` -- project name, type (laravel/wordpress), version, environments |
| Stack manifests | JSON | `StackLoaderService` | `stubs/stacks/{stack}/stack.json` -- PHP version, base image, services, environments |
| Service registries | JSON | `StackRegistryManagerService` | `stubs/stacks/{stack}/services/registry.json` -- available services with ports, vars |
| Stack registry | JSON | `StackRepositoryService` | `stubs/stacks/registry.json` -- available stack definitions |
| Docker Compose | YAML | `StackComposeBuilderService` | `.tuti/docker-compose.yml` + `.tuti/docker-compose.dev.yml` -- generated from stubs |
| Environment vars | .env | `StackEnvGeneratorService` | Project root `.env` -- shared by Laravel/Docker, auto-generated secure passwords |
| Service stubs | YAML (custom sections) | `StackStubLoaderService` | `stubs/stacks/{stack}/services/**/*.stub` -- `# @section:` markers |
| Debug logs | Text (structured) | `DebugLogService` (singleton) | `~/.tuti/logs/tuti.log` -- rotating, max 5MB, 5 files |
| Runtime state | In-memory (from Docker) | `ProjectStateManagerService` | Container status queried via `docker compose ps --format json` |

**Key design decision:** No database (SQLite or otherwise) -- purely file-based storage. All configuration is JSON, all Docker config is YAML, all environment data is .env format. Runtime state is derived from Docker, never persisted to disk.

---

### 8.2 Where is this data currently stored?

**Your Answer:**

**Global directory (`~/.tuti/`):**
```
~/.tuti/
├── config.json              # Global CLI configuration
├── settings.json            # User preferences (GlobalSettingsService)
├── projects.json            # Registered projects (GlobalRegistryService)
├── bin/
│   └── tuti                 # Installed binary
├── stacks/                  # Cached stack templates (from git repos)
├── cache/                   # Temporary files
├── logs/
│   └── tuti.log             # Debug log (DebugLogService, max 5MB x 5 files)
└── infrastructure/
    └── traefik/
        ├── docker-compose.yml  # Traefik v3.2 proxy config
        ├── .env                # Traefik env (TZ, dashboard credentials)
        ├── dynamic/
        │   └── tls.yml         # TLS config, middlewares, auth
        ├── certs/
        │   ├── local-cert.pem  # SSL cert (mkcert or self-signed)
        │   └── local-key.pem   # SSL key
        └── secrets/
            └── users           # htpasswd file for dashboard auth
```

**Project directory (per-project):**
```
{project-root}/
├── .env                     # Single shared env file (Laravel + Docker variables)
├── .tuti/
│   ├── config.json          # Project metadata (name, type, version, environments)
│   ├── docker-compose.yml   # Generated base compose (from stack template + services)
│   ├── docker-compose.dev.yml  # Development overlay
│   ├── docker/
│   │   └── Dockerfile       # Custom Dockerfile (from stack template)
│   ├── environments/
│   │   └── .env.dev.example # Environment template
│   └── scripts/
│       └── entrypoint-dev.sh  # Permission fixer (runs as root before app)
```

**Stack templates (embedded in binary, resolved via `base_path()`):**
```
stubs/
├── stacks/
│   ├── registry.json        # Available stacks (laravel, wordpress)
│   ├── laravel/             # 10 service stubs across 6 categories
│   └── wordpress/           # 5 service stubs across 5 categories
└── infrastructure/
    └── traefik/             # Global reverse proxy template
```

---

### 8.3 What systems need to connect or integrate?

**Your Answer:**
Must integrate with (currently implemented):
- Docker Engine / Docker Compose v2 (local environments) -- via `Symfony\Component\Process`
- Traefik v3.2 (reverse proxy, SSL, multi-project routing) -- via Docker labels
- serversideup/php Docker images (base images for Laravel fpm-nginx and WordPress fpm-apache)
- Laravel projects (detects `artisan` file for Laravel-specific .env updates)
- WordPress projects (Standard file structure or Bedrock/Composer structure)
- Git (stack template repositories defined in `stubs/stacks/registry.json`)
- mkcert (optional, for trusted local SSL certificates)
- OpenSSL (fallback for self-signed certificates)
- htpasswd (optional, for Traefik dashboard authentication)

Future integrations (not yet implemented):
- SSH (remote server deployment)
- CI/CD platforms (GitHub Actions, GitLab CI)
- Hosting providers (AWS, DigitalOcean, Forge, Vapor)
- Monitoring services (Sentry, New Relic)
- Node.js projects (Next.js, Nuxt.js stacks)
- Python projects (Django stack)
- Ruby projects (Rails stack)

---

### 8.4 Do you have any data quality issues?

**Your Answer:**

**Current data quality concerns found in codebase:**

1. **Variable substitution inconsistency** -- Two different placeholder systems coexist: `{{VAR}}` for build-time replacement in stubs and `${VAR:-default}` for Docker Compose runtime. The `StackEnvGeneratorService.replaceProjectVariables()` uses `str_replace("{{$key}}", ...)` which requires exact `{key}` format (single braces) in actual template files, but documentation says `{{VAR}}` (double braces). This could cause silent substitution failures.

2. **No JSON schema validation** -- `config.json`, `stack.json`, `registry.json` files are parsed with `json_decode()` but not validated against a schema. If a user manually edits these files and introduces missing keys, the `ProjectConfigurationVO::fromArray()` falls back to defaults (`'unknown'`, `'0.0.0'`) rather than throwing clear errors.

3. **Global registry can go stale** -- `GlobalRegistryService.projects.json` stores project paths, but if a user moves or deletes a project directory, the registry isn't updated. No cleanup/validation mechanism exists.

4. **Docker Compose YAML generation is string-based** -- Service stubs are appended to compose files using file operations and string concatenation, not parsed/serialized YAML. This risks YAML indentation errors or duplicate keys if a service stub has incorrect formatting.

5. **Environment file merge conflicts** -- `StackInitializationService.updateProjectEnv()` uses regex to replace values in existing .env files. If the .env file has been manually edited with unusual formatting (comments inline, extra spaces), the regex patterns may not match.

6. **No config migration system** -- If the config.json schema changes between versions, there's no migration mechanism. Old projects with outdated config formats may break with newer CLI versions.

---

## 9. RISKS & CONCERNS

### 9.1 What worries you most about this project?

**Your Answer:**

**Technical risks identified from codebase analysis:**

1. **No deployment implementation** -- The biggest risk is that the core differentiator (unified local + deployment) has zero implementation. Local dev works well, but without deployment, tuti-cli competes directly with Lando/DDEV on their home turf without their maturity.

2. **Test coverage gap** -- Only ~5 test files for 15+ production commands and 13+ services. Critical paths like `local:start`, `install`, Docker orchestration, and Traefik setup have no automated tests. A refactor could break user-facing features silently.

3. **Single developer bottleneck** -- One person maintaining 27 commands, 13+ services, 2 stack templates with 15 service stubs, plus build/release infrastructure. Bus factor of 1.

4. **Docker socket security exposure** -- Mounting `/var/run/docker.sock` (even read-only) into the Traefik container is a known security consideration. If Traefik is compromised, the attacker can read all Docker metadata.

5. **Platform-specific issues** -- The codebase handles `HOME` directory detection with 4+ fallback methods (`getenv('HOME')`, `$_SERVER['HOME']`, `posix_getpwuid`, `getenv('USERPROFILE')`). This suggests real cross-platform issues have been encountered but may not all be solved.

6. **`StackEnvGeneratorService.generateSecureValues()` has a regex bug** -- The pattern `/CHANGE_THIS(? :_IN_PRODUCTION)?/` has a space after `?` that makes it an invalid non-capturing group. This means `CHANGE_THIS_IN_PRODUCTION` placeholders may not be replaced, leaving insecure defaults in .env files.

[OWNER INPUT NEEDED for non-technical concerns: competition, burnout, timeline pressure, community building]

---

### 9.2 What could cause this project to fail?

**Your Answer:**
- "If it takes 2+ years to reach MVP and market moves on"
- "If bugs or complexity make it worse than existing tools"
- "If I abandon it due to lack of time/motivation"
- "If can't build community of contributors and users"
- "If a well-funded competitor launches similar tool"
- "If Docker dependencies make adoption too difficult"

**Technical failure modes from codebase:**
- If phpacker stops being maintained (sole dependency for zero-dependency binaries)
- If serversideup/php Docker images change their configuration format (both stacks depend on them)
- If Docker Compose v2 CLI format changes (all orchestration uses `docker compose` not `docker-compose`)
- If the PHAR compilation breaks with PHP 8.4+ changes (binary distribution stops working)

---

### 9.3 What resistance might you face internally?

**Your Answer:**
- "Users happy with current tools may not want to switch"
- "Learning curve for a new CLI tool"
- "Skepticism: 'another dev tool that will be abandoned in 6 months'"
- "Companies have invested in existing deployment pipelines"
- "Resistance to Docker requirement"

**Technical adoption barriers from codebase:**
- Traefik requires ports 80/443 -- conflicts with Apache/Nginx running on the host
- `/etc/hosts` entries required for `*.local.test` domains -- not automatic, OS-dependent
- ~25-50MB binary size -- large compared to typical CLI tools (though reasonable for embedded runtime)
- Docker Compose v2 required -- some users may still be on v1 (`docker-compose` command)

---

## 10. SUCCESS METRICS

### 10.1 How will you measure success?

**Your Answer:**
**Adoption metrics:**
- GitHub stars: 1k
- Weekly active installations: [OWNER INPUT NEEDED]
- Projects using tuti-cli: [OWNER INPUT NEEDED]

**Community metrics:**
- Contributors: [OWNER INPUT NEEDED]
- Discord/community members: [OWNER INPUT NEEDED]
- Issues/PRs from community: [OWNER INPUT NEEDED]

**Quality metrics (measurable from codebase today):**
- Bug reports vs. feature requests ratio
- Time to first successful deployment
- User retention (still using after 30/60/90 days)
- Test coverage percentage (currently low -- target: Commands >80%, Services >90%, Helpers >95%)
- PHPStan level maintained at 5+ with zero errors
- Zero security vulnerabilities in generated configs

**Business metrics (if applicable):**
- [OWNER INPUT NEEDED: Sponsors/donations, Commercial customers, Support contracts, etc.]

---

### 10.2 When will you know this solution is working?

**Your Answer:**

**First month (quick wins):**
- "Successfully deploy 1 real Laravel project with tuti-cli"
- "Get 5 beta testers to actually use it and provide feedback"
- "No critical bugs that block usage"

**3 months (adoption):**
- "50+ GitHub stars"
- "10+ community members providing feedback"
- "Documentation is good enough for self-service onboarding"

**6 months (transformation):**
- "500+ GitHub stars"
- "Mentioned in Laravel community discussions"
- "First external contributors submitting PRs"
- "Users prefer tuti-cli over alternatives"

**1 year (long-term impact):**
- "5,000+ GitHub stars"
- "Recommended in Laravel documentation"
- "Sustainable project with active community"
- "Clear path to monetization (if desired)"

---

### 10.3 What would make this project a failure?

**Your Answer:**
- "If after 1 year, less than 100 people are actively using it"
- "If it's too buggy and users report more problems than it solves"
- "If I burn out and abandon it halfway"
- "If the technical complexity makes it unmaintainable"
- "If market moves to a different approach (e.g., codespaces, cloud IDEs make local dev irrelevant)"

---

## 11. ADDITIONAL CONTEXT

### 11.1 Is there anything else we should know?

**Your Answer:**
- "This scratches my own itch - I personally need this tool daily"
- "Planning to use this as portfolio piece for [career goals]"
- "Considering building a business around this (commercial support, managed hosting, etc.)"
- "Have connections in Laravel community that could help with promotion"
- "Already have X beta testers lined up"

**Technical context from codebase:**

The project has a solid, well-architected foundation:

- **Clean architecture** -- Domain entities (`Project`, `ProjectConfigurationVO`, `ProjectStateEnum`), contracts (5 interfaces), infrastructure implementations, and services are properly separated. DDD-lite approach is consistent.
- **Extensive AI tooling setup** -- Both `.ai/` (7 skills, 11 guidelines for GitHub Copilot) and `.claude/` (7 commands, 11+ docs for Claude Code) are configured, suggesting heavy AI-assisted development. This is a development velocity multiplier.
- **Comprehensive display system** -- `HasBrandedOutput` trait provides 50+ output methods across 13 categories. This is a significant UX differentiator vs. competitors with plain text output.
- **Two production-ready stacks** -- Laravel (10 services across 6 categories) and WordPress (5 services across 5 categories with Standard + Bedrock support). The service registry pattern makes adding new services straightforward.
- **Debug system is production-ready** -- `DebugLogService` singleton with structured logging, context tracking, error collection, rotating log files. `tuti doctor` command provides comprehensive health checks.

---

### 11.2 Do you have any reference examples?

**Your Answer:**
**Tools I like:**
- Lando: Great local dev experience (but no deployment)
- Spin: Modern local dev experience (with deployment)
- Deployer: Solid deployment tool (but no local dev)
- Vercel CLI: Beautiful UX, want to match this experience
- Railway CLI: Simple yet powerful, good inspiration
- Laravel Valet: Minimal config approach
- Sail: Modern local dev experience

**What I want to do better:**
- Unified local + deployment (neither Lando nor Deployer do both)
- Modern CLI UX (better than DDEV's text output)
- Multi-project intelligence (better than manual switching)

**Additional technical inspiration from codebase choices:**
- serversideup/php Docker images (chosen for both stacks -- well-maintained, Laravel/WordPress optimized)
- Traefik v3.2 for reverse proxy (instead of Nginx Proxy Manager or Caddy)
- phpacker for binary compilation (instead of static PHP or Docker-based distribution)
- Laravel Zero 12.x as CLI framework (instead of Symfony Console directly)
- Pest over PHPUnit (modern, expressive test syntax)

---

### 11.3 What questions do you have for us?

**Your Answer:**

**Answerable from codebase analysis:**

> "Should I focus on Laravel-only first, or generic PHP from day 1?"

The codebase already supports both Laravel and WordPress with a pluggable stack system (`StackInstallerInterface`, `StackInstallerRegistry`). The architecture is generic-first: adding a new framework requires creating an installer class, a command, stubs, and a registry entry. **Recommendation: continue multi-framework but prioritize Laravel stack polish and testing before adding more frameworks.**

> "What's the best way to structure the project files for rapid iteration?"

The current structure is well-organized following domain-driven principles. The main improvement opportunity is test coverage -- adding tests for existing commands and services would enable faster, safer iteration. The test infrastructure (helpers, mocks, Pest config with custom expectations) is already in place.

**Questions that need strategic decisions:**
- "How should I prioritize features for MVP vs. v2.0?" -- Deployment is the biggest gap; everything else for local dev is working.
- "How do I balance building features vs. marketing/community building?" -- Prioritize marketing and community building to attract users and contributors, then focus on building features based on user feedback.

---

## 12. PRIORITY ASSESSMENT

### 12.1 How urgent is this problem? (1-10)

**Your Score:** 9

---

### 12.2 How important is this to your business? (1-10)

**Your Score:** 9

---

### 12.3 What happens if you don't solve this problem?

**Your Answer:**

**In 6 months:**
- "Continue being frustrated with current tool fragmentation"
- "Competitors may launch similar tools and capture market"
- "Miss opportunity to build presence in Laravel community"

**In 1 year:**
- "Opportunity cost - could have built significant user base"
- "Personal: Still dealing with daily dev workflow frustrations"
- "Miss potential business/career opportunities"

**Worst case:**
- "Someone else builds this and I'm left as a user, not creator"
- "Continue wasting 10 hours/week on tool management"

---

## NEXT STEPS

Based on this discovery, the following files will be generated:
- [x] `business-discovery-template.md` - This file (completed)
- [ ] `database-schema.md` - Data structure for project configs, environments, deployments (if needed)
- [x] `project-description.md` - Comprehensive project overview for contributors/documentation
- [x] `phases/` - MVP roadmap, feature prioritization, release planning (separate file per phase)
- [x] `user-stories.md` - User stories and acceptance criteria for each feature

---

## APPENDIX: CURRENT IMPLEMENTATION STATUS

### A. Command Inventory (from codebase)

| Command | Signature | Status | Has Tests |
|---------|-----------|--------|-----------|
| `install` | `tuti install [--force] [--skip-infra]` | Implemented | No |
| `doctor` | `tuti doctor [--fix]` | Implemented | No |
| `find` | `tuti find` | Implemented | Yes |
| `init` | `tuti init` | Implemented | No |
| `env:check` | `tuti env:check [--show]` | Implemented | No |
| `debug` | `tuti debug` | Implemented | No |
| `stack:laravel` | `tuti stack:laravel [name] [--mode=] [--services=*]` | Implemented | No |
| `stack:wordpress` | `tuti stack:wordpress [name] [--mode=] [--type=]` | Implemented | No |
| `stack:init` | `tuti stack:init {stack} {name}` | Legacy | No |
| `stack:manage` | `tuti stack:manage` | Minimal | No |
| `local:start` | `tuti local:start [--skip-infra]` | Implemented | No |
| `local:stop` | `tuti local:stop` | Implemented | No |
| `local:logs` | `tuti local:logs [--service=] [--tail=]` | Implemented | No |
| `local:status` | `tuti local:status` | Implemented | No |
| `local:rebuild` | `tuti local:rebuild [--pull]` | Implemented | No |
| `infra:start` | `tuti infra:start` | Implemented | No |
| `infra:stop` | `tuti infra:stop` | Implemented | No |
| `infra:restart` | `tuti infra:restart` | Implemented | No |
| `infra:status` | `tuti infra:status` | Implemented | No |
| `wp:setup` | `tuti wp:setup` | Placeholder | No |
| `ui:showcase` | `tuti ui:showcase` | Dev/Test only | No |
| `test:registry` | `tuti test:registry` | Dev/Test only | No |
| `test:compose-builder` | `tuti test:compose-builder` | Dev/Test only | No |
| `test:stack-loader` | `tuti test:stack-loader` | Dev/Test only | No |
| `test:tuti-directory` | `tuti test:tuti-directory` | Dev/Test only | No |
| `test:stack-overrides` | `tuti test:stack-overrides` | Dev/Test only | No |
| `validate:quick` | `tuti validate:quick` | Dev/Test only | No |

### B. Service Stubs Available

**Laravel Stack (10 services):**
| Category | Service | Version | Notes |
|----------|---------|---------|-------|
| databases | PostgreSQL | v17 | Default database |
| databases | MySQL | 8.4 | Alternative |
| databases | MariaDB | 11.4 | Alternative |
| cache | Redis | v7 | 256MB max memory |
| search | Meilisearch | v1.11 | Full-text search |
| search | Typesense | 27.1 | Alternative search |
| storage | MinIO | latest | S3-compatible |
| mail | Mailpit | latest | Email testing |
| workers | Scheduler | (PHP) | Laravel task scheduler |
| workers | Horizon | (PHP) | Queue management (requires Redis) |

**WordPress Stack (5 services):**
| Category | Service | Version | Notes |
|----------|---------|---------|-------|
| databases | MariaDB | 11.4 | Default (recommended) |
| databases | MySQL | 8.4 | Alternative |
| cache | Redis | v7 | Object caching |
| storage | MinIO | latest | S3-compatible |
| mail | Mailpit | latest | Email testing |
| cli | WP-CLI | latest | Auto-included |

### C. Architecture Diagram

```
User
 │
 ├─ tuti install          → InstallCommand → GlobalInfrastructureManager (Traefik)
 ├─ tuti stack:laravel    → LaravelCommand → StackInitializationService
 │                            ├─ StackLoaderService (parse stack.json)
 │                            ├─ StackFilesCopierService (copy templates)
 │                            ├─ StackComposeBuilderService (generate YAML)
 │                            ├─ StackEnvGeneratorService (generate .env)
 │                            └─ ProjectMetadataService (save config.json)
 ├─ tuti local:start      → StartCommand → ProjectStateManagerService
 │                            ├─ ProjectMetadataService (load config)
 │                            ├─ GlobalInfrastructureManager (ensure Traefik)
 │                            └─ DockerComposeOrchestrator (docker compose up)
 ├─ tuti local:stop       → StopCommand → ProjectStateManagerService
 │                            └─ DockerComposeOrchestrator (docker compose down)
 ├─ tuti doctor           → DoctorCommand → checks Docker, Compose, Traefik, project
 └─ tuti infra:*          → Infrastructure commands → GlobalInfrastructureManager

Storage:
 ~/.tuti/                 → Global config, settings, project registry, logs, Traefik
 {project}/.tuti/         → Project config, docker-compose, Dockerfile, scripts
 {project}/.env           → Shared environment variables (Laravel + Docker)
```

---

**Template Version:** 1.0
**Last Updated:** 2026-02-06
**Project:** Tuti CLI - https://github.com/tuti-cli/cli
