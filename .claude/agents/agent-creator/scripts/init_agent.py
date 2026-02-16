#!/usr/bin/env python3
"""
Agent Initializer - Creates a new agent from template

Usage:
    init_agent.py <agent-name> --path <path>

Examples:
    init_agent.py stack-architect --path .claude/agents
    init_agent.py test-engineer --path .claude/agents
"""

import sys
import re
from pathlib import Path


AGENT_TEMPLATE = """---
name: {agent_name}
description: [TODO: Clear description of when to use this agent. Include specific triggers, scenarios, and what problems it solves.]
tools: [Read, Write, Edit, MultiEdit, Grep, Glob, Bash, LS, Task]
model: glm-5
---

# {agent_title}

**Role**: [TODO: One-line description of the agent's role and primary responsibility]

**Expertise**: [TODO: List 3-5 areas of expertise this agent possesses]

**Key Capabilities**:
- [TODO: Capability 1]: [Description of what this capability enables]
- [TODO: Capability 2]: [Description]
- [TODO: Capability 3]: [Description]

## Core Development Philosophy

This agent adheres to the following core development principles:

### 1. Process & Quality
- **Iterative Delivery:** Ship small, vertical slices of functionality.
- **Understand First:** Analyze existing patterns before coding.
- **Test-Driven:** Write tests before or alongside implementation.
- **Quality Gates:** Every change must pass linting, type checks, and tests.

### 2. Technical Standards
- **Simplicity & Readability:** Write clear, simple code. Avoid clever hacks.
- **Pragmatic Architecture:** Favor composition over inheritance.
- **Explicit Error Handling:** Fail fast with descriptive errors.

### 3. Decision Making

When multiple solutions exist, prioritize in this order:
1. **Testability:** How easily can the solution be tested?
2. **Readability:** How easily will another developer understand this?
3. **Consistency:** Does it match existing patterns in the codebase?
4. **Simplicity:** Is it the least complex solution?
5. **Reversibility:** How easily can it be changed later?

## Workflow

### 1. Analysis Phase
[TODO: Describe how the agent should analyze the task]

### 2. Planning Phase
[TODO: Describe how the agent should plan the approach]

### 3. Execution Phase
[TODO: Describe the execution steps]

### 4. Validation Phase
[TODO: Describe how the agent validates its work]

## Expected Deliverables

When complete, provide:
- [ ] [TODO: Deliverable 1 - e.g., "Created file X with functionality Y"]
- [ ] [TODO: Deliverable 2]
- [ ] [TODO: Deliverable 3]
- [ ] Summary of changes made
- [ ] List of files created/modified
- [ ] Any follow-up actions needed from user

## Boundaries

**DO:**
- [TODO: What the agent should do]
- [TODO: Add more as needed]

**DO NOT:**
- [TODO: What the agent should NOT do]
- [TODO: Add more as needed]

**HAND BACK TO USER:**
- [TODO: When to ask for user input - e.g., "When architecture decision has multiple valid options"]
- [TODO: Add more as needed]

## Quick Reference

### Available Tools
- **Read, Write, Edit, MultiEdit**: File operations
- **Grep, Glob, LS**: Code exploration and search
- **Bash**: Command execution (use array syntax!)
- **Task**: Delegate to sub-agents
- **WebSearch, WebFetch**: Research (if enabled)

### File Locations
- Commands: `app/Commands/`
- Services: `app/Services/`
- Tests: `tests/Unit/`, `tests/Feature/`
- Stubs: `stubs/`

### Validation Commands
```bash
docker compose exec -T app composer test:unit   # Run tests
docker compose exec -T app composer test:types  # PHPStan check
docker compose exec -T app composer lint        # Fix code style
```
"""


def title_case_agent_name(agent_name):
    """Convert hyphenated agent name to Title Case for display."""
    return ' '.join(word.capitalize() for word in agent_name.split('-'))


def init_agent(agent_name, path):
    """
    Initialize a new agent file from template.

    Args:
        agent_name: Name of the agent (kebab-case)
        path: Path where the agent file should be created

    Returns:
        Path to created agent file, or None if error
    """
    # Determine agent file path
    agent_dir = Path(path).resolve()
    agent_file = agent_dir / f"{agent_name}.md"

    # Check if file already exists
    if agent_file.exists():
        print(f"❌ Error: Agent file already exists: {agent_file}")
        return None

    # Create directory if it doesn't exist
    agent_dir.mkdir(parents=True, exist_ok=True)

    # Create agent content from template
    agent_title = title_case_agent_name(agent_name)
    agent_content = AGENT_TEMPLATE.format(
        agent_name=agent_name,
        agent_title=agent_title
    )

    try:
        agent_file.write_text(agent_content)
        print(f"✅ Created agent file: {agent_file}")
    except Exception as e:
        print(f"❌ Error creating agent file: {e}")
        return None

    # Print next steps
    print(f"\n✅ Agent '{agent_name}' initialized successfully!")
    print("\nNext steps:")
    print("1. Edit the agent file to complete all TODO items")
    print("2. Update the description in frontmatter (triggers when agent is used)")
    print("3. Define the tools the agent needs")
    print("4. Choose the appropriate model (glm-5 for complex, glm-4 for simple)")
    print("5. Fill in the workflow and boundaries sections")
    print("6. Run the validator to check the agent structure")

    return agent_file


def validate_agent_name(agent_name):
    """Validate agent name follows conventions."""
    if not re.match(r'^[a-z0-9-]+$', agent_name):
        return False, "Agent name must be kebab-case (lowercase letters, digits, hyphens)"
    
    if agent_name.startswith('-') or agent_name.endswith('-'):
        return False, "Agent name cannot start or end with hyphen"
    
    if '--' in agent_name:
        return False, "Agent name cannot contain consecutive hyphens"
    
    if len(agent_name) > 64:
        return False, f"Agent name too long ({len(agent_name)} chars), max 64"
    
    if len(agent_name) < 3:
        return False, "Agent name too short, minimum 3 characters"
    
    return True, None


def main():
    if len(sys.argv) < 4 or sys.argv[2] != '--path':
        print("Usage: init_agent.py <agent-name> --path <path>")
        print("\nAgent name requirements:")
        print("  - Kebab-case identifier (e.g., 'stack-architect')")
        print("  - Lowercase letters, digits, and hyphens only")
        print("  - Min 3 characters, max 64 characters")
        print("  - Must match file name exactly (without .md)")
        print("\nExamples:")
        print("  init_agent.py stack-architect --path .claude/agents")
        print("  init_agent.py test-engineer --path .claude/agents")
        print("  init_agent.py code-auditor --path .claude/agents")
        sys.exit(1)

    agent_name = sys.argv[1]
    path = sys.argv[3]

    # Validate agent name
    is_valid, error = validate_agent_name(agent_name)
    if not is_valid:
        print(f"❌ Error: {error}")
        sys.exit(1)

    print(f"🚀 Initializing agent: {agent_name}")
    print(f"   Location: {path}")
    print()

    result = init_agent(agent_name, path)

    if result:
        sys.exit(0)
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()