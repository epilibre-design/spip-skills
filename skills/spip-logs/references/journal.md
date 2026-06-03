# journal() — admin-visible audit log

Source: `ecrire/inc/journal.php`

The journal records human-readable events that administrators can browse at `?exec=journal` in the private space. It complements `spip_log()` (file-based developer traces) with structured, DB-stored audit entries.

---

## Signature

```php
journal(string $message, array $options = []): void
```

---

## Options

| Key | Type | Default | Description |
|---|---|---|---|
| `qui` | int | current `$GLOBALS['visiteur_session']['id_auteur']` | Author performing the action |
| `quand` | string | now | Timestamp override (MySQL `DATETIME` format) |
| `id_objet` | int | 0 | ID of the related objet éditorial |
| `objet` | string | `''` | Type of the related objet (e.g. `'article'`, `'rubrique'`) |
| `etat` | string | `''` | State label displayed in the journal list |
| `ip` | string | auto-detected | IP address of the actor |

---

## Usage examples

### Simple trace (no object)

```php
journal(_T('monplugin:import_complete', ['nb' => $nb]));
```

### Linked to an objet éditorial

```php
journal(
    _T('monplugin:article_archive', ['titre' => $titre]),
    [
        'id_objet' => $id_article,
        'objet'    => 'article',
        'etat'     => 'archive',
    ]
);
```

### On behalf of another author

```php
journal(
    'Batch migration executed',
    ['qui' => $id_auteur_systeme, 'etat' => 'ok']
);
```

---

## When to use journal() vs spip_log()

| Need | Use |
|---|---|
| Trace visible to site admins in the private space | `journal()` |
| Developer/debug trace (arrays, raw data, stack context) | `spip_log()` |
| Audit: who changed what, when | `journal()` |
| Error or warning from a pipeline | `spip_log()` with `_LOG_ERREUR` / `_LOG_AVERTISSEMENT` |
| Both (critical operation) | both — `journal()` for the admin, `spip_log()` for the developer |

---

## Storage

Entries are stored in the `spip_meta` table (key `journal`) up to SPIP 4.1, and in a dedicated `spip_jobs` mechanism from SPIP 4.2 onward. Do not read this table directly — use `?exec=journal` or the `lire_config('journal')` API if you need programmatic access.

---

## Viewing the journal

Private space: `?exec=journal`

The list shows entries with author, date, object link, and state. No pagination — the journal is truncated automatically by SPIP to the most recent N entries.

---

## Invariants

- `journal()` writes to the DB — avoid calling it in hot paths (e.g. inside a loop over thousands of objects).
- The `$message` string is stored raw; use `_T()` to produce translatable strings.
- If no `qui` is provided and there is no active session, the entry is recorded with `id_auteur = 0`.

---

## See also

- `references/spip-log.md` → `spip_log()` for file-based traces
- `references/debug.md` → request-level debug tools
