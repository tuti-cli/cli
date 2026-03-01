# arch:brainstorm
Start an architecture brainstorming session. Propose 2-3 options with tradeoffs for a technical decision.

**Usage:**
- `/arch:brainstorm "<question>"` — Start brainstorming session

**Examples:**
- `/arch:brainstorm "best approach for user authentication"`
- `/arch:brainstorm "how to handle multi-tenant configuration"`
- `/arch:brainstorm "safest migration path for PHP 8.4 upgrade"`

**Process:**
1. architecture-lead proposes 2-3 options
2. architecture-challenger stress-tests options
3. knowledge-synthesizer structures debate
4. architecture-recorder documents decision (ADR)

**Output:**
- Proposal document in .workflow/proposals/
- Challenge document in .workflow/challenges/
- ADR in .workflow/ADRs/ (after /arch:decide)

Invoke `architecture-lead`:
> "Start architecture brainstorming for: $ARGUMENTS. GITHUB REPO: owner=tuti-cli repo=cli. Understand the problem and constraints. Develop 2-3 viable approaches with clear tradeoffs. Analyze each option's pros, cons, complexity, and risk. Present options with a recommendation. Write proposal to .workflow/proposals/[slug].md. Then invoke architecture-challenger to stress-test the proposal. Use knowledge-synthesizer to structure the debate if needed. Present final options to user for decision."
