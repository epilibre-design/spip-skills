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

Every example was run on a disposable 5.0.0-beta instance (`spip/spip` `c8e329575`, `ecrire` `2cd5843f`) with an `acme` plugin:

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

---

## Q3 re-scored with the revised expectations (2026-10-18)

Case 3 now accepts either the native `#[AsCronTask]` task or the beta workaround, never both, the workaround being
framed as temporary for a SPIP core bug. The first answer was not kept, so Q3 was re-run on the skill at `acfc29b`
(raw answer: `spip5-plugins-green-q3-raw.md`).

| Expectation | Result |
|---|---|
| One form for `acme_synchro`, says which and why; workaround framed as temporary for a core bug, `#[AsCronTask]` the target | ✅ delivers the workaround as active code, `SynchroTask` kept out of `src/` meanwhile; "bug du cœur", "à retirer ensuite", migration steps once fixed |
| If A: `#[AsCronTask(name, periode: 86400)]` service, `0` / `> 0`, no `genie/` | ✅ (given as target code, correct) |
| If B: `genie_acme_synchro_dist()` legacy convention, declared by a `TachesGeneralesCronEvent::addTask()` listener | ✅ `1` / `-1`, no `acme_taches_generales_cron`, no `<genie>` |
| Dev disable matches the form | ✅ B: plugin parameter `acme.synchro.enabled` read by the listener, states `spip.cron.*` does not apply; A: `spip.cron.acme_synchro.enabled` |
| `periode` not 0 | ✅ |
| Beta bug flagged with `queue_genie_can_use_service_registry()` | ✅ plus `start (cron service)` in `queue.log` |

**Q3: PASS (6/6).** GREEN stays 8/8.

Gaps reported by the agent, not addressed here: `cron.md` says services are not reachable from `genie_*` while
`services.md` lists `Spip::service()` for public services; the workaround listener is shown without its class;
precedence of a site's `config/custom.php` over a plugin parameter; how to set `APP_ENV=dev` for web requests; retry
behaviour after a failure (delay, limit) for both forms; HTTP client in 5.0 (`recuperer_url()` used from 4.x knowledge).
