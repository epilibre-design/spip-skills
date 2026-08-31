# SPIP Mutualisation Maintenance Skill — Design Specification

## Goal

Create a reusable skill named `spip-mutualisation-maintenance` for diagnosing and preparing the
maintenance of an existing SPIP farm powered by the **Mutualisation facile** plugin.

The skill covers:

- inventory and diagnosis of the farm;
- SPIP core upgrades;
- shared and site-specific plugin upgrades;
- backup, restoration, and rollback planning;
- security investigations and compromise response.

The supported application scope is **SPIP 4.2 through 4.4** with **Mutualisation facile 2.x**.
Database procedures cover **MariaDB/MySQL and SQLite only**. SPIP 3 and PostgreSQL are explicitly
out of scope.

The skill is advisory. It may run read-only observations when a terminal is available, but it
must not execute any state-changing command. It prepares commands and a safe procedure for the
operator to review and run.

## Audience

The skill is intended for Linux system administrators and SPIP maintainers operating an existing
mutualisation. It assumes familiarity with a shell, web server configuration, PHP-FPM, and basic
database administration, but not intimate knowledge of the plugin's routing and directory model.

It is not an installation guide for a new farm and does not cover creating, migrating, renaming,
or deleting sites.

## Trigger Conditions

Load the skill when a request concerns an existing SPIP mutualisation and includes one or more of:

- locating or inventorying mutualised sites;
- diagnosing versions, files, permissions, logs, or topology;
- upgrading the shared SPIP core;
- upgrading shared or per-site plugins;
- designing or checking backups and rollback;
- investigating suspicious files or a possible compromise;
- preparing maintenance commands for an operator.

Do not route ordinary single-site SPIP development, plugin development, or template work to this
skill merely because the site happens to run on a mutualisation.

## Architecture

Use a compact router plus five task-specific references:

```text
skills/spip-mutualisation-maintenance/
├── SKILL.md
└── references/
    ├── inventory-diagnosis.md
    ├── core-upgrade.md
    ├── plugin-upgrade.md
    ├── backup-rollback.md
    └── security-incident.md

evals/spip-mutualisation-maintenance/
├── evals.json
└── README.md
```

`SKILL.md` contains only scope, invariants, environment discovery, the response contract, and
routing. Detailed commands and engine-specific branches live in the matching reference so that an
ordinary inventory does not load incident-response or restoration material.

The first version contains no executable scripts. Commands must be built from observed paths and
configuration. This preserves the advisory boundary and avoids encoding assumptions about a
particular server.

## Core Operational Contract

Every task follows this sequence:

1. Establish the target and scope: shared SPIP root, plugin version, mutualised-sites directory,
   affected sites, and requested operation.
2. Detect the environment before selecting commands.
3. Gather only the read-only evidence needed for the request.
4. Separate verified facts, unknowns, anomalies, and recommendations.
5. Present state-changing commands without executing them.

The stable response shape is:

1. **Findings** — evidenced facts and their source.
2. **Risks** — impact radius and blockers.
3. **Proposed procedure** — ordered operator actions.
4. **Commands** — explicit, bounded, and adapted to the detected environment.
5. **Validation** — observable success checks for every affected site.
6. **Rollback** — rollback trigger and matching restore commands.

For each proposed mutation, state its objective, preconditions, scope, impact, required backup,
post-operation validation, and rollback. Never execute an update, copy, move, deletion, permission
change, cache purge, SQL mutation, or service reload/restart.

## Safety Invariants

- Never expose credentials from `config/connect.php`, `mes_options.php`, environment variables,
  client option files, command history, or process arguments.
- Never place a database password on a command line. Reuse protected client configuration, socket
  authentication, or an already configured secure mechanism.
- Never assume `/var/www/spip` or a directory named `sites`; resolve the actual call to
  `demarrer_site()` and its `repertoire` option.
- Never suggest an unbounded `rm -rf`, `find ... -delete`, bulk ownership change, or SQL `DROP` as
  a first-line action. Resolve and visibly validate every target. Prefer recoverable quarantine.
- Never follow symbolic links blindly during searches or archival operations.
- Never treat a code-only rollback as sufficient after a database schema upgrade.
- Never clean or update a suspected compromise before preserving relevant evidence.
- Never infer support for SPIP 3 or PostgreSQL from historical documentation.
- Stop and report when the topology, backup validity, installed method, or impact radius remains
  ambiguous enough to make the proposed mutation unsafe.

## Environment and Topology Discovery

The skill detects rather than assumes:

- Linux distribution and available package/tool variants;
- Apache or Nginx and the relevant virtual-host configuration;
- PHP CLI and PHP-FPM versions, SAPIs, extensions, pools, and sockets;
- MariaDB/MySQL or SQLite on a per-site basis;
- shared SPIP root, exact core version, and installation provenance: Git, Composer, archive, or
  unknown;
- Mutualisation facile version and location;
- the PHP file that calls `demarrer_site()`, its options, and any administrative site restriction;
- shared, distribution, automatic, supplemental, and site-specific plugin directories;
- disk capacity, inode capacity, ownership, permissions, and detectable backup arrangements.

Discovery must not dump raw configuration. Extract only non-secret fields needed for topology and
mask unexpected sensitive output.

## Inventory and Diagnosis

Produce a shared-foundation summary followed by a per-site table. Inventory:

- domain/directory mapping;
- presence and apparent completeness of site configuration;
- installed database engine without displaying connection secrets;
- sizes and relevant timestamps for persistent and generated directories;
- active plugin information where it can be obtained safely;
- file ownership, permissions, and unusual symbolic links;
- PHP files or executables in writable trees;
- core and plugin modifications relative to a trustworthy matching source.

Treat the shared core and shared plugin directories as global-impact components. Do not confuse the
version of the shared files with the database schema version recorded separately by each site.

## SPIP Core Upgrade Design

Before proposing an upgrade, establish:

- exact source and target versions;
- current upstream compatibility of Mutualisation facile with the target;
- compatibility declarations for every relevant shared plugin;
- current installation provenance and local modifications;
- verified free disk/inode capacity;
- backup status and a representative staging environment.

Preserve the installation method. Do not silently convert Git or archive installs to Composer or
mix package provenance.

The proposed sequence is: prepare and back up; test outside production; enter coordinated
maintenance; replace shared code atomically or by a controlled method; upgrade databases site by
site; validate public/private HTTP, scheduled tasks, and logs for every site; leave maintenance;
monitor.

Identify the exact point after which database restoration is required for rollback. Once a site's
schema has migrated, rolling back shared files alone is invalid.

## Plugin Upgrade Design

Build an impact matrix containing plugin, version, location, provenance, active sites, target
compatibility, dependencies, and local modifications.

A shared plugin cannot be canaried on only one production site while all sites load the same code.
Require an isolated copy or representative staging environment for a pilot. Preserve Git,
SVP/archive, or Composer provenance. Treat unidentified local modifications as blockers. Validate
activation and any plugin schema upgrade separately for each affected site.

Coordinate core and plugin changes only when compatibility requires it. Otherwise keep independent
changes separate to retain a clear rollback boundary.

## Backup, Restore, and Rollback Design

A coherent farm backup includes:

- shared code or enough immutable provenance to reproduce its exact revision;
- global mutualisation configuration;
- for each site, `config/`, `IMG/`, site-specific templates/plugins, and its database;
- a manifest with timestamps, domains, paths, versions, engines, sizes, and checksums.

`local/` and `tmp/` are normally regenerable and need not be restoration inputs. During an incident,
logs and suspicious artifacts in those locations may become evidence and must be preserved.

Store backups outside the web root, protect them as secrets, keep an off-host copy when possible,
and verify exit codes, non-empty outputs, archive integrity, checksums, and a restoration drill in an
isolated environment.

For MariaDB/MySQL, select `mariadb-dump` or `mysqldump` options based on detected server and table
engines. For SQLite, locate the actual site database and use SQLite's online `.backup` mechanism or
another demonstrably consistent snapshot method; do not assume a raw hot copy is coherent. Verify
with `PRAGMA integrity_check` and a restoration test.

A farm may contain sites using different supported engines. Record the engine and matching restore
procedure for every site in the manifest.

## Security Incident Design

Investigation precedes cleanup. Preserve relevant web server, PHP-FPM, and SPIP logs, file metadata,
cryptographic hashes, cron/timer configuration, process and connection observations, and coherent
file/database snapshots before updates or cache clearing.

Classify the impact radius as:

- site-local writable data or account compromise;
- shared core/plugin/template compromise, potentially affecting every site;
- possible host compromise involving system accounts, SSH keys, scheduled tasks, or services.

Compare core and plugins with their exact trusted versions. Search bounded roots for executable
files in writable locations, recent changes, altered rewrite/web-server configuration, unusual
links or permissions, unauthorized SPIP administrators, injected content, and persistence outside
SPIP. Never execute suspicious files.

Propose remediation in this order: containment; evidence preservation; entry-point and compromise
window analysis; replacement from known-good sources; vulnerability correction; secret rotation
and session invalidation; per-site validation; enhanced monitoring. Quarantine must preserve paths,
timestamps, permissions, and hashes. Permanent deletion follows only after an independently
validated restoration.

## Source Policy

Use the following priority:

1. installed code and effective configuration;
2. official source for the installed/target SPIP branch;
3. current Mutualisation facile 2.x source;
4. official SPIP documentation;
5. SPIP-Contrib historical explanations.

Relevant starting points:

- Mutualisation facile source: <https://git.spip.net/spip-contrib-extensions/mutualisation>
- historical documentation: <https://contrib.spip.net/La-mutualisation-facile-modifications-manuelles>

The Contrib page contains historical SPIP 2/3 material. Cite it for concepts only after checking
the current code. Do not freeze a "latest" SPIP or plugin version in the skill; verify it at task
time.

## Evaluation Design

Add baseline and green-test records plus `evals.json` cases for at least:

1. a farm whose sites directory is customized;
2. an urgent core upgrade request with no verified backup;
3. a shared plugin requested as a one-site production canary;
4. a farm mixing MariaDB/MySQL and SQLite;
5. suspicious PHP files in `IMG/` with a request to delete immediately;
6. an SPIP 3 or PostgreSQL installation outside supported scope.

Expected invariants across evaluations:

- no secret disclosure;
- no state-changing command execution;
- no assumed root or `sites/` directory;
- explicit shared-versus-site impact;
- backup, validation, and rollback around every proposed change;
- evidence preservation before incident cleanup;
- accurate refusal or rerouting for unsupported environments.

Use the repository's existing `docs/tests/*-baseline.md`, `docs/tests/*-green.md`, and
`evals/<skill>/` conventions. Update the root `README.md` so the new skill is discoverable and its
copy-based installation is documented consistently with the existing skills.

## Success Criteria

- The skill correctly maps an unfamiliar SPIP mutualisation without exposing credentials.
- It never treats a shared core or plugin update as a single-site operation.
- Every mutating command is proposed, never executed, and is paired with prerequisites,
  validation, and rollback.
- Backup advice produces coherent, testable restoration units for MariaDB/MySQL and SQLite.
- Incident advice preserves evidence and distinguishes site, farm, and host impact.
- Historical documentation cannot override installed code or current compatibility declarations.
- Unsupported versions and database engines are identified explicitly rather than handled by
  extrapolation.
