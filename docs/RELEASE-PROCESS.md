# Release Process

## Local Testing (Before Release)

```bash
# Build PHAR first
make build-phar
make test-phar

# Build binaries with phpacker
make build-binary-linux    # Linux only (faster for testing)
# OR
make build-binary          # All platforms

# Test binary locally (NO PHP required!)
make test-binary

# Install locally and test
make install-local
~/.tuti/bin/tuti --version
~/.tuti/bin/tuti install
```

## How Binaries Work

We use **phpacker** (as documented in Laravel Zero docs) to create self-contained binaries:

1. **Build PHAR** first: `php tuti app:build tuti.phar`
2. **Run phpacker**: `./vendor/bin/phpacker build --src=./builds/tuti.phar --php=8.4 all`
3. **Result**: Binaries with embedded PHP 8.4 runtime

The binaries are created in `builds/build/` organized by platform:
```
builds/build/
├── linux/
│   ├── linux-x64
│   └── linux-arm64
├── mac/
│   ├── mac-x64
│   └── mac-arm64
└── windows/
    └── windows-x64.exe
```

### Binary Features:
- ✅ **Completely self-contained** - includes PHP 8.4 runtime
- ✅ **No system dependencies** - user only needs the binary
- ✅ **All platforms** - Linux, macOS, Windows
- ✅ **Single file** - easy to distribute


## Release

```bash
make version-bump V=0.1.4
git add . && git commit -m "Release v0.1.4"
git tag -a v0.1.4 -m "Release v0.1.4"
git push origin main --tags
```

GitHub Actions will automatically build binaries for all platforms with static PHP embedded.

## Verify Release

1. https://github.com/tuti-cli/cli/actions
2. https://github.com/tuti-cli/cli/releases

## Rollback

```bash
git tag -d v0.1.4
git push origin :refs/tags/v0.1.4
```
