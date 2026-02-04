---
name: phar-binary
description: Build PHAR and native binaries with phpacker
globs:
  - box.json
  - Makefile
  - builds/**
---

# PHAR & Binary Build Skill

## When to Use
- Building PHAR archive
- Creating native binaries
- Troubleshooting build issues

## Build Commands

```bash
# PHAR (required first)
make build-phar

# All platforms
make build-binary

# Specific platform
make build-binary-linux
make build-binary-mac
make build-binary-windows
```

## Key Files

| File | Purpose |
|------|---------|
| `box.json` | PHAR configuration |
| `Makefile` | Build automation |
| `builds/tuti.phar` | PHAR output |
| `builds/build/` | Binary outputs |

## box.json Configuration

```json
{
    "directories": ["app", "bootstrap", "config"],
    "files": ["composer.json"],
    "exclude-composer-files": false,
    "compression": "GZ",
    "main": "tuti",
    "output": "builds/tuti.phar"
}
```

## phpacker

Used for creating native binaries with embedded PHP runtime.

```php
// In build script
$phpacker = new Phpacker([
    'phar' => 'builds/tuti.phar',
    'output' => 'builds/build/linux/tuti',
    'php_version' => '8.4',
]);
```

## Build Checklist

- [ ] Run tests before building
- [ ] Bump version in `config/app.php`
- [ ] Build PHAR first
- [ ] Test PHAR locally
- [ ] Build binaries for all platforms
- [ ] Test binaries without PHP installed

## Troubleshooting

**PHAR fails to build:**
- Check `box.json` directories
- Ensure all required files included
- Run `composer dump-autoload -o`

**Binary doesn't execute:**
- Verify PHP extensions in phpacker config
- Check file permissions
- Test on target platform
