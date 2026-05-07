---
name: spip-squelettes
description: Use when working in a SPIP squelettes folder, authoring BOUCLE loops,
  BALISE tags, critères, filtres, INCLURE fragments, or native formulaires. For
  web integrators building SPIP templates without PHP. Not for plugin development.
---

# SPIP — Squelettes Reference

SPIP generates pages from **squelettes** — `.html` files mixing HTML with BOUCLE loops and `#BALISE` tags. All template work lives in `squelettes/`. No PHP needed.

## SPIP-specific terms (always kept in original form)

| Term | Meaning |
|---|---|
| **source of data** | iterable data provider (e.g., SQL query result, array, csv file) |
| **boucle** | `<BOUCLE_xxx(TABLE){critères}>...</BOUCLE_xxx>` — data extractor and content generator (includes pre/post sections and zero-result alternative) |
| **squelette** | Template file (`.html` with SPIP syntax) |
| **paquet.xml** | Code manifest — declares metadata, dependencies |
| **balise** | `#TAG` — template marker compiled to PHP |
| **critère** | `{filter}` inside a boucle — constrains the SQL query |
| **filtre** | `|function` — post-processor applied to a value in a squelette |
| **formulaire CVT** | Charger/Vérifier/Traiter — SPIP's form pattern (load/validate/process) |
| **objet éditorial** | First-class content type with CRUD, i18n, revisions (article, rubrique…) |

## Complete BOUCLE Syntax

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

**Example — 10 articles, newest first, paginated:**

```html
<B_arts>
<ul>
<BOUCLE_arts(ARTICLES){id_rubrique}{par date}{inverse}{pagination 10}>
  <li><a href="#URL_ARTICLE">#TITRE</a> — [(#DATE|affdate_court)]</li>
</BOUCLE_arts>
</ul>
</B_arts>
<p>No article.</p>
<//B_arts>
```

**Nested loop — context inheritance:**

```html
<BOUCLE_rubs(RUBRIQUES){racine}{par titre}>
  <h2>#TITRE</h2>
  <!-- {id_rubrique} refers to the current row of the outer loop -->
  <BOUCLE_arts(ARTICLES){id_rubrique}{par date}{inverse}{limit 5}>
    <a href="#URL_ARTICLE">#TITRE</a>
  </BOUCLE_arts>
</BOUCLE_rubs>
```

## #BALISE Syntax

| Form | Meaning |
|------|---------|
| `#TITRE` | Outputs value; empty string if absent |
| `[(#TITRE)]` | Outputs nothing if empty (suppresses wrapper too) |
| `[before(#TITRE)after]` | Wraps value with before/after only if not empty |
| `[(#TITRE\|filtre1\|filtre2)]` | Chains filters; optional wrapper |

## Key Critères

| Critère | Effect |
|---------|--------|
| `{id_rubrique}` | Items in current rubrique (inherited from outer loop or URL) |
| `{id_rubrique=3}` | Items in rubrique #3 |
| `{par date}{inverse}` | Newest first |
| `{par titre}` | Alphabetical |
| `{limit 5}` | Max 5 results |
| `{pagination 10}` | 10 per page; add `[(#PAGINATION)]` for links |
| `{statut=publie}` | Published items only |
| `{doublons}` | Skip items already stored in an earlier doublon on this page |
| `{branche}` | Current rubrique and all its sub-rubriques |
| `{lang}` | Current language only |

## Key Balises

| Balise | Description |
|--------|-------------|
| `#TITRE` | Title |
| `#TEXTE` | Body (shortcodes processed) |
| `#URL_ARTICLE` / `#URL_RUBRIQUE` | Permalink |
| `#DATE` | Publication date |
| `#LOGO_ARTICLE` | Article logo `<img>` tag (empty if none) |
| `#LOGO_ARTICLE_RUBRIQUE` | Article logo with automatic rubrique fallback |
| `#ENV{key}` | URL param or INCLURE argument |
| `#PAGINATION` | Page nav links (requires `{pagination N}`) |
| `#CACHE{3600}` | Cache this page for N seconds |
| `#FILTRE{f1\|f2}` | Apply filters to the full rendered squelette |

## Load on Demand

- All native loop types, recursive loops → `references/boucles.md`
- Full critères + balises catalog → `references/balises-criteres.md`
- Filter signatures with examples → `references/filtres.md`
- INCLURE composition, file resolution, AJAX partial reload → `references/inclure-ajax.md`
- #ENV, #SET/#GET, #SESSION_SET/#SESSION → `references/variables.md`
- Modèles (`#MODELE`, raccourcis éditoriaux, `modeles/`) → `references/modeles.md`
- Native formulaires, #CACHE, 404, page variants → `references/avance.md`
- Copy-paste complete patterns → `references/exemples.md`

Not for plugin PHP development → use the `spip-plugins` skill.
