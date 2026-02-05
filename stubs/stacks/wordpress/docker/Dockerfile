# syntax=docker/dockerfile:1.4

# WordPress Application Dockerfile
# Using ServerSideUp PHP images with Apache (recommended for WordPress)
# Docs: https://serversideup.net/open-source/docker-php/

ARG PHP_VERSION=8.3

########################################
# Development Stage
########################################
# For development, we use serversideup/php directly with volume mounts
# No build stages needed - WordPress files are mounted from host
FROM serversideup/php:${PHP_VERSION}-fpm-apache AS development

LABEL maintainer="tuti-cli"
LABEL description="WordPress Application Container - Development"

# Set working directory
WORKDIR /var/www/html

# Switch to root for installations
USER root

# Build arguments for user and group IDs (for permission matching)
ARG USER_ID=1000
ARG GROUP_ID=1000

# Use ServerSideUp's method to change UID/GID of www-data
# This ensures proper file permissions with mounted volumes
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} && \
    docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID}

# Install additional system dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        mariadb-client \
        git \
        unzip \
        vim \
        curl \
        less \
    && rm -rf /var/lib/apt/lists/*

# Install additional PHP extensions commonly needed for WordPress
RUN install-php-extensions \
    mysqli \
    pdo_mysql \
    redis \
    imagick \
    intl \
    opcache \
    exif \
    zip \
    gd

# Note: WP-CLI is NOT installed here intentionally.
# Use the separate wordpress:cli-php8.3 container for CLI commands.
# This keeps the app container lean and focused on serving WordPress.

# Install Composer (for Bedrock installations)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP development settings
ENV PHP_OPCACHE_ENABLE=0 \
    PHP_DISPLAY_ERRORS=1 \
    PHP_DISPLAY_STARTUP_ERRORS=1 \
    PHP_ERROR_REPORTING="E_ALL" \
    SSL_MODE=off

# Copy custom entrypoint script
COPY --chmod=755 .tuti/scripts/entrypoint-dev.sh /etc/entrypoint.d/50-wordpress-setup.sh

USER www-data

WORKDIR /var/www/html

########################################
# Production Stage
########################################
FROM serversideup/php:${PHP_VERSION}-fpm-apache AS production

LABEL maintainer="tuti-cli"
LABEL description="WordPress Application Container - Production"

WORKDIR /var/www/html

USER root

# Install system dependencies (minimal for production)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    mysqli \
    pdo_mysql \
    redis \
    imagick \
    intl \
    opcache \
    exif \
    zip \
    gd


# Copy WordPress files (for immutable deployments)
COPY --chown=www-data:www-data . .

# Set production PHP settings
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=256 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=20000 \
    AUTORUN_ENABLED=true

# Create health check endpoint
RUN mkdir -p /var/www/html && echo '{"status":"ok"}' > /var/www/html/health.json

USER www-data
