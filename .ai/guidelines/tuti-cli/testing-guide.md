# Tuti CLI Testing Guide

## Testing Philosophy

We follow a comprehensive testing approach that ensures:
1. **Reliability** - Commands work as expected
2. **Maintainability** - Easy to update when code changes
3. **Documentation** - Tests serve as living documentation
4. **Confidence** - Safe refactoring and feature additions

## Test Structure

### File Organization

```
tests/
├── Feature/
│   └── Console/
│       ├── FindCommandTest.php           # ✅ Example
│       ├── InitCommandTest.php
│       ├── InstallCommandTest.php
│       ├── Local/
│       │   ├── StartCommandTest.php
│       │   ├── StopCommandTest.php
│       │   ├── StatusCommandTest.php
│       │   └── LogsCommandTest.php
│       └── Stack/
│           ├── InitCommandTest.php
│           ├── LaravelCommandTest.php
│           └── ManageCommandTest.php
└── Unit/
    ├── Services/
    │   ├── Stack/
    │   └── Project/
    └── HelperFunctionsTest.php
```

## Command Test Template

Use this template for all command tests:

```php
<?php

declare(strict_types=1);

/**
 * YourCommand Feature Tests
 * 
 * Brief description of what this command does.
 * 
 * @see \App\Commands\YourCommand
 */

use LaravelZero\Framework\Commands\Command;

describe('YourCommand', function (): void {
    
    beforeEach(function (): void {
        // Setup - runs before each test
    });
    
    afterEach(function (): void {
        // Cleanup - runs after each test
    });

    it('executes successfully', function (): void {
        $this->artisan('your:command')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays branded header', function (): void {
        $this->artisan('your:command --help')
            ->assertSuccessful()
            ->expectsOutputToContain('Expected Header Text');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(\App\Commands\YourCommand::class);
        
        expect($command)
            ->toHaveMethod('brandedHeader')
            ->toHaveMethod('success')
            ->toHaveMethod('failure');
    });
});

describe('YourCommand Integration', function (): void {
    // Integration tests
});

describe('YourCommand Error Handling', function (): void {
    // Error/edge case tests
});
```

## Test Categories

### 1. Basic Functionality Tests

Test core command behavior:

```php
it('executes successfully', function (): void {
    $this->artisan('command')
        ->assertSuccessful();
});

it('has correct signature', function (): void {
    $command = $this->app->make(\App\Commands\YourCommand::class);
    expect($command->getName())->toBe('your:command');
});

it('shows correct description', function (): void {
    $this->artisan('list')
        ->expectsOutputToContain('Your command description');
});
```

### 2. Output & Branding Tests

Test UI/UX consistency:

```php
it('displays branded header', function (): void {
    $this->artisan('command')
        ->expectsOutputToContain('Feature Name');
});

it('uses HasBrandedOutput methods', function (): void {
    $command = $this->app->make(\App\Commands\YourCommand::class);
    
    $traits = class_uses_recursive($command);
    expect($traits)->toContain(\App\Concerns\HasBrandedOutput::class);
});

it('shows success message', function (): void {
    $this->artisan('command')
        ->expectsOutputToContain('successfully');
});
```

### 3. Integration Tests

Test command interactions:

```php
it('calls other commands', function (): void {
    $this->artisan('command')
        ->assertSuccessful();
    
    // Verify side effects
});

it('integrates with services', function (): void {
    $mock = Mockery::mock(SomeService::class);
    $this->app->instance(SomeService::class, $mock);
    
    $this->artisan('command')
        ->assertSuccessful();
});
```

### 4. Argument & Option Tests

Test command inputs:

```php
it('accepts required argument', function (): void {
    $this->artisan('command project-name')
        ->assertSuccessful();
});

it('accepts optional argument', function (): void {
    $this->artisan('command')
        ->assertSuccessful();
});

it('respects --force flag', function (): void {
    $this->artisan('command --force')
        ->assertSuccessful();
});

it('handles --no-interaction mode', function (): void {
    $this->artisan('command --no-interaction')
        ->assertSuccessful();
});
```

### 5. Error Handling Tests

Test failure scenarios:

```php
it('fails when required file missing', function (): void {
    $this->artisan('command')
        ->assertFailed()
        ->expectsOutputToContain('not found');
});

it('handles invalid input gracefully', function (): void {
    $this->artisan('command invalid')
        ->assertFailed();
});

it('shows helpful error messages', function (): void {
    $this->artisan('command')
        ->assertFailed()
        ->expectsOutputToContain('Error:');
});
```

### 6. File Operation Tests

Test file creation/modification:

```php
it('creates required files', function (): void {
    $this->artisan('command')
        ->assertSuccessful();
    
    expect(file_exists('path/to/file'))->toBeTrue();
});

it('creates directories', function (): void {
    $this->artisan('command')
        ->assertSuccessful();
    
    expect(is_dir('path/to/dir'))->toBeTrue();
});

it('modifies existing files', function (): void {
    file_put_contents('file.txt', 'old');
    
    $this->artisan('command')
        ->assertSuccessful();
    
    expect(file_get_contents('file.txt'))->toBe('new');
});
```

## Pest Best Practices

### Use Descriptive Test Names

✅ **Good:**
```php
it('creates .tuti directory with correct structure')
it('fails when project already initialized')
it('reinitializes with --force flag')
```

❌ **Bad:**
```php
it('test1')
it('works')
it('checks stuff')
```

### Use `describe()` for Organization

```php
describe('InitCommand', function () {
    // Core functionality tests
});

describe('InitCommand Error Handling', function () {
    // Error scenarios
});

describe('InitCommand File Operations', function () {
    // File creation tests
});
```

### Chain Expectations

```php
expect($config)
    ->toBeArray()
    ->toHaveKey('project')
    ->toHaveKey('environment')
    ->not->toBeEmpty();
```

### Use Type Hints

```php
it('returns correct type', function (): void {
    // Test code
});

beforeEach(function (): void {
    // Setup
});
```

### Use Proper Cleanup

```php
beforeEach(function (): void {
    $this->testDir = sys_get_temp_dir() . '/test-' . uniqid();
    mkdir($this->testDir);
});

afterEach(function (): void {
    if (is_dir($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});
```

## Mocking Services

### Basic Mock

```php
it('uses service correctly', function (): void {
    $mock = Mockery::mock(StackInitializationService::class);
    $mock->shouldReceive('initialize')
        ->once()
        ->with('project-name', 'dev')
        ->andReturn(true);
    
    $this->app->instance(StackInitializationService::class, $mock);
    
    $this->artisan('command project-name')
        ->assertSuccessful();
});
```

### Partial Mock

```php
$mock = Mockery::mock(Service::class)->makePartial();
$mock->shouldReceive('specificMethod')
    ->andReturn('value');
```

## Common Assertions

### Command Assertions

```php
->assertSuccessful()              // Exit code 0
->assertFailed()                  // Exit code != 0
->assertExitCode(Command::SUCCESS)
->expectsOutputToContain('text')  // Output contains text
->expectsQuestion('Q?', 'answer') // Handles prompt
->expectsConfirmation('Q?', 'yes')
```

### Pest Expectations

```php
expect($value)->toBe($expected)
expect($value)->toEqual($expected)
expect($value)->toBeTrue()
expect($value)->toBeFalse()
expect($value)->toBeNull()
expect($value)->toBeEmpty()
expect($value)->toContain('item')
expect($value)->toHaveCount(5)
expect($value)->toHaveKey('key')
expect($value)->toBeInstanceOf(Class::class)
expect($value)->toBeArray()
expect($value)->toBeString()
expect($value)->toBeInt()
```

## Test Coverage Goals

### Target Coverage
- **Commands:** >80% line coverage
- **Services:** >90% line coverage
- **Helpers:** >95% line coverage

### What to Test
✅ Public methods
✅ Happy path
✅ Error scenarios
✅ Edge cases
✅ Integration points

### What NOT to Test
❌ Private methods (test through public API)
❌ Framework code
❌ Third-party packages
❌ Getters/setters (unless complex logic)

## Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Feature tests only
pest tests/Feature

# Specific file
pest tests/Feature/Console/FindCommandTest.php

# Specific test
pest --filter="executes successfully"

# With coverage
composer test:coverage

# Verbose output
pest --verbose

# Stop on failure
pest --stop-on-failure

# Parallel execution
pest --parallel

# Watch mode (requires pest-plugin-watch)
pest --watch
```

## Continuous Integration

Your tests should run on every:
- Git push
- Pull request
- Before merge
- Before deployment

Example GitHub Actions:
```yaml
- name: Run tests
  run: composer test:unit

- name: Run coverage
  run: composer test:coverage
```

## Examples from FindCommand

See `tests/Feature/Console/FindCommandTest.php` for a complete example showing:
- 21 comprehensive tests
- 7 describe blocks for organization
- Command discovery testing
- Integration testing
- Error handling
- Output verification
- Trait verification

## Quick Reference

### Test a Simple Command

```php
it('runs successfully', function (): void {
    $this->artisan('my:command')
        ->assertSuccessful()
        ->expectsOutputToContain('Success');
});
```

### Test with Arguments

```php
it('accepts arguments', function (): void {
    $this->artisan('my:command argument')
        ->assertSuccessful();
});
```

### Test with Options

```php
it('accepts options', function (): void {
    $this->artisan('my:command --option=value')
        ->assertSuccessful();
});
```

### Test File Creation

```php
it('creates file', function (): void {
    $this->artisan('my:command')
        ->assertSuccessful();
    
    expect(file_exists('file.txt'))->toBeTrue();
});
```

### Test Service Interaction

```php
it('calls service', function (): void {
    $mock = Mockery::mock(Service::class);
    $mock->shouldReceive('method')->once();
    
    $this->app->instance(Service::class, $mock);
    
    $this->artisan('my:command')
        ->assertSuccessful();
});
```

## Summary

This guide provides everything needed to write comprehensive, valuable tests for Tuti CLI commands. Follow these patterns for consistency and maintainability across the entire test suite.

**Key Takeaways:**
1. Use descriptive test names
2. Organize with `describe()` blocks
3. Test happy path AND errors
4. Use chainable expectations
5. Mock external dependencies
6. Clean up after tests
7. Aim for >80% coverage
8. Make tests serve as documentation
