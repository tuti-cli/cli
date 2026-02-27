---
name: audit-checklist
description: "Comprehensive checklist for codebase audits. Defines scope, depth, and coverage requirements for standard and legacy audits."
---

# Audit Checklist

Complete checklist for codebase auditing. Use this to ensure comprehensive coverage during /workflow:audit.

## Pre-Audit Setup

- [ ] Determine audit mode (standard vs legacy)
- [ ] Identify audit scope (full vs partial)
- [ ] Set up audit environment
- [ ] Clear previous audit artifacts
- [ ] Create .workflow/ directory if needed

## Architecture Audit

### Pattern Detection
- [ ] Identify architectural patterns (MVC, Service Layer, etc.)
- [ ] Map component boundaries
- [ ] Check boundary violations
- [ ] Document coupling issues
- [ ] Note missing abstractions

### Structure Analysis
- [ ] Map directory structure
- [ ] Identify entry points
- [ ] Document service layer
- [ ] Analyze data access patterns
- [ ] Check infrastructure config

### Design Issues
- [ ] Circular dependencies
- [ ] God objects/classes
- [ ] Primitive obsession
- [ ] Missing encapsulation
- [ ] Leaky abstractions

## Dependency Audit

### Health Check
- [ ] List all production dependencies
- [ ] List all development dependencies
- [ ] Check for outdated packages
- [ ] Check for EOL packages
- [ ] Check for abandoned packages

### Security Check
- [ ] Run vulnerability scan (composer audit / npm audit)
- [ ] Check for known CVEs
- [ ] Identify insecure versions
- [ ] Check dependency licenses

### Optimization
- [ ] Identify unused dependencies
- [ ] Check for duplicate functionality
- [ ] Note bundle/package size

**Commands:**
```bash
# PHP
composer outdated
composer audit

# JavaScript
npm outdated
npm audit
```

## Code Quality Audit

### Complexity Metrics
- [ ] Cyclomatic complexity check
- [ ] Method length analysis
- [ ] Class length analysis
- [ ] Nesting depth check
- [ ] Parameter count check

### Code Smells
- [ ] Long methods (>20 lines)
- [ ] Large classes (>200 lines)
- [ ] Deep nesting (>3 levels)
- [ ] Duplicate code blocks
- [ ] Dead code
- [ ] Magic numbers/strings
- [ ] Commented-out code

### Best Practices
- [ ] Type hints present
- [ ] Return types defined
- [ ] Strict types enabled
- [ ] Proper error handling
- [ ] Consistent naming

**Tools:**
```bash
# PHP
./vendor/bin/phpstan analyse
./vendor/bin/phpcs --standard=PSR12

# Complexity
phpmetrics --report-html=metrics/ ./
```

## Security Audit

### Vulnerability Scan
- [ ] SQL injection risks
- [ ] Command injection risks
- [ ] XSS vulnerabilities
- [ ] CSRF protection
- [ ] Authentication weaknesses
- [ ] Authorization gaps

### Secret Detection
- [ ] Hardcoded passwords
- [ ] API keys in code
- [ ] Private keys
- [ ] Database credentials
- [ ] OAuth tokens

### Configuration
- [ ] Debug mode settings
- [ ] Error display settings
- [ ] Session configuration
- [ ] CORS settings
- [ ] Security headers

**Patterns to Check:**
```
# Secrets
/password\s*=\s*['"]/i
/api[_-]?key\s*=\s*['"]/i
/secret\s*=\s*['"]/i

# Injection
/eval\s*\(/
/exec\s*\(/
/system\s*\(/
/->query\s*\(.*\$/
```

## Test Coverage Audit

### Coverage Analysis
- [ ] Overall coverage percentage
- [ ] Unit test coverage
- [ ] Integration test coverage
- [ ] Critical path coverage
- [ ] New code coverage

### Test Quality
- [ ] Meaningful assertions
- [ ] Edge case coverage
- [ ] Error case tests
- [ ] Mock/stub usage
- [ ] Test isolation

### Gap Identification
- [ ] Untested services
- [ ] Untested commands
- [ ] Untreated edge cases
- [ ] Missing integration tests

**Commands:**
```bash
# PHP with Pest
composer test:coverage

# Check specific coverage
./vendor/bin/pest --coverage --min=80
```

## Documentation Audit

### Code Documentation
- [ ] README completeness
- [ ] CLAUDE.md accuracy
- [ ] Inline comments quality
- [ ] Doc blocks present
- [ ] Type documentation

### API Documentation
- [ ] Endpoint documentation
- [ ] Request/response examples
- [ ] Authentication docs
- [ ] Error response docs

### User Documentation
- [ ] Installation guide
- [ ] Usage examples
- [ ] Configuration guide
- [ ] Troubleshooting

## Legacy Mode (Additional)

### EOL Assessment
- [ ] PHP version EOL check
- [ ] Framework version EOL check
- [ ] Database version EOL check
- [ ] Library version EOL check

### Deprecated Patterns
- [ ] Identify deprecated syntax
- [ ] Find legacy patterns
- [ ] Note compatibility layers
- [ ] Document workarounds

### Migration Scope
- [ ] Breaking changes inventory
- [ ] Upgrade path analysis
- [ ] Backward compatibility needs
- [ ] Data migration requirements

## Audit Report Template

### Executive Summary
- Overall health score (1-10)
- Critical issues count
- High priority issues count
- Recommended actions

### Categories

| Category | Critical | High | Medium | Low |
|----------|----------|------|--------|-----|
| Architecture | | | | |
| Dependencies | | | | |
| Code Quality | | | | |
| Security | | | | |
| Testing | | | | |
| Documentation | | | | |

### Priority Recommendations

**Immediate (Critical):**
1. [Recommendation]

**This Sprint (High):**
1. [Recommendation]

**Soon (Medium):**
1. [Recommendation]

**Nice to Have (Low):**
1. [Recommendation]

## Post-Audit Actions

- [ ] Write AUDIT.md with findings
- [ ] Hand off to tech-debt-mapper
- [ ] Create TECH-DEBT.md
- [ ] Generate GitHub issues
- [ ] Schedule remediation work
