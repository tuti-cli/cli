# arch:challenge

> Challenge an architecture proposal. Act as devil's advocate.

**Usage:**
- `/arch:challenge` — Challenge the most recent proposal
- `/arch:challenge <proposal>` — Challenge a specific proposal

**When to use:**
- Before accepting a proposal
- Want to identify weaknesses and risks
- Need thorough review before implementation

**Related commands:**
- `/arch:brainstorm` — Create proposal first
- `/arch:decide` — Record decision after review
- `/workflow:audit` — Audit existing implementation

**Examples:**
- `/arch:challenge`
- `/arch:challenge auth-proposal`

**Challenge Process:**
1. Read proposal and identify assumptions
2. Question each assumption
3. Identify weaknesses and risks
4. Consider edge cases and failure modes
5. Suggest improvements
6. Document challenge

**Interactive Checkpoints:**
1. **After reading proposal:**
   - AskUserQuestion: "Ready to see challenges?"
2. **After presenting each challenge:**
   - AskUserQuestion: "Address this challenge or continue to next?"
3. **Before creating challenge file:**
   - Show preview of challenge content
   - AskUserQuestion: "Create challenge file?"

**Challenge Categories:**
- Scalability concerns
- Security vulnerabilities
- Failure modes
- Cost implications
- Complexity issues
- Migration challenges

Invoke `architecture-challenger`:
> "Challenge the architecture proposal: $ARGUMENTS. GITHUB REPO: owner=tuti-cli repo=cli. IF no argument: find most recent proposal in .workflow/proposals/. Read the proposal completely. **INTERACTIVE: AskUserQuestion: 'Ready to see challenges?'** Present challenges one by one. **INTERACTIVE: After each challenge, AskUserQuestion: 'Address this challenge or continue?'** Question assumptions being made. Identify weaknesses in each option. Consider edge cases and failure modes. Analyze security implications. Suggest improvements to strengthen the proposal. **INTERACTIVE: Before writing, show preview and AskUserQuestion: 'Create challenge file?'** Write challenge to .workflow/challenges/[proposal]-challenge.md. Provide verdict: sound as-is, needs adjustments, significant concerns, or should be rejected."
