# Inventaire des dépréciations SPIP 5.0 (source)

Généré le 2026-10-17 sur SPIP 5.0.0-beta (ecrire c8e329575).
Colonnes : TRIG = trigger_deprecation('…', '5.0', …), ATTR = attribut #[\Deprecated] ; fichier:ligne ; fonction englobante ; message.

```text
TRIG|ecrire/inc_version.php:96|(top)|'Déclarer des pipelines via $GLOBALS[\'spip_pipeline\'] (options.php / mes_options.php) est obsolète et sans effet sur le conteneur compilé (pipelines concernés : %s). ' . 'Migrez vers <pipeline> dans
ATTR|ecrire/auth/sha256.inc.php:22|spip_sha256|#[\Deprecated(message: "use hash('sha256', \$str) instead", since: '5.0')] function spip_sha256($str) { 	return hash('sh
ATTR|ecrire/genie/optimiser.php:31|optimiser_caches_contextes|use method OptimiserTask::optimiserCachesContextes() from service spip.optimiser_task instead
ATTR|ecrire/genie/optimiser.php:46|optimiser_base_une_table|use method OptimiserTask::optimiserBaseUneTable() from service spip.optimiser_task instead
ATTR|ecrire/genie/optimiser.php:63|optimiser_sansref|use method OptimiserTask::optimiserSansref() from service spip.optimiser_task instead
ATTR|ecrire/public/criteres.php:603|critere_fusion_dist|use {groupby} instead of {fusion}
ATTR|ecrire/public/criteres.php:611|critere_fusion_supprimer_dist|use {groupby_supprimer} instead of {fusion_supprimer}
ATTR|ecrire/public/criteres.php:686|critere_collecte_dist|use {collate} instead of {collecte}
ATTR|ecrire/public/balises.php:2680|balise_HTML5_dist|remove this balise
TRIG|ecrire/public/balises.php:2681|balise_HTML5_dist|'Using #HTML5 is deprecated'); $p->code = html5_permis() ? "' '" : "''"; $p->interdire_scripts = false;  return $p; }
ATTR|ecrire/bootstrap/base/connect_sql.php:507|spip_query|use sql_query() instead
TRIG|ecrire/bootstrap/base/objets.php:1533|objet_type_decrire_infos_parents|'Parent declaration for %s should be an array of array', $objet); } $parents[$objet] = isset($infos['parent'][0]) ? $infos['parent'] : [$infos['parent']]; } elseif (isset($infos['field']['id_rubrique'
ATTR|ecrire/bootstrap/inc/request.php:20|_request|use "Spip::input()" instead.
TRIG|ecrire/bootstrap/inc/request.php:21|_request|'The "%s()" function is deprecated, use "Spip::input()" instead.', __FUNCTION__);  if (is_array($c)) { return Spip::input($var, $c); } 
TRIG|ecrire/bootstrap/inc/request.php:66|set_request|'The "%s()" function is deprecated, use "Spip::setInput()" instead.', __FUNCTION__);  if ($c === false) { $c = null; } 
TRIG|ecrire/bootstrap/inc/logging.php:31|spip_logger|'The "%s()" function is deprecated, use "Spip::logger()" instead.', __FUNCTION__);  return Spip::logger($name); }  /**
ATTR|ecrire/bootstrap/inc/logging.php:58|spip_log|use Spip::logger() instead
TRIG|ecrire/bootstrap/inc/i18n.php:51|_T|sprintf( 'Calling "%s" function with "%s" argument (2nd parameter) is deprecated, use "%s" option (3rd parameter) instead', __FUNCTION__,
TRIG|ecrire/inc/envoyer_mail.php:117|inc_envoyer_mail_dist|'Using "%s" is deprecated, use "%s" instead.', 'mail_embarquer_pieces_jointes', 'the plugin Facteur https://plugins.spip.net/facteur.html'
ATTR|ecrire/inc/filtres_mini.php:227|abs_url|use url_absolue() or liens_absolus() instead
TRIG|ecrire/inc/config.php:43|lire_config|'Using lire_config(,,false) is deprecated'); } [$store, $rest] = legacy_resolve_config($cfg, 'lire_config');  return Spip::service('spip.config.manager') ->repository($store)
TRIG|ecrire/inc/config.php:86|legacy_resolve_config|'Calling %s() with a "/<store>/..." prefix is deprecated; use service("spip.config.manager")->repository("<store>") instead.', $caller, );
TRIG|ecrire/inc/texte_mini.php:234|echappe_html|'Using "%s" arg is deprecated, use directly "%s" instead.', '$no_transform', 'Spip\Texte\Collecteur\HtmlTag::proteger_balisesHtml',
TRIG|ecrire/inc/texte_mini.php:251|echappe_html|'Using a preg for "%s" arg is deprecated, use a tag array instead.', '$html_tags', __FUNCTION__
ATTR|ecrire/inc/filtres_images_lib_mini.php:1131|_image_gd_output|use _image_object_output() instead
ATTR|ecrire/inc/session.php:751|fichier_session|use chemin_fichier_session() with lire_cookie_session() as 2nd parameter
ATTR|ecrire/inc/pipelines.php:39|f_insert_head|remove this pipeline
TRIG|ecrire/inc/cookie.php:92|spip_setcookie|'Using "%s" constant is deprecated, use option "%s" when call "%s" instead (automatic in HTTPS).', '_COOKIE_SECURE', 'secure: true',
TRIG|ecrire/inc/cookie.php:107|spip_setcookie|'Using "%s" constant is deprecated, use option "%s" when call "%s" instead.', '_COOKIE_SECURE_LIST', 'httponly: true',
TRIG|ecrire/inc/acces.php:38|creer_pass_aleatoire|'Using $sel parameter is deprecated and has no effect in %s() function', __FUNCTION__); } // Alphabet lisible $alphabet = '2345679ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'; return new Randomi
TRIG|ecrire/inc/bandeau.php:105|definir_barre_boutons|'Le menu `menu_squelette` est déprécié, utiliser `menu_apparence` à la place (parent du menu `%s`)', $infos['nom'] );
TRIG|ecrire/inc/bandeau.php:115|definir_barre_boutons|'Préfixer les menu par `bando_` est déprécié, utiliser le préfixe `menu_` à la place (parent du menu `%s`)', $infos['nom'] );
ATTR|ecrire/inc/log.php:19|inc_log_dist|use Spip::logger() instead
TRIG|ecrire/inc/cvt_autosave.php:31|autosave_clean_value|'La fonction autosave_clean_value() est obsolète, utilisez CvtAutoSaveListener::cleanValue() à la place.'); return CvtAutoSaveListener::cleanValue($val); } 
TRIG|ecrire/inc/filtres.php:1666|extraire_idiome|'Using boolean $options parameter in "%s()" function is deprecated. Use %s array parameter instead.', __FUNCTION__, '[\'echappe_span\' => ' . var_export($options, true) . ']'
TRIG|ecrire/inc/filtres.php:1729|extraire_multi|'Using boolean $options parameter in "%s()" function is deprecated. Use %s array parameter instead.', __FUNCTION__, '[\'echappe_span\' => ' . var_export($options, true) . ']'
TRIG|ecrire/inc/filtres.php:2493|in_any|'Le second argument de vals au format string est déprécié'); try { $v = new Serializer()->unserialize($vals, ['accept_legacy' => true]); $vals = $v; } catch (Exception) { $vals = null;
ATTR|prive/formulaires/dater.php:186|dater_formater_saisie_jour|use dater_formater_saisie_date() instead
ATTR|prive/formulaires/dater.php:401|dater_recuperer_date_saisie|use dater_recuperer_saisie_date() instead
ATTR|prive/formulaires/dater.php:458|dater_recuperer_heure_saisie|use dater_recuperer_saisie_heure() instead
ATTR|plugins-dist/spip/medias/action/editer_logo.php:163|logo_migrer_en_base|migrate the site & logos / tables in a SPIP 4.x or 5.x
ATTR|plugins-dist/spip/medias/action/editer_logo.php:305|type_du_logo|do not use this function. It is used for logo_migrer_en_base() only.
ATTR|plugins-dist/spip/images/filtres/images_transforme.php:1296|image_gamma|use image_luminosite() instead
ATTR|plugins-dist/spip/svp/inc/svp_outiller.php:407|entite2charset|use entite2utf8() instead
TRIG|plugins-dist/spip/sites/syndic/atomrss.php:89|analyser_backend|'Le paramètre $url_syndic est déprécié, utiliser un tableau avec la clé "url_syndic"' ); }
TRIG|plugins-dist/spip/sites/syndic/atomrss.php:117|analyser_backend|'La constante "_SYNDICATION_DEREFERENCER_URL" est dépréciée, utiliser le paramètre "spip.sites.rss_dereferencer_url" ou l’option "dereferencer_url"' ); $options['dereferencer_url'] = constant('_SYNDIC
TRIG|plugins-dist/spip/sites/syndic/atomrss.php:133|analyser_backend|'La constante "_SYNDICATION_MAX_ITEMS" est dépréciée, utiliser le paramètre "max_items"' ); $options['max_items'] = constant('_SYNDICATION_MAX_ITEMS');
TRIG|vendor/spip-league/kernel/boot.php:34|param|'The "%s()" function is deprecated, use "Spip::param()" instead.', __FUNCTION__, );
TRIG|vendor/spip-league/kernel/boot.php:57|service|'The "%s()" function is deprecated, use "Spip::service()" instead.', __FUNCTION__, );
ATTR|vendor/spip-league/kernel/src/Kernel.php:221|relative|use "Spip::directories()->relative()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:227|getRootDir|use "Spip::getProjectDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:233|getCoreDir|use "Spip::directories()->getCoreDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:239|getTmpDir|use "Spip::directories()->getTmpDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:248|getEtcDir|use "Spip::directories()->getConfigDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:257|getDocDir|use "Spip::directories()->getDocDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:266|getVarDir|use "Spip::directories()->getPublicAssetsDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:275|getExtensionsDir|use "Spip::directories()->getExtensionsDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:284|getPluginsDir|use "Spip::directories()->getPluginsDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:293|getTemplateDir|use "Spip::directories()->getTemplateDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:302|getPrivateTemplateDir|use "Spip::directories()->getPrivateTemplateDir()" instead.
ATTR|vendor/spip-league/kernel/src/Kernel.php:311|getCustomDir|use "Spip::directories()->getCustomDir()" instead.
ATTR|vendor/spip-league/kernel/src/Directory/SpipRelativeDirectories.php:105|getRootDir|use "getProjectDir()" instead.
ATTR|vendor/spip-league/plugin-manager/src/Boot/PluginManifest.php:96|getPlugins|Utiliser {@see getPaths()}
```
