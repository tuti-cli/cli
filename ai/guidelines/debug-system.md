# Debug System Guidelines for tuti-cli

## Overview
tuti-cli has a comprehensive debug logging system to help diagnose issues during development and troubleshooting.

## Debug Service Location
- **Class**: `App\Services\Debug\DebugLogService`
- **Helper**: `tuti_debug()` or `debug_log()`
- **Log Location**: `~/.tuti/logs/tuti.log`

## When to Add Debug Logging

### Always Log:
1. **Docker Operations** - All docker compose commands and their output
2. **File System Operations** - File checks, copies, deletions
3. **Process Executions** - External commands (composer, npm, etc.)
4. **State Changes** - Project state transitions
5. **Errors** - All exceptions and failures

### Example Usage:

```php
use App\Services\Debug\DebugLogService;

class MyService
{
    private DebugLogService $debug;

    public function __construct()
    {
        $this->debug = DebugLogService::getInstance();
        $this->debug->setContext('myservice:action');
    }

    public function doSomething(): void
    {
        $this->debug->info('Starting operation', ['param' => 'value']);

        try {
            // Do work
            $result = $this->performAction();
            
            $this->debug->debug('Operation result', ['result' => $result]);
            
        } catch (\Exception $e) {
            $this->debug->error('Operation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
```

## Log Levels

- **ERROR** - Always logged, even with debug disabled
- **WARNING** - Important issues that don't break functionality
- **INFO** - Key milestones in operations
- **DEBUG** - Detailed information for troubleshooting
- **COMMAND** - External commands being executed
- **PROCESS** - Process output (stdout/stderr)

## Helper Functions

```php
// Get debug service instance
$debug = tuti_debug();

// Quick logging
debug_log('message', ['data' => 'value'], 'INFO');
debug_log('error message', ['error' => $e->getMessage()], 'ERROR');
```

## Commands

### Debug Commands
```bash
# Show debug status
tuti debug

# Enable debug logging
tuti debug enable

# Disable debug logging  
tuti debug disable

# View logs
tuti debug logs --lines=100

# View errors only
tuti debug errors

# Clear logs
tuti debug clear
```

### Doctor Command
```bash
# Check system health
tuti doctor
```

## Context Setting

Always set context when starting a new operation:

```php
$debug = tuti_debug();
$debug->setContext('stack:init');  // stack:init, docker:orchestrator, etc.
```

## Best Practices

### 1. Log Before and After Important Operations
```php
$this->debug->info('Starting Docker build');
$process->run();
$this->debug->processOutput('docker build', $process->getOutput(), $process->getErrorOutput(), $process->getExitCode());
```

### 2. Include Relevant Data
```php
$this->debug->debug('Checking file', [
    'path' => $filePath,
    'exists' => file_exists($filePath),
    'permissions' => fileperms($filePath),
]);
```

### 3. Log Command Execution
```php
$this->debug->command('docker compose up', [
    'command' => $process->getCommandLine(),
    'working_dir' => $process->getWorkingDirectory(),
]);
```

### 4. Always Log Errors
```php
catch (Exception $e) {
    $this->debug->error('Failed to initialize stack', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    throw $e;
}
```

## Command Classes

In Command classes, inject DebugLogService:

```php
public function handle(
    SomeService $service,
    DebugLogService $debugService
): int {
    $debugService->setContext('command:name');
    $debugService->info('Command started');
    
    try {
        // Do work
        $debugService->info('Command completed');
        return self::SUCCESS;
    } catch (Throwable $e) {
        $debugService->error('Command failed', [
            'error' => $e->getMessage(),
        ]);
        return self::FAILURE;
    }
}
```

## Viewing Logs

### During Development
```bash
# Terminal 1: Enable debug and run command
tuti debug enable
tuti local:start

# Terminal 2: Tail logs in real-time
tail -f ~/.tuti/logs/tuti.log
```

### After Failure
```bash
# View recent errors
tuti debug errors

# View last 100 log lines
tuti debug logs --lines=100

# View all session logs
tuti debug logs --session
```

## Integration with Existing Code

When adding debug logging to existing services:

1. Add DebugLogService property:
```php
private DebugLogService $debug;
```

2. Initialize in constructor:
```php
public function __construct(...)
{
    $this->debug = DebugLogService::getInstance();
    $this->debug->setContext('namespace:class');
}
```

3. Add logging at key points:
- Before expensive operations
- After external calls
- On errors
- When state changes

## Examples from Codebase

### DockerComposeOrchestrator
```php
$this->debug->info('Starting project containers', ['project' => $project->getName()]);
$this->debug->command('docker compose up', ['command' => $process->getCommandLine()]);
$process->run();
$this->debug->processOutput('docker compose up', $stdout, $stderr, $exitCode);
```

### StackInitializationService
```php
$this->debug->info('Initializing stack', [
    'stack' => $stackPath,
    'project' => $projectName,
    'environment' => $environment,
]);

$this->debug->debug('Copying stack files');
$this->copierService->copyFromStack($stackPath);

$this->debug->debug('Stack files copied', ['count' => count($files)]);
```

## Don't Log Sensitive Data

Never log:
- Passwords
- API keys
- Private keys
- Full environment files with secrets

Instead:
```php
$this->debug->debug('Environment loaded', [
    'file' => $envPath,
    'variables_count' => count($env),
    // Don't log actual values
]);
```

## Testing Debug Code

When testing changes:
1. Enable debug: `tuti debug enable`
2. Run the operation
3. Check logs: `tuti debug errors` or `tuti debug logs`
4. Verify relevant information is captured
5. Ensure no sensitive data is exposed

## Future Enhancements

Consider adding:
- Log filtering by context
- Export logs to file
- Log rotation settings in config
- Performance metrics (timing)
- Structured logging to JSON
