# workflow:audit

> Perform deep analysis of codebase quality, security, and technical debt.

**Usage:**
- `/workflow:audit` — Standard audit for active codebase
- `/workflow:audit --legacy` — Deep audit for legacy codebase (migration planning)

**When to use:**
- Before major refactoring
- Assessing codebase health
- Planning legacy migration
- Security review

**Related commands:**
- `/workflow:feature` — Implement new features
- `/workflow:bugfix` — Fix identified issues
- `/arch:brainstorm` — Plan architecture changes

**Audit Areas:**
1. **Architecture** — Patterns, boundaries, violations
2. **Dependencies** — Outdated, deprecated, vulnerable packages
3. **Code Quality** — Complexity, duplication, dead code
4. **Security** — Vulnerabilities, secrets, injection risks
5. **Testing** — Coverage gaps, test quality

**Output:**
- `.workflow/AUDIT.md` — Comprehensive audit report
- `.workflow/TECH-DEBT.md` — Prioritized debt registry
- GitHub issues for each debt item

**Legacy Mode (--legacy):**
Additional deep analysis:
- EOL dependency identification
- Deprecated pattern detection
- Migration path assessment
- Compatibility analysis

Invoke `codebase-auditor` then `tech-debt-mapper`:
> "Perform codebase audit. GITHUB REPO: owner=tuti-cli repo=cli. Check for --legacy flag. **IF --legacy:** run deep legacy audit with additional EOL/deprecated detection and migration scope assessment. **ELSE:** run standard audit. Analyze architecture patterns, dependency health, code quality metrics, security vulnerabilities, and test coverage gaps. Spawn security-auditor in parallel for comprehensive security review. Write findings to .workflow/AUDIT.md. Then invoke tech-debt-mapper to categorize findings, map severity to priority, estimate effort, and create .workflow/TECH-DEBT.md with GitHub issues for all prioritized debt items."
