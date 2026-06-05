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
- Checks `$flux['args']['table']`: ✅
- Result: PASS

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

## Overall Baseline Conclusion

All 3 questions answered correctly from training knowledge. The `spip-plugins` skill will provide most value as:
- A precise reference for the full paquet.xml schema (all attributes/child elements)
- A complete pipelines catalog (100+ hooks grouped by category)
- A SQL API quick-reference with all function signatures
