# Feature Rules

Subsystem-specific rules. Highest priority — override base and project.

## Adding Rules

```
/ww:rules-add features/auth "Only use sanctum guards on API routes"
/ww:rules-add features/payments "Always pass idempotency_key to Stripe"
```

## Priority

```
features/*.md      <- wins on conflict
project/*.md
base/*.md          <- baseline
```

Feature rules load selectively based on task context.
