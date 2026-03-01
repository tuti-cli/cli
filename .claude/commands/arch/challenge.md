# arch:challenge
Challenge an existing architecture proposal. Act as devil's advocate to stress-test decisions.

**Usage:**
- `/arch:challenge` — Challenge the most recent proposal
- `/arch:challenge <proposal>` — Challenge a specific proposal

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

**Challenge Categories:**
- Scalability concerns
- Security vulnerabilities
- Failure modes
- Cost implications
- Complexity issues
- Migration challenges

Invoke `architecture-challenger`:
> "Challenge the architecture proposal: $ARGUMENTS. GITHUB REPO: owner=tuti-cli repo=cli. IF no argument: find most recent proposal in .workflow/proposals/. Read the proposal completely. Question assumptions being made. Identify weaknesses in each option. Consider edge cases and failure modes. Analyze security implications. Suggest improvements to strengthen the proposal. Write challenge to .workflow/challenges/[proposal]-challenge.md. Provide verdict: sound as-is, needs adjustments, significant concerns, or should be rejected. Report challenge findings."
