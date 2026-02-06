#!/usr/bin/env bash

#
# Tuti CLI Installer
#
# Downloads and installs the tuti CLI binary.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash
#
# Options:
#   TUTI_INSTALL_DIR   Override install location (default: ~/.tuti/bin)
#   TUTI_VERSION       Install specific version (default: latest)
#
# Examples:
#   curl -fsSL ... | bash                                    # Latest, default location
#   curl -fsSL ... | TUTI_INSTALL_DIR=/usr/local/bin bash    # Custom location
#   curl -fsSL ... | TUTI_VERSION=0.2.0 bash                 # Specific version
#

set -euo pipefail

readonly INSTALL_DIR="${TUTI_INSTALL_DIR:-$HOME/.tuti/bin}"
readonly GLOBAL_DIR="$HOME/.tuti"
readonly GITHUB_REPO="tuti-cli/cli"
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
die()     { error "$1"; exit 1; }

# --- Platform detection ---

detect_os() {
    case "$(uname -s)" in
        Linux*)  echo "linux" ;;
        Darwin*) echo "mac" ;;
        *)       die "Unsupported OS: $(uname -s). Only Linux and macOS are supported." ;;
    esac
}

detect_arch() {
    case "$(uname -m)" in
        x86_64|amd64)  echo "x64" ;;
        arm64|aarch64) echo "arm" ;;
        *)             die "Unsupported architecture: $(uname -m). Only x64 and ARM are supported." ;;
    esac
}

# Binary name matches phpacker output: tuti-{os}-{arch}
get_artifact_name() {
    echo "tuti-$(detect_os)-$(detect_arch)"
}

# --- Dependency checks ---

check_dependencies() {
    if ! command -v curl &>/dev/null && ! command -v wget &>/dev/null; then
        die "Either curl or wget is required. Install one and try again."
    fi

    if ! command -v docker &>/dev/null; then
        warn "Docker is not installed. You will need it to run tuti stacks."
    fi
}

# --- Download helper ---

download() {
    local url="$1" dest="$2"
    if command -v curl &>/dev/null; then
        curl -fsSL "$url" -o "$dest"
    else
        wget -qO "$dest" "$url"
    fi
}

# --- Fetch JSON field (no jq dependency) ---

json_field() {
    local field="$1"
    grep -o "\"${field}\"[[:space:]]*:[[:space:]]*\"[^\"]*\"" | head -1 | sed 's/.*"\([^"]*\)"$/\1/'
}

# --- Core steps ---

get_version() {
    if [ -n "${TUTI_VERSION:-}" ]; then
        echo "$TUTI_VERSION"
        return
    fi

    local version
    version=$(download "https://api.github.com/repos/${GITHUB_REPO}/releases/latest" - 2>/dev/null | json_field "tag_name" | sed 's/^v//')

    if [ -z "$version" ]; then
        die "Could not determine latest version. Check your internet connection."
    fi
    echo "$version"
}

setup_directories() {
    mkdir -p "$INSTALL_DIR"
    mkdir -p "${GLOBAL_DIR}/logs"
    mkdir -p "${GLOBAL_DIR}/cache"

    if [ ! -f "${GLOBAL_DIR}/config.json" ]; then
        cat > "${GLOBAL_DIR}/config.json" <<'EOF'
{
    "version": "1.0.0",
    "auto_update_stacks": true,
    "telemetry": false,
    "default_environment": "dev"
}
EOF
    fi

    success "Directory structure ready: ${GLOBAL_DIR}"
}

download_binary() {
    local version="$1"
    local artifact
    artifact=$(get_artifact_name)
    local url="https://github.com/${GITHUB_REPO}/releases/download/v${version}/${artifact}"
    local tmp_file
    tmp_file=$(mktemp)

    info "Downloading ${artifact} v${version}..."

    if ! download "$url" "$tmp_file" 2>/dev/null || [ ! -s "$tmp_file" ]; then
        rm -f "$tmp_file"
        die "Download failed: ${url}"
    fi

    mv "$tmp_file" "${INSTALL_DIR}/${BINARY_NAME}"
    chmod +x "${INSTALL_DIR}/${BINARY_NAME}"

    success "Installed: ${INSTALL_DIR}/${BINARY_NAME}"
}

setup_path() {
    # Already in PATH — nothing to do
    if echo "$PATH" | tr ':' '\n' | grep -qx "$INSTALL_DIR"; then
        return
    fi

    local path_line="export PATH=\"\$PATH:${INSTALL_DIR}\""
    local configs=()

    [ -f "$HOME/.zshrc" ]   && configs+=("$HOME/.zshrc")
    [ -f "$HOME/.bashrc" ]  && configs+=("$HOME/.bashrc")
    [ -f "$HOME/.profile" ] && configs+=("$HOME/.profile")

    local added=false
    for rc in "${configs[@]}"; do
        if ! grep -q "$INSTALL_DIR" "$rc" 2>/dev/null; then
            printf '\n# Tuti CLI\n%s\n' "$path_line" >> "$rc"
            success "Added to PATH in $(basename "$rc")"
            added=true
        fi
    done

    # Fish shell
    if [ -f "$HOME/.config/fish/config.fish" ]; then
        if ! grep -q "$INSTALL_DIR" "$HOME/.config/fish/config.fish" 2>/dev/null; then
            printf '\n# Tuti CLI\nset -gx PATH $PATH %s\n' "$INSTALL_DIR" >> "$HOME/.config/fish/config.fish"
            success "Added to PATH in config.fish"
            added=true
        fi
    fi

    if [ "$added" = true ]; then
        echo ""
        warn "Restart your terminal or run: source ~/.bashrc  (or ~/.zshrc)"
    elif [ ${#configs[@]} -eq 0 ]; then
        echo ""
        warn "Add to your PATH manually:"
        echo "  $path_line"
    fi
}

verify() {
    if [ ! -x "${INSTALL_DIR}/${BINARY_NAME}" ]; then
        die "Binary not found or not executable at ${INSTALL_DIR}/${BINARY_NAME}"
    fi

    local output
    if output=$("${INSTALL_DIR}/${BINARY_NAME}" --version 2>&1); then
        success "Verified: ${output}"
    else
        warn "Binary installed but initial test failed. Try restarting your terminal."
        warn "Then run: ${INSTALL_DIR}/${BINARY_NAME} --version"
    fi
}

# --- Main ---

main() {
    echo ""
    echo -e "${BLUE}Tuti CLI Installer${NC}"
    echo ""

    check_dependencies

    local version
    version=$(get_version)
    info "Version: v${version}"
    info "Platform: $(detect_os)-$(detect_arch)"
    info "Install to: ${INSTALL_DIR}"
    echo ""

    setup_directories
    download_binary "$version"
    setup_path
    verify

    echo ""
    success "Installation complete!"
    echo ""
    echo "  tuti --version          Verify installation"
    echo "  tuti stack:laravel      Create a Laravel project"
    echo "  tuti stack:wordpress    Create a WordPress project"
    echo ""
}

main
