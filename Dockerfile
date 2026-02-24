FROM serversideup/php:8.4-cli

# Build arguments for user/group IDs from .env
ARG PUID=1000
ARG PGID=1000

USER root

# Install Docker CLI and dependencies
RUN apt-get update \
    && apt-get install -y \
        git \
        unzip \
        sqlite3 \
        libsodium-dev \
        nano \
        ca-certificates \
        curl \
        gnupg \
        lsb-release \
    && install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg \
    && echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
      tee /etc/apt/sources.list.d/docker.list > /dev/null \
    && apt-get update \
    && apt-get install -y docker-ce-cli docker-compose-plugin \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js (LTS version)
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure npm for global installations (without sudo)
# Create npm directory structure with proper user/group from .env
RUN mkdir -p /var/www/.npm-global /var/www/.npm-cache \
    && chown -R ${PUID}:${PGID} /var/www/.npm-global /var/www/.npm-cache

# Set npm prefix to user-writable directory
ENV NPM_CONFIG_PREFIX=/var/www/.npm-global

# Set npm cache to user-writable directory (fixes EACCES error)
ENV NPM_CONFIG_CACHE=/var/www/.npm-cache

# Add npm global bin to PATH
ENV PATH="/var/www/.npm-global/bin:${PATH}"

# Install PHP extensions
RUN install-php-extensions \
    sodium \
    yaml \
    pcov

WORKDIR /var/www/html

USER www-data

CMD ["tail", "-f", "/dev/null"]
