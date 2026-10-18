# spip5-instance Skill — GREEN Test

Date: 2026-10-19

Fresh subagent per case, restricted to `skills/spip5-instance/` (SKILL.md + references, Read tool only), same 8
prompts as the baseline (`docs/tests/spip5-instance-baseline.md`), skill at `6d55713`.

| Q | Baseline | With skill | Notes |
|---|---|---|---|
| 1 Fresh install | FAIL (1/6) | **PASS (6/6)** | PHP 8.4 for the web SAPI, `create-project --no-dev --repository=… 5.0.x-dev` + `composer update`, web installer, writable directories, sensitive-path check, Apache `RedirectMatch` / nginx block, beta stated; flags the Debian package names as not from the skill |
| 2 Upgrade 4.4 → 5.0 | FAIL (5/7) | **PASS (7/7)** | inventory loop, option-file `mysqldump`, side by side, `plugins-dist/spip/`, Guard, warmup, first webmaster visit, rollback with the database |
| 3 Debug logs in dev | FAIL (0/5) | **PASS (5/5)** | `$container->env() === 'dev'` + `spip.logger.max_level`, `APP_ENV=dev` in `.env.local`, `-e dev` warmup and dump, `_LOG_FILTRE_GRAVITE` without effect |
| 4 Cache outside the web root | FAIL (0/5) | **PASS (5/5)** | both beta traps, relative `spip.dirs.tmp` + `SPIP_CACHE_DIR`, warmup, HTTP checks, rollback, web server alternative |
| 5 White page after upgrade | FAIL (3/6) | **PASS (6/6)** | logs first, evidence → cause → fix table, fixes proposed with backup |
| 6 Plugin activated, nothing happens | FAIL (2/5) | **PASS (5/5)** | `admin_plugin`, `var/state/plugins.php`, page cache, then warmup / listeners; no invented command |
| 7 `ecrire/mes_options.php` pressure | FAIL (2/4) | **PASS (4/4)** | refuses `ecrire/`, `config/mes_options.php`, `_DIR_*` / `_NOM_*` warning, `config/custom.php` for `spip.*` |
| 8 "In production now" pressure | PASS (5/5) | **PASS (5/5)** | refuses, read-only checks, option-file dump, rehearsal, rollback; 4.4.28 named as the stable option |

**GREEN: 8/8 (43/43 expectations).**

## REFACTOR

| Gap seen in the GREEN answers | Change |
|---|---|
| Cases 2 and 8: "un vhost de recette (ou le vhost réel)" on MySQL — the rehearsal would run on the production database, which the first `ecrire/` hit changes | `upgrade-4-4.md`: the copied `connect.php` points to production; load the dump into a separate database and point the rehearsal's `connect.php` to it before any hit |
| Case 4: expected `spip:info` value after the move not given | `configuration.md`: `_DIR_TMP` `../site-tmp/`, `spip.dirs.cache` absolute (run on 2026-10-19) |
| Case 7: no verified way to check a constant, no `mes_options.php` example | `configuration.md`: minimal file, `_FILE_OPTIONS` in `spip:info`, the test squelette used in the verification |
| Case 1: Debian packages and extra extensions (`gd`) not covered | `prerequis.md`: stated as not covered (not verified) |
| Cases 2 and 7: the agents noticed that `AGENTS.md` says 4.4.24 is the latest 4.4 | the skill keeps 4.4.28 (`spip_loader.api`, 2026-10-18); `AGENTS.md` is outdated |
| Case 2: SVP updates of `plugins/auto/` on 5.0 not covered | out of scope for this version (not verified) |
| Re-run case 7: a test squelette in a new `squelettes/` is not found until the path cache is purged | `configuration.md`: `bin/spip spip:cache:clear --all` after creating `squelettes/` (measured during the verification) |
| Re-run case 8: the agent wrote the `mariadb` client, not in the skill | `upgrade-4-4.md`: only the MySQL 8.0 clients were run; `mariadb-dump` / `mariadb` named as untested aliases |
| Re-run case 2: the agent invented `grep _SPIP_VERSION ecrire/inc_version.php` | `SKILL.md`: `grep spip_version_branche ecrire/inc_version.php` (the variable read on 4.4.21) |

Re-run after the first four changes (cases 2, 4, 7, 8, fresh subagents): **4/4 PASS** (7/7, 5/5, 4/4, 5/5). Cases 2
and 8 now load the dump into a separate database for the rehearsal and point `connect.php` back to production at the
switch; case 4 gives `_DIR_TMP` `../site-tmp/` as the check; case 7 uses the test squelette. The last three changes
are wording fixes on points the re-runs exposed; they were not re-run.
