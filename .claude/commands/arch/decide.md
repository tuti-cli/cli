# arch:decide
Record an architecture decision as an ADR (Architecture Decision Record).

**Usage:**
- `/arch:decide "<decision>"` — Record a decision with context
- `/arch:decide --from-proposal <file>` — Record from existing proposal
- `/arch:decide --accept <proposal>` — Accept proposal as-is

**Examples:**
- `/arch:decide "use JWT for CLI authentication"`
- `/arch:decide --from-proposals auth-proposal.md`
- `/arch:decide --accept auth-proposal.md`

**ADR Contents:**
- Context and problem statement
- Options considered
- Decision and reasoning
- Consequences (positive/negative)
- Implementation phases

**Output:**
- ADR file in .workflow/ADRs/
- GitHub issues for implementation (optional)

Invoke `architecture-recorder`:
> "Record architecture decision: $ARGUMENTS. GITHUB REPO: owner=tuti-cli repo=cli. IF --from-proposal or --accept: read the proposal from .workflow/proposals/. Also read any challenge from .workflow/challenges/. Document the decision with full context, reasoning, and consequences. Write ADR to .workflow/ADRs/00N-[slug].md with sequential numbering. IF implementation is needed: create GitHub issues with workflow:feature label. Set ADR status to Accepted. Report ADR number and any issues created."
