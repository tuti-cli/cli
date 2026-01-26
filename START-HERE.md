# 🚀 START HERE - Quick Guide

## ✅ Everything is Ready!

All files are configured and ready to build and release:
- ✅ Makefile - Docker commands for PHAR and binary builds
- ✅ GitHub Actions - Auto-builds PHAR + native binaries for Linux/macOS
- ✅ Install script - Smart installer detects OS/arch and downloads correct binary
- ✅ Documentation complete

---

## 📦 What Gets Built on Release

| File | Platform | Notes |
|------|----------|-------|
| `tuti.phar` | All (requires PHP 8.4+) | Fallback option |
| `tuti-linux-amd64` | Linux x64 | Native binary |
| `tuti-linux-arm64` | Linux ARM64 | Native binary |
| `tuti-darwin-amd64` | macOS Intel | Native binary |
| `tuti-darwin-arm64` | macOS Apple Silicon | Native binary |

---

## 🎯 Quick Commands (Do This Now!)

```bash
# 1. Check app:build is available
docker compose exec app php tuti list | grep app:build

# 2. Build PHAR
make build-phar

# 3. Test PHAR
make test-phar

# 4. Release (if tests pass)
make release-auto V=0.1.0
git push origin main --tags
```

---

## 📚 Documentation

- **READY-TO-RELEASE.md** - Detailed release guide
- **docs/RELEASE-PROCESS.md** - Release process documentation
- **docs/STACKS.md** - Stack system documentation

---

## 🔧 Available Make Commands

```bash
make help           # Show all commands
make build-phar     # Build PHAR only
make build-binaries # Build PHAR + attempt native binaries (local)
make test-phar      # Test PHAR
make check-build    # Verify app:build available
make release        # Show release steps
make release-auto V=x.y.z  # Automated release
```

---

## 📦 How Release Works

1. You run `make release-auto V=0.1.0` and push tags
2. GitHub Actions automatically:
   - Builds PHAR on Ubuntu
   - Builds Linux binaries on Ubuntu
   - Builds macOS binaries on macOS runner
   - Creates GitHub Release with all files
3. Users install with:
   ```bash
   curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash
   ```
   The script auto-detects their OS/arch and downloads the right binary.

---

## ✨ Next Steps

Execute the 4 quick commands above and you're done! 🎉

For detailed instructions, see **READY-TO-RELEASE.md**
