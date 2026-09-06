# Escal — configuration, mots-clés, surface PHP

## 1. Où vivent les réglages

One SPIP meta, `escal`, holding the casier `escal/config`.

```php
lire_config('escal/config/blocnav2');              // read
ecrire_config('escal/config/blocnav2', 'edito');   // write
effacer_config('escal');                           // wipe (uninstall does this)
```

```spip
#CONFIG{escal/config/blocnav2}                 <!-- in a squelette -->
#CONFIG{escal/config/taillepolice,75}          <!-- with a default -->
```

The second meta, `escal_base_version`, holds the install schema (`1.0.17`) and drives `escal_upgrade()`. Never edit it by hand.

Export/import: the `ieconfig` pipeline registers `escal,escal_base_version`, so the whole configuration travels through the **IEConfig** plugin.

## 2. Les écrans de réglage

UI path: **espace privé → Squelettes → Escal**, i.e. `?exec=configurer_escal&cfg=‹écran›`. Restricted to webmestres (`inc/escal_autoriser.php`).

The menu tree is the constant `_ESCAL_PAGES_CONFIG` in `escal_options.php`:

| Groupe | `cfg=` | Contenu |
|---|---|---|
| — | `accueil` | Landing screen, version check, no form |
| Généralités | `layout` | Font size, column order, width mode, column widths |
| | `popup` | Modal box on site entry (mot `popup`) |
| | `elements` | Page furniture: shadows, rounded corners, admin links, accessibility button |
| | `bandeau` | Header: title, slogan, logo, background image, alignment |
| | `menuh` | Horizontal menu: variant, 2nd level, sticky, logos |
| | `multilinguisme` | Language menu and behaviour |
| | `pied` | Footer: contact/plan/écrire links, social icons, copyright, citations |
| Colonne principale | `sommaire_principal` | Composite screen — embeds `sommaire_blocs`, `sommaire_annonces`, `sommaire_une`, `sommaire_une_derniers`, `…_bis`, `…_ter`, `sommaire_une_mots_cles` |
| | `autres_principal` | One 25 KB form with fieldsets for rubrique, article, contact, recherche, forum |
| Blocs latéraux | `choix_blocs` | Which noisette in which slot, for all five page families |
| | `parametrage_blocs` | Per-noisette settings (counts, titles, toggles, pagination) |
| Style | `fonds` | Background images, colours, cursors, seasonal graphics |
| | `bords` | Borders, frames, `cadre-couleur` styling |
| Plugins | `plugins` | Enable noisettes that depend on optional plugins |

**Dead files:** `configurer_escal_article_principal.php`, `…_rubrique_principal.php`, `…_contact_principal.php`, `…_forumsite_principal.php` are not in the menu and not included anywhere — their content lives inline in `autres_principal.php`. They remain reachable by typing `?exec=configurer_escal&cfg=article_principal`, but edit `autres_principal.php` instead.

Every form is declarative: only a `formulaires_configurer_escal_‹x›_saisies_dist()` function returning a **Saisies** array. The companion `.html` files are 0 bytes on purpose. A hidden saisie `_meta_casier` = `escal/config` triggers the save; there is no `traiter()`.

## 3. Conventions de nommage des clés

Learn these and most keys become guessable — but always confirm against the source before asserting one.

| Préfixe | Sens | Exemples |
|---|---|---|
| `blocnav*` / `blocextra*` | slot in a side column | `blocnav3`, `blocextraart2`, `blocnavpages7` |
| `blocune1…3` | central slot of the sommaire | |
| `onglet1…5` | content of an « À la une » tab | |
| `titre*` | heading text of a noisette | `titreactus`, `titretop`, `titrememerub` |
| `nombre*` / `nbre*` / `nbr*` | item count | `nombrederniersart`, `nbractus`, `nbrecol` |
| `toggle*` | make the block collapsible | `toggleedito`, `togglestats` |
| `pagin*` / `choixpagin*` | pagination on/off and size | `paginartderub`, `choixpaginrecherche` |
| `affich*` | show/hide a sub-element | `affichchapo`, `affichdescriptif`, `affichrubrique` |
| `couleur1…11`, `couleur*` | palette | `couleurfond`, `couleurpage`, `couleurune` |
| `taille*` | dimension in px or % | `taillepolice`, `taillelogoart`, `tailletexteune` |
| `lien*` | show a link to a special page | `liencontact`, `lienplan`, `lienagenda` |
| `tempo*` | carousel delay | `tempoactus`, `tempophotos` |
| `ordre*` | sort order | `ordrealaune`, `ordresitesfav` |

Suffixes mark the variant a key belongs to: bare = first listing, `bis` / `ter` = second and third, `motcle` = the keyword-driven listing, `rub` / `art` / `pages` / `forumsite` = the page family.

## 4. Les mots-clés techniques

Created by `install_groupe_mots()` at activation, both visible to `minirezo` and `comite`.
Their `tables_liees` differ: `affichage` → `articles,rubriques,syndic`; `Agenda_couleur` → `evenements,articles`.

### Groupe `Agenda_couleur` (5 mots)

The **descriptif holds the CSS colour**. Add your own by adding a mot with a colour in its descriptif.

| Mot | Descriptif |
|---|---|
| Noir | `#000000` |
| Rouge | `red` |
| Vert | `green` |
| Violet | `#FF00FF` |
| Marron | `#7F3D00` |

### Groupe `affichage` (44 mots)

**Désigner un contenu**

| Mot | Effet |
|---|---|
| `acces-direct` | the article shown in the "Accès direct" block |
| `accueil` | the article shown in the "Article d'accueil" tab |
| `actus` | the articles shown in the "Actus" block |
| `agenda` | articles, or rubriques whose articles, feed the agenda |
| `annonce` | the article shown in the "Annonce" block |
| `annonce-defilant` | the articles shown in the scrolling announcements |
| `annuaire` | the article used by `annuaire.html` |
| `archive` | the rubrique a random article is drawn from for the "Article archive" tab |
| `articles-de-rubrique` | the rubrique feeding the "Articles de rubrique" block |
| `article-libre1`…`5` | the article shown in each "Article libre" block |
| `citations` | the article used as a citation pool in the footer |
| `edito` | the article shown in the "Édito" block |
| `favori` | sites shown in "Sites favoris" |
| `mentions-legales` | the article served as legal notice |
| `mon-article`…`mon-article5` | the five articles available as home-page tabs |
| `photo-une` | articles whose images feed "Photos au hasard" |
| `popup` | the article shown in the modal box on entry — `modale.html`, included from `inc-pied.html` when `activerpopup` = `oui`; sizing via `largeurpopup`, `tempspopup`, `couleurfondpopup` |
| `RubriqueOnglet`…`RubriqueOnglet5` | the five rubriques available as home-page tabs |
| `special` | the rubrique/articles of the "Bloc à personnaliser" |
| `video-une` | articles whose videos feed the "Vidéos" block |

**Aiguiller le squelette**

| Mot | Effet |
|---|---|
| `pleinepage` | article rendered full width, no side columns |
| `forum` | on a **secteur**: the whole branch is served by the forum squelettes |
| `chrono` | articles of the rubrique listed antichronologically in menus (not inherited by sub-rubriques) |
| `texte2colonnes` | article body rendered in two columns |
| `article-sans-date` | hide publication and modification dates |

**Exclure d'un affichage**

| Mot | Effet |
|---|---|
| `invisible` | hides a rubrique and its sub-rubriques from all menus, the site map and the latest-articles listings |
| `pas-a-la-une` | excluded from the "Derniers articles" tabs |
| `pas-au-menu` | excluded from the horizontal menu |
| `pas-au-menu-vertical` | excluded from the vertical menus |
| `pas-au-plan` | excluded from the "Plan du site" block |
| `pas-a-decouvrir` | excluded from "À découvrir" when it scans the whole site |
| `site-exclu` | excluded from the "Sur le web" block |

### Groupes à créer soi-même

**`type_article`** and **`type_rubrique`** are *not* created by the installer. Create the group, add a mot, attach it to a **rubrique**, and Escal will include `inclusions/inc-article_‹mot›.html` / `inc-rubrique_‹mot›.html` if that file exists — falling back to `inc-article.html` / `inc-rubrique_normal.html` otherwise. Escal ships `inc-rubrique_trombino.html` and `inc-rubrique_forumSite.html` for this mechanism.

The `nav_mots` noisette hides the groups `affichage`, `trombino`, `type_article` and `type_rubrique` from visitors, so keep editorial groups out of those four names.

## 5. Surface PHP

### Filtres (`escal_fonctions.php`)

| Filtre | Usage |
|---|---|
| `couperpropre($text, $length, $ending, $exact)` | truncate without breaking tags or words |
| `citations($txt)` | pick one citation from an article used as a pool |
| `nofollow($texte)` | add `rel="nofollow"` to outbound links |
| `coef($texte, $max, $nbrMax, $minFont)` | font-size coefficient for a tag cloud |
| `max_mot`, `lettre1`, `supprimer_liens`, `jolie_version` | helpers |
| `decouper_en_XD_parties($texte, $nb, $partie)` | split a text into N columns |

### Balises (require **svpstats**)

`#TOTAL_VISITES` · `#NBPAGES_VISITEES` · `#MOY_VISITES` · `#JOUR_MAX_VISITES` · `#VAL_MAX_VISITES`

### Pipelines (`inc/escal_pipelines.php`)

| Pipeline | Effet |
|---|---|
| `porte_plume_barre_pre_charger` | adds an Escal dropdown to both editing toolbars |
| `porte_plume_lien_classe_vers_icone` | maps the buttons to `icones_barre/*.png` |
| `ieconfig_metas` | exports `escal,escal_base_version` |
| `inserer_modeles_lister_categories` | adds the "escal" category to the model inserter |
| `aide_index` | registers the in-app help pages |


**Dead declaration:** `paquet.xml` also declares a `header_prive_css` pipeline, but no
`escal_header_prive_css()` function exists in `inc/escal_pipelines.php`. The private-area
stylesheet is actually loaded by `<style source="styles/prive_perso.css" type="prive" />`
in `paquet.xml`. Don't look for that pipeline's effect — there isn't one.

### Raccourcis typographiques (`wheels/escal.json`, plugin ORR)

`<aide>` · `<important>` · `<avertissement>` · `<info>` · `<note>` → `<div class="…">`, plus `[| |]` for centring and a right-arrow button. Each has a matching toolbar button.

### Modèles (`modeles/`)

`escal_bloc` (coloured box, has a `.yaml` for the insertion wizard) · `tag` · `choix_article` · `galleria` · `doc_player` · `image_exif` · `image_inline` · `qrcode_impression` · `shoutbox` · `escal_version` · `spip_version` · Rainette models (`escal_conditions_tempsreel`, `escal_previsions_24h`, `escal_infos_ville`).

### Options chargées à chaque hit (`escal_options.php`)

`_ESCAL_PAGES_CONFIG` · search-term highlighting (`_SURLIGNE_RECHERCHE_REFERERS`) · `$GLOBALS['formulaires_no_spam'][] = 'contact'` · `$GLOBALS['forcer_lang'] = true` · `_IMG_GD_MAX_PIXELS` capped at 2 000 000.
