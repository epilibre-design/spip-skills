# spip-logs Skill — GREEN Verification

Date: 2026-06-05
Score: 4/4

## Results

| # | Question | Score | Source |
|---|---|---|---|
| 1 | Two SPIP logging functions and their difference | PASS | SKILL.md quick references |
| 2 | `_LOG_DEBUG` not written by default; how to enable | PASS | `references/spip-log.md` — gravité table + filter logic |
| 3 | Warning log call with plugin-specific file | PASS | `references/spip-log.md` — signature + constants |
| 4 | Audit event in private space linked to article #42 | PASS | `references/journal.md` — options table |

---

## Answers

### Q1 — Two SPIP logging functions and their difference

From SKILL.md:

| Function | Storage | Audience | Use for |
|---|---|---|---|
| `spip_log()` | Rotating text files in `tmp/log/` | Developer | Debug traces, plugin errors, request-level events |
| `journal()` | Database (`spip_jobs` log table) | Administrator | Audit events visible at `?exec=journal` in the private space |

```php
// Developer trace — goes to tmp/log/monplugin.log
spip_log('Plugin activated for id_article=' . $id_article, 'monplugin');

// Admin-visible audit entry — stored in DB, browsable at ?exec=journal
journal('Article archivé', ['id_objet' => $id_article, 'objet' => 'article', 'etat' => 'archive']);
```

---

### Q2 — `_LOG_DEBUG` not written by default; how to enable

From `references/spip-log.md` (gravité table + filter logic):

`_LOG_DEBUG` has value `8`. The default threshold `_LOG_FILTRE_GRAVITE` equals `_LOG_INFO` (value `6`).

A message is written only when `$gravite <= _LOG_FILTRE_GRAVITE`. Since 8 > 6, `_LOG_DEBUG` messages are silently discarded in production.

To enable debug output in a development environment, raise the threshold in `config/mes_options.php`:

```php
define('_LOG_FILTRE_GRAVITE', _LOG_DEBUG);  // write all levels including debug
```

Full gravité table (from lowest to highest value):

| Constant | Value | Written by default? |
|---|---|---|
| `_LOG_HS` | 0 | yes |
| `_LOG_CRITIQUE` | 1 | yes |
| `_LOG_ERREUR` | 2 | yes |
| `_LOG_AVERTISSEMENT` | 4 | yes |
| `_LOG_INFO` | 6 | yes — **default** |
| `_LOG_DEBUG` | 8 | **no** |

---

### Q3 — Warning log call with plugin-specific log file

From `references/spip-log.md` — signature:

```php
spip_log(mixed $message, string $type = 'spip', int $gravite = _LOG_INFO): void
```

```php
spip_log('Empty result from external API', 'monplugin', _LOG_AVERTISSEMENT);
```

- `$type = 'monplugin'` → writes to `tmp/log/monplugin.log` (separate from the core `spip.log`)
- `_LOG_AVERTISSEMENT` has value 4 — written by default (4 ≤ 6)
- Arrays/objects can be passed directly; SPIP calls `var_export()` automatically

---

### Q4 — Audit event in the private space linked to article #42

From `references/journal.md` — usage example + options table:

```php
journal(
    _T('monplugin:article_publie', ['titre' => $titre]),
    [
        'qui'      => $id_auteur,
        'id_objet' => 42,
        'objet'    => 'article',
        'etat'     => 'publie',
    ]
);
```

- Entry appears in the private space at `?exec=journal`
- `id_objet` + `objet` link the entry to the article (shown as a clickable link in the journal list)
- `etat` provides a status badge in the journal UI
- `qui` defaults to the currently logged-in author if omitted

---

## Verdict

**PASS — 4/4**

The skill closes all four gaps identified in the baseline:
1. Explains both mechanisms (file vs DB), names `journal()`, locates `tmp/log/`
2. Names `_LOG_FILTRE_GRAVITE`, explains the `≤` filter logic, shows how to raise the threshold
3. Correct three-argument signature and `_LOG_AVERTISSEMENT` constant
4. `journal()` with `id_objet`, `objet`, `etat` options targeting the private-space journal
