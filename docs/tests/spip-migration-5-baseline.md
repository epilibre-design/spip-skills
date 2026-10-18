# spip-migration-5 Skill — Baseline Test (RED)

Date: 2026-10-17

Fresh subagent, no tools, no skill: answers from training knowledge only, for the 8 prompts of
`evals/spip-migration-5/evals.json`. Scored against the eval expectations, checked in SPIP 5.0.0-beta
(`spip/spip` `c8e329575`, `ecrire` `2cd5843f`) and SPIP 4.4.23.

---

## Q1: pipeline `affichage_final` → listener

- Uses `SpipLeague\Bridge\Pipeline\Attribute\AsPipelineListener`: ❌ — uses Symfony `#[AsEventListener]`
- Types with `Spip\Framework\Pipeline\Event\AffichageFinalEvent`: ❌ — invents `Spip\Event\InsertHeadEvent`, switches pipeline to `insert_head`, invents `$event->append()`
- Provides `config/services.php` (autowire + autoconfigure + load): ❌ — no services file at all
- PSR-4 autoload in plugin `composer.json`: ✅
- `<pipeline>` no longer needed: ✅
- Mentions container recompilation: ✅ (vaguely, defers to Q8)
- Result: **FAIL** (3/6) — the produced code would never be registered.

## Q2: constants and helpers, 5.0 only

- `_IS_CLI` → `Spip::platform()->isCli()` or `PHP_SAPI`: ✅ (`PHP_SAPI`)
- `_ESPACE_PRIVE` → `Spip::isBackOffice()`: ⚠️ `test_espace_prive()` (valid, not the 5.0 idiom)
- `_DIR_RESTREINT_ABS` → `Spip::param('spip.routes.back_office')`: ⚠️ `generer_url_ecrire()` (acceptable)
- `_ROOT_RACINE` → `Spip::directories()->getProjectDir()`: ❌ — invents parameter `spip.dirs.root`
- `spip_log` → `Spip::logger('acme')->error()`: ❌ — `spip_logger()`, itself deprecated in 5.0
- `$GLOBALS['meta']` → `Spip::config()`: ✅ (`lire_config`, still supported)
- `param()` → `Spip::param()`: ❌ — keeps `param()`, "not sure what replaces it"
- Result: **FAIL** — keeps two deprecated helpers, invents a parameter, ignores the `Spip` facade entirely.

## Q3: squelette

- CSS `a[href] … url(#…)` ambiguity with optional parts: ❌ — "still works", suggests `#CHEMIN`
- `{fusion}` → `{groupby}`: ❌ — claims SQL functions are no longer accepted, rewrites with `|unique`
- `</INCLURE>` → `/>`: ✅
- `[(#LOGO_ARTICLE|left)]` → `[(#LOGO_ARTICLE{left})]`: ❌ — proposes `|inserer_attribut{class,…}`
- `POUR` → `DATA {source tableau, …}`: ✅ (`source table`, valid alias)
- Language keys case-sensitive: ✅
- Result: **FAIL** (3/6)

## Q4: meta mirror trap

- Values stored as JSON (`"[150]"`) on writes through the config API: ❌ — unknown
- `$GLOBALS['meta']` is a raw, non-unserialized mirror: ❌ — claims the key is empty or already decoded
- Fix with `Spip::config()` / `lire_config()`: ✅
- No manual decode on the mirror: ✅
- Result: **FAIL** — right fix, wrong diagnosis (the user cannot reason about other keys).

## Q5: cron

- `#[AsCronTask(name: 'acme_purge', periode: 3600)]`: ❌ — invents a `TachesGeneralesCronEvent::add()` listener
- Task class loaded via `config/services.php`: ❌
- Return-code convention change (0 = success, > 0 = failure): ❌ — explicitly keeps `return 1; // > 0 : succès`
- Does not keep `return 1`: ❌
- Removes `taches_generales_cron` / `genie/` file: ❌ — keeps the genie file
- Result: **FAIL** (0/5) — `return 1` in a 5.0 service task is treated as a failure and re-planned immediately
  (`queue_close_job()`, `ecrire/inc/queue.php`).

## Q6: dual 4.4 / 5.0 compatibility

- Legacy pipeline functions + `<pipeline>` keep working in 5.0: ✅ (with doubt)
- `test_espace_prive()`: ✅
- `PHP_SAPI === 'cli'`: ✅
- `generer_objet_url()`: ✅
- `Spip::` facade absent in 4.4: ❌ — not mentioned
- `lire_config()` not deprecated: ✅
- Compatibility range in `paquet.xml`: ✅
- Extra error: recommends `spip_logger()` "available in 4.4" — **it does not exist in 4.4** (fatal error) and is deprecated in 5.0.
  Also suggests PHP 8.2 (5.0 requires 8.4).
- Result: **PARTIAL** — good strategy, one fatal recommendation.

## Q7: tooled audit

- Both markers `trigger_deprecation(...)` and `#[\Deprecated]`: ❌ — only `@deprecated` / `trigger_deprecation`
- grep from a catalogue of removed symbols: ✅ (short list from memory)
- `bin/spip spip:pipeline:listeners`: ❌
- `var_mode=debug_js`: ❌
- PHP 8.4 minimum: ❌
- Result: **FAIL** (1/5) — generic PHP tooling, nothing SPIP 5 specific; Rector rules package unknown (`spip-league/rector`).

## Q8: CLI

- `bin/spip`, not spip-cli: ❌ — `bin/console` / `vendor/bin/spip`
- `spip:cache:warmup --clear`: ❌ — `cache:clear`
- `spip:pipeline:listeners`: ❌ — `debug:event-dispatcher` (Symfony framework command, not available)
- `config/services.php` + PSR-4: ⚠️ partially ("or the service is declared in the config")
- Result: **FAIL**

---

## Patterns observed

1. **Symfony full-stack reflexes** applied to SPIP: `#[AsEventListener]`, `bin/console`, `debug:event-dispatcher`, `cache:clear`.
2. **Invented SPIP 5 names**: `Spip\Event\*`, `$event->append()`, `spip.dirs.root`, `TachesGeneralesCronEvent::add()`.
3. **Unknown `Spip::` facade** — falls back on helpers deprecated in 5.0 (`param()`, `spip_logger()`), and recommends
   `spip_logger()` for 4.4 where it does not exist.
4. **Legacy semantics carried over silently**: cron `return 1`, `{fusion}`, logo pseudo-filters.
5. **No knowledge of stored-value changes** (JSON config, raw mirror).
6. Agent flags its own uncertainty (`[incertain]`) but still produces code that would not run.
