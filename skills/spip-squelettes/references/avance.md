# Advanced Features Reference

Topics: modèles (inline shortcodes), native formulaires, squelette variants, custom pages, 404 pages, #CACHE.

---

## Modèles — Inline Shortcodes

Modèles are small templates in `squelettes/modeles/` (or `modeles/` in a plugin). Editors insert them into article body text using a shortcode syntax; SPIP processes them when rendering `#TEXTE`.

### Calling a modèle from article text

```
<mymodel|param1=value1|param2=value2>
```

Example — embed a highlighted article:
```
<article_vedette|id_article=42|style=compact>
```

### Creating a modèle

Create `squelettes/modeles/article_vedette.html`:

```html
<BOUCLE_m(ARTICLES){id_article=#ENV{id_article}}>
  <div class="vedette [(#ENV{style}|sinon{full})]">
    [(#LOGO_ARTICLE|image_reduire{200})]
    <h3><a href="#URL_ARTICLE">#TITRE</a></h3>
    [(#DESCRIPTIF)]
  </div>
</BOUCLE_m>
```

Parameters from the shortcode arrive as `#ENV{param}`.

### Gotcha: modèles are processed inside #TEXTE only

Shortcodes in `#TEXTE` are processed when `propre` or `typo` is applied. They are **not** processed in `#CHAPO`, `#TITRE`, or custom fields unless you explicitly apply `|propre`.

---

## Native Formulaires

SPIP ships with several ready-to-use form balises. Drop them in any squelette; SPIP handles rendering, validation, and processing.

| Balise | Purpose | Typical placement |
|--------|---------|-------------------|
| `#FORMULAIRE_FORUM` | Post a comment on the current article | Inside `BOUCLE_ARTICLES` context |
| `#FORMULAIRE_SIGNATURE` | Sign a petition | Inside `BOUCLE_ARTICLES` with petition enabled |
| `#FORMULAIRE_INSCRIPTION` | New user registration | Any page, typically `login.html` |
| `#FORMULAIRE_LOGIN` | Log in / log out | Any page, typically `login.html` or header |
| `#FORMULAIRE_OUBLI` | Password reset by email | Any page |
| `#FORMULAIRE_RECHERCHE` | Site search form | Any page, typically header |

### Usage examples

```html
<!-- Comment form — context must be inside BOUCLE_ARTICLES -->
<BOUCLE_art(ARTICLES){id_article}>
  ...
  #FORMULAIRE_FORUM
</BOUCLE_art>

<!-- Login form on any page -->
#FORMULAIRE_LOGIN

<!-- Search form with custom button text -->
#FORMULAIRE_RECHERCHE

<!-- Registration form -->
#FORMULAIRE_INSCRIPTION
```

### Gotcha: #FORMULAIRE_FORUM context

`#FORMULAIRE_FORUM` requires `id_article` in context. Outside a `BOUCLE_ARTICLES`, it renders nothing. If you place it in a shared fragment, always pass `id_article` explicitly.

```html
<!-- inc-footer.html — no article context, so pass it explicitly -->
<INCLURE{fond=inc-commentaires,id_article=#ENV{id_article}} />

<!-- inc-commentaires.html -->
<BOUCLE_a(ARTICLES){id_article=#ENV{id_article}}>
  #FORMULAIRE_FORUM
</BOUCLE_a>
```

### PHP-level customization

To customize form validation or processing logic, use the `formulaire_charger`, `formulaire_verifier`, `formulaire_traiter` pipelines — that requires PHP and belongs in a plugin. See the `spip-plugins` skill.

---

## Custom page fonds

Any file in `squelettes/` is accessible as a page:

```
squelettes/plan.html          → accessible at ?page=plan
squelettes/contact.html       → accessible at ?page=contact
```

---

## 404 Page

Create `squelettes/404.html` to display a custom error page when a resource is not found.

```html
<!-- squelettes/404.html -->
<!DOCTYPE html>
<html>
<head><title>Page introuvable - [(#NOM_SITE_SPIP)]</title></head>
<body>
  <h1>Page introuvable</h1>
  <p>La page demandée n'existe pas ou a été déplacée.</p>

  <!-- Show a search form to help the visitor -->
  #FORMULAIRE_RECHERCHE

  <!-- Or list recent articles -->
  <BOUCLE_recent(ARTICLES){statut=publie}{par date}{inverse}{limit 5}>
    <li><a href="#URL_ARTICLE">#TITRE</a></li>
  </BOUCLE_recent>
</body>
</html>
```

SPIP automatically serves this page with an HTTP 404 status code — no PHP configuration required.

### Gotcha: 404.html is not triggered for private-space requests

The custom 404 page applies to the public site. Broken URLs in the private administration interface show SPIP's built-in error page.

---

## #CACHE — Cache Control

`#CACHE{seconds}` controls how long SPIP caches a page. Place it anywhere in the squelette (by convention, near the top).

```html
#CACHE{3600}        <!-- cache for 1 hour (default) -->
#CACHE{86400}       <!-- cache for 24 hours -->
#CACHE{0}           <!-- never cache — always regenerate -->
```

### Cache invalidation

SPIP automatically invalidates the cache when content in the page's context changes:
- An article in a `BOUCLE_ARTICLES` loop is published, modified, or unpublished
- A rubrique is updated

This means `#CACHE{3600}` does not mean visitors wait an hour to see a new article — the cache is cleared immediately when the article is published.

### Personalized cache

Pages with `#CACHE{3600}` are shared across all anonymous visitors. Logged-in users always bypass the cache and see live content. To serve different content to logged-in users, use `BOUCLE_CONDITION` with `#SESSION`.

### Gotcha: #CACHE{0} has a real cost

Every visit regenerates the page from scratch. On high-traffic sites, use the shortest non-zero TTL that makes sense for your data freshness requirements rather than defaulting to 0.

### Gotcha: #CACHE inside INCLURE

The `#CACHE` of the main page controls the outer cache. If an included fragment has its own `#CACHE{0}`, the fragment is regenerated but wrapped inside a cached outer page — the outer cache takes precedence for the full page delivery unless AJAX is used.

---

## Quick Decision Guide

| Need | Use |
|------|-----|
| Reuse a template block | `<INCLURE{fond=...}>` |
| Reusable block, optional (no error if missing) | `[(#INCLURE{fond=...})]` |
| Make a block reload without full page refresh | Add `{ajax}` to INCLURE |
| Insert a template from article body text | Modèle in `squelettes/modeles/` |
| Show a login/registration/search/comment form | `#FORMULAIRE_*` balise |
| Different template per article or rubrique | Variant files (`article-42.html`) |
| Custom "page not found" | `squelettes/404.html` |
| Control caching | `#CACHE{seconds}` |
| Read URL param or INCLURE argument | `#ENV{key}` |
| Store a computed value for reuse in same file | `#SET` / `#GET` |
| Store a value accessible across pages in the session | `#SESSION_SET` / `#SESSION` |
