# Security Policy

## Supported Versions

We release patches for security vulnerabilities for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | Supported          |

## Reporting a Vulnerability

We take security vulnerabilities seriously. If you discover a security vulnerability within Tuti CLI, please report it privately.

### How to Report

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via GitHub Security Advisories:

1. Go to [Security Advisories](https://github.com/tuti-cli/cli/security/advisories/new)
2. Click "Report a vulnerability"
3. Fill in the details

### What to Include

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if available)

### Response Timeline

- **Initial Response**: Within 48 hours
- **Status Update**: Within 7 days
- **Resolution**: Depends on severity and complexity

### Disclosure Policy

- We will confirm the vulnerability
- We will release a patch as soon as possible
- We will publish a security advisory on GitHub
- We will credit you (unless you prefer to remain anonymous)

## Security Best Practices

When using Tuti CLI:

1. **Keep updated**: Always use the latest version
2. **Review configurations**: Check generated docker-compose files
3. **Secure environment**: Don't commit `.env` files with secrets
4. **Limit permissions**: Run Docker with minimal required permissions

## Known Security Considerations

### Docker Socket Access

Tuti CLI interacts with Docker, which requires access to the Docker socket. This is necessary for:
- Starting/stopping containers
- Managing Docker Compose projects
- Viewing container logs

### Environment Variables

Tuti CLI manages environment variables for Docker Compose. Ensure:
- `.env` files are in `.gitignore`
- Sensitive values are not logged
- Production secrets are managed securely

### Interactive Terminal (TTY) Support

Tuti CLI uses `escapeshellarg()` in `DockerExecutorService::runInteractive()` for interactive terminal sessions. This is a documented exception to our standard security practice of using array syntax for all external process execution.

**Why this exception exists:**
- Interactive TTY sessions require PHP's `passthru()` function
- `passthru()` accepts only string commands, not arrays
- All inputs are from trusted internal sources (validated internally)
- Each command element is individually escaped with `escapeshellarg()`

This is a controlled exception with full documentation in the source code. See `app/Services/Docker/DockerExecutorService.php` for details.

---

Thank you for helping keep Tuti CLI and its users safe!
