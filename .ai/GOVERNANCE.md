# .ai/ Folder Governance

## ✅ Allowed

| File | Purpose |
|------|---------|
| `RULES.md` | AI assistant context |
| `INDEX.md` | Navigation |
| `GOVERNANCE.md` | This file |
| `guidelines/**/*.md` | Reusable coding patterns |
| `skills/*/SKILL.md` | Reusable task guides |
| `boost-config.json` | AI tool config |

## ❌ Not Allowed

- Implementation summaries → use `CHANGELOG.md`
- Feature documentation → use `docs/`
- Session logs → don't save
- One-time notes → don't save

## Decision Tree

```
├─ One-time work → CHANGELOG.md
├─ User docs → docs/*.md
├─ Pattern (3+ uses) → guidelines/**/*.md
├─ Repeatable task → skills/*/SKILL.md
└─ Project context → RULES.md
```
