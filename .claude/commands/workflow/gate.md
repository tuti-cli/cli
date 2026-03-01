# workflow:gate
Run all quality gates: lint, type check, and test coverage. Blocks pipeline if any gate fails.

**Usage:**
- `/workflow:gate` — Run all quality gates
- `/workflow:gate --quick` — Run lint and tests only (skip coverage)
- `/workflow:gate --fix` — Auto-fix lint issues before gating

**Quality Gates (in order):**
1. **Lint** — `composer lint` (Pint formatting)
2. **Types** — `composer test:types` (PHPStan analysis)
3. **Tests** — `composer test:unit` (Pest tests)
4. **Coverage** — `composer test:coverage` (80% minimum)

**Gate Rules:**
- All gates must pass
- No bypass allowed
- Auto-fix only for lint (--fix flag)

**Pipeline Blocking:**
- Lint fail → Block, run with --fix or fix manually
- Types fail → Block, fix type errors
- Tests fail → Block, fix failing tests
- Coverage fail → Block, add more tests

Invoke `coverage-guardian`:
> "Run all quality gates for pipeline. GITHUB REPO: owner=tuti-cli repo=cli. Execute gates in order: 1) composer lint, 2) composer test:types, 3) composer test:unit, 4) composer test:coverage. IF --quick: skip coverage gate. IF --fix: run `composer lint` first to auto-fix formatting. Report results for each gate. IF all gates pass: approve pipeline. IF any gate fails: block pipeline, report specific failures, provide remediation steps. Gates are mandatory with no bypass."
