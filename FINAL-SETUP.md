# Build & Release Workflow

## Local Testing

```bash
# 1. Build PHAR
make build-phar

# 2. Build binaries with phpacker
make build-binary

# 3. Test binary (no PHP required!)
make test-binary

# 4. Install locally
make install-local
~/.tuti/bin/tuti --version
```

## Release

```bash
make version-bump V=0.2.0
git add . && git commit -m "Release v0.2.0"
git tag -a v0.2.0 -m "Release v0.2.0"
git push origin main --tags
```

## What Happens on Release

GitHub Actions will:
1. Build PHAR: `php tuti app:build tuti.phar`
2. Build binaries: `./vendor/bin/phpacker build --src=./builds/tuti.phar --php=8.4 all`
3. Rename binaries: `linux-x64` → `tuti-linux-amd64`
4. Create GitHub Release with all files

## Binary Output

```
builds/build/
├── linux/linux-x64     → tuti-linux-amd64
├── linux/linux-arm64   → tuti-linux-arm64
├── mac/mac-x64         → tuti-darwin-amd64
├── mac/mac-arm64       → tuti-darwin-arm64
└── windows/windows-x64 → tuti-windows-amd64.exe
```

## User Installation

```bash
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash
```

Downloads binary to `~/.tuti/bin/tuti` - works without PHP installed!

---

Delete this file after review:
```bash
rm FINAL-SETUP.md
```
