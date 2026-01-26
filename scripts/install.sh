#!/usr/bin/env bash

#
# Tuti CLI Installer
#
# This script installs tuti CLI and sets up the required directory structure.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/tuti-cli/tuti-cli/main/scripts/install.sh | bash
#
# Or:
#   wget -qO- https://raw.githubusercontent.com/tuti-cli/tuti-cli/main/scripts/install.sh | bash
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default installation paths
INSTALL_DIR="${TUTI_INSTALL_DIR:-$HOME/.local/bin}"
GLOBAL_TUTI_DIR="${HOME}/.tuti"
GITHUB_REPO="tuti-cli/tuti-cli"
BINARY_NAME="tuti"

print_banner() {
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║                                                          ║"
    echo "║                 🚀 Tuti CLI Installer                    ║"
    echo "║                                                          ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

success() {
    echo -e "${GREEN}✓${NC} $1"
}

warn() {
    echo -e "${YELLOW}⚠${NC} $1"
}

error() {
    echo -e "${RED}✗${NC} $1"
}

# Detect OS and architecture
detect_platform() {
    local os=""
    local arch=""

    case "$(uname -s)" in
        Linux*)     os="linux";;
        Darwin*)    os="darwin";;
        MINGW*|MSYS*|CYGWIN*) os="windows";;
        *)          error "Unsupported operating system: $(uname -s)"; exit 1;;
    esac

    case "$(uname -m)" in
        x86_64|amd64)   arch="amd64";;
        arm64|aarch64)  arch="arm64";;
        i386|i686)      arch="386";;
        *)              error "Unsupported architecture: $(uname -m)"; exit 1;;
    esac

    echo "${os}-${arch}"
}

# Get the binary filename for the current platform
get_binary_name() {
    local platform=$(detect_platform)
    echo "tuti-${platform}"
}

# Check for required tools
check_dependencies() {
    local missing=()

    if ! command -v curl &> /dev/null && ! command -v wget &> /dev/null; then
        missing+=("curl or wget")
    fi

    if ! command -v git &> /dev/null; then
        warn "Git is not installed. Some features may not work."
    fi

    if ! command -v docker &> /dev/null; then
        warn "Docker is not installed. You'll need it to run stacks."
    fi

    if [ ${#missing[@]} -ne 0 ]; then
        error "Missing required dependencies: ${missing[*]}"
        echo "Please install them and try again."
        exit 1
    fi
}

# Create global .tuti directory structure
setup_global_directory() {
    info "Setting up global tuti directory..."

    # Create main directory
    mkdir -p "${GLOBAL_TUTI_DIR}"

    # Create subdirectories
    mkdir -p "${GLOBAL_TUTI_DIR}/stacks"
    mkdir -p "${GLOBAL_TUTI_DIR}/cache"
    mkdir -p "${GLOBAL_TUTI_DIR}/logs"

    # Create global config if not exists
    if [ ! -f "${GLOBAL_TUTI_DIR}/config.json" ]; then
        cat > "${GLOBAL_TUTI_DIR}/config.json" << 'EOF'
{
    "version": "1.0.0",
    "auto_update_stacks": true,
    "telemetry": false,
    "default_environment": "dev"
}
EOF
    fi

    success "Global directory created: ${GLOBAL_TUTI_DIR}"
}

# Get latest release version
get_latest_version() {
    local version=""
    if command -v curl &> /dev/null; then
        version=$(curl -fsSL "https://api.github.com/repos/${GITHUB_REPO}/releases/latest" | grep '"tag_name":' | sed -E 's/.*"v([^"]+)".*/\1/')
    else
        version=$(wget -qO- "https://api.github.com/repos/${GITHUB_REPO}/releases/latest" | grep '"tag_name":' | sed -E 's/.*"v([^"]+)".*/\1/')
    fi
    echo "${version}"
}

# Download and install the binary
install_binary() {
    info "Downloading tuti CLI..."

    # Create install directory if not exists
    mkdir -p "${INSTALL_DIR}"

    local platform=$(detect_platform)
    local binary_name=$(get_binary_name)
    local version=$(get_latest_version)
    local download_url=""
    local tmp_file=$(mktemp)

    if [ -z "${version}" ]; then
        warn "Could not determine latest version"
        info "Falling back to PHAR installation..."
        install_phar
        return
    fi

    info "Latest version: v${version}"
    info "Platform: ${platform}"

    # Construct download URL for native binary
    download_url="https://github.com/${GITHUB_REPO}/releases/download/v${version}/${binary_name}"

    info "Trying native binary: ${binary_name}"

    # Try to download native binary
    local download_success=false
    if command -v curl &> /dev/null; then
        if curl -fsSL "${download_url}" -o "${tmp_file}" 2>/dev/null; then
            download_success=true
        fi
    else
        if wget -qO "${tmp_file}" "${download_url}" 2>/dev/null; then
            download_success=true
        fi
    fi

    if [ "${download_success}" = true ] && [ -s "${tmp_file}" ]; then
        # Verify it's not an error page (check if it starts with ELF or Mach-O magic)
        if file "${tmp_file}" 2>/dev/null | grep -qE "(executable|Mach-O)"; then
            mv "${tmp_file}" "${INSTALL_DIR}/${BINARY_NAME}"
            chmod +x "${INSTALL_DIR}/${BINARY_NAME}"
            success "Native binary installed to: ${INSTALL_DIR}/${BINARY_NAME}"
            return
        fi
    fi

    warn "Native binary not available for ${platform}"
    info "Falling back to PHAR installation..."
    rm -f "${tmp_file}" 2>/dev/null
    install_phar
}

# Fallback: Install PHAR version
install_phar() {
    info "Installing PHAR version (requires PHP 8.4+)..."

    # Check for PHP
    if ! command -v php &> /dev/null; then
        error "PHP is required for PHAR installation but not found"
        error "Please install PHP 8.4+ or wait for native binary support for your platform"
        exit 1
    fi

    local version=$(get_latest_version)
    local phar_url="https://github.com/${GITHUB_REPO}/releases/download/v${version}/tuti.phar"
    local tmp_file=$(mktemp)

    if [ -z "${version}" ]; then
        phar_url="https://github.com/${GITHUB_REPO}/releases/latest/download/tuti.phar"
    fi

    info "Downloading PHAR from: ${phar_url}"

    if command -v curl &> /dev/null; then
        curl -fsSL "${phar_url}" -o "${tmp_file}"
    else
        wget -qO "${tmp_file}" "${phar_url}"
    fi

    if [ ! -s "${tmp_file}" ]; then
        error "Failed to download PHAR file"
        exit 1
    fi

    # Install PHAR directly (it's executable)
    mv "${tmp_file}" "${INSTALL_DIR}/${BINARY_NAME}"
    chmod +x "${INSTALL_DIR}/${BINARY_NAME}"

    success "PHAR installed to: ${INSTALL_DIR}/${BINARY_NAME}"
}

# Add to PATH if needed
setup_path() {
    local shell_config=""
    local path_export="export PATH=\"\${PATH}:${INSTALL_DIR}\""

    # Detect shell config file
    if [ -n "$ZSH_VERSION" ] || [ -f "$HOME/.zshrc" ]; then
        shell_config="$HOME/.zshrc"
    elif [ -n "$BASH_VERSION" ] || [ -f "$HOME/.bashrc" ]; then
        shell_config="$HOME/.bashrc"
    elif [ -f "$HOME/.profile" ]; then
        shell_config="$HOME/.profile"
    fi

    # Check if already in PATH
    if echo "$PATH" | grep -q "${INSTALL_DIR}"; then
        return
    fi

    if [ -n "$shell_config" ]; then
        # Check if already added to config
        if ! grep -q "tuti" "$shell_config" 2>/dev/null; then
            echo "" >> "$shell_config"
            echo "# Tuti CLI" >> "$shell_config"
            echo "${path_export}" >> "$shell_config"
            info "Added to PATH in ${shell_config}"
            warn "Please restart your terminal or run: source ${shell_config}"
        fi
    else
        warn "Could not detect shell config file."
        echo "Please add the following to your shell config:"
        echo "  ${path_export}"
    fi
}

# Verify installation
verify_installation() {
    info "Verifying installation..."

    if [ -x "${INSTALL_DIR}/${BINARY_NAME}" ]; then
        success "Tuti CLI installed successfully!"
        echo ""

        # Try to run version command
        if "${INSTALL_DIR}/${BINARY_NAME}" --version 2>/dev/null; then
            echo ""
        fi
    else
        error "Installation verification failed"
        exit 1
    fi
}

# Print next steps
print_next_steps() {
    echo ""
    echo -e "${GREEN}Installation complete!${NC}"
    echo ""
    echo "Next steps:"
    echo "  1. Restart your terminal or run: source ~/.bashrc (or ~/.zshrc)"
    echo "  2. Verify installation: tuti --version"
    echo "  3. Initialize a project: tuti init"
    echo "  4. Or use Laravel stack: tuti stack:laravel myapp"
    echo ""
    echo "Documentation: https://github.com/${GITHUB_REPO}"
    echo ""
}

# Uninstall function
uninstall() {
    info "Uninstalling tuti CLI..."

    if [ -f "${INSTALL_DIR}/${BINARY_NAME}" ]; then
        rm -f "${INSTALL_DIR}/${BINARY_NAME}"
        success "Removed: ${INSTALL_DIR}/${BINARY_NAME}"
    fi

    if [ -f "${INSTALL_DIR}/tuti.phar" ]; then
        rm -f "${INSTALL_DIR}/tuti.phar"
        success "Removed: ${INSTALL_DIR}/tuti.phar"
    fi

    echo ""
    read -p "Remove global tuti directory (${GLOBAL_TUTI_DIR})? [y/N] " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        rm -rf "${GLOBAL_TUTI_DIR}"
        success "Removed: ${GLOBAL_TUTI_DIR}"
    fi

    success "Uninstallation complete"
}

# Main installation flow
main() {
    print_banner

    # Parse arguments
    case "${1:-}" in
        --uninstall|-u)
            uninstall
            exit 0
            ;;
        --help|-h)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --uninstall, -u    Uninstall tuti CLI"
            echo "  --help, -h         Show this help message"
            echo ""
            echo "Environment variables:"
            echo "  TUTI_INSTALL_DIR   Installation directory (default: ~/.local/bin)"
            echo ""
            exit 0
            ;;
    esac

    info "Starting installation..."
    echo ""

    check_dependencies
    setup_global_directory
    install_binary
    setup_path
    verify_installation
    print_next_steps
}

main "$@"
