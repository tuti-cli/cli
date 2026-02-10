# Claude Terminal Settings for tuti-cli

## Recommended Terminal Usage

### Token-Efficient Commands

```bash
# Use these instead of reading files
grep -n "pattern" app/Services/*.php          # Find code locations
grep -r "ClassName" app/ --include="*.php"   # Find class usage
wc -l app/Services/**/*.php                   # Count lines

# Run tests instead of reading test output
composer test:unit                            # Full test suite
./vendor/bin/pest --filter "methodName"       # Single test
./vendor/bin/pest tests/Unit/path/file.php   # Single file

# Static analysis
composer test:types                           # PHPStan
```

### Avoid These Commands

```bash
# NEVER run these (large output, wastes tokens)
cat vendor/*                                  # Blocked
cat composer.lock                             # Blocked
find . -name "*.php"                          # Too many results
ls -laR                                       # Too verbose
docker logs --tail=1000                       # Too much output
```

### Efficient Debugging

```bash
# Instead of reading large logs
tail -n 50 storage/logs/tuti.log             # Last 50 lines only
grep "ERROR" storage/logs/*.log              # Only errors

# Instead of full test output
./vendor/bin/pest --filter "testName" 2>&1 | head -30
```

## IDE Integration Tips

1. Use IDE's built-in file tree instead of `find` commands
2. Use grep for targeted searches before reading files
3. Trust CLAUDE.md patterns - don't re-verify in source code
4. Run `composer test:unit` to verify changes work
