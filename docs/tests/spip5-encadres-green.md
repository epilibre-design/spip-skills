# SPIP 5.0 callouts — GREEN Test

Same cases and protocol as `spip5-encadres-baseline.md`, with the callout in the skill. The subagent reads only the
4.x skill: the callout must be enough on its own. Raw answers: `spip5-encadres-green-raw.md`.

## spip-logs (2026-10-18)

Callout facts checked in the 5.0.0-beta source (`spip/spip` `c8e329575`, which locks `spip/ecrire` `2cd5843f`,
`spip/framework` `d0e8c97e`): `#[\Deprecated(since: '5.0')]` on `spip_log()` and `trigger_deprecation()` in
`spip_logger()` (`ecrire/bootstrap/inc/logging.php`), level table in the same file, `spip.logger.*` defaults in
`config/spip/logger.php` (`max_level` = `notice`), `_LOG_FILTRE_GRAVITE` defined as a read-only mirror of
`spip.logger.max_level` (`ecrire/inc_version.php`), `_MAX_LOG` defined but never read, `$nombre_de_logs` /
`$taille_des_logs` gone. `spip_logger()` absent from 4.4.22.

Three iterations:

- **v1**: full level table and `spip.logger.*` parameters in the callout. Cases 1–3 pass.
- **v2** (review: the spec forbids copying 5.0 content): one level example, a one-line "no effect" warning for the 4.x
  settings, details in `spip-migration-5` `catalogue.md`. Case 4 added (`bin/spip` as the only marker). Cases 1–4
  pass, but cases 1 and 4 are unsure whether `Spip` needs a `use` (case 4 suggests "the import matching your
  install").
- **v3**: `Spip` is a global alias of `Spip\Framework\Spip` (`class_alias` in `spip/framework`
  `bootstrap/inc/facade.php`, Composer `files` autoload), stated in the first bullet. Cases 1 and 4 re-run: both say no
  `use` is needed.

| Case | RED | GREEN (v3; v2 for cases 2–3) | Notes |
|---|---|---|---|
| 1 — 5.0 only | FAIL | **PASS (4/4)** | cites `compatibilite="[5.0.0-dev;5.*]"`, `Spip::logger('acme')->error()`, no `spip_log()` / `spip_logger()`, `tmp/log/acme.log` |
| 2 — 4.4 + 5.0 | PARTIAL | **PASS (4/4)** | cites `[4.4.0;5.*]`, keeps `spip_log()`, explains why not `Spip::logger()` / `spip_logger()` |
| 3 — 4.x (no leak) | PASS | **PASS (3/3)** | cites `[4.2.0;4.4.*]`, `spip_log()` only; 5.0 as a future note, no 5.0 code |
| 4 — 5.0 site code | FAIL | **PASS (4/4)** | cites `bin/spip`, `Spip::logger('meteo')->error()`, `tmp/log/meteo.log`, warns not to set `_LOG_FILTRE_GRAVITE` |

## spip-formulaires (2026-10-18)

Callout facts checked on the local 5.0.0-beta instance (`spip/spip` `c8e329575`, `spip/ecrire` `2cd5843f`):
`_request()` and `set_request()` call `trigger_deprecation('spip', '5.0', …)` and delegate to `Spip::input()` /
`Spip::setInput()` (`ecrire/bootstrap/inc/request.php`; the `#[\Deprecated]` attribute on `_request()` is commented
out); `Spip::input()` reads the query then the request bag (`spip-league/http` `Inputs::get()`);
`spip_sanitize_from_request()` keeps its single-value form, the `'*'` / whole-environment form goes to
`spip_sanitize_env_from_request()` (already in 4.4, where `'*'` is deprecated since 4.4); `autosave_clean_value()`
calls `trigger_deprecation()` and delegates to `Spip\Pipeline\Listener\CvtAutoSaveListener::cleanValue()`. The
language keys used by the answers (`info_obligatoire`, `info_email_invalide`, `form_email_non_valide`) exist in
5.0.0-beta and 4.4.22.

Two iterations:

- **v1**: cases 5–7 pass.
- **v2** (review): the callout adds that `_request()` converts UTF-8 values of an AJAX request to a non-UTF-8 site
  charset (`importer_charset()`), which `Spip::input()` does not. Case 5 expectations tightened: the existing
  `_request()` in `traiter()` must be migrated in the delivered file, or explicitly flagged. Cases 5 and 6 re-run (v1
  case 5 also migrated `traiter()`, so it passes the tightened grid too); case 6 checks that the new sentence does
  not push dual-compat code to `Spip::input()`.

| Case | RED | GREEN (v2; v1 for case 7) | Notes |
|---|---|---|---|
| 5 — 5.0 site code | FAIL | **PASS (5/5)** | cites `VERSION = '5.0.0-beta'`, `Spip::input('email')`, no `use`; `traiter()` moved to `Spip::input()` too |
| 6 — 4.4 + 5.0 | PARTIAL | **PASS (4/4)** | cites `[4.4.0;5.*]`, keeps `_request()`, explains why not `Spip::input()` |
| 7 — 4.x (no leak) | PASS | **PASS (3/3)** | cites `[4.2.0;4.4.*]`, `_request()` only |

## spip-squelettes (2026-10-18)

Callout facts checked on the local 5.0.0-beta instance and its `ecrire/CHANGELOG.md`: `#[\Deprecated(since: '5.0')]`
on `critere_fusion_dist()`, `critere_fusion_supprimer_dist()`, `critere_collecte_dist()`, which delegate to
`{groupby}`, `{groupby_supprimer}`, `{collate}` (`ecrire/public/criteres.php`, same code as 4.4 `{fusion}`); removed:
`<INCLURE(fichier.php)>`, `fond=fichier.php`, `</INCLURE>` (#26), logo pseudo-filters `|left` `|right` `|center`
`|bottom` `|top` `|lien` `|fichier` (spip/spip!5631; `phraser_vieux_logos()` is gone from `normaliser.php`), `POUR`,
`|foreach`, `#EMBED_DOCUMENT`, `#LARGEUR_ECRAN` (spip/spip#5560); brackets in optional parts (spip/spip#3637);
case-sensitive language items (spip/spip#2536). In 4.4.22: `{groupby}` / `{collate}` absent; `#LOGO_X{right}`,
`#LOGO_X**`, `<INCLURE … />`, `#LAYOUT_PRIVE`, `#PARAM` present.

| Case | RED | GREEN | Notes |
|---|---|---|---|
| 8 — 5.0 site | FAIL (1/4) | **PASS (4/4)** | cites `vendor/spip/framework/`, `{groupby id_rubrique}`, fixes `{right}` and both `<INCLURE … />` |
| 9 — 4.4 + 5.0 | FAIL (1/4) | **PASS (4/4)** | cites `[4.4.0;5.*]`, keeps `{fusion}` (no `{groupby}` in 4.4), fixes `{right}` and `<INCLURE … />` |
| 10 — 4.x (no leak) | PASS | **PASS (2/2)** | cites `[4.2.0;4.4.*]`, `{fusion}`, file left as is; 5.0 changes listed as a future note |
