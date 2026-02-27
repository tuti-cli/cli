# workflow:fix
Apply a quick fix without full pipeline. For simple fixes that don't need full bug fix workflow.

**Usage:**
- `/workflow:fix <description>` — Apply fix based on description
- `/workflow:fix --patch <file>` — Apply changes from patch file

**Use Cases:**
- Typos and minor corrections
- Configuration adjustments
- Simple logic fixes
- Quick patches

**Not For:**
- Security issues (use /workflow:bugfix)
- Complex bugs (use /workflow:bugfix)
- Features (use /workflow:feature)

**Still Requires:**
- Quality gates (lint, test)
- Conventional commit

Invoke `error-detective`:
> "Apply a quick fix: $ARGUMENTS. GITHUB REPO: owner=tuti-cli repo=cli. IF --patch: read and apply patch file. ELSE: analyze the description, identify the fix needed, apply it. Run `composer lint && composer test` to verify. Create conventional commit with fix reference. No full bug fix pipeline needed."
