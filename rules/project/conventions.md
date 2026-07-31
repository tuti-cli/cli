# Project Conventions

## Naming

| Type | Convention | Example |
|------|------------|---------|
| Class | PascalCase | `StackInitializationService` |
| Method | camelCase | `getStackPath()` |
| Variable | camelCase | `$stackName` |
| Constant | UPPER_SNAKE | `MAX_RETRIES` |
| Interface | PascalCase + Interface | `StackInstallerInterface` |
| Command signature | `category:action` | `stack:laravel`, `local:start` |

## Class Patterns

```php
// Service pattern
final readonly class MyService
{
    public function __construct(
        private SomeInterface $dependency,
    ) {}
}

// Command pattern — all commands use HasBrandedOutput trait
final class MyCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'category:action {argument?} {--option=default}';
    protected $description = 'What it does';

    public function __construct(private readonly MyService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->brandedHeader('Feature Name');
        // Return Command::SUCCESS or Command::FAILURE, never exit()
    }
}
```

## Console Output (HasBrandedOutput)

All commands use `HasBrandedOutput` from `App\Concerns\HasBrandedOutput`.
Key methods: `brandedHeader()`, `step()`, `success()`, `failure()`, `created()`,
`modified()`, `completed()`, `failed()`, `section()`, `keyValue()`, `tipBox()`, `warningBox()`.
Themes: `LaravelRed`, `Gray`, `Ocean`, `Vaporwave`, `Sunset` (from `App\Enums\Theme`).

## Dev-Only Commands

Exclude from production via `config/commands.php` `'remove'` array with `!app()->isLocal()` check.

## Common Tasks

| Task | Files to modify |
|------|----------------|
| Add CLI command | `app/Commands/{Category}/Command.php` |
| Add dev-only command | Command + `config/commands.php` `'remove'` array |
| Add service class | `app/Services/{Domain}/Service.php` + bind in `AppServiceProvider` |
| Add framework stack | `stubs/stacks/{name}/` + Installer + Command + `registry.json` + `StackServiceProvider` |
| Add service stub | `stubs/stacks/{stack}/services/{category}/{name}.stub` + `registry.json` |

## Forbidden Patterns

- Never use `exit()` in commands
- Never use property injection or setters
- Never use PHPDoc for type-hinted code
- Never interpolate variables into shell command strings
