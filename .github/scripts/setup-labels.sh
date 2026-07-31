#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# setup-labels.sh
# Creates workflow-kit v2 label system on any GitHub repo.
#
# Usage:
#   .github/scripts/setup-labels.sh               # uses current repo (gh auth required)
#   .github/scripts/setup-labels.sh owner/repo    # explicit target
#
# Requirements: gh CLI installed + authenticated (gh auth login)
# ─────────────────────────────────────────────────────────────────────────────

set -e

REPO=${1:-$(gh repo view --json nameWithOwner -q .nameWithOwner 2>/dev/null || echo "")}

if [ -z "$REPO" ]; then
    echo "Failed to detect repo. Run: gh auth login, or pass repo as argument"
    echo "   Usage: .github/scripts/setup-labels.sh owner/repo"
    exit 1
fi

echo "Setting up workflow-kit v2 labels on: $REPO"
echo ""

create_label() {
    local name="$1" color="$2" description="$3"
    gh label create "$name" \
        --color "$color" \
        --description "$description" \
        --repo "$REPO" \
        --force 2>/dev/null \
        && echo "  OK $name" \
        || echo "  SKIP $name"
}

# ── WORKFLOW (drives pipeline selection) ─────────────────────────────────────
echo "Workflow labels (drives pipeline)"
create_label "workflow:feature"   "0075ca" "Feature pipeline — full implementation with review"
create_label "workflow:bugfix"   "d73a4a" "Bug fix pipeline — fix + regression test + patch"
create_label "workflow:refactor"  "e4e669" "Refactor pipeline — behaviour-preserving changes"
create_label "workflow:modernize" "8250df" "Modernize pipeline — legacy migration with backward compat"
create_label "workflow:task"      "bfd4f2" "Task pipeline — simple atomic task"

# ── TYPE (describes what kind of work) ────────────────────────────────────────
echo ""
echo "Type labels (describes work)"
create_label "type:feature"      "0075ca" "New feature or improvement"
create_label "type:bug"          "d73a4a" "Something is broken"
create_label "type:chore"        "e4e669" "Refactor, tooling, deps, config"
create_label "type:docs"         "0052cc" "Documentation"
create_label "type:security"      "b60205" "Security vulnerability or hardening"
create_label "type:performance"   "f9d0c4" "Performance optimization"
create_label "type:infra"         "0e8a16" "Infrastructure, CI/CD, DevOps"
create_label "type:test"          "bfd4f2" "Testing, coverage, QA"

# ── PRIORITY ─────────────────────────────────────────────────────────────────
echo ""
echo "Priority labels"
create_label "priority:critical"  "b60205" "Drop everything — production broken"
create_label "priority:high"     "e99695" "Urgent, next sprint"
create_label "priority:medium"   "f9d0c4" "Normal priority"
create_label "priority:low"      "fef2c0" "Nice to have, no deadline"

# ── STATUS ────────────────────────────────────────────────────────────────────
echo ""
echo "Status labels"
create_label "status:ready"          "0e8a16" "Groomed, ready to pick up"
create_label "status:in-progress"    "1d76db" "Someone is working on this"
create_label "status:review"         "8250df" "PR open, needs code review"
create_label "status:blocked"         "e11d48" "Waiting on something external"
create_label "status:needs-confirmation" "f97316" "External issue, needs triage"

# ── CLEANUP — remove GitHub defaults ────────────────────────────────────────
echo ""
echo "Removing conflicting GitHub defaults..."
for label in "bug" "documentation" "duplicate" "enhancement" \
             "good first issue" "help wanted" "invalid" "question" "wontfix"; do
    gh label delete "$label" --repo "$REPO" --yes 2>/dev/null \
        && echo "  REMOVED $label" || true
done

echo ""
echo "Done! Labels created on $REPO"
echo ""
echo "Suggested GitHub Project board columns:"
echo "  Inbox | Ready | In Progress | Blocked | In Review | Done"
