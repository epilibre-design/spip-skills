# Escal — catalogue des noisettes

A **noisette** is a block squelette in `inclusions/inc-‹nom›.html`. 107 ship with Escal 5.6.0.
A slot holds the **short name only** — `derniers_articles`, not `inc-derniers_articles.html`.

To override one, drop a file at `squelettes/inclusions/inc-‹nom›.html`. To add one, create the file and type its short name into a slot.

---

## 1. Blocs latéraux — availability per page family

Each family exposes a different subset. A value outside its family's list renders nothing.

| Noisette (valeur) | Sommaire | Article | Rubrique | Autres pages | Forum site |
|---|---|---|---|---|---|
| `acces_direct` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `actus` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `article_libre1`…`5` | ✅ | ✅ | ✅ | ✅ | — |
| `articles_de_rubrique` | ✅ | — | — | — | — |
| `calendrier` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `decouvrir_articles` | — | — | ✅ | — | — |
| `derniers_articles` | ✅ | — | ✅ | — | — |
| `derniers_comments` | ✅ | — | — | ✅ | ✅ |
| `documents_article` | — | ✅ | — | — | — |
| `documents_rubrique` | — | — | ✅ | — | — |
| `edito` | ✅ | — | — | ✅ | ✅ |
| `evenements` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `identification` | ✅ | — | — | — | — |
| `liste_auteurs` | ✅ | — | — | — | — |
| `meme_rub` | — | ✅ | — | — | — |
| `nav_mots` | — | ✅ | — | — | — |
| `nav_mots2` | ✅ | ✅ | ✅ | — | — |
| `perso` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `photos` | ✅ | — | — | ✅ | ✅ |
| `rainette` | ✅ | — | — | ✅ | ✅ |
| `recherche_multi` | ✅ | ✅ | — | — | — |
| `sites` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `sites_favoris` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `sites_recents` | ✅ | ✅ | — | — | — |
| `stats` | ✅ | — | — | ✅ | ✅ |
| `top` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `video_accueil` | ✅ | — | — | — | — |
| `rien` | ✅ | ✅ | ✅ | ✅ | ✅ |

Slot keys per family: `blocnav‹n›`/`blocextra‹n›` (sommaire, 1–10) · `blocnavart`/`blocextraart` (1–6) · `blocnavrub`/`blocextrarub` (1–6) · `blocnavpages`/`blocextrapages` (1–10) · `blocnavforumsite` (1–10).

## 2. What each lateral noisette does

| Valeur | Libellé UI | Contenu | Réglages notables |
|---|---|---|---|
| `acces_direct` | Accès direct | The single article tagged **`acces-direct`** | `toggleaccesdirect` |
| `actus` | Actus | Articles tagged **`actus`**, scrolling; splits future vs past `date_redac` | `titreactus`, `nbractus`, `hauteuractus`, `tempoactus`, `couperactus`, `toggleactus` |
| `article_libre1`…`5` | Article libre 1–5 | Five free slots, each showing the article tagged `article-libre1`…`5` | `togglearticlelibre1`…`5` |
| `articles_de_rubrique` | Articles de rubrique | Articles of the rubrique tagged **`articles-de-rubrique`** | `titreartderub`, `nombreartderub`, `paginartderub`, `toggleartderub` |
| `calendrier` | Mini calendrier | Month grid of events; links to the agenda page | `titrecalendrier`, `lienagenda`, `liennouvelevent`, `togglecalendrier` |
| `decouvrir_articles` | À découvrir | Random articles from the branch, minus those tagged `pas-a-decouvrir` | `titredecouvrirarticles`, `pagindecouvrir`, `toggledecouvrirarticles` |
| `derniers_articles` | Derniers articles | Newest articles, excluding rubriques tagged `pas-a-la-une`/`invisible` | `titrederniersart`, `nombrederniersart`, `datederniersart`, `togglederniersarticles` |
| `derniers_comments` | Derniers commentaires | Latest forum messages | `titredernierscomms`, `nbredernierscomms`, `toggledernierscomments` |
| `documents_article` | À télécharger | Documents attached to the current article | `titredocsart`, `pagin_doc_art`, `descriptifdoc` |
| `documents_rubrique` | À télécharger | Documents attached to the current rubrique | `titredocsrub`, `pagin_doc_rub` |
| `edito` | Édito | The single article tagged **`edito`** | `toggleedito` |
| `evenements` | Évènements à venir | Upcoming events (plugin Agenda) and/or articles with a future date | `titreevenements`, `paginevenements`, `toggleevenements` |
| `identification` | Identification | Login box / logged-in user panel | `toggleidentification`, `idlight`, `inscription` |
| `liste_auteurs` | Annuaire auteurs et autrices | Site authors, optional link to the trombinoscope | `togglelisteauteurs`, `lien_trombino_auteurs`, `paginauteurs` |
| `meme_rub` | Dans la même rubrique | Sibling articles of the current one | `titrememerub`, `nombrememerub`, `choixpaginmemerub`, `togglememerubrique` |
| `nav_mots` | Mots-clés associés | Mots of the current article, excluding the technical groups (`affichage`, `Agenda_couleur`, `trombino`, `type_article`, `type_rubrique`) | `titrenavmot` |
| `nav_mots2` | Navigation par mots-clés | Browse one chosen group of mots and its articles | `groupemot`, `titrenavmot2`, `nbreartnavmot2` |
| `perso` | Bloc à personnaliser | Articles of the branch tagged **`special`** — the intended "write your own" block | `titreperso`, `nombreartperso`, `ordreperso`, `tempoperso`, `toggleperso` |
| `photos` | Photos au hasard | Random images from articles tagged **`photo-une`** | `titrephotos`, `nombrephotos`, `tempophotos`, `lienphotos`, `togglephotos` |
| `rainette` | Rainette | Weather widget — requires the **Rainette** plugin | `ville`, `service`, `togglerainette` |
| `recherche_multi` | Recherche multi-critères | Multi-field search form (see pages-speciales.md) | `titrerecherchemulti`, `recherchemulti1`…`9`, `togglemulticritere` |
| `sites` | Sur le web | Referenced sites, optionally excluding those tagged `favori` / `site-exclu` | `titresites`, `nombresites`, `exclurefav`, `togglesites` |
| `sites_favoris` | Sites favoris | Sites tagged **`favori`**, in the current hierarchy | `titresitesfav`, `nombresitesfav`, `ordresitesfav`, `tempositesfav`, `togglesitesfav` |
| `sites_recents` | Derniers articles syndiqués | Newest syndicated items | `titresitesrecents`, `nombreartsitesrecents`, `togglesitesrecents` |
| `stats` | Statistiques | Visit counters — requires **svpstats** | `titrestats`, `totalvisites`, `moyennevisites`, `visiteursenligne`, `togglestats` |
| `top` | Articles les plus vus | Most-read articles | `titretop`, `nombretop`, `toggletop` |
| `video_accueil` | Vidéos | A video block on the home page | `titrevideoaccueil` |

Every `toggle…` key set to `oui` makes the block collapsible.

## 3. Blocs centraux du sommaire

`blocune1`, `blocune2`, `blocune3` accept only four values:

| Valeur | Fichier | Contenu |
|---|---|---|
| `rien` | — | empty slot |
| `annonce` | `inc-annonce.html` | Articles tagged **`annonce`**, optionally within a date window (`periodeaffichage`) |
| `annonce_defilant` | `inc-annonce_defilant.html` | Articles tagged **`annonce-defilant`**, as a carousel (`hauteurannoncedefil`, `tempoannoncedefil`, `ordreannonces`) |
| `a_la_une` | `inc-a_la_une.html` | The tabbed container — see below |

Defaults: `blocune1` falls back to `a_la_une` when unset.

## 4. « À la une » — the tabbed container

`inc-a_la_une.html` renders up to five tabs. Keys `onglet1`…`onglet5` choose the content of each; `titreonglet…` / `titremonart…` set the tab labels; `ancreonglet` controls anchoring.

**The `onglet‹n›` values are a distinct vocabulary — they are not noisette filenames:**

| Valeur `onglet‹n›` | Libellé UI | Noisette rendue |
|---|---|---|
| `derniersarticles` | Derniers articles | `inclusions/inc-une_derniers.html` |
| `derniersarticlesbis` | Derniers articles bis | `inclusions/inc-une_derniers_bis.html` |
| `derniersarticlester` | Derniers articles ter | `inclusions/inc-une_derniers_ter.html` |
| `articlesmotcle` | Articles avec mot-clé | `inclusions/inc-une_motcle.html` |
| `articlesmotcle2` | Articles avec mot-clé 2 | `inclusions/inc-une_motcle2.html` |
| `articlesmotcle3` | Articles avec mot-clé 3 | `inclusions/inc-une_motcle3.html` |
| `plansite` | Plan du site | `inclusions/inc-plan.html` |
| `articleaccueil` | Article d'accueil | `inclusions/inc-article_accueil.html` |
| `articlearchive` | Article archive | `inclusions/inc-article_archive.html` |
| `rubrique` … `rubrique5` | Rubrique 1–5 | `inclusions/inc-rubrique_accueil.html` … `inc-rubrique_accueil5.html` |
| `mon_article` … `mon_article5` | Mon article 1–5 | `inclusions/inc-mon_article.html` … `inc-mon_article5.html` |
| `sites_accueil` | Sur le web | `inclusions/inc-sites_accueil.html` |

How the content of each is chosen:

- **Derniers articles / bis / ter** — three independent listings, each with its own screen (`?cfg=sommaire_une_derniers`, `…_bis`, `…_ter`) and its own key prefixes: `nombrederniersart` vs `nombrearticlesbis` vs `nombrearticlesuneter`, plus per-listing toggles for surtitre, soustitre, chapeau, date, auteur, logo size, column count.
- **Articles avec mot-clé 1–3** — screen `?cfg=sommaire_une_mots_cles`; each picks a mot and lists its articles (`nombrearticlesunemotcle`, `ordrealaunemotcle`, `nbrecolmotcle`…).
- **Article d'accueil** — the article tagged **`accueil`**.
- **Article archive** — the article tagged **`archive`**.
- **Rubrique 1–5** — the rubriques tagged `RubriqueOnglet`, `RubriqueOnglet2`…`RubriqueOnglet5`.
- **Mon article 1–5** — the articles tagged `mon-article`, `mon-article2`…`mon-article5`; labels in `titremonart`…`titremonart5`.

## 5. Noisettes centrales hors slots

Reached by page routing or by the `type_article` / `type_rubrique` mots, never by a `bloc*` key:

| Fichier | Déclencheur |
|---|---|
| `inc-article.html` | default article body |
| `inc-article_pleine_page.html` | article tagged `pleinepage` (via `article_pleinepage.html`) |
| `inc-article_‹mot›.html` | mot `‹mot›` of group **`type_article`** on the rubrique |
| `inc-rubrique_normal.html` | default rubrique body |
| `inc-rubrique_‹mot›.html` | mot `‹mot›` of group **`type_rubrique`** on the rubrique — the only one shipped is `inc-rubrique_trombino.html` (mot titled exactly `trombino`) |
| `inc-forum_article.html` | article forum, when open |
| `inc-article_forumSite.html`, `inc-rubrique_forumSite.html` | included directly by `forumSite-article.html` / `forumSite-rubrique.html` — **not** through the mot groups |
| `inc-portfolio.html`, `inc-documents_article.html` | article attachments |

## 6. Variantes internes

About thirty files in `inclusions/` are never named in a slot — a parent noisette picks between them
at render time. Recognising the suffix convention avoids hunting for a setting that does not exist:

| Suffixe | Sens | Exemples |
|---|---|---|
| `_art`, `_event` | the article-backed vs event-backed version of the same block | `inc-calendrier_art`, `inc-calendrier_event`, `inc-evenements_articles`, `inc-evenements_events` |
| `_rub`, `_site` | scoped to the current rubrique vs the whole site | `inc-decouvrir_articles_rub`, `inc-decouvrir_articles_site`, `inc-sites_favoris_rub`, `inc-sites_favoris_site` |
| `_2eniveau` | the two-level variant of a menu or tree | `inc-menu2eniveau`, `inc-hierarchie_rub_2eniveau` |
| `2`, `3`, `_bis`, `_ter` | the 2nd/3rd independent copy of a configurable listing | `inc-une_derniers_bis`, `inc-nav_mots2`, `inc-rubrique_accueil3` |
| `_forumSite` | the version served inside the site forum | `inc-article_forumSite`, `inc-rubrique_forumSite` |
| `_mobile` | the small-screen version | `inc-events_mobile` |

**Which key picks the variant** — the parent noisette is what you place in a slot; these keys decide
what it renders. Answering "how do I select `inc-decouvrir_articles_site`?" with a slot name is wrong:
you place `decouvrir_articles` and set `siteourub`.

| Parent (valeur de slot) | Clé de bascule | Variantes |
|---|---|---|
| `decouvrir_articles` | `siteourub` = `site` \| `rub` (défaut `rub`) | `inc-decouvrir_articles_site` / `_rub` |
| `evenements` | `donneescalendrier` | `inc-evenements_events` / `inc-evenements_articles` |
| `calendrier` | `donneescalendrier` | `inc-calendrier_event` / `inc-calendrier_art` |
| `sites_favoris` | current context (rubrique or not) | `inc-sites_favoris_rub` / `_site` |

Others are pure plumbing with no setting of their own: `inc-triurlrubrique`, `inc-video_player`,
`inc-donnees_exif`, `inc-choixmenuV1` / `V2`, `inc-annonce_defilant_article`, `inc-evenements_inscription`,
`inc-events_agenda`, `inc-events_calendrier`.

## 7. Noisettes de structure

Not selectable — they build the chrome, and are the ones you override to restyle the frame:

`inc-head` (all stylesheets + favicons) · `inc-entete` · `inc-bandeau` (title, logo, accessibility button) · `inc-menu`, `inc-menuH2`, `inc-menu2eniveau`, `inc-menu2eniveauH2` (horizontal menus) · `inc-menu_vertical`, `inc-menu_vertical_2`, `inc-menu2eniveau_vertical` · `inc-menumobile`, `inc-menumobile2eniveau` · `inc-espace`, `inc-espace_article`, `inc-espace_self` (the free strip above/below the header) · `inc-pied` · `inc-javascripts` · `inc-hierarchie_art`, `inc-hierarchie_rub` (fil d'Ariane) · `inc-plan`, `inc-plan_secteur`, `inc-plan_rub2eniveau`.
