# Test Patterns

Complete code templates for all test types in Tuti CLI.

## Table of Contents

1. [Command Test Template](#command-test-template)
2. [Infrastructure Command Template](#infrastructure-command-template)
3. [Local Command Template](#local-command-template)
4. [Service Unit Test Template](#service-unit-test-template)
5. [Common Test Snippets](#common-test-snippets)

---

## Command Test Template

Basic template for any Laravel Zero command:

```php
<?php

declare(strict_types=1);

/**
 * YourCommand Feature Tests
 *
 * Tests the your:command functionality.
 *
 * @see \App\Commands\Category\YourCommand
 */

use LaravelZero\Framework\Commands\Command;

describe('YourCommand', function (): void {

    beforeEach(function (): void {
        // Setup
    });

    afterEach(function (): void {
        // Cleanup
    });

    // ─── Registration ────────────────────────────────────────────────────

    it('is registered in the application', function (): void {
        $command = $this->app->make(\App\Commands\Category\YourCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(\App\Commands\Category\YourCommand::class);

        expect($command->getName())->toBe('your:command');
    });

    it('has correct description', function (): void {
        $command = $this->app->make(\App\Commands\Category\YourCommand::class);

        expect($command->getDescription())->toBe('Your command description');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(\App\Commands\Category\YourCommand::class);
        $traits = class_uses_recursive($command);

        expect($traits)->toContain(\App\Concerns\HasBrandedOutput::class);
    });

    // ─── Happy Path ──────────────────────────────────────────────────────

    it('executes successfully', function (): void {
        $this->artisan('your:command')
            ->assertExitCode(Command::SUCCESS);
    });

    it('displays success message', function (): void {
        $this->artisan('your:command')
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Success');
    });

    // ─── Arguments ───────────────────────────────────────────────────────

    it('accepts required argument', function (): void {
        $this->artisan('your:command value')
            ->assertExitCode(Command::SUCCESS);
    });

    it('fails without required argument', function (): void {
        $this->artisan('your:command')
            ->assertExitCode(Command::FAILURE);
    });

    // ─── Options ─────────────────────────────────────────────────────────

    it('respects --force flag', function (): void {
        $this->artisan('your:command --force')
            ->assertExitCode(Command::SUCCESS);
    });

    // ─── Error Handling ──────────────────────────────────────────────────

    it('handles invalid input', function (): void {
        $this->artisan('your:command invalid')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Error');
    });
});
```

---

## Infrastructure Command Template

For `infra:*` commands using `InfrastructureManagerInterface`:

```php
<?php

declare(strict_types=1);

/**
 * InfraActionCommand Feature Tests
 *
 * @see \App\Commands\Infrastructure\ActionCommand
 */

use App\Commands\Infrastructure\ActionCommand;
use App\Contracts\InfrastructureManagerInterface;
use LaravelZero\Framework\Commands\Command;
use Tests\Mocks\FakeInfrastructureManager;

describe('InfraActionCommand', function (): void {

    beforeEach(function (): void {
        $this->fakeInfra = new FakeInfrastructureManager();
        $this->app->instance(InfrastructureManagerInterface::class, $this->fakeInfra);
    });

    // ─── Registration ────────────────────────────────────────────────────

    it('is registered in the application', function (): void {
        $command = $this->app->make(ActionCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(ActionCommand::class);

        expect($command->getName())->toBe('infra:action');
    });

    it('uses HasBrandedOutput trait', function (): void {
        $command = $this->app->make(ActionCommand::class);
        $traits = class_uses_recursive($command);

        expect($traits)->toContain(\App\Concerns\HasBrandedOutput::class);
    });

    // ─── Not Installed ───────────────────────────────────────────────────

    it('fails when infrastructure is not installed', function (): void {
        $this->fakeInfra->setInstalled(false);

        $this->artisan('infra:action')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('not installed');
    });

    // ─── Success Path ────────────────────────────────────────────────────

    it('succeeds when infrastructure is running', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setRunning(true);

        $this->artisan('infra:action')
            ->assertExitCode(Command::SUCCESS);
    });

    // ─── Error Handling ──────────────────────────────────────────────────

    it('returns failure on error', function (): void {
        $this->fakeInfra->setInstalled(true);
        $this->fakeInfra->setActionResult(false);

        $this->artisan('infra:action')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('Failed');
    });
});
```

---

## Local Command Template

For `local:*` commands that need project environment:

```php
<?php

declare(strict_types=1);

/**
 * LocalActionCommand Feature Tests
 *
 * @see \App\Commands\Local\ActionCommand
 */

use App\Contracts\OrchestratorInterface;
use App\Services\Docker\DockerService;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use Tests\Feature\Concerns\CreatesLocalProjectEnvironment;
use Tests\Mocks\FakeDockerOrchestrator;

describe('LocalActionCommand', function (): void {
    use CreatesLocalProjectEnvironment;

    beforeEach(function (): void {
        $this->setupLocalProject();

        // Mock Docker
        Process::fake([
            '*docker*' => Process::result(output: 'OK'),
        ]);

        $this->fakeOrchestrator = new FakeDockerOrchestrator();
        $this->app->instance(OrchestratorInterface::class, $this->fakeOrchestrator);
    });

    afterEach(function (): void {
        $this->cleanupLocalProject();
    });

    // ─── Registration ────────────────────────────────────────────────────

    it('is registered in the application', function (): void {
        $command = $this->app->make(\App\Commands\Local\ActionCommand::class);

        expect($command)->toBeInstanceOf(Command::class);
    });

    it('has correct signature', function (): void {
        $command = $this->app->make(\App\Commands\Local\ActionCommand::class);

        expect($command->getName())->toBe('local:action');
    });

    // ─── Without Project ─────────────────────────────────────────────────

    it('fails when not in a project directory', function (): void {
        $this->removeProjectConfig();

        $this->artisan('local:action')
            ->assertExitCode(Command::FAILURE)
            ->expectsOutputToContain('not a valid');
    });

    // ─── Happy Path ──────────────────────────────────────────────────────

    it('executes successfully', function (): void {
        $this->artisan('local:action')
            ->assertExitCode(Command::SUCCESS);
    });

    it('calls orchestrator method', function (): void {
        $this->artisan('local:action')
            ->assertExitCode(Command::SUCCESS);

        expect($this->fakeOrchestrator->actionCalled)->toBeTrue();
    });
});

// Helper function for Process::assertRan()
function localCommandStr($command): string
{
    return str_replace("'", '', $command);
}
```

---

## Service Unit Test Template

For testing service classes:

```php
<?php

declare(strict_types=1);

/**
 * YourService Unit Tests
 *
 * @see \App\Services\Domain\YourService
 */

describe('YourService', function (): void {

    beforeEach(function (): void {
        $this->service = app(\App\Services\Domain\YourService::class);
    });

    // ─── Constructor ─────────────────────────────────────────────────────

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(\App\Services\Domain\YourService::class);
    });

    // ─── Methods ─────────────────────────────────────────────────────────

    it('returns expected value from method', function (): void {
        $result = $this->service->someMethod('input');

        expect($result)->toBe('expected output');
    });

    it('returns correct type', function (): void {
        $result = $this->service->someMethod('input');

        expect($result)->toBeArray();
    });

    it('handles empty input', function (): void {
        $result = $this->service->someMethod('');

        expect($result)->toBeEmpty();
    });

    it('throws exception for invalid input', function (): void {
        expect(fn () => $this->service->someMethod('invalid'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('YourService Edge Cases', function (): void {

    it('handles null gracefully', function (): void {
        $service = app(\App\Services\Domain\YourService::class);

        expect(fn () => $service->someMethod(null))
            ->toThrow(TypeError::class);
    });

    it('handles very long strings', function (): void {
        $service = app(\App\Services\Domain\YourService::class);
        $longString = str_repeat('a', 10000);

        $result = $service->someMethod($longString);

        expect($result)->toBeString();
    });
});
```

---

## Common Test Snippets

### Test File Creation
```php
it('creates required files', function (): void {
    $this->artisan('command')
        ->assertSuccessful();

    expect(file_exists('path/to/file'))->toBeTrue();
});

it('creates file with correct content', function (): void {
    $this->artisan('command')
        ->assertSuccessful();

    $content = file_get_contents('path/to/file');
    expect($content)
        ->toContain('expected content')
        ->not->toContain('placeholder');
});
```

### Test Directory Structure
```php
it('creates directory structure', function (): void {
    $this->artisan('command')
        ->assertSuccessful();

    expect(is_dir('path/to/dir'))->toBeTrue();
    expect(is_dir('path/to/dir/subdir'))->toBeTrue();
});
```

### Test Interactive Prompts
```php
it('handles interactive confirmation', function (): void {
    $this->artisan('command')
        ->expectsConfirmation('Are you sure?', 'yes')
        ->assertSuccessful();
});

it('handles interactive question', function (): void {
    $this->artisan('command')
        ->expectsQuestion('Enter name:', 'my-project')
        ->assertSuccessful();
});
```

### Test Multiple Output Lines
```php
it('displays multiple output messages', function (): void {
    $this->artisan('command')
        ->assertSuccessful()
        ->expectsOutputToContain('Step 1')
        ->expectsOutputToContain('Step 2')
        ->expectsOutputToContain('Completed');
});
```

### Test Exit Codes
```php
it('returns success exit code', function (): void {
    $this->artisan('command')
        ->assertExitCode(Command::SUCCESS);
});

it('returns failure exit code on error', function (): void {
    $this->artisan('command --invalid')
        ->assertExitCode(Command::FAILURE);
});

it('returns invalid exit code for bad input', function (): void {
    $this->artisan('command')
        ->assertExitCode(Command::INVALID);
});
```

### Test Trait Methods Exist
```php
it('has branded output methods available', function (): void {
    $command = $this->app->make(\App\Commands\YourCommand::class);

    expect(method_exists($command, 'brandedHeader'))->toBeTrue();
    expect(method_exists($command, 'success'))->toBeTrue();
    expect(method_exists($command, 'failure'))->toBeTrue();
    expect(method_exists($command, 'note'))->toBeTrue();
    expect(method_exists($command, 'warning'))->toBeTrue();
});
```

### Test JSON Output
```php
it('outputs valid JSON', function (): void {
    $this->artisan('command --json')
        ->assertSuccessful();

    $output = $this->artisan('command --json')->output();
    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray()
        ->toHaveKey('status');
});
```

### Test Exception Handling
```php
it('catches and handles exceptions gracefully', function (): void {
    // Setup mock to throw
    $mock = Mockery::mock(Service::class);
    $mock->shouldReceive('method')
        ->once()
        ->andThrow(new RuntimeException('Error'));
    $this->app->instance(Service::class, $mock);

    $this->artisan('command')
        ->assertExitCode(Command::FAILURE)
        ->expectsOutputToContain('Error');
});
```

### Pest Expectations Chaining
```php
it('validates complex data structure', function (): void {
    $result = $this->service->getData();

    expect($result)
        ->toBeArray()
        ->toHaveKey('items')
        ->and($result['items'])
        ->toHaveCount(3)
        ->each->toBeArray();
});
```
