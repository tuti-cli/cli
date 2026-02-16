---
name: php-zero-patterns
description: Laravel Zero + PHP 8.4 coding patterns for Tuti CLI. Use when creating commands, services, or any PHP code. Includes strict typing, readonly classes, constructor injection, error handling, and PSR-12 formatting.
---

# PHP Zero Patterns

Coding patterns and conventions for Tuti CLI built on Laravel Zero.

## Quick Reference

### File Header (Required)
```php
<?php

declare(strict_types=1);

namespace App\[Domain];
```

### Service Pattern
```php
final readonly class MyService
{
    public function __construct(
        private SomeInterface $dependency,
    ) {}
    
    public function doSomething(): bool
    {
        // Implementation
        return true;
    }
}
```

### Command Pattern
```php
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
        
        // Implementation
        
        return Command::SUCCESS;
    }
}
```

## Core Principles

### 1. Strict Types
- `declare(strict_types=1)` in EVERY file
- No exceptions, no shortcuts

### 2. Final Classes
- All classes `final`
- Prefer composition over inheritance
- Services use `final readonly`

### 3. Constructor Injection Only
```php
// ✅ CORRECT
public function __construct(
    private SomeService $service,
    private AnotherService $another,
) {}

// ❌ WRONG - No property injection
#[Inject]
private SomeService $service;

// ❌ WRONG - No setters
public function setService(SomeService $service): void
```

### 4. Explicit Types Everywhere
```php
// ✅ CORRECT
public function process(string $input): array
{
    return [];
}

// ❌ WRONG
public function process($input)
{
    return [];
}
```

### 5. No PHPDoc for Type-Hinted Code
```php
// ✅ CORRECT
public function getName(): string
{
    return $this->name;
}

// ❌ WRONG - Redundant PHPDoc
/**
 * Get the name.
 * 
 * @return string
 */
public function getName(): string
{
    return $this->name;
}
```

## Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Class | PascalCase | `StackInitializationService` |
| Method | camelCase | `getStackPath()` |
| Variable | camelCase | `$stackName` |
| Constant | UPPER_SNAKE | `MAX_RETRIES` |
| Interface | PascalCase | `StackInstallerInterface` |
| Command | `category:action` | `stack:laravel` |

## Error Handling

### Commands: Return Exit Codes
```php
public function handle(): int
{
    if (!$this->service->isReady()) {
        $this->error('Service not ready');
        return Command::FAILURE;
    }
    
    return Command::SUCCESS;
}
```

### Services: Throw Exceptions
```php
public function processFile(string $path): string
{
    if (!file_exists($path)) {
        throw new InvalidArgumentException("File not found: {$path}");
    }
    
    return file_get_contents($path);
}
```

### Never Use exit()
```php
// ❌ WRONG
if ($error) {
    exit(1);
}

// ✅ CORRECT
if ($error) {
    return Command::FAILURE;
}
```

## PSR-12 Formatting

### Multiline Arrays
```php
$config = [
    'name' => 'tuti',
    'version' => '1.0.0',
    'services' => [
        'docker',
        'stack',
    ],  // ← Trailing comma
];
```

### Return Early
```php
// ✅ CORRECT
public function validate(array $data): bool
{
    if (empty($data)) {
        return false;
    }
    
    if (!isset($data['name'])) {
        return false;
    }
    
    return strlen($data['name']) > 3;
}

// ❌ WRONG
public function validate(array $data): bool
{
    if (empty($data)) {
        $result = false;
    } elseif (!isset($data['name'])) {
        $result = false;
    } else {
        $result = strlen($data['name']) > 3;
    }
    
    return $result;
}
```

## Service Registration

Register in `app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    $this->app->singleton(DockerService::class);
    $this->app->bind(StackInstallerInterface::class, LaravelStackInstaller::class);
}
```

## Directory Structure

```
app/
├── Commands/           # CLI commands (category subfolders)
├── Concerns/           # Traits (HasBrandedOutput, etc.)
├── Contracts/          # Interfaces
├── Domain/             # Value objects, models
├── Enums/              # PHP enums
├── Infrastructure/     # Implementations
├── Providers/          # Service providers
├── Services/           # Business logic (domain subfolders)
└── Support/            # Helper functions
```

## Common Patterns

### Interface Pattern
```php
interface StackInstallerInterface
{
    public function getIdentifier(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getFramework(): string;
    public function supports(string $stack): bool;
    public function installFresh(string $path, string $name, array $options): bool;
    public function applyToExisting(string $path, array $options): bool;
}
```

### Value Object Pattern
```php
final readonly class ProjectConfigurationVO
{
    public function __construct(
        public string $name,
        public string $path,
        public string $stack,
        public array $services = [],
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            path: $data['path'],
            stack: $data['stack'],
            services: $data['services'] ?? [],
        );
    }
}
```

### Enum Pattern
```php
enum Theme: string
{
    case LaravelRed = 'laravel-red';
    case Gray = 'gray';
    case Ocean = 'ocean';
    case Vaporwave = 'vaporwave';
    case Sunset = 'sunset';
    
    public function getColorScheme(): array
    {
        return match($this) {
            self::LaravelRed => ['primary' => 'red', 'secondary' => 'white'],
            self::Gray => ['primary' => 'gray', 'secondary' => 'white'],
            // ...
        };
    }
}
```

## Common Mistakes to Avoid

| ❌ Wrong | ✅ Correct |
|----------|-----------|
| `class MyService` | `final readonly class MyService` |
| `function get($id)` | `function get(int $id): Model` |
| `exit(1)` | `return Command::FAILURE` |
| `$this->service = $service;` | Use constructor promotion |
| `else { return false; }` | Return early, no else |

## Validation Commands

```bash
docker compose exec -T app composer test:unit   # Run tests
docker compose exec -T app composer test:types  # PHPStan check
docker compose exec -T app composer lint        # Fix code style
```
