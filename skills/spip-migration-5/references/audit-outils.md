# Auditing a plugin for SPIP 5.0

---

## 1. Static: grep from the catalogue

Run the grep block at the end of `catalogue.md` on the plugin. Each hit maps to a row of the catalogue.

## 2. Static: deprecation markers of the 5.0 source

SPIP 5.0 marks deprecations two ways — search **both** in the 5.0 source to get the up-to-date list:

```bash
cd /path/to/spip5
grep -rnE "trigger_deprecation\(\s*'spip[^']*',\s*'5\.0'" ecrire prive plugins-dist vendor/spip vendor/spip-league --include=*.php
grep -rnE -A1 "#\[\\\\?Deprecated\(.*since: '5\.0'" ecrire prive plugins-dist vendor/spip vendor/spip-league --include=*.php
```

`#[\Deprecated]` (PHP 8.4 attribute) carries the message, e.g. `spip_log`, `_request`, `spip_query`, `abs_url`,
the Kernel path methods, critères `fusion`/`collecte`. `trigger_deprecation()` covers argument forms (`lire_config(,,false)`,
`/store/` prefix, `_T` with `spip_lang`, `$GLOBALS['spip_pipeline']`, `menu_squelette`, `bando_*`, cookie constants).

Static analysis: PHPStan with deprecation rules against the 5.0 sources; Rector rules for SPIP live in
`spip-league/rector` (pulled by `spip-league/qa-tools` / `spip-league/sdk`).

## 3. Runtime on a 5.0 instance

- Requirements first: PHP **8.4**, `composer update` (Symfony 8.1), `plugins-dist/` now in `plugins-dist/spip/<plugin>/`
  (delete old copies at the root of `plugins-dist/`), config files only in `config/` (no `ecrire/mes_options.php`).
- Log deprecations (`spip.logger.max_level` → `debug` in `config/custom.php`), browse every page, form and task of the plugin,
  read `tmp/log/`.
- Squelettes: `?var_mode=recalcul`; JavaScript: `?var_mode=debug_js` (browser console).

## 4. Console `bin/spip` (5.0)

SPIP 5.0 ships **`bin/spip`** at the project root (`spip-league/console`, Symfony Console). It is not spip-cli
(`spip …`, the 4.x tool), not `bin/console`. `bin/spip list` shows what the instance provides.

| Command | Use |
|---|---|
| `bin/spip spip:cache:warmup --clear` (alias `cache:warmup`) | purge boot caches and recompile the container + plugin caches |
| `bin/spip spip:cache:clear` (`--boot` default, `--all` = whole `tmp/cache/`) | purge caches |
| `bin/spip spip:pipeline:listeners` (alias `pipeline:listeners`) | listeners registered on `spip.pipeline.*` |
| `bin/spip spip:pipeline:events` (alias `pipeline:events`) | pipelines that have a typed event |
| `bin/spip spip:container:debug` | container debug information |
| `bin/spip spip:config:dump` | effective `spip.*` parameters (defaults + `config/custom.php` + derived) |
| `bin/spip routes:list` | declared routes |
| `bin/spip config:lire` / `config:ecrire` | read / write `spip_meta` |
| `bin/spip about` | environment |

Global options: `-e/--env` (default `prod`), `--with-spip` (force full SPIP load).

## Listener not called — checklist

1. Class under the PSR-4 namespace of the plugin `composer.json`, file path matches.
2. Namespace loaded by `config/services.php` with `autowire()` + `autoconfigure()`.
3. Attribute is `SpipLeague\Bridge\Pipeline\Attribute\AsPipelineListener` (not Symfony's `AsEventListener`).
4. Argument typed with the exact event class, or `PipelineEvent` **with** the pipeline name.
5. Plugin active (manifest `var/state/plugins.php` lists its path).
6. `bin/spip spip:cache:warmup --clear`, then `bin/spip spip:pipeline:listeners`.
7. The same pipeline is not also handled by a legacy function that overwrites the result.
