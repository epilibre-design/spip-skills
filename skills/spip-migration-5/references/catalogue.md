# Catalogue 4.x → 5.0

Status: **removed** (fatal if used) · **deprecated** (works, emits a deprecation) · **SPIP 6** (deprecated, removal planned in 6).
Verified in SPIP 5.0.0-beta. "doc" = stated by dev.spip.net, not re-verified in source.

---

## Constants

| 4.x | 5.0 replacement | Status |
|---|---|---|
| `_IS_CLI` | `Spip::platform()->isCli()` (4.4+5.0 code: `PHP_SAPI === 'cli'`) | removed |
| `_ESPACE_PRIVE` | `Spip::isBackOffice()` (4.4+5.0: `test_espace_prive()`; squelette: `[(#NULL\|test_espace_prive\|?{oui,non})]`) | removed |
| `_DIR_RESTREINT_ABS` | `?exec=` URL: `generer_url_ecrire('acme')` (both versions); raw prefix: `Spip::param('spip.routes.back_office')` (`'ecrire/'`, relative, trailing slash); directory: `Spip::param('spip.dirs.core')`; squelette `#PARAM{spip.routes.back_office}` | removed |
| `_ROOT_RACINE` | `Spip::directories()->getProjectDir()` (absolute, trailing slash) | removed |
| `_ROOT_RESTREINT` | `Spip::directories()->getCoreDir()` | removed |
| `_ROOT_CWD` | `Spip::platform()->getCwd()` | removed |
| `_DIR_LOG` | `Spip::directories()->getLogDir()` | removed |
| `_ROOT_IMG_PACK` / `_DIR_IMG_PACK` | `Spip::directories()->getPrivateTemplateDir() . _NOM_IMG_PACK` (relative: `->relative()->…`); prefer `chemin_image()` / `find_in_theme()` | removed |
| `_OS_SERVEUR` | `Spip::platform()->isWindows()` | removed |
| `_SPIP_VERSION_ID` | `Spip\SpipVersion::VERSION` | deprecated |
| `_PHP_MIN` | `SpipVersion::PHP_MIN` | removed |
| `_ID_WEBMESTRES` | field `webmestre` of `spip_auteurs` / private UI | removed |
| `_CONTEXTE_IGNORE_VARIABLES` (regexp) | `_CONTEXTE_IGNORE_LISTE_VARIABLES` (array of patterns) | removed |
| `_CACHE_PIPELINES` | — (no pipeline cache any more) | removed |
| `_FILE_META`, `_META_CACHE_TIME` | `Spip::configs()->reset()`; cache under `tmp/cache/config/` | removed |
| `_COOKIE_SECURE`, `_COOKIE_SECURE_LIST` | `spip_setcookie(…, secure: true, httponly: true)` (secure automatic in HTTPS) | deprecated |
| `_SYNDICATION_DEREFERENCER_URL`, `_SYNDICATION_MAX_ITEMS` | parameter `spip.sites.rss_dereferencer_url`, option `max_items` | deprecated |
| `_SPIP_LOCK_MODE = 2` (NFS) | plugin **nfslock** | removed (doc) |
| `_TEST_FILE_EXISTS`, `_OUTILS_DEVELOPPEURS`, `SPIP_SQLITE3_*` | — | removed |
| `_DIR_*` redefined in `options.php` | `config/custom.php` parameters `spip.dirs.*` | ignored: `_DIR_*` are frozen at boot |

## Globals

| 4.x | 5.0 | Status |
|---|---|---|
| `$GLOBALS['meta']['x']` (read) | `Spip::config('x')` — see `config-metas.md` | raw mirror, do not read |
| `$GLOBALS['meta']['x'] = …` (write) | `Spip::configs()->set('x', …)` | no persistence |
| `$GLOBALS['spip_pipeline']` (in `options.php`) | `<pipeline>` in `paquet.xml` or listener | **ignored** (deprecation emitted) |
| `$GLOBALS['spip_matrice']` | — | not the runtime source any more |
| `$GLOBALS['spip_version_branche']` | `SpipVersion::branch()` | deprecated |
| `$GLOBALS['spip_version_base']` | `SpipVersion::BASE` | deprecated |
| `$GLOBALS['spip_version_code']` | `SpipVersion::CODE` | removed |
| `$GLOBALS['spip_version_affichee']` | `SpipVersion::display()` | removed |
| `$GLOBALS['auteur_session']` | `$GLOBALS['visiteur_session']` | removed |
| `$GLOBALS['formats_logos']` | read `_image_extensions_logos()`, write pipeline `image_extensions_logos` | removed |
| `$GLOBALS['traiter_math']`, `$tex_server` | plugin **mathjax** | removed |

## Helpers and functions

| 4.x | 5.0 replacement | Status |
|---|---|---|
| `\SpipLeague\Component\Kernel\param()` | `Spip::param($name)` (`Spip::param($name, $default)` returns `$default` if absent) | deprecated |
| `\SpipLeague\Component\Kernel\service()` | `Spip::service($id)` | deprecated |
| `app()->getRootDir()` | `Spip::directories()->getProjectDir()` | deprecated |
| `app()->getCoreDir()`, `getTmpDir()`, `getDocDir()`, `getPluginsDir()`, `getExtensionsDir()`, `getTemplateDir()`, `getPrivateTemplateDir()`, `getCustomDir()` | same method on `Spip::directories()` | deprecated |
| `app()->getEtcDir()` | `Spip::directories()->getConfigDir()` | deprecated |
| `app()->getVarDir()` (meant `local/`) | `Spip::directories()->getPublicAssetsDir()` | deprecated |
| `app()->relative()->getX()` | `Spip::directories()->relative()->getX()` | deprecated |
| `spip_log($msg, 'acme.' . _LOG_ERREUR)` | `Spip::logger('acme')->error($msg)` — see level table below | deprecated |
| `spip_logger('acme')` | `Spip::logger('acme')` | deprecated (**does not exist in 4.4**) |
| `inc_log_dist()` | `Spip::logger()` | deprecated |
| `_request($k)` | `Spip::input($k)`; `Spip::request()?->query->get($k)` | deprecated |
| `set_request($k, $v)` | `Spip::setInput($k, $v)` | deprecated |
| `_T($k, ['spip_lang' => 'en'])` | `_T($k, [], ['lang' => 'en'])` | deprecated |
| `_T($k, [], 'classe_css')`, `_L(…, 'classe')` | `_T($k, [], ['class' => 'classe_css'])` | removed (string 3rd arg) |
| `extraire_multi($t, 'en', true)`, `extraire_idiome(…, true)` | `…, ['echappe_span' => true]` | deprecated |
| `lire_config('/store/cle')`, `ecrire_config('/store/…')`, `effacer_config('/store/…')` | `Spip::configs('store')->get('cle')` / `->set()` | deprecated |
| `lire_config($k, $d, false)` | `lire_config($k, $d)` | deprecated |
| `lire_config()`, `ecrire_config()`, `effacer_config()`, `ecrire_meta()` | unchanged (bridge) — new code: `Spip::config()` / `Spip::configs()` | **not deprecated** |
| `touch_meta()` | `Spip::configs()->refresh()` | removed |
| `cache_meta()` | automatic | removed |
| `lire_config_metapack_dist`, `ecrire_config_metapack_dist`, prefix `methode::cle` | `Spip::configs('<store>')` | removed |
| `expliquer_config()` | internal `ConfigPath::parseLegacyStoreReference()` | removed |
| `spip_sha256($v)` | `hash('sha256', $v)` | deprecated |
| `abs_url($x)` | `url_absolue($url)` / `liens_absolus($texte)` | deprecated |
| `http_status($c)` | `http_response_code($c)` | removed (doc) |
| `json_export($d)`, `var2js($d)` | `json_encode($d)` | removed (doc) |
| `compacte($css, 'css')` | `minifier($css, 'css')` | removed (doc) |
| `regledetrois($a, $b, $c)` | `\|div` / `\|mult` | removed (doc) |
| `formulaire_recherche()` | `#FORMULAIRE_RECHERCHE_ECRIRE` | removed (doc) |
| `generer_url_entite($id, $objet, $args, $ancre, $public, $type)` | `generer_objet_url((int) $id, $objet, $args, $ancre, $public, $type)`; if `$public` is a **string** (remote connection): `generer_objet_url($id, $objet, $args, $ancre, true, $type, $public)` | removed (doc) — not a plain rename, see the note below this table |
| `generer_url_entite_absolue($id, $objet, $args, $ancre, $connect)` | `generer_objet_url_absolue($id, $objet, $args, $ancre, true, '', $connect ?? '')` (5th → 7th) | removed (doc) |
| `generer_url_ecrire_objet($objet, $id, …)` | `generer_objet_url_ecrire($id, $objet, …)` — **first two arguments swapped** | removed (doc) |
| `generer_url_ecrire_entite_edit()` | `generer_objet_url_ecrire_edit()` | removed (doc) |
| `generer_introduction_entite()`, `generer_info_entite()`, `generer_lien_entite()` | `generer_objet_introduction()`, `generer_objet_info()`, `generer_objet_lien()` | removed (doc) |
| custom `generer_{x}_{objet}()` (ex. `generer_introduction_article`) | rename `generer_{objet}_{x}()` (ex. `generer_article_introduction`) | removed (doc) |
| `charger_fonction('lister_objets', 'inc')` | `recuperer_fond('prive/objets/liste/<objets>', $ctx, ['ajax' => true])` | removed (doc) |
| `include_spip('base/serial')`, `base/auxiliaires` | `lister_tables_objets_sql()` | no effect (doc) |
| `debut_cadre*()`, `fin_cadre*()`, `debut_boite_info()`, `debut_boite_alerte()`, `cadre_depliable()`, `bloc_des_raccourcis()` | `boite_ouvrir($titre, 'info')` … `boite_fermer()` | removed (doc) |
| `html5_permis()` | write HTML5 directly | SPIP 6 |
| defining `mail_embarquer_pieces_jointes()` | plugin **facteur** | deprecated |
| `spip_sanitize_from_request($env, '*' \| [...])` | `spip_sanitize_env_from_request($env [, $keys])` | removed |
| `spip_query()` | `sql_query()` | deprecated |
| `fichier_session()` | `chemin_fichier_session()` with `lire_cookie_session()` | deprecated |
| `_image_gd_output()` | `_image_object_output()` | deprecated |
| `creer_pass_aleatoire($n, $sel)` | drop `$sel` (no effect) | deprecated |
| `in_any($v, 'serialized string')` | pass an array | deprecated |
| `echappe_html(…, $no_transform)` / `$html_tags` regexp | `HtmlTag::proteger_balisesHtml` / array of tags | deprecated |
| `autosave_clean_value()` | `CvtAutoSaveListener::cleanValue()` | deprecated |
| `optimiser_caches_contextes()`, `optimiser_base_une_table()`, `optimiser_sansref()` | methods of service `spip.optimiser_task` | deprecated |
| `dater_formater_saisie_jour()`, `dater_recuperer_date_saisie()`, `dater_recuperer_heure_saisie()` | `dater_formater_saisie_date()`, `dater_recuperer_saisie_date()`, `dater_recuperer_saisie_heure()` | deprecated |
| `image_gamma` | `image_luminosite` | deprecated |
| `entite2charset()` (svp) | `entite2utf8()` | deprecated |
| `analyser_backend($rss, $url_syndic)` (sites) | `analyser_backend($rss, ['url_syndic' => …])` | deprecated |
| `objet` descriptor `'parent' => [...]` (single) | array of arrays `'parent' => [[...]]` | deprecated |
| `calculer_parties($b, $idb, $partie, $mode)` | pass `$pas` as 5th argument | required |
| root-namespace compiler classes `Boucle`, `Champ`, `Critere`, `Texte`, `Contexte`, `Polyglotte` | `use Spip\Compilateur\Noeud\…;` | aliases removed |
| `|table_valeur` / `(DATA){source tableau}` on serialized strings | pass an array, unserialize explicitly if trusted | no auto-unserialize |

**URL helpers — not a plain rename** (checked in the 4.4.23 wrappers, `ecrire/inc/utils.php`, `inc/urls.php`):
the old functions were deprecated wrappers that reordered and cleaned arguments. Reproduce what the wrapper did:
`$connect` moves from 5th to 7th position, `generer_url_ecrire_objet()` takes `($objet, $id)` but
`generer_objet_url_ecrire()` takes `($id, $objet)`, and the new functions type `$args`, `$ancre`, `$type`, `$connect`
as `string` — a `null` that the wrapper turned into `''` now throws a `TypeError`. The upstream documentation
says "same arguments" and its `generer_url_ecrire_objet(3, 'article')` example is wrong.

### `spip_log()` levels → PSR-3 (from `ecrire/bootstrap/inc/logging.php`)

| `_LOG_*` | `Spip::logger()->…` |
|---|---|
| `_LOG_HS` | `emergency()` |
| `_LOG_ALERTE_ROUGE` | `alert()` |
| `_LOG_CRITIQUE` | `critical()` |
| `_LOG_ERREUR` | `error()` |
| `_LOG_AVERTISSEMENT` | `warning()` |
| `_LOG_INFO_IMPORTANTE` | `notice()` |
| `_LOG_INFO` (default of `spip_log`) | `info()` |
| `_LOG_DEBUG` | `debug()` |

Threshold `_LOG_FILTRE_GRAVITE` → parameter `spip.logger.max_level` (default `notice`); `$nombre_de_logs` → `spip.logger.max_files`;
`_FILE_LOG` → `spip.logger.log_path`. Set in `config/custom.php` (see `plugin-modele-5.md`).

## Features moved out

| Feature | Now |
|---|---|
| `\|image_typo` | plugin **Images typographiques** (`images_typo`) |
| `<math>`, `traiter_math()`, `produire_image_math()` | plugin **mathjax** |
| search-term highlighting | plugin **Surligne** |
| NETPBM thumbnails | GD2 or Imagick |
| `pgp` field of `spip_auteurs` | recreate with **Champs Extras** if needed |
| logo & document functions | plugin `spip/medias` (required by `ecrire`) |
| `base_dump_meta_name()` | plugin `spip/dump` |

## Private space

| 4.x | 5.0 |
|---|---|
| `?exec=admin_tech` | `?exec=admin_bdd` |
| menu parent `menu_squelette` | `menu_apparence` (deprecated remap) |
| menu ids `bando_*` | `menu_*` (deprecated remap) |
| `#LARGEUR_ECRAN` | `#LAYOUT_PRIVE{pleine-largeur}` (hyphen) |
| `<script>` / `<style>` in `paquet.xml` | listeners on `insert_head`, `insert_head_css`, `header_prive`, `header_prive_css` (deprecated) |
| `<menu>` / `<onglet>` in `paquet.xml` | `config/menus.php` / `config/onglets.php` (XML still read) |

## Grep patterns

```bash
grep -rnE "_IS_CLI|_ESPACE_PRIVE|_DIR_RESTREINT_ABS|_ROOT_(RACINE|RESTREINT|CWD|IMG_PACK)|_DIR_IMG_PACK|_DIR_LOG\b|_OS_SERVEUR|_ID_WEBMESTRES|_CONTEXTE_IGNORE_VARIABLES|_CACHE_PIPELINES|_FILE_META|_META_CACHE_TIME|_COOKIE_SECURE" .
grep -rnE "GLOBALS\[['\"](meta|spip_pipeline|spip_matrice|auteur_session|formats_logos|spip_version_(code|affichee|branche|base))" .
grep -rnE "\b(spip_log|spip_logger|_request|set_request|spip_query|spip_sha256|abs_url|json_export|var2js|http_status|compacte|regledetrois|formulaire_recherche|touch_meta|cache_meta|fichier_session|html5_permis|cadre_depliable|bloc_des_raccourcis|debut_cadre\w*|debut_boite_(info|alerte))\s*\(" .
grep -rnE "generer_(url_entite|url_entite_absolue|url_ecrire_objet|url_ecrire_entite_edit|introduction_entite|info_entite|lien_entite)\s*\(" .
grep -rnE "Kernel\\\\(param|service)\(|app\(\)->(getRootDir|getCoreDir|getTmpDir|getEtcDir|getDocDir|getVarDir|getPluginsDir|getExtensionsDir|getTemplateDir|getPrivateTemplateDir|getCustomDir|relative)\(" .
grep -rnE "lire_config\(['\"]/|ecrire_config\(['\"]/|effacer_config\(['\"]/|spip_sanitize_from_request\([^)]*,\s*(\[|'\*')" .
grep -rnE "\{(fusion|fusion_supprimer|collecte)\b|</INCLURE>|<INCLURE\((.*)\.php\)|fond=[^}]*\.php|\(POUR\)|\|foreach\b|#LOGO_[A-Z_]+\|(left|right|center|fichier|#URL)|#EXPOSER|#EMBED_DOCUMENT|#HTML5|\|icone\{|#LARGEUR_ECRAN|image_typo|<:[^:>]*[A-Z]" --include=*.html .
grep -rnE "<(script|style|menu|onglet)\b|parent=\"(menu_squelette|bando_)" paquet.xml
```
