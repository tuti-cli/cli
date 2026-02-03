---
name: stack-management
description: Create and modify Tuti CLI stacks, installers, and service stubs
globs:
  - app/Services/Stack/**
  - app/Commands/Stack/**
  - stubs/stacks/**
  - stubs/services/**
---

# Stack Management Skill

## When to Use
- Adding new framework stack (WordPress, Next.js, Django, etc.)
- Modifying existing stack installer
- Working with universal service stubs

## Key Files

```
app/Services/Stack/
├── StackRepositoryService.php      # Download/cache stacks
├── StackInitializationService.php  # Project initialization
├── StackComposeBuilderService.php  # docker-compose generation
├── StackStubLoaderService.php      # Load service stubs
├── StackInstallerRegistry.php      # Installer management
└── Installers/
    └── LaravelStackInstaller.php   # Reference implementation

stubs/
├── stacks/registry.json            # Stack definitions
└── services/registry.json          # Service definitions
```

## Add New Stack

### 1. Registry Entry (`stubs/stacks/registry.json`)
```json
{
    "wordpress": {
        "name": "WordPress Stack",
        "repository": "https://github.com/tuti-cli/wordpress-stack.git",
        "framework": "wordpress",
        "type": "php"
    },
    "nextjs": {
        "name": "Next.js Stack",
        "repository": "https://github.com/tuti-cli/nextjs-stack.git",
        "framework": "nextjs", 
        "type": "nodejs"
    }
}
```

### 2. Installer (`app/Services/Stack/Installers/WordPressStackInstaller.php`)
```php
final class WordPressStackInstaller implements StackInstallerInterface
{
    public function getIdentifier(): string { return 'wordpress'; }
    public function supports(string $stack): bool { return $stack === 'wordpress'; }
    // ... implement other methods
}
```

### 3. Register in Provider
```php
// app/Providers/StackServiceProvider.php
$this->app->tag([WordPressStackInstaller::class], 'stack.installers');
```

### 4. Command (`app/Commands/Stack/WordPressCommand.php`)
```php
final class StackWordPressCommand extends Command
{
    protected $signature = 'stack:wordpress {project-name?}';
}
```

## Add Service Stub

### 1. Create Stub (`stubs/services/cache/memcached.stub`)
```yaml
services:
  memcached:
    image: memcached:alpine
    container_name: ${PROJECT_NAME:-app}-memcached
```

### 2. Register (`stubs/services/registry.json`)
```json
{
    "cache": {
        "memcached": {
            "name": "Memcached",
            "stub": "cache/memcached.stub"
        }
    }
}
```
