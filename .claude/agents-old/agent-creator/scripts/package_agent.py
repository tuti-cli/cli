#!/usr/bin/env python3
"""
Agent Packager - Creates a distributable .agent file

Usage:
    package_agent.py <agent-file-path> [output-directory]

Example:
    package_agent.py .claude/agents/stack-architect.md
    package_agent.py .claude/agents/stack-architect.md ./dist
"""

import sys
import zipfile
from pathlib import Path
from validate_agent import validate_agent


def package_agent(agent_path, output_dir=None):
    """
    Package an agent file into a .agent file (zip format).

    Args:
        agent_path: Path to the agent .md file
        output_dir: Optional output directory for the .agent file

    Returns:
        Path to the created .agent file, or None if error
    """
    agent_path = Path(agent_path).resolve()

    # Validate agent file exists
    if not agent_path.exists():
        print(f"❌ Error: Agent file not found: {agent_path}")
        return None

    if not agent_path.is_file():
        print(f"❌ Error: Path is not a file: {agent_path}")
        return None

    # Run validation before packaging
    print("🔍 Validating agent...")
    valid, message = validate_agent(agent_path)
    if not valid:
        print(f"❌ Validation failed: {message}")
        print("   Please fix the validation errors before packaging.")
        return None
    print(f"✅ {message}\n")

    # Determine output location
    agent_name = agent_path.stem  # filename without extension
    if output_dir:
        output_path = Path(output_dir).resolve()
        output_path.mkdir(parents=True, exist_ok=True)
    else:
        output_path = agent_path.parent

    agent_filename = output_path / f"{agent_name}.agent"

    # Create the .agent file (zip format)
    try:
        with zipfile.ZipFile(agent_filename, 'w', zipfile.ZIP_DEFLATED) as zipf:
            # Add the agent file as AGENT.md in the archive
            zipf.write(agent_path, 'AGENT.md')
            print(f"  Added: AGENT.md")

        print(f"\n✅ Successfully packaged agent to: {agent_filename}")
        return agent_filename

    except Exception as e:
        print(f"❌ Error creating .agent file: {e}")
        return None


def main():
    if len(sys.argv) < 2:
        print("Usage: package_agent.py <agent-file-path> [output-directory]")
        print("\nExample:")
        print("  package_agent.py .claude/agents/stack-architect.md")
        print("  package_agent.py .claude/agents/stack-architect.md ./dist")
        sys.exit(1)

    agent_path = sys.argv[1]
    output_dir = sys.argv[2] if len(sys.argv) > 2 else None

    print(f"📦 Packaging agent: {agent_path}")
    if output_dir:
        print(f"   Output directory: {output_dir}")
    print()

    result = package_agent(agent_path, output_dir)

    if result:
        sys.exit(0)
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()