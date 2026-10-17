# spip-migration-5 Skill — GREEN Test

Date: 2026-10-17

Fresh subagent restricted to `skills/spip-migration-5/` (SKILL.md + references), same 8 prompts as the baseline
(`docs/tests/spip-migration-5-baseline.md`).

| Q | Baseline | With skill | Notes |
|---|---|---|---|
| 1 Pipeline → listener | FAIL (3/6) | **PASS (6/6)** | `AsPipelineListener`, `AffichageFinalEvent`, `services.php`, PSR-4, `<pipeline>` removed, warmup |
| 2 Constants & helpers | FAIL | **PASS (7/7)** | façade `Spip::` everywhere, `getProjectDir()`, `Spip::logger()->error()` |
| 3 Squelette | FAIL (3/6) | **PASS (6/6)** | CSS trap, `{groupby}`, `/>`, `{left}`, `DATA`, case |
| 4 Meta mirror | FAIL | **PASS (4/4)** | JSON `"[150]"`, raw mirror, no manual decode |
| 5 Cron | FAIL (0/5) | **PASS (5/5)** | `#[AsCronTask]`, **`return 0`**, convention change stated |
| 6 Dual 4.4/5.0 | PARTIAL | **PASS (7/7)** | façade absent in 4.4, `spip_logger()` absent in 4.4, keep `{fusion}` |
| 7 Tooled audit | FAIL (1/5) | **PASS (5/5)** | both markers, `spip:pipeline:listeners`, `debug_js`, PHP 8.4 |
| 8 CLI | FAIL | **PASS (4/4)** | `bin/spip`, not spip-cli / `bin/console` |

## Gaps reported by the agent → REFACTOR (verified in 5.0 source)

| Gap | Fix |
|---|---|
| `Spip::config()` return type on legacy keys | legacy `"150"` → `string`, JSON `"[150]"` → `int` (`ConfigPathAccessor::readSimpleKey`); example now casts `(int)` |
| Anything to declare in `paquet.xml` for `services.php`? | no: `PluginSymfonyServicesCollector` loads `config/services.php` of active plugins |
| `spip.routes.back_office` value | `'ecrire/'` (relative, trailing slash); `?exec=` URLs → `generer_url_ecrire()` |
| `getProjectDir()` trailing slash | yes (`trailingSlash()`) |
| `appendToBody()` / `addScript()` behaviour | before `</body>` else appended; `addScript(string $src, string $type = 'text/javascript', array $attributes = [])` |
| `affichage_final` vs `insert_head` for a script | guidance added |
| Procedural function inside a cron task | `include_spip()` or move into the class |
| `generer_objet_*` signatures | same arguments as the removed family |

Not addressed (would need a running instance): sample output of `bin/spip spip:pipeline:listeners`.
