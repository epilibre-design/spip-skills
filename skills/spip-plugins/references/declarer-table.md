# Declaring Tables

SPIP discovers plugin tables through four pipelines declared in the plugin's `_pipelines.php` (or the
file registered as `pipeline` in `paquet.xml`). Each pipeline function receives an array and must
return it augmented.

There are three table tiers:

| Pipeline | What it declares | Example |
|---|---|---|
| `declarer_tables_objets_sql` | Full objet éditorial (statut, titre, date, édition) | `spip_mots`, `spip_forum` |
| `declarer_tables_principales` | Non-editorial main tables (no statut/titre machinery) | `spip_jobs`, custom log tables |
| `declarer_tables_auxiliaires` | Link/pivot tables (no primary key for an object) | `spip_mots_liens` |
| `declarer_tables_interfaces` | Compiler-level aliases, treatments, jointures | column alias, statut rules |

For objets éditoriaux, see `references/declarer-objet.md` — this document covers the lower two tiers
and the interfaces pipeline.

---

## Field descriptor format

All tiers use the same `field` / `key` structure:

```php
$champs = [
    'id_mon_objet' => 'bigint(21) NOT NULL',
    'titre'        => "text DEFAULT '' NOT NULL",
    'statut'       => "varchar(8) DEFAULT '0' NOT NULL",
    'date'         => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
    'maj'          => 'TIMESTAMP',
];

$cles = [
    'PRIMARY KEY'    => 'id_mon_objet',        // single column primary key
    'KEY id_parent'  => 'id_parent',            // index named 'id_parent'
    'KEY optimal'    => 'statut,date',          // composite index
];
```

### SQL types in `field`

SPIP uses MySQL-style type strings. The database layer translates them for SQLite/PostgreSQL.
Common patterns:

| Value | Use for |
|---|---|
| `'bigint(21) NOT NULL'` | integer primary keys and foreign keys |
| `"bigint(21) DEFAULT '0' NOT NULL"` | nullable FK-style columns |
| `"varchar(255) DEFAULT '' NOT NULL"` | short strings |
| `"text DEFAULT '' NOT NULL"` | long strings |
| `"mediumtext DEFAULT '' NOT NULL"` | very long content |
| `"datetime DEFAULT '0000-00-00 00:00:00' NOT NULL"` | dates |
| `'TIMESTAMP'` | `maj` column (auto-updated by DB on every write) |
| `"ENUM('oui','non') DEFAULT 'non' NOT NULL"` | boolean-style flags |

Always include a `maj TIMESTAMP` column — it is the last-modified marker used by SPIP's cache.

---

## declarer_tables_principales

For tables that are installed and managed by SPIP schema tools but do not participate in the full
objet éditorial machinery (no statut, no editorial title, no edition form framework).

```php
// myplugin/myplugin_pipelines.php

function myplugin_declarer_tables_principales($tables) {
    $champs = [
        'id_log'    => 'bigint(21) NOT NULL',
        'date'      => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
        'niveau'    => "varchar(16) DEFAULT '' NOT NULL",
        'message'   => "text DEFAULT '' NOT NULL",
        'maj'       => 'TIMESTAMP',
    ];
    $cles = [
        'PRIMARY KEY' => 'id_log',
        'KEY date'    => 'date',
        'KEY niveau'  => 'niveau',
    ];

    $tables['spip_myplugin_logs'] = ['field' => &$champs, 'key' => &$cles];
    return $tables;
}
```

Register in `paquet.xml`:
```xml
<pipeline nom="declarer_tables_principales" inclure="myplugin_pipelines.php" />
```

SPIP's `_administrations.php` calls `maj_tables()` which compares the declared schema against the
actual DB and adds missing columns and indexes. See `references/cycle-de-vie.md`.

---

## declarer_tables_auxiliaires

For link/pivot tables (many-to-many associations) and other tables with no single-object primary key.

```php
// plugins-dist/spip/mots/base/mots.php:60
function mots_declarer_tables_auxiliaires($tables_auxiliaires) {
    $spip_mots_liens = [
        'id_mot'   => "bigint(21) DEFAULT '0' NOT NULL",
        'id_objet' => "bigint(21) DEFAULT '0' NOT NULL",
        'objet'    => "VARCHAR (25) DEFAULT '' NOT NULL"
    ];
    $spip_mots_liens_key = [
        'PRIMARY KEY' => 'id_mot,id_objet,objet',   // composite PK
        'KEY id_mot'  => 'id_mot',
        'KEY id_objet' => 'id_objet',
        'KEY objet'   => 'objet',
    ];

    $tables_auxiliaires['spip_mots_liens'] =
        ['field' => &$spip_mots_liens, 'key' => &$spip_mots_liens_key];

    return $tables_auxiliaires;
}
```

Auxiliary tables are created by `maj_tables()` but do **not** appear in the editorial backoffice
and have no statut/edition machinery.

---

## declarer_tables_interfaces

Registers compiler-level information: how the compiler translates `<BOUCLE_(forums)>` to SQL,
which column aliases exist, how `statut` maps to "published", and what text treatments apply.

```php
// plugins-dist/spip/forum/base/forum.php:29
function forum_declarer_tables_interfaces($interfaces) {

    // map plural loop name → table suffix
    // 'forums' → spip_forum (the compiler prepends 'spip_')
    $interfaces['table_des_tables']['forums'] = 'forum';

    // column aliases: #DATE in a forums loop reads 'date_heure', not 'date'
    $interfaces['exceptions_des_tables']['forums']['date'] = 'date_heure';
    $interfaces['exceptions_des_tables']['forums']['nom']  = 'auteur';

    // compiler jointure overrides (prevent wrong auto-join)
    $interfaces['exceptions_des_jointures']['spip_forum']['id_secteur'] = ['spip_articles', 'id_secteur'];

    // statut rules: what counts as 'published' in a boucle
    $interfaces['table_statut']['spip_forum'][] = [
        'champ'    => 'statut',
        'publie'   => 'publie',
        'previsu'  => 'publie,prop',
        'exception' => 'statut',    // allows {statut=publie} in loops
    ];

    // text treatment: what filter applies to #TEXTE in a forums loop
    $interfaces['table_des_traitements']['TEXTE']['forums'] = 'safehtml(%s)';

    return $interfaces;
}
```

### Key sub-arrays of `$interfaces`

| Sub-array | Purpose |
|---|---|
| `table_des_tables` | Maps loop plural name to table suffix (`'forums' => 'forum'`) |
| `exceptions_des_tables[$loop][$balise]` | Column alias within a loop (`'date' => 'date_heure'`) |
| `exceptions_des_jointures[$table][$balise]` | Redirect auto-join for a column to a specific table |
| `table_statut[$table][]` | Publication status rules (`publie`, `previsu`, `exception`) |
| `table_des_traitements[$BALISE][$loop]` | PHP expression applied to a `#BALISE` in a loop |
| `table_titre[$table]` | SQL expression that produces the canonical title (default: `'titre, lang'`) |
| `table_date[$table]` | Column name used as canonical date |

---

## File placement

```
myplugin/
├── paquet.xml
└── base/
    └── myplugin.php    ← pipeline functions: declarer_tables_principales, _auxiliaires, _interfaces
```

Or register directly from the root `myplugin_pipelines.php` file — either works as long as the
`inclure=` attribute in `paquet.xml` points to the right file.

---

## See also

- `references/declarer-objet.md` — full objet éditorial descriptor (`declarer_tables_objets_sql`)
- `references/cycle-de-vie.md` — when `maj_tables()` runs and how to write migration steps
- `references/sql-api.md` — runtime SQL operations on declared tables
