#!/bin/sh
# Entrypoint script for Laravel development
# Ensures proper permissions for storage directories
# This script runs before S6 services start

set -e

echo "🐳 tuti-cli: Setting up Laravel storage permissions..."

# Ensure we're in the right directory
cd /var/www/html || exit 1

# Function to ensure directory exists with proper permissions
ensure_writable_dir() {
    local dir=$1

    if [ ! -d "$dir" ]; then
        echo "📁 Creating directory: $dir"
        mkdir -p "$dir"
    fi

    # Set ownership to www-data
    chown -R www-data:www-data "$dir"

    # Set permissions: 775 for directories
    chmod -R 775 "$dir"
}

# Ensure Laravel storage directories exist and are writable
ensure_writable_dir "storage/framework/cache"
ensure_writable_dir "storage/framework/sessions"
ensure_writable_dir "storage/framework/views"
ensure_writable_dir "storage/logs"
ensure_writable_dir "storage/app/public"
ensure_writable_dir "bootstrap/cache"

echo "✅ Storage directories configured"

# Exit successfully - S6 will continue with service startup
exit 0
