#!/bin/bash
# Sync GitHub labels from labels.json
# Requires: gh CLI (https://cli.github.com/)

set -e

LABELS_FILE=".github/labels.json"
REPO="${1:-}"  # Optional: owner/repo format

if ! command -v gh &> /dev/null; then
    echo "Error: gh CLI is required. Install from https://cli.github.com/"
    exit 1
fi

if [ ! -f "$LABELS_FILE" ]; then
    echo "Error: $LABELS_FILE not found"
    exit 1
fi

echo "📦 Syncing GitHub labels..."

# Read labels from JSON
labels=$(cat "$LABELS_FILE")

# Get existing labels
existing_labels=$(gh label list --json name -q '.[].name' 2>/dev/null || echo "")

# Create or update each label
echo "$labels" | jq -c '.[]' | while read -r label; do
    name=$(echo "$label" | jq -r '.name')
    color=$(echo "$label" | jq -r '.color')
    description=$(echo "$label" | jq -r '.description')

    if echo "$existing_labels" | grep -q "^${name}$"; then
        echo "🔄 Updating label: $name"
        gh label edit "$name" --color "$color" --description "$description" 2>/dev/null || true
    else
        echo "✨ Creating label: $name"
        gh label create "$name" --color "$color" --description "$description" 2>/dev/null || true
    fi
done

echo "✅ Labels synced successfully!"
