# Console Branding & UI Components

## Overview

Tuti CLI uses `HasBrandedOutput` trait for consistent, beautiful UX/UI across all commands.
The trait provides themed branding, clear status messages, progress indicators, and organized output.

## Quick Start

```php
use App\Traits\HasBrandedOutput;

final class MyCommand extends Command
{
    use HasBrandedOutput;

    public function handle(): int
    {
        $this->brandedHeader('Stack Installation', 'my-project');
        
        $this->step(1, 3, 'Validating configuration');
        $this->success('Configuration valid');
        
        $this->step(2, 3, 'Creating files');
        $this->created('.tuti/docker-compose.yml');
        
        $this->step(3, 3, 'Starting services');
        $this->done('All services running');
        
        $this->completed('Installation finished!', [
            'cd my-project',
            'tuti local:start',
        ]);
        
        return Command::SUCCESS;
    }
}
```

## Method Reference

### Branding & Headers

```php
$this->welcomeBanner();                          // Full welcome screen with logo
$this->brandedHeader('Feature', 'project');      // Header with logo + tagline
$this->tutiLogo();                               // ASCII logo only
$this->tagline('Feature Name');                  // Themed tagline badge
$this->outro('Thanks!', 'Docs', 'https://...');  // Centered outro with link
$this->outro('Thanks!');                         // Outro without link
```

### Status Messages

```php
$this->success('Operation completed');           // ✔ Green checkmark
$this->failure('Something failed');              // ✖ Red X mark
$this->warning('Attention needed');              // ⚠ Yellow warning
$this->note('General information');              // → Blue arrow
$this->hint('Helpful suggestion');               // 💡 Cyan lightbulb
$this->waiting('Processing');                    // ◌ Gray dots
$this->done('Finished');                         // ● Green dot
$this->skipped('Optional step');                 // ○ Gray (skipped)
```

### Action Messages

```php
$this->taskStart('Installing');                  // ▶ Task beginning
$this->taskDone('Installed');                    // ✔ Task completed
$this->taskFailed('Install failed');             // ✖ Task failed
$this->action('Pulling images', 'nginx');        // ⟳ Action with detail
$this->command('docker-compose up');             // $ Shell command
```

### Progress & Steps

```php
$this->step(1, 4, 'Validating config');          // [1/4] Step progress
$this->stepBadge(2, 4);                          // Returns badge string
$this->bullet('Item text', 'green');             // • Bullet point
$this->subItem('Nested detail');                 //   └─ Sub-item
```

### File Operations

```php
$this->created('.tuti/config.json');             // + created (green)
$this->modified('.env');                         // ~ modified (yellow)
$this->deleted('old-file.txt');                  // - deleted (red)
$this->unchanged('composer.json');               // ○ unchanged (gray)
```

### Section Organization

```php
$this->section('Configuration');                 // ─── Section header ───
$this->subsection('Database');                   // ┌─ Subsection
$this->header('Options');                        // Bold mini header
$this->spacer();                                 // Empty line
```

### Dividers

```php
$this->divider();                                // ────────────────
$this->dividerDouble();                          // ════════════════
$this->dividerLight();                           // ┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄
$this->dividerWithText('Title');                 // ─── Title ───
```

### Callout Boxes

```php
$this->successBox('Done', ['Detail 1', 'Detail 2']);
$this->errorBox('Failed', ['Error detail']);
$this->warningBox('Warning', ['Warning detail']);
$this->tipBox('Pro Tip', ['Helpful info']);
$this->infoBox(['Key' => 'Value']);
```

### Key-Value Display

```php
$this->keyValue('Project', 'my-app');            // Key          value
$this->keyValueList(['A' => '1', 'B' => '2']);   // Multiple pairs
$this->labeledValue('Status', 'Running');        // Label: [Badge]
```

### Boxes & Panels

```php
$this->box('Title', ['Line 1', 'Line 2']);       // Titled bordered box
$this->panel(['Line 1', 'Line 2']);              // Simple bordered panel
```

### Badges

```php
$this->badge('TEXT');                            // Theme-colored badge
$this->badgeSuccess('RUNNING');                  // Green badge
$this->badgeError('STOPPED');                    // Red badge
$this->badgeWarning('PENDING');                  // Orange badge
$this->badgeInfo('NEW');                         // Blue badge
```

### Text Formatting

```php
$this->highlight('important');                   // Accent color text
$this->dim('secondary info');                    // Gray text
$this->bold('emphasized');                       // Bold white text
$this->hyperlink('Docs', 'https://...');         // Clickable terminal link
```

### Final Messages

```php
// Success with next steps
$this->completed('Installation done!', [
    'cd my-project',
    'tuti local:start',
]);

// Failure with suggestions
$this->failed('Could not start', [
    'Check Docker is running',
    'Verify port availability',
]);
```

## Theme System

Themes automatically apply consistent colors:

```php
$this->brandedHeader('Feature');                 // Random theme
$this->brandedHeader('Feature', null, Theme::Sunset);  // Specific theme
```

Available: `LaravelRed`, `Gray`, `Ocean`, `Vaporwave`, `Sunset`

## Best Practices

1. **Start with branding** - Use `brandedHeader()` or `welcomeBanner()`
2. **Use sections** - Organize output with `section()` for clarity
3. **Show progress** - Use `step()` for multi-step operations
4. **Provide feedback** - Use status messages (`success`, `failure`, etc.)
5. **Show file changes** - Use `created`, `modified`, `deleted`
6. **End with summary** - Use `completed()` or `failed()` with next steps
7. **Add outro** - Use `outro()` for branded closing
