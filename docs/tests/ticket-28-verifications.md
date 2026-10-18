# Ticket #28 — verifications

Date: 2026-10-18

Each result says whether it comes from a **run** on an instance or a **source read**; "How" names the script or the
file read.

Instances:

- SPIP 5.0.0-beta, `/src/jetables/v5.0-dev`: `spip/spip` `c8e329575`, `spip/ecrire` `26bfe470` (2026-10-07),
  `spip/framework` `d0e8c97e` — upstream heads on 2026-10-18, `composer outdated 'spip/*'` empty. PHP 8.4.25,
  SQLite.
- SPIP 4.4.21, `/src/jetables/v4.4.21` (runtime checks); `spip/ecrire` tags `4.4.22` and `4.4.28` (source reads).
- Disposable `acme` plugin activated on the 5.0 instance, removed afterwards.

Earlier verifications of `spip5-plugins` and `spip-migration-5` ran on `spip/spip` `c8e329575` with the lock of
that commit, `ecrire` `2cd5843f`; the instance's lock was moved to `26bfe470` on 2026-10-18 at 12:26. The skills now
say `spip/spip` `c8e329575` (`ecrire` `2cd5843f`) instead of `ecrire` `c8e329575`.

## spip5-plugins

| Question | Result | How |
|---|---|---|
| Can a `genie_*()` call a plugin service? | Yes if the service is public (`$services->set(Importeur::class)->public()`); a private one throws "removed or inlined" and leaves the job pending — closed by the first `queue_update_next_job_time()` once older than 180 s, with result `0`: replanned at recovery + period. **Run**: pending `acme_mort` job (`genie/`, period 3600) dated 200 s back, no other pending job; after the call, job dated now + 3600 s, priority 0, message in `tmp/log/job_mort.log` | `inc_genie_dist(['acme_synchro' => 0])`, both cases |
| Full workaround listener with `#[Autowire(param: …)]` | Works; `TachesGeneralesCronEvent` is `@deprecated` (PHPDoc only), PHPStan flags it | warmup, `spip:pipeline:listeners`, job scheduled |
| Site `config/custom.php` vs plugin `config/services.php` parameter | The site wins: loaded after the plugins (`SpipContainerFactory::buildForCompilation()`) | `acme.precedence` read by a listener: `site` |
| `$container->env()` | `'prod'` / `'dev'` in `config/custom.php`; **`null`** in a plugin's `config/services.php` (`PluginSymfonyServicesCollector` creates the loader without an environment) | parameter set from `env()`, printed by a listener |
| `APP_ENV=dev` for the web | `.env.local` at the root: public and `ecrire/` in `dev`, `container.dev.php` compiled on the first hit. A process variable is overridden by `.env.dist` unless it is in `$_SERVER` (`variables_order=EGPCS` → `dev`) | `acme_options.php` logging `kernel.environment` per request |
| Failed task | Legacy `-1`: replanned at once, priority `-1`, `-2`, `-3`, no limit, `$t` becomes `1`. `return -(time() - 3600 + 600)` delays by 600 s. Services: same code path with `> 0` (not run, queue bug) | `queue_schedule()` in separate processes |
| `recuperer_url()` / HttpClient | `recuperer_url()` works (status 200); no `symfony/http-client`, no PSR-18 client | script on the instance |
| Queue bug on `26bfe470` | Still present (`function_exists('app')` in `ecrire/inc/queue.php`) | source read |

## spip-migration-5

| Question | Result | How |
|---|---|---|
| `Spip::param('spip.dirs.tmp')` vs `getTmpDir()` | `tmp/` (raw, relative to the site dir; other `spip.dirs.*` differ: `project`, `etc`, `cache` are absolute) vs `/src/jetables/v5.0-dev/tmp/` (absolute, trailing slash); `relative()->getTmpDir()` = `_DIR_TMP` = `tmp/` from the root | script |
| `Spip::logger('acme')` without declaration | Channel created, `tmp/log/zorglub.log` written; `info()` dropped (level `notice`) | script |
| `$t` in `__invoke()` | Passed as first positional argument (`queue_start_cron_service_job()`, `$service->{$method}(...$args)`, args `[$last]`) | source read |
| `appendToBody()` without `</body>` | Appends at the end: the comment lands after `</rss>` on `spip.php?page=backend`; `isHtmlPage()` false there | listener + curl |
| PHPStan | With `phpstan-deprecation-rules`, level 1, scanning the 5.0 instance: `spip_log()`, `getRootDir()`, removed functions (`function.notFound`), removed constants (`constant.notFound`, not at level 0), `TachesGeneralesCronEvent`. Not `_request()` nor `Kernel\param()` (`trigger_deprecation()` only) | PHPStan 2.3.1 on test files |
| Rector | `spip-league/rector` `dev-main` `4f8dcb2` (2025-03-06): `SPIP_50` set empty; `UP_TO_SPIP_50` renames `generer_url_entite_absolue(…, 'distant')` to `generer_objet_url_absolue(…, 'distant')` (wrong: 5th is `$public`) and applies `ReadOnlyClassRector` | Rector 2.7.0 dry run |
| `"spip/framework": "^5.x-dev"` | Kept: declared by every 5.0 plugins-dist | `plugins-dist/spip/*/composer.json` |
| `_T($k, [], ['lang' => 'en'])` on 4.4 | Ignored: `Accueil` instead of `Home` on 4.4.21; source of 4.4.22 and 4.4.28 reads only `spip_lang`. `lang_select('en')` gives `Home` on 4.4.21 and 5.0 | script on both instances |

## plugin-escal

Escal `5.6.2` (`de508fb`, 2026-10-15): `compatibilite="[4.1.0;4.*]"`, schema `1.0.17`, 107 `inclusions/inc-*.html`
(same as 5.6.0). Diff 5.6.0 → 5.6.2: contact form sending (From = webmaster, visitor in `Reply-To`), Dictionnaires
tooltip. Also found: `contactmail` is the "show the visitor's e-mail field" switch, not the recipient (`destinataire`).

## spip-testing

`_chemin()` + `recuperer_fond()` on 4.4.21, outside any test harness: with an absolute directory,
`find_in_path()` is `false` and `recuperer_fond()` returns `''`; with the same directory relative to the cwd, both
work. Cause: `find_in_path()` checks `getCwd() . '/' . $entry`. The integration bootstrap of `howto-test.md` passed
`dirname(__DIR__)` (absolute): fixed to `'../../../'`.
