#!/bin/bash
# Claude Code output validation hook for tuti-cli
# Truncates large outputs to save tokens

# Read JSON input from stdin
INPUT=$(cat)

# Get the output from the command result
OUTPUT=$(echo "$INPUT" | jq -r '.result.stdout // empty')
STDERR=$(echo "$INPUT" | jq -r '.result.stderr // empty')

# Maximum lines to keep (adjust as needed)
MAX_LINES=100

# Count lines
LINE_COUNT=$(echo "$OUTPUT" | wc -l)

# If output is too large, warn but allow
if [ "$LINE_COUNT" -gt "$MAX_LINES" ]; then
  echo "⚠️  Output truncated from $LINE_COUNT to $MAX_LINES lines to save tokens" >&2
fi

exit 0
