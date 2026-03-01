# workflow:review
Run code review on current changes. Spawns code-reviewer and optional specialist reviewers.

**Usage:**
- `/workflow:review` — Review current branch changes
- `/workflow:review --security` — Include security review
- `/workflow:review --performance` — Include performance review
- `/workflow:review --full` — Full review (code + security + performance)

**Review Agents:**
| Type | Agent | Focus |
|------|-------|-------|
| Code | code-reviewer | Quality, patterns, best practices |
| Security | security-auditor | Vulnerabilities, injection, auth |
| Performance | performance-engineer | Bottlenecks, optimization |

**Review Output:**
- Blocking issues (must fix)
- Suggestions (should consider)
- Nits (minor improvements)

Invoke `code-reviewer`:
> "Run code review on current changes. GITHUB REPO: owner=tuti-cli repo=cli. Get git diff of current branch vs main. Spawn code-reviewer to analyze changes. IF --security: also spawn security-auditor. IF --performance: also spawn performance-engineer. IF --full: spawn all three. Use multi-agent-coordinator for parallel execution. Merge results into single review report. Present findings categorized by severity: BLOCKING, SUGGESTION, NIT. Report any issues that must be fixed before commit."
