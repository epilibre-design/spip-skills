# spip_log() — file-based logging

Source: `ecrire/inc/utils.php`

---

## Signature

```php
spip_log(mixed $message, string $type = 'spip', int $gravite = _LOG_INFO): void
```

---

## Gravité constants

Defined in `ecrire/inc/define_default.php`:

| Constant | Value |
|---|---|
| `_LOG_HS` | 0 |
| `_LOG_CRITIQUE` | 1 |
| `_LOG_ERREUR` | 2 |
| `_LOG_AVERTISSEMENT` | 4 |
| `_LOG_INFO` | 6 |
| `_LOG_DEBUG` | 8 |

**Filter logic:** a message is written only if `$gravite <= _LOG_FILTRE_GRAVITE`.

Default `_LOG_FILTRE_GRAVITE` = `_LOG_INFO` (6), so `_LOG_DEBUG` (8) is discarded unless the threshold is raised.

---

## Log file naming

Log files live in `tmp/log/`. The filename is derived from `$type`:

```
tmp/log/{type}.log          ← rolling current file
tmp/log/{type}_YYYY-MM.log  ← archive for previous months
```

Examples:
- `spip_log('…', 'spip')` → `tmp/log/spip.log`
- `spip_log('…', 'monplugin')` → `tmp/log/monplugin.log`

Each line follows this format:
```
YYYY-MM-DD HH:MM:SS  [LEVEL]  message — file:line
```

---

## Message formatting

If `$message` is not a string, SPIP calls `var_export($message, true)` before writing. Arrays and objects are safe to pass directly:

```php
spip_log(['id' => $id, 'statut' => $statut], 'monplugin', _LOG_DEBUG);
```

---

## Enabling _LOG_DEBUG in development

Add to `config/mes_options.php` (never commit this):

```php
define('_LOG_FILTRE_GRAVITE', _LOG_DEBUG);
```

Or scope it to a single log type by setting the constant before the call:

```php
// Temporarily raise the threshold just for this call
if (!defined('_LOG_FILTRE_GRAVITE')) {
    define('_LOG_FILTRE_GRAVITE', _LOG_DEBUG);
}
spip_log($data, 'monplugin', _LOG_DEBUG);
```

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

In the private space, `?exec=informations` shows the last lines of `spip.log` if the admin user has access.

---

## Invariants

- `spip_log()` never throws — it silently discards on permission errors or threshold filter.
- Each call resolves `debug_backtrace()` to append the caller file/line to the message.
- Log rotation happens automatically when a file exceeds `_LOG_MAX_SIZE` (default 100 KB); the current file is renamed to `{type}_YYYY-MM.log` and a fresh file starts.

---

## See also

- `references/journal.md` → audit log visible in private space
- `references/debug.md` → `?var_mode=debug` and request-level inspection tools
