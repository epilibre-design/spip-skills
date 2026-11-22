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

## 3. Static: PHPStan, Rector

Configuration and commands: `spip5-plugins` `outils.md`, *Static analysis*. What to expect (verified on 5.0.0-beta):

- **PHPStan** + `phpstan/phpstan-deprecation-rules`, level ≥ 1, scanning a 5.0 instance: reports `#[\Deprecated]`
  symbols, removed functions (`function.notFound`) and removed constants (`constant.notFound`). It misses everything
  deprecated by `trigger_deprecation()` only (`_request()`, `Kernel\param()`, argument forms): keep steps 1–2 and
  step 4.
- **Rector**: `spip-league/rector` has no 5.0 rule (`SPIP_50` set empty) and is not pulled by `spip-league/qa-tools`.
  Its 4.1 rule renames `generer_url_entite*` without moving a connection argument: check each changed call against
  `catalogue.md`.

## 4. Runtime on a 5.0 instance

- Requirements first: PHP **8.4**, `composer update` (Symfony 8.1), `plugins-dist/` now in `plugins-dist/spip/<plugin>/`
  (delete old copies at the root of `plugins-dist/`), config files only in `config/` (no `ecrire/mes_options.php`).
- Log deprecations (`spip.logger.max_level` → `debug` in `config/custom.php`), browse every page, form and task of the plugin,
  read `tmp/log/`.
- Squelettes: `?var_mode=recalcul`; JavaScript: `?var_mode=debug_js` (browser console).

## 5. Console `bin/spip` (5.0)

The command reference (every command, what it writes, global options, sequences) is owned by `spip5-instance`
(`references/bin-spip.md`). For an audit, the useful ones are:

| Command | Audit use |
|---|---|
| `bin/spip spip:cache:warmup --clear` | recompile the container after changing the plugin; a compile error shows here first |
| `bin/spip spip:pipeline:listeners` (alias `pipeline:listeners`) | is the plugin's listener registered on its `spip.pipeline.*`? |
| `bin/spip spip:pipeline:events` (alias `pipeline:events`) | which pipelines have a typed event |
| `bin/spip spip:container:debug` | compiled container file and class |

`bin/spip` is not spip-cli (`spip …`, the 4.x tool), not `bin/console`.

## Listener not called

Checklist (plugin active, PSR-4, `services.php`, exact attribute, event type, warmup, page cache, double registration):
`spip5-plugins` `outils.md`, *Listener / rule / task not called*.
