---
name: stack-management
description: Work with Tuti CLI's stack system, including templates, installers, and service stubs.
---

# Stack Management

## When to Use
Working with stack templates, installers, service stubs, or initialization.

## Key Files

- `app/Services/Stack/StackRepositoryService.php` - Stack management
- `app/Services/Stack/StackInitializationService.php` - Initialization
- `app/Services/Stack/StackComposeBuilderService.php` - Docker compose generation
- `app/Services/Stack/StackStubLoaderService.php` - Service stubs
- `stubs/stacks/registry.json` - Stack definitions
- `stubs/services/registry.json` - Service definitions

## Adding a New Stack

### 1. Create Stack Repository
```bash
mkdir -p stacks/my-stack/{docker,environments}
```

### 2. Create stack.json

```json
{
    "name": "My Stack",
    "version": "1.0.0",
    "description": "Description",
    "framework": "laravel",
    "type": "php"
}
```

### 3. Create Installer
4. 
```php
final class MyStackInstaller implements StackInstallerInterface
{
    public function getIdentifier(): string
    {
        return 'my-stack';
    }
    
    // Implement other interface methods
}
```

### 4. Register in StackServiceProvider
### 5. Create Command
```php
final class StackMyStackCommand extends Command
{
    protected $signature = 'stack:my-stack {project-name?}';
    // ...
}

Service Stubs

Service stubs are in `stubs/services/{category}/{service}.stub`:

Example: `stubs/services/databases/postgres.stub`
```yaml
services:
  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: ${DB_DATABASE:-laravel}
      POSTGRES_USER: ${DB_USERNAME:-postgres}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - postgres_data:/var/lib/postgresql/data
```

Best Practices

- Stacks: framework-specific
- Service stubs: framework-agnostic
- Installers: implement both fresh and existing modes
- Use constructor injection
- Write tests
