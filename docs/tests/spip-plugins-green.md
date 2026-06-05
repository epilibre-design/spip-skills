# spip-plugins Skill — GREEN Verification

## Answers (using skill files)

### Q1: Minimal paquet.xml for SPIP 4.1+ plugin with prefix "acme"

```xml
<paquet prefix="acme"
        categorie="outil"
        version="1.0.0"
        etat="stable"
        compatibilite="[4.1.0;4.99.99]"
        schema="1">
  <nom>Acme Plugin</nom>
  <auteur>Nom Auteur</auteur>
  <licence>GNU/GPL</licence>
  <necessite nom="spip" compatibilite="[4.1.0;]" />
</paquet>
```

### Q2: Run PHP code every time an article is saved

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
    return $flux; // always return $flux
}
```

### Q3: Query all published articles using SPIP SQL API

```php
$rows = sql_allfetsel(
    'id_article, titre, date',
    'spip_articles',
    "statut='publie'",
    '',
    'date DESC',
    ''
);
foreach ($rows as $row) {
    echo $row['titre'];
}
```

Or using `sql_select` + `sql_fetch`:
```php
$res = sql_select('id_article, titre, date', 'spip_articles', "statut='publie'", '', 'date DESC');
while ($row = sql_fetch($res)) {
    echo $row['titre'];
}
```

## Verification

| Expected | Pass? |
|---|---|
| paquet.xml has `compatibilite="[4.1.0;...]"` and all required attributes | ✅ |
| Pipeline declaration: `<pipeline nom="post_edition" inclure="acme_pipelines.php" />` | ✅ |
| Handler: `function acme_post_edition($flux)` checking `$flux['args']['table']` and returning `$flux` | ✅ |
| SQL uses `sql_allfetsel` or `sql_select`+`sql_fetch` (not PDO/mysqli) | ✅ |

## Conclusion

**PASS** — all 4 checks pass. The skill correctly provides all required information from its reference files.
