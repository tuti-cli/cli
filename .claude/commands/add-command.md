Create a new Laravel Zero CLI command: $ARGUMENTS

## Context -- read these files first

1. Pick a reference command by category:
   - Infrastructure: `app/Commands/Infrastructure/StartCommand.php`
   - Local: `app/Commands/Local/StartCommand.php` or `app/Commands/Local/RebuildCommand.php`
   - Stack: `app/Commands/Stack/LaravelCommand.php` or `app/Commands/Stack/WordPressCommand.php`
   - Root: `app/Commands/InitCommand.php` or `app/Commands/DoctorCommand.php`
2. `app/Concerns/HasBrandedOutput.php` - UI trait (all commands must use this)
3. `app/Concerns/BuildsProjectUrls.php` - URL building trait (if command shows URLs)
4. `app/Providers/AppServiceProvider.php` - service bindings

## Command categories and signatures

Existing categories:
- `infra:*` (start, stop, restart, status) - infrastructure management
- `local:*` (start, stop, logs, rebuild, status) - project containers
- `stack:*` (laravel, wordpress, init, manage) - stack installation
- `wp:*` (setup) - WordPress-specific
- `debug` (status, enable, disable, logs, clear) - debug system
- `doctor` - system health
- `env:check` - environment validation
- Root: `init`, `install`, `find`

## Template

```php
<?php

declare(strict_types=1);

namespace App\Commands\{Category};

use App\Concerns\HasBrandedOutput;
use LaravelZero\Framework\Commands\Command;

final class {Name}Command extends Command
{
    use HasBrandedOutput;

    protected $signature = '{category}:{action}
        {argument? : Description}
        {--option=default : Description}
    ';

    protected $description = 'What this command does';

    public function __construct(
        private readonly SomeService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->brandedHeader('{Feature Name}');

        try {
            $this->step(1, 2, 'Doing something');
            $this->service->execute();
            $this->success('Done');

            $this->completed('Operation finished!', [
                'next step 1',
                'next step 2',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
```

## File placement

`app/Commands/{Category}/{Name}Command.php`

## Checklist

- [ ] `declare(strict_types=1)`
- [ ] Class is `final`
- [ ] `use HasBrandedOutput` trait
- [ ] Constructor calls `parent::__construct()`
- [ ] Returns `Command::SUCCESS` or `Command::FAILURE`
- [ ] Has descriptive `$description`
- [ ] Debug logging via `tuti_debug()` for important operations
- [ ] Tests in `tests/Feature/Console/{Category}/{Name}CommandTest.php`
