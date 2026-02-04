---
name: laravel-zero-commands
description: Develop Laravel Zero console commands
globs:
  - app/Commands/**
  - config/commands.php
---

# Laravel Zero Commands Skill

## When to Use
- Creating new console commands
- Modifying existing commands

## Command Template

```php
declare(strict_types=1);

namespace App\Commands\Category;

use App\Services\MyService;
use LaravelZero\Framework\Commands\Command;

final class ActionCommand extends Command
{
    protected $signature = 'category:action 
        {argument? : Description}
        {--option=default : Description}
    ';

    protected $description = 'What this command does';

    public function __construct(
        private readonly MyService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->info('Starting...');
            $result = $this->service->execute();
            $this->info('Done!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
```

## File Location

```
app/Commands/
├── Local/           # local:start, local:stop
├── Stack/           # stack:laravel, stack:manage
└── Test/            # test:*
```

## Common Patterns

### Progress Bar
```php
$this->withProgressBar($items, function ($item) {
    $this->processItem($item);
});
```

### Interactive Input
```php
$name = $this->ask('Project name?', 'default');
$db = $this->choice('Database?', ['postgres', 'mysql'], 0);
$confirm = $this->confirm('Continue?', true);
```

### Table Output
```php
$this->table(['Name', 'Status'], [
    ['Laravel', 'Active'],
    ['WordPress', 'Pending'],
]);
```

## Testing

```php
test('command executes', function () {
    $this->artisan('category:action')
        ->assertExitCode(Command::SUCCESS);
});

test('command with mocked service', function () {
    $mock = Mockery::mock(MyService::class);
    $mock->shouldReceive('execute')->once()->andReturn(true);
    $this->app->instance(MyService::class, $mock);
    
    $this->artisan('category:action')
        ->assertExitCode(Command::SUCCESS);
});
```

## Checklist

- [ ] `declare(strict_types=1)`
- [ ] Class is `final`
- [ ] Constructor calls `parent::__construct()`
- [ ] Returns `Command::SUCCESS` or `Command::FAILURE`
- [ ] Has descriptive `$description`
- [ ] Includes tests
