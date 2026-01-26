# Local Testing & Release Workflow

## Build & Test Locally

```bash
# 1. Build PHAR
make build-phar
make test-phar

# 2. Build binaries (uses phpacker - downloads PHP runtime)
make build-binary

# 3. Test binary - NO PHP REQUIRED!
make test-binary

# 4. Install locally
make install-local

# 5. Add to PATH and test
echo 'export PATH="$PATH:$HOME/.tuti/bin"' >> ~/.bashrc
source ~/.bashrc
tuti --version
```

## Release (After Local Testing Passes)

```bash
make version-bump V=0.1.1
git add . && git commit -m "Release v0.1.1"
git tag -a v0.1.1 -m "Release v0.1.1"
git push origin main --tags
```

## Binary Output

phpacker creates:
- `builds/build/linux/linux-x64`
- `builds/build/linux/linux-arm64`
- `builds/build/mac/mac-x64`
- `builds/build/mac/mac-arm64`
- `builds/build/windows/windows-x64.exe`

## Clean Up

Delete this file after reading:
```bash
rm -f FINAL-SETUP.md
```
