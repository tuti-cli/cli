Add a new framework stack to tuti-cli: $ARGUMENTS

## Context -- read these files first

1. `stubs/stacks/registry.json` - existing stack definitions (laravel, wordpress)
2. `app/Services/Stack/Installers/LaravelStackInstaller.php` - reference installer implementation
3. `app/Services/Stack/Installers/WordPressStackInstaller.php` - second reference
4. `app/Providers/StackServiceProvider.php` - how installers are registered
5. `app/Commands/Stack/LaravelCommand.php` - reference command implementation
6. `stubs/stacks/laravel/stack.json` - reference stack manifest

## Steps

1. **Add to registry** (`stubs/stacks/registry.json`):
   ```json
   "{stack}": {
       "name": "{Stack} Stack",
       "description": "...",
       "repository": "https://github.com/tuti-cli/{stack}-stack.git",
       "branch": "main",
       "framework": "{stack}",
       "type": "{php|nodejs|python}",
       "min_cli_version": "1.0.0"
   }
   ```

2. **Create stack template** directory `stubs/stacks/{stack}/`:
   - `stack.json` - manifest (see `stubs/stacks/laravel/stack.json` for schema)
   - `docker-compose.yml` - base config with YAML anchors
   - `docker-compose.dev.yml` - dev overlay
   - `docker/Dockerfile` - multi-stage Dockerfile
   - `environments/.env.dev.example` - env template
   - `scripts/entrypoint-dev.sh` - dev entrypoint
   - `services/registry.json` - available services for this stack
   - `services/{category}/{name}.stub` - service stubs

3. **Create installer** at `app/Services/Stack/Installers/{Stack}StackInstaller.php`:
   - `final class` implementing `App\Contracts\StackInstallerInterface`
   - `declare(strict_types=1)`
   - See LaravelStackInstaller or WordPressStackInstaller for pattern

4. **Register installer** in `app/Providers/StackServiceProvider.php`:
   ```php
   $this->app->tag([{Stack}StackInstaller::class], 'stack.installers');
   ```

5. **Create command** at `app/Commands/Stack/{Stack}Command.php`:
   - `final class` with `use HasBrandedOutput`
   - Signature: `stack:{stack} {project-name?} {--mode=} {--path=} {--services=*} {--force}`
   - Follow pattern from `app/Commands/Stack/LaravelCommand.php`

6. **Create tests** at `tests/Feature/Console/Stack/{Stack}CommandTest.php`
