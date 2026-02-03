# Testing with Pest

## Command Tests

```php
use LaravelZero\Framework\Commands\Command;

test('command succeeds', function () {
    $this->artisan('stack:laravel', ['project-name' => 'test'])
        ->assertExitCode(Command::SUCCESS);
});

test('command output', function () {
    $this->artisan('local:status')
        ->expectsOutput('Environment running')
        ->assertExitCode(Command::SUCCESS);
});

test('command interaction', function () {
    $this->artisan('stack:laravel')
        ->expectsQuestion('Project name?', 'my-app')
        ->expectsConfirmation('Include Redis?', 'yes')
        ->assertExitCode(Command::SUCCESS);
});
```

## Mocking Services

```php
use App\Services\Stack\StackInitializationService;

test('command uses service', function () {
    $mock = Mockery::mock(StackInitializationService::class);
    $mock->shouldReceive('initialize')
        ->once()
        ->with(['stack' => 'laravel'])
        ->andReturn(true);
    
    $this->app->instance(StackInitializationService::class, $mock);
    
    $this->artisan('stack:laravel')
        ->assertExitCode(Command::SUCCESS);
});
```

## Unit Tests

```php
use App\Services\Stack\StackStubLoaderService;

test('stub loader returns services', function () {
    $loader = app(StackStubLoaderService::class);
    
    $services = $loader->getAvailableServices();
    
    expect($services)->toHaveKey('databases.postgres');
});
```

## Assertions

```php
expect($result)->toBe('value');
expect($array)->toContain('item');
expect($object)->toBeInstanceOf(MyClass::class);
expect($array)->toHaveCount(3);
expect($string)->toContain('substring');
```

## Run Tests

```bash
composer test           # All tests
composer test:unit      # Unit only  
composer test:feature   # Feature only
```
