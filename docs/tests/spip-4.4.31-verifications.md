# SPIP 4.4.31 — verifications

Date: 2026-11-22

Each result says whether it comes from a **run** on the instance or a **source read**.

## Instance

- `/src/jetables/v4.4.31` (former `v4.4.30`, upgraded 2026-11-22; backup
  `/src/jetables/v4.4.30-sauvegarde-2026-11-22.tgz`): `spip/spip` `62485d649fcb7abba91ced4b768eaa3afe621ba6`
  (tag 4.4.31), `spip/ecrire` 4.4.31 `5d7078d2`, `spip/prive` 1.1.10 `693c1396`, `spip/dist` 4.2.10 `c2beb84a`,
  `spip/security` 1.6.7 `12bb638d`, PHP 8.4.28 (built-in server on `http://127.0.0.1:18931`), SQLite.
- plugins-dist (tags): aide 3.2.9, archiviste 2.2.7, bigup 3.3.15, compagnon 3.3.2, compresseur 2.2.5, dump 2.2.5,
  filtres_images 4.3.12, forum 3.1.20, mediabox 3.2.2, medias 4.4.18, mots 4.3.3, plan 4.1.8, porte_plume 3.1.15,
  revisions 3.1.15, safehtml 3.2.3, sites 4.4.9, statistiques 3.2.3, svp 3.2.8, textwheel 3.4.1, urls_etendues 4.2.6.
- Core schema `2026_09_24_00`; no database upgrade from 4.4.30.
- Survey: `spip_loader.api` latest 4.4 = 4.4.31 on 2026-11-22.

## Runs

| Question | Result | How |
|---|---|---|
| Instance serves 4.4.31 | `/` 200, `?page=plan` 200, `?page=backend` 200, `/ecrire/` 302; generator `SPIP 4.4.31` | `curl` on 2026-11-22 |
| Job args storage | `job_queue_add('ma_fonction', …, ['a', 42])` → `args` = `YToyOntpOjA7czoxOiJhIjtpOjE7aTo0Mjt9` (base64 of `a:2:{i:0;s:1:"a";i:1;i:42;}`), `signature` set | CLI on copy `jetable-4431-audit`, job deleted after |
| Password minimum | `_PASS_LONGUEUR_MINI` = 12 | CLI on copy |
| Secrets in `$GLOBALS['meta']` | no `secret_*` / `alea_*` key; `lire_config('alea_ephemere')` = null | CLI on copy |
| Direct display of `formulaires/` | `?page=formulaires/login`, `?page=formulaires/recherche` → 403 (anonymous) | `curl` on copy (port 18932) |
| 404 response | `?page=article&id_article=999999` → `404 Not Found`, `Cache-Control: no-store, no-cache, must-revalidate` | `curl -D -` on copy |
| Ajax context storage | after page views: `tmp/cache/contextes/7/791602947f1814b7c90b2c66c06eabe4` (subdirectory = first hex char of the md5) | `find` on copy |
| Test harness `tests/` on 4.4.15 (baseline) | `composer install` (lock: `spip/spip` 4.4.15, `spip/ecrire` 4.4.15), `scripts/install-spip-test.sh` OK; `phpunit --testsuite unit` OK 15 tests / 20 assertions; `--testsuite integration` OK 14 tests / 26 assertions, 21 PHPUnit deprecations | `tests/` |
| Test harness on 4.4.31 | `composer update 'spip/*' --with-all-dependencies`: `spip/spip` 4.4.31 `62485d64`, `spip/ecrire` 4.4.31 `5d7078d2`, `spip/prive` 1.1.10, `spip/dist` 4.2.10, `spip/spip-cli` dev-master `e9043b84`, `spip/tests` dev-master `5b169c78`; plugins-dist cloned at 4.4.31 tags (medias 4.4.18). `install-spip-test.sh` with `--admin-pass='adminadmin'` → database installed, then « Le mot de passe doit contenir au moins 12 caractères. », no `config/connect.php`, script stops. With `--admin-pass='admin-test-pass'`: OK. unit OK 15/20, integration OK 14/26 (21 PHPUnit deprecations) | `tests/` |
| Log masking (securite#4940) | with `_LOG_FILTRE_GRAVITE` 8 on the copy, `?page=test_ajax&password=Secret123&token=abc&api_key=K` → `spip.log`: `calcul (…) [squelettes/test_ajax] page=test_ajax, password=***, token=***, api_key=***, x=1, …`; `spip_log(['pass' => 'Secret123', 'email' => 'a@b.example'], 'acme')` and `spip_log('password=Secret123', 'acme')` → both written in clear in `acme.log` | `curl` + `tmp/log/` on copy |
| CVT redirect (securite#4947, #4954) | test form `acme_test` (`traiter()` returns `redirect` = posted `cible`, or the form argument from `?retour=`), page `test_form`. Normal POST: `spip.php?page=plan` → 302 `http://127.0.0.1:18932/spip.php?page=plan`; `https://evil.example/` → 302 `https://evil.example/`; `//evil.example/x` → 302 `//evil.example/x`; `javascript:alert(1)` → 302 `./`; `?retour=https://evil.example/` → 302 `https://evil.example/`. Ajax POST (`var_ajax=form`): `?retour=https://evil.example/` → 200, no redirect link, `message_ok` shown; `?retour=spip.php?page=plan` → hidden `ajax_redirect` link to it; posted `https://evil.example/` (not in the QS) → `ajax_redirect` link to it | `python3` POST script on copy |
| Ajax block markup and storage | `<INCLURE{fond=inclure/test_bloc, env, ajax} />` → `<div class="ajaxbloc ajax-id-1" data-ajax-env="daadbaac23cf856944bc7310f4d58c3c|yo52iYNd…" data-origin="spip.php?page=test_ajax&amp;x=1">`; new file `tmp/cache/contextes/d/daadbaac23cf856944bc7310f4d58c3c` = base64 of `<hash>:a:1:{s:4:"fond";s:17:"inclure/test_bloc";}`; with `curl -A Googlebot…` the block has no Ajax container | `curl` on copy, squelettes `test_ajax.html`, `inclure/test_bloc.html` |
| Dynamic sorts (#206, securite#4961) | 3 numbered + 2 unnumbered test articles: `{par num titre, titre}` = `{par num #ENV{tri}, #ENV{tri}}` (tri=titre) = `Un|Deux|Dix|Aaa sans numero|Zzz sans numero`; `{!par sinum titre, num titre, titre}` = its dynamic form; `{par #ENV{tri}}` with `tri=titre,(SELECT 1)` → empty boucle, `tri=titre) --` → sorted by titre | `curl` on copy, squelette `test_tri.html`, rows inserted in the copy's SQLite |
| JS loaded by `#INSERT_HEAD` | `jquery.js`, `jquery.form.js`, `jquery.autosave.js`, `jquery.placeholder-label.js`, `ajaxCallback.js`, `js.cookie.js` | `curl` of the sommaire on copy; `ecrire/inc/pipelines.php` `f_jQuery()` |
| `SpipCles::secret_perso()` | `secret_perso('acme')`: 32 bytes, same value on two calls, differs from `secret_perso('acme', false)` and from `secret_perso('autre')`; creates meta `secret_de_acme` and a `secret_de_acme` entry in `config/cles.php` | CLI on copy |
| Redirect after an action (securite#4938, !413) | test action `squelettes/action/acme_test.php` on the copy, PHP built-in server (so `redirige_par_entete()` answers `200` + `Refresh:` instead of `302` + `Location:`, as on any server not matching `_SERVEUR_SOFTWARE_ACCEPTE_LOCATION_APRES_COOKIE`): `redirect=https://evil.example/` → `204` (refused); `redirect=spip.php?page=plan` → refresh to `…/spip.php?page=plan`; action sets `$GLOBALS['redirect']` to the plan URL with `redirect=https://evil.example/` → refresh to the plan; action sets `$GLOBALS['redirect']='https://example.org/'` → refresh to `https://example.org/` (trusted, not sanitised); no redirect → `204` | `curl -D -` on copy (port 18932) |
| Autorisation chain (securite#4931) | with `autoriser_chose_dist()` and `autoriser_truc_faire_dist()` defined, visitor `6forum`: `autoriser('voir','chose')` → true (via `autoriser_voir_dist`), `autoriser('supprimer','chose')` → false (via `autoriser_defaut_dist`), `autoriser_chose_dist()` never entered; `autoriser('supprimer','truc')` → true, `autoriser_truc_faire_dist()` entered | CLI on copy `jetable-4431-audit` |

## Source reads

| Question | Result | File |
|---|---|---|
| Autorisation lookup chain | `autoriser_{type}_{faire}`, `…_{faire}_dist`, `autoriser_{type}_faire` (literal `_faire`), `autoriser_{type}_faire_dist`, `autoriser_{faire}`, `…_dist`, `autoriser_defaut`, `…_dist`; the former `autoriser_{type}[_dist]` steps are gone | `ecrire/inc/autoriser.php:199-216` |
| Generic `modifierliens` | `autoriser_modifierliens_dist()`: `modifier` on the object, plus `associer<table>` when `$opt['lien']['objet']` is given | `ecrire/inc/autoriser.php:634-646`; `prive/formulaires/editer_liens.php:112,233,308` |
| Redirect after an action | `traiter_appels_actions()` secures `_request('redirect')` with `securiser_redirect_action()` before the action; after it, `$GLOBALS['redirect']` if set (not sanitised) else the secured request value, via `redirige_action()`; no redirect → 204 | `ecrire/public/aiguiller.php:83-135` |
| `SpipCles::secret_perso()` | `secret_perso(string $nom, bool $composite = true)`; key `secret_de_<nom>`, composite = disk key XOR meta key | `ecrire/src/Chiffrer/SpipCles.php:63,101-109` |
| Job signature and args | args `base64_encode(serialize())`; `queue_start_job()` refuses a bad signature and decodes base64 when `args` has no `:` | `ecrire/inc/queue.php:78,255-275`; signature column since `2026_08_03_00` (4.4.20, securite#4895) |
| `#CACHE` in the private space | ignored (`if ($p->param && !test_espace_prive())`) | `ecrire/public/balises.php:1783-1784` |
| 404 cache | `cache_valide()` returns -1 and `creer_cache()` returns early for `type-page`/`cache` 404 | `ecrire/public/cacher.php:217-222,305` |
| Meta cache | `touch_meta()` strips `secret_*` and `alea_*` from `tmp/cache/meta_cache.php`; `SpipCles` keeps its meta keys in a private property | `ecrire/inc/meta.php`; commit `f3f63dbe2` |
| Ajax contexts | `AjaxContext`: sensitive keys (`fond`, `connect`) stored on disk (`_DIR_CACHE . 'contextes/' . md5[0] . '/' . md5`) or memoized if `_MEMOIZE_CONTEXTES_AJAX`; rest signed base64; `_CACHE_CONTEXTES_AJAX` forces storage; `_CACHE_CONTEXTES_AJAX_SUR_LONGUEUR`; no Ajax block for `_IS_BOT`; `encoder_contexte_ajax()` `@deprecated`; cron `optimiser_contextes` every 3600 s | `ecrire/src/Calcul/AjaxContext.php`, `inc/filtres.php:3940-3982`, `inc/utils.php:3506`, `inc/genie.php:118`, `genie/optimiser_contextes.php` |
| Log masking | `Spip\Debug\Context::mask()`/`maskUrl()` used only by `presenter_contexte()` (calcul log), the profiler line and `debusquer_contexte()`; `spip_log()` itself unchanged | `ecrire/src/Debug/Context.php`, `public/parametrer.php:141,149`, `public/debusquer.php:205` |
| Password minimum | `_PASS_LONGUEUR_MINI` 12 in `inc/utils.php:2892`; the install step defines 6 only if still undefined | `ecrire/inc/utils.php`, `ecrire/install/etape_3b.php:34-35` |
| md5 password reset | upgrade `2026_09_24_00`: authors `0minirezo`/`1comite` with `LENGTH(pass)=32` get `pass='x'`, `htpass=''`, `low_sec=''` | `ecrire/maj/2026.php:28,51-60` |
| Auth pipelines (4.4.31) | `auth_lister_methodes_authentification()` pipes `['args' => [], 'data' => $GLOBALS['liste_des_authentifications']]` (static); `auth_identifier_login($login, $password, $serveur = '', $phpauth = false, $usage = 'login', $methode_demandee = null)`; `auth_valider_identification` pipeline `args` methode/login/password(bool)/serveur/phpauth/usage, `data['auteur']`, refusal through `erreur`; usages `login`, `controler_auteur`, `login_action_auth`; both pipelines declared in `ecrire/paquet.xml:54-55` | `ecrire/inc/auth.php:429-442,538-662`, `ecrire/paquet.xml` |
| Traitement helpers (securite#4915) | `declarer_traitement_raccourcis(?string $contexte_modeles = null)`, `declarer_traitement_typo()`, `declarer_traitement_typo_sans_numero()`; a context adds `['contexte_public' => …]` to `propre()`/`typo()`; with a context, modèles allowed only from `$options['modeles_autorises']`, `_MODELES_PUBLICS_AUTORISES` or pipeline `modeles_publics_autorises` (flux `contexte`, `modeles`, since 4.4.26 commit `b31fd7d7b`), `formulaire` always refused | `ecrire/public/interfaces.php:34-68`, `ecrire/inc/modeles.php:46-123`, `plugins-dist/textwheel/inc/texte.php:203,840` |
| `redirige_formulaire()` | non-Ajax: `redirige_par_entete()` (pseudo-protocols → `./`); Ajax: pseudo-protocols dropped, a URL equal to a `$_GET` value must pass `securiser_redirect_action($url, false)` | `ecrire/inc/headers.php:119-160` |
| Identification in `traiter()` (prive#151) | after `traiter()`, if the visitor was not connected and now is, the form context is re-encoded with `encodeFormContext(…, true)` | `ecrire/public/aiguiller.php:403-409` |
| Autosave CSRF (securite#4983) | `action_session_dist()`: `arg` must be `set`, `verifier_action_auteur("session-set", $hash)`, POST only, `var` matches `^autosave_[a-z_0-9-]+$`, `_`-prefixed fields dropped; hash produced by `cvtautosave_formulaire_charger()` | `ecrire/action/session.php`, `ecrire/inc/cvt_autosave.php:80-95`, `prive/javascript/jquery.autosave.js` |
| `formulaires/` direct display (securite#4948) | `?page=` with `/`: refused unless webmestre, and always refused when it contains `formulaires/` | `ecrire/public.php:37-49` |
