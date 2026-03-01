# workflow:improve
Analyze and improve the workflow system itself. Identifies bottlenecks and proposes optimizations.

**Usage:**
- `/workflow:improve` — Analyze workflow and propose improvements
- `/workflow:improve --apply` — Apply proposed improvements
- `/workflow:improve --metrics` — Show current workflow metrics
- `/workflow:improve "<suggestion>"` — Propose specific improvement

**Examples:**
- `/workflow:improve` — General workflow analysis
- `/workflow:improve "issue-executor should validate labels before starting"`
- `/workflow:improve --metrics` — Show performance data

**Analysis Areas:**
- Pipeline performance (bottlenecks, delays)
- Agent effectiveness (selection accuracy)
- Command efficiency (steps, shortcuts)
- Quality metrics (coverage, review time)

**Improvement Types:**
- Pipeline optimizations (parallel execution)
- Agent improvements (new specialists)
- Command enhancements (shortcuts, defaults)

Invoke `workflow-improver`:
> "Analyze and improve the workflow system. GITHUB REPO: owner=tuti-cli repo=cli. IF --metrics: show current performance metrics. IF specific suggestion: analyze that improvement. ELSE: perform general workflow analysis. Identify bottlenecks, inefficiencies, and optimization opportunities. Propose improvements with expected impact. IF --apply: implement approved improvements. Generate improvement report. Update relevant agent or command files if changes are made."
