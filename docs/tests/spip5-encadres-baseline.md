# SPIP 5.0 callouts — Baseline Test (RED)

Spec: `docs/superpowers/specs/2026-10-17-spip5-encadres-design.md`. Cases: `evals/spip5-encadres/evals.json`.

Each case: fresh subagent, the 4.x skill alone (copy taken before the callout), one fixture copied into a neutral
folder (`p1` … `p4`) so the path does not give the version away. The prompt never names the version.
Raw answers: `spip5-encadres-baseline-raw.md`.

## spip-logs (2026-10-18)

| Case | Fixture marker | Answer | Result |
|---|---|---|---|
| 1 — 5.0 only | `compatibilite="[5.0.0-dev;5.*]"` | `spip_log(…, 'acme.' . _LOG_ERREUR)`; advises `define('_LOG_FILTRE_GRAVITE', _LOG_DEBUG)` in `mes_options.php` (no effect in 5.0) | **FAIL** — never reads `compatibilite` |
| 2 — 4.4 + 5.0 | `compatibilite="[4.4.0;5.*]"` | `spip_log(…, 'acme.' . _LOG_ERREUR)` | **PARTIAL** — right code by default, no marker cited |
| 3 — 4.x (control) | `compatibilite="[4.2.0;4.4.*]"` | `spip_log(…, 'acme.' . _LOG_ERREUR)` | PASS |
| 4 — 5.0 site code | `bin/spip` only (no `paquet.xml`), filter in `squelettes/mes_fonctions.php` | `spip_log(…, 'meteo.' . _LOG_ERREUR)` | **FAIL** — `bin/spip` ignored |

Failure pattern: the agent never checks the version; the skill gives it no reason to.
