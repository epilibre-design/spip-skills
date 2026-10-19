# `bin/spip`

SPIP 5.0 console (Symfony Console, `spip-league/console`) at the project root. spip-cli (`spip …`) is a 4.x tool and
does not boot 5.0. Run from the project root, as the web server user when the command writes files.

Verified 2026-10-18, `spip/spip` `c8e329575` (`bin/spip list --raw`).

## Commands

| Command | Use | Writes |
|---|---|---|
| `spip:info` | real version (`5.0.0-beta`), `_DIR_TMP`, `_DIR_ETC`, `_FILE_OPTIONS` | no |
| `about` | PHP and parameter count; its `SPIP` line says `dev`, not the release | no |
| `spip:config:dump [--prefix=…]` | effective container parameters (default prefix `spip.`; `--prefix=kernel` for `kernel.environment`) | no |
| `config:lire <key>… [--json]` | read `spip_meta` (needs the database) | no |
| `routes:list` | the 6 HTTP routes (`/spip.php`, `/ecrire/…`, `/{path}`) | no |
| `spip:container:debug` | compiled container file and class | no |
| `spip:pipeline:listeners`, `spip:pipeline:events` | listeners per pipeline, typed events (plugin work: `spip5-plugins`) | no |
| `spip:cache:warmup [--clear]` | recompile `tmp/cache/boot/` and the container of the current environment; `--clear` purges the boot cache first and removes the other environments' containers (rebuilt on their next hit) | yes |
| `spip:cache:clear` | `--boot` (default): delete `tmp/cache/boot/`, rebuilt by the next hit; `--all`: empty `tmp/cache/` (page cache, compiled squelettes), boot rebuilt | yes |
| `config:ecrire <key>:<value>…` (`--json`, `--fichier`, `--plugin`) | write `spip_meta` | yes |
| `database:optimize [--reorder\|--clean\|--all]` | optimise the tables | yes |
| `sites:syndics:maj` | refresh syndicated feeds | yes |

Aliases: `cache:clear`, `cache:warmup`, `sql:optimize`.

Global options: `-e <env>` (the help says default `prod`; without `-e`, `APP_ENV` from `.env*` applies),
`--with-spip` (full SPIP load).

**Not available:** installing a site, activating or deactivating a plugin, running database upgrades. Use the web
installer and `ecrire/?exec=admin_plugin`; schema upgrades run on the first webmaster hit of `ecrire/`.

## Sequences

| Situation | Commands |
|---|---|
| After editing `.env*` or `config/custom.php` | `bin/spip spip:cache:warmup --clear`, then `bin/spip spip:config:dump` |
| Same, for the dev environment | `bin/spip -e dev spip:cache:warmup --clear` and `bin/spip -e dev spip:config:dump` |
| Site copied or moved | `rm -rf tmp/cache/*` then `bin/spip spip:cache:warmup --clear` (the boot cache holds absolute paths) |
| Page output stale | `bin/spip spip:cache:clear --all` (or `?var_mode=recalcul` as administrator) |
| Health check | `bin/spip spip:info`, `bin/spip config:lire version_installee adresse_site` |
