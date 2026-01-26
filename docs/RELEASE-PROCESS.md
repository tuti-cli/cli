# Release Process

## Local Testing (Before Release)

```bash
# Build PHAR first
make build-phar
make test-phar

# Build binaries for all platforms (uses phpacker)
make build-binary

# Test binary locally (no PHP required!)
make test-binary

# Install locally and test
make install-local
~/.tuti/bin/tuti --version
~/.tuti/bin/tuti install
```

## Binary Output Structure

phpacker creates binaries in `builds/build/`:
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

## Release

```bash
make version-bump V=0.1.1
git add . && git commit -m "Release v0.1.1"
git tag -a v0.1.1 -m "Release v0.1.1"
git push origin main --tags
```

GitHub Actions will automatically:
1. Build PHAR
2. Build binaries with phpacker for all platforms
3. Create GitHub Release with all files

## Verify Release

1. https://github.com/tuti-cli/cli/actions
2. https://github.com/tuti-cli/cli/releases

## Rollback

```bash
git tag -d v0.1.1
git push origin :refs/tags/v0.1.1
```
