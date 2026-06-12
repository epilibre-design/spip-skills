---
name: spip-squelettes
description: Use for any SPIP template question — BOUCLE loops, balises, critères,
  filtres, INCLURE, pagination, image filters, recursive trees, AJAX reloads. Covers
  public squelettes and plugin squelettes (espace privé, modèles). Trigger even when
  the user doesn't say "squelette". Not for PHP hooks/pipelines → use spip-plugins.
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
| **critère** | `{criteria}` inside a boucle — constrains the SQL query |
| **filtre** | `\|function` — post-processor applied to a value in a squelette |
| **formulaire CVT** | Charger/Vérifier/Traiter — SPIP's form pattern (load/validate/process) |
| **objet éditorial** | First-class content type with CRUD, i18n, revisions (article, rubrique…) |

## Complete BOUCLE Syntax

> **SPIP boucle tags are NOT XML.** `<//B_name>` is a custom SPIP delimiter — it CLOSES the zero-result zone, it does not open it. The zero-result content goes BEFORE `<//B_name>`, between `</B_name>` and `<//B_name>`.

```
<BB_name>                             ← opens always-displayed header
<!-- header: always displayed -->
<B_name>                              ← opens pre+post zone (≥1 result only)
<!-- pre-section: before loop rows, only if ≥1 result -->
<BOUCLE_name(TABLE){critère1}{critère2}>
  <!-- body: repeated for each result row -->
</BOUCLE_name>
<!-- post-section: after loop rows, only if ≥1 result -->
</B_name>                             ← closes pre+post zone; opens zero-result zone
<!-- zero-result alternative: displayed only when loop returns nothing -->
<//B_name>                            ← closes zero-result zone; opens always-displayed footer
<!-- footer: always displayed -->
</BB_name>                            ← closes always-displayed footer
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
- Full critères + balises catalog → `references/balises.md` and `references/criteres.md`
- Filter signatures with examples → `references/filtres.md`
- INCLURE composition, file resolution, AJAX partial reload → `references/inclure-ajax.md`
- #ENV, #SET/#GET, #SESSION_SET/#SESSION → `references/variables.md`
- Modèles (`#MODELE`, raccourcis éditoriaux, `modeles/`) → `references/modeles.md`
- Native formulaires, #CACHE, 404, page variants → `references/avance.md`
- Copy-paste complete patterns → `references/exemples.md`

Not for plugin PHP development → use the `spip-plugins` skill.
Not for PHPUnit tests on squelettes or `#BALISE` → use the `spip-testing` skill.
