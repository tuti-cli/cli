#!/usr/bin/env bash

#
# Tuti CLI Uninstaller
#
# Removes tuti binary, PATH entries, and optionally the ~/.tuti data directory.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/uninstall.sh | bash
#
# Options:
#   --purge    Also remove ~/.tuti directory (logs, cache, config) without prompting
#

set -euo pipefail

readonly INSTALL_DIR="${TUTI_INSTALL_DIR:-$HOME/.tuti/bin}"
readonly GLOBAL_DIR="$HOME/.tuti"
readonly BINARY_NAME="tuti"

# --- Output helpers ---

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info()    { echo -e "${BLUE}>${NC} $1"; }
success() { echo -e "${GREEN}+${NC} $1"; }
warn()    { echo -e "${YELLOW}!${NC} $1"; }
error()   { echo -e "${RED}x${NC} $1" >&2; }

# --- Remove binary ---

remove_binary() {
    local found=false

    # Default location
    if [ -f "${INSTALL_DIR}/${BINARY_NAME}" ]; then
        rm -f "${INSTALL_DIR}/${BINARY_NAME}"
        success "Removed: ${INSTALL_DIR}/${BINARY_NAME}"
        found=true
    fi

    # Check common alternative locations
    for dir in /usr/local/bin /usr/bin; do
        if [ -f "${dir}/${BINARY_NAME}" ]; then
            info "Found tuti at ${dir}/${BINARY_NAME}"
            if [ -w "${dir}/${BINARY_NAME}" ]; then
                rm -f "${dir}/${BINARY_NAME}"
                success "Removed: ${dir}/${BINARY_NAME}"
            else
                warn "Run with sudo to remove: sudo rm ${dir}/${BINARY_NAME}"
            fi
            found=true
        fi
    done

    if [ "$found" = false ]; then
        warn "No tuti binary found in expected locations."
    fi

    # Clean up empty bin directory
    if [ -d "$INSTALL_DIR" ] && [ -z "$(ls -A "$INSTALL_DIR" 2>/dev/null)" ]; then
        rmdir "$INSTALL_DIR" 2>/dev/null || true
    fi
}

# --- Remove PATH entries from shell configs ---

remove_path_entries() {
    local configs=(
        "$HOME/.bashrc"
        "$HOME/.zshrc"
        "$HOME/.profile"
        "$HOME/.config/fish/config.fish"
    )
    local cleaned=false

    for rc in "${configs[@]}"; do
        if [ -f "$rc" ] && grep -q "$INSTALL_DIR" "$rc" 2>/dev/null; then
            local tmp_file
            tmp_file=$(mktemp)
            { grep -v "# Tuti CLI" "$rc" || true; } | { grep -v "$INSTALL_DIR" || true; } > "$tmp_file"
            mv "$tmp_file" "$rc"
            success "Cleaned PATH from $(basename "$rc")"
            cleaned=true
        fi
    done

    if [ "$cleaned" = false ]; then
        info "No PATH entries found in shell configs."
    fi
}

# --- Remove global data directory ---

remove_global_dir() {
    local purge="$1"

    if [ ! -d "$GLOBAL_DIR" ]; then
        info "No ${GLOBAL_DIR} directory found."
        return
    fi

    if [ "$purge" = true ]; then
        rm -rf "$GLOBAL_DIR"
        success "Removed: ${GLOBAL_DIR}"
        return
    fi

    # Interactive prompt (only works when not piped)
    if [ -t 0 ]; then
        echo ""
        info "Found data directory: ${GLOBAL_DIR}"
        read -p "  Remove it? This deletes logs, cache, and config. [y/N] " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            rm -rf "$GLOBAL_DIR"
            success "Removed: ${GLOBAL_DIR}"
        else
            info "Kept: ${GLOBAL_DIR}"
        fi
    else
        # Piped execution — don't remove data dir without explicit --purge
        warn "Kept ${GLOBAL_DIR} (use --purge to remove, or run interactively)"
    fi
}

# --- Main ---

main() {
    local purge=false
    for arg in "$@"; do
        case "$arg" in
            --purge) purge=true ;;
        esac
    done

    echo ""
    echo -e "${BLUE}Tuti CLI Uninstaller${NC}"
    echo ""

    remove_binary
    remove_path_entries
    remove_global_dir "$purge"

    echo ""
    success "Uninstall complete."
    echo ""
}

main "$@"
