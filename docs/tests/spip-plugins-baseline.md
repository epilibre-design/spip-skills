# spip-plugins Skill — RED Baseline Test

Answers given from training knowledge only (no skill loaded).

---

## Q1: Minimal valid paquet.xml for SPIP 4.1+ plugin with prefix "acme"

```xml
<paquet prefix="acme"
        categorie="outil"
        version="1.0.0"
        etat="stable"
        compatibilite="[4.1.0;4.99.99]">
  <nom>Acme Plugin</nom>
  <auteur>Author Name</auteur>
  <licence>GNU/GPL</licence>
  <necessite nom="spip" compatibilite="[4.1.0;]" />
</paquet>
```

**Self-assessment:**
- `compatibilite` format: ✅ uses `[4.1.0;4.99.99]`
- Required attributes present: ✅ prefix, categorie, version, etat, compatibilite all present
- Result: PASS

---

## Q2: Run PHP code every time an article is saved

**paquet.xml declaration:**
```xml
<pipeline nom="post_edition" inclure="acme_pipelines.php" />
```

**PHP handler in `acme_pipelines.php`:**
```php
function acme_post_edition($flux) {
    if ($flux['args']['table'] === 'spip_articles') {
        $id = $flux['args']['id_objet'];
        // act on article #$id after save
    }
    return $flux;
}
```

**Self-assessment:**
- Pipeline declaration correct (`<pipeline nom="post_edition" inclure="..."/>`): ✅
- Handler name pattern `acme_post_edition($flux)`: ✅
- Returns `$flux`: ✅
- Checks `$flux['args']['table']`: ⚠️ works but deprecated — canonical key is `$flux['args']['objet']`
- Result: PARTIAL

---

## Q3: Query all published articles using SPIP's SQL API

```php
// Using sql_allfetsel (fetch all at once)
$rows = sql_allfetsel(
    'id_article, titre, date',
    'spip_articles',
    "statut='publie'",
    '',
    'date DESC',
    '10'
);
foreach ($rows as $row) {
    echo $row['titre'];
}

// Or using sql_select + sql_fetch (streaming)
$res = sql_select('id_article, titre', 'spip_articles', "statut='publie'", '', 'date DESC');
while ($row = sql_fetch($res)) {
    echo $row['titre'];
}
```

**Self-assessment:**
- Uses `sql_allfetsel` and `sql_select`+`sql_fetch`: ✅ (not raw PDO/mysqli)
- Result: PASS

## Q4: Add install/upgrade support to a plugin with prefix "acme" that needs a DB table

```xml
<!-- paquet.xml — guessed answer -->
<paquet prefix="acme" version="1.0.0" ...>
  <install inclure="acme_install.php" />
</paquet>
```

```php
// acme_install.php — guessed function names
function acme_install() {
    // create table on activation
}

function acme_uninstall() {
    // drop table on deactivation
}
```

**Self-assessment:**
- Uses `<install>` tag in paquet.xml: ❌ (tag does not exist in the paquet.xml DTD)
- File name convention `{prefix}_administrations.php`: ❌ (guessed `acme_install.php`)
- Function names `{prefix}_upgrade($nom_meta_base_version, $version_cible)`: ❌ (guessed `acme_install()`)
- Function name `{prefix}_vider_tables($nom_meta_base_version)`: ❌ (guessed `acme_uninstall()`)
- `schema` attribute triggers the upgrade mechanism: ❌ (unaware)
- Result: FAIL

---

## Overall Baseline Conclusion

| Q | Result | Key gap |
|---|---|---|
| 1 | PASS | All required attributes present |
| 2 | PARTIAL | `$flux['args']['table']` works but is deprecated; canonical key is `objet` |
| 3 | PASS | Correct SQL API usage |
| 4 | FAIL | `<install>` tag doesn't exist; wrong file name and function name conventions |

The `spip-plugins` skill adds most value on:
- Canonical `$flux['args']['objet']` key (vs deprecated `table`)
- `schema` attribute + `{prefix}_administrations.php` naming convention
- `{prefix}_upgrade()` and `{prefix}_vider_tables()` exact signatures
