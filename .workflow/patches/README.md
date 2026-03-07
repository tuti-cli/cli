# Workflow Patches

This directory contains bug fix documentation (patches) that capture lessons learned from fixing issues.

## Purpose

Patches serve as institutional memory for the workflow system. They document:
- What went wrong (the problem)
- Why it happened (root cause)
- How it was fixed (the solution)
- How to prevent it (prevention steps)

## Naming Convention

```
issue-<NUMBER>-<slug>.md
```

Examples:
- `issue-67-replace-exec-with-process.md`
- `issue-68-domain-validation-hosts.md`

## Patch Template

```markdown
# Patch: <Brief Description>

**Issue:** #<NUMBER>
**Date:** YYYY-MM-DD
**Category:** security | docker | testing | php | workflow

## Problem
What went wrong?

## Root Cause
Why did it happen?

## Solution
How was it fixed?

## Prevention
How to prevent this in the future?

## Files Changed
- path/to/file1.php
- path/to/file2.php
```

## Lifecycle

1. **Created:** When a bug is fixed during workflow execution
2. **Referenced:** Loaded during pre-flight to prevent recurring issues
3. **Deleted:** Automatically removed when the linked issue is closed

## Related

- `.workflow/INDEX.md` - Categorized index of all patches
- `.claude/agents/patch-writer.md` - Agent that creates patches
