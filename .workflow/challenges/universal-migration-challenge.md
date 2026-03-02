# Architecture Challenge: Universal Migration/Backup System

**Date:** 2026-03-02
**Challenger:** architecture-challenger
**Proposal:** Option A - Interface-Based Abstraction with Unified Archive Format

---

## Executive Summary

This challenge analyzes the proposal for a universal migration/backup system using `MigrationExporterInterface` with per-stack implementations and a unified `.tuti` archive format. While the interface-based approach follows established patterns (`StackInstallerInterface`), significant concerns exist around large file handling, database consistency, secret management, and failure recovery.

**Verdict: Proposal has significant concerns requiring redesign**

---

## Assumptions Questioned

### Assumption 1: Tar-based archiving is sufficient for all backup sizes

**Original:** The proposal assumes a unified tar-based `.tuti` archive format works for all projects.

**Challenge:**
- WordPress media libraries routinely exceed 10GB
- Laravel storage directories with user uploads can reach 50GB+
- Tar requires loading entire file into memory for compression (unless streamed carefully)
- PHAR memory limits may conflict with large archive operations

**Risk:** High
**Mitigation:**
- Implement streaming archive generation (phar:// wrapper won't work for this)
- Consider chunked archives for large datasets
- Add size estimation before archive creation with user confirmation
- Implement `--max-size` with automatic exclusion/chunking

---

### Assumption 2: Database exports are atomic operations

**Original:** Database dumps via `mysqldump`/`pg_dump` are treated as consistent snapshots.

**Challenge:**
- No mention of transaction isolation levels
- Active writes during dump create inconsistent state
- Foreign key constraints may be violated in partial restores
- InnoDB buffer pool state not captured

**Risk:** High
**Mitigation:**
- Use `--single-transaction` for InnoDB (MySQL)
- Use `--snapshot` for PostgreSQL
- Lock tables for MyISAM (with warning about downtime)
- Document that writes should be paused for consistency
- Consider binlog/WAL archiving for point-in-time recovery

---

### Assumption 3: Single `.env` file contains all secrets

**Original:** Secrets are stored in `.env` and will be included in archive.

**Challenge:**
- `.env` may contain production database credentials
- API keys for third-party services
- Stripe/payment provider keys
- WordPress authentication salts
- Archives stored in potentially insecure locations (project directory, user home)

**Risk:** Critical
**Mitigation:**
- **NEVER** include `.env` in archive without encryption
- Add `--include-env` flag with explicit user opt-in and warning
- Consider age/NaCl encryption for sensitive archives
- Store secrets separately from data
- Mask secrets in manifest.json (show key names, not values)
- Add `--secrets-only` mode for credential export with extra encryption

---

### Assumption 4: Cross-environment restore always works

**Original:** A backup from one environment can be restored to another.

**Challenge:**
- Docker MySQL backup to non-Docker MySQL may have path differences
- Volume mount paths differ between environments
- Storage paths in database content (WordPress uploads, Laravel storage)
- Different PHP/Node versions between environments
- Different service versions (MySQL 5.7 vs 8.0, PostgreSQL 14 vs 16)

**Risk:** High
**Mitigation:**
- Store environment metadata in manifest.json:
  - Stack type and version
  - Service versions (PHP, MySQL, PostgreSQL, etc.)
  - Docker Compose version
  - Volume mount paths
- Validate compatibility before restore
- Warn on version mismatches
- Provide migration scripts for version upgrades
- Store absolute paths in database content and provide search-replace tooling

---

### Assumption 5: All stack exporters implement the interface correctly

**Original:** Each stack implements `MigrationExporterInterface` and produces consistent output.

**Challenge:**
- No validation of exporter output format
- Laravel exporter might include storage/symlinks differently than WordPress
- Inconsistent file path handling across exporters
- No standardized error handling across implementations

**Risk:** Medium
**Mitigation:**
- Define strict schema for exporter output
- Add contract tests that all exporters must pass
- Include exporter version in manifest.json
- Add validation step before archive creation
- Define minimum data that MUST be included (database, config)

---

## Weaknesses Identified

### Weakness 1: No partial restore capability

**Affects:** All use cases
**Severity:** High
**Description:** The proposal describes full backup/restore only. Real-world scenarios often need:
- Database-only restore (files untouched)
- Files-only restore (database untouched)
- Single table/collection restore
- Exclude large directories from backup

**Scenario:** Developer corrupts one table and wants to restore just that table from last night's backup. Currently, they would have to:
1. Extract entire archive to temp location
2. Manually extract the SQL for that table
3. Restore manually

This defeats the purpose of a CLI tool.

---

### Weakness 2: No concurrent operation protection

**Affects:** All commands
**Severity:** High
**Description:** What happens when:
- User runs `backup:create` while `local:start` is initializing containers?
- User runs `backup:restore` while application is actively writing to database?
- Two `backup:create` commands run simultaneously?

**Scenario:** User starts backup, then realizes they forgot to stop a background job. The backup contains partially-written data. Restore is corrupt.

---

### Weakness 3: No incremental backup support

**Affects:** Large projects
**Severity:** Medium
**Description:** Full backups for 50GB+ projects are:
- Slow (30+ minutes)
- Storage-intensive (50GB per backup)
- Network-intensive for remote storage

**Scenario:** A daily backup schedule would consume 1.5TB per month for a single large project. Users will disable backups.

---

### Weakness 4: No progress indication or cancellation

**Affects:** All backup operations
**Severity:** Medium
**Description:** Long-running operations need:
- Progress bars with file/byte counts
- Time estimates
- Graceful cancellation (Ctrl+C) that cleans up partial archives
- Resume capability for interrupted operations

**Scenario:** User starts backup of 20GB project, realizes after 10 minutes they need to leave. Ctrl+C leaves partial archive and temp files.

---

### Weakness 5: No storage location configuration

**Affects:** All backups
**Severity:** Low
**Description:** Where are backups stored?
- Project directory? (Committed to git by accident)
- `~/.tuti/backups/`? (Fills up home partition)
- Custom path? (Not configurable)
- Remote storage? (S3, SFTP - not mentioned)

**Scenario:** Developer's home partition fills with backups, crashing other applications.

---

### Weakness 6: No backup rotation/retention policy

**Affects:** Storage management
**Severity:** Medium
**Description:** Without retention policies:
- Backups accumulate indefinitely
- Disk fills
- Old backups become irrelevant (schema changes)

**Scenario:** Project has 500 backups from 2 years of development. Restoring requires manually searching through hundreds of files.

---

## Edge Cases

### Edge Case 1: Symlinks in project directory

**Scenario:** Laravel storage symlinks `public/storage` to `storage/app/public`
**Expected Behavior:** Symlinks preserved or dereferenced consistently
**Proposed Behavior:** Not specified
**Gap:** Tar handles symlinks, but:
- Cross-filesystem symlinks break
- Absolute symlinks break when extracted elsewhere
- Circular symlinks cause infinite loops

---

### Edge Case 2: Empty database

**Scenario:** Fresh project with migrations not yet run
**Expected Behavior:** Backup succeeds, restore works
**Proposed Behavior:** Not specified
**Gap:** `mysqldump` on empty database may produce different output than expected. Restore to non-empty database may fail.

---

### Edge Case 3: Multiple databases

**Scenario:** Project uses both MySQL and Redis, or multiple MySQL databases
**Expected Behavior:** Both backed up consistently
**Proposed Behavior:** Interface appears to assume single database
**Gap:** No mention of multi-database projects (e.g., read replicas, separate auth database)

---

### Edge Case 4: Database container not running

**Scenario:** User runs `backup:create` on stopped project
**Expected Behavior:** Clear error message or auto-start
**Proposed Behavior:** Not specified
**Gap:** `docker compose exec` on stopped container fails with cryptic error

---

### Edge Case 5: Insufficient disk space

**Scenario:** Creating backup with less than 2x project size free
**Expected Behavior:** Pre-flight check with clear error
**Proposed Behavior:** Not specified
**Gap:** Archive creation fails partway through, leaving corrupt partial archive

---

### Edge Case 6: Windows line endings

**Scenario:** Project developed on Windows with CRLF line endings
**Expected Behavior:** Line endings preserved
**Proposed Behavior:** Not specified
**Gap:** Git may have converted to LF, but database content may contain CRLF. Restore to Linux container may have issues.

---

### Edge Case 7: Docker named volumes vs bind mounts

**Scenario:** PostgreSQL data in named volume, not bind mount
**Expected Behavior:** Volume data included in backup
**Proposed Behavior:** Not specified
**Gap:** `docker compose exec postgres pg_dump` handles database, but what about `docker volume` data that's not database?

---

## Failure Modes

### Failure Mode 1: Restore fails halfway through

**Trigger:** Disk fills during restore, or corrupted archive
**Impact:** Database partially restored, files partially extracted, project in broken state
**Recovery:** Not specified - user must manually clean up
**Prevention:**
- Pre-flight disk space check
- Atomic restore (extract to temp, move into place)
- Transaction-wrapped database restore with rollback
- Automatic backup of current state before restore

---

### Failure Mode 2: Archive corruption

**Trigger:** Bit rot, incomplete transfer, storage failure
**Impact:** Archive cannot be extracted
**Recovery:** No recovery without backup of backup
**Prevention:**
- Checksums (SHA-256) in manifest and as separate file
- PAR2 redundancy data for recovery
- Multiple archive copies to different locations
- Archive verification command (`backup:verify`)

---

### Failure Mode 3: Version incompatibility

**Trigger:** Restoring backup from Tuti 1.0 with Tuti 2.0
**Impact:** Restore fails or produces incorrect results
**Recovery:** Keep old Tuti version available (not practical)
**Prevention:**
- Version field in manifest.json
- Backward compatibility layer for old formats
- Warning before restore if version mismatch
- Migration scripts for format upgrades

---

### Failure Mode 4: Database schema mismatch

**Trigger:** Restoring database backup to project with different migrations
**Impact:** Foreign key errors, missing columns, application crashes
**Recovery:** Run migrations after restore (may fail)
**Prevention:**
- Store migration state in manifest
- Validate schema version before restore
- Option to restore migrations alongside data
- Warning if target has unapplied migrations

---

### Failure Mode 5: Out-of-memory during archive

**Trigger:** Large project on machine with limited RAM
**Impact:** PHP process killed, partial archive, temp files left
**Recovery:** Delete temp files, try again with more memory or streaming
**Prevention:**
- Streaming archive operations (no in-memory tar)
- Memory limit detection with warning
- Chunk large files
- Temp file cleanup on failure

---

## Security Concerns

### Concern 1: Secrets in archives

**Vulnerability:** Database credentials, API keys, salts stored in archive
**Attack Vector:**
- Archive file copied to unsecured location
- Archive uploaded to cloud storage without encryption
- Archive committed to git repository
- Archive extracted on shared server

**Mitigation:**
- **Default: Exclude `.env` from archives**
- `--include-env` requires explicit confirmation
- `--encrypt` flag for password-based encryption (age or NaCl)
- Mask sensitive values in manifest display
- Security warning in backup:create output

---

### Concern 2: Database content injection

**Vulnerability:** Malicious data in database could execute during restore
**Attack Vector:**
- SQL injection payloads in restored data
- Serialized objects with malicious payloads (Laravel/WordPress)
- Path traversal in file uploads

**Mitigation:**
- Restore using `--no-privileges` to prevent escalation
- Validate SQL before execution
- Sanitize serialized data
- Run in isolated transaction

---

### Concern 3: Archive tampering

**Vulnerability:** Man-in-the-middle modifies archive during transfer
**Attack Vector:**
- Malicious archive replaces legitimate backup
- Backdoor inserted via modified restore

**Mitigation:**
- Sign archives with ed25519 key
- Verify signature before restore
- Checksum validation mandatory
- `backup:verify` command for integrity check

---

### Concern 4: Path traversal in archives

**Vulnerability:** Maliciously crafted archive extracts outside project directory
**Attack Vector:** Archive contains `../../../etc/passwd`
**Mitigation:**
- Validate all paths are within project root
- Use PharData with proper path checking
- Reject archives with absolute paths
- Reject archives with `..` in paths

---

## Suggestions

### Improvement 1: Add layered backup strategy

**For:** Backup format
**Suggestion:**
```
.tuti/
  manifest.json          # Metadata, checksums, versions
  database/
    mysql.sql.gz         # Compressed database dump
    postgres.sql.gz      # (if multiple databases)
  files/
    storage.tar.gz       # User uploads
    config.tar.gz        # Config files (excluding .env)
  env/
    .env.encrypted       # Optional encrypted secrets
```

**Tradeoff:** More complex structure, but enables:
- Partial restores (database only, files only)
- Incremental backups (only changed layers)
- Selective exclusion (skip storage on quick backups)

---

### Improvement 2: Add pre-flight validation

**For:** All backup/restore commands
**Suggestion:**
```php
interface BackupPreflightInterface {
    public function checkDiskSpace(string $path): bool;
    public function checkContainersRunning(): bool;
    public function estimateBackupSize(): int;
    public function validateRestoreTarget(): array;
    public function checkVersionCompatibility(Manifest $manifest): bool;
}
```

**Tradeoff:** More code, but prevents common failures

---

### Improvement 3: Implement atomic restore

**For:** Restore operations
**Suggestion:**
1. Create restore point of current state
2. Extract to temp directory
3. Validate extracted content
4. Begin database transaction
5. Restore database
6. Move files into place (atomic on same filesystem)
7. Commit transaction
8. On failure: rollback transaction, restore from restore point

**Tradeoff:** Requires 2x disk space during restore, but guarantees consistency

---

### Improvement 4: Add streaming archive support

**For:** Large file handling
**Suggestion:**
```php
interface StreamingArchiveInterface {
    public function createStream(): \Generator;
    public function addFile(string $path, int $size): void;
    public function addDirectory(string $path): void;
    public function finalize(): string; // Returns checksum
}
```

Use `PharData` with `addFile()` method for memory-efficient archiving.

**Tradeoff:** More complex implementation, but handles arbitrary sizes

---

### Improvement 5: Add backup profile system

**For:** Flexible backup configuration
**Suggestion:**
```json
// .tuti/backup-profiles.json
{
  "quick": {
    "include": ["database"],
    "exclude": ["storage", "node_modules"]
  },
  "full": {
    "include": ["database", "storage", "config"],
    "encrypt": true
  },
  "migration": {
    "include": ["database", "storage"],
    "exclude-env": false,
    "sanitize-urls": true
  }
}
```

**Tradeoff:** More configuration, but covers more use cases

---

### Improvement 6: Add manifest-first design

**For:** Archive format
**Suggestion:** Require manifest.json to be created FIRST, before any data:
```json
{
  "version": "1.0.0",
  "tuti_version": "2.1.0",
  "created_at": "2026-03-02T10:30:00Z",
  "stack": "laravel",
  "services": {
    "php": "8.4",
    "mysql": "8.0",
    "redis": "7.0"
  },
  "components": {
    "database": {
      "type": "mysql",
      "size": 52428800,
      "checksum": "sha256:abc123...",
      "tables": ["users", "posts", ...]
    },
    "files": {
      "storage": {
        "size": 1073741824,
        "checksum": "sha256:def456...",
        "file_count": 1523
      }
    }
  },
  "checksums": {
    "manifest": "sha256:ghi789..."
  }
}
```

**Tradeoff:** More metadata overhead, but enables validation, partial restore, and versioning

---

## Proposed Interface Redesign

Based on the above challenges, here's a more robust interface design:

```php
interface MigrationExporterInterface
{
    // Metadata
    public function getIdentifier(): string;
    public function getVersion(): string;
    public function getRequiredServices(): array;

    // Pre-flight
    public function canExport(): bool;
    public function estimateSize(): int;
    public function validateExportTarget(): array;

    // Export (streaming)
    public function exportDatabase(string $path): bool;
    public function exportFiles(string $path, array $include = [], array $exclude = []): bool;
    public function exportConfig(string $path, bool $includeSecrets = false): bool;

    // Import
    public function canImport(Manifest $manifest): bool;
    public function validateImportSource(Manifest $manifest): array;
    public function importDatabase(string $path): bool;
    public function importFiles(string $path): bool;
    public function importConfig(string $path): bool;

    // Cleanup
    public function rollbackImport(): bool;
    public function cleanup(): void;
}

interface ArchiveServiceInterface
{
    public function create(Manifest $manifest, string $outputPath): string;
    public function extract(string $archivePath, string $targetPath): Manifest;
    public function verify(string $archivePath): bool;
    public function listContents(string $archivePath): array;
    public function extractPartial(string $archivePath, string $targetPath, array $components): Manifest;
}
```

---

## Conclusion

**Verdict:**
- [ ] Proposal is sound as-is
- [ ] Proposal needs minor adjustments
- [x] Proposal has significant concerns
- [ ] Proposal should be rejected

**Reasoning:**

The proposal's interface-based approach follows established patterns in the codebase, which is good. However, significant gaps exist:

**Critical Issues (Must Address):**
1. **Secret Management:** Storing `.env` in archives without encryption is a security vulnerability
2. **Large File Handling:** No streaming, no chunking, no size limits - will fail for production workloads
3. **Database Consistency:** No transaction handling, no locking, inconsistent snapshots likely
4. **Failure Recovery:** No atomic restores, no rollback, no cleanup of partial operations

**High Priority Issues:**
1. **Partial Restore:** Cannot restore database without files or vice versa
2. **Concurrent Operations:** No protection against simultaneous operations
3. **Version Compatibility:** No handling of version mismatches between backup and restore
4. **Cross-Environment:** No handling of path differences, version differences

**Recommended Path Forward:**

1. **Phase 1 - Core Safety:**
   - Implement manifest-first design
   - Add secret exclusion by default with `--include-env` opt-in
   - Implement streaming archive operations
   - Add pre-flight validation (disk space, container status)
   - Implement atomic restore with rollback

2. **Phase 2 - Consistency:**
   - Add transaction-wrapped database restore
   - Implement lock mechanisms for concurrent operations
   - Add progress indication and cancellation
   - Implement backup verification

3. **Phase 3 - Advanced Features:**
   - Add partial restore capability
   - Implement backup profiles
   - Add incremental backup support
   - Implement remote storage backends

4. **Phase 4 - Developer Experience:**
   - Add backup rotation/retention
   - Implement cross-environment migration helpers
   - Add backup scheduling

---

**Challenge Completed:** 2026-03-02
**Reviewed by:** architecture-challenger
