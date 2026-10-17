# Squelettes: 4.x → 5.0

---

## Brackets inside optional parts — CSS trap

5.0 accepts `[` and `]` inside the optional parts of a balise: `[avant] (#VAL{texte})]` prints `avant] texte`.
Side effect: CSS selectors with brackets followed by `(#…)` can now be parsed as an optional part.

```css
/* 4.x — may break in 5.0 */
a[href] { background-image: url(#CHEMIN_IMAGE{lien.svg}); }

/* 5.0 */
a[href] { background-image: url\(#CHEMIN_IMAGE{lien.svg}); }
a[href] { background-image: url("#CHEMIN_IMAGE{lien.svg}"); }
```

Check every `<style>` block and every `.css.html` squelette containing `[` … `(#`.

## Language items are case-sensitive

`<:toto:>` and `<:TOTO:>` are no longer the same key (4.4 already lowercased with a deprecation). Make the call
match the declaration exactly; use lowercase keys and module names: `<:acme:titre_page:>`.

## Inclusions

| 4.x | 5.0 |
|---|---|
| `<INCLURE(fichier.php)>`, `<INCLURE{fond=fichier.php}>` | removed — only `.html` squelettes |
| `<INCLURE{fond=x}{env}></INCLURE>` | `<INCLURE{fond=x}{env} />` |

## Critères

| 4.x | 5.0 | Status |
|---|---|---|
| `{fusion champ}` | `{groupby champ}` (expressions allowed: `{groupby YEAR(date)}`) | deprecated |
| `{fusion_supprimer}` | `{groupby_supprimer}` | deprecated |
| `{collecte utf8_spanish_ci}` | `{collate utf8_spanish_ci}` | deprecated |

## Logos

| 4.x | 5.0 |
|---|---|
| `[(#LOGO_ARTICLE\|left)]` (also `right`, `center`) | `[(#LOGO_ARTICLE{left})]` |
| `[(#LOGO_ARTICLE\|#URL_ARTICLE)]` | `[(#LOGO_ARTICLE{#URL_ARTICLE})]` |
| `[(#LOGO_ARTICLE\|fichier)]` | `[(#LOGO_ARTICLE**)]` |

## Removed loops, filters and balises

| 4.x | 5.0 |
|---|---|
| `<BOUCLE_l(POUR){tableau #LISTE{a,b}}>` | `<BOUCLE_l(DATA){source tableau, #LISTE{a,b}}>` |
| `[(#LISTE{a,b}\|foreach)]` | a `DATA` loop |
| `[(#EXPOSER\|on,off)]` | `[(#EXPOSE{on,off})]` |
| `[(#EMBED_DOCUMENT\|…)]` | `[(#MODELE{emb, …})]` |
| `[(#FORMULAIRE_RECHERCHE\|param)]` | `[(#FORMULAIRE_RECHERCHE{param})]` |
| `\|icone{Éditer,article-24.png,#LANG_RIGHT,edit,ajax}` | `\|icone_verticale{Éditer,article,edit,right ajax}` (or `icone_horizontale`) |
| `\|regledetrois{4,3}` | `\|div{3}\|mult{4}` |
| `\|image_typo` | plugin Images typographiques |
| `#LARGEUR_ECRAN` (private) | `#LAYOUT_PRIVE{pleine-largeur}` |
| `[(#CONST{_ESPACE_PRIVE}\|?{oui,non})]` | `[(#NULL\|test_espace_prive\|?{oui,non})]` |
| `[(#CONST{_DIR_RESTREINT_ABS})]` | `[(#PARAM{spip.routes.back_office})]` |

## Deprecated, removed in SPIP 6

`[(#HTML5|oui) …]` / `#HTML5`: write HTML5 directly.

## Data

`|table_valeur` and `(DATA){source tableau, …}` no longer `unserialize()` strings: pass an array.

## Checking

```bash
grep -rnE "\{(fusion|fusion_supprimer|collecte)\b|</INCLURE>|\.php\)?\}?>|\(POUR\)|\|foreach\b|#LOGO_[A-Z_]+\|(left|right|center|fichier|#URL)|#EXPOSER|#EMBED_DOCUMENT|#HTML5|\|icone\{|\|regledetrois|#LARGEUR_ECRAN|image_typo" --include=*.html .
grep -rnE "<:([^:>]*:)?[^:>]*[A-Z]" --include=*.html .  # uppercase module or key
grep -rnE "\[[^]]*\].*\(#" --include=*.html .          # bracket + (# : review for the CSS trap
```

Then load each page with `?var_mode=recalcul` and read compilation errors.
