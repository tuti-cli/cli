# syntax=docker/dockerfile:1.4

# Laravel Application Dockerfile
# Using ServerSideUp PHP images
# Docs: https://serversideup.net/open-source/docker-php/

ARG PHP_VERSION=8.4
ARG VARIANT=fpm-nginx

########################################
# Base Stage
########################################
FROM serversideup/php:${PHP_VERSION}-${VARIANT} AS base

LABEL maintainer="tuti-cli"
LABEL description="Laravel Application Container"

# Set working directory
WORKDIR /var/www/html

# Switch to root for installations
USER root

# Install additional system dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        postgresql-client \
        rsync \
    && rm -rf /var/lib/apt/lists/*

# Install additional PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    intl \
    opcache \
    pcntl \
    bcmath \
    gd

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Switch back to www-data
USER www-data

########################################
# Development Stage
########################################
FROM base AS development

USER root

# Build arguments for user and group IDs (for permission matching)
ARG USER_ID=1000
ARG GROUP_ID=1000

# Modify www-data user to match host user
RUN usermod -u ${USER_ID} www-data \
    && groupmod -g ${GROUP_ID} www-data

# Install development tools
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        vim \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js for asset compilation
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# PHP development settings
ENV PHP_OPCACHE_ENABLE=0 \
    PHP_DISPLAY_ERRORS=1 \
    PHP_DISPLAY_STARTUP_ERRORS=1 \
    PHP_ERROR_REPORTING="E_ALL"

USER www-data

WORKDIR /var/www/html

########################################
# Production Dependencies Stage
########################################
FROM base AS vendor

WORKDIR /var/www/html

# Copy composer files first for better caching
COPY --chown=www-data:www-data composer.json composer.lock ./

# Install dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

########################################
# Production Build Stage
########################################
FROM base AS production

WORKDIR /var/www/html

# Copy vendor from vendor stage
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor

# Copy application code
COPY --chown=www-data:www-data . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# Cache configuration
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Set production PHP settings
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=256 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=20000 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true

# Create health check endpoint
RUN mkdir -p public && echo '{"status":"ok"}' > public/health

USER www-data

# Default command handled by base image
