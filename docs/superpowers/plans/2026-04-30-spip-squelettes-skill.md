# SPIP Squelettes Skill — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create `skills/spip-squelettes/` — a comprehensive Claude Code skill for SPIP template developers (web integrators working in `squelettes/`), upgrading the earlier `spip` skill with 5 reference files covering boucles, balises, critères, filtres, advanced features, and examples.

**Architecture:** Skills live in `/src/spip/skills/` (version-controlled) and are symlinked into `~/.claude/skills/`. `spip-squelettes` supersedes the earlier `spip` skill (same audience, wider scope). Each reference file is sourced from `programmer.spip.net` before writing. Implementation follows TDD: baseline subagent test (RED) → write files (GREEN) → verify with subagent.

**Tech Stack:** Markdown, YAML frontmatter, SPIP 4.1+ (`programmer.spip.net` as source of truth)

---

## File Map

| Action | Path |
|---|---|
| Create | `/src/spip/skills/spip-squelettes/SKILL.md` |
| Create | `/src/spip/skills/spip-squelettes/references/boucles.md` |
| Create | `/src/spip/skills/spip-squelettes/references/balises-criteres.md` |
| Create | `/src/spip/skills/spip-squelettes/references/filtres.md` |
| Create | `/src/spip/skills/spip-squelettes/references/avance.md` |
| Create | `/src/spip/skills/spip-squelettes/references/exemples.md` |
| Create | `~/.claude/skills/spip-squelettes` → symlink to above |
| Remove | `~/.claude/skills/spip` symlink (if present) |
| Create | `/src/spip/docs/tests/spip-squelettes-baseline.md` |
| Create | `/src/spip/docs/tests/spip-squelettes-green.md` |

---

### Task 0: Audit existing `spip` skill state

**Files:**
- Read: `/src/spip/skills/spip/` (if it exists)

- [ ] **Step 1: Check current state of skills directory**

```bash
find /src/spip/skills -type f | sort 2>/dev/null || echo "no /src/spip/skills dir"
ls -la ~/.claude/skills/ 2>/dev/null || echo "no ~/.claude/skills"
```

Document:
- Does `/src/spip/skills/spip/SKILL.md` exist? If yes, its content is the starting point for `SKILL.md` in Task 3.
- Does `/src/spip/skills/spip/references/balises-criteres.md` exist? If yes, it is the starting point for Task 5.
- Does `/src/spip/skills/spip/references/filtres.md` exist? If yes, it is the starting point for Task 6.
- Is `~/.claude/skills/spip` a symlink? It will be removed in Task 1.

- [ ] **Step 2: Decide migration approach**

If `/src/spip/skills/spip/` exists and has content:
→ Copy `skills/spip/` to `skills/spip-squelettes/` as starting point, then expand.
→ Use: `cp -r /src/spip/skills/spip /src/spip/skills/spip-squelettes`

If it does not exist (or is empty):
→ Create from scratch. Tasks 3–8 provide full file content.

---

### Task 1: Setup — directory structure and symlinks

**Files:**
- Create: `/src/spip/skills/spip-squelettes/references/` (directory)
- Create: `~/.claude/skills/spip-squelettes` (symlink)

- [ ] **Step 1: Create skill directories**

```bash
mkdir -p /src/spip/skills/spip-squelettes/references
mkdir -p /src/spip/docs/tests
```

Expected: no output (directories created silently)

- [ ] **Step 2: Create symlink**

```bash
mkdir -p ~/.claude/skills
ln -s /src/spip/skills/spip-squelettes ~/.claude/skills/spip-squelettes
```

- [ ] **Step 3: Remove old `spip` symlink if present**

```bash
[ -L ~/.claude/skills/spip ] && rm ~/.claude/skills/spip && echo "removed old spip symlink" || echo "no spip symlink found — nothing to remove"
```

- [ ] **Step 4: Verify**

```bash
ls -la ~/.claude/skills/
```

Expected: `spip-squelettes -> /src/spip/skills/spip-squelettes` present; no `spip` entry.

- [ ] **Step 5: Commit**

```bash
cd /src/spip
git add skills/spip-squelettes/
git commit -m "chore: scaffold spip-squelettes skill directory"
```

---

### Task 2: RED — Baseline subagent test

Establish what Claude does WITHOUT the skill. This MUST run before any skill files are written.

**Files:**
- Create: `/src/spip/docs/tests/spip-squelettes-baseline.md`

- [ ] **Step 1: Dispatch baseline subagent**

Dispatch a fresh subagent with this exact prompt. Do not mention the skill or load it.

```
Answer these SPIP template questions as precisely and concretely as possible.
SPIP uses its own template language (not PHP, not Twig, not Jinja).

1. Write a BOUCLE that lists the last 5 published articles from rubrique #3,
   sorted by date descending. Show the exact SPIP syntax.

2. How do you display #LOGO_ARTICLE only when it exists, and fall back to
   #LOGO_RUBRIQUE when it doesn't?

3. What critère enables paginated results in a BOUCLE, and what balise renders
   the pagination links?

4. Write a recursive BOUCLE to render the full rubrique tree as a nested <ul>.

5. How does the {doublons} critère work? Give a concrete example with a featured
   article followed by a list that excludes it.

6. How do you include a partial template fragment and make it reload via AJAX
   without a full page reload?

7. Apply image_reduire (max width 300px) then image_recadre (300×200, crop center)
   to #LOGO_RUBRIQUE. Show the exact SPIP filter chain syntax.

8. What balise renders the SPIP login form? Where do you place it in a squelette?
```

- [ ] **Step 2: Create baseline document**

Create `/src/spip/docs/tests/spip-squelettes-baseline.md`:

```markdown
# SPIP Squelettes Skill — Baseline Test (RED)

Date: 2026-04-30

## Subagent Answers

[paste exact answers here]

## Analysis

| Q | SPIP syntax correct? | Notes |
|---|---|---|
| 1 | Y/N | |
| 2 | Y/N | |
| 3 | Y/N | |
| 4 | Y/N | |
| 5 | Y/N | |
| 6 | Y/N | |
| 7 | Y/N | |
| 8 | Y/N | |

**Score: X/8**

## Key gaps identified

[List which concepts were missing or wrong — these are what the skill must teach]
```

- [ ] **Step 3: Commit baseline**

```bash
cd /src/spip
git add docs/tests/spip-squelettes-baseline.md
git commit -m "test: spip-squelettes baseline (RED)"
```

---

### Task 3: Write SKILL.md (≤500 words)

**Files:**
- Create or overwrite: `/src/spip/skills/spip-squelettes/SKILL.md`

- [ ] **Step 1: Fetch primary source**

Fetch `https://programmer.spip.net/-Ecriture-des-squelettes-` and verify the BOUCLE syntax overview. Note any details that differ from the content below.

- [ ] **Step 2: Write SKILL.md**

Create `/src/spip/skills/spip-squelettes/SKILL.md`:

```markdown
---
name: spip-squelettes
description: Use when working in a SPIP squelettes folder, authoring BOUCLE loops,
  #BALISE tags, critères, filtres, INCLURE fragments, or native formulaires. For
  web integrators building SPIP templates without PHP. Not for plugin development.
---

# SPIP — Squelettes Reference (SPIP 4.1+)

SPIP generates pages from **squelettes** — `.html` files mixing HTML with BOUCLE loops and `#BALISE` tags. All template work lives in `squelettes/`. No PHP needed.

## BOUCLE Syntax

```html
<B_name>        <!-- pre-section: output once before body, only if ≥1 result -->
<BOUCLE_name(TABLE){critère1}{critère2}>
  ...repeated for each result row...
</BOUCLE_name>
<BB_name>       <!-- post-section: output once after body, only if ≥1 result -->
<//B_name>      <!-- zero-result alternative: output when loop returns nothing -->
```

**Example — 10 articles, newest first, paginated:**

```html
<B_arts><ul></B_arts>
<BOUCLE_arts(ARTICLES){id_rubrique}{par date}{inverse}{pagination 10}>
  <li><a href="#URL_ARTICLE">#TITRE</a> — [(#DATE|affdate_court)]</li>
</BOUCLE_arts>
<BB_arts></ul></BB_arts>
<//B_arts><p>Aucun article.</p>
[(#PAGINATION)]
```

**Nested loop — context inheritance:**

```html
<BOUCLE_rubs(RUBRIQUES){racine}{par titre}>
  <h2>#TITRE</h2>
  <!-- {id_rubrique} here refers to the current row of the outer loop -->
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
| `[before(#TITRE)after]` | Wraps value with before/after text only if not empty |
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
| `{doublons}` | Skip items already output by an earlier loop on this page |
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

## Load on Demand

- All native loop types, recursive loops → `references/boucles.md`
- Full critères + balises catalog → `references/balises-criteres.md`
- Filter signatures with examples → `references/filtres.md`
- INCLURE, modèles, ajax, formulaires, 404 → `references/avance.md`
- Copy-paste complete patterns → `references/exemples.md`

Not for plugin PHP development → use the `spip-plugins` skill.
```

- [ ] **Step 3: Verify word count**

```bash
wc -w /src/spip/skills/spip-squelettes/SKILL.md
```

Expected: under 500 words. If over 500, trim the Key Balises table (remove 2–3 rows) or shorten the nested-loop example comment.

- [ ] **Step 4: Commit**

```bash
cd /src/spip
git add skills/spip-squelettes/SKILL.md
git commit -m "feat: add spip-squelettes SKILL.md"
```

---

### Task 4: Write references/boucles.md

**Files:**
- Create: `/src/spip/skills/spip-squelettes/references/boucles.md`

- [ ] **Step 1: Fetch primary sources**

Fetch all of these pages (not just one) before writing. Record any loop types or behaviors not listed below:
- `https://programmer.spip.net/La-boucle`
- `https://programmer.spip.net/-Les-types-de-boucles-`
- `https://programmer.spip.net/La-boucle-HIERARCHIE`
- `https://programmer.spip.net/La-boucle-DATA`
- `https://programmer.spip.net/La-boucle-CONDITION`

- [ ] **Step 2: Write boucles.md**

Create `/src/spip/skills/spip-squelettes/references/boucles.md`:

```markdown
# SPIP Boucles Reference (SPIP 4.1+)

## BOUCLE Structure — Full Form

```html
<B_name>
  <!-- pre-section: rendered ONCE before the body, only when loop has ≥1 result -->
  <ul>
<BOUCLE_name(TABLE){critère1}{critère2}>
  <!-- body: rendered once per result row -->
  <li>#BALISE</li>
</BOUCLE_name>
  </ul>
<BB_name>
  <!-- post-section: rendered ONCE after the body, only when loop has ≥1 result -->
<//B_name>
  <!-- zero-result alternative: rendered only when loop returns 0 results -->
  <p>Aucun résultat.</p>
```

## Native Loop Types

| Loop | Table | Primary use |
|------|-------|-------------|
| ARTICLES | spip_articles | Published articles |
| RUBRIQUES | spip_rubriques | Site sections / categories |
| AUTEURS | spip_auteurs | Authors and contributors |
| FORUMS | spip_forum | Forum messages / comments |
| MOTS | spip_mots | Keywords and tags |
| DOCUMENTS | spip_documents | Files, images, audio, video |
| SITES | spip_syndication | Syndicated external sites |
| SYNDIC_ARTICLES | spip_syndic_articles | Syndicated articles from a site |
| HIERARCHIE | (virtual) | Ancestor rubriques of the current page |
| DATA | (external) | CSV, JSON, XML, or YAML data source |
| CONDITION | (virtual) | Conditional block (if/else) |

### ARTICLES

Primary columns: `id_article`, `titre`, `texte`, `descriptif`, `chapo`, `date`, `date_redac`, `statut`, `id_rubrique`, `id_secteur`, `lang`, `id_trad`

```html
<BOUCLE_articles(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{limit 10}>
  <article>
    <h2><a href="#URL_ARTICLE">#TITRE</a></h2>
    <time>[(#DATE|affdate_court)]</time>
    [(#DESCRIPTIF|sinon{#TEXTE|textebrut|couper{200}})]
  </article>
</BOUCLE_articles>
```

### RUBRIQUES

Primary columns: `id_rubrique`, `titre`, `descriptif`, `texte`, `id_parent`, `id_secteur`, `statut`, `lang`

```html
<BOUCLE_rubriques(RUBRIQUES){racine}{par titre}>
  <h2><a href="#URL_RUBRIQUE">#TITRE</a></h2>
  [(#DESCRIPTIF)]
</BOUCLE_rubriques>
```

### HIERARCHIE

Returns the ancestor rubrique chain from root to current rubrique. Always includes the current node. Used for breadcrumbs.

```html
<nav aria-label="fil d'ariane">
<BOUCLE_fil(HIERARCHIE)>
  <a href="#URL_RUBRIQUE">#TITRE</a> ›
</BOUCLE_fil>
<span>#TITRE</span>  <!-- current page title, from outer context -->
</nav>
```

Invariant: the first row is always the root rubrique (depth 0); the last row is the immediate parent of the current page.

### DOCUMENTS

Primary columns: `id_document`, `titre`, `fichier`, `extension`, `mime_type`, `taille`, `largeur`, `hauteur`

```html
<!-- Images attached to current article -->
<BOUCLE_docs(DOCUMENTS){id_article}{extension=jpg|png|gif|webp}{par rang_lien}>
  <figure>
    [(#LOGO_DOCUMENT|image_reduire{600,0})]
    <figcaption>[(#TITRE)]</figcaption>
  </figure>
</BOUCLE_docs>
```

### DATA — External Sources

Loops over CSV, JSON, XML, or YAML data. Column names come from the source headers.

```html
<!-- JSON endpoint -->
<BOUCLE_api(DATA){source json, https://api.example.com/items.json}{cle items}>
  <p>#titre — #description</p>
</BOUCLE_api>

<!-- Local CSV (relative to SPIP root) -->
<BOUCLE_csv(DATA){source csv, local/data/items.csv}>
  <p>#col1 — #col2</p>
</BOUCLE_csv>
```

### CONDITION — Conditional Block

Renders based on a PHP-style expression. Prefer for logged-in/anonymous guards.

```html
<!-- Show only to logged-in visitors -->
<BOUCLE_logged(CONDITION){si #SESSION{id_auteur}}>
  <p>Bienvenue, [(#SESSION{nom})] !</p>
</BOUCLE_logged>

<!-- Show only to anonymous visitors -->
<BOUCLE_anon(CONDITION){si !#SESSION{id_auteur}}>
  [(#FORMULAIRE_LOGIN)]
</BOUCLE_anon>
```

## Nested BOUCLEs — Context Inheritance

An inner BOUCLE automatically inherits the current row of the outer BOUCLE. `{id_rubrique}` inside an inner loop refers to the `id_rubrique` of the outer loop's current row — no explicit argument needed.

```html
<BOUCLE_rubs(RUBRIQUES){racine}{par titre}>
  <h2>#TITRE</h2>
  <!-- id_rubrique here = outer loop's current row id -->
  <BOUCLE_arts(ARTICLES){id_rubrique}{par date}{inverse}{limit 5}>
    <a href="#URL_ARTICLE">#TITRE</a>
  </BOUCLE_arts>
</BOUCLE_rubs>
```

## Recursive BOUCLE — Full Rubrique Tree

```html
<ul>
<BOUCLE_arbre(RUBRIQUES){id_parent}{tout}>
  <li>
    <a href="#URL_RUBRIQUE">#TITRE</a>
    <ul>
    <BOUCLE_arbre>  <!-- recursive call: re-runs with id_parent = current id_rubrique -->
    </ul>
  </li>
</BOUCLE_arbre>
</ul>
```

Gotcha: `{tout}` includes non-published rubriques. Remove it to show only published rubriques, but test with your SPIP version — some configurations require `{tout}` for the recursive call to work even when showing only published items.

## {doublons} — Cross-loop Deduplication

`{doublons}` skips any item (identified by primary key) already rendered by a previous BOUCLE of the same type on the same page.

```html
<!-- Step 1: featured article -->
<BOUCLE_vedette(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{limit 1}>
  <article class="featured">
    [(#LOGO_ARTICLE_RUBRIQUE|image_reduire{800,0})]
    <h2><a href="#URL_ARTICLE">#TITRE</a></h2>
  </article>
</BOUCLE_vedette>

<!-- Step 2: remaining articles — featured one automatically excluded -->
<ul>
<BOUCLE_reste(ARTICLES){id_rubrique}{statut=publie}{doublons}{par date}{inverse}{limit 9}>
  <li><a href="#URL_ARTICLE">#TITRE</a></li>
</BOUCLE_reste>
</ul>
```

Named doublons: `{doublons NOM}` lets you reference a specific earlier loop, useful when loop names collide across INCLURE fragments.
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip-squelettes/references/boucles.md
git commit -m "feat: add spip-squelettes boucles reference"
```

---

### Task 5: Write references/balises-criteres.md

**Files:**
- Create: `/src/spip/skills/spip-squelettes/references/balises-criteres.md`

Note: If this file was migrated from `skills/spip/references/balises-criteres.md` in Task 0, start from that content and expand it with the sections below.

- [ ] **Step 1: Fetch primary sources**

Fetch these pages before writing. Add any critères or balises found that are not listed below:
- `https://programmer.spip.net/-Les-criteres-`
- `https://programmer.spip.net/-Les-balises-`
- `https://programmer.spip.net/La-balise-PAGINATION`

- [ ] **Step 2: Write balises-criteres.md**

Create `/src/spip/skills/spip-squelettes/references/balises-criteres.md`:

```markdown
# SPIP Balises & Critères Reference (SPIP 4.1+)

## Critères Catalog

### Filtering

| Critère | Effect | Example |
|---------|--------|---------|
| `{id_rubrique}` | Items in the current rubrique (inherited from outer loop or URL context) | `<BOUCLE_a(ARTICLES){id_rubrique}>` |
| `{id_rubrique=N}` | Items in rubrique #N | `{id_rubrique=3}` |
| `{id_secteur}` | Items anywhere in the sector (entire rubrique subtree under a top-level rubrique) | `{id_secteur}` |
| `{branche}` | Current rubrique and all its sub-rubriques recursively | `{branche}` |
| `{statut=publie}` | Published items only | `{statut=publie}` |
| `{tout}` | All statuses including drafts and proposed | `{tout}` |
| `{lang}` | Items in the current navigation language | `{lang}` |
| `{lang_select}` | Items in the user-selected language (includes translated versions) | `{lang_select}` |
| `{id_article=N}` | Specific article | `{id_article=#ENV{id_article}}` |
| `{id_mot=N}` | Items tagged with keyword #N | `{id_mot}` (inherits from context) |
| `{recherche}` | Full-text search results — reads search term from `#ENV{recherche}` | `{recherche}` |
| `{doublons}` | Skip items already output by a previous BOUCLE of the same type on this page | `{doublons}` |
| `{doublons NOM}` | Skip items output by a loop named NOM | `{doublons vedette}` |
| `{objet=article}{id_objet}` | Documents attached to a specific article | — |

### Sorting

| Critère | Effect |
|---------|--------|
| `{par titre}` | Ascending alphabetical by title |
| `{par date}` | Ascending by publication date |
| `{par date_redac}` | Ascending by editorial date |
| `{par num titre}` | Numerical sort on leading number in title ("10. Article" → correct order) |
| `{par hasard}` | Random order (changes on every page load — cache accordingly) |
| `{inverse}` | Reverse the current sort direction (always pair with `{par ...}`) |

### Pagination & Limits

| Critère | Effect |
|---------|--------|
| `{limit N}` | Return at most N results |
| `{limit N,M}` | Return M results starting at offset N (0-indexed) |
| `{pagination N}` | Enable pagination; N items per page. Requires `[(#PAGINATION)]` for links. |
| `{debut_boucle N}` | Static offset — start at result N (0-indexed, no pagination) |

### Date Filtering

| Critère | Effect |
|---------|--------|
| `{age>N}` | Items older than N days |
| `{age<N}` | Items newer than N days |
| `{annee}` | Filter to year read from `#ENV{annee}` |
| `{mois}` | Filter to month read from `#ENV{mois}` |
| `{jour}` | Filter to day read from `#ENV{jour}` |

### Joins

`{jointure TABLE}` performs an SQL JOIN so you can filter by columns of a related table:

```html
<!-- Articles that belong to the keyword group "Actualités" -->
<BOUCLE_arts(ARTICLES){jointure mots}{titre_groupe=Actualités}>
  <a href="#URL_ARTICLE">#TITRE</a>
</BOUCLE_arts>
```

---

## Balises Catalog

### Article Balises

| Balise | Description | Notes |
|--------|-------------|-------|
| `#TITRE` | Title text | |
| `#TEXTE` | Body content (shortcodes → HTML, typography applied) | Use `\|textebrut` to strip HTML |
| `#DESCRIPTIF` | Short description / excerpt | Often empty |
| `#CHAPO` | Chapeau (introduction paragraph) | May be empty |
| `#PS` | Post-scriptum | May be empty |
| `#DATE` | Publication date | Format with `\|affdate` or `\|affdate_court` |
| `#DATE_REDAC` | Editorial date | |
| `#DATE_MODIF` | Last modification date | |
| `#URL_ARTICLE` | Permalink (uses clean URLs if configured) | Prefer over `#URL_ARTICLE_SPIP` |
| `#LOGO_ARTICLE` | Article logo as `<img>` tag | Empty string if no logo set |
| `#LOGO_ARTICLE_RUBRIQUE` | Article logo, falling back to rubrique logo | Use this for reliable thumbnails |
| `#LANG` | Article language code (fr, en, …) | |
| `#ID_ARTICLE` | Numeric article ID | |
| `#ID_RUBRIQUE` | Rubrique ID of the article | |
| `#STATUT` | Status string (publie, prop, prepa, poubelle) | |

### Rubrique Balises

| Balise | Description |
|--------|-------------|
| `#TITRE` | Rubrique title |
| `#TEXTE` | Rubrique body text |
| `#DESCRIPTIF` | Short description |
| `#URL_RUBRIQUE` | Rubrique URL |
| `#LOGO_RUBRIQUE` | Rubrique logo `<img>` tag |
| `#LOGO_RUBRIQUE_SURVOL` | Hover-state logo |
| `#ID_RUBRIQUE` | Numeric ID |
| `#ID_PARENT` | Parent rubrique ID (0 at root level) |
| `#ID_SECTEUR` | Top-level rubrique ID |
| `#PROFONDEUR` | Depth level (0 = root rubrique) |

### Global / Context Balises

| Balise | Description | Example |
|--------|-------------|---------|
| `#NOM_SITE_SPIP` | Site name from configuration | `<title>#NOM_SITE_SPIP</title>` |
| `#URL_SITE_SPIP` | Site root URL | |
| `#LANG` | Current language code | |
| `#ENV{key}` | URL parameter or INCLURE argument | `#ENV{id_article}` |
| `#SET{key, value}` | Set a page-scoped variable | `[(#SET{count, 0})]` |
| `#GET{key}` | Read a page-scoped variable | `[(#GET{count})]` |
| `#CACHE{seconds}` | Cache this page for N seconds (0 = no cache) | `#CACHE{3600}` |
| `#PAGINATION` | Pagination links (requires `{pagination N}` critère) | `[(#PAGINATION)]` |
| `#SESSION{key}` | Value from the current visitor's session | `#SESSION{id_auteur}` |
| `#LOGO_SITE_SPIP` | Site logo configured in back-office | |
| `#CHEMIN{file}` | Resolved path to a file in SPIP's load path | |

### Document Balises

| Balise | Description |
|--------|-------------|
| `#LOGO_DOCUMENT` | Document thumbnail / preview `<img>` tag |
| `#URL_DOCUMENT` | URL to the document file |
| `#TITRE` | Document title |
| `#EXTENSION` | File extension (jpg, pdf, mp3…) |
| `#MIME_TYPE` | MIME type string |
| `#TAILLE` | File size in bytes |
| `#LARGEUR` / `#HAUTEUR` | Image dimensions (pixels) |
| `#EMBED_DOCUMENT` | Embedded player (for audio/video documents) |

---

## Common Gotchas

**`#URL_ARTICLE` vs `#URL_ARTICLE_SPIP`:** `#URL_ARTICLE` uses clean URLs when configured; `#URL_ARTICLE_SPIP` always outputs `spip.php?article=N`. Always prefer `#URL_ARTICLE` in squelettes.

**Empty `#LOGO_*`:** `#LOGO_ARTICLE` returns an empty string (not null) when no logo is set — `[(#LOGO_ARTICLE)]` correctly suppresses the wrapper. Use `#LOGO_ARTICLE_RUBRIQUE` for an automatic content-to-container fallback.

**`{statut}` default in public context:** BOUCLE ARTICLES without `{statut=...}` shows only published articles in the public front-end. Be explicit with `{statut=publie}` anyway — it makes intent clear and avoids surprises in preview/staging environments.

**`{id_rubrique}` inheritance:** Works automatically inside a BOUCLE_RUBRIQUES parent. On a standalone page where the URL does not carry `id_rubrique`, you must use `{id_rubrique=#ENV{id_rubrique}}` explicitly.

**`#PAGINATION` placement:** Place `[(#PAGINATION)]` outside the BOUCLE body (after `</BOUCLE_name>`) — it renders before the `<//B_name>` zero-result section.
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip-squelettes/references/balises-criteres.md
git commit -m "feat: add spip-squelettes balises-criteres reference"
```

---

### Task 6: Write references/filtres.md

**Files:**
- Create: `/src/spip/skills/spip-squelettes/references/filtres.md`

Note: If this file was migrated from `skills/spip/references/filtres.md` in Task 0, start from that content and expand it with the image and conditional sections below.

- [ ] **Step 1: Fetch primary sources**

Fetch all of these pages before writing. Add any filters found that are not listed below:
- `https://programmer.spip.net/-Les-filtres-`
- `https://programmer.spip.net/Traitement-des-images`
- `https://programmer.spip.net/Les-filtres-de-dates`
- `https://programmer.spip.net/Les-filtres-de-texte`

- [ ] **Step 2: Write filtres.md**

Create `/src/spip/skills/spip-squelettes/references/filtres.md`:

```markdown
# SPIP Filtres Reference (SPIP 4.1+)

Apply filters with `|` inside `[(…)]` wrappers:

```html
[(#TEXTE|typo)]
[(#TITRE|maj|entites_html)]
[(#LOGO_ARTICLE|image_reduire{300,200})]
```

Multiple filters chain left-to-right; each receives the output of the previous one.

## Text Filters

| Filter | Signature | Description | Example |
|--------|-----------|-------------|---------|
| `typo` | `typo($texte)` | French typography: non-breaking spaces before `:;?!`, guillemets | `[(#TITRE|typo)]` |
| `propre` | `propre($texte)` | Full SPIP processing: shortcodes → HTML + typography | `[(#TEXTE|propre)]` |
| `textebrut` | `textebrut($texte)` | Strips all HTML and shortcodes; returns plain text | `[(#TEXTE|textebrut)]` |
| `couper` | `couper($texte, $nb=50)` | Truncates to $nb characters, appends "…" | `[(#TEXTE|textebrut|couper{200})]` |
| `supprimer_tags` | `supprimer_tags($texte)` | Strips HTML tags only; keeps text content | `[(#TEXTE|supprimer_tags)]` |
| `entites_html` | `entites_html($texte)` | Escapes `<`, `>`, `&` to HTML entities | `[(#TITRE|entites_html)]` |
| `maj` | `maj($texte)` | Uppercase | `[(#TITRE|maj)]` |
| `min` | `min($texte)` | Lowercase | `[(#TITRE|min)]` |
| `liens_absolus` | `liens_absolus($texte, $base='')` | Makes relative links absolute | `[(#TEXTE|liens_absolus{#URL_SITE_SPIP})]` |
| `nettoyer_titre_url` | `nettoyer_titre_url($texte)` | Slug-ifies: "Mon Titre !" → "mon-titre" | `[(#TITRE|nettoyer_titre_url)]` |
| `translitteration` | `translitteration($texte)` | Converts accented characters to ASCII | |

## Date Filters

| Filter | Signature | Description | Example |
|--------|-----------|-------------|---------|
| `affdate` | `affdate($date, $format='')` | Formats date; no $format = long locale format ("12 janvier 2026") | `[(#DATE|affdate)]` |
| `affdate_court` | `affdate_court($date)` | Short date (DD/MM/YYYY or locale equivalent) | `[(#DATE|affdate_court)]` |
| `affdate_jourcourt` | `affdate_jourcourt($date)` | Day + month only, no year | `[(#DATE|affdate_jourcourt)]` |
| `affdate_saison` | `affdate_saison($date)` | Season name (Printemps, Été, Automne, Hiver) | |
| `timestamp` | `timestamp($date)` | SPIP date string → Unix timestamp (integer) | `[(#DATE|timestamp)]` |
| `date_iso` | `date_iso($date)` | ISO 8601 format; use in `<time datetime="">` | `[<time datetime="(#DATE|date_iso)">(#DATE|affdate_court)</time>]` |
| `date_relative` | `date_relative($date, $precision=86400)` | "il y a 2 jours" style | `[(#DATE|date_relative{3600})]` |

## URL Filters

| Filter | Signature | Description | Example |
|--------|-----------|-------------|---------|
| `url_absolue` | `url_absolue($url, $base='')` | Makes URL absolute using site base | `[(#URL_ARTICLE|url_absolue)]` |
| `parametre_url` | `parametre_url($url, $key, $val)` | Appends or replaces a query parameter | `[(#URL_ARTICLE|parametre_url{debug,1})]` |

## Conditional Filters

| Filter | Signature | Description | Example |
|--------|-----------|-------------|---------|
| `sinon` | `sinon($val, $default)` | Returns $default when $val is empty | `[(#DESCRIPTIF\|sinon{#TEXTE\|couper{150}})]` |
| `oui` | `oui($val)` | Returns $val if truthy, empty string otherwise | `[(#LOGO_ARTICLE\|oui)]` |
| `non` | `non($val)` | Returns empty if truthy, $val otherwise | |
| `=={val}` | used as `\|=={val}` | True (non-empty) if value equals val; used in CONDITION critère | `{si #ENV{page}\|=={article}}` |

## Image Filters

Image filters operate on `<img>` HTML tags (what `#LOGO_*` balises return), not on URLs directly.

| Filter | Signature | Description | Example |
|--------|-----------|-------------|---------|
| `image_reduire` | `image_reduire($img, $larg=0, $haut=0)` | Resizes proportionally. 0 = unconstrained on that axis. | `[(#LOGO_ARTICLE\|image_reduire{300,0})]` |
| `image_recadre` | `image_recadre($img, $larg, $haut, $mode='center')` | Crops to exact $larg×$haut. $mode: center, top, bottom, left, right | `[(#LOGO_ARTICLE\|image_recadre{300,200})]` |
| `image_passe_partout` | `image_passe_partout($img, $larg, $haut)` | Fills canvas without cropping; adds padding | |
| `image_format` | `image_format($img, $format)` | Converts to jpg / png / gif / webp | `[(#LOGO_ARTICLE\|image_format{webp})]` |
| `image_nb` | `image_nb($img)` | Converts to grayscale | |
| `image_flouter` | `image_flouter($img, $rayon=3)` | Gaussian blur | |
| `image_src` | `image_src($img)` | Extracts the `src` URL from an `<img>` tag | `[(#LOGO_ARTICLE\|image_src)]` |

### Typical Image Pipeline

```html
<!-- 300×200 thumbnail, cropped from center, with rubrique logo as fallback -->
[(#LOGO_ARTICLE_RUBRIQUE|image_reduire{600,0}|image_recadre{300,200,center})]
```

Gotcha: always run `image_reduire` before `image_recadre`. Reducing first prevents SPIP from upscaling a small source image to fill the crop canvas, which would produce a blurry result.

## Number / Size Filters

| Filter | Signature | Description |
|--------|-----------|-------------|
| `nombre_en_toutes_lettres` | `nombre_en_toutes_lettres($n)` | 42 → "quarante-deux" |
| `taille_en_octets` | `taille_en_octets($n)` | 1258291 → "1,2 Mo" |
| `virgule_en_point` | `virgule_en_point($n)` | Normalizes decimal separator to `.` |
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip-squelettes/references/filtres.md
git commit -m "feat: add spip-squelettes filtres reference"
```

---

### Task 7: Write references/avance.md

**Files:**
- Create: `/src/spip/skills/spip-squelettes/references/avance.md`

- [ ] **Step 1: Fetch primary sources**

Fetch all of these pages before writing. Note anything that contradicts the content below (especially ajax behavior which changed between SPIP versions):
- `https://programmer.spip.net/La-balise-INCLURE`
- `https://programmer.spip.net/AJAX-dans-SPIP`
- `https://programmer.spip.net/Les-modeles`
- `https://programmer.spip.net/Les-formulaires`
- `https://programmer.spip.net/-Les-squelettes-`
- `https://programmer.spip.net/La-balise-CACHE`
- `https://programmer.spip.net/la-page-404`

- [ ] **Step 2: Write avance.md**

Create `/src/spip/skills/spip-squelettes/references/avance.md`:

```markdown
# SPIP Squelettes — Advanced Features (SPIP 4.1+)

## INCLURE — Sub-template Fragments

INCLURE embeds another squelette file inline, resolved along SPIP's file load path.

```html
<!-- squelettes/fragments/nav.html -->
<INCLURE{fond=fragments/nav}>

<!-- Optional form: suppresses output entirely if the fragment returns nothing -->
[(#INCLURE{fond=fragments/sidebar})]

<!-- Pass arguments; read them inside the fragment with #ENV -->
<INCLURE{fond=fragments/card, id_article=#ID_ARTICLE, mode=compact}>
<!-- Inside fragments/card.html: #ENV{mode} → "compact" -->
```

**File resolution order:** `squelettes/` → active theme folder → active plugin folders → SPIP core. First match wins.

**Context:** The included fragment inherits the caller's `#ENV`. Additional arguments passed with `{fond=..., key=val}` override or extend it.

### INCLURE with {ajax}

Makes the included block reload asynchronously when pagination or URL arguments change, without a full page refresh. SPIP handles the JS wiring automatically.

```html
<!-- Caller page: this block reloads on its own -->
<INCLURE{fond=fragments/article-list, id_rubrique=#ENV{id_rubrique}}{ajax}>

<!-- squelettes/fragments/article-list.html -->
<BOUCLE_arts(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{pagination 10}{ajax}>
  <a href="#URL_ARTICLE">#TITRE</a>
</BOUCLE_arts>
[(#PAGINATION)]
```

Requirements:
- SPIP's JavaScript must be loaded on the page (included by default in standard squelettes via the JS meta block)
- The `{ajax}` critère on the BOUCLE inside the fragment enables ajax pagination
- `{ajax}` on the INCLURE makes the container block swappable

Limitation: forms inside an ajax-loaded block submit normally (full page POST). Ajax only affects navigation (pagination, URL changes).

---

## Modèles — Inline Content Embeds

Modèles let editors embed formatted blocks in article body text using shortcode syntax. SPIP resolves `<typeN|model_name>` to `squelettes/modeles/model_name.html`.

```
<!-- Editor writes in article text: -->
<img123|right>         → squelettes/modeles/img.html  (id_document=123, align=right)
<doc456|legende>       → squelettes/modeles/doc.html  (id_document=456, legende=oui)
<article789|compact>   → squelettes/modeles/article.html (id_article=789, compact=oui)
```

**Creating a custom modèle `squelettes/modeles/card.html`:**

```html
<!-- Called with <articleN|card> in body text -->
<BOUCLE_card(ARTICLES){id_article}>
  <div class="card">
    [(#LOGO_ARTICLE_RUBRIQUE|image_reduire{200,0})]
    <h3><a href="#URL_ARTICLE">#TITRE</a></h3>
    [(#DESCRIPTIF)]
  </div>
</BOUCLE_card>
```

---

## Native Formulaires

SPIP provides plug-in form balises. Place them anywhere in a squelette — no PHP needed.

| Balise | Form displayed | Context requirement |
|--------|----------------|---------------------|
| `#FORMULAIRE_FORUM` | Comment / forum post form | Requires `id_article` in context |
| `#FORMULAIRE_SIGNATURE` | Petition signature form | Requires `id_article` |
| `#FORMULAIRE_INSCRIPTION` | New account registration | None |
| `#FORMULAIRE_LOGIN` | Login / password form | None |
| `#FORMULAIRE_OUBLI` | Forgotten password (sends email) | None |
| `#FORMULAIRE_RECHERCHE` | Site search form | None |
| `#FORMULAIRE_NOUVEAUTES` | Newsletter / new content subscription | None |

```html
<!-- Login form anywhere -->
[(#FORMULAIRE_LOGIN)]

<!-- Comment form below an article -->
<BOUCLE_art(ARTICLES){id_article}>
  #TEXTE
  [(#FORMULAIRE_FORUM)]
</BOUCLE_art>

<!-- Search box in header -->
[(#FORMULAIRE_RECHERCHE)]
```

Gotcha: `#FORMULAIRE_FORUM` is only displayed when anonymous posting is enabled in SPIP configuration, OR when the visitor is logged in. If neither condition holds, it renders nothing silently.

---

## Squelette Variants — File Precedence

SPIP selects the most specific file for each request. Resolution order (first match wins):

1. `squelettes/article-{id_article}.html` — e.g., `article-42.html` for article #42
2. `squelettes/article-{id_rubrique}.html` — e.g., `article-3.html` for articles in rubrique #3
3. `squelettes/article.html` — default for all articles
4. Plugin-provided squelette
5. SPIP core default

Same pattern applies to `rubrique.html`, `auteur.html`, and any custom page type.

**Custom page types:** `squelettes/mypage.html` is accessible at `?page=mypage` (or `/mypage` with clean URLs). No routing configuration needed.

---

## Custom 404 Page

Create `squelettes/404.html`. SPIP serves it automatically when a URL resolves to no content.

```html
<!-- squelettes/404.html -->
#CACHE{0}  <!-- no cache for error pages -->
<h1>Page introuvable</h1>
<p>Retour à <a href="#URL_SITE_SPIP">l'accueil</a></p>
```

---

## #CACHE — Page Caching

```html
#CACHE{3600}   <!-- Cache this rendered page for 1 hour -->
#CACHE{0}      <!-- No cache: always regenerate (use for dynamic/personalised pages) -->
```

Place `#CACHE{N}` near the top of the squelette. SPIP invalidates the cache automatically when relevant content (articles, rubriques) is published or modified. Use `#CACHE{0}` for pages that show session-dependent content (e.g., a logged-in greeting).

---

## #ENV, #SET, #GET — Variables

```html
<!-- Read a URL parameter or an argument passed by INCLURE -->
#ENV{id_rubrique}
[(#ENV{mode}|sinon{default})]

<!-- Set a page-scoped variable (useful to pass data across loop iterations) -->
[(#SET{ma_variable, valeur})]

<!-- Read it back anywhere on the same page -->
[(#GET{ma_variable})]
```

Scope: `#SET`/`#GET` variables are scoped to the current page render. They do not cross INCLURE boundaries automatically — pass them explicitly as INCLURE arguments if needed.
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip-squelettes/references/avance.md
git commit -m "feat: add spip-squelettes avance reference"
```

---

### Task 8: Write references/exemples.md

**Files:**
- Create: `/src/spip/skills/spip-squelettes/references/exemples.md`

- [ ] **Step 1: Fetch primary sources**

Fetch these pages and adjust examples if they differ from the patterns below:
- `https://programmer.spip.net/Squelette-de-base`
- `https://www.spip.net/fr_rubrique135.html` (navigate to sub-pages for template examples)

- [ ] **Step 2: Write exemples.md**

Create `/src/spip/skills/spip-squelettes/references/exemples.md`:

```markdown
# SPIP Squelettes — Practical Examples (SPIP 4.1+)

## Breadcrumb (fil d'ariane)

```html
<nav aria-label="fil d'ariane">
<BOUCLE_fil(HIERARCHIE)>
  <a href="#URL_RUBRIQUE">#TITRE</a>
  <span aria-hidden="true"> › </span>
</BOUCLE_fil>
<span aria-current="page">#TITRE</span>
</nav>
```

## Navigation Menu — Two-level (no recursion)

```html
<nav>
  <ul>
  <BOUCLE_menu(RUBRIQUES){racine}{par titre}>
    <li>
      <a href="#URL_RUBRIQUE">#TITRE</a>
      <ul>
      <BOUCLE_sous(RUBRIQUES){id_parent}{par titre}>
        <li><a href="#URL_RUBRIQUE">#TITRE</a></li>
      </BOUCLE_sous>
      </ul>
    </li>
  </BOUCLE_menu>
  </ul>
</nav>
```

## Navigation Menu — Full Tree (recursive)

```html
<ul>
<BOUCLE_arbre(RUBRIQUES){id_parent}{par titre}>
  <li>
    <a href="#URL_RUBRIQUE">#TITRE</a>
    <ul><BOUCLE_arbre></ul>
  </li>
</BOUCLE_arbre>
</ul>
```

## Article List with Pagination and Empty State

```html
<B_arts><ul></B_arts>
<BOUCLE_arts(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{pagination 10}>
  <li>
    <a href="#URL_ARTICLE">#TITRE</a>
    <time datetime="[(#DATE|date_iso)]">[(#DATE|affdate_court)]</time>
    [(#DESCRIPTIF|sinon{#TEXTE|textebrut|couper{200}})]
  </li>
</BOUCLE_arts>
<BB_arts></ul></BB_arts>
<//B_arts>
  <p>Aucun article dans cette rubrique.</p>
[(#PAGINATION)]
```

## Featured Article + Remaining List

```html
<!-- Hero: most recent article in full -->
<BOUCLE_vedette(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{limit 1}>
  <article class="hero">
    [(#LOGO_ARTICLE_RUBRIQUE|image_reduire{900,0}|image_recadre{900,400,center})]
    <h2><a href="#URL_ARTICLE">#TITRE</a></h2>
    [(#CHAPO|sinon{#DESCRIPTIF})]
  </article>
</BOUCLE_vedette>

<!-- Grid: next 9 articles, featured one automatically excluded -->
<ul class="grid">
<BOUCLE_reste(ARTICLES){id_rubrique}{statut=publie}{doublons}{par date}{inverse}{limit 9}>
  <li>
    [(#LOGO_ARTICLE_RUBRIQUE|image_reduire{300,0}|image_recadre{300,200,center})]
    <a href="#URL_ARTICLE">#TITRE</a>
  </li>
</BOUCLE_reste>
</ul>
```

## Image Gallery (article attachments)

```html
<div class="gallery">
<BOUCLE_photos(DOCUMENTS){id_article}{extension=jpg|png|gif|webp}{par rang_lien}>
  <figure>
    <a href="#URL_DOCUMENT">
      [(#LOGO_DOCUMENT|image_reduire{400,0}|image_recadre{400,300,center})]
    </a>
    <figcaption>[(#TITRE)]</figcaption>
  </figure>
</BOUCLE_photos>
</div>
```

## Related Articles by Keyword

```html
<section class="related">
  <h3>Articles liés</h3>
  <BOUCLE_mots(MOTS){id_article}>
    <BOUCLE_liés(ARTICLES){id_mot}{statut=publie}{doublons}{limit 5}>
      <a href="#URL_ARTICLE">#TITRE</a>
    </BOUCLE_liés>
  </BOUCLE_mots>
</section>
```

## Archive by Month

```html
<ul>
<BOUCLE_mois(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{par mois}{doublons mois}>
  <li>
    <a href="[(#URL_RUBRIQUE|parametre_url{mois,#DATE|affdate{m}}|parametre_url{annee,#DATE|affdate{Y}})]">
      [(#DATE|affdate_jourcourt)] <!-- displays month name -->
    </a>
  </li>
</BOUCLE_mois>
</ul>
```

## Multi-language Article Switcher

```html
<BOUCLE_trad(ARTICLES){id_trad}{tout}{par lang}>
  <a href="#URL_ARTICLE" lang="#LANG" hreflang="#LANG">#LANG</a>
</BOUCLE_trad>
```

## AJAX-Reloadable List Fragment

```html
<!-- In the main squelette -->
<INCLURE{fond=fragments/article-list, id_rubrique=#ENV{id_rubrique}}{ajax}>

<!-- squelettes/fragments/article-list.html -->
<ul>
<BOUCLE_arts(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{pagination 10}{ajax}>
  <li><a href="#URL_ARTICLE">#TITRE</a></li>
</BOUCLE_arts>
</ul>
[(#PAGINATION)]
```

## Login / Logout Area

```html
<!-- Show welcome bar if logged in -->
<BOUCLE_loggedin(CONDITION){si #SESSION{id_auteur}}>
  <p>Bienvenue, [(#SESSION{nom})] —
    <a href="[(#URL_SITE_SPIP|parametre_url{action,logout})]">Déconnexion</a>
  </p>
</BOUCLE_loggedin>

<!-- Show login form if not logged in -->
<BOUCLE_loggedout(CONDITION){si !#SESSION{id_auteur}}>
  [(#FORMULAIRE_LOGIN)]
</BOUCLE_loggedout>
```

## Site Search Results Page (`squelettes/recherche.html`)

```html
#CACHE{0}
<h1>Résultats pour : [(#ENV{recherche}|textebrut)]</h1>

<B_res><ul></B_res>
<BOUCLE_res(ARTICLES){recherche}{statut=publie}{par points}{inverse}{pagination 20}>
  <li>
    <a href="#URL_ARTICLE">#TITRE</a>
    [(#DESCRIPTIF|sinon{#TEXTE|textebrut|couper{200}})]
  </li>
</BOUCLE_res>
<BB_res></ul></BB_res>
<//B_res>
  <p>Aucun résultat.</p>
[(#PAGINATION)]
```
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip-squelettes/references/exemples.md
git commit -m "feat: add spip-squelettes exemples reference"
```

---

### Task 9: GREEN — Verify with subagent

**Files:**
- Create: `/src/spip/docs/tests/spip-squelettes-green.md`

- [ ] **Step 1: Dispatch verification subagent WITH skill loaded**

Dispatch a fresh subagent with this prompt:

```
Load the spip-squelettes skill, then answer these questions:

1. Write a BOUCLE listing last 5 articles from rubrique #3, newest first.

2. Show #LOGO_ARTICLE conditionally — fall back to #LOGO_RUBRIQUE if empty.

3. What critère enables pagination? What balise renders page links?

4. Write a recursive BOUCLE to display the full rubrique tree as a nested <ul>.

5. How does {doublons} work? Give an example: featured article then a list
   that excludes it.

6. How to INCLURE a fragment and make it reload via AJAX without a full page
   refresh?

7. Apply image_reduire (max 300px wide) then image_recadre (300×200, center)
   to #LOGO_RUBRIQUE. Show the exact filter chain.

8. What balise renders the SPIP login form? Where do you place it?
```

- [ ] **Step 2: Score answers and document**

Create `/src/spip/docs/tests/spip-squelettes-green.md`:

```markdown
# SPIP Squelettes Skill — Verification Test (GREEN)

Date: 2026-04-30

## Subagent Answers (with skill loaded)

[paste exact answers]

## Score

| Q | Correct? | Notes |
|---|---|---|
| 1 | Y/N | |
| 2 | Y/N | |
| 3 | Y/N | |
| 4 | Y/N | |
| 5 | Y/N | |
| 6 | Y/N | |
| 7 | Y/N | |
| 8 | Y/N | |

**Score: X/8** (pass threshold: 7/8)

## Delta vs. Baseline

[Note which answers improved from baseline]

## Remaining gaps (if any)

[List any answers still wrong or incomplete — fix in iteration step]
```

- [ ] **Step 3: Iterate if score < 7/8**

For each question with a wrong or incomplete answer:
1. Identify which reference file is missing the content
2. Add the missing content to that file
3. Commit: `git commit -m "fix: improve [filename] — [what was missing]"`
4. Re-run that specific question with a fresh subagent
5. Repeat until the full suite scores ≥7/8

- [ ] **Step 4: Commit test results**

```bash
cd /src/spip
git add docs/tests/spip-squelettes-green.md
git commit -m "test: spip-squelettes verification (GREEN)"
```

---

### Task 10: Final verification and wrap-up

- [ ] **Step 1: Verify all skill files exist**

```bash
find /src/spip/skills/spip-squelettes -type f | sort
```

Expected output:
```
/src/spip/skills/spip-squelettes/SKILL.md
/src/spip/skills/spip-squelettes/references/avance.md
/src/spip/skills/spip-squelettes/references/balises-criteres.md
/src/spip/skills/spip-squelettes/references/boucles.md
/src/spip/skills/spip-squelettes/references/exemples.md
/src/spip/skills/spip-squelettes/references/filtres.md
```

- [ ] **Step 2: Verify symlink is correct**

```bash
ls -la ~/.claude/skills/spip-squelettes
```

Expected: `~/.claude/skills/spip-squelettes -> /src/spip/skills/spip-squelettes`

- [ ] **Step 3: Verify SKILL.md word count**

```bash
wc -w /src/spip/skills/spip-squelettes/SKILL.md
```

Expected: under 500 words.

- [ ] **Step 4: Final git log check**

```bash
cd /src/spip
git log --oneline -12
```

Expected: 8 commits visible (setup + baseline + SKILL.md + 5 references + green test).

- [ ] **Step 5: Archive or remove the old `spip` skill (if it existed)**

If `/src/spip/skills/spip/` exists from the Task 0 audit:

```bash
# Only run if skills/spip/ exists and its content was migrated into spip-squelettes
git rm -r skills/spip/
git commit -m "chore: remove superseded spip skill (replaced by spip-squelettes)"
```

The skill is complete when GREEN score ≥7/8 and all 6 files are in place.
