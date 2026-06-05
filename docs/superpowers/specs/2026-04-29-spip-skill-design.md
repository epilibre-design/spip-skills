# SPIP Skill Design

## Goal

Create two Claude Code skills targeting distinct SPIP audiences:

- **`spip`** — for web integrators and designers working in `/squelettes`. Non-technical audience: people building dynamic websites with SPIP's template engine without writing PHP.
- **`spip-plugins`** — for PHP developers building SPIP plugins. Technical audience (~5% of SPIP users): core-dev work with paquet.xml, pipelines, and the PHP/SQL API.

**Target: SPIP 4.1+.**

---

## Skill 1: `spip`

### Audience

Non-technical web integrators who work primarily inside a `/squelettes` folder. They know HTML/CSS and understand content hierarchy, but don't write PHP.

### Trigger Conditions

Auto-triggers when Claude sees:
- `.html` files containing `<BOUCLE_` syntax
- Files in a `squelettes/` directory
- User invokes `/spip` explicitly

### File Structure

```
~/.claude/skills/spip/
  SKILL.md                     # Overview + quick reference + core examples
  references/
    balises-criteres.md        # All BOUCLE table types, critères catalog, built-in balises
    filtres.md                 # Built-in filtres with signatures and examples
```

### SKILL.md Frontmatter

```yaml
---
name: spip
description: Use when working in a SPIP squelettes folder, writing BOUCLE loops,
  balises, critères or filtres, or when the user asks about building a SPIP website
  template. Not for plugin development — use spip-plugins for that.
---
```

### SKILL.md Body Sections

1. **Overview** — SPIP generates pages from squelettes (HTML files mixing static markup with BOUCLE loops and `#BALISE` tags). Key directory: `squelettes/`. No PHP knowledge needed for template work.

2. **BOUCLE Quick Reference** — Syntax, 2–3 real examples (articles in a rubrique, paginated list, nested loop), and a pointer to `references/balises-criteres.md` for the full catalog.

3. **Balise & Filtre Quick Reference** — Most-used balises (`#TITRE`, `#TEXTE`, `#URL_ARTICLE`, `#DATE`, `#LOGO_ARTICLE`), conditional syntax (`[(#BALISE|filtre)]`, `[texte(#BALISE)texte]`), filter chaining, and a pointer to `references/filtres.md`.

4. **Reference Pointers** — "For full X reference, read `references/X.md`."

### references/balises-criteres.md

- All BOUCLE table types with descriptions (ARTICLES, RUBRIQUES, DOCUMENTS, AUTEURS, FORUMS, MOTS, SYNDICATION, etc.)
- Complete critères catalog: filtering (`{id_rubrique}`, `{statut}`), sorting (`{par champ}`, `{inverse}`), pagination (`{limit n}`, `{debut_articles}`), joins between tables
- Built-in balises per table type with descriptions
- Nested and recursive loop patterns

### references/filtres.md

- Built-in filtres catalog with signatures and examples: text (`|majuscules`, `|couper{n}`, `|texte_raccourci`), date (`|affdate`, `|affdate_court`), image (`|image_reduire{w,h}`, `|image_passe_partout`), URL (`|url_absolue`), etc.
- Filter chaining syntax
- Writing a custom filtre (one-liner: define `function filtre_nom_dist($val) {}` in `mes_fonctions.php`)

---

## Skill 2: `spip-plugins`

### Audience

PHP developers building SPIP plugins. They write PHP, understand hooks/events, and need precise API references.

### Trigger Conditions

Auto-triggers when Claude sees:
- `paquet.xml` at project root or in a plugin subdirectory
- PHP files with `_pipelines.php` naming convention
- References to `spip_articles`, `spip_rubriques`, or other `spip_*` tables in PHP
- User invokes `/spip-plugins` explicitly

### File Structure

```
~/.claude/skills/spip-plugins/
  SKILL.md                     # Plugin anatomy + pipeline quick ref + core examples
  references/
    paquet-xml.md              # Full paquet.xml schema with all attributes/elements
    pipelines-catalog.md       # All built-in pipelines grouped by category
    api.md                     # SQL API, utility functions, security, cache
```

### SKILL.md Frontmatter

```yaml
---
name: spip-plugins
description: Use when developing a SPIP plugin (paquet.xml present, _pipelines.php,
  spip_* table references in PHP) or when the user asks about plugin architecture,
  pipelines, hooks, or the SPIP PHP/SQL API.
---
```

### SKILL.md Body Sections

1. **Overview** — A SPIP plugin is a directory in `plugins/` with a `paquet.xml` manifest. Plugins extend SPIP via pipelines (hooks), add squelettes, define formulaires, and modify the DB schema via `_administrations.php`.

2. **Plugin Anatomy** — Inline conventional file map:
   - `paquet.xml` — manifest (required)
   - `options.php` — loaded on every page (constants, global config)
   - `fonctions.php` — custom balises/filtres for squelettes
   - `_pipelines.php` — pipeline handler functions
   - `_administrations.php` — DB schema versioning
   - `lang/` — i18n strings
   - `formulaires/` — CVT forms (Charger/Vérifier/Traiter)
   - `squelettes/` — templates bundled with plugin

3. **Pipeline Quick Reference** — Declare in `paquet.xml`, write handler, 3 most common hooks (`pre_edition`, `post_edition`, `affichage_final`) with inline examples.

4. **CVT Forms Quick Reference** — `charger()`, `verifier()`, `traiter()` function signatures + minimal example.

5. **Reference Pointers** — "For full X reference, read `references/X.md`."

### references/paquet-xml.md

- Full `<paquet>` element with all attributes (`prefix`, `categorie`, `version`, `etat`, `compatibilite`, `schema`, `logo`, `documentation`)
- All child elements: `<nom>`, `<auteur>`, `<licence>`, `<necessite>`, `<utilise>`, `<pipeline>`, `<credit>`
- Annotated complete example

### references/pipelines-catalog.md

- All built-in SPIP pipelines grouped by category:
  - **Content display:** `affichage_final`, `affichage_entetes_final`, `recuperer_fond`
  - **Admin interface:** `ajouter_boutons`, `ajouter_onglets`, `affiche_gauche`, `affiche_droite`, `affiche_milieu`
  - **Forms:** `formulaire_charger`, `formulaire_traiter`, `formulaire_verifier`
  - **DB events:** `pre_insertion`, `post_insertion`, `pre_edition`, `post_edition`
  - Others
- How to declare and call a custom pipeline

### references/api.md

- **SQL API:** `sql_select`, `sql_fetch`, `sql_allfetsel`, `sql_insert`, `sql_updateq`, `sql_delete` — signatures + examples
- **Utility functions:** `_request()`, `include_spip()`, `find_in_path()`, `pipeline()`
- **Cache:** `spip_clear_cache()`, cache invalidation patterns
- **Security:** `securiser_acces_low_sec()` (SPIP 4.1+, defined in `inc/acces`; replaces deprecated `securiser_acces()`), `_request()` vs raw superglobals

---

## Success Criteria

- `spip`: a web integrator can ask about any template concept (BOUCLE syntax, available critères, filtre for resizing images) and get a correct answer without PHP/plugin noise
- `spip-plugins`: a PHP developer can ask about paquet.xml structure, pipeline hooks, or SQL API and get precise SPIP 4.1+ answers
- Both SKILL.md files stay under 500 words for fast auto-load
- Reference files provide deep lookup without burdening every conversation
