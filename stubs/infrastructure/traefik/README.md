# tuti-cli Traefik Infrastructure

This directory contains the global Traefik reverse proxy configuration that routes traffic to all tuti-cli managed projects.

## Features

- **Automatic HTTPS**: All local projects get HTTPS with self-signed certificates
- **Wildcard routing**: Projects use subdomains like `myapp.local.test`
- **Dashboard**: Access Traefik dashboard at `https://traefik.local.test`

## Setup

### 1. Add local domain to /etc/hosts

```bash
# Add this line to /etc/hosts (Linux/Mac) or C:\Windows\System32\drivers\etc\hosts (Windows)
127.0.0.1 traefik.local.test
```

For wildcard support, consider using dnsmasq or similar.

### 2. Install mkcert (recommended)

For trusted local certificates:

```bash
# Mac
brew install mkcert
mkcert -install

# Linux
apt install mkcert
mkcert -install

# Generate certificates
cd certs/
mkcert -cert-file local-cert.pem -key-file local-key.pem "*.local.test" localhost 127.0.0.1
```

### 3. Start Traefik

This is handled automatically by tuti-cli:

```bash
tuti install
```

Or manually:

```bash
docker network create traefik_proxy
docker compose up -d
```

## Files

- `docker-compose.yml` - Main Traefik configuration
- `dynamic/tls.yml` - TLS and middleware configuration
- `certs/` - SSL certificates
- `secrets/users` - Dashboard authentication

## Dashboard Access

- URL: https://traefik.local.test
- Default credentials are generated during installation (see `.env` file)
