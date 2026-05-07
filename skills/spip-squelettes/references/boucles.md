# BOUCLE Types Reference

---

## Anatomy of a BOUCLE

```html
<BB_name>
<!-- optional header section: always displayed -->
<B_name>
<!-- optional pre-section: output once before body, only if ≥1 result -->
<BOUCLE_name(TABLE){critère1}{critère2}>
  <!-- Content repeated for each result row -->
</BOUCLE_name>
<!-- optional post-section: output once after body, only if ≥1 result -->
</B_name>
<!-- optional zero-result alternative: output when loop returns nothing -->
<//B_name>
<!-- optional footer section: always displayed -->
</BB_name>
```

- **`<BB_name>` - `<B_name>`** — header section. Always displayed (e.g. `<thead>` part of a table that can have no result)
- **`<B_name>` - `<BOUCLE_name>`** — pre-section. Wraps HTML that should only appear when the loop has at least one result (e.g. an opening `<ul>`).
- **`</BOUCLE_name>` - `</B_name>`** — post-section. Appears after loop output, only when ≥1 result (e.g. a closing `</ul>` and pagination link).
- **`</B_name>` - `<//B_name>`** — zero-result alternative. Shown when the loop finds nothing (e.g. "No articles yet.").
- **`</B_name>` - `</BB_name>`** — footer section. Always displayed (e.g. `<tfoot>` part of a table that can have no result)

### Annotated example

```html
<B_articles>
<ul class="article-list">

<BOUCLE_articles(ARTICLES){id_rubrique}{par date}{inverse}{pagination 10}>
  <li><a href="#URL_ARTICLE">#TITRE</a> — [(#DATE|affdate)]</li>
</BOUCLE_articles>

</ul>
[(#PAGINATION)]
</B_articles>

  <p>No article in this rubrique</p>
<//B_articles>
```

> **Gotcha:** This is not `<xml>` format

---

## Native BOUCLE Types

| Type | Table | Primary use |
|---|---|---|
| `ARTICLES` | `spip_articles` | Published articles |
| `RUBRIQUES` | `spip_rubriques` | Sections / categories |
| `AUTEURS` | `spip_auteurs` | Authors |
| `FORUMS` | `spip_forum` | Forum messages |
| `MOTS` | `spip_mots` | Keywords / tags |
| `DOCUMENTS` | `spip_documents` | Files, images, media |
| `SITES` | `spip_syndic` | Linked external sites |
| `SYNDIC_ARTICLES` | `spip_syndic_articles` | Syndicated feed items |
| `HIERARCHIE` | `spip_rubriques` | Ancestor rubriques (breadcrumb) |
| `DATA` | *(any source)* | External data: JSON, CSV, XML, YAML, etc. |
| `CONDITION` | *(none)* | Conditional block, no SQL query |

### ARTICLES

Lists articles from the SPIP database.

```html
<!-- Last 5 articles in rubrique #3, newest first -->
<BOUCLE_arts(ARTICLES){id_rubrique=3}{par date}{inverse}{0,5}>
  <li><a href="#URL_ARTICLE">#TITRE</a></li>
</BOUCLE_arts>
```

Key balises: `#TITRE`, `#TEXTE`, `#CHAPO`, `#DESCRIPTIF`, `#DATE`, `#DATE_REDAC`, `#URL_ARTICLE`, `#LOGO_ARTICLE`, `#ID_ARTICLE`, `#ID_RUBRIQUE`, `#LANG`, `#STATUT`.

### RUBRIQUES

Lists sections (categories). Context automatically passes `{id_rubrique}` from a parent BOUCLE.

```html
<!-- Top-level sections, sorted alphabetically -->
<BOUCLE_rubs(RUBRIQUES){racine}{par titre}>
  <h2><a href="#URL_RUBRIQUE">#TITRE</a></h2>
</BOUCLE_rubs>
```

Key balises: `#TITRE`, `#TEXTE`, `#URL_RUBRIQUE`, `#LOGO_RUBRIQUE`, `#ID_RUBRIQUE`, `#ID_PARENT`, `#PROFONDEUR`.

### AUTEURS

Lists authors. Can be scoped to an article via `{id_article}`.

```html
<BOUCLE_auteurs(AUTEURS){id_article}>
  <span class="author">#NOM</span>
</BOUCLE_auteurs>
```

### FORUMS

Lists forum messages. Scope with `{id_article}` or `{id_parent}` for thread nesting.

```html
<BOUCLE_msgs(FORUMS){id_article}{par date}>
  <p><strong>#NOM_EMAIL</strong>: #TEXTE</p>
</BOUCLE_msgs>
```

### MOTS

Lists keywords/tags. Scope with `{id_article}` to get tags for a specific article, or `{type=Categorie}` to filter by keyword group.

```html
<BOUCLE_tags(MOTS){id_article}>
  <span class="tag">#TITRE</span>
</BOUCLE_tags>
```

### DOCUMENTS

Lists files and media attached to articles or rubriques.

```html
<!-- Images attached to current article -->
<BOUCLE_docs(DOCUMENTS){id_article}{extension IN jpg,png,webp}>
  [(#FICHIER|image_reduire{600})]
</BOUCLE_docs>
```

Key balises: `#FICHIER`, `#URL_DOCUMENT`, `#TITRE`, `#EXTENSION`, `#MIME_TYPE`, `#TAILLE`, `#LARGEUR`, `#HAUTEUR`.

### SITES and SYNDIC_ARTICLES

`SITES` lists linked external sites (from SPIP's syndication system). `SYNDIC_ARTICLES` lists individual items from those feeds.

```html
<BOUCLE_flux(SYNDIC_ARTICLES){id_syndic}{par date}{inverse}{0,5}>
  <li><a href="#URL_SYNDIC_ARTICLE">#TITRE</a></li>
</BOUCLE_flux>
```

---

## BOUCLE HIERARCHIE

`HIERARCHIE` returns the ancestor rubriques of the current page, from root down to the direct parent. It is used to build breadcrumbs. It must be inside a RUBRIQUES or ARTICLES boucle

```html
<BOUCLE_hier(HIERARCHIE){id_rubrique}>
  <a href="#URL_RUBRIQUE">#TITRE</a> &gt;
</BOUCLE_hier>
```

> **Invariant:** HIERARCHIE always includes the rubrique of the current page. It walks upward from root to the current rubrique. The final `>` separator is always output (consider using the pre/post sections or CSS to style or trim the last one).

> **Gotcha:** HIERARCHIE does not include the article itself, only its ancestor rubriques. Add the article's `#TITRE` manually after the loop if you want it in the trail.

---

## Nested BOUCLEs and Context Inheritance

Inner loops automatically inherit the context (current identifiers) of their parent loop. A critère like `{id_rubrique}` inside a child loop means "use the `id_rubrique` of the current parent loop iteration."

```html
<BOUCLE_rubs(RUBRIQUES){racine}{par titre}>
  <h2>#TITRE</h2>
  <B_arts>
    <ul>
    <BOUCLE_arts(ARTICLES){id_rubrique}{par titre}>
      <li><a href="#URL_ARTICLE">#TITRE</a></li>
    </BOUCLE_arts>
    </ul>
  </B_arts>
</BOUCLE_rubs>
```

`{id_rubrique}` in the inner `BOUCLE_arts` automatically resolves to the `#ID_RUBRIQUE` of the current `BOUCLE_rubs` iteration — no explicit value needed.

### Accessing parent loop values

Use `#_parentname:BALISE` to read a balise from a named parent loop:

```html
<BOUCLE_rubs(RUBRIQUES)>
  <ul>
    <BOUCLE_arts(ARTICLES){id_rubrique}>
      <!-- Shows "SectionTitle — ArticleTitle" -->
      <li>#_rubs:TITRE — #TITRE</li>
    </BOUCLE_arts>
  </ul>
</BOUCLE_rubs>
```

> **Gotcha:** You must use the exact loop name (without `BOUCLE_` prefix) with an underscore prefix: `#_rubs:TITRE`, not `#rubs:TITRE`.

---

## Recursive BOUCLEs

A recursive boucle calls itself to traverse tree-structured data. The inner boucle references the outer boucle's name as its type.

```html
<BOUCLEx(TABLE){id_parent}>
  ...
  <BOUCLEn(BOUCLEx) />   <!-- calls BOUCLEx again with new context -->
  ...
</BOUCLEx>
```

### Full rubrique tree (nested `<ul>`)

```html
<B_rubs>
<ul>

<BOUCLE_rubs(RUBRIQUES){id_parent}{par num titre, titre}>
  <li>
    <a href="#URL_RUBRIQUE">#TITRE</a>
    <BOUCLE_sous(BOUCLE_rubs) />
  </li>
</BOUCLE_rubs>

</ul>
</B_rubs>
```

- The first call uses `{id_parent}` = 0 (root rubriques).
- Each recursive call passes the current rubrique's `id_rubrique` as the new `id_parent`.
- Recursion stops automatically when a rubrique has no children.

> **Gotcha:** Recursive loops have no depth limit guard in SPIP itself. On a very deep or circular tree (e.g. broken `id_parent` data), they can loop indefinitely. Always ensure the tree data is sound.

**Forum thread tree (recursive replies):**

```html
<BOUCLE_forum(FORUMS){id_article}>
  <p>#TEXTE</p>
  <B_replies>
    <ul class="replies">
    <BOUCLE_replies(FORUMS){id_parent}>
      <li>#TEXTE
        <BOUCLE_deep(BOUCLE_replies) />
      </li>
    </BOUCLE_replies>
    </ul>
  </B_replies>
</BOUCLE_forum>
```

---

## BOUCLE DATA

`DATA` iterates over any data source, not just SQL tables. It was introduced in SPIP 3.0 and remains central in SPIP 4.x.

### Syntax

```html
<BOUCLE_name(DATA){source format, données}>
  [(#CLE)] - [(#VALEUR|print)]
</BOUCLE_name>
```

The `{source format, données}` critère requires:
- **format** — one of: `table` / `array` / `tableau`, `csv`, `json`, `yaml`, `xml`, `file`, `glob` / `pregfiles` / `ls`, `rss` / `atom`, `sql`, `plugins`, `liste`, `enum` (and other formats provided by plugins)
- **données** — a data path: file path, URL, PHP array expression, directory path, or inline string that the format can transform into an iterable table

### DATA-specific criteria and behavior

`DATA` supports most standard criteria plus iterator-specific ones:

| Criterion | Purpose |
|---|---|
| `{valeur=x}` | Filter rows after source loading. Operators: `=`, `>`, `<`, `>=`, `<=`, `==`, `LIKE`. |
| `{par xx}` / `{!par xx}` | Sort ascending / descending. |
| `{a,b}` and `{pagination N}` | Offset/limit and pagination. |
| `{fusion /x/y}` | Merge duplicates on one or more paths after sorting. |
| `{datapath chemin}` | Resolve data path roots for local data files. |
| `{datacache N}` | Cache source loading for N seconds (`0` disables). |

> **Gotcha:** Filtering in a `DATA` loop is done after data retrieval (in PHP), not pushed down to an SQL engine as with native SQL loops.

### Examples

```html
<!-- JSON from external API -->
<BOUCLE_events(DATA){source json, https://api.example.com/events.json}>
  <li>#VALEUR{titre} — [(#VALEUR{date}|affdate)]</li>
</BOUCLE_events>

<!-- CSV file on disk -->
<BOUCLE_csv(DATA){source csv, local/data/produits.csv}>
  <tr><td>#VALEUR{nom}</td><td>#VALEUR{prix}</td></tr>
</BOUCLE_csv>

<!-- Inline list -->
<BOUCLE_sizes(DATA){liste S,M,L,XL}{" | "}>
  #VALEUR
</BOUCLE_sizes>

<!-- Enumerate values from 2 to 10 by steps of 2 -->
<BOUCLE_steps(DATA){enum 2,10,2}{", "}>
  #VALEUR
</BOUCLE_steps>
```

### `{liste ...}` iterator

Use `{liste ...}` for simple inline datasets:

```html
<BOUCLE_months(DATA){liste january, february, march, april}{" ; "}>
  #VALEUR
</BOUCLE_months>
```

Useful patterns:
- Reverse order conditionally: `{inverse #ENV{reverse}}`
- Slice a subset: `{3,2}`
- Use dynamic values: `{liste #GET{v1}, #ENV{v2}}`

### `{enum ...}` iterator

`{enum val1,val2}` or `{enum val1,val2,pas}` enumerates numeric or alphabetic values.

```html
<!-- letters -->
<BOUCLE_letters(DATA){enum g,m}{", "}>#VALEUR</BOUCLE_letters>

<!-- decimals -->
<BOUCLE_dec(DATA){enum 15.5,10.3}{", "}>#VALEUR</BOUCLE_dec>
```

SPIP automatically detects direction (`val1 -> val2`) and applies default step `1` when omitted.

### Protecting iterated data

Inside `DATA`, SPIP protects balises output because sources may be untrusted. This can affect balises such as form calls.

```html
<BOUCLE_safe(DATA){liste a,b,c}>
  #FORMULAIRE_RECHERCHE*{#VALEUR}
</BOUCLE_safe>
```

Use the `*` form when needed (`#BALISE*`) to avoid undesired escaping in these contexts.

### Declaring a custom DATA format

Add `inc/monformat_to_array.php` with `inc_monformat_to_array_dist(...)`, then call it via `{source monformat,...}`.

```php
<?php
function inc_monformat_to_array_dist($data, $param1 = '', $param2 = '') {
    // Return an array or Traversable consumable by the DATA loop.
    return [];
}
```

```html
<BOUCLE_custom(DATA){source monformat, test}>
  #VALEUR
</BOUCLE_custom>
```

### Cache for DATA loops

By default, DATA loops on a URL are cached for 24h. Override with `{datacache N}` (N in seconds), or disable with `{datacache 0}`:

```html
<!-- Refresh every hour -->
<BOUCLE_feed(DATA){source rss, https://blog.example.com/feed}{datacache 3600}>
  <li><a href="#VALEUR{link}">#VALEUR{title}</a></li>
</BOUCLE_feed>
```

> **Gotcha:** DATA loops on a URL incur an HTTP request during page compilation (cached). They do not make a live HTTP call on every page load when the cache is active. Remove `{datacache 0}` in production to avoid latency.

---

## BOUCLE CONDITION

`CONDITION` executes its body if a condition evaluates to true. It performs no SQL query.

### Syntax

```html
<BOUCLE_name(CONDITION){si expression}>
  ... shown when condition is true ...
</BOUCLE_name>
  ... shown when condition is false ...
<//B_name>
```

### Use cases

```html
<!-- Show content only to logged-in users -->
<BOUCLE_auth(CONDITION){si #SESSION{id_auteur}}>
  <p>Bienvenue, [(#SESSION{nom})] !</p>
</BOUCLE_auth>
  <p>Veuillez vous <a href="#URL_PAGE{login}">connecter</a>.</p>
<//B_auth>

<!-- Show a block only if an ENV variable is set -->
<BOUCLE_show(CONDITION){si #ENV{mode_debug}}>
  <div class="debug">Mode debug actif</div>
</BOUCLE_show>
```

> **Gotcha:** `{si ...}` tests for truthiness: an empty string, `"0"`, or `null` evaluates to false. Be careful with balises that return `"0"` as a legitimate value — use explicit comparisons where needed: `{si #ENV{count}|>{0}}`.

---

## Optional Table (Plugin-dependent)

When a loop targets a table that may not exist (e.g. from an optional plugin), append `?` to avoid a visible error for administrators:

```html
<BOUCLE_events(EVENEMENTS ?){id_article}{!par date}>
  ...
</BOUCLE_events>
```

Without `?`, SPIP shows a warning on the page to site administrators when the table is missing.

---

## Summary: When to Use Which BOUCLE

| Need | Use |
|---|---|
| List articles, filter by rubrique/date/statut | `ARTICLES` |
| Navigate section tree | `RUBRIQUES` |
| Breadcrumb trail | `HIERARCHIE` |
| Full site tree (nested menus) | `RUBRIQUES` recursive |
| Tags/keywords | `MOTS` |
| Attached files/images | `DOCUMENTS` |
| External JSON/CSV/RSS data | `DATA` |
| Show/hide based on condition | `CONDITION` |
| Forum threads | `FORUMS` recursive |
