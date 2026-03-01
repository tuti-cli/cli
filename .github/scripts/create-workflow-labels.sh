#!/bin/bash
# Create/update all GitHub labels for the v5 workflow system
# Usage: ./create-workflow-labels.sh

set -e

REPO="${1:-tuti-cli/cli}"

echo "==========================================="
echo "  Creating v5 Workflow Labels"
echo "  Repository: $REPO"
echo "==========================================="
echo ""

# Type Labels (Agent selection)
echo "Creating TYPE labels..."

create_label() {
    local name="$1"
    local color="$2"
    local description="$3"

    if gh label list --repo "$REPO" --json name -q ".[] | select(.name == \"$name\") | .name" | grep -q "$name"; then
        echo "  Updating: $name"
        gh label edit "$name" --repo "$REPO" --color "$color" --description "$description" 2>/dev/null || true
    else
        echo "  Creating: $name"
        gh label create "$name" --repo "$REPO" --color "$color" --description "$description" 2>/dev/null || true
    fi
}

# Type Labels
create_label "type:feature" "0e8a16" "New feature implementation"
create_label "type:bug" "d73a4a" "Bug fix"
create_label "type:enhancement" "a2eeef" "Feature enhancement or improvement"
create_label "type:chore" "7057ff" "Maintenance task"
create_label "type:security" "e11d21" "Security vulnerability or fix"
create_label "type:performance" "1d76db" "Performance optimization"
create_label "type:infra" "0db7ed" "Infrastructure or DevOps change"
create_label "type:architecture" "5319e7" "Architectural change or decision"
create_label "type:docs" "0075ca" "Documentation only change"
create_label "type:test" "fbca04" "Test coverage or testing change"

echo ""
echo "Creating WORKFLOW labels..."

# Workflow Type Labels
create_label "workflow:feature" "0e8a16" "Feature implementation pipeline"
create_label "workflow:bugfix" "d73a4a" "Bug fix pipeline"
create_label "workflow:refactor" "1d76db" "Refactor pipeline"
create_label "workflow:modernize" "5319e7" "Legacy migration pipeline"
create_label "workflow:task" "7057ff" "Simple task pipeline"

echo ""
echo "Creating PRIORITY labels..."

# Priority Labels
create_label "priority:critical" "e11d21" "Critical priority - execute immediately"
create_label "priority:high" "b60205" "High priority - this sprint"
create_label "priority:normal" "008672" "Normal priority - backlog"
create_label "priority:low" "cfd3d7" "Low priority - nice to have"

echo ""
echo "Creating STATUS labels..."

# Status Labels
create_label "status:ready" "0e8a16" "Ready to implement"
create_label "status:in-progress" "1d76db" "Work is in progress"
create_label "status:review" "fbca04" "PR exists, ready for review"
create_label "status:blocked" "e11d21" "Blocked by something else"
create_label "status:needs-confirmation" "d4c5f9" "Needs triage/confirmation"
create_label "status:needs-reproduction" "d4c5f9" "Needs a reproduction step"
create_label "status:rejected" "b60205" "Closed, will not implement"
create_label "status:done" "008672" "Completed and closed"
create_label "status:stale" "eeeeee" "No recent activity"

echo ""
echo "Creating ADDITIONAL labels..."

# Additional Labels
create_label "good first issue" "7057ff" "Good for newcomers"
create_label "help wanted" "008672" "Extra attention is needed"
create_label "breaking change" "e11d21" "Breaking change requiring major version bump"
create_label "dependencies" "0366d6" "Pull requests that update a dependency file"
create_label "php" "4f5b93" "PHP related changes"
create_label "docker" "0db7ed" "Docker related changes"
create_label "github-actions" "2088ff" "GitHub Actions related changes"

echo ""
echo "==========================================="
echo "  Label creation complete!"
echo "==========================================="
echo ""
echo "Summary of labels created:"
echo "  - 10 type labels (agent selection)"
echo "  - 5 workflow labels (pipeline routing)"
echo "  - 4 priority labels (execution priority)"
echo "  - 9 status labels (issue lifecycle)"
echo "  - 7 additional labels (miscellaneous)"
echo "  Total: 35 labels"
echo ""
echo "Labels can be viewed at:"
echo "  https://github.com/$REPO/labels"
