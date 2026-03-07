# arch:brainstorm

> Start an architecture brainstorming session with multiple options.

**Usage:**
- `/arch:brainstorm "<question>"` — Start brainstorming session

**When to use:**
- Exploring multiple approaches to a technical problem
- Need to compare tradeoffs between solutions
- Starting architecture decision process

**Related commands:**
- `/arch:decide` — Record the final decision as an ADR
- `/arch:challenge` — Stress-test an existing proposal
- `/workflow:issue` — Execute implementation after decision

**Examples:**
- `/arch:brainstorm "best approach for user authentication"`
- `/arch:brainstorm "how to handle multi-tenant configuration"`
- `/arch:brainstorm "safest migration path for PHP 8.4 upgrade"`

**Process:**
1. architecture-lead proposes 2-3 options
2. architecture-challenger stress-tests options
3. knowledge-synthesizer structures debate
4. architecture-recorder documents decision (ADR)

**Interactive Checkpoints:**
1. **After analyzing requirements:**
   - AskUserQuestion: "Ready to explore options?"
2. **After presenting each option:**
   - AskUserQuestion: "Explore more options or proceed to recommendation?"
3. **Before creating proposal file:**
   - Show preview of proposal content
   - AskUserQuestion: "Create proposal file?"

**Output:**
- Proposal document in .workflow/proposals/
- Challenge document in .workflow/challenges/
- ADR in .workflow/ADRs/ (after /arch:decide)

Invoke `architecture-lead`:
> "Start architecture brainstorming for: $ARGUMENTS. GITHUB REPO: owner=tuti-cli repo=cli. Understand the problem and constraints. **INTERACTIVE: After analysis, AskUserQuestion: 'Ready to explore options?'** Develop 2-3 viable approaches with clear tradeoffs. **INTERACTIVE: After each option, AskUserQuestion: 'Explore more options or proceed?'** Analyze each option's pros, cons, complexity, and risk. Present options with a recommendation. **INTERACTIVE: Before writing file, show preview and AskUserQuestion: 'Create proposal file?'** Write proposal to .workflow/proposals/[slug].md. Then invoke architecture-challenger to stress-test the proposal."
