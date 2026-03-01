# workflow:coverage
Check test coverage thresholds. Invokes coverage-guardian to enforce coverage gates.

**Usage:**
- `/workflow:coverage` — Check overall coverage
- `/workflow:coverage --new-code` — Check new code coverage only
- `/workflow:coverage --report` — Generate detailed coverage report

**Coverage Thresholds:**
| Metric | Threshold |
|--------|-----------|
| Overall | 80% |
| New Code | 90% |
| Commands | 80% |
| Services | 90% |

**Hard Gate:** No bypass allowed. Coverage must meet thresholds.

Invoke `coverage-guardian`:
> "Check test coverage thresholds. GITHUB REPO: owner=tuti-cli repo=cli. Run `composer test:coverage` to get coverage metrics. Compare against thresholds: Overall >= 80%, New Code >= 90%, Services >= 90%, Commands >= 80%. IF thresholds met: approve pipeline continuation. IF thresholds not met: block pipeline, generate report with uncovered files and lines, recommend tests to add. Coverage is a hard gate with no bypass."
