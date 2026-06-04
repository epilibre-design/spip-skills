---
name: spip-lang
description: Use when creating, editing, or auditing SPIP language files (lang/prefix_XX.php,
  lang/paquet-prefix_XX.php), naming keys, adding translations, or using _T() / <:module:key:>
  in PHP and squelettes.
---

# SPIP language files

SPIP stores translatable strings in PHP files under `lang/`. Each plugin declares its
language module in `paquet.xml`; by convention the reference language is `fr` (configurable via `<traduire reference="…">`).

---

## Quick routing

| Goal | Read |
|---|---|
| Create or edit a `lang/prefix_fr.php` file | `references/format.md` |
| Name keys consistently (prefixes, plural forms, placeholders) | `references/conventions.md` |
| Use `_T()` in PHP or `<:module:key:>` in a squelette | `references/usage.md` |
| Declare the module in `paquet.xml` | `../spip-plugins/references/i18n.md` |
| Send translated output in a different language (`lang_select`) | `references/usage.md` |

---

## File overview

```
monplugin/
└── lang/
    ├── monplugin_fr.php          ← main strings (reference)
    ├── monplugin_en.php          ← translation (same keys)
    └── paquet-monplugin_fr.php   ← plugin manager metadata
```

`paquet.xml` declaration:
```xml
<traduire module="monplugin" reference="fr" />
```

---

## Minimal file skeleton

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
    // A
    'aucun_objet'          => 'Aucun objet trouvé',

    // B
    'bouton_ajouter'       => 'Ajouter',

    // E
    'erreur_champ_vide'    => 'Le champ @champ@ est obligatoire',

    // I
    'info_1_objet'         => 'Un objet',
    'info_nb_objets'       => '@nb@ objets',

    // T
    'titre_objets'         => 'Mes objets',
];
```

Key rules:
- Lowercase `snake_case`
- Alphabetical section comments (`// A`, `// B`…)
- Variable placeholders: `@nom@`

---

## paquet- module

```php
// lang/paquet-monplugin_fr.php
return [
    'monplugin_description' => 'Description affichée dans le gestionnaire de plugins',
    'monplugin_slogan'      => 'Accroche courte',
];
```

These two keys are mandatory. Module in `_T()` calls: `paquet-monplugin:`.

---

## Source of truth

- SPIP core lang files: `ecrire/lang/` and `plugins-dist/*/lang/`
- Detailed conventions: `references/conventions.md`
- PHP and squelette usage: `references/usage.md`
- File format details: `references/format.md`
