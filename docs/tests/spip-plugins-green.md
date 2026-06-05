# spip-plugins Skill — GREEN Verification

Date: 2026-06-05

## Answers (using skill files)

### Q1: Minimal paquet.xml for SPIP 4.1+ plugin with prefix "acme"

From `references/paquet-xml.md` — required attributes: `prefix`, `categorie`, `version`, `etat`, `compatibilite`; required children: `<nom>`, `<auteur>`, `<licence>`.

```xml
<paquet prefix="acme"
        categorie="outil"
        version="1.0.0"
        etat="stable"
        compatibilite="[4.1.0;4.*]">
  <nom>Acme Plugin</nom>
  <auteur>Nom Auteur</auteur>
  <licence>GNU/GPL</licence>
</paquet>
```

---

### Q2: Run PHP code every time an article is saved

From `references/pipelines.md` — `post_edition` args structure:

**paquet.xml declaration:**
```xml
<pipeline nom="post_edition" inclure="acme_pipelines.php" />
```

**PHP handler in `acme_pipelines.php`:**
```php
function acme_post_edition($flux) {
    if ($flux['args']['objet'] === 'article') {
        $id = $flux['args']['id_objet'];
        // act on article #$id after save
    }
    return $flux; // always return $flux — returning null breaks the chain
}
```

Key: `$flux['args']['objet']` is the canonical key (e.g. `'article'`). `$flux['args']['table']` also exists but is marked "kept for BC" — prefer `objet`.

---

### Q3: Query all published articles using SPIP SQL API

From `references/sql-api.md`:

```php
$rows = sql_allfetsel(
    'id_article, titre, date',
    'spip_articles',
    "statut='publie'",
    '',
    'date DESC'
);
foreach ($rows as $row) {
    echo $row['titre'];
}
```

Or streaming with `sql_select` + `sql_fetch`:
```php
$res = sql_select('id_article, titre', 'spip_articles', "statut='publie'", '', 'date DESC');
while ($row = sql_fetch($res)) {
    echo $row['titre'];
}
sql_free($res);
```

---

### Q4: Add install/upgrade support to a plugin with prefix "acme" that needs a DB table

From `references/cycle-de-vie.md`:

**In `paquet.xml` — add the `schema` attribute** (no `<install>` tag; it does not exist):

```xml
<paquet prefix="acme" version="1.0.0" schema="1.0.0" ...>
  <nom>Acme Plugin</nom>
  ...
</paquet>
```

**Create `acme_administrations.php`** at the plugin root (name derived by convention):

```php
if (!defined('_ECRIRE_INC_VERSION')) { return; }

function acme_upgrade($nom_meta_base_version, $version_cible) {
    $maj = [];

    $maj['create'] = [
        ['maj_tables', ['spip_acme']],
    ];

    include_spip('base/upgrade');
    maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

function acme_vider_tables($nom_meta_base_version) {
    sql_drop_table('spip_acme');
    effacer_meta($nom_meta_base_version);
}
```

Rules:
- File name: `{prefix}_administrations.php` — loaded by naming convention, not declared in paquet.xml
- `schema` attribute triggers upgrade when its value differs from the stored meta
- Two mandatory functions: `{prefix}_upgrade($nom_meta_base_version, $version_cible)` and `{prefix}_vider_tables($nom_meta_base_version)`

---

## Verification

| Expected | Pass? |
|---|---|
| paquet.xml has `compatibilite="[4.1.0;...]"` and all required attributes | ✅ |
| Pipeline handler uses `$flux['args']['objet']` (canonical) not deprecated `table` | ✅ |
| SQL uses `sql_allfetsel` or `sql_select`+`sql_fetch` (not PDO/mysqli) | ✅ |
| No `<install>` tag; uses `schema` attribute + `{prefix}_administrations.php` convention | ✅ |
| Function names: `{prefix}_upgrade()` and `{prefix}_vider_tables()` | ✅ |

## Conclusion

**PASS — 5/5 checks.** The skill correctly provides all required information from its reference files.

Corrections over the original baseline:
- Q2: `$flux['args']['objet']` replaces deprecated `$flux['args']['table']`
- Q4: replaces the hallucinated `securiser_acces_low_sec()` question with a real, verifiable convention
