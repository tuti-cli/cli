# workflow:evolve
Improve agent definitions from accumulated patches. Makes the workflow system smarter by learning from past issues.

**Usage:**
- `/workflow:evolve` — Evolve all agents from all patches
- `/workflow:evolve --recent` — Only process recent patches
- `/workflow:evolve --agent <name>` — Evolve specific agent
- `/workflow:evolve --dry-run` — Show proposed changes without applying

**Evolution Process:**
1. Read accumulated patches from .workflow/patches/
2. Identify recurring patterns and issues
3. Map issues to relevant agents
4. Propose improvements (checklists, rules, knowledge)
5. Update agent .md files

**Improvement Types:**
- Add checklist items from recurring issues
- Add prevention rules from bug fixes
- Add known issues sections
- Update best practices

Invoke `skill-evolver`:
> "Evolve agent definitions from accumulated patches. GITHUB REPO: owner=tuti-cli repo=cli. IF --recent: only process patches from last 7 days. IF --agent: only evolve specified agent. IF --dry-run: show proposals without applying. Read all patches from .workflow/patches/. Identify recurring patterns. Map patterns to relevant agents. Propose improvements to agent checklists, prevention rules, and knowledge sections. Apply updates to agent .md files. Generate evolution report showing what was learned and improved."
