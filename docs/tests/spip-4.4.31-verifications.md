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
