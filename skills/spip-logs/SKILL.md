---
name: spip-logs
description: Use when writing, reading, or debugging SPIP logs — spip_log(), journal(),
  log constants, log file location, gravité levels, or log rotation.
  For SPIP 4.1+.
---

# SPIP — Logs & Journal

SPIP has two distinct logging entry points:

- **`spip_log()`** — writes to rotating text files in `tmp/log/`; for developer/debug traces
- **`journal()`** — event log API; in core it writes to `tmp/log/journal.log` via `spip_log()`, and a plugin can override it (via `charger_fonction('journal', 'inc')`) to store entries in DB and display them

## SPIP-specific terms (always kept in original form)

| Term | Meaning |
|---|---|
| **gravité** | Severity level of a log entry (`_LOG_DEBUG`, `_LOG_INFO`, …) |
| **journal** | Site event log; minimal in core (a dedicated log file), extensible by plugins |
| **tmp/log/** | Directory that holds rotating `.log` files written by `spip_log()` |
| **_LOG_FILTRE_GRAVITE** | Constant that acts as a threshold: only messages with gravité ≤ this value are written |

---

## Quick reference — spip_log()

```php
spip_log(mixed $message = null, string|int $name = null): void
```

| `$name` form | Meaning | Example |
|---|---|---|
| `'type'` | Log file prefix, gravité defaults to `_LOG_INFO` | `'monplugin'` → `tmp/log/monplugin.log` |
| int | Gravité, file defaults to `spip.log` | `_LOG_ERREUR` |
| `'type.' . niveau` | Both at once | `'monplugin.' . _LOG_DEBUG` |

`$message` accepts any value — non-strings are dumped with `print_r()` automatically.

### Gravité constants

| Constant | Value | Written by default? | Use for |
|---|---|---|---|
| `_LOG_HS` | 0 | yes | System down / unrecoverable |
| `_LOG_ALERTE_ROUGE` | 1 | yes | Red alert |
| `_LOG_CRITIQUE` | 2 | yes | Critical error |
| `_LOG_ERREUR` | 3 | yes | Recoverable error |
| `_LOG_AVERTISSEMENT` | 4 | yes | Warning |
| `_LOG_INFO_IMPORTANTE` | 5 | yes | Important info |
| `_LOG_INFO` | 6 | **no** | Normal info — **default gravité of a `spip_log()` call** |
| `_LOG_DEBUG` | 7 | **no** | Debug trace |

Default threshold (`_LOG_FILTRE_GRAVITE`) = `_LOG_INFO_IMPORTANTE` (5). Messages with a gravité value **greater** than the threshold are silently discarded — so a plain `spip_log($msg, 'monplugin')` (gravité `_LOG_INFO` = 6) writes **nothing** until the threshold is raised in `config/mes_options.php`.

### Typical usage

```php
// Info — requires _LOG_FILTRE_GRAVITE >= _LOG_INFO to be written
spip_log('Plugin activated for id_article=' . $id_article, 'monplugin');

// Important info — written with the default threshold
spip_log('Import finished', 'monplugin.' . _LOG_INFO_IMPORTANTE);

// Warning — something unexpected but non-blocking
spip_log('Empty result from external API', 'monplugin.' . _LOG_AVERTISSEMENT);

// Error — an operation failed
spip_log('sql_insert failed: ' . $msg, 'monplugin.' . _LOG_ERREUR);

// Debug — only written when _LOG_FILTRE_GRAVITE >= _LOG_DEBUG
spip_log(['query' => $query, 'result' => $result], 'monplugin.' . _LOG_DEBUG);
```

---

## Quick reference — journal()

```php
journal(string $phrase, array $opt = []): void
```

Records a site event. The core implementation (`inc_journal_dist()` in `ecrire/inc/journal.php`) is minimal: it appends the `$opt` values to the phrase (`' :: ' . join(', ', $opt)`) and calls `spip_log($phrase, 'journal')` — so entries land in `tmp/log/journal.log`. There is no fixed option schema and no DB storage in core; a plugin can override `inc_journal` (loaded via `charger_fonction('journal', 'inc')`) to store entries in base and provide display/selection tools.

```php
// Log a content publication event
journal(
    _T('monplugin:log_article_publie', ['titre' => $titre]),
    ['id_article' => $id_article, 'statut' => 'publie']
);
```

---

## Decision tree — I want to…

| Goal | Read |
|---|---|
| Write a developer/debug trace | `references/spip-log.md` |
| Configure gravité levels and log threshold | `references/spip-log.md` |
| Understand log file naming and rotation | `references/spip-log.md` |
| Enable `_LOG_DEBUG` messages in dev | `references/spip-log.md` |
| Record a site event with `journal()` | `references/journal.md` |
| Store/display journal entries beyond `tmp/log/journal.log` | `references/journal.md` (plugin override) |
| Debug a plugin with SPIP's native debug tools | `references/debug.md` |
| Understand `_LOG_FILTRE_GRAVITE` override | `references/spip-log.md` |

---

## Workflow index

### Instrumenting a plugin with traces

1. `references/spip-log.md` → choose the right gravité
2. `references/spip-log.md` → pick a `$type` name scoped to the plugin (e.g. `'monplugin'`)
3. `references/spip-log.md` → enable debug output in dev via `_LOG_FILTRE_GRAVITE`

### Recording site events with journal()

1. `references/journal.md` → call `journal()` with a phrase and optional context array
2. `references/journal.md` → read `tmp/log/journal.log` (or a plugin's storage if `inc_journal` is overridden)

### Debugging a failing pipeline or action

1. `references/debug.md` → use `spip_log()` + `?var_mode=debug` for request-level traces
2. `references/spip-log.md` → tail `tmp/log/` to read output in real time

---

## Source of truth

- `ecrire/inc/utils.php` → `spip_log()` and `journal()` entry points
- `ecrire/inc/log.php` → `inc_log_dist()`, the actual file writer (naming, rotation, line format)
- `ecrire/inc/journal.php` → `inc_journal_dist()`, the core journal implementation
- `ecrire/inc_version.php` → `_LOG_*` constants and `_LOG_FILTRE_GRAVITE` default
