# SPIP 5 Callouts in Existing Skills — Design Specification

## Goal

Make every existing 4.x skill **say when it stops being right**. Each skill gets a short
"SPIP 5.0" callout listing the breaks that concern its topic and pointing to the 5.0 skill that
owns the details. No 5.0 content is duplicated in the 4.x skills.

**Target: SPIP 5.0.0-beta**, verified against `ecrire` commit `c8e329575` (2026-09-22). Facts below
come from `spip-migration-5` (`catalogue.md`, `squelettes.md`, `audit-outils.md`) and from
`docs/notes/2026-10-17-spip-code-hors-doc.md`.

---

## Why

None of the existing skills mentions SPIP 5. An agent working on a 5.0 site loads `spip-logs` or
`spip-squelettes` (their triggers match), applies 4.x rules, and produces deprecated or broken
code: `spip_log()` with `_LOG_*`, `_request()` in a form, `{fusion}`, spip-cli commands. The
callout makes the agent check the version first and switch skills.

**Dependency:** `spip-migration-5` (PR #25) and `spip5-plugins` (PR #27) are merged; callouts may point to both.
Links to `spip5-instance` are added when that skill exists.

---

## Callout contract

Same shape in every skill, placed right after the core principle / overview in `SKILL.md`:

```markdown
## SPIP 5.0

This skill describes SPIP 4.x. These rules change on SPIP 5.0 — a site is 5.0 if **any** of:
`ecrire/src/SpipVersion.php` declares `VERSION = '5.…'`, `bin/spip` exists, `vendor/spip/framework/` exists,
plugins live in `plugins-dist/spip/`; a plugin targets 5.0 if its `paquet.xml` `compatibilite` starts at 5.0
or its `composer.json` requires `spip/framework`.

- <break 1 → replacement>
- <break 2 → replacement>

Details: `spip-migration-5` (<reference file>).
```

Rules:

- 2 to 5 bullets, each one a concrete symbol or command and its 5.0 replacement;
- how to detect a 5.0 site is always the first line (observable facts, not "if you use SPIP 5"); any single marker
  is enough. Markers checked on 5.0.0-beta (`c8e329575`) and 4.4.23:

  | Marker | 5.0.0-beta | 4.4.23 |
  |---|---|---|
  | `ecrire/src/SpipVersion.php` | yes, `VERSION = '5.0.0-beta'` | absent |
  | `bin/spip` | yes | absent |
  | `vendor/spip/framework/` | yes (root `require`) | absent |
  | `plugins-dist/spip/<plugin>/` | yes | `plugins-dist/<plugin>/` |
  | root `composer.json` `name` | `spip/spip` | `spip/spip` too — **not a marker** |
  | `config/services.php` in a plugin | only plugins with services — **not required** | absent |

  `$GLOBALS['spip_version_branche']` also works at runtime (full version in 5.0, `version_compare()` only);
- no explanation of the 5.0 model; that lives in the 5.0 skills;
- the skill `description` gains "SPIP 4.x" where it is ambiguous, so the right skill is picked.

---

## Content per skill

| Skill | Bullets | Points to |
|---|---|---|
| `spip-logs` | `spip_log($m, 'x.' . _LOG_ERREUR)` → `Spip::logger('x')->error($m)` (deprecated, still works; `Spip` is a global alias); `spip_logger()` deprecated and absent in 4.4; gravités → PSR-3 methods (one example, table left in the catalogue); the 4.x threshold and rotation settings have no effect; dual compat keeps `spip_log()` | `catalogue.md` (levels table, `spip.logger.*`), `spip5-plugins` `services.md` |
| `spip-squelettes` | fix every squelette touched, not only the added code; removed: `</INCLURE>`, `.php` inclusions, logo pseudo-filters `\|right` … `\|fichier` (new forms already in 4.4); `{fusion}` / `{collecte}` → `{groupby}` / `{collate}` (old names deprecated; new ones absent in 4.4); `POUR`, `\|foreach`, `#EMBED_DOCUMENT`, `#LARGEUR_ECRAN` → `#LAYOUT_PRIVE{…}`, `_DIR_RESTREINT_ABS` → `#PARAM{spip.routes.back_office}`; CSS bracket trap; case-sensitive language items | `squelettes.md` |
| `spip-lang` | string 3rd argument of `_T()` / `_L()` → `TypeError` in 5.0 → `['class' => …]` (fix it in every file touched); `_T($k, ['spip_lang' => 'en'])` deprecated → `_T($k, [], ['lang' => 'en'])`, but 4.4 ignores the `lang` option: dual compat uses `lang_select()` or keeps `spip_lang`; language files, `<traduire>`, `lang_select()` unchanged | `catalogue.md` |
| `spip-formulaires` | `_request()` / `set_request()` → `Spip::input()` / `Spip::setInput()` in `charger/verifier/traiter`; whole-environment form `spip_sanitize_from_request($env, '*')` removed → `spip_sanitize_env_from_request()` (single-value form stays); `autosave_clean_value()` deprecated | `catalogue.md` |
| `spip-plugins` | `$GLOBALS['meta']` is a raw mirror, arrays stored as JSON → `unserialize()` returns `false`: read with `lire_config()` (both versions), fix existing helpers in every file touched; `<pipeline>` / `<genie>` / `affichage_final` / `$GLOBALS['html']` unchanged, dual compat keeps them, 5.0-only model in `spip5-plugins`; `compatibilite` `[5.0.0;5.*]` refuses `5.0.0-beta` → `[5.0.0-dev;5.*]` or `[4.4.0;5.*]` | `config-metas.md`, `double-compat.md`, `plugin-modele-5.md`, `spip5-plugins` |
| `spip-testing` | spip-cli is a 4.x tool; on 5.0 use `bin/spip` (`spip:cache:warmup --clear`, `config:lire`); PHP 8.4 for the 5.0 test instance; a test suite for both versions runs twice | `audit-outils.md` |
| `plugin-escal` | install instructions use spip-cli (4.x); Escal compatibility with 5.0 to be checked in its `paquet.xml` before any advice | — |
| `spip-mutualisation-maintenance` | covers 4.2–4.4 farms only; the 5.0 mutualised entry point is experimental upstream; do not apply these procedures to a 5.0 instance | `spip5-instance` when available |

Each bullet is re-checked in the 5.0 source when the callout is written (deprecation markers listed
in `docs/notes/2026-10-17-spip-5-deprecations-inventaire.md`).

---

## Testing (writing-skills)

An edit to a skill needs a failing test first. One eval case per skill, in
`evals/spip5-encadres/evals.json`: a task on a **5.0-only** project where only the 4.x skill is loaded.

The prompt **never names the version**: it must be deduced from the fixture files. Each fixture carries only some
of the markers above (never `config/services.php`), and the markers vary between cases (`bin/spip` in one,
`ecrire/src/SpipVersion.php` in another, a plugin `paquet.xml` with `compatibilite="[5.0.0-dev;5.*]"` in a third).
A case passes only if the answer cites the marker it relied on. "5.0-only" is likewise carried by the fixture
(`compatibilite` lower bound at 5.0), not by the prompt.

GREEN requires the **5.0 API**. A deprecated 4.x form that still runs (`spip_log()`, `{fusion}`, `_request()`,
pipeline function) fails the case, even with a warning. The only accepted exception is a separate dual-compat
variant of the case, whose fixture declares `compatibilite="[4.4.0;5.*]"`: there the GREEN answer is the form
valid on both versions, per `spip-migration-5` `double-compat.md`.

| Skill | Case (version given by the fixture only) | RED (expected without callout) | GREEN |
|---|---|---|---|
| `spip-logs` | log an error in a 5.0 plugin | `spip_log(…, _LOG_ERREUR)` | `Spip::logger('x')->error()` (dual-compat variant: `spip_log()`) |
| `spip-squelettes` | group articles by year on a 5.0 site | `{fusion}` | `{groupby}` (dual-compat variant: `{fusion}`) |
| `spip-lang` | bilingual email subject, body left as is (its `_T()` has a string 3rd argument) | string argument left in place | fixed to `['class' => …]` (dual-compat variant: `lang_select()`, not the `lang` option) |
| `spip-formulaires` | read a posted field in `verifier` | `_request()` | `Spip::input()` (dual-compat variant: `_request()`) |
| `spip-plugins` | banner after `<body>` from a setting read by an existing `unserialize($GLOBALS['meta'][…])` helper; new plugin on a 5.0 site | helper reused as is | helper rewritten with `lire_config()`; `compatibilite` accepting the site's version; `<pipeline>` + function (listener only for 5.0-only, via `spip5-plugins`) |
| `spip-testing` | clear caches before integration tests on 5.0 | `spip cache:vider` | `bin/spip spip:cache:clear` |
| `plugin-escal` | install Escal on a 5.0 site | spip-cli commands | version check first |
| `spip-mutualisation-maintenance` | upgrade a 5.0 farm | 4.x procedure applied | stops and states the limit |

A second run with a **4.4** project (4.4.23 fixture, version again not named in the prompt) checks that the callout does not leak 5.0 code into 4.x work
(no `Spip::` on 4.4). Records in `docs/tests/spip5-encadres-baseline.md` and
`docs/tests/spip5-encadres-green.md`.

---

## Delivery

One branch and one PR per skill (`docs/spip5-encadre-<skill>`), each with its eval case and record,
so they can be reviewed and merged independently. Order: `spip-logs`, `spip-formulaires`,
`spip-squelettes`, `spip-lang` (highest traffic, smallest change), then `spip-plugins`,
`spip-testing`, `plugin-escal`, `spip-mutualisation-maintenance`.
