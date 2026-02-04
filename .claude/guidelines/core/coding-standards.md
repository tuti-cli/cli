# Coding Standards

## PHP Conventions

### Required
```php
declare(strict_types=1);
```

### Class Design
- Classes: `final` by default
- Properties: `private readonly`
- Constructor: injection only
- Methods: `public` only when needed

### Example
```php
declare(strict_types=1);

namespace App\Services\Stack;

final class MyService
{
    public function __construct(
        private readonly SomeInterface $dependency,
    ) {}

    public function execute(string $input): Result
    {
        return $this->process($input);
    }

    private function process(string $input): Result
    {
        // Implementation
    }
}
```

## Formatting

- PSR-12 (Laravel Pint)
- Trailing commas in multiline arrays
- Return early, avoid else/elseif
- Type all properties and methods
- No PHPDoc for type-hinted code

## Naming

| Type | Convention | Example |
|------|------------|---------|
| Class | PascalCase | `StackInitializationService` |
| Method | camelCase | `getStackPath()` |
| Variable | camelCase | `$stackName` |
| Constant | UPPER_SNAKE | `MAX_RETRIES` |
| Interface | PascalCase + Interface | `StackInstallerInterface` |

## Commands

```php
protected $signature = 'category:action 
    {argument : Description}
    {--option=default : Description}
';

public function handle(): int
{
    // Return Command::SUCCESS or Command::FAILURE
}
```

## Error Handling

```php
try {
    $result = $this->service->execute();
} catch (SpecificException $e) {
    $this->error("Failed: {$e->getMessage()}");
    return Command::FAILURE;
}
```

## Tools

- **Pint**: `composer pint`
- **PHPStan**: `composer phpstan`
- **Rector**: `composer rector`
- **Pest**: `composer test`
