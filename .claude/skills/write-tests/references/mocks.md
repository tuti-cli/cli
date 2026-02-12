# Mocking Strategies

Comprehensive guide to mocking in Tuti CLI tests.

## Table of Contents

1. [Process::fake() for Docker](#processfake-for-docker)
2. [Service Mocks](#service-mocks)
3. [Interface Fakes](#interface-fakes)
4. [Mockery Patterns](#mockery-patterns)
5. [Test Helpers](#test-helpers)

---

## Process::fake() for Docker

All Docker commands must be mocked using `Process::fake()`.

### Basic Setup

```php
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    Process::fake([
        '*docker*' => Process::result(output: 'OK'),
    ]);
});
```

### Matching Patterns

**CRITICAL**: Use wildcards (`*`) because Laravel escapes arguments with single quotes.

```php
// ✅ CORRECT - Uses wildcards
Process::fake([
    '*docker*compose*' => Process::result(output: 'OK'),
    '*docker*ps*' => Process::result(output: '[]'),
]);

// ❌ WRONG - Won't match because of quote escaping
Process::fake([
    'docker compose up' => Process::result(output: 'OK'),
]);
```

### Asserting Commands Were Run

```php
it('runs docker compose up', function (): void {
    Process::fake(['*docker*compose*' => Process::result(output: 'OK')]);

    $this->artisan('local:start')->assertSuccessful();

    Process::assertRan(function ($command) {
        $cmd = dockerCommandStr($command);

        return str_contains($cmd, 'docker')
            && str_contains($cmd, 'compose')
            && str_contains($cmd, 'up');
    });
});

// Helper to strip quotes - MUST use unique name per file
function dockerCommandStr($command): string
{
    return str_replace("'", '', $command);
}
```

### Multiple Command Responses

```php
Process::fake([
    '*docker*compose*up*' => Process::result(output: 'Started'),
    '*docker*compose*down*' => Process::result(output: 'Stopped'),
    '*docker*compose*ps*' => Process::result(output: json_encode([
        ['Name' => 'app', 'State' => 'running'],
    ])),
]);
```

### Simulating Failures

```php
Process::fake([
    '*docker*' => Process::result(
        output: '',
        errorOutput: 'Error: daemon not running',
        exitCode: 1
    ),
]);
```

### Checking Command Count

```php
it('calls docker exactly once', function (): void {
    Process::fake(['*docker*' => Process::result(output: 'OK')]);

    $this->artisan('infra:start')->assertSuccessful();

    Process::assertRanTimes(1);
});
```

---

## Service Mocks

### Basic Service Mock

```php
use Mockery;
use App\Services\Stack\StackInitializationService;

beforeEach(function (): void {
    $mock = Mockery::mock(StackInitializationService::class);
    $mock->shouldReceive('initialize')
        ->once()
        ->with('project-name', 'dev')
        ->andReturn(true);

    $this->app->instance(StackInitializationService::class, $mock);
});
```

### Mock with Multiple Methods

```php
$mock = Mockery::mock(DockerService::class);
$mock->shouldReceive('isRunning')
    ->andReturn(true);
$mock->shouldReceive('start')
    ->once();
$mock->shouldReceive('getContainers')
    ->andReturn(['app', 'nginx']);

$this->app->instance(DockerService::class, $mock);
```

### Mock Return Values

```php
// Return specific value
$mock->shouldReceive('method')->andReturn('value');

// Return sequence of values
$mock->shouldReceive('method')
    ->andReturn('first', 'second', 'third');

// Return based on arguments
$mock->shouldReceive('method')
    ->with('valid')
    ->andReturn(true);
$mock->shouldReceive('method')
    ->with('invalid')
    ->andReturn(false);
```

---

## Interface Fakes

Create fake implementations for complex interfaces.

### FakeInfrastructureManager Pattern

```php
<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Contracts\InfrastructureManagerInterface;

final class FakeInfrastructureManager implements InfrastructureManagerInterface
{
    // Tracking properties
    public bool $installCalled = false;
    public bool $startCalled = false;
    public bool $stopCalled = false;

    // Configurable responses
    public bool $isInstalledResult = false;
    public bool $isRunningResult = false;
    public bool $startResult = true;

    public function isInstalled(): bool
    {
        return $this->isInstalledResult;
    }

    public function isRunning(): bool
    {
        return $this->isRunningResult;
    }

    public function start(): void
    {
        $this->startCalled = true;

        if (! $this->startResult) {
            throw new RuntimeException('Failed to start');
        }

        $this->isRunningResult = true;
    }

    // ... other interface methods

    // Fluent setters for test setup
    public function setInstalled(bool $installed = true): void
    {
        $this->isInstalledResult = $installed;
    }

    public function setRunning(bool $running = true): void
    {
        $this->isRunningResult = $running;
    }

    public function reset(): void
    {
        $this->installCalled = false;
        $this->startCalled = false;
        $this->stopCalled = false;
        $this->isInstalledResult = false;
        $this->isRunningResult = false;
    }
}
```

### Using Fakes in Tests

```php
use App\Contracts\InfrastructureManagerInterface;
use Tests\Mocks\FakeInfrastructureManager;

beforeEach(function (): void {
    $this->fake = new FakeInfrastructureManager();
    $this->app->instance(InfrastructureManagerInterface::class, $this->fake);
});

it('starts infrastructure', function (): void {
    $this->fake->setInstalled(true);

    $this->artisan('infra:start')->assertSuccessful();

    expect($this->fake->startCalled)->toBeTrue();
});
```

---

## Mockery Patterns

### Partial Mocks

```php
$mock = Mockery::mock(Service::class)->makePartial();
$mock->shouldReceive('externalApiCall')
    ->andReturn(['status' => 'ok']);

// Other methods use real implementation
```

### Spy Pattern

```php
$spy = Mockery::spy(SomeService::class);
$this->app->instance(SomeService::class, $spy);

$this->artisan('command')->assertSuccessful();

// Verify method was called
$spy->shouldHaveReceived('method')
    ->once()
    ->with('expected-arg');
```

### Throwing Exceptions

```php
$mock->shouldReceive('method')
    ->andThrow(new RuntimeException('Something went wrong'));
```

### Callback Validation

```php
$mock->shouldReceive('process')
    ->once()
    ->withArgs(function ($data) {
        return $data['required_key'] === 'expected_value';
    });
```

---

## Test Helpers

### CreatesLocalProjectEnvironment

Sets up a complete project structure:

```php
use Tests\Feature\Concerns\CreatesLocalProjectEnvironment;

describe('LocalCommand', function (): void {
    use CreatesLocalProjectEnvironment;

    beforeEach(function (): void {
        $this->setupLocalProject();
    });

    afterEach(function (): void {
        $this->cleanupLocalProject();
    });

    it('has project config', function (): void {
        expect(file_exists($this->testProjectDir . '/.tuti/config.json'))
            ->toBeTrue();
    });
});
```

Available methods:
- `setupLocalProject()` - Creates temp dir with .tuti structure
- `cleanupLocalProject()` - Removes temp dir
- `createDockerCompose($content)` - Create custom compose file
- `createProjectConfig($config)` - Create custom config
- `removeProjectConfig()` - Remove config to test error path
- `removeDockerCompose()` - Remove compose to test error path

### CreatesTestStackEnvironment

For stack-related tests:

```php
use Tests\Feature\Concerns\CreatesTestStackEnvironment;

describe('StackCommand', function (): void {
    use CreatesTestStackEnvironment;

    beforeEach(function (): void {
        $this->setupStackEnvironment('laravel');
    });
});
```

### Custom Temp Directory

```php
beforeEach(function (): void {
    $this->testDir = createTestDirectory();
    chdir($this->testDir);
});

afterEach(function (): void {
    chdir(base_path()); // Return to project root
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

---

## Command String Helpers

Each test file needs a uniquely named helper function:

```php
// InfraStartCommandTest.php
function infraStartCommandStr($command): string
{
    return str_replace("'", '', $command);
}

// LocalStatusCommandTest.php
function localStatusCommandStr($command): string
{
    return str_replace("'", '', $command);
}

// DockerServiceTest.php
function dockerServiceCommandStr($command): string
{
    return str_replace("'", '', $command);
}
```

This prevents function name collisions when Pest loads multiple test files.
