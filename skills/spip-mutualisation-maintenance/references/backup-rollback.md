# Backup, restoration, and rollback

Back up a coherent restore unit, not just “the SPIP directory.” Commands in this reference are templates to propose to the operator; the agent does not run them.

Sources: [official SPIP update guide](https://www.spip.net/fr_article1318.html), the installed SPIP connection/configuration, and SQLite's documented [online backup command](https://sqlite.org/cli.html#special_commands_to_sqlite3_dot_commands_).

## 1. Define the restore unit

For each maintenance checkpoint, record:

- exact shared-code provenance: Git commit/tag, Composer files/lock, or exact archive identity;
- global mutualisation configuration;
- each site's `config/`, `IMG/`, site-specific templates and plugins;
- each site's database and engine;
- a manifest with UTC time, host, site/domain, paths, versions, table prefix, sizes, checksums, and backup tool versions.

`local/` and `tmp/` are normally regenerated and excluded from ordinary restoration. In an incident, logs and suspicious artifacts in them are evidence: follow [security-incident.md](security-incident.md) before excluding or clearing anything.

Store backups outside the document root with restrictive permissions and an off-host copy. Treat them as secrets.

## 2. Prepare safe paths

Use explicit resolved values. Do not repurpose `HOME`, use an unresolved glob, or accept `/`.

```bash
spip_root=/srv/www/example-spip
backup_root=/srv/backups/spip-farm/2026-08-28T120000Z

spip_root="$(realpath -e -- "$spip_root")"
backup_parent="$(realpath -e -- "$(dirname -- "$backup_root")")"
test -n "$spip_root" && test "$spip_root" != /
test -n "$backup_parent" && test "$backup_parent" != /
case "$backup_parent" in "$spip_root"|"$spip_root"/*) exit 1;; esac
```

Creating `backup_root` and changing its mode are proposed mutations; show them in the operator plan only after the assertions.

## 3. MariaDB/MySQL backup

### Authentication

Never put a password in `-pPASSWORD`, a URI, the answer, or a process argument. Reuse a protected option file or socket authentication already approved by the operator:

```bash
client_opts=/etc/mysql/spip-backup.cnf  # mode 0600, prepared by the operator
mariadb --defaults-extra-file="$client_opts" --batch --skip-column-names \
  -e 'SELECT VERSION();'
```

Do not generate the credential file or copy credentials out of SPIP configuration.

### Consistency decision

Inspect table engines before selecting the dump strategy:

```bash
db_name=verified_database_name
mariadb --defaults-extra-file="$client_opts" --batch --skip-column-names \
  -e "SELECT COALESCE(ENGINE,'VIEW'), COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() GROUP BY ENGINE" \
  "$db_name"
```

- All relevant tables transactional (normally InnoDB): `--single-transaction --quick` can provide a consistent logical snapshot when DDL is frozen.
- Non-transactional tables or concurrent DDL: schedule an application write freeze and choose a reviewed lock/snapshot method. Do not claim `--single-transaction` protects those tables.

Proposed dump shape:

```bash
dump_path="$backup_root/databases/$site_key.sql"
mariadb-dump --defaults-extra-file="$client_opts" \
  --single-transaction --quick --routines --events --triggers \
  --databases "$db_name" >"$dump_path"
test -s "$dump_path"
```

Use `mysqldump` only when that is the installed compatible client. Record `mariadb-dump --version` or `mysqldump --version`. Check the actual exit code before compression; a non-empty partial file is not success.

### Restore verification

Do not first overwrite production. Restore to an isolated server or explicitly temporary database with a validated identifier, then check tables and SPIP behavior:

```bash
restore_db=restore_test_verified_name
mariadb --defaults-extra-file="$client_opts" -e "CREATE DATABASE \`$restore_db\`"
mariadb --defaults-extra-file="$client_opts" "$restore_db" <"$dump_path"
mariadb --defaults-extra-file="$client_opts" --batch --skip-column-names \
  -e 'CHECK TABLE spip_meta' "$restore_db"
```

`CREATE DATABASE` and import are state-changing examples for the operator, not agent actions. Quote/validate identifiers instead of interpolating arbitrary input. Clean up the test database only after results are recorded and the operator approves.

## 4. SQLite backup

Locate the exact database from the site's sanitized connection inventory; it is commonly under the site's `config/bases/`. Do not use a raw hot `cp` as the consistency mechanism.

Proposed online backup and validation:

```bash
sqlite_db=/srv/www/example-spip/site-data/example/config/bases/spip.sqlite
sqlite_backup="$backup_root/databases/$site_key.sqlite"

sqlite_db="$(realpath -e -- "$sqlite_db")"
test -f "$sqlite_db"
sqlite3 "$sqlite_db" ".timeout 5000" ".backup '$sqlite_backup'"
test -s "$sqlite_backup"
test "$(sqlite3 "$sqlite_backup" 'PRAGMA integrity_check;')" = ok
sqlite3 "$sqlite_backup" 'PRAGMA foreign_key_check;'
```

If the CLI version does not accept the proposed invocation, stop and adapt it in staging; do not fall back silently to a live raw copy.

For a restore drill, restore/copy the backup to a new isolated filename, point an isolated site copy at it, run `PRAGMA integrity_check`, then exercise SPIP. Preserve the production database until the restored site passes.

## 5. Persistent files and manifest

Archive an explicit list; do not recursively archive the whole hosting parent. `tar` and `rsync` normally preserve symbolic links as links rather than following them, but review unexpected links before restoration.

Example manifest inputs:

```text
shared/config/mes_options.php
shared/mutualisation/paquet.xml
sites/<verified-site>/config/
sites/<verified-site>/IMG/
sites/<verified-site>/squelettes/        (when present)
sites/<verified-site>/plugins/           (when present)
```

For every archive/dump:

```bash
sha256sum -- "$artifact" >>"$backup_root/SHA256SUMS"
gzip -t -- "$artifact.gz"                 # gzip artifacts
tar -tf "$artifact.tar" >/dev/null        # tar artifacts
sha256sum -c "$backup_root/SHA256SUMS"
```

Record exit codes, byte sizes, ownership/modes needed for restoration, and the off-host copy location. Do not include credentials in a human-readable manifest.

## 6. Rollback matrix

| Change reached | Restore shared code | Restore site DB | Restore persistent files |
|---|---|---|---|
| Code prepared, no production switch | No | No | No |
| Shared code switched, no schema migration or writes | Exact old release | Usually no; verify checkpoint | Only if changed |
| Site schema migrated or new code wrote data | Exact old release | Matching pre-change DB for that site | Matching pre-change files when writes/uploads occurred |
| State uncertain or incident overlaps change | Known-good shared release | Every affected matching DB | Every affected known-good persistent set; retain evidence separately |

Never restore one site's database from a different checkpoint than the shared code/plugins it expects. A rollback plan names the exact manifest and checksums, not “the latest backup.”

## 7. Restore acceptance

For every site, verify public HTTP, private authentication, `spip_meta`/schema state, active plugins, representative content/media, forms/uploads, scheduled jobs, and PHP/SPIP logs. Keep maintenance active until the ledger is complete. A successful archive checksum proves transport integrity, not application restoration.
