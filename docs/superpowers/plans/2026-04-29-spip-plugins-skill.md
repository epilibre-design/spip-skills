# spip-plugins Skill — Conception Spec

> **For agentic workers:** This is the authoritative design spec for the `spip-plugins` skill.
> Before editing any reference file or the SKILL.md, read this document in full.
> Design decisions below override intuition — don't re-litigate them.

**Goal:** Produce a Claude skill that lets a PHP developer build SPIP 4.1+ plugins correctly without consulting the incomplete/outdated spip.net documentation.

**Audience:** PHP developers who know SPIP's editorial model (rubriques, articles, auteurs) but need authoritative API reference for plugin development. Not frontend integrators (→ `spip` skill).

**Source of truth:** `/src/spip/spip` — composer-installed SPIP 4.4 core + plugins-dist. All examples, signatures, and conventions must be extracted from this codebase, not from spip.net.

---

## Skill identity

```yaml
name: spip-plugins
triggers:
  - paquet.xml present in the project
  - files matching *_pipelines.php
  - spip_* table references in PHP
  - user asks about pipelines, hooks, plugin architecture, SPIP PHP/SQL API
not-for:
  - squelettes / template work → use the `spip` skill
  - SPIP internals / compiler → use the `spip-expert` skill
```

---

## Language policy

All prose (descriptions, explanations, table headers, section titles) is in **English**.

SPIP-specific terms are **always kept in their original French/SPIP form**, never translated:

| Keep as-is | Never write |
|---|---|
| `boucle` | ~~"loop"~~ (when referring to SPIP's `<BOUCLE_>`) |
| `squelette` | ~~"template"~~ (in SPIP context) |
| `pipeline` | ~~"hook"~~ |
| `balise` | ~~"tag"~~ |
| `critère` | ~~"criterion/filter"~~ |
| `filtre` | ~~"filter function"~~ |
| `formulaire CVT` | ~~"CVT form"~~ |
| `objet éditorial` | ~~"editorial object"~~ |
| `genie` | ~~"cron task"~~ |
| `rubrique` | ~~"section"~~ |
| `paquet.xml` | (always verbatim) |

Code examples keep their original comments even when in French, **unless** the comment was written for this skill (then use English).

---

## File structure

```
spip-plugins/
├── SKILL.md                          # entry point loaded by Claude — glossary + workflow index
└── references/
    ├── paquet-xml.md                 # Bloc 1 ✅
    ├── arborescence.md               # Bloc 1 ✅
    ├── pipelines.md                  # Bloc 2 ✅ — large, TOC present
    ├── cvt-formulaires.md            # Bloc 3 ✅
    ├── sql-api.md                    # Bloc 4 ✅
    ├── declarer-table.md             # Bloc 4 ✅
    ├── declarer-objet.md             # Bloc 4 ✅
    ├── balises-filtres-criteres.md   # Bloc 4 ✅
    ├── i18n.md                       # Bloc 4 ✅
    ├── cycle-de-vie.md               # Bloc 5 ✅
    ├── actions.md                    # Bloc 6 ✅
    ├── autorisations.md              # Bloc 6 ✅
    ├── prive-objets.md               # Bloc 7 ✅
    ├── exec-generique.md             # Bloc 7 ✅
    ├── cache.md                      # Bloc 7 ✅
    ├── multilinguisme.md             # Bloc 7 ✅
    ├── queue-jobs.md                 # Bloc 7 ✅
    └── upload-bigup.md               # Bloc 7 ✅
```

**Size constraint:** no reference file beyond ~350 lines without a TOC at the top.

---

## Extraction methodology

Each reference file is produced by reading real source files, not by recalling general knowledge:

1. `find` the relevant files in `/src/spip/spip` (excluding `vendor/`)
2. Cross-reference multiple examples to identify invariants vs. plugin-specific choices
3. Synthesise: write the rule, then show a minimal real example with file path in a comment
4. Never invent examples — if a real pattern can't be found in the corpus, say so

Corpus hierarchy (prefer in this order):
1. SPIP core (`ecrire/`, `ecrire/public/`, `ecrire/inc/`)
2. plugins-dist with schema + administrations (forum, medias, mots, revisions, urls…)
3. plugins-dist without schema (aide, compresseur, tw…)

---

## Progress tracker

### Bloc 1 — Structure ✅

- [x] `references/paquet-xml.md` — all elements and attributes with observed values
- [x] `references/arborescence.md` — directory layout, loading order, special root files
- [x] `SKILL.md` stub — frontmatter + glossary table

### Bloc 2 — Pipelines ✅

- [x] Catalogue exhaustif with TOC
- [x] For each pipeline: signature, `$flux` shape (what enters, what must exit), example
- [x] Group by domain: édition, affichage, déclaration, formulaires, authentification, cron
- [x] Cover: pipelines déclarés vides (extension points) vs. pipelines avec implémentation core
- [x] Source: `ecrire/paquet.xml` (master list) + cross-ref each plugin's usage
- Note: 824 lines — TOC present, compliant with size rule

### Bloc 3 — CVT formulaires ✅

- [x] `charger` / `verifier` / `traiter` signatures and contracts
- [x] `$flux` shape for the formulaire pipelines (`formulaire_charger`, `formulaire_verifier`, `formulaire_traiter`)
- [x] Multi-step formulaires (`cvt_multietapes`)
- [x] autosave pattern (`cvt_autosave`)
- [x] File: `inc/cvt_multietapes.php`, `inc/cvt_autosave.php` in core; `formulaires/` across plugins-dist
- [x] Real example: `formulaires/forum.php` (forum plugin)

### Bloc 4 — Auxiliaires ✅

- [x] **`sql-api.md`** — `sql_select`, `sql_insertq`, `sql_updateq`, `sql_delete`, `sql_alter`, `sql_drop_table`; transaction helpers; `sql_quote`; when to use `objet_modifier()` vs raw SQL
- [x] **`declarer-table.md`** — `declarer_tables_principales`, `declarer_tables_auxiliaires`, `declarer_tables_interfaces`; field/key structure; `$interfaces` sub-arrays
- [x] **`declarer-objet.md`** — `declarer_tables_objets_sql`; objet éditorial descriptor keys; `objet_modifier`, `objet_inserer`, `objet_instituer`
- [x] **`balises-filtres-criteres.md`** — `balise_X_dist(Champ $p)` signature; `Champ` object API; `critere_X_dist` signature; `Boucle` mutation; filtre registration in `_fonctions.php` / `spip_matrice`
- [x] **`i18n.md`** — `_T('module:cle')`, `_L()`, `lang_select()`; `lang/module_fr.php` format; paquet-module lang file; `<traduire>` in paquet.xml

### Bloc 5 — Cycle de vie ✅

- [x] **`cycle-de-vie.md`** — `_administrations.php` in depth: `maj_plugin()`, `maj_tables()`, migration step format; `_vider_tables()`; when `schema` bumps are required; `<install>` element in paquet.xml

### Rédaction finale SKILL.md ✅

- [x] Workflow section: when to load which reference
- [x] Quick-start: minimal plugin skeleton (paquet.xml + 3 files) — corrected to `<paquet>` element
- [x] Decision tree: "I want to do X → read Y" — now includes actions.md and autorisations.md
- [x] 161 lines — well under 300 line limit

### Bloc 6 — Actions & Autorisations ✅

- [x] **`actions.md`** — `action_nom_dist()` anatomy; `securiser_action` verify + generate; `generer_action_auteur`, `redirige_action_auteur`, `redirige_action_post`; `#URL_ACTION_AUTEUR` balise; redirect pattern; invariants
- [x] **`autorisations.md`** — `autoriser()` API; lookup chain (8-step with `$type`, 4-step without); `autoriser_*_dist` naming; built-in statut values; `faire` catalogue; `#AUTORISER` balise; `autoriser_exception`; `autoriser_defaut_dist` default rule

### Bloc 7 — Private UI, Cache, Multilinguisme, Queue, Bigup ✅

- [x] **`prive-objets.md`** — `prive/objets/` hierarchy (liste, infos, contenu, editer); fallback resolution; balises available per context; what SPIP provides vs. what the plugin must create
- [x] **`exec-generique.md`** — `?exec=mon_objets` / `?exec=mon_objet` out-of-the-box; echafaudage sources; what is free vs. what requires custom squelettes; `url_voir` / `url_edit` descriptor keys; invariants
- [x] **`cache.md`** — `#CACHE{n}`, `#CACHE{0}`, `statique`/`cache-client` modifiers; `_DUREE_CACHE_DEFAUT`, `_NO_CACHE`, `_VAR_NOCACHE`, `spip_interdire_cache`; invalidation via `suivre_invalideur()`; session-keyed caches; `$derniere_modif_invalide`
- [x] **`multilinguisme.md`** — `lang` + `id_trad` columns; translation group mechanics; `action_referencer_traduction_dist()`; `{lang}`, `{id_trad}`, `{traduction}` critères; Polyglot compatibility
- [x] **`queue-jobs.md`** — `queue_add_job()` full signature; `spip_jobs` schema; `_JQ_SCHEDULED`/`_JQ_PENDING`; `queue_link_job()`; execution model; fallback to synchronous; periodic tasks via `<genie>`
- [x] **`upload-bigup.md`** — `_bigup_rechercher_fichiers` in `charger()`; `#SAISIE_FICHIER` vs `#BIGUP_TOKEN`; `$_FILES` in `verifier()`; file move in `traiter()`; pipeline flow; multi-form scoping

### Corrections post-review ✅

- [x] Fixed `<plugin>` → `<paquet>` in `cycle-de-vie.md` and `SKILL.md` quick-start (was generating invalid XML)
- [x] `editable='non'` now documents that it restricts `objet_modifier()` to `champs_editables`, not just UI
- [x] `cvt-formulaires.md` traiter() now documents no-rollback behaviour and shows SQL transaction pattern
- [x] Prefix standardised to `monplugin` throughout SKILL.md (was mixing `myplugin`/`monplugin`)

---

## Quality bar per reference file

A reference file is **done** when:

1. Every function/pipeline/element has: exact signature, what it receives, what it must return
2. At least one real example with source path in a comment
3. All invariants (always true) separated from conventions (common practice)
4. Edge cases documented (e.g. `schema` without `_administrations.php` → nothing called)
5. Cross-references to other reference files where relevant

---

## Decisions log

| Date | Decision | Rationale |
|---|---|---|
| 2026-04-29 | English prose, French SPIP terms | Skill triggered by English queries; SPIP terms are proper nouns with no adequate translation |
| 2026-04-29 | Extract from source, not spip.net | spip.net doc is incomplete and sometimes wrong for 4.x |
| 2026-04-29 | SKILL.md glossary as first section | Readers need the term mapping before any reference makes sense |
| 2026-04-29 | `pipelines.md` gets its own TOC | Catalogue will exceed 350 lines; TOC mandatory per size constraint |
| 2026-04-29 | `<pipeline action="">` documented as extension point | Non-obvious: many devs assume empty action = bug |
| 2026-04-29 | Root element is `<paquet>`, not `<plugin>`; `<nom>` is a child element | Confirmed from forum/paquet.xml — `<plugin>` was a pre-4.x form |
| 2026-04-29 | `editable='non'` restricts `objet_modifier()`, not just the UI | Source: `editer_objet.php:128` — `champs_editables` from descriptor filters the write list |
