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

| Case | RED | GREEN | Notes |
|---|---|---|---|
| 1 — 5.0 only | FAIL | **PASS (4/4)** | cites `compatibilite="[5.0.0-dev;5.*]"`, `Spip::logger('acme')->error()`, no `spip_log()` / `spip_logger()`, `tmp/log/acme.log`; settings via `spip.logger.*` |
| 2 — 4.4 + 5.0 | PARTIAL | **PASS (4/4)** | cites `[4.4.0;5.*]`, keeps `spip_log()`, explains why not `Spip::logger()` / `spip_logger()` |
| 3 — 4.x (no leak) | PASS | **PASS (3/3)** | cites `[4.2.0;4.4.*]`, `spip_log()` only; 5.0 mentioned as a future note, no 5.0 code |
