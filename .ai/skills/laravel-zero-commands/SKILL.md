---
name: laravel-zero-commands
description: Develop Laravel Zero commands with best practices.
---

# Laravel Zero Commands

## When to Use
Creating or modifying console commands.

## Command Template

```php
declare(strict_types=1);

namespace App\Commands\SomeCategory;

use App\Services\SomeService;
use LaravelZero\Framework\Commands\Command;

final class MyCommand extends Command
{
    protected $signature = 'category:name 
        {argument : Description}
        {--option=default : Description}
    ';

    protected $description = 'Command description';

    public function __construct(
        private readonly SomeService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->info('Starting...');
            
            $result = $this->service->doSomething();
            
            $this->info('Completed successfully!');
            
            return Command::SUCCESS;
        } catch (SomeException $e) {
            $this->error("Failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
```

Common Patterns

### Progress Bar

``` php
$items = collect(range(1, 100));
$bar = $this->output->createProgressBar($items->count());
$bar->start();

$items->each(function ($item) use ($bar) {
    // Process item
    $bar->advance();
});

$bar->finish();
$this->newLine();
```

Table Output
```php
$data = [
    ['Name' => 'John', 'Age' => 30],
    ['Name' => 'Jane', 'Age' => 25],
];

$this->table(array_keys($data[0]), $data);
```
Asking Questions
```php
$name = $this->ask('What is your name?');
$confirm = $this->confirm('Continue?');
$choice = $this->choice('Select one', ['Option 1', 'Option 2']);

Best Practices

- Use `declare(strict_types=1)`
- Make classes `final`
- Use constructor injection
- Return `Command::SUCCESS` or `Command::FAILURE`
- Handle exceptions properly
- Provide clear user feedback
- Write tests```
