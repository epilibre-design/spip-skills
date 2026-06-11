# Naming conventions for SPIP lang keys

---

## Standard prefixes

SPIP organizes keys by semantic prefix. Keeping these prefixes ensures consistency
across plugins and helps translators quickly understand what each string is for.

| Prefix | Role | Example |
|---|---|---|
| `titre_` | Section title, object label (header display) | `titre_objets`, `titre_rubrique` |
| `info_` | Informational message, counter, state | `info_1_objet`, `info_nb_objets` |
| `erreur_` | Error message (validation, system) | `erreur_champ_vide`, `erreur_acces` |
| `bouton_` | Button label or action link | `bouton_ajouter`, `bouton_supprimer` |
| `texte_` | Long text block (help, explanation) | `texte_aide_configuration` |
| `avis_` | Warning or contextual notice | `avis_modification_enregistree` |
| `aucun_` | Empty state - no result | `aucun_objet`, `aucun_objet_trouve` |
| `objet_` | Editorial object name (singular/plural) | `objet_type_monobjet` |
| `icone_` | Icon tooltip | `icone_modifier_objet` |
| `item_` | Menu or choice-list entry | `item_statut_publie` |
| `choix_` | `<select>` option | `choix_langue_defaut` |
| `login_` | Authentication-screen strings | `login_connexion_requise` |
| `paquet_` | Reserved for `paquet-prefix_XX.php` file usage | `monplugin_description` |

---

## Singular / plural

SPIP has no automatic pluralization engine. The convention is to provide two keys:

```php
'info_1_objet'    => 'One object',       // exactly 1
'info_nb_objets'  => '@nb@ objects',     // 2 or more (@nb@ = count)
```

PHP usage:
```php
$n = sql_countsel('spip_objets');
echo ($n === 1)
    ? _T('monplugin:info_1_objet')
    : _T('monplugin:info_nb_objets', ['nb' => $n]);
```

---

## `@name@` placeholders

Any dynamic value in a string must use `@name@` placeholder syntax.

```php
// lang/monplugin_en.php
'erreur_champ_vide'         => 'Field @champ@ is required',
'info_nb_elements_max'      => 'Maximum @max@ allowed elements',
'texte_confirmation_suppr'  => 'Delete "@titre@"?',
```

Rules:
- Placeholder name: lowercase, no spaces
- No HTML inside the placeholder itself (`_T()` handles escaping)
- Exactly one `@` on each side (not `@@name@@`)

---

## Alphabetical order and section comments

Keys are sorted alphabetically **globally** (not grouped by prefix), with a letter
comment each time the first letter changes:

```php
return [
    // A
    'aucun_objet'        => 'No object',
    'avis_attention'     => 'Warning',

    // B
    'bouton_ajouter'     => 'Add',
    'bouton_supprimer'   => 'Delete',

    // E
    'erreur_acces'       => 'Access denied',
    'erreur_champ_vide'  => 'This field is required',

    // I
    'info_1_objet'       => 'One object',
    'info_nb_objets'     => '@nb@ objects',
];
```

---

## Shared keys from SPIP core

Some core keys can be reused directly (without redefining them in your plugin):

| Core key | Module | Usage |
|---|---|---|
| `info_obligatoire` | `spip:` | Required field message (CVT validation) |
| `info_modification_enregistree` | `ecrire:` | Successful save message |
| `bouton_enregistrer` | `spip:` | Generic submit button label |
| `bouton_annuler` | `ecrire:` | Cancel button label |
| `annuler` | `spip:` | Cancel label |
| `info_acces_interdit` | `spip:` | Access denied |

Do not redefine a key that already exists in core - use `_T('spip:key')` directly.

---

## Special case: keys without module prefix

A key without module prefix is searched in the `spip` then `ecrire` modules — in any
context (see `inc_traduire_dist()` in `ecrire/inc/traduire.php`). So `_T('info_obligatoire')`
works anywhere. Several modules can also be searched explicitly with
`_T('module1|module2:cle')`. For plugin keys, always use your own module prefix.

---

## Pre-commit checklist

- [ ] All keys use lowercase `snake_case`
- [ ] Keys are globally alphabetically sorted with section-letter comments
- [ ] Placeholders follow the `@nom@` pattern
- [ ] Singular/plural pair (`info_1_X` / `info_nb_Xs`) exists when needed
- [ ] `paquet-prefix_fr.php` contains `prefix_description` and `prefix_slogan`
- [ ] No core key is unnecessarily redefined