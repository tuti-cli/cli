# Testing Standards

## Coverage Thresholds

| Scope | Minimum |
|-------|---------|
| Commands | 80% |
| Services | 90% |
| Helpers | 95% |
| New code in PR | 90% |

## Test Naming

Test name must complete "it [does something]":
```
// Good
it('returns 404 when user not found')
it('creates .tuti directory with correct structure')

// Bad
it('test user')
it('works correctly')
```

## Test Structure

Arrange / Act / Assert — always in this order.
Use `describe()` blocks for organization.

## Mocking

- Mock services via `$this->app->instance()`
- Test helpers in `tests/Feature/Concerns/` (CreatesHelperTestEnvironment, CreatesLocalProjectEnvironment, CreatesTestStackEnvironment)
- Mock orchestrator: `tests/Mocks/FakeDockerOrchestrator.php`
- Command tests: `$this->artisan('command')->assertExitCode(Command::SUCCESS)`

## Test Organisation

```
tests/
  Unit/Services/   # Pure logic, no DB, no HTTP
  Feature/         # Full stack with database
  Mocks/           # Shared test doubles
```

## What Must Always Be Tested

1. Happy path
2. Validation failures
3. Edge cases (empty, null, boundary)
4. Exception paths
5. Side effects (emails, jobs, events)
