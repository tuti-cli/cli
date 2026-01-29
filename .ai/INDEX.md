# Tuti CLI AI Guidelines & Skills Index

This directory contains AI guidelines and skills for developing Tuti CLI.

## Guidelines

Guidelines are loaded as context and provide best practices and conventions.

### Tuti CLI Specific Guidelines

- **[Core Architecture](guidelines/tuti-cli/core-architecture.md)** - Overall architecture and patterns
- **[Stack System](guidelines/tuti-cli/stack-system.md)** - Stack management architecture
- **[Docker Integration](guidelines/tuti-cli/docker-integration.md)** - Docker service management
- **[Laravel Zero Conventions](guidelines/tuti-cli/laravel-zero-conventions.md)** - Tuti CLI's Laravel Zero patterns

### Laravel Zero Guidelines

- **[Commands](guidelines/laravel-zero/commands.md)** - Command development
- **[Dependencies](guidelines/laravel-zero/dependencies.md)** - Common dependencies
- **[Testing](guidelines/laravel-zero/testing.md)** - Testing with Pest

## Skills

Skills are activated on-demand for specific domains.

- **[Stack Management](skills/stack-management/SKILL.md)** - Work with stacks and installers
- **[Docker Compose Generation](skills/docker-compose-generation/SKILL.md)** - Generate docker-compose files
- **[Service Stubs](skills/service-stubs/SKILL.md)** - Manage service stubs
- **[Laravel Zero Commands](skills/laravel-zero-commands/SKILL.md)** - Develop commands

## How to Use

### For GLM

Reference guidelines in your conversation:

@GLM: Use the stack-system guidelines to create a new WordPress stack installer.

Activate specific skills:

@GLM: Activate the stack-management skill. I need to add a new service.

### For Claude

Claude can read these files as context. Reference them directly:


@Claude: Please review the docker-compose-generation skill and help me...

## Configuration

See `boost-config.json` for the active guidelines and skills.

## Updating

To add new guidelines or skills:
1. Create the file in the appropriate directory
2. Add to `boost-config.json`
3. Reference it in conversations

## Documentation Resources

- Laravel Documentation: https://laravel.com/docs
- Laravel Zero Documentation: https://laravel-zero.com
- Tuti CLI Documentation: `docs/` directory


How to Use with GLM and Claude


Example with GLM

@GLM: I need to create a new WordPress stack for Tuti CLI. Please use the stack-management skill and follow the guidelines in .ai/guidelines/tuti-cli/stack-system.md to implement this. The stack should be similar to the Laravel stack but for WordPress.

Example with Claude

@Claude: I want to add a Memcached service stub to Tuti CLI. Please refer to the service-stubs skill at .ai/skills/service-stubs/SKILL.md and the docker-integration guidelines. Follow the naming conventions and include health checks.
