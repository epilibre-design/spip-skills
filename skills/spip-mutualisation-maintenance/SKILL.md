---
name: spip-mutualisation-maintenance
description: Use when diagnosing or preparing maintenance of an existing SPIP 4.2–4.4 farm using Mutualisation facile 2.x, including inventory, shared core or plugin upgrades, MariaDB/MySQL or SQLite backup and rollback, and compromise investigation.
---

# SPIP mutualisation maintenance

## Core principle

Establish the topology before choosing commands. Read-only observations may be run when terminal access is available. **Never execute a state-changing command:** present it for the operator to review and run.

A single SPIP core and shared plugin tree can serve every site, while each site keeps its own configuration, database, `IMG/`, `local/`, and `tmp/`. Always state whether an observation or proposed change affects one site, several sites, the shared farm, or the host.

## Supported scope

- SPIP 4.2–4.4 with Mutualisation facile 2.x
- Linux with a detected Apache or Nginx/PHP-FPM stack
- MariaDB/MySQL and SQLite, detected per site
- Existing farms: inventory, diagnosis, core/plugins upgrade preparation, backup/rollback, and compromise investigation

SPIP 3, PostgreSQL, new-farm installation, and site creation/migration/deletion are outside this skill. For them, offer only a bounded read-only inventory and require a separately researched procedure for the exact legacy environment.

## Mandatory workflow

1. Resolve the candidate scope and read [inventory-diagnosis.md](references/inventory-diagnosis.md).
2. Detect the shared root, the effective `demarrer_site()` call and `repertoire`, the plugin version, the web/PHP stack, and the database engine for every affected site.
3. Separate verified facts, unknowns, and anomalies. Mask secrets; never paste connection files or credential-bearing command output.
4. Read only the reference needed for the requested operation.
5. Stop if a required precondition remains unknown.
6. Return the response in this order:

| Section | Required content |
|---|---|
| Findings | Verified facts with their evidence; unknowns stay explicit |
| Risks | Impact radius, compatibility gaps, local modifications, and blockers |
| Proposed procedure | Ordered operator actions, including a maintenance window when needed |
| Commands | Bounded commands labeled **proposed — not executed**; placeholders or verified paths only |
| Validation | Checks for every affected site, not only the administration site |
| Rollback | Trigger, restore unit, engine-specific database recovery, and validation |

Every proposed mutation must name its objective, prerequisites, exact scope, likely impact, required backup, success check, and matching rollback.

## Routing

| Request | Read |
|---|---|
| Map sites, paths, versions, plugins, permissions, capacity, or web/PHP stack | [inventory-diagnosis.md](references/inventory-diagnosis.md) |
| Upgrade the shared SPIP core | [core-upgrade.md](references/core-upgrade.md), then [backup-rollback.md](references/backup-rollback.md) |
| Upgrade shared or site-specific plugins | [plugin-upgrade.md](references/plugin-upgrade.md), then [backup-rollback.md](references/backup-rollback.md) |
| Prepare or verify backups, restoration, or rollback | [backup-rollback.md](references/backup-rollback.md) |
| Investigate suspicious files, intrusion, or compromise | [security-incident.md](references/security-incident.md); do not start with an update or cache purge |

## Stop conditions

Do not prepare ready-to-run mutations until all relevant conditions are resolved:

- the shared root or effective mutualisation directory is ambiguous;
- the installed method (Git, Composer, archive/SVP) or local modifications are unknown;
- the affected-site list or shared-versus-local plugin location is uncertain;
- a core/plugin upgrade lacks a recent **verified and restorable** backup;
- compatibility with the target version has not been checked against current package declarations;
- an incident has not yet had evidence preserved and its impact radius bounded;
- a proposed target resolves to `/`, a workspace root, an unresolved variable, or a path containing unexpected symbolic links.

## Red flags

| Temptation | Required response |
|---|---|
| “I am root and accept the risk.” | Authority does not replace a verified backup or rollback. Stop the upgrade. |
| “The files are obviously malicious; delete them now.” | Preserve metadata, hashes, logs, and copies; quarantine recoverably before deletion. |
| “The sites are usually in `sites/`.” | Read the effective `demarrer_site()` configuration. A convention is not evidence. |
| “Update the shared plugin for one production site only.” | One shared path cannot be a one-site code canary. Use an isolated copy/staging environment. |
| “The old Contrib page documents it.” | Installed code and current package compatibility take precedence over historical documentation. |
| “A backup file exists.” | Verify integrity and perform or document an isolated restoration test. Existence alone is not a rollback. |

## Common mistakes

- Reading or printing all of `connect.php` to identify the database engine.
- Putting a MariaDB/MySQL password in `-p...`, a script, process arguments, or the answer.
- Treating `local/` and `tmp/` as persistent content, or deleting them before incident evidence is preserved.
- Rolling back shared files after a site database schema has already migrated.
- Mixing Git, Composer, and archive/SVP provenance during an upgrade.
- Using `rm -rf`, `find -delete`, recursive `chown`, or `DROP DATABASE` against a broad or unresolved target.

## Source policy

Use, in order: effective installed code/configuration; official source for the exact SPIP branch; current [Mutualisation facile source](https://git.spip.net/spip-contrib-extensions/mutualisation); official SPIP documentation; then the [historical Contrib article](https://contrib.spip.net/La-mutualisation-facile-modifications-manuelles). Verify “latest” versions at task time instead of freezing them here.
