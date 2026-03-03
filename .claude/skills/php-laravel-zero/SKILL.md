---
name: stack/php-laravel-zero
description: "Stack-specific testing patterns and conventions for PHP/Laravel Zero projects. Pest framework, service mocking, command testing, and coverage requirements."
---

# PHP/Laravel Zero Stack Guide

Testing conventions and patterns for the Tuti CLI project (PHP 8.4, Laravel Zero 12.x, Pest).

## Testing Stack

| Component | Tool | Purpose |
|-----------|------|---------|
| Framework | Pest | Test writing and execution |
| Static Analysis | PHPStan | Type safety |
| Formatting | Laravel Pint | Code style (PSR-12) |
| Refactoring | Rector | Automated improvements |

## Test Commands

```bash
composer test              # Full suite: rector + pint + phpstan + pest
composer test:unit         # Pest tests only (parallel)
composer test:types        # PHPStan static analysis
composer test:lint         # Pint format check (dry-run)
composer test:refactor     # Rector check (dry-run)
composer test:coverage     # Pest with coverage
composer lint              # Fix formatting with Pint
composer refactor          # Fix code with Rector
```

## Test Structure

```
tests/
├── Feature/
│   ├── Concerns/          # Test helpers
│   │   ├── CreatesHelperTestEnvironment.php
│   │   ├── CreatesLocalProjectEnvironment.php
│   │   └── CreatesTestStackEnvironment.php
│   └── Console/           # Command tests
│       ├── InfraStartCommandTest.php
│       ├── LocalStartCommandTest.php
│       └── StackLaravelCommandTest.php
├── Unit/
│   └── Services/          # Service unit tests
│       ├── StackServiceTest.php
│       └── DockerExecutorServiceTest.php
├── Mocks/                 # Test mocks
│   └── FakeDockerOrchestrator.php
├── Pest.php               # Pest configuration
└── TestCase.php           # Base test case
```

## Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Test File | `{Class}Test.php` | `StackServiceTest.php` |
| Feature Test | `{Category}{Command}Test.php` | `InfraStartCommandTest.php` |
| Test Name | Descriptive sentence | `it('creates directory with correct structure')` |

## Pest Patterns

### Basic Test

```php
<?php

declare(strict_types=1);

use App\Services\MyService;

beforeEach(function () {
    $this->service = app(MyService::class);
});

describe('MyService', function () {
    it('does something expected', function () {
        $result = $this->service->doSomething();

        expect($result)->toBeTrue();
    });
});
```

### Command Test

```php
<?php

declare(strict_types=1);

use App\Commands\MyCommand;
use App\Contracts\MyServiceInterface;
use Symfony\Component\Console\Command\Command;

describe('MyCommand', function () {
    beforeEach(function () {
        $this->service = Mockery::mock(MyServiceInterface::class);
        $this->app->instance(MyServiceInterface::class, $this->service);
    });

    it('returns success on valid input', function () {
        $this->service->shouldReceive('process')->once();

        $this->artisan('my:command', ['arg' => 'value'])
            ->assertExitCode(Command::SUCCESS);
    });

    it('returns failure on error', function () {
        $this->service->shouldReceive('process')
            ->andThrow(new RuntimeException('Error'));

        $this->artisan('my:command', ['arg' => 'value'])
            ->assertExitCode(Command::FAILURE);
    });
});
```

### Service Test with Mock

```php
<?php

declare(strict_types=1);

use App\Services\StackService;
use App\Contracts\StackRepositoryInterface;

describe('StackService', function () {
    beforeEach(function () {
        $this->repository = Mockery::mock(StackRepositoryInterface::class);
        $this->service = new StackService($this->repository);
    });

    describe('getStack', function () {
        it('returns stack by name', function () {
            $this->repository
                ->shouldReceive('find')
                ->with('laravel')
                ->andReturn(['name' => 'laravel']);

            $result = $this->service->getStack('laravel');

            expect($result)->toBe(['name' => 'laravel']);
        });

        it('throws when stack not found', function () {
            $this->repository
                ->shouldReceive('find')
                ->with('unknown')
                ->andReturn(null);

            expect(fn() => $this->service->getStack('unknown'))
                ->toThrow(RuntimeException::class);
        });
    });
});
```

## Process Testing

When testing code that runs external processes:

```php
// Use array syntax for process commands
Process::run(['docker', 'info']);

// In tests, mock with wildcards
Process::fake([
    '*docker*' => Process::result(exitCode: 0),
]);

// Helper to strip quotes in assertions
function commandStr(string $cmd): string {
    return str_replace("'", '', $cmd);
}
```

## Test Helpers

### CreatesHelperTestEnvironment

```php
use Tests\Feature\Concerns\CreatesHelperTestEnvironment;

class MyTest extends TestCase
{
    use CreatesHelperTestEnvironment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testPath = $this->createTestDirectory();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestDirectory($this->testPath);
        parent::tearDown();
    }
}
```

### FakeDockerOrchestrator

```php
use Tests\Mocks\FakeDockerOrchestrator;

$orchestrator = new FakeDockerOrchestrator();
$this->app->instance(OrchestratorInterface::class, $orchestrator);
```

## Coverage Requirements

| Area | Minimum Coverage |
|------|-----------------|
| Commands | 80% |
| Services | 90% |
| Helpers | 95% |
| Overall | 80% |
| New Code | 90% |

## PHPStan Configuration

```neon
# phpstan.neon
parameters:
    level: 5
    paths:
        - app
    excludePaths:
        - app/Console/Kernel.php
```

## Pint Configuration

```php
// pint.json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "final_class": true
    }
}
```

## Running Specific Tests

```bash
# Single file
./vendor/bin/pest tests/Unit/Services/StackServiceTest.php

# By filter
./vendor/bin/pest --filter "creates directory"

# By describe block
./vendor/bin/pest --filter "StackService"

# Parallel execution
./vendor/bin/pest --parallel
```

## Common Assertions

```php
// Exit codes
->assertExitCode(Command::SUCCESS)
->assertExitCode(Command::FAILURE)

// Output
->expectsOutput('Expected output')
->expectsOutputToContain('partial')

// Files
expect($path)->toBeFile();
expect($path)->toBeDirectory();

// Arrays
expect($result)->toBeArray();
expect($result)->toHaveKey('status');
expect($result)->toContain('value');

// Exceptions
expect(fn() => $service->fail())
    ->toThrow(Exception::class);
expect(fn() => $service->fail())
    ->toThrow(Exception::class, 'Expected message');
```

## Regression Tests

For bug fixes, follow this pattern:

1. Write test that reproduces bug
2. Confirm test FAILS
3. Apply fix
4. Confirm test PASSES

```php
// Regression test for bug #123
it('handles null input gracefully', function () {
    // Before fix: this would throw
    // After fix: returns safe default
    $result = $this->service->process(null);

    expect($result)->toBeArray();
});
```
