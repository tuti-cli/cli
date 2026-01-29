# Tuti CLI Core Architecture

Tuti CLI is a Docker-based environment management tool for Laravel projects built with Laravel Zero.

## Project Overview

- **Framework**: Laravel Zero 12.x (micro-framework for console applications)
- **PHP Version**: 8.4
- **Purpose**: Manage local development environments for Laravel projects with Docker
- **Output**: Self-contained binary with embedded PHP runtime

## Directory Structure

app/
├── Commands/              # Console commands
│   ├── Local/            # Environment management (start, stop, logs)
│   ├── Stack/            # Stack installation commands
│   └── Test/             # Testing commands
├── Contracts/            # Interfaces and contracts
├── Domain/               # Domain models and value objects
├── Infrastructure/       # External service integrations
├── Providers/            # Service providers
├── Services/             # Business logic services
│   ├── Context/          # Context management
│   ├── Docker/           # Docker integration
│   ├── Global/           # Global configuration (~/.tuti/)
│   ├── Project/          # Project-specific (.tuti/)
│   ├── Stack/            # Stack management
│   └── Storage/          # Storage operations
├── Support/              # Helper functions
└── Traits/               # Reusable traits

stubs/
├── stacks/               # Stack registry and templates
└── services/             # Service stubs for docker-compose


## Architectural Modes

### Global Mode (~/.tuti/)
- Configuration: `~/.tuti/settings.json`, `~/.tuti/projects.json`
- Stack templates: `~/.tuti/stacks/`
- Logs: `~/.tuti/logs/`
- Cache: `~/.tuti/cache/`

### Project Mode (.tuti/)
- Configuration: `.tuti/config.json`
- Docker configuration: `.tuti/docker-compose.yml`
- Environment files: `.tuti/.env`

## Key Patterns

### Service Layer Pattern
All services are:
- `final` classes (no inheritance unless needed)
- Constructor injection only
- `readonly` properties for dependencies
- Single responsibility
- Private methods by default

Example:
```php
final class StackInitializationService
{
    public function __construct(
        private readonly StackRepositoryService $stackRepository,
        private readonly StackComposeBuilderService $composeBuilder,
        private readonly Files $files,
    ) {
    }

    public function initialize(array $options): bool
    {
        $stackPath = $this->stackRepository->getStackPath($options['stack']);
        $compose = $this->composeBuilder->buildWithStack($stackPath, $options['services']);
        
        return $this->files->put('.tuti/docker-compose.yml', $compose) !== false;
    }
}

## Command Pattern
Commands extend Laravel Zero's `Command` class:

final class StackLaravelCommand extends Command
{
    protected $signature = 'stack:laravel 
        {project-name? : Name of the project}
        {--mode=fresh : Installation mode (fresh|existing)}
        {--path=. : Path for installation}
        {--services=* : Services to include}
        {--no-interaction : Run without prompts}
    ';
    
    protected $description = 'Initialize a Laravel project with Docker support';

    public function __construct(
        private readonly StackInitializationService $stackInit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->stackInit->initialize([
                'stack' => 'laravel',
                'mode' => $this->option('mode'),
                'project_name' => $this->argument('project-name'),
                'services' => $this->option('services'),
            ]);

            $this->info('Laravel stack initialized successfully!');

            return Command::SUCCESS;
        } catch (StackNotFoundException $e) {
            $this->error("Stack not found: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}


Coding Standards

- **ALWAYS** enable strict types: `declare(strict_types=1);`
- Classes are `final` by default
- Properties are `private` by default
- Use `readonly` for immutable DTOs and injected dependencies
- Type all properties and methods
- No PHPDoc for type-hinted code
- Return early, avoid `else`/`elseif`
- Use trailing commas in multiline arrays
- Follow PSR-12 formatting (handled by Laravel Pint)
