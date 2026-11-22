# SPIP 4.4.23 → 4.4.31 — changelog audit against the skills

Date: 2026-11-22. Sources: `spip/ecrire` `CHANGELOG.md` at tag `4.4.31` (105 entries for 4.4.23–4.4.31),
`spip/prive` `CHANGELOG.md` at `1.1.10` (1.1.5–1.1.10), and the `CHANGELOG.md` of the plugins-dist packages
between their 4.4.22 and 4.4.31 tags (`plugins-dist.json`). Evidence details are in
`spip-4.4.31-verifications.md`.

Action: `edit` (a passage is wrong or incomplete), `add` (new fact to document), `none` (with the reason).
Tasks 4–12 of `docs/superpowers/plans/2026-11-22-skills-4-4-31.md` implement the `edit`/`add` rows.

## Rows with an action

| Version | Entry | Skill | Passage | Action | Evidence |
|---|---|---|---|---|---|
| 4.4.26 | securite#4931 generic per-type autorisations need the `_faire` suffix | spip-plugins | `autorisations.md:70-89` lookup chain (steps 3–4 say `autoriser_{type}[_dist]`) | edit | source read `ecrire/inc/autoriser.php:199-216` |
| 4.4.31 | spip/spip#6106 generic `modifierliens` autorisation; prive 1.1.10 `editer_liens` checks it on both objects; mots 4.3.3 `autoriser_mot_modifierliens_dist()` | spip-plugins | `autorisations.md` core autorisations | add | source read `inc/autoriser.php:634`, `prive/formulaires/editer_liens.php:112,233,308` |
| 4.4.26 | securite#4938 never `redirige_par_entete()` in an action | spip-plugins | `actions.md:46-49`, `:172-186` | edit | source read `ecrire/public/aiguiller.php:83-135` |
| 4.4.28 | !413 a redirect set by the action (`$GLOBALS['redirect']`) is trusted, not sanitised | spip-plugins | `actions.md` redirect section | edit | same; `ecrire/action/confirmer_email.php:43` |
| 4.4.29 | #205 `SpipCles::secret_perso('monplugin')` | spip-plugins | `config.md` (secrets) | add | source read `ecrire/src/Chiffrer/SpipCles.php:63,101-109` |
| 4.4.26 | securite#4918 `secret_*` keys no longer in `$GLOBALS['meta']`; `secret_*`/`alea_*` stripped from the meta cache file | spip-plugins, spip-mutualisation-maintenance | `config.md`; `SKILL.md` | add | run (CLI: no `secret_*`/`alea_*` key in `$GLOBALS['meta']`) + source read `inc/meta.php` `touch_meta()`, commit `f3f63dbe2` |
| 4.4.29 | #176 job_queue args base64-encoded | spip-plugins | `queue-jobs.md:114` (`args` "Serialised arguments array") | edit | run (`job_queue_add` → `args` = base64 of the serialised array) |
| 4.4.20 (missed by the 4.4.22 baseline) | securite#4895 jobs are signed (`spip_jobs.signature`, `queue_sign_job()`); a job with a bad signature is not run | spip-plugins | `queue-jobs.md` table and "how jobs run" | add | run (`signature` filled) + source read `inc/queue.php:255-262`, `maj/2026.php` |
| 4.4.24 | #181 `#CACHE` does nothing in the private space | spip-plugins, spip-squelettes | `cache.md:11-30`, `balises.md:215-230` | add | source read `public/balises.php:1783-1784` |
| 4.4.25 | #184 404 pages not cached | spip-plugins | `cache.md` | add | source read `public/cacher.php:217-222,305`; run: 404 served with `Cache-Control: no-store` |
| 4.4.26 | securite#4915 helpers to declare raccourcis/typo traitements; `propre()`/`typo()` options, `interdire_scripts` (plural since textwheel 3.4.1) | spip-plugins | `declarer-table.md` `table_des_traitements` | add | source read (Task 6) |
| 4.4.31 | `auth_lister_methodes_authentification` pipeline; `auth_identifier_login($usage)` | spip-plugins | `pipelines.md` | add | source read (Task 6) |
| 4.4.29 | #196 `encoder_contexte_ajax()` / `decoder_contexte_ajax()` deprecated → `Spip\Calcul\AjaxContext` | spip-plugins | `balises-filtres-criteres.md` or `pipelines.md` (wherever Ajax contexts are built in PHP) | add | source read `inc/filtres.php:3940-3982` (`@deprecated`) |
| 4.4.26, 4.4.29 | securite#4920, #196 Ajax contexts: no external signed context; sensitive part (`fond`, `connect`) stored on disk `tmp/cache/contextes/<1st hex char>/<md5>` or memoized (`_MEMOIZE_CONTEXTES_AJAX`), rest signed base64 in the page; `_CACHE_CONTEXTES_AJAX` forces all to disk; no Ajax block for robots; hourly cron `optimiser_contextes` (50 000 files per run) | spip-squelettes, spip-mutualisation-maintenance | `inclure-ajax.md`; disk/purge in `inventory-diagnosis.md` | add | run (`tmp/cache/contextes/7/791602…` created by a page view) + source read `src/Calcul/AjaxContext.php`, `inc/genie.php:118`, `inc/utils.php:3506` |
| 4.4.26 | securite#4948 `formulaires/` squelettes cannot be displayed directly, even by webmestres | spip-formulaires, spip-testing | wherever `?page=formulaires/…` would be suggested for a check | add | run: `?page=formulaires/login` → 403 |
| 4.4.26, 4.4.29 | securite#4947, #4954 stricter `redirige_formulaire()`; a redirect coming from the query string must be secured | spip-formulaires | `cvt-formulaires.md:157` `redirect` key | edit | source read (Task 8) |
| 4.4.31 | prive#151 the form flow goes on when the visitor gets identified in `traiter()` | spip-formulaires | `cvt-formulaires.md` `traiter` | add | source read (Task 8) |
| 4.4.31, 4.4.23 | securite#4983 autosave keys and CSRF token on `action/session`; autosave no longer strips backslashes | spip-formulaires | `cvt-formulaires.md:279-295` | edit | source read (Task 8) |
| 4.4.26, 4.4.28 | securite#4940 sensitive values masked — **only** in the squelette calcul log line (`presenter_contexte()`), the profiler URL and the debugger; plain `spip_log()` does not mask | spip-logs | `spip-log.md:93`, `debug.md` | add | source read `src/Debug/Context.php`, `public/parametrer.php:141,149`, `public/debusquer.php:205`; commit `a7397427b` |
| 4.4.29 | #64 new passwords: 12 characters minimum (`_PASS_LONGUEUR_MINI` = 12; the install step keeps 6) | spip-testing, spip-mutualisation-maintenance | `howto-test.md:342` `--admin-pass='adminadmin'` (10 chars); install commands | edit | run (`_PASS_LONGUEUR_MINI` = 12) + source read `inc/utils.php:2892`, `install/etape_3b.php:35`; spip-cli behaviour checked in Task 10 |
| 4.4.26 | securite#4934 md5 passwords of redacteurs/admins reset (`pass='x'`, upgrade `2026_09_24_00`) | spip-mutualisation-maintenance | `core-upgrade.md` post-upgrade checks | add | source read `maj/2026.php:28,51-60`; run on a copy in Task 11 |
| — | harness pinned to `spip/spip` 4.4.15 | spip-testing | `tests/composer.lock` | edit | run (Task 10) |
| — | double-compat facts read on 4.4.22/4.4.23/4.4.28 | spip-migration-5 | `double-compat.md:3,41`, `catalogue.md:111`, `SKILL.md:17` | edit | source read 4.4.31 (Task 12) |
| — | 4.4 → 5.0 upgrade run from 4.4.21, schema `2026_08_03_00` | spip5-instance | `upgrade-4-4.md:3,12,42`, `prerequis.md:13` | edit | run on a copy (Task 12) |
| — | spip-cli checked on 4.4.14 | plugin-escal | `SKILL.md:75` | edit | run on a copy (Task 12) |
| — | example release `spip-4.4.1` | spip-mutualisation-maintenance | `core-upgrade.md:59,97,99` | edit | none (example) |
| — | Composer route uses a non-existent `spip/core` package; `composer update` at the root gives a mixed tree | spip-mutualisation-maintenance | `core-upgrade.md` Composer route | edit | run on copies (found during Task 11) |

## Rows without an action

| Version | Entry | Reason |
|---|---|---|
| 4.4.31 | securite#4981, #5020, #4970 AuthZ/CSRF on `action/purger`, `tester_taille`, `tester` | core actions; the pattern (check autorisation + `securiser_action()`) is already in `actions.md` |
| 4.4.31 | `lire_php_auth()` via `auth_identifier_login()`; login validation step; #213 email login case-insensitive | core auth internals; the new hooks are covered by the pipeline row |
| 4.4.31 | !462, #207, !459 modèles/img optimisations | internal |
| 4.4.30 | #206 dynamic `par num` / `par sinum` fixed | skills only show static `{par num titre}` (`boucles.md:220`, `exemples.md:142`), unaffected |
| 4.4.29 | securite#4961 escaping of dynamic sort expressions | no dynamic sort example in the skills |
| 4.4.29 | securite#4956 unpredictable class names from base64 escaping | internal |
| 4.4.29 | securite#4966 `spip_sanitize_from_request()`, `calculer_contexte()` coherent with `_request()` | internal; skills already tell to read input with `_request()` |
| 4.4.29 | securite#4954 webmestre display of a sub-directory squelette sanitises `$_GET`; `attribut_html()` in `inc/admin`; no `config_fonc` from the request | internal |
| 4.4.29 | #163, #178, !446, !444, #202, #195 | bug fixes, no API change |
| 4.4.29 | #196 `contextFileName`, no `$_POST` in Ajax contexts, one file per context, no context for an empty block or one with PHP | covered by the Ajax contexts row |
| 4.4.28 | #193 faster md5 reset; #192 key reads logged at debug level; #191 stronger hosting key without `phpinfo()` | internal |
| 4.4.27 | QuotedStrings collector, SQLite quoting | internal |
| 4.4.26 | securite#4952 debugger escaping; #4928 `balise_svg` sanitiser; #4927 `copie_locale()` aborts on failed sanitisation, `<SVG` uppercase; #4917 restricted documents copies; #4918 `..` refused in `spip_documents`; #4925, #4929 SQLite `TIMESTAMPDIFF`/`INTERVAL` | internal hardening, no documented API changes behaviour |
| 4.4.26 | securite#4933 an admin cannot degrade themself; `autoriser_auteur_modifier()` expects `restreintes`; #4935 author sessions rebuilt from the database | core autorisation internals |
| 4.4.26 | modèles made inert, QuotedStrings collector, SQL escaping failures, `_sqlite_timestampdiff`, minipage, #189 | internal |
| 4.4.25 | `curl_close` (PHP 8.5), spip/urls#4842 `self()` with arborescent URLs | internal |
| 4.4.24 | #182 genie `mise_a_jour` every 6 h | `queue-jobs.md` does not list core task periods |
| 4.4.24 | !370, !371, !369, !357, #179, #175, #169, !126, #174, queue notice | bug fixes, no API change |
| 4.4.23 | securite#4907 modèles escaping on texts > 8 kB, forbidden modèles, preg errors; #173, #170; autosave backslashes | internal (autosave covered above) |
| prive 1.1.5–1.1.10 | securite#4909, #4945, #4949, #4954, #4958 (associer checked in `traiter()`), login redirect check, #145, #149, job args filter, password form autocomplete | private-space internals; `modifierliens` and autosave rows cover the API side |
| plugins-dist | bigup 3.3.15, svp 3.2.8 use `AjaxContext` | confirms the deprecation row |
| plugins-dist | forum 3.1.17–3.1.20, medias 4.4.13–4.4.18, sites 4.4.6–4.4.9, statistiques 3.2.0–3.2.3, revisions 3.1.14–3.1.15, urls_etendues 4.2.5–4.2.6, mots 4.3.2, safehtml 3.2.0–3.2.3 | autorisation/CSRF/sanitiser hardening of their own actions; no skill documents those internals |
| plugins-dist | textwheel 3.4.0–3.4.1, porte_plume 3.1.12–3.1.15 `propre()` options, `interdire_scripts` | covered by the securite#4915 row |
