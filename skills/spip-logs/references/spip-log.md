# spip_log() — file-based logging

Source: `spip_log()` in `ecrire/inc/utils.php`; the file writer is `inc_log_dist()` in `ecrire/inc/log.php`.

---

## Signature

```php
spip_log(mixed $message = null, string|int $name = null): void
```

- `spip_log($msg)` → `spip.log`, gravité `_LOG_INFO`
- `spip_log($msg, 'monplugin')` → `monplugin.log`, gravité `_LOG_INFO`
- `spip_log($msg, _LOG_DEBUG)` → `spip.log`, gravité `_LOG_DEBUG`
- `spip_log($msg, 'monplugin.' . _LOG_DEBUG)` → `monplugin.log`, gravité `_LOG_DEBUG`

---

## Gravité constants

Defined in `ecrire/inc_version.php`:

| Constant | Value |
|---|---|
| `_LOG_HS` | 0 |
| `_LOG_ALERTE_ROUGE` | 1 |
| `_LOG_CRITIQUE` | 2 |
| `_LOG_ERREUR` | 3 |
| `_LOG_AVERTISSEMENT` | 4 |
| `_LOG_INFO_IMPORTANTE` | 5 |
| `_LOG_INFO` | 6 |
| `_LOG_DEBUG` | 7 |

**Filter logic:** a message is written only if `$gravite <= _LOG_FILTRE_GRAVITE`.

Default `_LOG_FILTRE_GRAVITE` = `_LOG_INFO_IMPORTANTE` (5), so both `_LOG_INFO` (6) — the default gravité of a `spip_log()` call — and `_LOG_DEBUG` (7) are discarded unless the threshold is raised.

---

## Log file naming and rotation

Log files live in `tmp/log/` (`_DIR_LOG`). The filename comes from the type part of `$name` (default `'spip'`, i.e. `_FILE_LOG`):

```
tmp/log/{type}.log    ← current file
tmp/log/{type}.log.1  ← most recent rotated file
tmp/log/{type}.log.2  …up to $GLOBALS['nombre_de_logs'] (default 4)
```

Rotation triggers when the current file exceeds `$GLOBALS['taille_des_logs']` KB (default 100).

Each line follows this format (caller `file:line:function` is only prepended when `_LOG_FILELINE` is defined and true):
```
YYYY-MM-DD HH:MM:SS IP (pid NNNNN) :Pri:|:Pub: level: message
```

`:Pri:`/`:Pub:` distinguishes private-space from public requests. Messages written to a named log (e.g. `monplugin.log`) are **also duplicated into `spip.log`**.

A single PHP process writes at most `_MAX_LOG` lines (default 100) per log file; further calls are dropped silently (except for the `maj` log).

---

## Message formatting

If `$message` is not a string, `spip_log()` calls `print_r($message, true)` before writing. Arrays and objects are safe to pass directly:

```php
spip_log(['id' => $id, 'statut' => $statut], 'monplugin.' . _LOG_DEBUG);
```

`<` characters are escaped to `&lt;` in the written line unless `_LOG_BRUT` is defined and true.

---

## Enabling _LOG_DEBUG in development

Add to `config/mes_options.php` (never commit this):

```php
define('_LOG_FILTRE_GRAVITE', _LOG_DEBUG);
```

`_LOG_FILTRE_GRAVITE` is a global constant for the entire PHP process — once defined it cannot be reverted or scoped to a single call or plugin. Set it only in `config/mes_options.php` for the whole dev environment.

---

## Good practices

- Use a `$type` name matching the plugin prefix to keep log files separated.
- Pass arrays directly rather than manually serializing them.
- Reserve `_LOG_ERREUR` and above for actual failures — not warnings or unexpected-but-handled states.
- Never log passwords, tokens, or personal data.
- `spip_log()` is a no-op if the `tmp/log/` directory is not writable — confirm permissions in production.

---

## Reading logs

In production, tail the file:
```bash
tail -f tmp/log/monplugin.log
```

There is no core private-space screen for browsing log files — read them on the server (or via a dedicated plugin).

---

## Invariants

- `spip_log()` never throws — it silently discards on permission errors or threshold filter.
- Caller file/line is resolved via `debug_backtrace()` only when `_LOG_FILELINE` is defined and true — not on every call.
- Log rotation happens automatically when a file exceeds `$GLOBALS['taille_des_logs']` KB (default 100); files are shifted to `{type}.log.1`, `.2`, … keeping `$GLOBALS['nombre_de_logs']` (default 4) old files.

---

## See also

- `references/journal.md` → audit log visible in private space
- `references/debug.md` → `?var_mode=debug` and request-level inspection tools
