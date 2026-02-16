#!/bin/bash
# Sync GitHub labels from labels.json
# Requires: gh CLI (https://cli.github.com/), jq

set -e

LABELS_FILE=".github/labels.json"
REPO="${1:-}"

# Check dependencies
if ! command -v gh &> /dev/null; then
    echo "Error: gh CLI is required. Install from https://cli.github.com/"
    exit 1
fi

if ! command -v jq &> /dev/null; then
    echo "Error: jq is required. Install from https://stedolan.github.io/jq/"
    exit 1
fi

if [ ! -f "$LABELS_FILE" ]; then
    echo "Error: $LABELS_FILE not found"
    exit 1
fi

# Build repo flag
if [ -n "$REPO" ]; then
    REPO_ARGS=("--repo" "$REPO")
    echo "📦 Syncing labels to repository: $REPO"
else
    REPO_ARGS=()
    echo "📦 Syncing labels to current repository"
fi

# Get existing labels
echo "🔍 Fetching existing labels..."
if ! existing_labels=$(gh label list "${REPO_ARGS[@]}" --json name -q '.[].name'); then
    echo "❌ Error: Failed to list labels. Make sure you have access to the repository."
    exit 1
fi
echo "✓ Found $(echo "$existing_labels" | wc -l) existing labels"

# Read labels from JSON
labels=$(cat "$LABELS_FILE")
label_count=$(echo "$labels" | jq '. | length')
echo "📝 Processing $label_count labels from $LABELS_FILE..."

# Create or update each label
echo "$labels" | jq -c '.[]' | while read -r label; do
    name=$(echo "$label" | jq -r '.name')
    color=$(echo "$label" | jq -r '.color')
    description=$(echo "$label" | jq -r '.description')

    if echo "$existing_labels" | grep -q "^${name}$"; then
        echo "🔄 Updating label: $name"
        if gh label edit "$name" "${REPO_ARGS[@]}" --color "$color" --description "$description"; then
            echo "  ✓ Updated successfully"
        else
            echo "  ⚠️  Failed to update"
        fi
    else
        echo "✨ Creating label: $name"
        if gh label create "$name" "${REPO_ARGS[@]}" --color "$color" --description "$description"; then
            echo "  ✓ Created successfully"
        else
            echo "  ⚠️  Failed to create"
        fi
    fi
done

echo "✅ Labels synced successfully!"
