#!/bin/bash
# Entrypoint script for Laravel development
# Ensures proper permissions for storage directories

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🐳 tuti-cli Laravel Container Starting...${NC}"

# Ensure we're in the right directory
cd /var/www/html || exit 1

# Function to ensure directory exists with proper permissions
ensure_writable_dir() {
    local dir=$1

    if [ ! -d "$dir" ]; then
        echo -e "${YELLOW}Creating directory: $dir${NC}"
        mkdir -p "$dir"
    fi

    # Set ownership to www-data
    chown -R www-data:www-data "$dir"

    # Set permissions: 775 for directories, 664 for files
    chmod -R 775 "$dir"
}

# Ensure Laravel storage directories exist and are writable
echo -e "${GREEN}Ensuring storage directories are writable...${NC}"

ensure_writable_dir "storage/framework/cache"
ensure_writable_dir "storage/framework/sessions"
ensure_writable_dir "storage/framework/views"
ensure_writable_dir "storage/logs"
ensure_writable_dir "storage/app/public"
ensure_writable_dir "bootstrap/cache"

echo -e "${GREEN}✓ Storage directories configured${NC}"

# Execute the original ServerSideUp entrypoint
exec docker-php-serversideup-entrypoint "$@"
