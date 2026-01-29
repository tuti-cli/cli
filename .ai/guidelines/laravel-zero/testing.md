# Testing Laravel Zero Commands with Pest

## Basic Command Test

```php
test('command executes successfully', function () {
    $this->artisan('command:name')
        ->assertExitCode(Command::SUCCESS);
});

With Arguments and Options

```php
test('command with arguments', function () {
    $this->artisan('command:name', ['arg1' => 'value'])
        ->expectsOutput('Expected output')
        ->assertExitCode(Command::SUCCESS);
});

Testing Output

```php
test('command displays expected output', function () {
    $this->artisan('command:name')
        ->expectsOutput('Hello World')
        ->expectsOutputContains('partial match')
        ->doesntExpectOutput('unexpected text');
});

Mocking Services

```php
test('command uses service correctly', function () {
    $service = Mockery::mock(SomeService::class);
    $service->shouldReceive('doSomething')->once()->andReturn('result');
    
    $this->app->instance(SomeService::class, $service);
    
    $this->artisan('command:name')
        ->assertExitCode(Command::SUCCESS);
});


Testing Interactions

```php
test('command asks for input', function () {
    $this->artisan('command:name')
        ->expectsQuestion('What is your name?', 'John')
        ->expectsOutput('Hello, John')
        ->assertExitCode(Command::SUCCESS);
});

test('command confirms action', function () {
    $this->artisan('command:name')
        ->expectsConfirmation('Continue?', 'yes')
        ->assertExitCode(Command::SUCCESS);
});
