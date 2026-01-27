#!/usr/bin/env bash

#
# Tuti CLI Installer
#
# This script installs tuti CLI and sets up the required directory structure.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash
#
# Or:
#   wget -qO- https://raw.githubusercontent.com/tuti-cli/cli/main/scripts/install.sh | bash
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default installation paths
INSTALL_DIR="${TUTI_INSTALL_DIR:-$HOME/.tuti/bin}"
GLOBAL_TUTI_DIR="${HOME}/.tuti"
GITHUB_REPO="tuti-cli/cli"
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
    # phpacker creates: tuti-linux-amd64, tuti-linux-arm64, tuti-darwin-amd64, tuti-darwin-arm64
    echo "tuti-${platform}"
}

# Check for required tools
check_dependencies() {
    local missing=()

    if ! command -v curl &> /dev/null && ! command -v wget &> /dev/null; then
        missing+=("curl or wget")
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
        error "Could not determine latest version. Please check your internet connection."
        exit 1
    fi

    info "Latest version: v${version}"
    info "Platform: ${platform}"

    # Construct download URL for self-contained binary
    download_url="https://github.com/${GITHUB_REPO}/releases/download/v${version}/${binary_name}"

    info "Downloading: ${binary_name}"

    # Download binary
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
        mv "${tmp_file}" "${INSTALL_DIR}/${BINARY_NAME}"
        chmod +x "${INSTALL_DIR}/${BINARY_NAME}"
        success "Installed to: ${INSTALL_DIR}/${BINARY_NAME}"
    else
        error "Failed to download binary for ${platform}"
        error "URL: ${download_url}"
        rm -f "${tmp_file}" 2>/dev/null
        exit 1
    fi
}

# Add to PATH if needed
setup_path() {
    local shell_configs=()
    local path_export="export PATH=\"\$PATH:${INSTALL_DIR}\""
    local path_added=false

    # Detect available shell config files (2026 modern shells)
    [ -f "$HOME/.zshrc" ] && shell_configs+=("$HOME/.zshrc")
    [ -f "$HOME/.bashrc" ] && shell_configs+=("$HOME/.bashrc")
    [ -f "$HOME/.config/fish/config.fish" ] && shell_configs+=("$HOME/.config/fish/config.fish")
    [ -f "$HOME/.config/nushell/config.nu" ] && shell_configs+=("$HOME/.config/nushell/config.nu")
    [ -f "$HOME/.profile" ] && shell_configs+=("$HOME/.profile")

    # Check if already in PATH
    if echo "$PATH" | grep -q "${INSTALL_DIR}"; then
        success "Tuti CLI directory already in PATH"
        return
    fi

    # Try to add to detected shell configs
    for config in "${shell_configs[@]}"; do
        if [ -f "$config" ]; then
            # Check if already added to this config
            if ! grep -q "${INSTALL_DIR}" "$config" 2>/dev/null; then
                echo "" >> "$config"
                echo "# Tuti CLI" >> "$config"

                # Handle different shell syntaxes
                case "$config" in
                    *config.fish)
                        echo "set -gx PATH \$PATH ${INSTALL_DIR}" >> "$config"
                        ;;
                    *config.nu)
                        echo "\$env.PATH = (\$env.PATH | split row (char esep) | append '${INSTALL_DIR}')" >> "$config"
                        ;;
                    *)
                        echo "$path_export" >> "$config"
                        ;;
                esac

                success "Added to PATH in $(basename "$config")"
                path_added=true
            else
                success "Already configured in $(basename "$config")"
                path_added=true
            fi
        fi
    done

    if [ "$path_added" = false ]; then
        warn "Could not detect shell config file automatically."
        echo ""
        echo "Please add Tuti CLI to your PATH manually:"
        echo ""
        echo "For Bash/Zsh:"
        echo "  echo 'export PATH=\"\$PATH:${INSTALL_DIR}\"' >> ~/.bashrc"
        echo "  source ~/.bashrc"
        echo ""
        echo "For Fish:"
        echo "  echo 'set -gx PATH \$PATH ${INSTALL_DIR}' >> ~/.config/fish/config.fish"
        echo ""
        echo "For Nushell:"
        echo "  echo '\$env.PATH = (\$env.PATH | split row (char esep) | append \"${INSTALL_DIR}\")' >> ~/.config/nushell/config.nu"
        echo ""
    else
        echo ""
        warn "Please restart your terminal or run one of these commands:"
        echo "  source ~/.bashrc    # For Bash"
        echo "  source ~/.zshrc     # For Zsh"
        echo "  exec fish           # For Fish"
        echo "  exec nu             # For Nushell"
        echo ""
    fi
}

# Verify installation
verify_installation() {
    info "Verifying installation..."

    if [ -x "${INSTALL_DIR}/${BINARY_NAME}" ]; then
        success "Tuti CLI installed successfully!"
        echo ""

        # Try to run version command and check for issues
        local version_output
        if version_output=$("${INSTALL_DIR}/${BINARY_NAME}" --version 2>&1); then
            echo "$version_output"
            success "Binary is working correctly!"
        else
            error "Binary execution failed:"
            echo "$version_output"
            echo ""

            # Check if it's a PHP dependency issue
            if echo "$version_output" | grep -q "php.*No such file"; then
                error "The binary requires PHP but PHP is not installed or not in PATH"
                echo ""
                echo "This suggests the binary is not truly self-contained."
                echo "Please install PHP or use a different installation method."
                echo ""
                echo "To install PHP on Ubuntu/Debian:"
                echo "  sudo apt update && sudo apt install php-cli"
                echo ""
                echo "To install PHP on macOS:"
                echo "  brew install php"
                echo ""
                exit 1
            else
                warn "Binary installed but failed initial test. You may need to:"
                echo "1. Restart your terminal"
                echo "2. Run: source ~/.bashrc (or your shell config)"
                echo "3. Try: ${INSTALL_DIR}/${BINARY_NAME} --version"
            fi
        fi
    else
        error "Installation verification failed - binary not executable"
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
            echo "  TUTI_INSTALL_DIR   Installation directory (default: ~/.tuti/bin)"
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
