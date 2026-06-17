# Using i18n strings in PHP and squelettes

---

## _T() - fetch a translated string (PHP)

```php
// Signature (ecrire/inc/utils.php)
_T(string $cle, array $args = [], array $options = []): string
```

`$cle` is `'module:key'` - module prefix routes lookup to the right lang file.

### Common cases

```php
// Simple lookup
echo _T('monplugin:titre_liste_objets');

// With placeholder substitution
echo _T('monplugin:erreur_champ_vide', ['champ' => 'title']);
echo _T('monplugin:info_nb_objets', ['nb' => $count]);

// Optional key - return '' if missing (no raw-key fallback)
$label = _T('monplugin:libelle_optionnel', [], ['force' => false]);

// Disable placeholder HTML escaping (raw value)
echo _T('monplugin:texte_avec_lien', ['url' => $url], ['sanitize' => false]);
```

### Available options

| Option | Default | Effect |
|---|---|---|
| `force` | `true` | `true` -> return raw key if missing; `false` -> return `''` |
| `sanitize` | `true` | `true` -> escape HTML in `$args` values |

---

## _L() - interpolate placeholders in an existing string

Low-level helper - use when you already have the source string and only want to
replace `@name@` placeholders:

```php
$texte = _L('Welcome @firstname@ @lastname@!', ['firstname' => $p, 'lastname' => $n]);
```

`_T()` calls `_L()` internally. Prefer `_T()` in most cases.

---

## lang_select() - switch active language

Useful to generate translated content in a different language than current context
(for example, sending email in recipient language):

```php
// Push a new language
lang_select('it');
$sujet = _T('monplugin:email_sujet');   // fetched in Italian
$corps = _T('monplugin:email_corps');

// Restore previous language - null or no argument
lang_select(null);  // same as lang_select()
```

`lang_select($lang)` pushes current language and switches to `$lang`.
`lang_select(null)` (or `lang_select()` without args) pops and restores previous language.

**Common pitfall**: `lang_select($lang)` returns the language you passed (`$lang`),
not the previous language. Do not store return value for restoration:

```php
// Wrong - returns 'en', not previous language; second call does not restore
$save = lang_select('en');
lang_select($save);  // calls lang_select('en') again

// Correct
lang_select('en');
// ... _T() calls ...
lang_select(null);  // or lang_select() without args
```

**In squelettes**, `lang_select` can be used as a filter to switch language for a block,
then restored with `#EVAL{lang_select()}`:

```html
[(#VALEUR{lang}|lang_select|vide)]
... translated content in that language ...
[(#EVAL{lang_select()}|vide)]
```

---

## Singular / plural

**In PHP:**

```php
$n = sql_countsel('spip_monsobjets');
echo ($n === 1)
    ? _T('monplugin:info_1_monobjet')
    : _T('monplugin:info_nb_monobjets', ['nb' => $n]);
```

**In squelettes — `singulier_ou_pluriel` filter (preferred):**

```html
[(#GRAND_TOTAL|singulier_ou_pluriel{monplugin:info_1_monobjet,monplugin:info_nb_monobjets})]
```

`singulier_ou_pluriel($nb, $key_singular, $key_plural)` calls `_T()` on the right key and
substitutes `@nb@` automatically. **Returns empty string when `$nb == 0`** — use `|sinon`
to handle the zero case:

```html
[(#GRAND_TOTAL|singulier_ou_pluriel{info_1_article,info_nb_articles}|sinon{<:aucun_article:>})]
```

Keys without module prefix resolve against `spip`/`ecrire` modules (for core keys).

---

## Usage in SPIP squelettes

### Short syntax (recommended)

```html
<:monplugin:titre_liste_objets:>
```

Core SPIP keys (from `ecrire/lang/`) can be used **without module prefix** — SPIP searches
`spip` then `ecrire` modules automatically:

```html
<:auteur:>
<:date:>
<:annuler:>
```

### With SPIP balise placeholders

Placeholder values can be any SPIP balise — `#ENV{}`, `#GET{}`, `#CONFIG{}`, or a direct balise:

```html
<:form_forum_bonjour{nom=#ENV{nom}}:>
<:nouvelle_version_spip{version=#CONFIG{derniere_maj_notifiee}}:>
<:info_copyright_doc{spipnet=#GET{home_server},spipnet_affiche=#GET{home_server}}:>
```

### With filters

Append SPIP filters directly after the key (before the closing `:`). Multiple filters can be chained:

```html
<:info_titre|label_nettoyer:>
<:lien_trier_statut|attribut_html:>
<:icone_modifier_article|attribut_html:>
<:info_copyright_doc{spipnet_affiche=#GET{home_server}}|textebrut|attribut_html:>
```

### Via `|_T` filter

```html
[(#VAL{monplugin:titre_liste_objets}|_T)]
```

With key built dynamically from object info:
```html
[(#OBJET|objet_info{info_aucun_objet}|_T)]
```

With dynamic environment:
```html
[(#MODULE|concat{:}|concat{#CLE}|_T)]
```

### Comparison of forms

| Form | When to use |
|---|---|
| `<:module:key:>` | Standard static case, most readable |
| `<:key:>` | Core SPIP key (no module prefix needed) |
| `<:module:key{param=#BALISE}:>` | Placeholder from a SPIP balise or `#ENV{}` / `#GET{}` |
| `<:module:key\|filter:>` | Translated string needs filtering (escaping, label cleanup) |
| `[(#VAL{module:key}\|_T)]` | Key built dynamically from SPIP balises |

---

## UI label filters

Two filters prepare translated strings for inline label display (table headers, form labels):

| Filter | Effect |
|---|---|
| `label_nettoyer` | Removes trailing `:` or space; uppercases first letter |
| `label_ponctuer` | Same as `label_nettoyer` + appends ` :` (language-aware punctuation) |

```html
<:info_titre|label_nettoyer:>         ← table column header
<:info_maximum|label_ponctuer:>       ← form label with trailing colon
```

Use `label_nettoyer` when you control the surrounding punctuation (e.g., inside a `<th>`);
use `label_ponctuer` when the label must end with a colon regardless of source string.

---

## Common patterns (PHP)

### CVT validation - field error

```php
// verifier()
$erreurs['titre'] = _T('monplugin:erreur_champ_vide', ['champ' => 'title']);

// Reuse core key (no plugin redefinition needed)
$erreurs['titre'] = _T('info_obligatoire');  // no prefix -> searched in 'spip' then 'ecrire' modules
```

### CVT success - global message

```php
// traiter()
return ['message_ok' => _T('ecrire:info_modification_enregistree')];
```

### Email notification

```php
lang_select($destinataire_lang);
$sujet = _T('monplugin:email_notification_sujet');
$corps = _T('monplugin:email_notification_corps', ['titre' => $objet['titre']]);
lang_select(null);

$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
$envoyer_mail($destinataire_email, $sujet, $corps);
```

### Conditional key (optional label)

```php
$label = _T('monplugin:libelle_contexte_special', [], ['force' => false])
    ?: _T('monplugin:libelle_defaut');
```

---

## Useful core modules

| Module | File | Example keys |
|---|---|---|
| `spip:` | `ecrire/lang/spip_fr.php` | `info_obligatoire`, `bouton_enregistrer`, `info_acces_interdit` |
| `ecrire:` | `ecrire/lang/ecrire_fr.php` | `info_modification_enregistree`, `bouton_annuler` |
| `public:` | `ecrire/lang/public_fr.php` | `mots_clefs`, `accueil_site`, `derniers_articles` |
| `paquet-X:` | `lang/paquet-X_fr.php` | `X_description`, `X_slogan` |

---

## See also

- `format.md` - lang file structure and formatting
- `conventions.md` - key naming rules
- `../spip-plugins/references/i18n.md` - `<traduire>` declaration and advanced `lang_select()` usage
