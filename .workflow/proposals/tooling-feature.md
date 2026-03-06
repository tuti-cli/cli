# Implementation Plan: Custom Tooling Feature for Tuti CLI

## Context

**Problem:** Users want to run commands through Tuti CLI that execute inside Docker containers, similar to Lando's tooling feature.

**Goal:** Enable commands like `tuti artisan migrate`, `tuti npm run dev`, `tuti composer update`, `tuti wp get posts` - where Tuti routes these to the correct container.

**Decision:** **Option A: Catch-All Command** with stack defaults auto-discovered from stubs.

---

## User Experience

```bash
tuti artisan migrate        # → docker compose exec app php artisan migrate
tuti npm run dev            # → docker compose exec node npm run dev
tuti composer install       # → docker compose exec app composer install
tuti wp post list           # → docker compose exec app wp post list
tuti mycustomcmd --flag     # → custom tool from .tuti/tooling.json
```

---

## Architecture

### Custom Application Class
Laravel Zero doesn't natively support catch-all commands. We'll create a custom `TutiApplication` that catches `CommandNotFoundException` and delegates to tooling:

```php
// app/Application/TutiApplication.php
final class TutiApplication extends Application
{
    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::doRunCommand($command, $input, $output);
        } catch (CommandNotFoundException $e) {
            $toolName = $input->getFirstArgument();
            if ($this->toolingService->hasTool($toolName)) {
                return $this->runTooling($toolName, $input, $output);
            }
            throw $e;
        }
    }
}
```

### Configuration Resolution
1. Load stack defaults from `stubs/stacks/{stack}/tooling.json` (bundled in PHAR)
2. Load user overrides from `.tuti/tooling.json`
3. Merge with user config taking precedence

### Security (Critical)
```php
// SAFE: Array-based execution (no shell interpolation)
$command = [
    'docker', 'compose', 'exec',
    '-T',
    '-w', $workingDir,
    $serviceName,
    ...$cmdParts,  // ['php', 'artisan']
    ...$userArgs   // ['migrate', '--force']
];
Process::run($command);  // Laravel escapes each array element
```

### Pre-flight Validation
```php
// 1. Docker available
if (!$this->dockerExecutor->isDockerAvailable()) { /* error */ }

// 2. Project initialized
if (!$this->projectDirectoryService->exists()) { /* error */ }

// 3. Container running
if (!$this->isContainerRunning($serviceName)) { /* error */ }

// 4. Service exists in compose
if (!$this->serviceExists($serviceName)) { /* error */ }
```

---

## Configuration Schema

### Tool Definition (`.tuti/tooling.json`)
```json
{
  "tools": {
    "artisan": {
      "service": "app",
      "cmd": ["php", "artisan"],
      "description": "Run Laravel Artisan commands",
      "tty": "auto",
      "dir": "/var/www/html"
    },
    "npm": {
      "service": "node",
      "cmd": ["npm"],
      "description": "Run npm commands",
      "tty": false
    },
    "composer": {
      "service": "app",
      "cmd": ["composer"],
      "description": "Run Composer commands"
    },
    "wp": {
      "service": "app",
      "cmd": ["wp"],
      "description": "Run WP-CLI commands",
      "dir": "/var/www/html"
    }
  },
  "defaults": {
    "service": "app",
    "tty": "auto",
    "dir": "/var/www/html"
  }
}
```

### Schema Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `service` | string | Yes | Container service name (app, node, etc.) |
| `cmd` | string[] | Yes | Command parts as array |
| `description` | string | No | Help text for `tuti tooling:list` |
| `tty` | bool/"auto" | No | Interactive mode (default: "auto") |
| `dir` | string | No | Working directory in container |
| `env_passthrough` | string[] | No | Host env vars to pass to container |

---

## Files to Create

```
app/
├── Application/
│   └── TutiApplication.php         # Custom Application with catch-all
├── Commands/Tooling/
│   ├── ToolingListCommand.php      # tooling:list - show available tools
│   └── ToolingValidateCommand.php  # tooling:validate - check config
├── Services/Tooling/
│   ├── ToolingConfigService.php    # Load/merge tooling.json
│   └── ToolingExecutorService.php  # Execute tools in containers
├── Domain/Tooling/
│   └── ToolingDefinitionVO.php     # Tool config value object

stubs/stacks/
├── laravel/tooling.json            # Laravel default tools
└── wordpress/tooling.json          # WordPress default tools

bootstrap/
└── app.php                         # Update to use TutiApplication
```

---

## Implementation Steps

### Step 1: Domain Layer
- [ ] Create `ToolingDefinitionVO` value object
- [ ] Define schema validation rules

### Step 2: Configuration Service
- [ ] Create `ToolingConfigService` to load/merge configs
- [ ] Load from stack stubs (PHAR-compatible via `base_path()`)
- [ ] Merge with user `.tuti/tooling.json`
- [ ] JSON schema validation

### Step 3: Execution Service
- [ ] Create `ToolingExecutorService`
- [ ] Pre-flight validation (Docker, container, service)
- [ ] TTY detection (auto/true/false)
- [ ] Array-based command execution (security)
- [ ] Exit code propagation

### Step 4: Custom Application
- [ ] Create `TutiApplication` extending `Application`
- [ ] Catch `CommandNotFoundException`
- [ ] Delegate to `ToolingExecutorService`
- [ ] Update `bootstrap/app.php`

### Step 5: Helper Commands
- [ ] Create `ToolingListCommand` (`tuti tooling:list`)
- [ ] Create `ToolingValidateCommand` (`tuti tooling:validate`)

### Step 6: Stack Defaults
- [ ] Create `stubs/stacks/laravel/tooling.json`
- [ ] Create `stubs/stacks/wordpress/tooling.json`

### Step 7: Tests
- [ ] Unit tests: `ToolingConfigService`, `ToolingExecutorService`
- [ ] Feature tests: ToolingCommand execution
- [ ] Test edge cases: invalid tool, container stopped, etc.

---

## Stack Default Tools

### Laravel (`stubs/stacks/laravel/tooling.json`)
```json
{
  "tools": {
    "artisan": {"service": "app", "cmd": ["php", "artisan"]},
    "composer": {"service": "app", "cmd": ["composer"]},
    "php": {"service": "app", "cmd": ["php"]},
    "npm": {"service": "node", "cmd": ["npm"]},
    "node": {"service": "node", "cmd": ["node"]}
  }
}
```

### WordPress (`stubs/stacks/wordpress/tooling.json`)
```json
{
  "tools": {
    "wp": {"service": "app", "cmd": ["wp"], "dir": "/var/www/html"},
    "composer": {"service": "app", "cmd": ["composer"]},
    "php": {"service": "app", "cmd": ["php"]},
    "npm": {"service": "node", "cmd": ["npm"]}
  }
}
```

---

## Verification

### Unit Tests
```bash
docker compose exec -T app composer test:unit -- --filter Tooling
```

### Manual Testing
```bash
# From a Laravel project directory
tuti artisan migrate
tuti artisan --help
tuti npm install
tuti composer update

# List available tools
tuti tooling:list

# Validate configuration
tuti tooling:validate

# Error cases
tuti nonexistentcmd    # Should show "Tool 'nonexistentcmd' not found"
cd /tmp && tuti artisan migrate  # Should show "Not a tuti project"
```

### Edge Cases to Test
- Tool name conflicts with existing command (should run existing command)
- Container not running (clear error message)
- Service not in compose file (list available services)
- Malformed tooling.json (validation error)
- Interactive commands (artisan tinker, mysql) - TTY passthrough

---

## Reserved Commands (Won't be caught as tools)
- `list`, `help`, `init`, `start`, `stop`, `restart`, `status`
- `stack:*`, `local:*`, `infra:*`, `tooling:*`
- All currently registered commands
