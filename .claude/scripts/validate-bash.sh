#!/bin/bash
# Claude Code Bash validation hook for tuti-cli
# Blocks access to large/binary/sensitive directories to save tokens

# Read JSON input from stdin
INPUT=$(cat)

# Extract the command from JSON
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // empty')

# If no command found, allow it
if [ -z "$COMMAND" ]; then
  exit 0
fi

# Define forbidden patterns for tuti-cli project
FORBIDDEN_PATTERNS=(
  # Dependency directories (very large)
  "vendor/"
  "node_modules/"

  # Build artifacts
  "builds/"
  "builds/build/"

  # IDE/Editor configs
  "\.idea/"
  "\.vscode/"

  # Git internals
  "\.git/"

  # Cache/temp directories
  "\.phpunit\.cache"
  "\.tmp/"
  "storage/logs/"

  # Environment files
  "\.env$"
  "\.env\."

  # Binary/compiled files
  "\.phar$"
  "\.sqlite$"

  # Lock files (large, not useful for context)
  "composer\.lock$"

  # Docker volumes/data
  "\.tuti/"

  # Log files
  "\.log$"
)

# Check if command contains any forbidden patterns
for pattern in "${FORBIDDEN_PATTERNS[@]}"; do
  if echo "$COMMAND" | grep -qE "$pattern"; then
    echo "ERROR: Access to '$pattern' is blocked by security policy to save tokens" >&2
    exit 2  # Exit code 2 = blocking error
  fi
done

# Command is clean, allow it
exit 0
