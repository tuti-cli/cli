# improve-workflow
Improve the workflow system itself using the same plan → approve → PR process.

**Usage:** `/improve-workflow "description of change"`

Invoke `tuti-workflow-master`:
> "GITHUB REPO: owner=tuti-cli repo=cli — use --repo tuti-cli/cli for gh CLI commands. Workflow self-improvement: $ARGUMENTS. If no argument, ask what to change. Then: identify affected files (tuti-workflow-master.md, WORKFLOW.md, command files), create a type: chore status: ready issue titled 'workflow: <description>', run implementation plan mode showing exactly which lines change in which files, LIST THE AGENT SQUAD that will be used (refactoring-specialist as primary for type: chore, plus any task-based agents), wait for approval, edit the files keeping tuti-workflow-master.md and WORKFLOW.md in sync, commit as chore(workflow): <description> (#N), create PR."
