# Patch: Duplicate Database Key in Docker Compose YAML

**Date:** 2026-03-09
**Severity:** High
**Status:** Fixed

## Problem

When creating a WordPress (Bedrock) project, the installation failed with:

```
FAILED  Installation failed: Generated invalid YAML for /home/yevhenii/www/elements/.tuti/docker-compose.yml:
Duplicate key "database" detected at line 93
```

## Root Cause

The duplicate detection logic in `OptionalServicesBuilder::appendServices()` used the registry key name (e.g., `mariadb`) to check for existing services, but the actual YAML service name in the stub file was different (e.g., `database`).

### Code Path

1. User selects `databases.mariadb` service
2. `$serviceName = 'mariadb'` extracted from registry key
3. Check looks for `"  mariadb:"` in compose content - **NOT FOUND**
4. Stub file `mariadb.stub` defines service as `database:`
5. Code appends another `database:` entry
6. YAML parsing fails with duplicate key error

### Affected Code

- `app/Services/Stack/OptionalServicesBuilder.php` line 73 (base compose)
- `app/Services/Stack/OptionalServicesBuilder.php` line 310 (dev compose)

## Solution

1. Load the stub YAML first
2. Parse the YAML to extract the actual service name (first top-level key)
3. Use the actual service name for duplicate detection

### New Method

Added `extractServiceName()` helper method that:
- Uses Symfony YAML parser (already in project)
- Returns the first top-level key from parsed YAML
- Returns null for invalid/empty YAML

```php
private function extractServiceName(string $yaml): ?string
{
    if (mb_trim($yaml) === '') {
        return null;
    }

    try {
        $parsed = Yaml::parse($yaml);

        if (! is_array($parsed) || $parsed === []) {
            return null;
        }

        $keys = array_keys($parsed);

        return $keys[0];
    } catch (ParseException) {
        return null;
    }
}
```

## Files Changed

- `app/Services/Stack/OptionalServicesBuilder.php`
- `tests/Unit/Services/Stack/OptionalServicesBuilderTest.php` (regression test added)

## Regression Test

Added test case `it skips service when stub service name differs from registry key` that:
1. Creates a stub with registry key `mariadb` but YAML service name `database`
2. Creates a compose file that already has `database:` service
3. Verifies only one `database:` service exists after processing
4. Verifies the YAML is valid (parses without errors)

## Prevention

- Always extract actual service name from stub YAML, never assume registry key matches
- Use proper YAML parsing instead of string matching for structured data
