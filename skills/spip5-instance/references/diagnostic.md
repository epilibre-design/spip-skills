# Diagnostics

Each case was reproduced on a 5.0.0-beta copy (`spip/ecrire` `26bfe470`, 2026-10-18). Read first, change after: the
checks below are read-only; the fixes are proposed with their rollback.

## White page or HTTP 500

Start with the **web server / PHP-FPM error log** (Apache `error.log`, `php-fpm` log): several of these give an empty
500 body. Then `tmp/log/spip.log`.

| Evidence | Cause | Fix |
|---|---|---|
| Body or CLI: `Composer detected issues in your platform: … require a PHP version ">= 8.4.1"` | PHP < 8.4 for that SAPI | switch the PHP-FPM pool / Apache module to 8.4 |
| Empty 500; log: `Class "Symfony\Component\Runtime\SymfonyRuntime" not found` or a failed `require` of `vendor/autoload_runtime.php`; CLI: `Dependencies are missing. Try running "composer install".` | `vendor/` missing or incomplete (files copied without Composer) | `composer install --no-dev` |
| `Cannot redeclare function barre_outils_css_icones() (previously declared in <old path>/plugins-dist/…)`; public 500, `ecrire/` still redirects | site copied or moved, `tmp/cache/boot/` points to the old directory | `rm -rf tmp/cache/*` then `bin/spip spip:cache:warmup --clear` |
| `An Error Occurred: Internal Server Error`; log `mkdir(): Permission denied` | `tmp/cache/` not writable by the web server user | give it back write permission, warmup |
| `Artefacts boot absents : options.php introuvable…` on every hit | `SPIP_CACHE_DIR` set alone, or `spip.dirs.tmp` absolute | remove them, or use the combination in `configuration.md`; warmup |
| `Warning: Constant _DIR_TMP already defined in …/config/mes_options.php` | `_DIR_*` (or `_NOM_*`) defined in `config/mes_options.php` | remove the `define()`; use `spip.dirs.*` / `SPIP_ETC_DIR` (`configuration.md`) |

Also check `bin/spip spip:info` (version, `_DIR_TMP`, `_FILE_OPTIONS`) and that the document root is the project
root.

## Plugin activated, nothing changes

Site side, in this order:

1. `ecrire/?exec=admin_plugin`: is it in « Actifs », or in errors? « Impossible d'activer le plugin ../plugins/<name> »
   with « Nécessite SPIP en version ≤ 4.* minimum » = its `paquet.xml` `compatibilite` stops at 4: it does not run
   on 5.0. A missing dependency is shown there too.
2. `var/state/plugins.php` lists its directory (`'plugins/<name>/'`). An activation from the back office rewrites
   this file and recompiles the container at once; no command is needed.
3. Page cache: `?var_mode=recalcul` as administrator, or `bin/spip spip:cache:clear --all`.
4. Still nothing: the plugin's own code — new classes need `bin/spip spip:cache:warmup --clear`, listeners show in
   `bin/spip spip:pipeline:listeners`; the full developer checklist is in `spip5-plugins` (`outils.md`).

There is no `bin/spip` command to list or activate plugins (and no spip-cli on 5.0).

## 403 or 429 from Guard

Guard (replacing `ecran_securite.php`) answers before SPIP and writes **nothing** in `tmp/log/`; the reason is in the
response body.

| Response | Rule | Who |
|---|---|---|
| 403 `You are not authorized to view this page (robot agenda/double pagination)` | `BotAbuseUrlRule` | bot user agents with two `debut_*` parameters, or a repeated `calendrier_annee` |
| 429, `Retry-After: 300` | `LoadSheddingRule` | bots (not « friends »), GET, at random, when the load average exceeds `spip.guard.load_threshold` (default `0` = off) |

Reproduce with the client's user agent: `curl -A '<user agent>' -D - -o /dev/null '<url>'`. No command lists the
rules; plugin rules are classes with `#[AsGuard]` (`spip5-plugins`).

## Permissions

Writable by the web server user: `IMG/`, `local/`, `tmp/` (and its subdirectories), `var/state/`; `config/` during
the install. A read-only `tmp/`, `var/` or `var/state/` whose content already exists showed no symptom on public pages (measured);
a read-only empty `tmp/cache/` is an immediate 500 (above).
