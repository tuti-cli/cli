# arch:decide

> Record an architecture decision as an ADR (Architecture Decision Record).

**Usage:**
- `/arch:decide "<decision>"` — Record a decision with context
- `/arch:decide --from-proposal <file>` — Record from existing proposal
- `/arch:decide --accept <proposal>` — Accept proposal as-is

**When to use:**
- After brainstorming, ready to finalize decision
- Need to document why an approach was chosen
- Creating permanent record for future reference

**Related commands:**
- `/arch:brainstorm` — Explore options first
- `/arch:challenge` — Stress-test before deciding
- `/workflow:issue` — Implement the decision

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

**Interactive Checkpoints:**
1. **After gathering context:**
   - AskUserQuestion: "What aspect needs decision?" (if not specified)
2. **After presenting options:**
   - AskUserQuestion: "Which option do you prefer?"
3. **Before creating ADR file:**
   - Show preview of ADR content
   - AskUserQuestion: "Create ADR file?"

**Output:**
- ADR file in .workflow/ADRs/
- GitHub issues for implementation (optional)

Invoke `architecture-recorder`:
> "Record architecture decision: $ARGUMENTS. GITHUB REPO: owner=tuti-cli repo=cli. **INTERACTIVE: If context unclear, AskUserQuestion: 'What aspect needs decision?'** IF --from-proposal or --accept: read the proposal from .workflow/proposals/. Also read any challenge from .workflow/challenges/. Present 2-3 options with tradeoffs. **INTERACTIVE: AskUserQuestion: 'Which option do you prefer?'** Document the decision with full context, reasoning, and consequences. **INTERACTIVE: Before writing, show preview and AskUserQuestion: 'Create ADR file?'** Write ADR to .workflow/ADRs/00N-[slug].md with sequential numbering. IF implementation is needed: create GitHub issues with workflow:feature label. Set ADR status to Accepted. Report ADR number and any issues created."
