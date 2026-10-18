# Upgrade 4.4 → 5.0, rollback

Run on a copy of a 4.4.21 site (SQLite, 11 plugins in `plugins/`, one in `plugins/auto/`) on 2026-10-18, to
`spip/ecrire` `26bfe470`. Everything here is **proposed** on a user's site, never run on production; rehearse it on a
copy first.

## Before

- 5.0 is a beta: the decision to put a client site on it is the client's.
- Database upgrade paths: from 3.2+ only, 4.1+ advised, 4.4 ideally. The latest 4.4 is **4.4.28**.
- 4.4.21 and 5.0.0-beta have the **same core schema** (`2026_08_03_00`): no core database upgrade from a recent 4.4;
  plugins may still upgrade theirs.
- Inventory the plugins: 5.0 refuses every plugin whose `paquet.xml` `compatibilite` stops at 4
  (`[4.1.0;4.*]`, `[4.0.0;4.*]`, `[4.1.0;4.*.*]`). Decide for each one: 5.0-compatible version, replacement, or drop.

```bash
for f in plugins/*/paquet.xml plugins/auto/*/paquet.xml plugins/auto/*/*/paquet.xml; do
  [ -f "$f" ] && echo "$f $(grep -o 'compatibilite="[^"]*"' "$f" | head -1)"; done
```

- Squelettes and plugin code: removed syntaxes and APIs are in `spip-migration-5`.
- What does not carry over: `config/ecran_securite.php` (not loaded in 5.0; Guard replaces it,
  `spip.guard.load_threshold` replaces `ECRAN_SECURITE_LOAD`), `ecrire/mes_options.php` (ignored), `_DIR_*` /
  `_NOM_*` in `mes_options.php` (fatal), `_LOG_FILTRE_GRAVITE` (`caches-logs.md`).

## Backup

```bash
# SQLite
sqlite3 config/bases/spip.sqlite ".backup '/srv/backup/spip.sqlite'"
sqlite3 /srv/backup/spip.sqlite "pragma integrity_check;"     # ok
# MySQL/MariaDB: credentials from config/connect.php in an option file (mode 600), never on the command line
umask 077; printf '[client]\nuser=<user>\npassword=<password>\nhost=<host>\nport=<port>\n' > /srv/backup/db.cnf
mysqldump --defaults-extra-file=/srv/backup/db.cnf --single-transaction --routines --triggers --no-tablespaces \
  <database> > /srv/backup/site.sql
# files
tar -czf /srv/backup/site-4.4.tgz --exclude='site/tmp/cache' -C /var/www site
```

Test that the backup reads back before going on (`pragma integrity_check`, `tail -1 site.sql` → `-- Dump completed`,
`tar -tzf … | head`). `--defaults-extra-file` must be the first option. Without `--no-tablespaces`, MySQL 8 prints
"Access denied; you need (at least one of) the PROCESS privilege(s)" for a user limited to its database. Run with
the MySQL 8.0 clients (`mysqldump`, `mysql`). MariaDB ships the same commands, also named `mariadb-dump` and
`mariadb`; those were not run here. The empty database for a rehearsal is created by whoever administers the server
(`CREATE DATABASE`, rights for the SPIP user).

## Upgrade, side by side

The old directory stays untouched until the switch, which keeps the rollback short.

```bash
composer create-project --no-dev --repository=https://get.spip.net/composer spip/spip /var/www/site5 5.0.x-dev
cd /var/www/site5 && composer update --no-dev
# data from the 4.4 site
cp -a /var/www/site/config/connect.php /var/www/site/config/cles.php /var/www/site/config/chmod.php config/
cp -a /var/www/site/config/bases config/            # SQLite only; MySQL keeps its server
cp -a /var/www/site/config/.htaccess config/        # Apache: keep the deny file
cp -a /var/www/site/config/mes_options.php config/  # if it exists, after removing _DIR_* / _NOM_* defines
rm -rf IMG && cp -a /var/www/site/IMG IMG
cp -a /var/www/site/plugins plugins                 # incompatible ones will be refused
cp -a /var/www/site/squelettes squelettes           # if it exists
bin/spip spip:cache:warmup --clear
```

Do not copy `tmp/`, `local/` (rebuilt) or `ecran_securite.php`.

**MySQL/MariaDB: the copied `connect.php` points to the production database**, and the first 5.0 hit on `ecrire/`
changes it (plugin schemas, metas rewritten as JSON). For the rehearsal, load the dump into a separate, empty database
and point the rehearsal's `config/connect.php` (`spip_connect_db(…)`, 5th argument = database name) to it before any
hit:

```bash
mysql --defaults-extra-file=/srv/backup/db.cnf <rehearsal_database> < /srv/backup/site.sql
```

On the production switch, the shared database is expected; the backup taken just before is the rollback. With
SQLite, the copied `config/bases/` is already a separate copy.

Then point a staging vhost (rehearsal) or the production vhost (switch) at `/var/www/site5` and, as webmaster, open
`ecrire/` and `ecrire/?exec=admin_plugin`:

- plugins stopping at 4 are refused: « Impossible d'activer le plugin ../plugins/<name> », « Nécessite SPIP en
  version ≤ 4.* minimum »; plugins declaring `5.*` stay active and appear in `var/state/plugins.php`;
- plugin schema upgrades run **on this first webmaster hit**, not on public pages (`medias` 1.8.1 → 1.9.2), logged
  in `tmp/log/maj.log`;
- metas are rewritten as JSON (`spip_meta.plugin` starts with `[{"SPIP":`).

The plugins-dist live in `plugins-dist/spip/<name>/`. Upgrading in place (5.0 files over a 4.4 tree) leaves the old
flat `plugins-dist/<name>/`: delete them. A leftover `plugins-dist/forum/` was ignored in the test (no error), but
the documentation asks for their removal.

## Checks

- public pages (home, a few articles, a rubrique, a document under `IMG/`) answer as on 4.4;
- `ecrire/` logs in; `ecrire/?exec=admin_plugin` shows no unexpected refusal;
- `bin/spip spip:info` → `5.0.0-beta`; `tmp/log/maj.log`, `tmp/log/spip.log` and the web server log without new
  fatal errors;
- the sensitive paths of `prerequis.md` are not served.

## Rollback

Once 5.0 has served `ecrire/`, the 4.4 code on that database **half works**: pages answer 200 but the log fills with
errors (`pipeline desactive`, `spip_connect … non trouve`). Restoring the code alone is not a rollback.

1. point the vhost back at the 4.4 directory (side by side) or restore the files archive;
2. restore the database backup:

```bash
sqlite3 /srv/backup/spip.sqlite ".backup 'config/bases/spip.sqlite'"      # SQLite
mysql --defaults-extra-file=/srv/backup/db.cnf <database> < /srv/backup/site.sql   # MySQL/MariaDB
```

3. purge `tmp/cache/` of the 4.4 site.

Verified: `pragma integrity_check` ok, plugin schema back (`medias` 1.8.1), `plugin` meta serialised again, same
article count, public 200, `ecrire/` 302, `ecrire/inc_version.php` `4.4.21`. One `pipeline desactive` line on the
first hit after the purge is the cache being rebuilt; it does not come back.
