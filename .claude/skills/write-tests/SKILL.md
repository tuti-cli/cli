---
name: write-tests
description: Write comprehensive Pest tests for Tuti CLI commands and services. Use when creating new test files, improving test coverage, writing tests for commands in app/Commands/, or when asked to test a feature. Handles command tests, service unit tests, mocking, Process::fake() patterns, and trait-based test helpers.
---

# Write Tests

Generate comprehensive Pest tests for Tuti CLI following established patterns and 2026 best practices.

## Quick Start

**For Commands:**
```php
<?php

declare(strict_types=1);

use LaravelZero\Framework\Commands\Command;

describe('YourCommand', function (): void {
    beforeEach(function (): void {
        // Setup mocks, fakes
    });

    it('executes successfully', function (): void {
        $this->artisan('your:command')
            ->assertExitCode(Command::SUCCESS);
    });
});
```

**For Services:**
```php
<?php

declare(strict_types=1);

describe('YourService', function (): void {
    it('returns expected value', function (): void {
        $service = app(YourService::class);

        expect($service->doSomething())->toBeTrue();
    });
});
```

## Workflow

### 1. Analyze the Target
- Read the command/service to understand inputs, outputs, dependencies
- Identify: signature, arguments, options, services injected, exit codes
- Check for trait usage (HasBrandedOutput, etc.)

### 2. Determine Test Type
| Target | Location | Focus |
|--------|----------|-------|
| Command | `tests/Feature/Console/` | Artisan execution, output, exit codes |
| Service | `tests/Unit/Services/` | Pure logic, return values |
| Infrastructure cmd | `tests/Feature/Console/` | Mock interfaces, state changes |
| Local cmd | `tests/Feature/Console/` | Use CreatesLocalProjectEnvironment trait |

### 3. Write Tests by Category
Use `describe()` blocks to organize:
- **Registration**: signature, description, traits
- **Happy Path**: successful execution
- **Edge Cases**: invalid input, missing files
- **Error Handling**: failures, exceptions

### 4. Mock External Dependencies
- Docker commands → `Process::fake()`
- Services → `$this->app->instance(Interface::class, $mock)`
- File system → Use temp directories with cleanup

## ⚠️ Important Rules

### NEVER Modify Production Files for Tests
Tests must be isolated from production code. Use `tests/Fixtures/` for:
- Stack templates and manifests
- Service configurations
- Any file-based test data

**❌ WRONG:**
```php
// Creating files in stubs/stacks/ during tests
file_put_contents(stack_path('test-stack') . '/stack.json', '...');
```

**✅ CORRECT:**
```php
// Use fixtures directory
$fixturePath = dirname(__DIR__, 3) . '/Fixtures/stacks/test-stack';
// Or use UsesStackFixtures trait
```

### Test Fixtures Structure
```
tests/Fixtures/
├── stacks/
│   └── test-stack/
│       ├── stack.json
│       ├── docker-compose.yml
│       └── services/
│           ├── registry.json
│           └── databases/
│               └── postgres.stub
└── configs/
    └── test-config.json
```

## Test Categories

Every command test should cover:

1. **Registration** - Is command registered? Correct signature?
2. **Traits** - Uses HasBrandedOutput?
3. **Happy Path** - Executes successfully
4. **Arguments/Options** - Handles all input variations
5. **Output** - Displays expected messages
6. **Error States** - Returns FAILURE when appropriate

## Reference Files

- [patterns.md](references/patterns.md) - Complete code templates for all test types
- [mocks.md](references/mocks.md) - Mocking strategies, Process::fake(), fakes

## Key Patterns

### Process::fake() for Docker Commands
```php
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    Process::fake([
        '*docker*compose*' => Process::result(output: 'OK'),
    ]);
});

it('executes docker compose', function (): void {
    $this->artisan('local:start')->assertSuccessful();

    Process::assertRan(function ($command) {
        return str_contains(commandStr($command), 'docker')
            && str_contains(commandStr($command), 'compose');
    });
});

function commandStr($command): string {
    return str_replace("'", '', $command);
}
```

### Service Mocking
```php
beforeEach(function (): void {
    $this->fakeService = new FakeSomeService();
    $this->app->instance(SomeServiceInterface::class, $this->fakeService);
});
```

### File Cleanup
```php
beforeEach(function (): void {
    $this->testDir = createTestDirectory();
});

afterEach(function (): void {
    cleanupTestDirectory($this->testDir);
});

function createTestDirectory(): string
{
    $dir = sys_get_temp_dir() . '/tuti-test-' . bin2hex(random_bytes(8));

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function cleanupTestDirectory(string $dir): void
{
    if (is_dir($dir)) {
        File::deleteDirectory($dir);
    }
}
```

## File Naming

| Command | Test File |
|---------|-----------|
| `app/Commands/Infrastructure/StartCommand.php` | `tests/Feature/Console/InfraStartCommandTest.php` |
| `app/Commands/Local/StatusCommand.php` | `tests/Feature/Console/LocalStatusCommandTest.php` |
| `app/Services/Docker/DockerService.php` | `tests/Unit/Services/Docker/DockerServiceTest.php` |

## Coverage Targets

- **Commands**: >80%
- **Services**: >90%
- **Helpers**: >95%

## Running Tests

```bash
docker compose exec -T app composer test:unit          # All unit tests
docker compose exec -T app ./vendor/bin/pest --filter "test name"  # Single test
```
