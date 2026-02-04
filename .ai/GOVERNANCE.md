# AI Folder Governance

## Purpose
`.ai/` and `.claude/` folders contain **permanent, reusable** AI assistant context.

## ✅ Allowed Content

| Type | Path | Description |
|------|------|-------------|
| Rules | `RULES.md` | Quick reference for AI |
| Index | `INDEX.md` | Navigation/structure |
| Guidelines | `guidelines/**/*.md` | Coding patterns, architecture |
| Skills | `skills/*/SKILL.md` | Reusable task guides |
| Config | `boost-config.json` | AI tool configuration |

## ❌ Not Allowed

| Type | Why |
|------|-----|
| Implementation summaries | Temporary - use CHANGELOG.md |
| Feature docs | Put in `docs/` folder |
| Session logs | Temporary - don't save |
| Quick references for one feature | Temporary |
| "Complete" markers | Tracked in git commits |

## Adding New Content

### Before adding a file, ask:

1. **Is it reusable?** (Will AI need this for future, similar tasks?)
2. **Is it permanent?** (Not tied to a single feature/session?)
3. **Does it exist elsewhere?** (Check docs/, README.md, CHANGELOG.md)

### Decision Tree

```
Need to document something?
├─ One-time implementation → CHANGELOG.md
├─ User-facing feature → docs/*.md
├─ Coding pattern (3+ uses) → guidelines/**/*.md
├─ Repeatable AI task → skills/*/SKILL.md
└─ Project rules → RULES.md
```

## Folder Sync

`.ai/` and `.claude/` should have identical content. Update both when making changes.
