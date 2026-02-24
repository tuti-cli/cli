# PHP Code Templates

Quick reference templates for common PHP patterns in Tuti CLI.

## Service Templates

### Basic Service
```php
<?php

declare(strict_types=1);

namespace App\Services\Domain;

use App\Contracts\SomeInterface;

final readonly class MyService
{
    public function __construct(
        private SomeInterface $dependency,
    ) {}

    public function doSomething(string $input): bool
    {
        return ! empty($input);
    }
}
```

### Service with Multiple Dependencies
```php
<?php

declare(strict_types=1);

namespace App\Services\Stack;

use App\Contracts\OrchestratorInterface;
use App\Services\Docker\DockerService;
use App\Services\Project\ProjectStateManagerService;

final readonly class StackInitializationService
{
    public function __construct(
        private OrchestratorInterface $orchestrator,
        private DockerService $docker,
        private ProjectStateManagerService $stateManager,
    ) {}

    public function initialize(string $path, string $stack): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $this->stateManager->setStack($path, $stack);
        
        return true;
    }
}
```

## Command Templates

### Basic Command
```php
<?php

declare(strict_types=1);

namespace App\Commands\Category;

use App\Concerns\HasBrandedOutput;
use App\Services\MyService;
use LaravelZero\Framework\Commands\Command;

final class MyCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'category:action {argument?} {--option=default}';
    protected $description = 'Description of what this command does';

    public function __construct(private readonly MyService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->brandedHeader('Feature Name');

        $argument = $this->argument('argument');
        $option = $this->option('option');

        if (! $this->service->isReady()) {
            $this->error('Service not ready');
            return Command::FAILURE;
        }

        $this->service->execute($argument, $option);

        $this->success('Operation completed successfully');

        return Command::SUCCESS;
    }
}
```

### Command with Multiple Options
```php
<?php

declare(strict_types=1);

namespace App\Commands\Local;

use App\Concerns\HasBrandedOutput;
use App\Services\Docker\DockerService;
use LaravelZero\Framework\Commands\Command;

final class StartCommand extends Command
{
    use HasBrandedOutput;

    protected $signature = 'local:start
        {--rebuild : Rebuild containers before starting}
        {--detach|d : Run in background}
        {--services=* : Specific services to start}';

    protected $description = 'Start the local development environment';

    public function __construct(private readonly DockerService $docker)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->brandedHeader('Starting Local Environment');

        $services = $this->option('services');
        $rebuild = $this->option('rebuild');
        $detach = $this->option('detach');

        if ($rebuild) {
            $this->step('Rebuilding containers...');
            $this->docker->rebuild();
        }

        $this->step('Starting services...');
        $this->docker->start($services, $detach);

        $this->success('Environment started successfully');

        return Command::SUCCESS;
    }
}
```

## Interface Templates

### Basic Interface
```php
<?php

declare(strict_types=1);

namespace App\Contracts;

interface MyServiceInterface
{
    public function execute(string $action): bool;
    public function getStatus(): array;
}
```

### Stack Installer Interface
```php
<?php

declare(strict_types=1);

namespace App\Contracts;

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

## Value Object Templates

### Basic Value Object
```php
<?php

declare(strict_types=1);

namespace App\Domain;

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

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'stack' => $this->stack,
            'services' => $this->services,
        ];
    }
}
```

## Enum Templates

### Basic Enum
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum Theme: string
{
    case LaravelRed = 'laravel-red';
    case Gray = 'gray';
    case Ocean = 'ocean';
    case Vaporwave = 'vaporwave';
    case Sunset = 'sunset';
}
```

### Enum with Methods
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ServiceType: string
{
    case Database = 'database';
    case Cache = 'cache';
    case Search = 'search';
    case Mail = 'mail';
    case Storage = 'storage';

    public function getCategory(): string
    {
        return match ($this) {
            self::Database => 'databases',
            self::Cache => 'cache',
            self::Search => 'search',
            self::Mail => 'mail',
            self::Storage => 'storage',
        };
    }

    public function getDefaultPort(): int
    {
        return match ($this) {
            self::Database => 5432,
            self::Cache => 6379,
            self::Search => 7700,
            self::Mail => 1025,
            self::Storage => 9000,
        };
    }
}
```

## Trait Templates

### Basic Trait
```php
<?php

declare(strict_types=1);

namespace App\Concerns;

trait HasBrandedOutput
{
    protected function brandedHeader(string $title): void
    {
        $this->newline();
        $this->line("<fg=cyan;options=bold>  ▶ $title</>");
        $this->newline();
    }

    protected function step(string $message): void
    {
        $this->line("  <fg=yellow>●</> $message");
    }

    protected function success(string $message): void
    {
        $this->line("  <fg=green>✓</> $message");
    }

    protected function failure(string $message): void
    {
        $this->line("  <fg=red>✗</> $message");
    }
}
```

## Service Provider Templates

### Basic Provider
```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\MyServiceInterface;
use App\Services\MyService;
use Illuminate\Support\ServiceProvider;

final class MyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MyServiceInterface::class, MyService::class);
    }

    public function boot(): void
    {
        // Boot logic here
    }
}
```

## Test Templates

### Service Test
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain;

use App\Services\Domain\MyService;
use Tests\TestCase;

describe('MyService', function (): void {
    beforeEach(function (): void {
        $this->service = app(MyService::class);
    });

    describe('doSomething', function (): void {
        it('returns true for non-empty input', function (): void {
            expect($this->service->doSomething('test'))->toBeTrue();
        });

        it('returns false for empty input', function (): void {
            expect($this->service->doSomething(''))->toBeFalse();
        });
    });
});
```

### Command Test
```php
<?php

declare(strict_types=1);

use App\Commands\Category\MyCommand;
use LaravelZero\Framework\Commands\Command;
use Tests\TestCase;

describe('MyCommand', function (): void {
    beforeEach(function (): void {
        // Setup mocks
    });

    describe('registration', function (): void {
        it('has correct signature', function (): void {
            $command = app(MyCommand::class);
            expect($command->getName())->toBe('category:action');
        });

        it('uses HasBrandedOutput trait', function (): void {
            expect(class_uses(MyCommand::class))
                ->toHaveKey(App\Concerns\HasBrandedOutput::class);
        });
    });

    describe('execution', function (): void {
        it('executes successfully', function (): void {
            $this->artisan('category:action')
                ->assertExitCode(Command::SUCCESS);
        });
    });
});
```
