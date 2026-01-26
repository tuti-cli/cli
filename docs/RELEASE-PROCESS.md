# Release Process

## Quick Release

```bash
make version-bump V=0.1.0
make build-phar
make test-phar
git add . && git commit -m "Release v0.1.0"
git tag -a v0.1.0 -m "Release v0.1.0"
git push origin main --tags
```

GitHub Actions will automatically:
1. Build PHAR
2. Create self-contained binaries for Linux and macOS (with embedded PHP)
3. Create GitHub Release with all files

## What Gets Built

| File | Platform | PHP Required |
|------|----------|--------------|
| `tuti-linux-amd64` | Linux x64 | No |
| `tuti-linux-arm64` | Linux ARM64 | No |
| `tuti-darwin-amd64` | macOS Intel | No |
| `tuti-darwin-arm64` | macOS Apple Silicon | No |
| `tuti.phar` | Any | Yes |

## Verify Release

1. https://github.com/tuti-cli/cli/actions
2. https://github.com/tuti-cli/cli/releases

## Rollback

```bash
git tag -d v0.1.0
git push origin :refs/tags/v0.1.0
```
