# Debug tools — request-level inspection

SPIP provides several built-in mechanisms for inspecting a request in development without touching external tools.

---

## ?var_mode — debug overlay

Append `?var_mode=debug` (or `&var_mode=debug`) to any public URL to display the debug overlay.

| Mode | URL param | Effect |
|---|---|---|
| Debug overlay | `?var_mode=debug` | Shows boucles, compilation time, cache hits, template files used |
| Force recalculation | `?var_mode=recalcul` | Bypasses cache for this request |
| Show compiler output | `?var_mode=inclure` | Dumps compiled PHP for each included squelette |
| Preview (no cache write) | `?var_mode=preview` | Equivalent to `_VAR_NOCACHE` — regenerates without writing |

`var_mode` is only available to logged-in administrators. Anonymous requests ignore it.

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

```php
// Log every SQL query to tmp/log/sql.log (very verbose)
define('_LOG_REQUETES_LONGUES', 0); // log all queries, not just slow ones
```

`_LOG_REQUETES_LONGUES` is a threshold in milliseconds. Setting it to `0` logs everything. Default is unset (no query logging).

Source: `ecrire/req/mysql.php`

---

## Tracing pipeline execution

Add a `spip_log()` call at the top of any pipeline function to trace when it fires:

```php
function monplugin_pre_edition($flux) {
    spip_log(
        ['pipeline' => 'pre_edition', 'args' => $flux['args']],
        'monplugin',
        _LOG_DEBUG
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

// Log all SQL queries
define('_LOG_REQUETES_LONGUES', 0);

// Disable cache for all requests
define('_NO_CACHE', 1);
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

- `?var_mode` is stripped from cache keys — using it never pollutes the cache.
- `_NO_CACHE = 1` forces regeneration **and** writes a fresh cache file (use `_VAR_NOCACHE` to regenerate without writing).
- `_LOG_FILTRE_GRAVITE` applies globally for the process; it cannot be scoped to a single plugin's log type.

---

## See also

- `references/spip-log.md` → `spip_log()` and `_LOG_FILTRE_GRAVITE`
- `references/journal.md` → audit log in private space
