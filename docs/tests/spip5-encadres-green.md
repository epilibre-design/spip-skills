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
