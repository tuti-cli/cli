# workflow:test
Run tests for current implementation. Invokes test-engineer to write missing tests and ensures all tests pass.

**Usage:**
- `/workflow:test` — Run all tests
- `/workflow:test --filter <name>` — Run specific tests by name
- `/workflow:test --coverage` — Run with coverage report
- `/workflow:test --write` — Write missing tests for changed files

**Test Types:**
- Unit tests — Individual functions/methods
- Integration tests — Component interactions
- Feature tests — CLI commands

**Commands:**
```bash
composer test:unit          # Run all tests
composer test:coverage      # Run with coverage
./vendor/bin/pest --filter "test name"  # Run specific test
```

Invoke `test-engineer`:
> "Run tests for current implementation. GITHUB REPO: owner=tuti-cli repo=cli. IF --filter: run specific tests matching the filter. IF --coverage: run with coverage and show report. IF --write: analyze changed files and write missing tests. Run `composer test:unit` to execute Pest tests. Report test results and any failures. IF tests fail: analyze failures and report what needs fixing."
