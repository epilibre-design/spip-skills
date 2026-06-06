# SPIP lang file format

---

## Full structure of a reference file

Example for lang/monplugin_fr.php:

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

Formatting rules:
- Fixed header comment: `// This is a SPIP language file  --  Ceci est un fichier langue de SPIP`
- Use `return [...]` - no variable assignment, no `$GLOBALS['i18n']`
- No closing `?>` tag at end of file
- `=>` alignment is optional but recommended for readability
- Always use `<?php` alone on line 1, never `<?`

---

## Translation file (non-reference language)

A translation file has **exactly the same structure**. It may omit keys (SPIP falls
back to the reference language for missing keys) but must not introduce extra keys.

Example for lang/monplugin_en.php:

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

## paquet- file (plugin manager metadata)

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
    'monplugin_description' => 'Full description shown in the plugin manager.',
    'monplugin_slogan'      => 'Short one-line slogan',
];
```

- File name: `lang/paquet-monplugin_en.php`
- Module name in `_T()`: `paquet-monplugin:`
- Two mandatory keys: `{prefix}_description` and `{prefix}_slogan`
- Keys are prefixed with plugin name (for example `forum_description`, `forum_slogan`), not `paquet_`
- No extra keys in this file

---

## Values containing HTML

SPIP escapes placeholder values (`@nom@`) by default. To include HTML, put the HTML
in the lang string itself (not inside placeholder content):

```php
// OK - HTML in the key value
'texte_aide'  => 'See the <a href="@url@">documentation</a>',

// Avoid - HTML in placeholder content (will be escaped)
// 'texte_aide' => '@lien@' with _T(..., ['lien' => '<a href="...">doc</a>'])
```

To pass raw HTML via placeholders without escaping:
```php
_T('monplugin:texte_aide', ['url' => $url], ['sanitize' => false]);
```

---

## Apostrophes and special characters

```php
// Apostrophe inside single-quoted string: escape with \
'titre_modifier_objet' => 'Edit object\'s settings',

// Or use double quotes (less common in SPIP lang files)
'titre_modifier_objet' => "Edit object's settings",
```

Prefer single quotes plus escaped apostrophe (`\'`) - this is the convention used in
SPIP core lang files.

---

## Supported language codes

SPIP supports many language codes (including regional variants). Common examples:

| Code | Language |
|---|---|
| `fr` | French |
| `en` | English |
| `es` | Spanish |
| `de` | German |
| `it` | Italian |
| `pt` | Portuguese |
| `ar` | Arabic |
| `ca` | Catalan |
| `nl` | Dutch |

The code is the file suffix: `monplugin_en.php`, `monplugin_ar.php`, etc.
Full list is available in SPIP core under `ecrire/lang/`.

---

## Language fallback

SPIP resolves a key in this order:
1. Active language (current `lang_select()` or `$GLOBALS['spip_lang']`)
2. Reference language declared by `<traduire reference="..." />` in plugin manifest
3. Raw key fallback (module removed, underscores converted to spaces) if `force = true`
4. Empty string if `force = false`

---

## See also

- `conventions.md` - key naming rules
- `usage.md` - `_T()`, `_L()`, `lang_select()`, squelettes
- `../spip-plugins/references/i18n.md` - `<traduire>` declaration in paquet.xml