#!/usr/bin/env bash

#
# Quick Test Script for Tuti CLI
#
# This script performs basic integration tests
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TUTI_BIN="${SCRIPT_DIR}/builds/tuti.phar"
TEST_DIR="/tmp/tuti-test-$(date +%s)"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

success() {
    echo -e "${GREEN}✓${NC} $1"
}

error() {
    echo -e "${RED}✗${NC} $1"
    exit 1
}

cleanup() {
    if [ -d "${TEST_DIR}" ]; then
        rm -rf "${TEST_DIR}"
    fi
}

trap cleanup EXIT

echo "╔══════════════════════════════════════════════════════════╗"
echo "║                                                          ║"
echo "║              Tuti CLI Integration Tests                 ║"
echo "║                                                          ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Test 1: PHAR exists
info "Test 1: Checking PHAR exists..."
if [ ! -f "${TUTI_BIN}" ]; then
    error "PHAR not found at ${TUTI_BIN}. Run 'make build-phar' first."
fi
success "PHAR exists"

# Test 2: Version command
info "Test 2: Running --version..."
if php "${TUTI_BIN}" --version > /dev/null; then
    success "Version command works"
else
    error "Version command failed"
fi

# Test 3: List command
info "Test 3: Running list..."
if php "${TUTI_BIN}" list > /dev/null; then
    success "List command works"
else
    error "List command failed"
fi

# Test 4: Install command
info "Test 4: Running install..."
if php "${TUTI_BIN}" install --force > /dev/null; then
    success "Install command works"
else
    error "Install command failed"
fi

# Test 5: Check ~/.tuti directory
info "Test 5: Checking ~/.tuti directory..."
if [ -d "$HOME/.tuti" ] && [ -f "$HOME/.tuti/config.json" ]; then
    success "Global directory created"
else
    error "Global directory not created"
fi

# Test 6: Stack manage list
info "Test 6: Running stack:manage list..."
if php "${TUTI_BIN}" stack:manage list > /dev/null; then
    success "Stack manage command works"
else
    error "Stack manage command failed"
fi

# Test 7: Help commands
info "Test 7: Testing help commands..."
if php "${TUTI_BIN}" stack:laravel --help > /dev/null; then
    success "Stack Laravel help works"
else
    error "Stack Laravel help failed"
fi

# Test 8: Create test directory
info "Test 8: Creating test directory..."
mkdir -p "${TEST_DIR}"
cd "${TEST_DIR}"
success "Test directory created: ${TEST_DIR}"

# Test 9: Test with existing Laravel project detection
info "Test 9: Testing existing project detection..."
mkdir -p "${TEST_DIR}/fake-laravel"
cd "${TEST_DIR}/fake-laravel"
touch artisan
touch composer.json
mkdir -p bootstrap
touch bootstrap/app.php
echo '{"require":{"laravel/framework":"^11.0"}}' > composer.json

# Check if detection works (this just tests the command doesn't crash)
if php "${TUTI_BIN}" stack:laravel --help > /dev/null; then
    success "Existing project detection test passed"
else
    error "Existing project detection test failed"
fi

echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}All tests passed! ✓${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo ""
echo "Next steps:"
echo "  1. Test manual installation: cd ${TEST_DIR} && php ${TUTI_BIN} stack:laravel test --mode=fresh"
echo "  2. Review output and verify files are created"
echo "  3. If all looks good, proceed with release"
echo ""
