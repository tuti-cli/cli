
#### 3.2 Stack System
Create `.ai/guidelines/tuti-cli/stack-system.md`:

```markdown
# Tuti CLI Stack System

The stack system provides pre-configured Docker setups for different frameworks.

## Stack Architecture

### Stack Sources

1. **Remote Repositories**:
   - Defined in `stubs/stacks/registry.json`
   - Downloaded to `~/.tuti/stacks/` on first use
   - Updated via `tuti stack:manage update`

2. **Local Development** (for contributors):
   - Placed in `stacks/` directory
   - Takes precedence over remote stacks

### Stack Components

**Stack Repository** (`stubs/stacks/registry.json`):
```json
{
    "stacks": {
        "laravel": {
            "name": "Laravel Stack",
            "description": "Laravel with Docker support",
            "repository": "https://github.com/tuti-cli/laravel-stack.git",
            "branch": "main",
            "framework": "laravel",
            "type": "php"
        }
    }
}


**Stack Directory Structure**:

laravel-stack/
├── stack.json                 # Stack manifest
├── docker/
│   ├── Dockerfile
│   └── nginx.conf
├── environments/
│   ├── .env.dev.example
│   └── .env.prod.example
├── docker-compose.yml          # Base compose
├── docker-compose.dev.yml      # Dev overrides
└── docker-compose.prod.yml     # Prod overrides

Stack Installer Interface

All stack installers must implement `StackInstallerInterface`:

interface StackInstallerInterface
{
    public function getIdentifier(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getFramework(): string;
    public function supports(string $stackIdentifier): bool;
    public function detectExistingProject(string $path): bool;
    public function installFresh(string $projectPath, string $projectName, array $options = []): bool;
    public function applyToExisting(string $projectPath, array $options = []): bool;
    public function getStackPath(): string;
    public function getAvailableModes(): array;
}


Key Services

**StackRepositoryService** (`app/Services/Stack/StackRepositoryService.php`):
- Downloads stacks from remote repositories
- Caches stacks locally
- Checks for updates

**StackInitializationService** (`app/Services/Stack/StackInitializationService.php`):
- Calls appropriate stack installer
- Handles fresh and existing modes
- Manages service selection

**StackComposeBuilderService** (`app/Services/Stack/StackComposeBuilderService.php`):
- Combines base compose with service stubs
- Applies stack-specific overrides
- Generates final docker-compose.yml

**StackStubLoaderService** (`app/Services/Stack/StackStubLoaderService.php`):
- Loads service stubs from `stubs/services/`
- Provides service configurations

### Adding a New Stack

1. Create stack repository (e.g., `tuti-cli/wordpress-stack`)
2. Add to `stubs/stacks/registry.json`
3. Create installer in `app/Services/Stack/Installers/`
4. Register in `StackServiceProvider`
5. Create command in `app/Commands/Stack/`

## Service Stubs

Located in `stubs/services/`:
- `registry.json`: Service definitions
- `databases/`: Postgres, MySQL, MariaDB
- `cache/`: Redis
- `search/`: Meilisearch, Typesense
- `storage/`: MinIO
- `mail/`: Mailpit

Service stubs are universal templates that work with any stack.
