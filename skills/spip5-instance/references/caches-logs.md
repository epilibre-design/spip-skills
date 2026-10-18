# Caches and logs

Verified 2026-10-18, `spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`.

## Caches

| Cache | Where | Rebuilt by | Purge |
|---|---|---|---|
| Boot cache and compiled container | `tmp/cache/boot/` (`container.<env>.php`, `options.php`, `fonctions.php`, `chemins.php`, `menus.php`) | `bin/spip spip:cache:warmup`, or the next HTTP hit if it is missing | `bin/spip spip:cache:clear` (`--boot`) |
| Page cache, compiled squelettes, paths | `tmp/cache/` (`calcul/`, `skel/`, `paths/`…) | on demand | `bin/spip spip:cache:clear --all`, `?var_mode=recalcul` |
| Active plugin manifest | `var/state/plugins.php` | plugin activation in `ecrire/?exec=admin_plugin` (the container is recompiled at the same time) | not purged by the cache commands |
| Public assets | `local/` | on demand | — |

- The boot cache contains **absolute paths**: after copying or moving a site, `rm -rf tmp/cache/*` then warmup;
  otherwise pages answer 500 with « Cannot redeclare function … (previously declared in <old path>…) ».
- Warmup is needed after a change of `.env*` or `config/custom.php` (values are compiled in). It is not needed after
  activating a plugin from the back office, nor after editing `config/mes_options.php` (read on every hit).
- `tmp/cache/` must be writable: an empty, read-only boot cache gives HTTP 500 « An Error Occurred: Internal Server
  Error » and `mkdir(): Permission denied` in the log.

## Logs

Files: `tmp/log/<channel>.log` (`spip.log`, `maj.log` for schema upgrades, `distant.log` for HTTP fetches,
`serializer.log`…), plus the web server / PHP-FPM error log for fatal errors.

Level: `spip.logger.max_level`, PSR-3 (`debug`, `info`, `notice`, `warning`, `error`…), default `notice`; entries of
that level and above are written. Measured: with `notice`, `notice` and `warning` lines; with `debug`, also `debug`
and `info`; with `warning`, only `warning`. A `notice` entry is labelled `!INFO` in the file (legacy label).

```
2026-10-18 21:58:18 127.0.0.1 (pid 2514005) :Pub:!INFO: N
2026-10-18 21:58:18 127.0.0.1 (pid 2514005) :Pub:WARNING: W
```

`debug` on a development machine only, not in production — `config/custom.php` with `$container->env() === 'dev'`
and `APP_ENV=dev` in that machine's `.env.local` (`configuration.md`), then
`bin/spip -e dev spip:cache:warmup --clear` and check with `bin/spip -e dev spip:config:dump --prefix=spip.logger`.

`_LOG_FILTRE_GRAVITE` in `config/mes_options.php` has no effect (measured: `define('_LOG_FILTRE_GRAVITE', 7)` still
writes no `debug` line); use the `spip.logger.*` parameters. Writing logs from code (`Spip::logger()`, channels) → `spip5-plugins`, `spip-logs`.
