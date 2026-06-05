---
name: spip-logs
description: Use when writing, reading, or debugging SPIP logs — spip_log(), journal(),
  log constants, log file location, gravité levels, or the private-space journal interface.
  For SPIP 4.1+.
---

# SPIP — Logs & Journal

SPIP has two distinct logging mechanisms:

- **`spip_log()`** — writes to rotating text files in `tmp/log/`; for developer/debug traces
- **`journal()`** — writes to the database; visible in the private space at `?exec=journal`

## SPIP-specific terms (always kept in original form)

| Term | Meaning |
|---|---|
| **gravité** | Severity level of a log entry (`_LOG_DEBUG`, `_LOG_INFO`, …) |
| **journal** | Administrative audit log stored in DB, visible at `?exec=journal` |
| **tmp/log/** | Directory that holds rotating `.log` files written by `spip_log()` |
| **_LOG_FILTRE_GRAVITE** | Constant that acts as a threshold: only messages with gravité ≤ this value are written |

---

## Quick reference — spip_log()

```php
spip_log(mixed $message, string $type = 'spip', int $gravite = _LOG_INFO): void
```

| Parameter | Description |
|---|---|
| `$message` | Any value — arrays/objects are `var_export()`'d automatically |
| `$type` | Log file prefix: `'spip'` → `tmp/log/spip.log` |
| `$gravite` | One of the `_LOG_*` constants (see table below) |

### Gravité constants

| Constant | Value | Written by default? | Use for |
|---|---|---|---|
| `_LOG_HS` | 0 | yes | System down / unrecoverable |
| `_LOG_CRITIQUE` | 1 | yes | Critical error |
| `_LOG_ERREUR` | 2 | yes | Recoverable error |
| `_LOG_AVERTISSEMENT` | 4 | yes | Warning |
| `_LOG_INFO` | 6 | yes | Normal info — **default** |
| `_LOG_DEBUG` | 8 | **no** | Debug trace (requires raising `_LOG_FILTRE_GRAVITE`) |

Default threshold (`_LOG_FILTRE_GRAVITE`) = `_LOG_INFO` (6). Messages with a gravité value **greater** than the threshold are silently discarded.

### Typical usage

```php
// Info — always written in production
spip_log('Plugin activated for id_article=' . $id_article, 'monplugin');

// Warning — something unexpected but non-blocking
spip_log('Empty result from external API', 'monplugin', _LOG_AVERTISSEMENT);

// Error — an operation failed
spip_log('sql_insert failed: ' . $msg, 'monplugin', _LOG_ERREUR);

// Debug — only written when debug logging is enabled
spip_log(['query' => $query, 'result' => $result], 'monplugin', _LOG_DEBUG);
```

---

## Quick reference — journal()

```php
journal(string $message, array $options = []): void
```

Writes an audit entry to the database. Visible at `?exec=journal` in the private space.

| Option key | Type | Description |
|---|---|---|
| `qui` | int | `id_auteur` of the actor (defaults to current logged-in author) |
| `quand` | string | Timestamp override (MySQL DATETIME: `YYYY-MM-DD HH:MM:SS`) |
| `id_objet` | int | Related object ID |
| `objet` | string | Related object type (e.g. `'article'`) |
| `etat` | string | Status label (e.g. `'publie'`, `'erreur'`) |
| `ip` | string | IP address override |

```php
// Log a content publication
journal(
    _T('monplugin:log_article_publie', ['titre' => $titre]),
    ['qui' => $id_auteur, 'id_objet' => $id_article, 'objet' => 'article', 'etat' => 'publie']
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
| Write an audit entry visible in the private space | `references/journal.md` |
| Link a journal entry to an objet éditorial | `references/journal.md` |
| Debug a plugin with SPIP's native debug tools | `references/debug.md` |
| Understand `_LOG_FILTRE_GRAVITE` override | `references/spip-log.md` |

---

## Workflow index

### Instrumenting a plugin with traces

1. `references/spip-log.md` → choose the right gravité
2. `references/spip-log.md` → pick a `$type` name scoped to the plugin (e.g. `'monplugin'`)
3. `references/spip-log.md` → enable debug output in dev via `_LOG_FILTRE_GRAVITE`

### Recording admin-visible audit events

1. `references/journal.md` → call `journal()` with `id_objet` / `objet` / `etat`
2. `references/journal.md` → check visibility at `?exec=journal`

### Debugging a failing pipeline or action

1. `references/debug.md` → use `spip_log()` + `?var_mode=debug` for request-level traces
2. `references/spip-log.md` → tail `tmp/log/` to read output in real time

---

## Source of truth

- `ecrire/inc/utils.php` → `spip_log()` implementation
- `ecrire/inc/journal.php` → `journal()` implementation
- `ecrire/inc/define_default.php` → `_LOG_*` constants and `_LOG_FILTRE_GRAVITE` default
