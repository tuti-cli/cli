#!/bin/bash
# =============================================================================
# WordPress Development Entrypoint Script
# =============================================================================
# This script runs during S6 init in the serversideup/php container (as root).
# It ensures WordPress directories exist with correct ownership so the web
# process (www-data) can install/delete plugins, themes, and upload files.
# =============================================================================

set -e

echo "🔧 Setting up WordPress development environment..."

# Resolve the UID/GID that www-data should own files as
OWNER_UID="${PUID:-1000}"
OWNER_GID="${PGID:-1000}"

# Standard WordPress installation
if [ -d "/var/www/html/wp-content" ] || [ -f "/var/www/html/wp-config.php" ]; then
    # Create writable directories WordPress needs
    mkdir -p /var/www/html/wp-content/uploads
    mkdir -p /var/www/html/wp-content/plugins
    mkdir -p /var/www/html/wp-content/themes
    mkdir -p /var/www/html/wp-content/upgrade

    # Fix ownership so the web process can write (install/delete plugins, upload files)
    chown -R "${OWNER_UID}:${OWNER_GID}" /var/www/html/wp-content

    echo "✅ WordPress directories ready (owner ${OWNER_UID}:${OWNER_GID})"
fi

# Bedrock installation
if [ -d "/var/www/html/web" ]; then
    mkdir -p /var/www/html/web/app/uploads
    chown -R "${OWNER_UID}:${OWNER_GID}" /var/www/html/web/app/uploads
    echo "✅ Bedrock directories ready"
fi

echo "🚀 WordPress development setup complete!"
