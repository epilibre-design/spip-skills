# Balises Reference

---

## Balises

Balises output content from the database, from context, or computed by SPIP. They appear inside or outside boucles.

**Syntax recap:**
- `#BALISE` — bare, fails silently if empty
- `[(#BALISE)]` — optional: outputs nothing if balise is empty
- `[before(#BALISE)after]` — wraps with text only when balise has content
- `[(#BALISE|filtre1|filtre2)]` — filter chain

---

### Balises inside BOUCLE ARTICLES

| Balise | Description |
|---|---|
| `#ID_ARTICLE` | Unique identifier |
| `#TITRE` | Title |
| `#TEXTE` | Body text (SPIP markup rendered) |
| `#DESCRIPTIF` | Short description field |
| `#CHAPO` | Introduction / chapeau text |
| `#PS` | Post-scriptum |
| `#SURTITRE` | Surtitle (pre-title) |
| `#SOUSTITRE` | Subtitle |
| `#DATE` | Publication date |
| `#DATE_REDAC` | Authoring date |
| `#DATE_MODIF` | Last modification date |
| `#URL_ARTICLE` | Public URL of the article |
| `#LANG` | Language code of the article (`fr`, `en`, etc.) |
| `#STATUT` | Status: `publie`, `prepa`, `prop`, `refuse` |
| `#ID_RUBRIQUE` | ID of the parent rubrique |
| `#ID_SECTEUR` | ID of the top-level secteur |
| `#POPULARITE` | Popularity score (percentage) |

```html
<article>
  <h1>#TITRE</h1>
  [<p class="intro">(#CHAPO)</p>]
  <div class="body">#TEXTE</div>
  <p class="date">[(#DATE|affdate_court)]</p>
  <a href="#URL_ARTICLE">Lire la suite</a>
</article>
```

#### Logo balises (ARTICLES)

| Balise | Description |
|---|---|
| `#LOGO_ARTICLE` | Logo of the article (with hover support if two logos uploaded) |
| `#LOGO_ARTICLE_NORMAL` | Logo without hover |
| `#LOGO_ARTICLE_SURVOL` | Hover logo only |
| `#LOGO_ARTICLE_RUBRIQUE` | Article logo; falls back to the rubrique logo if no article logo |
| `#LOGO_RUBRIQUE` | Logo of the parent rubrique |

```html
<!-- Fallback logo: article logo if set, else rubrique logo -->
[(#LOGO_ARTICLE_RUBRIQUE{#URL_ARTICLE})]

<!-- Conditionally show logo only when present -->
[(#LOGO_ARTICLE)]
```

**Gotcha:** `#LOGO_ARTICLE` returns nothing if no logo has been uploaded. Use `#LOGO_ARTICLE_RUBRIQUE` to get automatic fallback to the rubrique's logo. Always wrap logo balises in `[(…)]` so no empty `<img>` tags appear.

---

### Balises inside BOUCLE RUBRIQUES

| Balise | Description |
|---|---|
| `#ID_RUBRIQUE` | Unique identifier |
| `#TITRE` | Title |
| `#TEXTE` | Body text of the rubrique description |
| `#DESCRIPTIF` | Short descriptif |
| `#ID_PARENT` | ID of the parent rubrique (0 if at root) |
| `#ID_SECTEUR` | ID of the top-level secteur |
| `#PROFONDEUR` | Depth level in the rubrique tree (0 = root) |
| `#LANG` | Language of the rubrique |
| `#URL_RUBRIQUE` | Public URL |
| `#DATE` | Date of the most recent publication in this rubrique |
| `#INTRODUCTION` | First 600 characters of `#TEXTE`, no markup |
| `#NOTES` | Footnotes generated from the text |
| `#LOGO_RUBRIQUE` | Logo of the rubrique |
| `#LOGO_RUBRIQUE_NORMAL` | Logo without hover |
| `#LOGO_RUBRIQUE_SURVOL` | Hover logo |

```html
<BOUCLE_rubriques(RUBRIQUES){id_parent=0}{par titre}>
<li>
  [(#LOGO_RUBRIQUE{#URL_RUBRIQUE})]
  <a href="#URL_RUBRIQUE">#TITRE</a>
</li>
</BOUCLE_rubriques>
```

**Gotcha:** A BOUCLE RUBRIQUES by default only returns **active** rubriques — ones that contain published articles, documents, or sub-rubriques. Use `{tout}` to include empty rubriques.

---

### Balises inside BOUCLE DOCUMENTS

| Balise | Description |
|---|---|
| `#ID_DOCUMENT` | Unique identifier |
| `#TITRE` | Title of the document |
| `#DESCRIPTIF` | Description |
| `#CREDITS` | Credits (e.g. photographer name) |
| `#ALT` | Alternative text for images |
| `#FICHIER` | Relative URL of the file (path from site root) |
| `#URL_DOCUMENT` | Absolute URL for linking |
| `#EXTENSION` | File extension: `pdf`, `jpg`, `mp4`, etc. |
| `#MEDIA` | Media type: `image`, `audio`, `video`, `file` |
| `#TYPE_DOCUMENT` | Human-readable type label |
| `#LARGEUR` | Image width in pixels |
| `#HAUTEUR` | Image height in pixels |
| `#LOGO_DOCUMENT` | Thumbnail/preview image |
| `#EMBED_DOCUMENT` | Embeds the document (player for audio/video, viewer for PDF) |

```html
<!-- Image gallery -->
<BOUCLE_gallery(DOCUMENTS){id_article}{mode=image}{doublons}>
<figure>
  [(#FICHIER|image_reduire{300})]
  [<figcaption>(#TITRE)</figcaption>]
</figure>
</BOUCLE_gallery>

<!-- Clickable thumbnail linking to full file -->
[(#LOGO_DOCUMENT{#URL_DOCUMENT})]

<!-- PDF download link -->
<BOUCLE_pdf(DOCUMENTS){id_article}{extension=pdf}>
<a href="#URL_DOCUMENT">#TITRE (PDF)</a>
</BOUCLE_pdf>
```

**Gotcha:** Use `{mode=document}` to get attached documents, `{mode=image}` for inline images, or omit `mode` to get both. The `{doublons}` critère prevents images already embedded in `#TEXTE` from appearing twice in the document list.

---

### Global Balises (available outside any boucle)

These balises work anywhere in a squelette, inside or outside a boucle.

#### Site configuration

| Balise | Description |
|---|---|
| `#NOM_SITE_SPIP` | Site name (from admin configuration) |
| `#URL_SITE_SPIP` | Site URL, no trailing slash |
| `#DESCRIPTIF_SITE_SPIP` | Site description |
| `#EMAIL_WEBMASTER` | Webmaster email |
| `#LOGO_SITE_SPIP` | Site logo |
| `#CHARSET` | Character encoding (default: `utf-8`) |
| `#LANG` | Current language of the site |
| `#LANG_DIR` | Text direction: `ltr` or `rtl` |

```html
<meta charset="[(#CHARSET)]">
<html lang="#LANG" dir="#LANG_DIR">
<title>#NOM_SITE_SPIP</title>
```

#### Environment & variables

| Balise | Description |
|---|---|
| `#ENV{key}` | Value of URL parameter or INCLURE argument named `key` |
| `#ENV{key, default}` | With fallback default value |
| `#SET{key, value}` | Sets a local variable in the current squelette |
| `#GET{key}` | Reads a variable set with `#SET` |

```html
<!-- Read URL param: spip.php?page=foo&id=42 -->
[(#ENV{id})]

<!-- Set and reuse a value -->
#SET{couleur, bleu}
La couleur est : #GET{couleur}
```

**Gotcha:** `#ENV` and `#GET`/`#SET` do not cross INCLURE boundaries. Variables set with `#SET` inside an INCLURE are not visible in the parent squelette, and vice versa. Pass values explicitly as INCLURE arguments.

#### Pagination

| Balise | Description |
|---|---|
| `#PAGINATION` | Renders the pagination navigation links |
| `#PAGINATION{modele}` | Renders pagination using a custom modèle |
| `#TOTAL_BOUCLE` | Total number of results in the boucle (before pagination) |
| `#COMPTEUR_BOUCLE` | Current row index within the loop (1-based) |

```html
<!-- Full paginated list with count -->
<p>[(#TOTAL_BOUCLE) articles au total</p>

<BOUCLE_arts(ARTICLES){par date}{inverse}{pagination 10}>
  <p>#COMPTEUR_BOUCLE. <a href="#URL_ARTICLE">#TITRE</a></p>
</BOUCLE_arts>

[(#PAGINATION)] <!-- Appears if more than 10 articles -->
</B_arts>
```

**Gotcha:** `#PAGINATION` must be placed in the **pre-section** (`<B_name>…`) or the **post-section** (`…</B_name>`), not inside the loop body. It renders as nothing when pagination is active.

#### Cache

| Balise | Description |
|---|---|
| `#CACHE{seconds}` | Sets squelette cache duration in seconds |
| `#CACHE{0}` | Disables cache — squelette recomputed on every request |
| `#FILTRE{f}` | Applies one or more filters to the full rendered squelette |

```html
<!-- Cache for 1 hour -->
#CACHE{3600}

<!-- Cache for 1 day -->
#CACHE{24*3600}

<!-- Never cache (e.g., real-time search results) -->
#CACHE{0}
```

**Gotcha:** `#CACHE` affects the whole squelette, not individual boucles. Place it at the top of the file. If omitted, SPIP uses a default cache duration (typically 24h).

#### Post-processing with `#FILTRE`

`#FILTRE` applies a filter (or a filter chain) to the full squelette output, once rendering is complete.

- Syntax: `#FILTRE{filtre_1|filtre_2|...|filtre_n}`
- Placed at the end of a squelette, this balise applies its parameter as a filter to the final generated output.

```html
#FILTRE{supprimer_tags|filtrer_entites|trim}
```

Exception: this balise's filter is applied to squelettes included with `#INCLURE`, but not with `<INCLURE>`.

Example use case: in the notification plugin, a squelette such as `inscription.html` generates an email body. Adding `#FILTRE{supprimer_tags|filtrer_entites|trim}` at the end makes the result email-friendly by removing HTML tags, filtering HTML entities, and trimming redundant whitespace.

#### Navigation & URLs

| Balise | Description |
|---|---|
| `#SELF` | URL of the current page, stripped of SPIP internal params |
| `#URL_PAGE{name}` | URL of a squelette page: `spip.php?page=name` |
| `#URL_PAGE{name, key=val}` | URL with additional params |
| `#MENU_LANG` | Language switcher menu |
| `#REM` | Comment — not rendered in output: `[(#REM) comment here]` |
| `#SQUELETTE` | Path of the current squelette file |

```html
<form action="#SELF" method="get">
  <input type="search" name="recherche">
</form>

[(#REM) This section is the main article body ]
```

---

### Other Loop-Specific Balises (quick reference)

#### BOUCLE AUTEURS

| Balise | Description |
|---|---|
| `#ID_AUTEUR` | Author ID |
| `#NOM` | Author name |
| `#BIO` | Biography |
| `#EMAIL` | Email |
| `#URL_AUTEUR` | Public author page URL |
| `#LOGO_AUTEUR` | Author avatar/logo |

#### BOUCLE MOTS

| Balise | Description |
|---|---|
| `#ID_MOT` | Mot-clé ID |
| `#TITRE` | Mot-clé label |
| `#URL_MOT` | Public URL |
| `#ID_GROUPE` | Group ID this mot belongs to |

#### BOUCLE HIERARCHIE

| Balise | Description |
|---|---|
| `#ID_RUBRIQUE` | Rubrique ID at this level |
| `#TITRE` | Title |
| `#URL_RUBRIQUE` | URL |
| `#PROFONDEUR` | Depth (0 = root) |
| `#RANG` | Position of this node in the hierarchy path |

```html
<!-- Breadcrumb -->
<BOUCLE_hier(HIERARCHIE){self}>
<span><a href="#URL_RUBRIQUE">#TITRE</a> &rsaquo; </span>
</BOUCLE_hier>
#TITRE
```

---

## Common Patterns & Gotchas Summary

| Issue | Solution |
|---|---|
| Logo outputs empty `<img>` | Wrap in `[(…)]`: `[(#LOGO_ARTICLE)]` |
| `#LOGO_ARTICLE` shows nothing | Use `#LOGO_ARTICLE_RUBRIQUE` for automatic rubrique fallback |
| `#ENV{key}` not available in INCLURE | Pass explicitly: `<INCLURE{fond=frag, key=#ENV{key}}>` |
| `#PAGINATION` appears inside loop | Move it to pre- or post-section |
| `#TEXTE` shows raw SPIP markup | Apply `|propre` filter: `[(#TEXTE|propre)]` — but in ARTICLES boucle, `#TEXTE` is already processed |
