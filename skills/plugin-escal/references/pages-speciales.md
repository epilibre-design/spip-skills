# Escal — pages spéciales

Every page below is a squelette at the plugin root, reached as `spip.php?page=‹nom›` (or via the site's URL rewriting). Unless noted, they all share the **`blocnavpages1…10` / `blocextrapages1…10`** slot family for their side columns, plus `formrecherche` (`colgauche` | `coldroite` | vide) to place the search form.

| Page | Fichier | URL | Colonnes |
|---|---|---|---|
| Agenda | `agenda.html` | `?page=agenda` | `espace` only (no side slots) |
| Auteur | `auteur.html` | `?page=auteur&id_auteur=N` | `…pages` |
| Forum d'article | `forum.html` | `?page=forum&id_article=N` | `…pages` |
| Recherche | `recherche.html` | `?page=recherche&recherche=…` | `…pages` |
| Erreur 404 / 401 | `404.html`, `401.html` | served by SPIP on error | `…pages` |
| Contact | `contact.html` | `?page=contact` | `…pages` |
| Annuaire de sites | `annuaire.html` | `?page=annuaire` | `…pages` |
| Mots-clés | `mot.html` | `?page=mot&id_mot=N` | `…pages` |
| Trombinoscope auteurs | `trombino-auteurs.html` | `?page=trombino-auteurs` | `…pages` |
| Forum du site | `forumSite-rubrique.html` etc. | `?page=forumSite-rubrique&id_rubrique=N` | `blocnavforumsite1…10` |
| Plan | `plan.html` | `?page=plan` | `…pages` |
| Article pleine page | `article_pleinepage.html` | routed from `article.html` | none |
| Mentions légales | `mentions-legales.html` | `?page=mentions-legales` | `…pages` |

---

## Agenda et mini-calendrier

Two distinct things, often confused.

**Le mini-calendrier** is the *noisette* `calendrier` (`inclusions/inc-calendrier.html`) — a small month grid you place in a side slot. It reads its events from `calendrier_mini_escal.json.html`. Keys: `titrecalendrier`, `togglecalendrier`, `lienagenda` (show a link to the full agenda), `liennouvelevent` (offer logged-in users a "new event" link), `couleureventscalendrier`, `donneescalendrier`, `listeeventscalendrier`.

**La page Agenda** (`agenda.html`) is the full-screen calendar. It includes `{fond=calendrier}` on desktop — a FullCalendar widget fed by the JSON endpoint `calendrier_quete.json.html` — and `inclusions/inc-events_mobile.html` on small screens. Its own stylesheet is `styles/agenda.css`; it is served `noindex, nofollow`.

Dependencies: **`agenda`** (≥ 4.3.0) supplies the `EVENEMENTS` objects, **`fullcalendarcompat`** (≥ 1.1.0) supplies the FullCalendar/moment libraries. Escal does not bundle a "mini-calendrier" plugin — the mini calendar is its own noisette.

**Event colours** come from the mots group **`Agenda_couleur`**, created at install with five mots (Noir, Rouge, Vert, Violet, Marron). The *descriptif* of each mot holds the CSS colour (`#000000`, `red`, `green`, `#FF00FF`, `#7F3D00`). Tag an event — or an article — with one of these mots and it renders in that colour; the page prints a legend listing only the colours actually in use. Add your own colour by adding a mot to the group with a CSS colour in its descriptif.

`agenda.html` reads events from both `EVENEMENTS` and `ARTICLES`, so a dated article can appear in the agenda.

## Auteur

`auteur.html` — public page for one author. Shows the bio, logo, contact, and their articles. Related noisette: `liste_auteurs` ("Annuaire auteurs et autrices"), whose `lien_trombino_auteurs` key adds a link to the trombinoscope. Keys governing what an author page shows are shared with the article settings (`auteurart`, `iconesauteurs`).

## Forum d'article

`forum.html` — the "respond to this article" page, in the context of `id_article`. Escal renders threads through `inclusions/inc-forum_article.html` on the article page itself; `forum.html` is the standalone posting page carrying `#FORMULAIRE_FORUM`.

Settings live in the *Article* config screen: `afficheformreponse` (show the reply form under the article), `togglereponsearticle`, `affichcomm`, `nbredernierscomms`. Spam protection comes from the required **`nospam`** plugin.

Do not confuse this with the **forum du site** below — different feature, different squelettes.

## Recherche

`recherche.html` — results page, `noindex`. Its config screen lets you switch each result family on or off and order them:

`resultat_articles`, `resultat_rubriques`, `resultat_sites`, `resultat_messages`, and their companions `resultat_ordre_articles`, `resultat_ordre_rubriques`, `resultat_ordre_sites`, `resultat_ordre_forums`. Pagination: `choixpaginrecherche`, `nombreart`.

`escal_options.php` sets `_SURLIGNE_RECHERCHE_REFERERS` and copies `recherche` into `var_recherche`, so search terms are highlighted on the destination page.

**Recherche multi-critères** is a separate feature: the noisette `recherche_multi` (`inclusions/inc-recherche_multi.html` + `formulaires/recherche_multi.php`). Keys `recherchemulti1`…`recherchemulti9` each enable one criterion; `titrerecherchemulti` sets the heading.

## Erreur 404

`404.html` emits `#HTTP_HEADER{HTTP/1.0 #ENV{code,'404 Not Found'}}` plus no-cache headers and `<meta name="robots" content="none">`. It reuses the standard chrome and the `…pages` side slots, so a visitor landing on a dead URL still gets the menus. `401.html` covers unauthorised access. No configuration key switches them on — SPIP serves them automatically.

## Contact

`contact.html` renders `#FORMULAIRE_CONTACT`, a **custom CVT** in `formulaires/contact.php` (`charger` / `verifier` / `traiter`) — it is not SPIP's built-in form.

Fields: `nom`, `prenom`, `email`, `sujet`, `champsup1`, `champsup2`, `checkbox`, `fichier`, plus a `website_url` honeypot. Config screen keys: `contactmail` (recipient), `contactbienvenue` (intro text), `titrechampsup1` / `titrechampsup2` (labels for the two free fields, empty = hidden), `titrecheckbox` / `checkboxliste` / `checkboxoblig`, `fichierjoint` (allow an attachment).

Sending relies on **`facteur`**. `escal_options.php` registers the form with **`nospam`** via `$GLOBALS['formulaires_no_spam'][] = 'contact'`. Attachment validation rejects oversized files and disallowed MIME types with dedicated messages (`contact_fichier_trop_gros`, `contact_format_non_autorise`).

A link to the page is added in the footer by `liencontact`.

## Annuaire de sites

`annuaire.html` is **not** built on SPIP's `SITES` objects. It is built on the **pétition (signatures)** mechanism:

1. Create an article and tag it **`annuaire`**.
2. Open a pétition on that article in the private area.
3. Each signature carries a name, email, site URL and message — that becomes one directory entry.

The page renders `#FORMULAIRE_SIGNATURE` for submissions, then lists signatures paginated 5 per page, deduplicated by URL (`|unique{signatures}`), each with a screenshot fetched from `image.thum.io`. Past 50 entries, an internal search box over the signatures appears automatically.

For a classic list of referenced sites, use the `sites`, `sites_favoris` or `sites_recents` noisettes instead, or the `site.html` page.

## Mots-clés

`mot.html` — all content carrying one mot: articles, rubriques, sites, documents. Keys: `motsclesune`, `auteurrub`, `daterub`, `taillelogoart`, `descriptifdoc`, `descriptifdoccouper`.

Escal's own technical groups are normally hidden from visitors: the `nav_mots` noisette explicitly excludes the groups `affichage`, `trombino`, `type_article` and `type_rubrique` from the mots it displays. Keep your editorial groups separate from those four names.

## Trombinoscope

Two distinct trombinoscopes:

- **`trombino-auteurs.html`** (`?page=trombino-auteurs`) — grid of the site's *auteurs* with their logos. Linked from the `liste_auteurs` noisette when `lien_trombino_auteurs` is on.
- **Trombinoscope de rubrique** — tag a rubrique with a mot named `trombino` in the group **`type_rubrique`**; `rubrique.html` then renders `inclusions/inc-rubrique_trombino.html`, a card grid of that rubrique's articles (one card per person). The group `type_rubrique` is not created by the installer — create it yourself.

## Forum du site

A full discussion forum, separate from article comments. Activation is by keyword:

1. Tag a **secteur** (top-level rubrique) with the mot **`forum`** from the `affichage` group.
2. Everything under that secteur is then served by the forum squelettes instead of the normal ones.

| Squelette | Rôle |
|---|---|
| `forumSite-rubrique.html` | list of discussion topics in a rubrique |
| `forumSite-sujet.html` | one topic and its replies (`#FORMULAIRE_FORUM`) |
| `forumSite-article.html` | an article displayed inside the forum |
| `forumSite-proposer.html` | start a new topic |

`article.html` and `rubrique.html` detect the case with `<BOUCLE(RUBRIQUES){id_secteur}{titre_mot=forum}>` and delegate. Side column: `blocnavforumsite1…10` (14 allowed noisettes, no extra column). Config screen `?cfg=forumsite_principal`: `colforumsite`, `sujetsforum`, `paginforumsite`, `archiveforum`, `accueilforum`.
