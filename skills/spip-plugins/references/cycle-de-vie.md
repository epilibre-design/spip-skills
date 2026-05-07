# Plugin Lifecycle — Installation and Migration

SPIP calls a plugin's installation and uninstallation logic through two conventions: the `schema`
attribute in `paquet.xml` and two PHP functions in the `_administrations.php` file.

---

## Activating installation support

In `paquet.xml`, simply add the `schema` attribute to `<paquet>`:

```xml
<!-- paquet.xml -->
<paquet
  prefix="monplugin"
  version="1.3.0"
  schema="1.3.0"
  ...>

  <nom>Mon Plugin</nom>
  ...
</paquet>
```

That alone is enough. SPIP loads `{prefix}_administrations.php` from the plugin root **by
naming convention** — there is **no `<install>` tag** in the `paquet.xml` DTD. (The `<install>`
element existed in the old `plugin.xml` format, removed when SPIP 3 introduced `paquet.xml`.)

- `schema` is the **current schema version** — when it differs from the stored meta value, SPIP
  triggers the upgrade (or install)
- Without `schema`, SPIP never calls `{prefix}_upgrade()` — tables declared via
  `declarer_tables_objets_sql` / `declarer_tables_principales` are created by `maj_tables()` but
  only if you call it yourself (or at initial site install)
- `{prefix}_administrations.php` at the plugin root must contain `{prefix}_upgrade()` and
  `{prefix}_vider_tables()`

---

## The administrations file

File: `myplugin/monplugin_administrations.php`

```php
if (!defined('_ECRIRE_INC_VERSION')) {
    return;
}

function monplugin_upgrade($nom_meta_base_version, $version_cible) {
    $maj = [];

    // 'create' runs on fresh install (no existing meta), then jumps to $version_cible
    $maj['create'] = [
        ['maj_tables', ['spip_monplugin']],
    ];

    // incremental migration steps, keyed by version string
    $maj['1.1.0'] = [
        ['sql_alter', "TABLE spip_monplugin ADD mon_champ TEXT DEFAULT '' NOT NULL"],
    ];
    $maj['1.2.0'] = [
        ['sql_alter', 'TABLE spip_monplugin ADD INDEX mon_champ (mon_champ)'],
        ['sql_updateq', 'spip_monplugin', ['statut' => 'publie'], "statut='oui'"],
    ];
    $maj['1.3.0'] = [
        ['maj_tables', ['spip_monplugin']],   // picks up new columns from the descriptor
    ];

    include_spip('base/upgrade');
    maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

function monplugin_vider_tables($nom_meta_base_version) {
    sql_drop_table('spip_monplugin');
    effacer_meta($nom_meta_base_version);
}
```

---

## maj_plugin() — the upgrade driver

```php
// ecrire/base/upgrade.php:205
maj_plugin(
    $nom_meta_base_version,   // meta key storing the installed schema version (e.g., 'monplugin_base_version')
    $version_cible,           // the schema version from paquet.xml (the target)
    $maj,                     // array of migration steps
    $table_meta = 'meta'      // which meta table to use (almost always 'meta')
): void
```

How it runs:
1. Reads `$GLOBALS['meta'][$nom_meta_base_version]` as the current installed version
2. If missing (fresh install) and `$maj['create']` exists: runs `create` steps only, then jumps to
   `$version_cible`
3. Otherwise: sorts all version keys semantically (semver) and runs every step where
   `installed < step_key <= $version_cible`
4. After each version's steps complete: writes the version into the meta table
5. On timeout: redirects to the admin page to continue

---

## Step format

Each step in `$maj['x.y.z']` is an array `[function_name, arg1, arg2, ...]`:

```php
$maj['1.1.0'] = [
    // Call maj_tables() with a list of table SQL names to sync schema
    ['maj_tables', ['spip_monplugin', 'spip_monplugin_liens']],

    // Raw SQL operations
    ['sql_alter', "TABLE spip_monplugin ADD col TEXT DEFAULT '' NOT NULL"],
    ['sql_alter', 'TABLE spip_monplugin ADD INDEX col (col)'],
    ['sql_alter', 'TABLE spip_monplugin DROP INDEX ancien_index'],

    // Data migration
    ['sql_updateq', 'spip_monplugin', ['nouveau_champ' => 'valeur_defaut'], '1=1'],
    ['sql_delete', 'spip_monplugin_archive', ''],

    // Drop an old table
    ['sql_drop_table', 'spip_monplugin_obsolete'],

    // Set a meta
    ['ecrire_meta', 'monplugin_config_cle', 'valeur_defaut'],

    // Call an arbitrary function (must exist at migration time)
    ['monplugin_migrer_donnees'],
];
```

Any named function can be used as a step; the first array element is the function name, the rest
are arguments.

---

## maj_tables() — sync schema from descriptor

```php
// ecrire/base/create.php:203
maj_tables($tables = [], $serveur = ''): void
```

Calls `alterer_base()` which compares the declared descriptor (`field`, `key`) against the actual
DB schema and:
- **Adds** missing columns and indexes
- **Does NOT** drop columns or indexes that exist in the DB but not in the descriptor

Use `sql_alter('TABLE ... DROP COLUMN ...')` explicitly in a migration step to remove old columns.

`maj_tables([])` with an empty array syncs ALL declared tables; passing table names limits the scope.
Always prefer passing explicit names in `_administrations.php`.

---

## _vider_tables() — uninstall

Called when the plugin is deactivated. Must:
1. Drop all plugin-created tables
2. Clear all plugin-created metas (configuration, version marker)

```php
// plugins-dist/spip/mots/mots_administrations.php:97
function mots_vider_tables($nom_meta_base_version) {
    sql_drop_table('spip_mots');
    sql_drop_table('spip_groupes_mots');
    sql_drop_table('spip_mots_liens');

    effacer_meta('articles_mots');
    effacer_meta('config_precise_groupes');
    effacer_meta($nom_meta_base_version);  // remove the version marker last
}
```

Always call `effacer_meta($nom_meta_base_version)` last — removing the version marker is what tells
SPIP the plugin is cleanly uninstalled.

---

## When to bump schema

Bump `schema=` in `paquet.xml` whenever `_upgrade()` has new steps to run:

| Change | Bump required? |
|---|---|
| New column added to `field` in descriptor | **Yes** — add `maj_tables` step + bump |
| New index added to `key` in descriptor | **Yes** — add `sql_alter ADD INDEX` step + bump |
| Column removed from descriptor | **Yes** — add `sql_alter DROP COLUMN` step + bump |
| New table declared | **Yes** — add `maj_tables` step + bump |
| Table dropped on uninstall only | No bump needed for existing installs |
| Configuration meta added | Optional — but use `ecrire_meta` step if upgrade must set defaults |
| PHP-only change (no DB) | No bump needed |

The `schema` version in `paquet.xml` and the highest version key in `$maj` should always match.

---

## Invariants

- `maj_plugin` is idempotent: re-running it (after a timeout redirect) skips already-applied steps
- `maj_tables` only adds; it never drops
- Steps for version `x.y.z` only run if the stored meta is below `x.y.z`
- `'create'` key only runs on **first install** (no existing meta); if the meta already exists,
  `create` is ignored
- `effacer_meta($nom_meta_base_version)` must be the **last** call in `_vider_tables()` — SPIP
  reads this meta to determine installed state

---

## See also

- `references/declarer-table.md` — how `field`/`key` descriptors work (used by `maj_tables`)
- `references/declarer-objet.md` — `principale = 'oui'` makes tables eligible for `maj_tables`
- `references/sql-api.md` — `sql_alter`, `sql_drop_table` for step operations
- `references/paquet-xml.md` — `schema` attribute details

