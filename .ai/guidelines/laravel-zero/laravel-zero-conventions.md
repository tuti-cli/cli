# Laravel Zero Conventions

Tuti CLI is built with Laravel Zero.

## Command Development

### Command Signature
```php
protected $signature = 'command:name 
    {argument : Argument description}
    {--option=default : Option description}
    {--flag : Boolean flag}
';


Input/Output


// Input
$name = $this->ask('What is your name?');
$confirm = $this->confirm('Continue?');
$choice = $this->choice('Select', ['a', 'b', 'c']);

// Output
$this->info('Success message');
$this->warn('Warning message');
$this->error('Error message');
$this->table(['Column 1', 'Column 2'], [['A', 'B'], ['C', 'D']]);

// Progress bar
$bar = $this->output->createProgressBar(100);
$bar->start();
foreach ($items as $item) {
    // Process item
    $bar->advance();
}
$bar->finish();


Dependency Injection
```php
public function __construct(
    private readonly SomeService $service,
) {
    parent::__construct();
}


Error Handling
```php
try {
    $result = $this->service->doSomething();
} catch (SpecificException $e) {
    $this->error("Operation failed: {$e->getMessage()}");
    return Command::FAILURE;
}


Service Providers

```php
final class SomeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SomeService::class);
    }

    public function boot(): void
    {
        // Bootstrap logic
    }
}


Testing with Pest

```php
test('command executes successfully', function () {
    $this->artisan('command:name', ['argument' => 'value'])
        ->assertExitCode(Command::SUCCESS)
        ->expectsOutput('Expected output');
});


Configuration

```php
// Get config
$value = config('some.key');

// Set config
config(['some.key' => 'value']);


Logging

```php
use Illuminate\Support\Facades\Log;

Log::info('Message', ['context' => 'data']);
Log::warning('Warning message');
Log::error('Error message', ['exception' => $e->getMessage()]);


Best Practices

- Return `Command::SUCCESS` or `Command::FAILURE` from `handle()`
- Use clear, user-friendly messages
- Validate input before processing
- Write tests for all commands
- Use dependency injection, not static calls


### Step 4: Create Laravel Zero Guidelines

Create `.ai/guidelines/laravel-zero/commands.md`:

```markdown
# Laravel Zero Command Development

## Command Structure

```php
declare(strict_types=1);

namespace App\Commands;

use App\Services\SomeService;
use LaravelZero\Framework\Commands\Command;

final class MyCommand extends Command
{
    protected $signature = 'my:command 
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
        // Command logic
        return Command::SUCCESS;
    }
}


Command Registration

Commands are registered in `config/app.php` or via Service Providers.

## Available Methods

### Input Methods
- `ask($question, $default = null)`
- `anticipate($question, $choices, $default = null)`
- `choice($question, $choices, $default = null, $multiple = false)`
- `confirm($question, $default = false)`
- `secret($question)`

### Output Methods
- `info($string)`
- `line($string)`
- `comment($string)`
- `question($string)`
- `error($string)`
- `warn($string)`
- `table($headers, $rows)`
- `newLine($count = 1)`

### Progress Bar
$bar = $this->output->createProgressBar($count);
$bar->start();
// Process items
$bar->finish();
$this->newLine();
