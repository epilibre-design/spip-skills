# Debug tools — request-level inspection

SPIP provides several built-in mechanisms for inspecting a request in development without touching external tools.

---

## ?var_mode — debug overlay

Append `?var_mode=debug` (or `&var_mode=debug`) to any public URL to display the debug overlay.

| Mode | URL param | Effect |
|---|---|---|
| Recalculation | `?var_mode=calcul` / `?var_mode=recalcul` | Recompute the page (recalcul also refreshes includes/images) |
| Debug overlay | `?var_mode=debug` | Shows boucles, squelettes used, compiled code; no cache write |
| Included squelettes | `?var_mode=inclure` | Displays which squelette files are included; no cache write |
| Preview | `?var_mode=preview` | Activates preview criteria in boucles (view unpublished content); no cache write |
| Other modes | `?var_mode=traduction,urls,images` | Language-string overlay / recompute URLs / recompute image filters |

`calcul` and `recalcul` are available to everyone. The other modes require an autorisation check (`debug`, or `previsualiser` for `preview`) — by default that means a logged-in webmestre/admin; unauthorized requests are redirected to login or ignored. Source: `init_var_mode()` in `ecrire/inc/utils.php`.

---

## _VAR_NOCACHE — suppress cache writes in PHP

```php
// Force regeneration without writing to cache for the current request
if (!defined('_VAR_NOCACHE')) {
    define('_VAR_NOCACHE', true);
}
```

Use in `config/mes_options.php` for site-wide dev mode, or conditionally in a pipeline before the cache layer runs.

---

## Inspecting SQL queries

- Append `?var_profile=1` to a public URL (with `debug` autorisation) to display the SQL queries used by the page, or define `_DEBUG_TRACE_QUERIES` to trace queries on every request.
- Define `_DEBUG_SLOW_QUERIES` to append a SQL comment (boucle, request URI, IP) to each query, so MySQL's own slow-query log shows where a slow query came from.

```php
define('_DEBUG_SLOW_QUERIES', true);
```

Source: `ecrire/req/mysql.php`

---

## Tracing pipeline execution

Add a `spip_log()` call at the top of any pipeline function to trace when it fires:

```php
function monplugin_pre_edition($flux) {
    spip_log(
        ['pipeline' => 'pre_edition', 'args' => $flux['args']],
        'monplugin.' . _LOG_DEBUG
    );
    return $flux;
}
```

Pair with `define('_LOG_FILTRE_GRAVITE', _LOG_DEBUG)` in `config/mes_options.php`.

---

## config/mes_options.php — dev overrides

This file is loaded early and is gitignored by SPIP conventions. Safe place for development constants:

```php
<?php
// Enable debug log level
define('_LOG_FILTRE_GRAVITE', _LOG_DEBUG);

// Tag SQL queries for the MySQL slow-query log
define('_DEBUG_SLOW_QUERIES', true);

// Disable cache: compute every request without storing
define('_NO_CACHE', -1);
```

Never commit `config/mes_options.php` to production.

---

## Reading log output in real time

```bash
# All SPIP core messages
tail -f tmp/log/spip.log

# Plugin-specific file
tail -f tmp/log/monplugin.log

# Follow all log files at once
tail -f tmp/log/*.log
```

---

## Invariants

- The no-cache `var_mode`s (`debug`, `inclure`, `preview`, `traduction`) set `_VAR_NOCACHE`, so they never pollute the cache.
- `_NO_CACHE` values (see `cache_valide()` in `ecrire/public/cacher.php`): `1` forces regeneration **and** writes a fresh cache file; `-1` regenerates without writing (like `_VAR_NOCACHE`); `0` uses the cache when present.
- `_LOG_FILTRE_GRAVITE` applies globally for the process; it cannot be scoped to a single plugin's log type.

---

## See also

- `references/spip-log.md` → `spip_log()` and `_LOG_FILTRE_GRAVITE`
- `references/journal.md` → audit log in private space
