# workflow:bugfix
Execute bug fix through the full pipeline with regression testing and patch documentation.

**Usage:**
- `/workflow:bugfix <N>` — Execute bug fix pipeline
- `/workflow:bugfix <N> --hotfix` — Emergency hotfix (expedited pipeline)

**Bug Fix Pipeline:**
1. **ANALYZE** — error-detective finds root cause
2. **FIX** — Apply the fix
3. **REGRESSION TEST** — Write test that reproduces bug, confirm fix
4. **REVIEW** — Code review + security review
5. **PATCH** — Document in .workflow/patches/
6. **COMMIT** — Conventional commit with fix reference
7. **PR** — Create pull request

**Regression Test Rule:**
No fix ships without a regression test. The test must:
1. Reproduce the bug (FAIL before fix)
2. Pass after fix
3. Prevent future recurrence

**Patch Documentation:**
Every bug fix is documented in .workflow/patches/ with:
- Problem description
- Root cause analysis
- Solution applied
- Prevention measures

Invoke `error-detective` then `master-orchestrator`:
> "Execute bug fix pipeline for issue #$ARGUMENTS. GITHUB REPO: owner=tuti-cli repo=cli. First invoke error-detective for root cause analysis. Then apply fix with regression test (MUST reproduce bug first). After fix verified, invoke patch-writer to document in .workflow/patches/. Continue through review, commit, and PR stages. IF --hotfix: expedite review, but still require regression test. PATCH FILE IS MANDATORY for every bug fix."
