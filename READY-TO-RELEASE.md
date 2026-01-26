# 🚀 Testing and Release - Final Commands

## ✅ All Files Ready!

Structure:
- ✅ `Makefile` - updated for Docker with binary builds
- ✅ `config/commands.php` - BuildCommand uncommented
- ✅ `.github/workflows/release.yml` - Builds PHAR + native binaries (Linux/macOS)
- ✅ `scripts/install.sh` - Smart installer (detects platform, downloads binary or PHAR)
- ✅ `docs/RELEASE-PROCESS.md` - documentation

---

## 📦 What Gets Built on Release

GitHub Actions will build:
- **tuti.phar** - PHAR file (requires PHP 8.4+)
- **tuti-linux-amd64** - Linux x64 binary
- **tuti-linux-arm64** - Linux ARM64 binary
- **tuti-darwin-amd64** - macOS Intel binary
- **tuti-darwin-arm64** - macOS Apple Silicon binary

---

## 📝 Commands to Execute

### Step 1: Verify app:build is available

```bash
docker compose exec app php tuti list | grep app:
```

**Expected result:** Should show `app:build`

### Step 2: Build PHAR

```bash
make build-phar
```

**Or directly:**
```bash
docker compose exec app php -d phar.readonly=0 tuti app:build tuti --build-version=0.1.0
```

### Step 3: Test PHAR

```bash
make test-phar
```

**Or directly:**
```bash
docker compose exec app php builds/tuti --version
docker compose exec app php builds/tuti list | head -20
docker compose exec app php builds/tuti install --force
```

### Step 4: If tests pass - make release

```bash
# Automated release
make release-auto V=0.1.0

# Then
git push origin main --tags
```

---

## 🎯 Alternative: Manual Release

```bash
# 1. Update version
make version-bump V=0.1.0

# 2. Build PHAR
make build-phar

# 3. Test
make test-phar

# 4. Commit
git add .
git commit -m "Release v0.1.0"

# 5. Create tag
git tag -a v0.1.0 -m "Release v0.1.0"

# 6. Push
git push origin main --tags
```

---

## 🔍 What Happens After Push Tags

GitHub Actions will automatically:
1. Build PHAR on GitHub runners
2. Test it
3. Create Release at https://github.com/tuti-cli/cli/releases
4. Attach files:
   - `tuti.phar`
   - Native binaries for Linux/macOS
   - `scripts/install.sh`

---

## 📦 Users Can Install

```bash
# Quick install
curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash

# Or directly
wget https://github.com/tuti-cli/cli/releases/download/v0.1.0/tuti.phar
chmod +x tuti.phar
sudo mv tuti.phar /usr/local/bin/tuti
tuti install
```

---

## ❓ Troubleshooting

### If app:build not found
Check `config/commands.php` - `BuildCommand` should be commented out in the `hidden` array (as it is now).

### If PHAR doesn't build
```bash
# Try directly in container
docker compose exec app bash
php -d phar.readonly=0 tuti app:build tuti --build-version=0.1.0
exit
```

### If you need help
```bash
make help
docker compose exec app php tuti --help
```

---

## ✨ Ready!

Now just execute commands from **Step 1-4** and everything will work! 🎉
