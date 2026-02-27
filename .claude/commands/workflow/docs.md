# workflow:docs
Update documentation after implementation. Invokes doc-updater to keep CHANGELOG, README, and inline docs synchronized.

**Usage:**
- `/workflow:docs` — Update docs for recent changes
- `/workflow:docs --all` — Full documentation review and update
- `/workflow:docs --changelog` — Update CHANGELOG only
- `/workflow:docs --readme` — Update README only

**Documentation Types:**
- CHANGELOG.md — Always updated for every change
- README.md — Updated for user-facing changes
- Inline docs — Docblocks and comments
- API docs — Command help text

**Update Triggers:**
- New command added
- Command signature changed
- New configuration option
- Bug fix shipped
- Breaking change

Invoke `doc-updater`:
> "Update documentation after implementation. GITHUB REPO: owner=tuti-cli repo=cli. Analyze recent changes (git diff or last commit). IF --changelog: update CHANGELOG.md only. IF --readme: update README.md only. IF --all: full documentation review. ELSE: update relevant documentation based on change type. Update CHANGELOG with appropriate category (Added/Changed/Fixed/Deprecated/Removed/Security). Link to issue number. Update README if user-facing change. Update inline docblocks if signatures changed. Verify documentation accuracy."
