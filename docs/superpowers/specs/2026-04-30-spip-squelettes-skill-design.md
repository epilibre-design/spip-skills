# SPIP Squelettes Skill — Design Specification

## Goal

Create a Claude Code skill (`spip-squelettes`) that makes Claude a reliable pair programmer for SPIP web integrators writing squelettes (`.html` template files). The skill covers the complete SPIP template layer: BOUCLE loops, #BALISE tags, critères, filtres, INCLURE, modèles, ajax, native formulaires, and practical patterns — all without requiring PHP knowledge from the user.

**Target: SPIP 4.1+.**

---

## Audience

### Primary audience (the skill IS for these people)
- Web integrators and webmasters who build SPIP sites inside `squelettes/`
- Know HTML, CSS, and SPIP's editorial hierarchy (rubriques, articles, auteurs, mots-clés, documents)
- Do not write PHP and are not expected to
- Francophone (French-speaking community)

### Non-audience (the skill is NOT for these people)
- PHP developers building plugins → `spip-plugins` skill
- SPIP administrators (installation, configuration, backups)
- Total beginners who need a "what is SPIP" conceptual introduction
- Developers extending the SPIP core via PHP API

---

## Relationship with the Existing `spip` Skill

A `spip` skill was designed (spec: `2026-04-29-spip-skill-design.md`) and implemented (GREEN test passed: `docs/tests/spip-green.md`) for the **same primary audience** (squelettes web integrators). Its scope:
- `SKILL.md`: BOUCLE syntax overview, key critères table, balise examples
- `references/balises-criteres.md`: loop types, critères, built-in balises
- `references/filtres.md`: filter catalog

**Overlap:** `spip-squelettes` covers everything `spip` covers, and adds: dedicated boucles reference (native types, recursive loops), INCLURE/modèles, ajax, native formulaires, 404 pages, squelette variants, and a full examples reference.

**Resolution: Upgrade in place.** Rename `spip` → `spip-squelettes` (update directory name, symlink, frontmatter). Expand the 2 existing references to 5. The old `~/.claude/skills/spip` symlink is replaced by `~/.claude/skills/spip-squelettes`. This avoids duplicating effort and keeps the knowledge base coherent.

**Important:** The plan (Task 0) establishes the current state of `/src/spip/skills/spip/` before touching anything.

---

## Trigger Conditions

Claude should auto-load this skill when:
- Any `.html` file in context contains `<BOUCLE_` or `</BOUCLE_`
- Working directory or a parent contains a `squelettes/` folder
- User mentions "squelette", "boucle", "balise", "critère", "filtre" in a SPIP context
- User explicitly invokes `/spip-squelettes`

Claude should NOT load this skill when:
- Working context is clearly a plugin (`paquet.xml` present, `_pipelines.php` present, no `squelettes/`) → `spip-plugins` instead
- User is asking about SPIP PHP API, pipelines, or plugin architecture

---

## Proposed Frontmatter for SKILL.md

```yaml
---
name: spip-squelettes
description: Use when working in a SPIP squelettes folder, authoring BOUCLE loops,
  #BALISE tags, critères, filtres, INCLURE fragments, or native formulaires. For
  web integrators building SPIP templates without PHP. Not for plugin development.
---
```

---

## File Architecture

```
skills/spip-squelettes/
  SKILL.md                     ≤500 words — BOUCLE syntax essentials, balise syntax,
                               quick-ref critères table, decision tree for references
  references/
    boucles.md                 Native loop types, recursive loops, nested loops, DATA/CONDITION
    balises-criteres.md        Complete critères catalog + complete balise catalog per type
    filtres.md                 Full filter catalog with exact signatures and examples
    avance.md                  INCLURE, modèles, ajax, formulaires, 404, variants, CACHE
    exemples.md                Copy-paste patterns: menus, pagination, galleries, archives
```

**Rationale for this split:**
- `SKILL.md` loads on every trigger and must stay fast. It covers only what is needed to write a basic BOUCLE.
- `boucles.md` is separated from `balises-criteres.md` because loop structure (types, pre/post sections, recursive patterns) is conceptually distinct from the critères and balises catalogs.
- `avance.md` bundles higher-order features (INCLURE, modèles, ajax, formulaires) that are not needed for basic squelettes — loaded on demand.
- `exemples.md` provides copy-paste patterns for the most common tasks; entirely optional.

---

## Content Plan per File

### SKILL.md (≤500 words)

**Include:**
- One-sentence definition of a squelette
- Full BOUCLE syntax with all structural parts labeled (`<B_name>`, `<BOUCLE_name>`, `</BOUCLE_name>`, `<BB_name>`, `<//B_name>`)
- `#BALISE` bare vs `[(#BALISE)]` optional vs `[before(#BALISE)after]` form
- Filter chaining: `[(#BALISE|filtre1|filtre2)]`
- Quick-ref table: 10 most-used critères
- Quick-ref table: 8 most-used balises
- One annotated nested-loop example
- Decision tree: which reference file to load for what question
- Disambiguation note: not for plugin PHP dev → spip-plugins

**Do NOT include:**
- Exhaustive critères catalog → balises-criteres.md
- Filter signatures → filtres.md
- INCLURE/modèles/ajax → avance.md
- Long examples → exemples.md

---

### references/boucles.md

**Include:**
- Full table of all native BOUCLE types with description and primary columns:
  ARTICLES, RUBRIQUES, AUTEURS, FORUMS, MOTS, DOCUMENTS, SITES, SYNDIC_ARTICLES,
  HIERARCHIE, DATA, CONDITION
- Pre-section (`<B_name>`) and post-section (`<BB_name>`) semantics with annotated example
- Zero-result alternative `<//B_name>`
- How nested BOUCLEs inherit context (what `{id_rubrique}` means inside an inner loop)
- Recursive loop pattern (rubrique tree with `<BOUCLE_r>`)
- BOUCLE DATA (CSV, JSON, XML external sources) — syntax and examples
- BOUCLE CONDITION — syntax and use cases

**Do NOT include:**
- Individual critère reference entries → balises-criteres.md
- Balise listings per type → balises-criteres.md

**Sources (fetch before writing):**
- `https://programmer.spip.net/La-boucle`
- `https://programmer.spip.net/-Les-types-de-boucles-`
- `https://programmer.spip.net/La-boucle-HIERARCHIE`
- `https://programmer.spip.net/La-boucle-DATA`
- `https://programmer.spip.net/La-boucle-CONDITION`

---

### references/balises-criteres.md

**Include:**
- Complete critères catalog grouped by function:
  - Filtering: `{id_rubrique}`, `{id_secteur}`, `{branche}`, `{statut}`, `{tout}`, `{lang}`, `{lang_select}`, `{doublons}`, `{recherche}`, `{jointure}`, date critères (`{annee}`, `{mois}`, `{age>N}`)
  - Sorting: `{par champ}`, `{inverse}`, `{par hasard}`
  - Pagination: `{limit N}`, `{limit N,M}`, `{pagination N}`, `{debut_boucle N}`
- Complete balise catalog grouped by context type:
  - Article: #TITRE, #TEXTE, #DESCRIPTIF, #CHAPO, #DATE, #DATE_REDAC, #URL_ARTICLE, #LOGO_ARTICLE, #LOGO_ARTICLE_RUBRIQUE, #ID_ARTICLE, #LANG, #STATUT…
  - Rubrique: #TITRE, #TEXTE, #URL_RUBRIQUE, #LOGO_RUBRIQUE, #ID_RUBRIQUE, #ID_PARENT, #PROFONDEUR…
  - Document: #LOGO_DOCUMENT, #URL_DOCUMENT, #EXTENSION, #EMBED_DOCUMENT…
  - Global: #NOM_SITE_SPIP, #URL_SITE_SPIP, #ENV, #SET, #GET, #PAGINATION, #CACHE, #LANG, #INCLURE…
- At least one usage example per entry
- Common gotchas (see quality criteria below)

**Do NOT include:**
- Filter syntax → filtres.md
- INCLURE/modèles advanced patterns → avance.md

**Sources (fetch before writing):**
- `https://programmer.spip.net/-Les-criteres-`
- `https://programmer.spip.net/-Les-balises-`
- `https://programmer.spip.net/La-balise-PAGINATION`

---

### references/filtres.md

**Include:**
- Filter catalog with **exact signatures** grouped by category:
  - Text: `typo`, `propre`, `textebrut`, `couper`, `supprimer_tags`, `entites_html`, `maj`, `min`, `liens_absolus`, `nettoyer_titre_url`
  - Date: `affdate`, `affdate_court`, `affdate_jourcourt`, `timestamp`, `date_iso`, `date_relative`
  - URL: `url_absolue`, `parametre_url`
  - Conditional: `sinon`, `oui`, `non`, `=={val}`
  - Image: `image_reduire`, `image_recadre`, `image_passe_partout`, `image_format`, `image_nb`, `image_src`
  - Number: `taille_en_octets`
- For each filter: full signature with parameter types, description, inline template example
- Filter chaining section with a typical image pipeline example
- Gotcha: `image_reduire` before `image_recadre` to avoid upscaling

**Do NOT include:**
- Custom filter creation (PHP-level) → spip-plugins skill

**Sources (fetch before writing):**
- `https://programmer.spip.net/-Les-filtres-`
- `https://programmer.spip.net/Traitement-des-images`
- `https://programmer.spip.net/Les-filtres-de-dates`
- `https://programmer.spip.net/Les-filtres-de-texte`

---

### references/avance.md

**Include:**
- `<INCLURE{fond=fragment}>` — all syntax forms, argument passing, file resolution order
- `[(#INCLURE{fond=...})]` optional form and when to use it
- `{ajax}` on INCLURE — how it works, requirements (SPIP JS must be loaded), limitations
- Modèles (`squelettes/modeles/` folder) — how shortcodes call them, how to create a custom model
- Native formulaires table: #FORMULAIRE_FORUM, #FORMULAIRE_SIGNATURE, #FORMULAIRE_INSCRIPTION, #FORMULAIRE_LOGIN, #FORMULAIRE_OUBLI, #FORMULAIRE_RECHERCHE
- Squelette variants and file precedence (article-42.html > article-3.html > article.html)
- Custom 404 page (creating `squelettes/404.html`)
- #CACHE semantics and cache invalidation
- #ENV, #SET, #GET — scope and INCLURE boundary behavior

**Do NOT include:**
- PHP-level formulaire customization → spip-plugins skill

**Sources (fetch before writing):**
- `https://programmer.spip.net/La-balise-INCLURE`
- `https://programmer.spip.net/AJAX-dans-SPIP`
- `https://programmer.spip.net/Les-modeles`
- `https://programmer.spip.net/Les-formulaires`
- `https://programmer.spip.net/-Les-squelettes-`
- `https://programmer.spip.net/La-balise-CACHE`
- `https://programmer.spip.net/la-page-404`

---

### references/exemples.md

**Include (all as complete, copy-paste ready templates):**
- Breadcrumb using HIERARCHIE loop
- Navigation menu (recursive rubrique tree)
- Article list with pagination (`<B_>` pre/post sections, `<//B_>` empty state)
- Featured article + remaining list using `{doublons}`
- Image gallery with DOCUMENTS loop
- Related articles by keyword (MOTS + ARTICLES nested)
- Archive by month
- Multi-language switcher (ARTICLES with `{id_trad}`)
- AJAX-reloadable INCLURE fragment
- Conditional display (logged-in vs. anonymous via BOUCLE CONDITION + #SESSION)

**Sources (fetch before writing):**
- `https://programmer.spip.net/Squelette-de-base`
- `https://www.spip.net/fr_rubrique135.html` (navigate to template examples)

---

## Language Policy

**Prose:** English — consistent with the established convention in this project (`2026-04-29-spip-skill-design.md` and `2026-04-29-spip-skills.md` both use English prose). The superpowers skill ecosystem is English-first.

**SPIP domain terms:** Always kept in French — boucle, squelette, balise, critère, filtre, rubrique, article, auteur, mot-clé, fond. These are proper nouns in the SPIP ecosystem; translating them would create confusion with the official documentation and community resources.

---

## Quality Criteria for Each Reference File

A reference file is considered "done" when all of the following hold:

- [ ] Content was sourced from `programmer.spip.net` (URL fetched during writing, not from LLM training memory alone)
- [ ] Every filter, balise, and critère entry includes at least one inline template example
- [ ] Exact function signatures documented for all filtres (name + all parameters)
- [ ] At least one common gotcha/pitfall noted per major section
- [ ] SPIP 4.1 syntax verified — no deprecated SPIP 3.x patterns
- [ ] Invariants documented (e.g., "BOUCLE HIERARCHIE always includes the current page node")

---

## Test Strategy (RED/GREEN verification)

These 8 questions define both the baseline (RED) test — run WITHOUT skill — and the GREEN verification — run WITH skill. A passing GREEN score is ≥7/8.

| # | Question | Reference file tested |
|---|---|---|
| 1 | Write a BOUCLE listing last 5 articles from rubrique #3, newest first. | SKILL.md |
| 2 | Show `#LOGO_ARTICLE` conditionally — fall back to `#LOGO_RUBRIQUE` if empty. | balises-criteres.md |
| 3 | What critère enables pagination, and what balise renders the page links? | SKILL.md / balises-criteres.md |
| 4 | Write a recursive BOUCLE to display the full rubrique tree as a nested `<ul>`. | boucles.md |
| 5 | How does `{doublons}` work? Example: featured article then remaining list that excludes it. | boucles.md / SKILL.md |
| 6 | How to INCLURE a fragment and make it reload via AJAX without a full page refresh? | avance.md |
| 7 | Apply `image_reduire` (max 300px wide) then `image_recadre` (300×200, center) to `#LOGO_RUBRIQUE`. | filtres.md |
| 8 | What balise renders the SPIP login form? Where do you place it in a squelette? | avance.md |
