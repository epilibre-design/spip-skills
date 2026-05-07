# Declaring Objets Éditoriaux

An objet éditorial is a table that participates in SPIP's full editorial machinery: statut lifecycle
(prepa → prop → publie), edition form framework (`formulaires_editer_objet_*`), authorisation checks,
cache invalidation, search indexing, and optional versioning.

Declare one via the `declarer_tables_objets_sql` pipeline.

---

## The descriptor

```php
// myplugin/base/myplugin.php

function myplugin_declarer_tables_objets_sql($tables) {
    $tables['spip_mon_objet'] = [

        // --- Identity ---
        'type'         => 'mon_objet',          // singular, snake_case
        'table_objet'  => 'mon_objets',         // plural loop name; defaults to type + 's'
        'principale'   => 'oui',                // 'oui' = tracked as main table

        // --- Schema ---
        'field' => [
            'id_mon_objet' => 'bigint(21) NOT NULL',
            'titre'        => "text DEFAULT '' NOT NULL",
            'descriptif'   => "text DEFAULT '' NOT NULL",
            'texte'        => "longtext DEFAULT '' NOT NULL",
            'statut'       => "varchar(8) DEFAULT '0' NOT NULL",
            'date'         => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
            'maj'          => 'TIMESTAMP',
        ],
        'key' => [
            'PRIMARY KEY' => 'id_mon_objet',
            'KEY statut'  => 'statut',
            'KEY date'    => 'date',
        ],

        // --- Editable fields (used by objet_modifier) ---
        'champs_editables' => ['titre', 'descriptif', 'texte'],

        // --- Canonical accessors ---
        'titre' => "titre, '' AS lang",         // SQL expression for the title
        'date'  => 'date',                      // column used as canonical date

        // --- Statut lifecycle ---
        'statut_textes_instituer' => [
            'prepa'  => 'info_statut_prepa',
            'prop'   => 'info_statut_prop',
            'publie' => 'info_statut_publie',
            'poubelle' => 'info_statut_poubelle',
        ],

        // --- UI strings (i18n keys) ---
        'texte_retour'        => 'icone_retour',
        'texte_objets'        => 'myplugin:titre_liste_objets',
        'texte_objet'         => 'myplugin:titre_un_objet',
        'info_aucun_objet'    => 'myplugin:info_aucun_objet',
        'info_1_objet'        => 'myplugin:info_1_objet',
        'info_nb_objets'      => 'myplugin:info_nb_objets',

        // --- Search ---
        'rechercher_champs' => [
            'titre'      => 8,   // weight: higher = more relevant
            'texte'      => 1,
            'descriptif' => 5,
        ],

        // --- Auto-joins (enables joining other tables in boucles) ---
        'tables_jointures' => ['mots_liens'],   // adds keyword join to this object

        // --- Versioning (requires revisions plugin) ---
        'champs_versionnes' => ['titre', 'descriptif', 'texte'],
    ];

    return $tables;
}
```

---

## Descriptor keys reference

### Required

| Key | Type | Description |
|---|---|---|
| `field` | array | Column definitions (see `references/declarer-table.md` for SQL type strings) |
| `key` | array | Index definitions |
| `type` | string | Singular type slug, matches `id_type` convention (e.g., `'article'`) |

### Strongly recommended

| Key | Type | Description |
|---|---|---|
| `principale` | `'oui'` | Mark as main table so `maj_tables()` creates it |
| `titre` | string | SQL expression for canonical title, e.g., `"titre, '' AS lang"` |
| `date` | string | Column name used as the date |
| `champs_editables` | array | Columns written by `objet_modifier()` (all others ignored) |
| `statut_textes_instituer` | array | Statut code → i18n key; activates the statut change UI |

### Optional

| Key | Type | Description |
|---|---|---|
| `table_objet` | string | Plural for the loop type; defaults to `type + 's'` |
| `table_objet_surnoms` | array | Accepted aliases in loops |
| `page` | string | URL type for public pages; `''` = no public page |
| `editable` | `'oui'/'non'` | Whether the edition UI is offered; default `'oui'`. **Setting `'non'` also restricts `objet_modifier()` to columns listed in `champs_editables`** — if `champs_editables` is empty, `objet_modifier()` becomes a no-op on that object |
| `parent` | array | `['type' => 'rubrique', 'champ' => 'id_rubrique']` for hierarchical objects |
| `rechercher_champs` | array | Column weights for full-text search |
| `tables_jointures` | array | Plural table names auto-joined in boucles |
| `champs_versionnes` | array | Columns tracked by the revisions plugin |
| `url_voir` | string | Action name for "view" URL generation |
| `url_edit` | string | Action name for "edit" URL generation |
| `texte_*`, `info_*` | string | i18n keys for backoffice UI strings |

---

## Real example — forum

```php
// plugins-dist/spip/forum/base/forum.php:90
$tables['spip_forum'] = [
    'table_objet' => 'forums',
    'type'        => 'forum',
    'editable'    => 'non',          // no standard edition form
    'principale'  => 'oui',
    'page'        => '',             // no standalone public page
    'titre'       => "titre, '' AS lang",
    'date'        => 'date_heure',

    'champs_editables' => ['titre', 'texte', 'nom_site', 'url_site'],

    'field' => [
        'id_forum'   => 'bigint(21) NOT NULL',
        'id_objet'   => "bigint(21) DEFAULT '0' NOT NULL",
        'objet'      => "VARCHAR (25) DEFAULT '' NOT NULL",
        'statut'     => "varchar(8) DEFAULT '0' NOT NULL",
        'maj'        => 'TIMESTAMP',
        // ... more fields
    ],
    'key' => [
        'PRIMARY KEY' => 'id_forum',
        'KEY id_auteur' => 'id_auteur',
        'KEY optimal'   => 'statut,id_parent,id_objet,objet,date_heure',
    ],
    'rechercher_champs' => ['titre' => 3, 'texte' => 1, 'auteur' => 2],
];
// add forum join to all objects
$tables[]['tables_jointures'][] = 'forums';
```

The `$tables[]['tables_jointures'][] = 'forums'` pattern (anonymous key `[]`) appends the jointure
to *every* registered object, not just `spip_forum` itself.

---

## High-level API: objet_inserer / objet_modifier / objet_instituer

These functions operate on any declared objet éditorial. They fire the relevant pipelines and keep
caches consistent. Use them instead of raw SQL writes for editorial content.

### objet_inserer

```php
// ecrire/action/editer_objet.php:203
$id = objet_inserer(
    'mon_objet',    // type (singular)
    $id_parent,     // optional: parent id (rubrique, etc.)
    $set            // optional: initial field values array
);
// Returns: (int) new id on success, false or 0 on failure
```

Pipelines fired: `pre_insertion` → `post_insertion`

Automatically sets `statut = 'prepa'` (or first key of `statut_textes_instituer`), the current date,
and the session language if the object supports multilingualism.

### objet_modifier

```php
// ecrire/action/editer_objet.php:94
$err = objet_modifier(
    'mon_objet',    // type
    $id,            // object id
    $set            // array of field => value to write
);
// Returns: '' on success, error string on failure
```

Only columns listed in `champs_editables` are written. Passes through `objet_modifier_champs()`
which fires the `pre_edition` / `post_edition` pipelines and invalidates caches.

If `$set` contains `statut`, `id_parent`, or the canonical date column, `objet_instituer()` is
called automatically.

### objet_instituer

```php
// ecrire/action/editer_objet.php:370
$err = objet_instituer(
    'mon_objet',    // type
    $id,            // object id
    $c,             // array with keys: 'statut', 'id_parent' (optional), date column (optional)
    $calcul_rub     // bool: recompute rubrique counts (default true)
);
// Returns: '' on success, error string on failure
```

Checks authorisations before applying the statut change. On publication, fixes the date to `NOW()`
unless a future date is already set. Fires the `post_edition` notification.

---

## Invariants

- `objet_inserer` returns 0 (not false) when the type is unknown or the table is badly declared
- `objet_modifier` silently ignores columns not in `champs_editables`
- `objet_instituer` only changes statut if authorised; it does not error out on refusal (logs instead)
- A `maj TIMESTAMP` column in `field` is mandatory — the DB keeps it current automatically
- Tables declared with `principale = 'oui'` are created by `maj_tables()` in `_administrations.php`

---

## See also

- `references/declarer-table.md` — field types, `declarer_tables_auxiliaires`, `declarer_tables_interfaces`
- `references/cycle-de-vie.md` — `_administrations.php`, `maj_tables()`, schema migrations
- `../../spip-formulaires/references/cvt-formulaires.md` — `formulaires_editer_objet_charger/verifier/traiter()` pattern
- `references/sql-api.md` — when to use raw SQL vs `objet_modifier`
