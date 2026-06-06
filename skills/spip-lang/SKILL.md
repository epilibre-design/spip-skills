---
name: spip-lang
description: Use for SPIP translation/i18n work — creating, editing, or auditing language files
  (lang/prefix_XX.php, lang/paquet-prefix_XX.php), naming keys, adding translations,
  using _T() / <:module:key:>, or debugging missing/untranslated strings.
---

# SPIP language files

SPIP stores translatable strings in PHP files under `lang/`. Each plugin declares its
language module in `paquet.xml`; the reference language is defined by `<traduire reference="…">` (typically `fr`).

---

## Quick routing

| Goal | Read |
|---|---|
| Create or edit a `lang/prefix_fr.php` file | `references/format.md` |
| Add a new translation file (`lang/prefix_XX.php`) | `references/format.md` |
| Name keys consistently (prefixes, plural forms, placeholders) | `references/conventions.md` |
| Use `_T()` in PHP or `<:module:key:>` in a squelette | `references/usage.md` |
| Debug missing or untranslated output | `references/usage.md` + `references/conventions.md` |
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
- Keep alphabetical section comments (`// A`, `// B`…)
- Lowercase `snake_case`
- Variable placeholders must use `@nom@`
- Save files as UTF-8 (without BOM)

---

## paquet-module

```php
// lang/paquet-monplugin_fr.php
return [
    'monplugin_description' => 'Description affichée dans le gestionnaire de plugins',
    'monplugin_slogan'      => 'Accroche courte',
];
```

These two keys are **mandatory**. Module in `_T()` calls: `paquet-monplugin:` (not `monplugin:`).

---

## Scope boundary

For plugin architecture, pipelines, or full `paquet.xml` structure, route to `spip-plugins`.
For language files and translation usage, stay in this skill.

---

## Source of truth

- SPIP core lang files: `ecrire/lang/` and `plugins-dist/*/lang/`
- Detailed conventions: `references/conventions.md`
- PHP and squelette usage: `references/usage.md`
- File format details: `references/format.md`
