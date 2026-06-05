# SPIP Skills Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create two Claude Code skills — `spip` (template reference for web integrators) and `spip-plugins` (plugin dev reference for PHP developers) — targeting SPIP 4.1+.

**Architecture:** Skills are version-controlled in `/src/spip/skills/` and symlinked into `~/.claude/skills/` so Claude Code can load them. Each skill has a concise `SKILL.md` (fast auto-load) plus a `references/` folder for deep lookup. Implementation follows the writing-skills TDD cycle: baseline subagent test (RED) → write files (GREEN) → verify subagent now answers correctly.

**Tech Stack:** Markdown, YAML frontmatter, SPIP 4.1+ (programmer.spip.net)

---

### Task 1: Setup — directory structure and symlinks

**Files:**
- Create: `/src/spip/skills/spip/SKILL.md` (empty placeholder to establish dir)
- Create: `/src/spip/skills/spip-plugins/SKILL.md` (empty placeholder)
- Create: `~/.claude/skills/spip` → symlink
- Create: `~/.claude/skills/spip-plugins` → symlink

- [ ] **Step 1: Create skill directories in repo**

```bash
mkdir -p /src/spip/skills/spip/references
mkdir -p /src/spip/skills/spip-plugins/references
mkdir -p /src/spip/docs/tests
```

- [ ] **Step 2: Symlink into Claude's skills directory**

```bash
mkdir -p ~/.claude/skills
ln -s /src/spip/skills/spip ~/.claude/skills/spip
ln -s /src/spip/skills/spip-plugins ~/.claude/skills/spip-plugins
```

- [ ] **Step 3: Verify symlinks**

```bash
ls -la ~/.claude/skills/
```
Expected: `spip -> /src/spip/skills/spip` and `spip-plugins -> /src/spip/skills/spip-plugins`

- [ ] **Step 4: Commit**

```bash
cd /src/spip
git add skills/
git commit -m "chore: scaffold spip and spip-plugins skill directories"
```

---

### Task 2: RED — Baseline test for `spip` skill

Establish what Claude does WITHOUT the skill. Run this BEFORE writing any skill files.

**Files:**
- Create: `/src/spip/docs/tests/spip-baseline.md`

- [ ] **Step 1: Dispatch a subagent WITHOUT loading any skill**

Dispatch a subagent with this exact prompt (do not reference any skill):

```
Answer these SPIP template questions as precisely as possible:

1. Write a BOUCLE that lists the last 5 published articles from rubrique #3, sorted by date descending.
2. How do you display #TITRE only when it is not empty?
3. What critère enables paginated results in a BOUCLE, and what balise renders the page links?
4. How do you apply the image_reduire filter with a max width of 300px to #LOGO_ARTICLE?
```

- [ ] **Step 2: Record results**

Create `/src/spip/docs/tests/spip-baseline.md` and document:
- Exact answers given
- Whether the BOUCLE syntax was correct (`<BOUCLE_name(TABLE){critères}>...</BOUCLE_name>`)
- Whether `[(#BALISE)]` optional syntax was used or not
- Whether `{pagination N}` + `[(#PAGINATION)]` were both mentioned
- Any non-SPIP patterns used (e.g., PHP, Twig, generic template syntax)

- [ ] **Step 3: Commit baseline**

```bash
cd /src/spip
git add docs/tests/spip-baseline.md
git commit -m "test: spip skill baseline (RED)"
```

---

### Task 3: Write `/src/spip/skills/spip/SKILL.md`

**Files:**
- Create: `/src/spip/skills/spip/SKILL.md`

- [ ] **Step 1: Write SKILL.md**

Create `/src/spip/skills/spip/SKILL.md` with this exact content:

```markdown
---
name: spip
description: Use when working in a SPIP squelettes folder, writing BOUCLE loops,
  balises, critères or filtres, or when the user asks about building a SPIP website
  template. Not for plugin development — use spip-plugins for that.
---

# SPIP — Template Reference (SPIP 4.1+)

SPIP generates pages from **squelettes** — `.html` files mixing static markup with BOUCLE loops and `#BALISE` tags. All template work lives in `squelettes/`. No PHP needed.

## BOUCLE Syntax

```html
<BOUCLE_name(TABLE){critère1}{critère2}>
  ...repeated for each result...
</BOUCLE_name>
<//B_name>  <!--[ optional: shown when loop returns 0 results ]-->
```

**Examples:**

```html
<!-- Last 10 articles in current rubrique, newest first, paginated -->
<BOUCLE_articles(ARTICLES){id_rubrique}{par date}{inverse}{pagination 10}>
  <a href="#URL_ARTICLE">#TITRE</a> — #DATE
</BOUCLE_articles>
[(#PAGINATION)]

<!-- Nested: all top-level rubriques then their articles -->
<BOUCLE_rubriques(RUBRIQUES){racine}>
  <h2>#TITRE</h2>
  <BOUCLE_arts(ARTICLES){id_rubrique}{par titre}>
    <p><a href="#URL_ARTICLE">#TITRE</a></p>
  </BOUCLE_arts>
</BOUCLE_rubriques>
```

## Balises & Conditions

```html
#TITRE                            <!-- direct output -->
[(#TITRE)]                        <!-- optional: nothing if empty -->
[(#TITRE|majuscules)]             <!-- with filter -->
[<b>(#TITRE)</b>]                 <!-- surrounding markup only if tag non-empty -->
[(#LOGO_ARTICLE|image_reduire{200})]
```

## Key Critères

| Critère | Effect |
|---|---|
| `{id_rubrique}` | Articles in current rubrique (inherited from context) |
| `{id_rubrique=3}` | Articles in rubrique #3 |
| `{par titre}` | Sort ascending by title |
| `{par date}{inverse}` | Sort newest first |
| `{limit 5}` | Max 5 results |
| `{pagination 10}` | 10 per page — add `[(#PAGINATION)]` for page links |
| `{statut=publie}` | Published only |
| `{tout}` | All statuses (including drafts) |
| `{lang}` | Current language only |
| `{doublons}` | Skip items already displayed by another loop |

Full reference: `references/balises-criteres.md` · `references/filtres.md`
```

- [ ] **Step 2: Verify word count stays under 500**

```bash
wc -w /src/spip/skills/spip/SKILL.md
```
Expected: under 500 words

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip/SKILL.md
git commit -m "feat: add spip SKILL.md"
```

---

### Task 4: Write `/src/spip/skills/spip/references/balises-criteres.md`

**Files:**
- Create: `/src/spip/skills/spip/references/balises-criteres.md`

- [ ] **Step 1: Fetch latest SPIP documentation for table types and critères**

Fetch `https://programmer.spip.net/-Ecriture-des-squelettes-` and check for any table types or critères not listed below. Add any found to the file.

- [ ] **Step 2: Write balises-criteres.md**

Create `/src/spip/skills/spip/references/balises-criteres.md`:

```markdown
# SPIP Balises & Critères Reference (SPIP 4.1+)

## BOUCLE Table Types

| Alias | Content |
|---|---|
| `ARTICLES` | Articles |
| `RUBRIQUES` | Sections/categories |
| `AUTEURS` | Authors |
| `DOCUMENTS` | Attached files and media |
| `FORUMS` | Forum messages |
| `MOTS` | Keywords |
| `GROUPES_MOTS` | Keyword groups |
| `SYNDICATION` | Syndicated news items |
| `SITES` | Syndicated sites |
| `SIGNATURES` | Petition signatures |
| `REFERENCES` | Bibliographic references |
| `VISITES` | Visit statistics |

Real table name also works: `(spip_articles)` instead of `(ARTICLES)`.

## Critères Catalog

### Filtering

| Critère | Description |
|---|---|
| `{id_article=5}` | Specific article by ID |
| `{id_rubrique}` | In current rubrique (inherited from context) |
| `{id_rubrique=3}` | In rubrique #3 |
| `{id_secteur}` | In current sector |
| `{id_auteur}` | By current author |
| `{id_mot=7}` | Tagged with keyword #7 |
| `{statut=publie}` | Published only |
| `{statut=prop}` | Proposed only |
| `{tout}` | All statuses |
| `{lang}` | Current language |
| `{lang=fr}` | French only |
| `{doublons}` | Exclude items already displayed by any loop |
| `{doublons articles}` | Exclude using named group "articles" |

### Sorting

| Critère | Description |
|---|---|
| `{par titre}` | Alphabetical by title |
| `{par date}` | By publication date |
| `{par date_redac}` | By editorial date |
| `{par date_modif}` | By modification date |
| `{par id_article}` | By ID |
| `{par hasard}` | Random order |
| `{inverse}` | Reverse current sort order |
| `{par num titre}` | Numeric sort on leading number in title (e.g. "3. Mon titre") |

### Pagination & Limit

| Critère | Description |
|---|---|
| `{limit 10}` | First 10 results |
| `{limit 5,10}` | Skip 5, take next 10 |
| `{pagination 10}` | 10 per page — use `[(#PAGINATION)]` to render page links |
| `{debut_articles}` | Use URL parameter `debut_articles` as offset |

### Context & Joins

| Critère | Description |
|---|---|
| `{id_rubrique?}` | Use rubrique from context if available, otherwise all |
| `{0,1}` | Return exactly 1 result without iterating |
| `{strict}` | Strict matching for joins |

## Built-in Balises by Table

### ARTICLES
`#ID_ARTICLE` `#TITRE` `#DESCRIPTIF` `#CHAPO` `#TEXTE` `#PS` `#NOTES`
`#DATE` `#DATE_REDAC` `#DATE_MODIF` `#DATE_NOUVEAUTE`
`#URL_ARTICLE` `#LOGO_ARTICLE` `#LOGO_ARTICLE_NORMAL`
`#ID_RUBRIQUE` `#ID_SECTEUR` `#NOM_SITE` `#URL_SITE`
`#LANG` `#STATUT` `#VU`
`[(#INTRODUCTION)]` — auto-generated from `#CHAPO` or start of `#TEXTE`

### RUBRIQUES
`#ID_RUBRIQUE` `#TITRE` `#DESCRIPTIF` `#TEXTE`
`#URL_RUBRIQUE` `#LOGO_RUBRIQUE` `#LOGO_RUBRIQUE_NORMAL`
`#ID_SECTEUR` `#ID_PARENT` `#LANG` `#STATUT`

### AUTEURS
`#ID_AUTEUR` `#NOM` `#BIO` `#EMAIL`
`#URL_AUTEUR` `#LOGO_AUTEUR` `#STATUT` `#LANG`

### DOCUMENTS
`#ID_DOCUMENT` `#TITRE` `#DESCRIPTIF`
`#FICHIER` `#URL_DOCUMENT` `#MIME_TYPE` `#TAILLE`
`#LARGEUR` `#HAUTEUR` `#EXTENSION` `#DATE`
`#LOGO_DOCUMENT`

### MOTS
`#ID_MOT` `#TITRE` `#TEXTE` `#URL_MOT` `#ID_GROUPE` `#TYPE`

## Loop Patterns

### Alternative section (0 results)

```html
<BOUCLE_arts(ARTICLES){id_rubrique}>
  <li><a href="#URL_ARTICLE">#TITRE</a></li>
</BOUCLE_arts>
<p>Aucun article dans cette rubrique.</p>
<//B_arts>
```

Content between `</BOUCLE_arts>` and `<//B_arts>` shows only when loop returns 0 results.

### Recursive loop (full rubrique tree)

```html
<!-- inc-rubriques.html — called recursively -->
<BOUCLE_sousrub(RUBRIQUES){id_parent}{tout}>
  <li>#TITRE
    <ul><INCLURE{fond=inc-rubriques,id_rubrique=#ID_RUBRIQUE} /></ul>
  </li>
</BOUCLE_sousrub>
```

### Avoiding duplicates across loops

```html
<BOUCLE_vedette(ARTICLES){id_rubrique}{limit 1}>
  <h2>#TITRE</h2>  <!-- featured article -->
</BOUCLE_vedette>

<BOUCLE_reste(ARTICLES){id_rubrique}{doublons}{limit 9}>
  <p>#TITRE</p>   <!-- remaining articles, skips the one above -->
</BOUCLE_reste>
```
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip/references/balises-criteres.md
git commit -m "feat: add spip balises-criteres reference"
```

---

### Task 5: Write `/src/spip/skills/spip/references/filtres.md`

**Files:**
- Create: `/src/spip/skills/spip/references/filtres.md`

- [ ] **Step 1: Fetch filtres documentation**

Fetch `https://programmer.spip.net/-Filtres-` for any additional filtres not listed below and add them.

- [ ] **Step 2: Write filtres.md**

Create `/src/spip/skills/spip/references/filtres.md`:

```markdown
# SPIP Filtres Reference (SPIP 4.1+)

Syntax: `[(#BALISE|filtre)]` or `[(#BALISE|filtre{arg})]`
Chain: `[(#TEXTE|couper{200}|propre)]`

## Text

| Filtre | Description |
|---|---|
| `|majuscules` | UPPERCASE |
| `|minuscules` | lowercase |
| `|ucfirst` | First letter uppercase |
| `|couper{150}` | Truncate to ~150 chars, word-aware |
| `|propre` | Convert SPIP markup to clean HTML |
| `|texte_raccourci` | Short text, strips HTML |
| `|entites_html` | Escape HTML entities |
| `|supprimer_tags` | Strip all HTML tags |
| `|typo` | Apply French typographic rules (non-breaking spaces, guillemets) |
| `|liens_ouverts` | Open external links in new tab |
| `|sinon{Valeur par défaut}` | Output fallback string if balise is empty |
| `|strlen` | Return string length |

## Date

| Filtre | Description |
|---|---|
| `|affdate` | Full localized date: "1er janvier 2024" |
| `|affdate_court` | Short localized date: "01/01/24" |
| `|affdate_mois_annee` | Month and year: "janvier 2024" |
| `|affdate{%d %B %Y}` | Custom strftime format |
| `|date_iso` | ISO 8601: "2024-01-01T00:00:00" |
| `|timestamp` | Unix timestamp integer |
| `|annee` | Year: "2024" |
| `|mois` | Month number: "01" |
| `|jour` | Day number: "01" |
| `|heures` | Hours: "14" |
| `|minutes` | Minutes: "30" |

## Image & Media

| Filtre | Description |
|---|---|
| `|image_reduire{300}` | Resize to max 300px wide or tall (preserves ratio) |
| `|image_reduire{300,200}` | Resize to fit within 300×200px box |
| `|image_passe_partout{300,200}` | Pad/letterbox to exact 300×200px |
| `|image_recadrer{300,200}` | Crop to exact 300×200px (center anchor) |
| `|image_recadrer{300,200,center,top}` | Crop with explicit anchor |
| `|image_nb` | Convert to greyscale |
| `|image_sepia` | Convert to sepia tone |
| `|image_rotation{90}` | Rotate 90° clockwise |
| `|image_flip_vertical` | Flip vertically |
| `|image_flip_horizontal` | Flip horizontally |

## URL

| Filtre | Description |
|---|---|
| `|url_absolue` | Convert relative URL to absolute (with domain) |
| `|attribut_html` | Escape for safe use in HTML attribute value |
| `|parametre_url{cle,valeur}` | Add or replace a URL query parameter |
| `|nettoyer_uri` | Sanitize URI string |

## Logic

| Filtre | Description |
|---|---|
| `|oui` | Output value only if truthy |
| `|non` | Output value only if falsy |
| `|vide` | True (1) if empty |
| `|!vide` | True (1) if not empty |
| `|array_count` | Count array elements |
| `|intval` | Cast to integer |

## Custom Filtres

Define in `squelettes/mes_fonctions.php` (auto-loaded by SPIP, no plugin needed):

```php
// callable as [(#TITRE|mon_filtre)] or [(#TITRE|mon_filtre{arg})]
function filtre_mon_filtre_dist($valeur, $arg = '') {
    return strtoupper($valeur) . $arg;
}
```

Use: `[(#TITRE|mon_filtre{!})]` → "MON TITRE!"
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip/references/filtres.md
git commit -m "feat: add spip filtres reference"
```

---

### Task 6: GREEN — Verify `spip` skill

- [ ] **Step 1: Dispatch subagent WITH skill loaded**

Dispatch a subagent with the `spip` skill loaded. Use these same questions from Task 2:

```
You have access to the spip skill. Answer these SPIP template questions:

1. Write a BOUCLE that lists the last 5 published articles from rubrique #3, sorted by date descending.
2. How do you display #TITRE only when it is not empty?
3. What critère enables paginated results in a BOUCLE, and what balise renders the page links?
4. How do you apply the image_reduire filter with a max width of 300px to #LOGO_ARTICLE?
```

- [ ] **Step 2: Verify each answer**

| Expected | Pass? |
|---|---|
| BOUCLE uses `(ARTICLES){id_rubrique=3}{statut=publie}{par date}{inverse}{limit 5}` | |
| Optional tag uses `[(#TITRE)]` syntax | |
| Mentions both `{pagination 10}` AND `[(#PAGINATION)]` | |
| Filter syntax is `[(#LOGO_ARTICLE\|image_reduire{300})]` | |

If any answer is wrong: identify which file is missing the information, fix it, recommit, and re-run this step.

- [ ] **Step 3: Commit test record**

```bash
cd /src/spip
git add docs/tests/
git commit -m "test: spip skill GREEN verification"
```

---

### Task 7: RED — Baseline test for `spip-plugins` skill

- [ ] **Step 1: Dispatch subagent WITHOUT skill**

```
Answer these SPIP plugin development questions as precisely as possible:

1. Show the minimal valid paquet.xml for a SPIP 4.1+ plugin with prefix "acme".
2. How do you run PHP code every time an article is saved? Show the paquet.xml declaration and the PHP handler function.
3. How do you query all published articles using SPIP's SQL API? Show the exact function call and how to iterate results.
4. What is the SPIP 4.1+ replacement for the deprecated securiser_acces() function?
```

- [ ] **Step 2: Record results**

Create `/src/spip/docs/tests/spip-plugins-baseline.md` and document:
- Whether `paquet.xml` used correct `compatibilite` format `[4.1.0;...]`
- Whether `post_edition` pipeline handler had correct name pattern `acme_post_edition` and returned `$flux`
- Whether SQL query used `sql_allfetsel` / `sql_select` vs raw PDO/mysqli
- Whether `securiser_acces_low_sec()` was named correctly

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add docs/tests/spip-plugins-baseline.md
git commit -m "test: spip-plugins skill baseline (RED)"
```

---

### Task 8: Write `/src/spip/skills/spip-plugins/SKILL.md`

**Files:**
- Create: `/src/spip/skills/spip-plugins/SKILL.md`

- [ ] **Step 1: Write SKILL.md**

Create `/src/spip/skills/spip-plugins/SKILL.md`:

```markdown
---
name: spip-plugins
description: Use when developing a SPIP plugin (paquet.xml present, _pipelines.php,
  spip_* table references in PHP) or when the user asks about plugin architecture,
  pipelines, hooks, or the SPIP PHP/SQL API.
---

# SPIP Plugins — Developer Reference (SPIP 4.1+)

A SPIP plugin is a directory in `plugins/` with a `paquet.xml` manifest. Plugins extend SPIP via pipelines (event hooks), add squelettes, define CVT forms, and version the DB schema via `_administrations.php`.

## Plugin File Map

| File | Purpose |
|---|---|
| `paquet.xml` | Manifest — required |
| `options.php` | Loaded on every page — constants, global config |
| `fonctions.php` | Custom balises/filtres available in squelettes |
| `_pipelines.php` | Pipeline handler functions |
| `_administrations.php` | DB schema creation/upgrades (versioned by `schema=` in paquet.xml) |
| `lang/monplugin_fr.php` | i18n strings |
| `formulaires/mon_form.html` + `.php` | CVT form (template + logic) |
| `squelettes/` | Templates bundled with the plugin |

## Minimal paquet.xml

```xml
<paquet prefix="monplugin"
        categorie="outil"
        version="1.0.0"
        etat="stable"
        compatibilite="[4.1.0;4.99.99]"
        schema="1">
  <nom>Mon Plugin</nom>
  <auteur>Nom Auteur</auteur>
  <licence>GNU/GPL</licence>
  <necessite nom="spip" compatibilite="[4.1.0;]" />
  <pipeline nom="post_edition" inclure="monplugin_pipelines.php" />
</paquet>
```

## Pipeline Pattern

```php
// In _pipelines.php
// Handler name = {prefix}_{pipeline_nom}
function monplugin_post_edition($flux) {
    if ($flux['args']['table'] === 'spip_articles') {
        $id = $flux['args']['id_objet'];
        // act on article #$id after save
    }
    return $flux; // always return $flux
}
```

Common hooks: `pre_edition` `post_edition` `pre_insertion` `post_insertion` `affichage_final` `formulaire_charger` `formulaire_verifier` `formulaire_traiter`

## CVT Form Skeleton

```php
// formulaires/mon_form.php
function formulaires_mon_form_charger_dist() {
    return ['champ' => _request('champ') ?: ''];
}
function formulaires_mon_form_verifier_dist() {
    $erreurs = [];
    if (!_request('champ')) {
        $erreurs['champ'] = _T('monplugin:champ_requis');
    }
    return $erreurs;
}
function formulaires_mon_form_traiter_dist() {
    // sql_insert / sql_updateq here
    return ['editable' => false];
}
```

Full reference: `references/paquet-xml.md` · `references/pipelines-catalog.md` · `references/api.md`
```

- [ ] **Step 2: Verify word count**

```bash
wc -w /src/spip/skills/spip-plugins/SKILL.md
```
Expected: under 500 words

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip-plugins/SKILL.md
git commit -m "feat: add spip-plugins SKILL.md"
```

---

### Task 9: Write `/src/spip/skills/spip-plugins/references/paquet-xml.md`

**Files:**
- Create: `/src/spip/skills/spip-plugins/references/paquet-xml.md`

- [ ] **Step 1: Fetch latest paquet.xml documentation**

Fetch `https://programmer.spip.net/paquet-xml` for any attributes or elements not listed below and add them.

- [ ] **Step 2: Write paquet-xml.md**

Create `/src/spip/skills/spip-plugins/references/paquet-xml.md`:

```markdown
# paquet.xml Reference (SPIP 4.1+)

Required at the root of every plugin directory.

## `<paquet>` Attributes

| Attribute | Required | Values / Notes |
|---|---|---|
| `prefix` | Yes | Unique identifier, lowercase, no spaces (e.g. `monplugin`) |
| `categorie` | Yes | `outil` `contenu` `communication` `navigation` `statistique` `spam` `maintenance` `squelette` `dist` |
| `version` | Yes | Semver string: `1.2.3` |
| `etat` | Yes | `dev` `test` `stable` |
| `compatibilite` | Yes | SPIP version range: `[4.1.0;4.99.99]` or `[4.1.0;]` for open-ended |
| `schema` | No | Integer — increment to trigger `_administrations.php` on next activation |
| `logo` | No | Relative path to logo image (e.g. `monplugin_logo.png`) |
| `documentation` | No | URL to online documentation |
| `nom_fichier_lang` | No | Override default lang file prefix (default: `{prefix}`) |

## Child Elements

### `<nom>`
Human-readable name shown in the plugin manager.
```xml
<nom>Mon Plugin</nom>
```

### `<auteur>`
Repeatable. Optional `lien` attribute for author URL.
```xml
<auteur lien="https://example.com">Jean Dupont</auteur>
```

### `<licence>`
```xml
<licence lien="https://www.gnu.org/licenses/gpl.html">GNU/GPL</licence>
```

### `<necessite>`
Hard dependency — plugin will not activate without it.
```xml
<necessite nom="spip" compatibilite="[4.1.0;]" />
<necessite nom="saisies" compatibilite="[4.0.0;]" />
```

### `<utilise>`
Soft dependency — activates the named plugin if installed, but not required.
```xml
<utilise nom="mots" compatibilite="[3.0.0;]" />
```

### `<pipeline>`
Declares a pipeline hook. `inclure` is the file containing the handler function.
Handler name must be: `{prefix}_{nom}` (e.g. `monplugin_post_edition`).
```xml
<pipeline nom="post_edition"    inclure="monplugin_pipelines.php" />
<pipeline nom="affichage_final" inclure="monplugin_pipelines.php" />
```

### `<credit>`
Third-party library attribution.
```xml
<credit lien="https://library.example.com">SomeLibrary v2.3</credit>
```

## Full Annotated Example

```xml
<paquet prefix="monplugin"
        categorie="outil"
        version="1.2.0"
        etat="stable"
        compatibilite="[4.1.0;4.99.99]"
        schema="3"
        logo="monplugin_logo.png"
        documentation="https://contrib.spip.net/monplugin">

  <nom>Mon Plugin</nom>
  <auteur lien="https://example.com">Jean Dupont</auteur>
  <licence lien="https://www.gnu.org/licenses/gpl.html">GNU/GPL</licence>

  <necessite nom="spip" compatibilite="[4.1.0;]" />
  <necessite nom="saisies" compatibilite="[4.0.0;]" />
  <utilise nom="mots" />

  <pipeline nom="pre_edition"     inclure="monplugin_pipelines.php" />
  <pipeline nom="post_edition"    inclure="monplugin_pipelines.php" />
  <pipeline nom="affichage_final" inclure="monplugin_pipelines.php" />

  <credit lien="https://vendor.example.com">Vendor Lib v1.0</credit>
</paquet>
```
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip-plugins/references/paquet-xml.md
git commit -m "feat: add spip-plugins paquet-xml reference"
```

---

### Task 10: Write `/src/spip/skills/spip-plugins/references/pipelines-catalog.md`

**Files:**
- Create: `/src/spip/skills/spip-plugins/references/pipelines-catalog.md`

- [ ] **Step 1: Fetch pipelines documentation**

Fetch `https://programmer.spip.net/Pipelines` for any pipelines not listed below and add them to the appropriate category.

- [ ] **Step 2: Write pipelines-catalog.md**

Create `/src/spip/skills/spip-plugins/references/pipelines-catalog.md`:

```markdown
# SPIP Pipelines Catalog (SPIP 4.1+)

Declare in `paquet.xml`: `<pipeline nom="name" inclure="file.php" />`
Handler name: `{prefix}_{name}($flux)` — always `return $flux`.

## Content Display

| Pipeline | `$flux['data']` | Use for |
|---|---|---|
| `affichage_final` | Final HTML string of the page | Modify or inject into full page output |
| `affichage_entetes_final` | HTTP headers array | Add/modify response headers |
| `recuperer_fond` | Rendered HTML of a single fond (template) | Modify a specific template's output |

```php
function monplugin_affichage_final($flux) {
    $flux['data'] = str_replace('</body>', '<p>injected</p></body>', $flux['data']);
    return $flux;
}
```

## Database Events

| Pipeline | Key `$flux` keys | Use for |
|---|---|---|
| `pre_insertion` | `$flux['data']` = field array; `$flux['args']['table']` | Modify data before INSERT |
| `post_insertion` | `$flux['args']['id_objet']`; `$flux['args']['table']` | Act after INSERT (send email, create related object) |
| `pre_edition` | `$flux['data']` = changed fields; `$flux['args']['table']` | Modify data before UPDATE |
| `post_edition` | `$flux['args']['id_objet']`; `$flux['args']['table']`; `$flux['args']['action']` | Act after UPDATE |

`$flux['args']['action']` values for `post_edition`: `modifier` (field edit), `instituer` (status change).

```php
function monplugin_post_edition($flux) {
    if ($flux['args']['table'] === 'spip_articles'
        && $flux['args']['action'] === 'instituer') {
        // article status changed — notify, clear cache, etc.
        $id = $flux['args']['id_objet'];
    }
    return $flux;
}
```

## CVT Forms

| Pipeline | `$flux['args']['form']` | Use for |
|---|---|---|
| `formulaire_charger` | Form name string | Add fields to any existing CVT form |
| `formulaire_verifier` | Form name string | Add validation rules to any CVT form |
| `formulaire_traiter` | Form name string | Add processing to any CVT form |

## Admin Interface

| Pipeline | Use for |
|---|---|
| `ajouter_boutons` | Add buttons to admin top navigation bar |
| `ajouter_onglets` | Add tabs to admin object edit pages |
| `affiche_gauche` | Add content to left column in admin |
| `affiche_droite` | Add content to right column in admin |
| `affiche_milieu` | Add content to main area in admin |
| `affiche_enfants` | Add content to child-objects panel |

## Head & Assets

| Pipeline | Use for |
|---|---|
| `insert_head` | Add CSS/JS to public `<head>` |
| `insert_head_css` | Add CSS only to public `<head>` |
| `header_prive` | Add CSS/JS to admin `<head>` |

## Cron & Other

| Pipeline | Use for |
|---|---|
| `taches_generales_cron` | Register background cron tasks |
| `rechercher_objet_type` | Extend SPIP search to custom object types |
| `objet_compte_enfants` | Count child objects for admin display |

## Custom Pipelines

Create your own pipeline callable by other plugins:

```php
// In your plugin code
$result = pipeline('mon_pipeline_custom', $initial_data);
```

Other plugins declare in their `paquet.xml`:
```xml
<pipeline nom="mon_pipeline_custom" inclure="their_pipelines.php" />
```
And define `theirplugin_mon_pipeline_custom($flux)`.
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip-plugins/references/pipelines-catalog.md
git commit -m "feat: add spip-plugins pipelines catalog"
```

---

### Task 11: Write `/src/spip/skills/spip-plugins/references/api.md`

**Files:**
- Create: `/src/spip/skills/spip-plugins/references/api.md`

- [ ] **Step 1: Fetch SQL API documentation**

Fetch `https://programmer.spip.net/-Fonctions-de-l-API-SQL-` for any SQL functions not listed below and add them.

- [ ] **Step 2: Write api.md**

Create `/src/spip/skills/spip-plugins/references/api.md`:

```markdown
# SPIP PHP/SQL API Reference (SPIP 4.1+)

Never use raw `mysqli_*` or PDO directly. Always go through SPIP's SQL abstraction layer.

## SQL API

### `sql_select` — query returning a resource

```php
$res = sql_select(
    $select,   // string or array: 'id_article, titre' or ['id_article', 'titre']
    $from,     // string: 'spip_articles'
    $where,    // string or array: "statut='publie'" or ["statut='publie'", "lang='fr'"]
    $groupby,  // string: '' or 'id_rubrique'
    $orderby,  // string: 'date DESC'
    $limit,    // string: '10' or '5,10'
    $having,   // string: ''
    $serveur   // string: '' for default DB
);
while ($row = sql_fetch($res)) {
    echo $row['titre'];
}
```

### `sql_fetch` — next row from resource

```php
$row = sql_fetch($res); // array or false when exhausted
```

### `sql_allfetsel` — select + fetch all rows at once

```php
$rows = sql_allfetsel(
    'id_article, titre, date',
    'spip_articles',
    "statut='publie'",
    '',         // groupby
    'date DESC',
    '10'        // limit
);
// Returns: [['id_article'=>1,'titre'=>'...'], ...]
```

### `sql_fetsel` — fetch single row

```php
$row = sql_fetsel('titre, texte, date', 'spip_articles', "id_article=$id");
// Returns: ['titre'=>'...', 'texte'=>'...'] or false
```

### `sql_getfetsel` — fetch single field value

```php
$titre = sql_getfetsel('titre', 'spip_articles', "id_article=$id");
// Returns: 'Mon titre' or false
```

### `sql_insert` — INSERT a row

```php
$new_id = sql_insert('spip_articles', [
    'titre'   => 'Nouveau titre',
    'statut'  => 'prepa',
    'lang'    => 'fr',
    'id_rubrique' => 3,
]);
```

### `sql_updateq` — UPDATE with auto-quoting

```php
sql_updateq('spip_articles',
    ['titre' => 'Titre modifié', 'statut' => 'publie'],
    "id_article=$id"
);
```

### `sql_delete` — DELETE rows

```php
sql_delete('spip_articles', "id_article=$id");
```

### `sql_count` — count rows

```php
$n = sql_count(sql_select('id_article', 'spip_articles', "statut='publie'"));
```

### `sql_quote` — quote a value for WHERE clause

```php
$safe = sql_quote($user_input);
$where = "titre=" . sql_quote($titre);
```

### `sql_in` — generate IN(...) clause

```php
$where = sql_in('id_article', [1, 2, 3]); // "id_article IN (1,2,3)"
$where = sql_in('id_article', [1, 2, 3], 'NOT'); // "id_article NOT IN (1,2,3)"
```

## Utility Functions

### `_request($key, $default = '')`

Safe GET+POST accessor. Always use instead of `$_GET`/`$_POST`.

```php
$id    = intval(_request('id_article'));
$titre = _request('titre', 'Sans titre');
```

### `include_spip($fichier)`

Include a SPIP file found via `find_in_path`. Returns true on success.

```php
include_spip('inc/securiser_action');
include_spip('inc/acces'); // required before using securiser_acces_low_sec()
```

### `find_in_path($fichier, $dossier = '', $all = false)`

Locate a file across SPIP's load path (plugins, squelettes, SPIP core).

```php
$path = find_in_path('mes_fonctions.php');
$all  = find_in_path('style.css', 'squelettes/', true); // array of all matches
```

### `pipeline($nom, $valeur)`

Execute a pipeline and return the (possibly modified) value.

```php
$html = pipeline('affichage_final', $html);
```

### `_T($cle)` — translate

```php
echo _T('monplugin:message_confirmation');
// Reads from lang/monplugin_fr.php: $GLOBALS['i18n']['message_confirmation']
```

## Cache Invalidation

```php
include_spip('inc/invalideur');

// Invalidate cache for a specific object
suivre_invalideur("id_article=" . intval($id));

// Invalidate all caches (expensive — use sparingly)
include_spip('inc/invalideur');
lister_invalideurs(); // list current invalideurs
```

## Security

### `securiser_acces_low_sec()` (SPIP 4.1+)

Defined in `inc/acces`. Creates a fixed token for an action/author pair. The URL is accessible even when the author is disconnected — anyone with the URL can trigger the action. Use only for low-security actions.

```php
include_spip('inc/acces');
$token_url = securiser_acces_low_sec($id_auteur, 'mon_action', 'param=val');
```

> `securiser_acces()` from SPIP <4.1 is **deprecated**. A `filtre_securiser_acces_dist()` shim exists in `inc/filtres` for backward compatibility only — do not use it in new code.

### Always use `_request()` over raw superglobals

`_request()` merges GET+POST and applies SPIP input filtering. Direct access to `$_GET`/`$_POST` bypasses this and is a security risk in plugin code.

### CVT forms handle CSRF automatically

SPIP's built-in CVT form system (`formulaires/`) includes CSRF token verification. Use it for all user-facing forms instead of rolling your own nonce logic.
```

- [ ] **Step 3: Commit**

```bash
cd /src/spip
git add skills/spip-plugins/references/api.md
git commit -m "feat: add spip-plugins API reference"
```

---

### Task 12: GREEN — Verify `spip-plugins` skill

- [ ] **Step 1: Dispatch subagent WITH skill loaded**

Use the same questions from Task 7 with the `spip-plugins` skill loaded:

```
You have access to the spip-plugins skill. Answer these SPIP plugin questions:

1. Show the minimal valid paquet.xml for a SPIP 4.1+ plugin with prefix "acme".
2. How do you run PHP code every time an article is saved? Show the paquet.xml declaration and the PHP handler function.
3. How do you query all published articles using SPIP's SQL API? Show the exact function call and how to iterate results.
4. What is the SPIP 4.1+ replacement for the deprecated securiser_acces() function?
```

- [ ] **Step 2: Verify each answer**

| Expected | Pass? |
|---|---|
| `paquet.xml` has `compatibilite="[4.1.0;...]"` | |
| Pipeline declaration: `<pipeline nom="post_edition" inclure="acme_pipelines.php" />` | |
| Handler: `function acme_post_edition($flux)` checking `$flux['args']['table']` and returning `$flux` | |
| SQL query uses `sql_allfetsel` or `sql_select`+`sql_fetch` (not PDO/mysqli) | |
| Names `securiser_acces_low_sec()` and notes it requires `include_spip('inc/acces')` | |

If any answer is wrong: identify the gap, fix the relevant reference file, recommit, and re-run this step.

- [ ] **Step 3: Commit test record**

```bash
cd /src/spip
git add docs/tests/
git commit -m "test: spip-plugins skill GREEN verification"
```
