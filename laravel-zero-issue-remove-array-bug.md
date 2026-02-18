# Bug: `remove` array in `config/commands.php` does not remove Laravel built-in commands

## Description

The `remove` configuration option in `config/commands.php` does not work as expected when trying to remove Laravel's built-in commands (migrations, database operations, make commands, etc.). The commands remain visible in the command list even after being added to the `remove` array.

However, the `hidden` array works correctly for the same commands.

## Steps to Reproduce

1. Create a fresh Laravel Zero application
2. Add Laravel built-in command classes to the `remove` array in `config/commands.php`:
```php
'remove' => [
    Illuminate\Database\Console\Migrations\MigrateCommand::class,
    Illuminate\Database\Console\Migrations\FreshCommand::class,
    Illuminate\Database\Console\Seeds\SeedCommand::class,
    Illuminate\Database\Console\WipeCommand::class,
    Illuminate\Database\Console\Migrations\MigrateMakeCommand::class,
    Illuminate\Foundation\Console\ModelMakeCommand::class,
],
```

3. Run `php application list`
4. Observe that the commands (`migrate`, `migrate:fresh`, `db:seed`, `db:wipe`, `make:migration`, `make:model`) are still visible in the command list

## Expected Behavior

Commands added to the `remove` array should be completely removed from the application and not appear in the command list or be executable.

According to the documentation:
> `remove`: "Removes the list of commands provided."

## Actual Behavior

Commands in the `remove` array remain visible and executable, as if the configuration has no effect.

## Workaround

Using the `hidden` array instead of `remove` works as expected:

```php
'hidden' => [
    Illuminate\Database\Console\Migrations\MigrateCommand::class,
    Illuminate\Database\Console\Migrations\FreshCommand::class,
    Illuminate\Database\Console\Seeds\SeedCommand::class,
    Illuminate\Database\Console\WipeCommand::class,
    // ... other commands
],
```

This successfully hides the commands from the list (though they can still be run if the command name is known).

## Environment

- **Laravel Zero Version**: 12.x
- **PHP Version**: 8.4
- **Laravel Version**: 11.x
- **Operating System**: Linux (Docker container)
- **Installation Type**: Fresh installation via composer

## Additional Context

### Commands Affected

The following Laravel built-in commands are not removed by the `remove` array:

**Migration Commands:**
- `Illuminate\Database\Console\Migrations\MigrateCommand`
- `Illuminate\Database\Console\Migrations\FreshCommand`
- `Illuminate\Database\Console\Migrations\InstallCommand`
- `Illuminate\Database\Console\Migrations\RefreshCommand`
- `Illuminate\Database\Console\Migrations\ResetCommand`
- `Illuminate\Database\Console\Migrations\RollbackCommand`
- `Illuminate\Database\Console\Migrations\StatusCommand`

**Database Commands:**
- `Illuminate\Database\Console\Seeds\SeedCommand`
- `Illuminate\Database\Console\WipeCommand`

**Make Commands:**
- `Illuminate\Database\Console\Migrations\MigrateMakeCommand`
- `Illuminate\Database\Console\Factories\FactoryMakeCommand`
- `Illuminate\Database\Console\Seeds\SeederMakeCommand`
- `Illuminate\Foundation\Console\ModelMakeCommand`

**Scheduling Commands:**
- `Illuminate\Console\Scheduling\ScheduleRunCommand`
- `Illuminate\Console\Scheduling\ScheduleListCommand`
- `Illuminate\Console\Scheduling\ScheduleFinishCommand`

### Test Case

```php
// config/commands.php
return [
    // ... other config
    
    'remove' => [
        Illuminate\Database\Console\Migrations\MigrateCommand::class,
    ],
];

// Then run: php application list
// Expected: "migrate" command should not appear
// Actual: "migrate" command still appears
```

### Potential Root Cause

It appears that Laravel's built-in commands are registered through service providers (like `Illuminate\Database\DatabaseServiceProvider`) which may load AFTER the `remove` configuration is processed by the Console Kernel. This would explain why:

1. The `remove` array has no effect (commands aren't registered yet when it's checked)
2. The `hidden` array works (it's processed after all commands are registered)

### Suggested Fix

The Console Kernel should process the `remove` array after all service providers have been loaded and all commands have been registered, or implement a two-pass approach:

1. First pass: Register all commands (including from service providers)
2. Second pass: Apply `remove` configuration

Alternatively, document this limitation and recommend using `hidden` for service provider-registered commands.

## Related

- This issue affects developers building CLI applications who want to remove Laravel's framework commands that aren't relevant to their application
- Common use case: Building a domain-specific CLI tool that doesn't need migration/database commands

## Proposed Documentation Update

If this is a known limitation, the documentation should clarify:

```php
/*
|--------------------------------------------------------------------------
| Removed Commands
|--------------------------------------------------------------------------
|
| Note: The 'remove' array works for commands registered in app/Console/Commands
| but may not work for commands registered via service providers.
| For service provider commands, use the 'hidden' array instead.
|
*/
```

## Thank You

Thank you for maintaining Laravel Zero! It's an excellent framework for building CLI applications. I hope this bug report helps improve the project.