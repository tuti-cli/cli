# Release Process

## What Gets Built

On each release, GitHub Actions builds:
- **tuti.phar** - PHAR file (requires PHP 8.4+)
- **tuti-linux-amd64** - Linux x64 native binary
- **tuti-linux-arm64** - Linux ARM64 native binary  
- **tuti-darwin-amd64** - macOS Intel native binary
- **tuti-darwin-arm64** - macOS Apple Silicon native binary

---

## Prerequisites

Make sure Docker is running:
```bash
make up
```

## Quick Release

```bash
# One command release (builds, tests, commits, tags)
make release-auto V=0.1.0

# Then push to GitHub
git push origin main --tags
```

GitHub Actions will automatically build all binaries and create the release.

---

## Step-by-Step Release

### 1. Bump Version
```bash
make version-bump V=0.1.0
```

### 2. Build PHAR
```bash
make build-phar
```

### 3. Test PHAR
```bash
make test-phar
```

### 4. Commit & Tag
```bash
git add .
git commit -m "Release v0.1.0"
git tag -a v0.1.0 -m "Release v0.1.0"
```

### 5. Push
```bash
git push origin main --tags
```

### 6. Verify
1. Check: https://github.com/tuti-cli/cli/actions
2. Check: https://github.com/tuti-cli/cli/releases

---

## Available Commands

```bash
make help              # Show all commands
make build-phar        # Build PHAR
make build-binaries    # Build PHAR + attempt native binaries (local)
make test-phar         # Test PHAR
make check-build       # Verify app:build is available
make version-bump V=x  # Update version
make release           # Show release steps
make release-auto V=x  # Full automated release
```

---

## Troubleshooting

If `app:build` is not available, check `config/commands.php` and ensure `BuildCommand` is commented out in the `hidden` array.

---

## Rollback

```bash
git tag -d v0.1.0
git push origin :refs/tags/v0.1.0
# Delete release on GitHub UI
```
