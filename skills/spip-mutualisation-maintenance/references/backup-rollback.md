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

### Authentication and independent client detection

Never put a password in `-pPASSWORD`, a URI, the answer, or a process argument. Reuse a protected option file or socket authentication already approved by the operator. Detect the SQL client independently from the dump client; either family may be installed without the other:

```bash
client_opts=/etc/mysql/spip-backup.cnf  # mode 0600, prepared by the operator

if command -v mariadb >/dev/null 2>&1; then
  sql_client=mariadb
elif command -v mysql >/dev/null 2>&1; then
  sql_client=mysql
else
  echo 'STOP: no MariaDB/MySQL SQL client found' >&2
  exit 1
fi

"$sql_client" --defaults-extra-file="$client_opts" --batch --skip-column-names \
  -e 'SELECT VERSION();'
```

Do not generate the credential file or copy credentials out of SPIP configuration.

### Read-only client and server detection

Detect the installed dump client separately before proposing a command shape. Record the SQL client, dump client, and database server versions before continuing:

```bash
client_opts=/etc/mysql/spip-backup.cnf  # mode 0600, prepared by the operator
db_name=verified_database_name

if command -v mariadb-dump >/dev/null 2>&1; then
  dump_client=mariadb-dump
elif command -v mysqldump >/dev/null 2>&1; then
  dump_client=mysqldump
else
  echo 'STOP: no MariaDB/MySQL logical dump client found' >&2
  exit 1
fi

"$sql_client" --version
"$dump_client" --version
"$sql_client" --defaults-extra-file="$client_opts" --batch --skip-column-names \
  -e 'SELECT VERSION();' "$db_name"
```

### Consistency decision

Inspect table engines before selecting the dump strategy:

```bash
db_name=verified_database_name
"$sql_client" --defaults-extra-file="$client_opts" --batch --skip-column-names \
  -e "SELECT COALESCE(ENGINE,'VIEW'), COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() GROUP BY ENGINE" \
  "$db_name"
```

- All relevant tables transactional (normally InnoDB): `--single-transaction --quick` can provide a consistent logical snapshot when DDL is frozen.
- Non-transactional tables or concurrent DDL: schedule an application write freeze and choose a reviewed lock/snapshot method. Do not claim `--single-transaction` protects those tables.

### Proposed dump shape

```bash
dump_path="$backup_root/databases/$site_key.sql"
"$dump_client" --defaults-extra-file="$client_opts" \
  --single-transaction --quick --routines --events --triggers \
  --no-create-db "$db_name" >"$dump_path"
dump_status=$?
if test "$dump_status" -ne 0; then
  echo 'STOP: database dump failed; do not accept a partial file as a backup' >&2
  exit 1
fi
if ! test -s "$dump_path"; then
  echo 'STOP: database dump is empty' >&2
  exit 1
fi
```

`--databases` is deliberately forbidden for this workflow because it embeds database-selection statements that can defeat a restore target. Use the independently detected `dump_client`. Check the actual exit code before compression; a non-empty partial file is not success.

### Restore verification

Do not run a drill on the source server or reuse its credentials. The drill server/instance must be isolated from production, have no route or credentials capable of reaching the source, and expose an identity different from the source endpoint. Its dedicated option file must authenticate only to that isolated endpoint. Reject dumps containing database-selection or database-lifecycle statements before import, then restore to a validated database name different from the source name:

```bash
restore_client_opts=/etc/mysql/spip-restore-drill.cnf  # dedicated isolated endpoint, mode 0600
restore_db=restore_test_verified_name
table_prefix=verified_table_prefix

case "$db_name" in ''|*[!A-Za-z0-9_]* ) echo 'STOP: invalid source database identifier' >&2; exit 1;; esac
case "$restore_db" in ''|*[!A-Za-z0-9_]* ) echo 'STOP: invalid restore database identifier' >&2; exit 1;; esac
case "$table_prefix" in ''|*[!A-Za-z0-9_]* ) echo 'STOP: invalid table prefix' >&2; exit 1;; esac
if test "$restore_db" = "$db_name"; then echo 'STOP: drill database must differ from source' >&2; exit 1; fi
if test "$(realpath -e -- "$restore_client_opts")" = "$(realpath -e -- "$client_opts")"; then echo 'STOP: drill must use a dedicated option file' >&2; exit 1; fi

if ! source_identity="$("$sql_client" --defaults-extra-file="$client_opts" --batch --skip-column-names -e "SELECT CONCAT(@@hostname, ':', @@port, ':', @@datadir)")"; then echo 'STOP: cannot identify source endpoint' >&2; exit 1; fi
if ! restore_identity="$("$sql_client" --defaults-extra-file="$restore_client_opts" --batch --skip-column-names -e "SELECT CONCAT(@@hostname, ':', @@port, ':', @@datadir)")"; then echo 'STOP: cannot identify drill endpoint' >&2; exit 1; fi
if test -z "$source_identity" || test -z "$restore_identity" || test "$source_identity" = "$restore_identity"; then echo 'STOP: source and drill endpoints are not proven distinct' >&2; exit 1; fi

LC_ALL=C rg -q -i '^[[:space:]]*(CREATE|ALTER|DROP)[[:space:]]+DATABASE|^[[:space:]]*USE[[:space:]]|/\\*![0-9]+' "$dump_path"
dump_sql_status=$?
case "$dump_sql_status" in
  0) echo 'STOP: dump can select or mutate a database outside the explicit restore target' >&2; exit 1;;
  1) ;;
  *) echo 'STOP: cannot inspect dump for database-selection statements' >&2; exit 1;;
esac

if ! source_schema="$("$sql_client" --defaults-extra-file="$restore_client_opts" --batch --skip-column-names -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$db_name'")"; then echo 'STOP: cannot check source schema absence on drill endpoint' >&2; exit 1; fi
if test -n "$source_schema"; then echo 'STOP: drill endpoint exposes the source database' >&2; exit 1; fi
"$sql_client" --defaults-extra-file="$restore_client_opts" -e "CREATE DATABASE \`$restore_db\`" || { echo 'STOP: cannot create isolated drill database' >&2; exit 1; }
"$sql_client" --defaults-extra-file="$restore_client_opts" "$restore_db" <"$dump_path" || { echo 'STOP: restore import failed' >&2; exit 1; }
if ! source_schema="$("$sql_client" --defaults-extra-file="$restore_client_opts" --batch --skip-column-names -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$db_name'")"; then echo 'STOP: cannot recheck source schema absence' >&2; exit 1; fi
if test -n "$source_schema"; then echo 'STOP: restore created or exposed the source database' >&2; exit 1; fi
meta_table="${table_prefix}meta"
"$sql_client" --defaults-extra-file="$restore_client_opts" --batch --skip-column-names \
  -e "CHECK TABLE \`$meta_table\`" "$restore_db" || { echo 'STOP: drill integrity check failed' >&2; exit 1; }
```

The endpoint-identity difference is necessary but not sufficient: the operator must also verify the drill network/credential isolation before import. The two absence checks prove the dump did not create or select the source-named database on the isolated endpoint. `CREATE DATABASE` and import are state-changing examples for the operator, not agent actions. Clean up the test database only after results and isolation evidence are recorded and the operator approves.

## 4. SQLite backup

Locate the exact database from the site's sanitized connection inventory; it is commonly under the site's `config/bases/`. Do not use a raw hot `cp` as the consistency mechanism. SQLite's own CLI documents `.backup` as the online backup command, and `PRAGMA integrity_check` is the first integrity gate after the copy ([SQLite CLI `.backup`](https://sqlite.org/cli.html#special_commands_to_sqlite3_dot_commands_), [SQLite integrity check pragma](https://sqlite.org/pragma.html#pragma_integrity_check)).

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

If the CLI version does not accept the proposed invocation, stop and adapt it in staging; do not fall back silently to a live raw copy. `PRAGMA foreign_key_check` is an additional logical consistency check, not a replacement for `PRAGMA integrity_check` ([SQLite integrity check pragma](https://sqlite.org/pragma.html#pragma_integrity_check), [SQLite foreign key check pragma](https://sqlite.org/pragma.html#pragma_foreign_key_check)).

For a restore drill, restore/copy the backup to a new isolated filename, point an isolated site copy at it, run `PRAGMA integrity_check`, then exercise SPIP. Preserve the production database until the restored site passes.

## 5. Persistent files and manifest

Archive an explicit list; do not recursively archive the whole hosting parent. `tar` and `rsync` normally preserve symbolic links as links rather than following them, but review unexpected links before restoration.

Example manifest inputs:

```text
<verified-global-config-path>
<verified-mutualisation-plugin-path>/paquet.xml
<verified-site-root>/config/
<verified-site-root>/IMG/
<verified-site-root>/squelettes/        (when present)
<verified-site-root>/plugins/           (when present)
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
