# workflow:feature
Plan and execute a feature implementation with task breakdown, agent assignments, and quality gates.

**Usage:**
- `/workflow:feature` — Plan feature from current context
- `/workflow:feature --plan-only` — Create plan without executing
- `/workflow:feature <N>` — Plan and execute issue #N

**Pipeline:**
1. **Planning Phase** — feature-planner → task-decomposer
   - Analyze requirements
   - Break into tasks
   - Assign agents
   - Define checkpoints
2. **Execution Phase** — master-orchestrator
   - Execute tasks sequentially
   - Run quality gates
   - Create commits at checkpoints
   - Create PR when complete

**Output:**
- `.workflow/features/feature-N.md` — Task breakdown
- Implemented code with tests
- Pull request with documentation

**Task Structure:**
- Atomic, completable units
- Clear agent assignments
- Defined inputs/outputs
- Commit checkpoints every 3-5 tasks

Invoke `feature-planner` then `task-decomposer` then `master-orchestrator`:
> "Plan and execute feature implementation. GITHUB REPO: owner=tuti-cli repo=cli. IF issue number provided: fetch issue #$ARGUMENTS. ELSE: use current context. Invoke feature-planner to analyze requirements, break into tasks with agent assignments, and create .workflow/features/feature-N.md. Then invoke task-decomposer to refine tasks into atomic units with clear inputs/outputs and completion criteria. IF --plan-only: stop and present plan for approval. ELSE: invoke master-orchestrator to execute the plan with sequential task execution, quality gates at each checkpoint, and final PR creation."
