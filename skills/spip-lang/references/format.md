# Format des fichiers lang SPIP

---

## Structure complète d'un fichier de référence

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
    // A
    'aucun_objet'               => 'Aucun objet trouvé',
    'avis_modification_requise' => 'Modification nécessaire',

    // B
    'bouton_ajouter_objet'      => 'Ajouter un objet',

    // E
    'erreur_acces_refuse'       => 'Accès refusé',
    'erreur_champ_vide'         => 'Le champ @champ@ est obligatoire',

    // I
    'info_1_objet'              => 'Un objet',
    'info_nb_objets'            => '@nb@ objets',

    // T
    'titre_liste_objets'        => 'Liste des objets',
    'titre_modifier_objet'      => 'Modifier l\'objet',
    'titre_nouvel_objet'        => 'Nouvel objet',
];
```

Règles de format :
- En-tête fixe : `// This is a SPIP language file  --  Ceci est un fichier langue de SPIP`
- `return [...]` — pas de variable, pas de `$GLOBALS['i18n']`
- Pas de balises `?>` fermantes en fin de fichier
- Alignement des `=>` optionnel mais recommandé pour la lisibilité
- Toujours `<?php` seul sur la première ligne, pas `<?`

---

## Fichier de traduction (non-référence)

Un fichier de traduction a **exactement la même structure**. Il peut omettre des clés (SPIP
retombe sur la langue de référence pour les clés absentes) mais ne doit pas en ajouter.

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
    // A
    'aucun_objet'          => 'No object found',

    // B
    'bouton_ajouter_objet' => 'Add object',

    // I
    'info_1_objet'         => 'One object',
    'info_nb_objets'       => '@nb@ objects',
];
```

---

## Fichier paquet- (métadonnées du gestionnaire)

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
    'monplugin_description' => 'Description complète affichée dans le gestionnaire de plugins.',
    'monplugin_slogan'      => 'Accroche courte (une ligne)',
];
```

- Fichier : `lang/paquet-monplugin_fr.php`
- Module dans `_T()` : `paquet-monplugin:`
- Deux clés obligatoires : `{prefix}_description` et `{prefix}_slogan`
- Les clés sont préfixées par le nom du plugin (ex. `forum_description`, `forum_slogan`), pas par `paquet_`
- Pas de clés supplémentaires dans ce fichier

---

## Valeurs avec HTML

SPIP échappe les valeurs des placeholders (`@nom@`) par défaut. Pour insérer du HTML dans
une chaîne, il faut que la valeur de la clé elle-même contienne le HTML (jamais via placeholder) :

```php
// OK — HTML dans la valeur de la clé
'texte_aide'  => 'Voir la <a href="@url@">documentation</a>',

// À éviter — HTML dans le placeholder (sera échappé)
// 'texte_aide' => '@lien@'   avec _T(..., ['lien' => '<a href="...">doc</a>'])
```

Pour passer du HTML brut via un placeholder sans échappement :
```php
_T('monplugin:texte_aide', ['url' => $url], ['sanitize' => false]);
```

---

## Apostrophes et caractères spéciaux

```php
// Apostrophe dans une chaîne single-quoted : échapper avec \
'titre_modifier_objet' => 'Modifier l\'objet',

// Ou utiliser double-quotes (moins courant en SPIP)
'titre_modifier_objet' => "Modifier l'objet",
```

Préférer les guillemets simples avec `\'` pour l'apostrophe — c'est la convention des fichiers
lang SPIP core.

---

## Codes langue supportés

SPIP supporte de nombreux codes langue (y compris des variantes régionales). En voici quelques exemples courants :

| Code | Langue |
|---|---|
| `fr` | Français |
| `en` | Anglais |
| `es` | Espagnol |
| `de` | Allemand |
| `it` | Italien |
| `pt` | Portugais |
| `ar` | Arabe |
| `ca` | Catalan |
| `nl` | Néerlandais |

Le code correspond au suffixe du fichier : `monplugin_en.php`, `monplugin_ar.php`, etc.
La liste complète des langues est disponible dans `ecrire/lang/` du core SPIP.

---

## Fallback de langue

SPIP résout une clé dans cet ordre :
1. Langue active (`lang_select()` courant ou `$GLOBALS['spip_lang']`)
2. Langue de référence déclarée dans `<traduire reference="…" />` (définie par le plugin)
3. Retour de la clé brute (sans le préfixe module, underscores → espaces) si `force = true`
4. Chaîne vide si `force = false`

---

## Voir aussi

- `conventions.md` — règles de nommage des clés
- `usage.md` — `_T()`, `_L()`, `lang_select()`, squelettes
- `../spip-plugins/references/i18n.md` — déclaration `<traduire>` dans paquet.xml
