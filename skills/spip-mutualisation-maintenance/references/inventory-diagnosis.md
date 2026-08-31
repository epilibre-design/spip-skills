# Inventory and diagnosis

Use this reference before every other maintenance mode. Its output is the evidence base for later commands.

Sources: [Mutualisation facile `paquet.xml`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/paquet.xml), [`mes_options.php.txt`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/mes_options.php.txt), and [`mutualiser.php`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/mutualiser.php).

## 1. Bound the search

Start from a directory supplied by the operator, the current deployment directory, or a document root read from the active virtual-host configuration. Do not search all of `/`.

Read-only examples:

```bash
# Set only after the operator identifies a bounded hosting parent.
hosting_root=/srv/www
test -d "$hosting_root" && test "$hosting_root" != /

find -P "$hosting_root" -xdev -maxdepth 5 -type f \
  -path '*/ecrire/inc_version.php' -print

command -v nginx >/dev/null && nginx -T 2>/dev/null \
  | sed -E 's/[[:space:]]*#.*$//' \
  | awk '$1 == "server_name" || $1 == "root" || $1 == "fastcgi_pass" { print }'
command -v apache2ctl >/dev/null && apache2ctl -S 2>&1 \
  | rg '(^VirtualHost configuration:|namevhost|alias|port [0-9]+ namevhost|\(.*\.conf:[0-9]+\))'
```

The pipelines discard raw configuration before it can enter the agent context and emit only allowlisted topology directives. If a local directive still contains an unexpected credential or token, redact it locally or stop and ask the operator for a sanitized mapping. Summarize the useful mapping; do not paste unrelated configuration or secrets.

For each candidate, resolve the physical path and validate the SPIP markers without changing directory contents:

```bash
spip_root=/srv/www/example-spip
spip_root="$(realpath -e -- "$spip_root")"
test -n "$spip_root" && test "$spip_root" != /
test -f "$spip_root/ecrire/inc_version.php"
test -f "$spip_root/spip.php"
```

## 2. Resolve the effective mutualisation configuration

Do not assume that `sites/` is used. Discover every bounded PHP configuration candidate rather than switching back to two conventional paths after discovery:

```bash
mapfile -d '' -t php_candidates < <(
  find -P "$spip_root" -xdev -maxdepth 8 -type f \
    \( -name '*.php' -o -name '*.php.txt' \) -print0
)
((${#php_candidates[@]} > 0)) || { echo 'STOP: no bounded PHP candidates found' >&2; exit 1; }

rg -l -0 \
  'mutualiser\.php|demarrer_site[[:space:]]*\(|repertoire|_SITES_ADMIN_MUTUALISATION|\b(include|include_once|require|require_once)\b' \
  -- "${php_candidates[@]}" | tr '\0' '\n'
```

The filename-only output is safe to return to the agent. Inspect matched source locally, never by printing a complete file into the agent context. Starting from every listed bootstrap candidate, follow every relevant literal `include`, `include_once`, `require`, and `require_once`; resolve each target with `realpath -e`, require it to remain under `spip_root`, add it to the candidate set, and repeat until no new relevant include remains. Dynamic or external includes that cannot be resolved safely are an explicit topology blocker. The operator or a trusted local sanitizer may return only these allowlisted facts: source file path, resolved include path, host-normalization rule, `repertoire`, `_SITES_ADMIN_MUTUALISATION`, `table_prefix`, `cookie_prefix`, and `url_img_courtes`. Never return unrelated source text; the same files may contain database, SMTP, or API credentials.

Record:

- the file that loads the plugin;
- the `$site` derivation and any `www`/port normalization;
- the `repertoire` value passed to `demarrer_site()`;
- `_SITES_ADMIN_MUTUALISATION`, if defined;
- important options such as `table_prefix`, `cookie_prefix`, and `url_img_courtes`.

The sample `mes_options.php.txt` loads `mutualiser.php`, derives `$site` from `HTTP_HOST`, and passes the `repertoire` option into `demarrer_site()`; `mutualiser.php` then stores that value in `$GLOBALS['mutualisation_dir']`, defines `_DIR_SITE`, and later builds `_SPIP_PATH` from `_DIR_SITE`, `_DIR_RACINE`, `squelettes-dist/`, `prive/`, and `_DIR_RESTREINT` ([sample `mes_options.php.txt`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/mes_options.php.txt), [`mutualiser.php`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/mutualiser.php)).

Resolve both the effective site directory and the loaded mutualisation plugin path only after reading the matched configuration:

```bash
repertoire=sites-customises                  # verified value, not a default assumption
mutualiser_include=mutualisation/mutualiser.php  # verified include path from config
mutu_dir="$(realpath -e -- "$spip_root/$repertoire")"
mutualisation_path="$(realpath -e -- "$(dirname -- "$spip_root/$mutualiser_include")")"
case "$mutu_dir" in
  "$spip_root"/*) ;;
  *) echo 'STOP: mutualisation directory escapes the shared root' >&2; exit 1 ;;
esac
case "$mutualisation_path" in
  "$spip_root"/*) ;;
  *) echo 'STOP: mutualisation plugin path escapes the shared root' >&2; exit 1 ;;
esac
```

## 3. Identify exact versions and provenance

Read the relevant version declarations, not directory names:

```bash
rg -n '\$spip_version_(affichee|branche)|SPIP_VERSION' "$spip_root/ecrire/inc_version.php"
rg -n '^(<paquet|[[:space:]]*(prefix|version|compatibilite)=)' \
  "$mutualisation_path/paquet.xml"
```

For Mutualisation facile 2.x, verify both the loaded plugin path and `paquet.xml`. The current 2.x package declares version `2.0.1` with compatibility `[4.2.0;4.*]`, so compare the installed declaration at the resolved `mutualisation_path` with the exact target rather than relying on memory ([Mutualisation facile `paquet.xml`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/paquet.xml)).

Classify core provenance:

```bash
git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null
git -C "$spip_root" status --short 2>/dev/null
test -f "$spip_root/composer.json" && test -f "$spip_root/composer.lock"
```

| Evidence | Classification |
|---|---|
| Matching Git root, remote, branch/tag, clean status | Git |
| Root package plus lock file managing SPIP | Composer |
| No VCS/package manager evidence; files match an official release | Archive |
| Mixed evidence or unexplained files | Unknown — stop before upgrade commands |

## 4. Enumerate sites without crossing boundaries

```bash
find -P "$mutu_dir" -xdev -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort
find -P "$mutu_dir" -xdev -mindepth 2 -maxdepth 2 -type l -printf '%p -> %l\n'
```

For every directory, record the domain/key, whether `config/connect.php` exists, and whether `IMG/`, `local/`, and `tmp/` exist. In `mutualiser.php`, `demarrer_site()` treats the site as not fully installed when `_DIR_SITE` is missing or the connection file cannot be found, so a missing connection file is evidence of an incomplete installation rather than a valid site ([`mutualiser.php`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/mutualiser.php)).

The connection file is usually `config/connect.php` but can be relocated or renamed through `_DIR_CONNECT` / `_FILE_CONNECT_INS` (`mutualiser.php:67-76`); treat a missing `config/connect.php` as an incomplete installation **or** a non-default connection layout, and verify before concluding. The plugin's own default `lister_sites` (`mutualisation_lister_sites_dist()`, `exec/mutualisation.php:531-539`) globs `<repertoire>/*/config/connect.php` only, so a site present on disk but absent from the administration dashboard is a diagnostic signal, not automatically an anomaly.

Identify the database engine without displaying the raw connection call. Prefer existing sanitized inventory tooling. Otherwise inspect locally and emit only an allowlisted result (`mysql`, `sqlite3`, or `unknown`). Do not `source` or `include` an untrusted configuration during an incident. SQLite files are normally beneath the site's `config/bases/`; their existence is evidence to reconcile with the sanitized connection type, not permission to print the connection file.

## 5. Map plugin impact

Inventory all configured paths before deciding that a plugin is shared:

- `plugins-dist/` — distributed with the shared core;
- shared `plugins/` and `plugins/auto/`;
- paths added through `_DIR_PLUGINS_SUPPL` or `_SPIP_PATH`;
- plugin directories inside an individual site's tree.

```bash
find -P "$spip_root/plugins" "$spip_root/plugins/auto" \
  -xdev -mindepth 1 -maxdepth 2 -type f -name paquet.xml -print 2>/dev/null

rg -n '_DIR_PLUGINS_SUPPL|_SPIP_PATH|dossier_squelettes' \
  "$spip_root/config" "$mutu_dir" --glob '*.php' 2>/dev/null
```

Do not dump serialized caches from `tmp/` into the answer. The Mutualisation administration page reads per-site `tmp/meta_cache.php` to summarize plugin state and upgrade status, so any cache-derived plugin list must be timestamped and rechecked per site before it drives an upgrade decision ([`exec/mutualisation.php`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/exec/mutualisation.php)).

## 6. Capacity, ownership, and anomalies

```bash
df -hP -- "$spip_root"
df -iP -- "$spip_root"
find -P "$spip_root" -xdev -maxdepth 2 -printf '%u:%g %m %y %p\n' | sort
find -P "$mutu_dir" -xdev -mindepth 1 -maxdepth 3 -printf '%u:%g %m %y %p\n' | sort
```

Report permission anomalies; do not run `chmod`, `chown`, ACL changes, or group membership changes. Compare with the actual PHP-FPM/Apache worker account and the deployment account before recommending a correction.

Bound recent/executable-file searches to verified roots:

```bash
incident_since='2026-08-01 00:00:00'
find -P "$mutu_dir" -xdev -type f -newermt "$incident_since" -printf '%TY-%Tm-%TdT%TH:%TM:%TS %m %u:%g %p\n'
find -P "$mutu_dir" -xdev -type f \
  \( -path '*/IMG/*.php' -o -path '*/local/*.php' -o -path '*/tmp/*.php' \) -print
```

During a suspected compromise, stop here and read [security-incident.md](security-incident.md) before hashes, copies, cache operations, or cleanup.

## 7. Required inventory output

### Shared foundation

Report shared root, SPIP version/provenance/local changes, Mutualisation facile version/path/config file, effective `repertoire`, administration site restriction, web server, PHP CLI/FPM versions, shared plugin paths, disk/inodes, whether `?exec=mutualisation` is reachable on non-administration vhosts (unauthenticated `renouvelle_alea` / `dirliste` / `dirsize` exposure — see [security-incident.md](security-incident.md)), and unknowns.

### Per-site table

| Site key/domain | Path | Install state | DB engine | Persistent size | Active/shared plugins | Owner/mode anomalies | Recent/suspicious files |
|---|---|---|---|---:|---|---|---|

Finish with prioritized anomalies and the additional evidence required. Do not turn a permission observation into a state-changing action.
