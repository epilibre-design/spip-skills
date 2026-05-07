# Multilinguisme — id_trad, lang, and translation groups

SPIP's multilinguisme support lets an objet éditorial exist in multiple languages, each as a
separate row linked together by the `id_trad` column. This is distinct from the plugin i18n
system (see `references/i18n.md`), which handles interface strings.

Sources: `ecrire/base/objets.php`, `ecrire/action/referencer_traduction.php`,
`ecrire/public/criteres.php`

---

## The two columns

Objects that support multilinguisme carry two extra columns:

| Column | SQL type | Meaning |
|---|---|---|
| `lang` | `VARCHAR(10) DEFAULT ''` | Language code of this row (e.g. `'fr'`, `'en'`, `''` = site default) |
| `id_trad` | `bigint(21) DEFAULT '0'` | Translation group reference: `0` = not part of a group, non-zero = group id |

**Translation group id:** by convention, all translations in a group share the same `id_trad`
value, which is typically the `id_<objet>` of the first object in the group. This is not
enforced structurally — it is maintained by `action_referencer_traduction_dist()`.

Source: `ecrire/base/objets.php:148` (articles), `ecrire/base/objets.php:349` (rubriques)

---

## Adding multilinguisme to a custom objet éditorial

Declare `lang` and `id_trad` in the `field` array, add their keys, and set the `titre` accessor
to include `lang`:

```php
// base/myplugin.php
$tables['spip_mon_objets'] = [
    'type'  => 'monobjet',
    'titre' => 'titre, lang',   // 'lang' must be in the SQL expression for traductions to work

    'field' => [
        'id_mon_objet' => 'bigint(21) NOT NULL',
        'titre'        => "text DEFAULT '' NOT NULL",
        'lang'         => "VARCHAR(10) DEFAULT '' NOT NULL",
        'id_trad'      => "bigint(21) DEFAULT '0' NOT NULL",
        // ...
        'maj'          => 'TIMESTAMP',
    ],
    'key' => [
        'PRIMARY KEY' => 'id_mon_objet',
        'KEY id_trad' => 'id_trad',
        'KEY lang'    => 'lang',
    ],
    'champs_editables' => ['titre', 'lang', 'id_trad'],
];
```

The `prive/objets/editer/traductions.html` supplement is then usable to link translations
from the edit form.

---

## Translation group mechanics

### Linking two objects as translations

```php
// ecrire/action/referencer_traduction.php:41
action_referencer_traduction_dist('monobjet', $id_objet, $id_trad);
```

| `$id_trad` value | Effect |
|---|---|
| `0` | Removes `$id_objet` from its translation group; resets `id_trad = 0` |
| Non-zero (different from `$id_objet`) | Adds `$id_objet` to the group of `$id_trad` |

**Group assignment rules (from source):**

```php
// ecrire/action/referencer_traduction.php:60-72
if ($id_lier == 0) {
    // $id_trad has no group yet — create one; both objects now share id_trad = $id_trad
    sql_updateq($table, ['id_trad' => $id_trad], "id IN ($id_trad, $id_objet)");
} elseif ($id_lier == $id_objet) {
    // Re-keying: change the reference for the whole existing group
    sql_updateq($table, ['id_trad' => $id_trad], "id_trad = $id_lier");
} else {
    // Normal join: add $id_objet into the existing group
    sql_updateq($table, ['id_trad' => $id_lier], "id = $id_objet");
}
```

### Removing an object from a group

```php
action_referencer_traduction_dist('monobjet', $id_objet, 0);
// If the group is now a singleton, its remaining member also gets id_trad = 0
```

---

## Critères in boucles

### Filter by language

```html
<BOUCLE_articles(ARTICLES){lang=#LANG}>...</BOUCLE_articles>
```

`{lang}` compares the row's `lang` column against the critère value. `#LANG` resolves to the
current page language.

### Filter by translation group

```html
[(#REM) all translations of a given article ]
<BOUCLE_trad(ARTICLES){id_trad=#ID_ARTICLE}{!id_article=#ID_ARTICLE}>...</BOUCLE_trad>
```

The `{id_trad}` critère matches rows where `id_trad = <value>` OR where the row's own id
equals `<value>` (i.e. it finds the group anchor too).

### The `{traduction}` critère

```html
[(#REM) articles that are translations of the current article ]
<BOUCLE_trad(ARTICLES){traduction}>...</BOUCLE_trad>
```

The `{traduction}` critère (source: `ecrire/public/criteres.php:399`) builds the condition:
`(id_trad > 0 AND id_trad = <id_trad of the parent boucle>) OR id_article = <id_trad of parent>`.

---

## Lang resolution at page level

SPIP sets `$GLOBALS['spip_lang']` at the start of each request. Public squelettes can declare
which languages they support via `{lang_select}`:

```html
<BOUCLE_art(ARTICLES){id_article}{lang_select}>
```

`{lang_select}` changes the global lang to the object's `lang` value for the duration of
that boucle, restoring it afterwards.

---

## Working with lang in PHP

```php
// Read the current lang
$lang = $GLOBALS['spip_lang'];  // or lang_select() for resolution

// Set lang for a block of code
lang_select('en');
// ... work in English ...
lang_select();  // restore previous
```

---

## Polyglots and multi-site

The **Polyglot** plugin (not included in SPIP core) extends multilinguisme to route requests
by language prefix (`/en/`, `/fr/`) and to synchronise `lang` with URL routing. If Polyglot
is installed:

- `$GLOBALS['spip_lang']` is driven by the URL prefix, not by the visitor's browser
- The `lang_select` mechanism works transparently

Detecting Polyglot:

```php
if (defined('_POLYGLOTTE')) {
    // Polyglot is active
}
```

---

## Invariants

- A row with `id_trad = 0` is not part of any translation group
- The `id_trad` value is an object id within the same table — it is not a foreign key to a
  separate "translation group" table
- If `lang` is empty (`''`), the object is considered to be in the site's default language
- `objet_modifier()` writes `lang` and `id_trad` only if they are in `champs_editables`
- The `titre` SQL expression must include `lang` for translation UI to work correctly
  (e.g. `'titre, lang'` not `'titre'`)

---

## See also

- `references/i18n.md` — plugin interface strings (`_T()`, `lang/` files)
- `references/declarer-objet.md` — `titre` accessor, `champs_editables`
- `../../spip-formulaires/references/cvt-formulaires.md` — `formulaires_editer_objet_charger()` sets `lang` automatically
- `references/prive-objets.md` — `editer/traductions.html` supplement
