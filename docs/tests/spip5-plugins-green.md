# spip5-plugins Skill — GREEN Test

Date: 2026-10-17

Fresh subagent restricted to `skills/spip5-plugins/` (SKILL.md + references, Read tool only), same 8 prompts as the
baseline (`docs/tests/spip5-plugins-baseline.md`).

| Q | Baseline | With skill | Notes |
|---|---|---|---|
| 1 Minimal plugin | FAIL (1/6) | **PASS (6/6)** | `[5.0.0-dev;5.*]`, no `<pipeline>`, PSR-4, `services.php`, `AffichageFinalEvent` + `appendToBody()`, warmup + `spip:pipeline:listeners` |
| 2 `pre_edition` | FAIL (1/5) | **PASS (5/5)** | `PreEditionEvent`, `getSqlTable()` + `getAction()`, `getSubject()`/`setSubject()`, warns about the doc's `getTable()` |
| 3 Daily task | FAIL (1/6) | **PASS (6/6)** | `#[AsCronTask]`, `0`/`1`, `spip.cron.acme_synchro.enabled` in `config/custom.php` under `dev`; states the beta queue bug and gives the check and the workaround |
| 4 Logger + config | FAIL (0/5) | **PASS (5/5)** | `#[Autowire(service: 'spip.acme.logger')]`, `#[Autowire(service: 'spip.config')]`, channel via `spip.logger_registry`, warns that a plain type breaks the container |
| 5 Rate limit 429 | FAIL (0/5) | **PASS (5/5)** | `GuardRuleInterface`, `#[AsGuard(priority: 50)]`, `tooManyRequests(60)`, context only, priorities explained |
| 6 Private page | FAIL (2/4) | **PASS (4/4)** | `config/menus.php` under `menu_edition`, `prive/squelettes/contenu/acme_outils.html`, language key, no `<menu>` |
| 7 Listener not called | FAIL (1/5) | **PASS (5/5)** | full checklist, warmup, `spip:pipeline:listeners`, page cache, no spip-cli |
| 8 `$GLOBALS['meta']` pressure | FAIL (2/4) | **PASS (4/4)** | refuses with the verified `'[30]'` mirror (`(int) '[30]'` = `0`), injection in three lines, cast, no manual decode |

**GREEN: 8/8.**

## REFACTOR

| Gap seen in the GREEN answers | Change |
|---|---|
| Guard example called an undefined `incrementer()`: the agent had to write it | `guard.md`: complete, verified method |
| Agent invented a way to disable the legacy cron workaround and flagged it as unverified | `cron.md`: `spip.cron.<name>.*` does not apply to the workaround; skip `addTask()` on a plugin parameter |

## Verification of the skill's examples

Every example was run on a disposable 5.0.0-beta instance (`ecrire` `c8e329575`) with an `acme` plugin:

- `<!-- acme -->` before `</body>` on a served page; `insert_head` output visible after `spip:cache:clear --all`;
- `pre_edition`: `objet_modifier('article', 1, ['titre' => 'éléphant rose'])` stores `ÉLÉPHANT ROSE`;
- Guard: `200` ×30 then `429` with `Retry-After: 60`; other pages unaffected;
- injection: `Importeur` built with both `#[Autowire]` arguments, `error()` written to `tmp/log/acme.log`; plain
  `LoggerInterface`, `LoggerRegistry`, `ConfigRepositoryInterface`, `ConfigManagerInterface` fail to autowire;
  `RequestStack`, `Inputs`, `PlatformInterface`, `SpipDirectoriesInterface` autowire; `#[Autowire(param: …)]` works;
- cron: `spip.cron.acme_synchro.enabled = false` under `APP_ENV=dev` removes the task from the registry; the queue
  returns `false` for `service:` jobs (core `optimiser` included); the legacy `genie/` + `taches_generales_cron`
  listener workaround runs (`result = 1`);
- menu: `acme_outils` listed under `menu_edition` for an administrator, page rendered with its language string;
- `plugin_version_compatible()` on 5.0.0-beta: `[5.0.0;5.*]` false, `[5.0.0-dev;5.*]` true, `[4.4.0;5.*]` true;
- meta mirror: after `Spip::configs()->set('acme_seuil', 30)`, API `30`, mirror and SQL `'[30]'`.
