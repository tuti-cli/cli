#!/bin/bash
# =============================================================================
# WordPress Development Entrypoint Script
# =============================================================================
# This script runs before S6 services start in the serversideup/php container.
# It sets up WordPress development environment.
# =============================================================================

set -e

echo "🔧 Setting up WordPress development environment..."

# Ensure proper directory permissions for WordPress
if [ -d "/var/www/html" ]; then
    # Create wp-content directories if they don't exist
    mkdir -p /var/www/html/wp-content/uploads
    mkdir -p /var/www/html/wp-content/plugins
    mkdir -p /var/www/html/wp-content/themes
    mkdir -p /var/www/html/wp-content/upgrade

    echo "✅ WordPress directories ready"
fi

# For Bedrock installations, check for web directory
if [ -d "/var/www/html/web" ]; then
    mkdir -p /var/www/html/web/app/uploads
    echo "✅ Bedrock directories ready"
fi

echo "🚀 WordPress development setup complete!"
