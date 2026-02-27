# improve-workflow
Improve the workflow system itself using the same plan → approve → PR process.

**Usage:**
- `/improve-workflow "description of change"` — full flow with issue + PR
- `/improve-workflow --no-issue "description of change"` — quick inline changes (no issue, no commits, current branch)

**Agent Catalog Reference:**
- Use `agent-installer` to browse and install agents from the awesome-claude-code-subagents catalog
- Or browse directly: [awesome-claude-code-subagents](https://github.com/VoltAgent/awesome-claude-code-subagents)

Invoke `tuti-workflow-master`:
> "GITHUB REPO: owner=tuti-cli repo=cli — use --repo tuti-cli/cli for gh CLI commands. Workflow self-improvement: $ARGUMENTS. If no argument, ask what to change. Check for --no-issue flag. Then: identify affected files (tuti-workflow-master.md, WORKFLOW.md, command files, settings.json), sync CLAUDE.md .claude Configuration section if agents/skills change. **Use agent-installer to check available agents before creating new ones.** Suggest relevant agents from the catalog if the improvement involves new capabilities. IF --no-issue: plan → approve → edit files in current branch WITHOUT creating issue, commit, or PR. ELSE: create a type: chore status: ready issue titled 'workflow: <description>', run implementation plan mode showing exactly which lines change in which files, LIST THE AGENT SQUAD that will be used (refactoring-specialist as primary for type: chore, plus any task-based agents), wait for approval, edit the files keeping all workflow files in sync, commit as chore(workflow): <description> (#N), create PR."
