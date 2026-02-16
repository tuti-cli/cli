# Contributing to Tuti CLI

Thank you for considering contributing to Tuti CLI!

## Development Setup

1. Fork the repository
2. Clone your fork: `git clone https://github.com/YOUR_USERNAME/cli.git`
3. Install dependencies: `composer install`
4. Create a branch: `git checkout -b feature/your-feature`

## Coding Standards

- Follow PSR-12 coding standard
- Run `composer lint` before committing
- Run `composer analyse` for static analysis
- Write tests for new features
- Aim for 80%+ code coverage

## Testing
```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test
php vendor/bin/pest tests/Unit/Services/ConfigServiceTest.php
```

## Git & Workflow

### Commit Messages
Use **Conventional Commits** format:
- Format: `type(scope): subject`
- Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`
- Examples:
  - `feat(local): add port conflict detection`
  - `fix(docker): resolve container naming issue`
  - `docs(readme): update installation instructions`

### Branch Strategy
- `main` - stable releases only
- `develop` - active development
- `feature/*` - new features (e.g., `feature/redis-support`)
- `fix/*` - bug fixes (e.g., `fix/port-conflict`)

### Pull Request Requirements
- All tests must pass (Github Actions)
- Description explains **what** and **why**
- Update documentation if needed
- Follow Conventional Commits for PR title
## Pull Request Process

1. Update documentation if needed
2. Ensure all tests pass
3. Update CHANGELOG.md
4. Submit pull request with clear description

## Code Style

We use Laravel Pint with strict rules:
- Declare strict types in all files
- Use final classes by default
- Use strict comparison (===)
- Type hint everything

## Questions?

Open an issue or join our Discord!
