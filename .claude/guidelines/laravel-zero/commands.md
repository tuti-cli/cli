# Laravel Zero Commands

## Template

```php
declare(strict_types=1);

namespace App\Commands\Category;

use App\Services\MyService;
use LaravelZero\Framework\Commands\Command;

final class ActionCommand extends Command
{
    protected $signature = 'category:action 
        {argument? : Optional argument}
        {--option=default : Option with default}
        {--flag : Boolean flag}
    ';

    protected $description = 'What the command does';

    public function __construct(
        private readonly MyService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $argument = $this->argument('argument');
        $option = $this->option('option');
        
        try {
            $this->service->execute($argument, $option);
            $this->info('Done!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
```

## Signature Syntax

```php
// Required argument
{name : Description}

// Optional argument  
{name? : Description}

// Optional with default
{name=default : Description}

// Option with value
{--option=default : Description}

// Boolean flag
{--flag : Description}

// Shortcut
{--Q|queue : Description}
```

## Common Patterns

### Interactive Mode
```php
if (!$this->option('no-interaction')) {
    $name = $this->ask('Project name?', 'my-project');
    $confirm = $this->confirm('Continue?', true);
}
```

### Table Output
```php
$this->table(
    ['Name', 'Status'],
    [
        ['Laravel', 'Active'],
        ['WordPress', 'Pending'],
    ]
);
```

### Progress
```php
$this->withProgressBar($items, function ($item) {
    // Process each item
});
```

## Exit Codes

| Constant | Value | When to Use |
|----------|-------|-------------|
| `Command::SUCCESS` | 0 | Operation completed |
| `Command::FAILURE` | 1 | Operation failed |
| `Command::INVALID` | 2 | Invalid input |

## Useful Services

### Filesystem
```php
use Illuminate\Filesystem\Filesystem;

public function __construct(
    private readonly Filesystem $files,
) {}
```

### Process
```php
use Illuminate\Support\Facades\Process;

$result = Process::run('docker-compose up -d');
if (!$result->successful()) {
    throw new RuntimeException($result->errorOutput());
}
```

### HTTP
```php
use Illuminate\Support\Facades\Http;

$response = Http::get('https://api.example.com');
$data = $response->json();
```
