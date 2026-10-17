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

**Dependency:** PR #25 (`spip-migration-5`) merged. Links to `spip5-plugins` and `spip5-instance`
are added when those skills exist; until then the callouts point to `spip-migration-5` only.

---

## Callout contract

Same shape in every skill, placed right after the core principle / overview in `SKILL.md`:

```markdown
## SPIP 5.0

This skill describes SPIP 4.x. On a 5.0 site (`composer.json` requires `spip/spip` 5.*,
`bin/spip` present, `config/services.php` in plugins), these rules change:

- <break 1 → replacement>
- <break 2 → replacement>

Details: `spip-migration-5` (<reference file>).
```

Rules:

- 2 to 5 bullets, each one a concrete symbol or command and its 5.0 replacement;
- how to detect a 5.0 site is always the first line (observable facts, not "if you use SPIP 5");
- no explanation of the 5.0 model; that lives in the 5.0 skills;
- the skill `description` gains "SPIP 4.x" where it is ambiguous, so the right skill is picked.

---

## Content per skill

| Skill | Bullets | Points to |
|---|---|---|
| `spip-logs` | `spip_log($m, 'x.' . _LOG_ERREUR)` → `Spip::logger('x')->error($m)` (deprecated, still works); `spip_logger()` deprecated and absent in 4.4; level table `_LOG_*` → PSR-3; level set by `spip.logger.max_level` in `config/custom.php`, files in `tmp/log/` | `catalogue.md` (levels table) |
| `spip-squelettes` | `<INCLURE{…}></INCLURE>` and `<INCLURE(x.php)>` → `<INCLURE{…} />`, `.html` only; `{fusion}` / `{collecte}` → `{groupby}` / `{collate}` (old names deprecated; new ones absent in 4.4); `#LARGEUR_ECRAN` → `#LAYOUT_PRIVE{…}`; `_DIR_RESTREINT_ABS` → `#PARAM{spip.routes.back_office}` | `squelettes.md` |
| `spip-lang` | `_T($k, ['spip_lang' => 'en'])` → `_T($k, [], ['lang' => 'en'])`; string 3rd argument of `_T()` / `_L()` removed → `['class' => …]`; language files unchanged | `catalogue.md` |
| `spip-formulaires` | `_request()` / `set_request()` → `Spip::input()` / `Spip::setInput()` in `charger/verifier/traiter`; `spip_sanitize_from_request()` removed → `spip_sanitize_env_from_request()`; `autosave_clean_value()` deprecated | `catalogue.md` |
| `spip-plugins` | pipeline functions still run but the native model is listeners in `config/services.php`; `genie/` → `#[AsCronTask]`; `$GLOBALS['meta']` is a raw mirror → `lire_config()` / `Spip::config()`; `$GLOBALS['spip_pipeline']` in `options.php` ignored | `plugin-modele-5.md`, `config-metas.md` (later `spip5-plugins`) |
| `spip-testing` | spip-cli is a 4.x tool; on 5.0 use `bin/spip` (`spip:cache:warmup --clear`, `config:lire`); PHP 8.4 for the 5.0 test instance; a test suite for both versions runs twice | `audit-outils.md` |
| `plugin-escal` | install instructions use spip-cli (4.x); Escal compatibility with 5.0 to be checked in its `paquet.xml` before any advice | — |
| `spip-mutualisation-maintenance` | covers 4.2–4.4 farms only; the 5.0 mutualised entry point is experimental upstream; do not apply these procedures to a 5.0 instance | `spip5-instance` when available |

Each bullet is re-checked in the 5.0 source when the callout is written (deprecation markers listed
in `docs/notes/2026-10-17-spip-5-deprecations-inventaire.md`).

---

## Testing (writing-skills)

An edit to a skill needs a failing test first. One eval case per skill, in
`evals/spip5-encadres/evals.json`: a task on a **5.0** project where only the 4.x skill is loaded.

| Skill | Case | RED (expected without callout) | GREEN |
|---|---|---|---|
| `spip-logs` | log an error in a 5.0 plugin | `spip_log(…, _LOG_ERREUR)` | `Spip::logger()->error()` or a deprecation warning |
| `spip-squelettes` | group articles by year on a 5.0 site | `{fusion}` | `{groupby}` (or `{fusion}` with the dual-compat reason) |
| `spip-lang` | translate a string in English from PHP | `['spip_lang' => 'en']` | `['lang' => 'en']` as 3rd argument |
| `spip-formulaires` | read a posted field in `verifier` | `_request()` | `Spip::input()` |
| `spip-plugins` | add an `affichage_final` hook on a 5.0 plugin | `_pipelines.php` only | listener, or a stated reason to keep the function |
| `spip-testing` | clear caches before integration tests on 5.0 | `spip cache:vider` | `bin/spip spip:cache:clear` |
| `plugin-escal` | install Escal on a 5.0 site | spip-cli commands | version check first |
| `spip-mutualisation-maintenance` | upgrade a 5.0 farm | 4.x procedure applied | stops and states the limit |

A second run with a **4.4** project checks that the callout does not leak 5.0 code into 4.x work
(no `Spip::` on 4.4). Records in `docs/tests/spip5-encadres-baseline.md` and
`docs/tests/spip5-encadres-green.md`.

---

## Delivery

One branch and one PR per skill (`docs/spip5-encadre-<skill>`), each with its eval case and record,
so they can be reviewed and merged independently. Order: `spip-logs`, `spip-formulaires`,
`spip-squelettes`, `spip-lang` (highest traffic, smallest change), then `spip-plugins`,
`spip-testing`, `plugin-escal`, `spip-mutualisation-maintenance`.
