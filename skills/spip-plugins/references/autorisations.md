# Authorisation System

SPIP uses a function-dispatch authorisation model. The `autoriser()` API resolves the correct
`autoriser_*` function at runtime using a fixed lookup chain, then returns a boolean.

---

## Table of contents

1. [Calling autoriser()](#calling-autoriser)
2. [Lookup chain](#lookup-chain)
3. [Writing autoriser_ functions](#writing-autoriser_-functions)
4. [Built-in statut values](#built-in-statut-values)
5. [Special faire values](#special-faire-values)
6. [Declaring in paquet.xml](#declaring-in-paquetxml)
7. [autoriser() in squelettes](#autoriser-in-squelettes)
8. [Temporary exceptions](#temporary-exceptions)
9. [Invariants](#invariants)
10. [See also](#see-also)

---

## Calling autoriser()

```php
// ecrire/inc/autoriser.php:107
autoriser(
    string $faire,        // action: 'voir', 'modifier', 'supprimer', 'publier', 'configurer', …
    string $type = '',    // object type: 'article', 'rubrique', 'forum', or '' for global
    int|string $id = null,// object identifier, or null when not applicable
    int|array $qui = null,// who: null = current visitor session, int = id_auteur, array = full auteur row
    array $opt = []       // extra context passed to the autoriser_ function
): bool
```

```php
// Typical usage
include_spip('inc/autoriser');

// "Can the current visitor modify article 42?"
if (!autoriser('modifier', 'article', 42)) {
    return;
}

// "Can the current visitor access the private space?"
if (!autoriser('ecrire')) {
    redirige_par_entete(generer_url_public('login'));
}

// "Is the current visitor a webmaster?"
if (autoriser('webmestre')) {
    // show admin-only block
}

// "Can a specific auteur configure the plugin?"
if (autoriser('configurer', '_monplugin', null, $id_auteur)) {
    // ...
}
```

The leading `_` in `'_monplugin'` tells SPIP that this is a compound name, not an object type. SPIP
will look for `autoriser_monplugin_configurer_dist` (not `autoriser_monplugin_configurer_dist` after
type normalisation — see lookup chain).

---

## Lookup chain

`autoriser_dist()` tries functions in this order and uses the first one that exists:

```
When $type is non-empty:
  1. autoriser_{type}_{faire}
  2. autoriser_{type}_{faire}_dist
  3. autoriser_{type}
  4. autoriser_{type}_dist
  5. autoriser_{faire}
  6. autoriser_{faire}_dist
  7. autoriser_defaut
  8. autoriser_defaut_dist

When $type is empty (global checks):
  1. autoriser_{faire}
  2. autoriser_{faire}_dist
  3. autoriser_defaut
  4. autoriser_defaut_dist
```

**`_dist` suffix convention:** plugins define `autoriser_X_Y_dist()`. The version without `_dist` is
a site-level override (placed in `mes_fonctions.php` or a theme). Never define the non-`_dist`
version in a plugin — it would prevent site-level overrides.

### Default fallback

```php
// ecrire/inc/autoriser.php:313
function autoriser_defaut_dist($faire, $type, $id, $qui, $opt): bool {
    return $qui['statut'] === '0minirezo' and !$qui['restreint'];
}
```

The default requires a full administrator (`0minirezo`) who is not a restricted admin.

---

## Writing autoriser_ functions

### Minimal example

```php
// monplugin/monplugin_autoriser.php
if (!defined('_ECRIRE_INC_VERSION')) { return; }

// Pipeline hook — must exist (can be empty)
function monplugin_autoriser() {}

// "Can $qui see a monobjet?"
function autoriser_monobjet_voir_dist($faire, $type, $id, $qui, $opt): bool {
    return in_array($qui['statut'], ['0minirezo', '1comite']);
}

// "Can $qui modify a monobjet?"
function autoriser_monobjet_modifier_dist($faire, $type, $id, $qui, $opt): bool {
    if ($qui['statut'] === '0minirezo') {
        return true;
    }
    // comité members can only edit their own objects
    return $qui['statut'] === '1comite'
        && sql_getfetsel('id_auteur', 'spip_monobjets', 'id_monobjet=' . intval($id)) == $qui['id_auteur'];
}
```

### Delegating to a parent check

```php
// "Can $qui modify a forum? If the forum is attached to an object, check that object's rights"
// plugins-dist/spip/forum/forum_autoriser.php:89
function autoriser_forum_modifier_dist($faire, $type, $id, $qui, $opt): bool {
    // ... read the forum's attached objet, then:
    return autoriser('modifier', $t['objet'], $t['id_objet'], $qui, $opt);
}
```

### Plugin configuration gate (`configurer_monplugin`)

A common pattern for protecting a plugin's configuration page:

```php
function autoriser_configurermonplugin_dist($faire, $type, $id, $qui, $opt): bool {
    return autoriser('configurer', '', $id, $qui, $opt);
    // delegates to autoriser_configurer_dist — requires full admin
}
```

In `exec/` or `formulaires/`, guard with:
```php
if (!autoriser('configurer', '_monplugin')) { ... }
```

---

## Built-in statut values

| `$qui['statut']` | Meaning |
|---|---|
| `'0minirezo'` | Full administrator |
| `'1comite'` | Rédacteur (editor) |
| `'6forum'` | Visitor with forum access |
| `'5poubelle'` | Banned |
| `''` | Not logged in |

`$qui['restreint']`: array of rubrique ids the admin is restricted to; empty = unrestricted.
`$qui['webmestre']`: `'oui'` or `'non'` — webmaster status (strongest admin level).

---

## Special faire values

| `faire` | Resolved by | Meaning |
|---|---|---|
| `'ecrire'` | `autoriser_ecrire_dist` | Access to the private space |
| `'webmestre'` | `autoriser_webmestre_dist` | Webmaster-level (requires `webmestre=oui`) |
| `'configurer'` | `autoriser_configurer_dist` | Site configuration — full admin, not restricted |
| `'creer'` | `autoriser_creer_dist` | Create any content — full or comité |
| `'voir'` | falls to `autoriser_defaut_dist` unless overridden | Object visibility |
| `'modifier'` | object-specific or `autoriser_defaut_dist` | Edit an object |
| `'supprimer'` | object-specific or `autoriser_defaut_dist` | Delete an object |
| `'publier'` | object-specific or `autoriser_defaut_dist` | Change statut to publie |
| `'publierdans'` | `autoriser_publierdans_dist` | Publish in a specific rubrique |

---

## Declaring in paquet.xml

The `autoriser` pipeline must be declared so SPIP loads the authorisation file:

```xml
<!-- paquet.xml -->
<pipeline nom="autoriser" inclure="monplugin_autoriser.php" />
```

The pipeline handler itself is empty:
```php
function monplugin_autoriser() {}
```

SPIP fires the `autoriser` pipeline the first time `autoriser()` is called in a request, which
causes `monplugin_autoriser.php` to be included and all its `autoriser_*_dist` functions to become
available.

---

## autoriser() in squelettes

In squelettes, use the `#AUTORISER` balise:

```html
[(#AUTORISER{modifier, article, #ID_ARTICLE}|oui)
    <a href="#URL_ECRIRE{articles, id_article=#ID_ARTICLE}">Modifier</a>
]

[(#AUTORISER{webmestre}|oui)
    <!-- webmaster-only block -->
]
```

`#AUTORISER` calls `autoriser()` with the current visitor session. It returns `true` or `false`;
apply `|oui` or `|non` to use it as a conditional.

---

## Temporary exceptions

To grant a temporary permission override within a PHP execution (e.g. during a batch operation):

```php
// ecrire/inc/autoriser.php:241
autoriser_exception('supprimer', 'forum', $id_forum, true);   // grant
// ... do the operation ...
autoriser_exception('supprimer', 'forum', $id_forum, false);  // revoke

// Wildcard: grant for all ids
autoriser_exception('supprimer', 'forum', '*', true);
```

Exceptions bypass the normal lookup chain. Use only for internal operations where you have already
verified the right at a higher level.

---

## Invariants

- `autoriser()` always returns a `bool`; function signatures returning non-bool produce a
  deprecation warning in SPIP 4.4 and will be fatal in future versions
- Plugin functions must use the `_dist` suffix — without it, the function would block site-level
  overrides
- The `$qui` array always contains at least `['statut' => '', 'id_auteur' => 0, 'webmestre' => 'non']`
  even for anonymous visitors
- `include_spip('inc/autoriser')` must be called before any `autoriser()` call in action files and
  exec files; it is included automatically in formulaire CVT contexts

---

## See also

- `references/actions.md` — checking `autoriser()` inside a secured action
- `references/pipelines.md` → `autoriser` pipeline declaration
- `references/arborescence.md` → `monplugin_autoriser.php` file
