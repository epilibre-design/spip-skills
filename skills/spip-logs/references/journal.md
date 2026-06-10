# journal() — site event log

Source: `journal()` in `ecrire/inc/utils.php`, delegating to `inc_journal_dist()` in `ecrire/inc/journal.php`.

The journal API records site events ("journal de bord du site"). The core implementation is deliberately minimal: it writes to a dedicated log file. A plugin can override it to store entries in the database and provide display/selection tools.

---

## Signature

```php
journal(string $phrase, array $opt = []): void
```

`journal()` loads the implementation with `charger_fonction('journal', 'inc')` and calls it — so a plugin shipping its own `inc/journal.php` (function `inc_journal()`) replaces the core behavior entirely.

---

## Core behavior (`inc_journal_dist`)

```php
function inc_journal_dist($phrase, $opt = []) {
    if (!strlen($phrase)) {
        return;
    }
    if ($opt) {
        $phrase .= ' :: ' . str_replace("\n", ' ', join(', ', $opt));
    }
    spip_log($phrase, 'journal');
}
```

- An empty `$phrase` is ignored.
- `$opt` has **no fixed schema**: its values are simply joined with `', '` and appended to the phrase after `' :: '` (keys are not written).
- The result goes through `spip_log($phrase, 'journal')` → `tmp/log/journal.log`, at gravité `_LOG_INFO` (6). With the default `_LOG_FILTRE_GRAVITE` (= `_LOG_INFO_IMPORTANTE`, 5), journal entries are therefore **not written** unless the threshold is raised — or a plugin provides a real storage backend.

---

## Usage examples

### Simple event

```php
journal(_T('monplugin:import_complete', ['nb' => $nb]));
```

### With context values

```php
journal(
    _T('monplugin:article_archive', ['titre' => $titre]),
    ['article ' . $id_article, 'statut archive']
);
```

---

## When to use journal() vs spip_log()

| Need | Use |
|---|---|
| Site-level event that a plugin may later store/display for admins | `journal()` |
| Developer/debug trace (arrays, raw data, stack context) | `spip_log()` |
| Error or warning from a pipeline | `spip_log()` with `'type.' . _LOG_ERREUR` / `_LOG_AVERTISSEMENT` |
| Both (critical operation) | both — `journal()` for the site event, `spip_log()` for the developer |

---

## Storage and viewing

In core, entries land in `tmp/log/journal.log` (subject to the same rotation as any `spip_log()` file). 
There is no DB table and no private-space screen for the journal in core — that is left to plugins overriding `inc_journal`.

---

## Invariants

- The `$phrase` string is stored raw; use `_T()` to produce translatable strings.
- `$opt` values are flattened into the message — they carry no semantics in core.
- The override point is `charger_fonction('journal', 'inc')`; never call `inc_journal_dist()` directly.

---

## See also

- `references/spip-log.md` → `spip_log()` for file-based traces
- `references/debug.md` → request-level debug tools
