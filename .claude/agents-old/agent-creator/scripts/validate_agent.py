#!/usr/bin/env python3
"""
Agent Validator - Validates agent file structure and content

Usage:
    validate_agent.py <agent-file-path>

Example:
    validate_agent.py .claude/agents/stack-architect.md
"""

import sys
import re
import yaml
from pathlib import Path


def validate_agent(agent_path):
    """
    Validate an agent file.
    
    Args:
        agent_path: Path to the agent .md file
        
    Returns:
        Tuple of (is_valid, message)
    """
    agent_path = Path(agent_path)
    
    # Check file exists
    if not agent_path.exists():
        return False, f"Agent file not found: {agent_path}"
    
    if not agent_path.is_file():
        return False, f"Path is not a file: {agent_path}"
    
    if not agent_path.suffix == '.md':
        return False, f"Agent file must be a .md file: {agent_path}"
    
    # Read content
    try:
        content = agent_path.read_text()
    except Exception as e:
        return False, f"Error reading file: {e}"
    
    # Check frontmatter exists
    if not content.startswith('---'):
        return False, "No YAML frontmatter found (must start with ---)"
    
    # Extract frontmatter
    match = re.match(r'^---\n(.*?)\n---', content, re.DOTALL)
    if not match:
        return False, "Invalid frontmatter format (must be ---\\n<yaml>\\n---)"
    
    frontmatter_text = match.group(1)
    
    # Parse YAML
    try:
        frontmatter = yaml.safe_load(frontmatter_text)
        if not isinstance(frontmatter, dict):
            return False, "Frontmatter must be a YAML dictionary"
    except yaml.YAMLError as e:
        return False, f"Invalid YAML in frontmatter: {e}"
    
    # Define required and allowed properties
    REQUIRED_PROPERTIES = {'name', 'description', 'tools', 'model'}
    ALLOWED_PROPERTIES = REQUIRED_PROPERTIES
    
    # Check required fields
    missing = REQUIRED_PROPERTIES - set(frontmatter.keys())
    if missing:
        return False, f"Missing required field(s): {', '.join(sorted(missing))}"
    
    # Check for unexpected properties
    unexpected = set(frontmatter.keys()) - ALLOWED_PROPERTIES
    if unexpected:
        return False, f"Unexpected field(s): {', '.join(sorted(unexpected))}"
    
    # Validate name
    name = frontmatter.get('name', '')
    if not isinstance(name, str):
        return False, f"'name' must be a string, got {type(name).__name__}"
    name = name.strip()
    if not name:
        return False, "'name' cannot be empty"
    if not re.match(r'^[a-z0-9-]+$', name):
        return False, f"'name' must be kebab-case (lowercase letters, digits, hyphens): {name}"
    if name.startswith('-') or name.endswith('-') or '--' in name:
        return False, f"'name' cannot start/end with hyphen or have consecutive hyphens: {name}"
    if len(name) > 64:
        return False, f"'name' too long ({len(name)} chars), max 64"
    
    # Validate description
    description = frontmatter.get('description', '')
    if not isinstance(description, str):
        return False, f"'description' must be a string, got {type(description).__name__}"
    description = description.strip()
    if not description:
        return False, "'description' cannot be empty"
    if len(description) > 1024:
        return False, f"'description' too long ({len(description)} chars), max 1024"
    
    # Validate tools
    tools = frontmatter.get('tools', [])
    if isinstance(tools, str):
        # Parse bracketed list like "[Read, Write, Bash]"
        tools_str = tools.strip()
        if tools_str.startswith('[') and tools_str.endswith(']'):
            tools_str = tools_str[1:-1]
            tools = [t.strip() for t in tools_str.split(',') if t.strip()]
        else:
            tools = [tools_str]
    
    if not isinstance(tools, (list, tuple)):
        return False, f"'tools' must be a list, got {type(tools).__name__}"
    if not tools:
        return False, "'tools' cannot be empty"
    
    # Validate model
    model = frontmatter.get('model', '')
    if not isinstance(model, str):
        return False, f"'model' must be a string, got {type(model).__name__}"
    model = model.strip()
    if not model:
        return False, "'model' cannot be empty"
    
    VALID_MODELS = {'glm-5', 'glm-4', 'claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'}
    if model not in VALID_MODELS:
        return False, f"Unknown model '{model}'. Valid models: {', '.join(sorted(VALID_MODELS))}"
    
    # Check body content exists
    body_match = re.search(r'^---\n.*?\n---\n+(.+)', content, re.DOTALL)
    if not body_match:
        return False, "Agent must have body content after frontmatter"
    
    body = body_match.group(1).strip()
    if len(body) < 100:
        return False, "Agent body content is too short (min 100 characters)"
    
    # Check for required sections in body
    required_sections = ['Role', 'Workflow', 'Expected Deliverables']
    for section in required_sections:
        if section not in body:
            return False, f"Agent body missing required section: {section}"
    
    return True, "Agent is valid!"


def main():
    if len(sys.argv) != 2:
        print("Usage: validate_agent.py <agent-file-path>")
        print("\nExample:")
        print("  validate_agent.py .claude/agents/stack-architect.md")
        sys.exit(1)
    
    agent_path = sys.argv[1]
    
    print(f"🔍 Validating agent: {agent_path}")
    print()
    
    valid, message = validate_agent(agent_path)
    
    if valid:
        print(f"✅ {message}")
        sys.exit(0)
    else:
        print(f"❌ {message}")
        sys.exit(1)


if __name__ == "__main__":
    main()