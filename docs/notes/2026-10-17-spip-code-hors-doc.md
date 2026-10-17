# Présent dans le code, absent de la doc

Éléments trouvés dans les sources de SPIP mais absents de la documentation dev.spip.net (dépôt `spip/docs`) ou contredits par elle,
relevés pendant la création du skill `spip-migration-5`. Candidats à des MR de documentation.

Sources vérifiées : SPIP 5.0.0-beta (`ecrire` `c8e329575`, 2026-09-22) et SPIP 4.4.23.
Documentation comparée : `spip/docs` `main` (`ac8c932`).

## SPIP 5.0

### Façade / Kernel

- `Spip::config()` accepte un 3e argument `$store` (`Spip::config('cle', $defaut, 'meta_perso')`).
- `Spip::setInput($key, $val, $from)` existe (non documenté ; la doc ne cite que `input()` / `inputs()`).
- Alias global `Spip` posé par `vendor/spip/framework/bootstrap/inc/facade.php` (`class_alias`) : `use Spip\Framework\Spip;` est facultatif.
- `param()` accepte un 2e argument : sans lui, lève `ParameterNotFoundException` si le paramètre est absent ; avec lui (même `null`), retourne la valeur par défaut.
- `Kernel` : méthodes **non** dépréciées : `getCwd()`, `getProjectDir()`, `getLogDir()`, `getCacheDir()`, `getBuildDir()`,
  `getShareDir()`, `isBackOffice()`, `isFrontOffice()`, `getEnvironment()`, `isDebug()`.
  Dépréciées (`#[\Deprecated]`) : `relative()`, `getRootDir()`, `getCoreDir()`, `getTmpDir()`, `getEtcDir()`, `getDocDir()`,
  `getVarDir()`, `getExtensionsDir()`, `getPluginsDir()`, `getTemplateDir()`, `getPrivateTemplateDir()`, `getCustomDir()`.
- **Bug source** : le message de dépréciation de `Kernel::getRootDir()` recommande `Spip::getProjectDir()`, qui n'existe pas
  (le bon appel est `Spip::directories()->getProjectDir()`).
- `SpipDirectoriesInterface::relative(?string $cwd = null)` : argument optionnel (la référence le présente comme obligatoire).

### Dépréciations

- Deux mécanismes : `trigger_deprecation('spip', '5.0', …)` **et** l'attribut PHP 8.4 `#[\Deprecated(since: '5.0')]` ; un audit doit chercher les deux.
- Fonctions marquées `#[\Deprecated]` non citées par la doc : `spip_query`, `fichier_session`, `f_insert_head`, `inc_log_dist`, `_image_gd_output`.
- `_request()` est marqué `#[\Deprecated]` (la doc le dit seulement « déprécié » côté façade, mais l'utilise encore dans ses exemples d'events).

### Attributs

- `#[AsCronTask]` : signature réelle `(string $name, int $periode = 0, int $priorite = 0, string $method = '__invoke')` —
  `method` non documenté ; `periode` a une valeur par défaut.

### CHANGELOG `ecrire` (Unreleased) absent de la doc

- Support AVIF.
- Constante `_DIR_CACHE_BOOT` (`tmp/cache/boot/`) ; caches `boot/chemins.php`, `boot/options.php`, `boot/fonctions.php`,
  `boot/menus.php`, `boot/classmap.php` remplaçant `tmp/cache/charger_plugins_*.php`.
- `ecrire` requiert `spip/compresseur`, `spip/sites`, `spip/medias`.
- Supprimés : `spip_sqlite3_constantes()`, constantes `SPIP_SQLITE3_*`, `_OUTILS_DEVELOPPEURS`, `_TEST_FILE_EXISTS`.
- Fonctions de logos & documents déplacées dans `spip/medias` ; `base_dump_meta_name()` déplacée dans `spip/dump`.
- `login.js` sans jQuery.

### Configuration

- `lire_config()` / `ecrire_config()` **ne sont pas** dépréciés (seuls le préfixe `/store/` et `lire_config(,, false)` le sont),
  alors que `guides/migration/config.md` les annote « déprécié ».

## SPIP 4.4 (versions correctives 4.4.1 → 4.4.23)

- Pipelines ajoutés : `trig_supprimer_objets_tables` (≥ 4.4.16), `trig_logout` (4.4.17), `modeles_publics_autorises` (4.4.22).
- Modèles dans les contributions publiques (4.4.22) : `_MODELES_PUBLICS_AUTORISES`, `modeles_publics_autorises()`,
  `modeles_publics_interdits()` (`formulaire` toujours interdit), `neutraliser_modeles_publics()` ; texte > 8 ko : tous les `<` échappés.
- Dépréciations : `_IS_CLI` (4.4.5), 3e arg non-tableau de `_T`/`_L` (4.4.6), `spip_sanitize_from_request($env, '*'|array)` (4.4.10),
  tableaux sérialisés dans `|table_valeur`/`DATA` (4.4.9), `cadre_depliable()` (4.4.12), `_ROOT_RACINE`/`_ROOT_RESTREINT`/`_ROOT_CWD`,
  5e param de `quete_logo` (4.4.16), `|chercher_rubrique{}` avec `actionable=1` et POST sur `editer_*` (4.4.17),
  `install_debut_html()`/`install_fin_html()` (4.4.1), `forum_desactiver_modeles()`, `compacte()`.
- Ajouts : `urls_type_objet_valide()`, `safe_export_env()`, `spip_sanitize_env_from_request()`, options de `safehtml()`
  (`allowIframe`, `allowIframeURIRegexp`), `afficher_html_suspect()`, option `callback_valider_url` de `recuperer_url`,
  `SpipCles::secret_des_actions()`, signature des jobs, `AbstractCollecteur::remplacer()`, autorisation `modelesphp_previsualiser`.
- Comportements : `#SESSION` vide → chaîne vide (4.4.15), `#TRUC*` d'env sans traitements (4.4.11), `#FILTRE` signé (4.4.22),
  `#CHEMIN_IMAGE` avif/webp/jpg, `find_in_path()` refuse `../`, `htaccess.txt` interdit le PHP dans `IMG/` et `local/` (4.4.16).
- Doc 4.4 : `_images_extensions_logos()` n'existe pas → `_image_extensions_logos()`.

## Ajouts (suite de l'analyse 5.0)

### Cron

- `SpipGenieTaskPass` lit deux paramètres non documentés ailleurs que dans une mention : `spip.cron.<name>.enabled` (bool) et
  `spip.cron.<name>.period` (int, remplace la période de l'attribut). → documentés dans la branche `docs-5.0-ascrontask-signature`.
- Une tâche `periode: 0` est enregistrée (exécutable via la queue avec l'include `service:`) mais jamais planifiée.
- Le pipeline `taches_generales_cron` est **toujours appelé** par `taches_generales()` après fusion des tâches `#[AsCronTask]` :
  il n'est pas supprimé, contrairement à ce que laisse entendre `features/cron-taches-attributs.md` (« remplaçant »).
- Commentaire du cœur : ne pas mettre de période < 30 s.

### Console `bin/spip` (aucune page de référence)

- `about`, `hello`, `config:lire`, `config:ecrire`, `database:optimize` (alias `sql:optimize`), `routes:list`,
  `sites:syndics:maj` (alias `syndics:maj`), `spip:cache:clear` (alias `cache:clear`, options `--boot` par défaut / `--all`),
  `spip:cache:warmup` (alias `cache:warmup`, option `--clear`), `spip:config:dump`, `spip:container:debug`, `spip:info`,
  `spip:pipeline:events` (alias `pipeline:events`), `spip:pipeline:listeners` (alias `pipeline:listeners`).
- Option globale `--with-spip` : force le chargement complet de `ecrire/inc_version.php`.
- Option globale `-e/--env` (défaut `prod`), `--no-debug`.

### Events de pipelines

- Emplacement réel : `vendor/spip/framework/src/Pipeline/Event/` (paquet `spip/framework`), pas
  `ecrire/src/Framework/Pipeline/Event/` comme l'indique `reference/evenements-pipelines.md`.
- Events typés présents dans le code mais absents de la table de correspondance :
  `afficher_nombre_objets_associes_a` → `AfficherNombreObjetsAssociesAEvent`, `formulaire_receptionner` → `FormulaireReceptionnerEvent`,
  `formulaire_verifier_etape` → `FormulaireVerifierEtapeEvent`, `optimiser_base_disparus` → `OptimiserBaseDisparusEvent`,
  `pre_edition_lien` / `post_edition_lien` → `PreEditionLienEvent` / `PostEditionLienEvent`,
  `pre_propre` / `post_propre` → `PrePropreEvent` / `PostPropreEvent`, `pre_typo` / `post_typo` → `PreTypoEvent` / `PostTypoEvent`,
  `trig_logout` → `TrigLogoutEvent`, `trig_supprimer_objets_lies` → `TrigSupprimerObjetsLiesEvent`,
  ainsi que `pre_insertion` → `PreInsertionEvent` (détaillé plus bas dans la page mais absent de la table),
  `affiche_droite` / `affiche_gauche` (détaillés, absents de la table) et la classe abstraite `AfficheColonneEvent`.
- Les plugins-dist définissent leurs propres events (ex. `Spip\Plugin\Bigup\Pipeline\Event\PreparerInputOptionsEvent`).
- `bin/spip spip:pipeline:events` liste les events typés réellement enregistrés : source de vérité plutôt que la page.

### Plugins-dist 5.0 (exemples réels de migration)

- `plugins-dist/spip/bigup` : 0 `<pipeline>` dans `paquet.xml`, `config/services.php` qui charge `src/Cron/` et `src/Pipeline/`,
  listeners `final readonly class` avec `#[Autowire(service: '…')]`, logger dédié via `spip.logger_registry`.
- `paquet.xml` des plugins-dist : `compatibilite="[5.0.0-dev;5.*]"` ; `composer.json` : `"require": {"spip/framework": "^5.x-dev"}`,
  `"require-dev": {"spip-league/sdk": "^2.4"}`, namespace `Spip\Plugin\<Nom>\`.
- Les balises `<menu>` restent utilisées dans certains `paquet.xml` 5.0 (dump, mediabox, urls).

### Compatibilité 4.4 + 5.0 (non traitée par la doc)

- Disponibles dans **les deux** versions : `test_espace_prive()`, `PHP_SAPI`, `generer_objet_url()` et famille `generer_objet_*`,
  `minifier()`, `boite_ouvrir()`/`boite_fermer()`, `_image_extensions_logos()`, `spip_sanitize_env_from_request()`, balise `#PARAM`.
- Supprimés en 5.0 alors que définis en 4.4 : `_DIR_RESTREINT_ABS`, `_ESPACE_PRIVE`, `_ROOT_RACINE`, `_DIR_IMG_PACK`, `_CACHE_PIPELINES`.
- La façade `Spip` n'existe pas en 4.4.

### CLI : spip-cli (4.x) vs bin/spip (5.0)

- SPIP 5.0 : `bin/spip` à la racine du projet (paquet `spip-league/console`, `symfony/console`) — cité une seule fois dans
  `architecture/depots-spip-league.md`, sans page de référence des commandes.
- SPIP 4.x : `spip-cli` (commande globale `spip`). Les skills `spip-testing` et `plugin-escal` s'appuient sur spip-cli :
  à signaler comme 4.x uniquement.

### Dépréciations 5.0 relevées dans la source, absentes de la doc

(Inventaire complet : 63 marqueurs, voir [2026-10-17-spip-5-deprecations-inventaire.md](2026-10-17-spip-5-deprecations-inventaire.md).)

- **`$GLOBALS['spip_pipeline']` dans `options.php` / `mes_options.php` est sans effet** sur le conteneur compilé
  (`ecrire/inc_version.php:96`, détecté par `DynamicPipelineDeclarationDetector`) → `<pipeline>` ou listener. Rupture silencieuse importante.
- `_T('cle', ['spip_lang' => 'xx'])` → option `['lang' => 'xx']` en 3e argument.
- `set_request()` → `Spip::setInput()` ; `spip_logger()` → `Spip::logger()`.
- `spip_query()` → `sql_query()` ; `fichier_session()` → `chemin_fichier_session()` ; `f_insert_head` (pipeline) à retirer ;
  `inc_log_dist()` → `Spip::logger()` ; `_image_gd_output()` → `_image_object_output()`.
- `echappe_html()` : argument `$no_transform` → `Spip\Texte\Collecteur\HtmlTag::proteger_balisesHtml` ; `$html_tags` en regexp → tableau de balises.
- `creer_pass_aleatoire($longueur, $sel)` : `$sel` sans effet.
- `in_any($val, $vals)` : `$vals` en chaîne (sérialisée) déprécié.
- `autosave_clean_value()` → `CvtAutoSaveListener::cleanValue()`.
- `optimiser_caches_contextes()`, `optimiser_base_une_table()`, `optimiser_sansref()` → méthodes du service `spip.optimiser_task`.
- Déclaration de parent d'objet (`declarer_tables_objets_sql`, clé `parent`) : doit être un tableau de tableaux.
- `prive/formulaires/dater.php` : `dater_formater_saisie_jour()`, `dater_recuperer_date_saisie()`, `dater_recuperer_heure_saisie()`
  → `dater_formater_saisie_date()`, `dater_recuperer_saisie_date()`, `dater_recuperer_saisie_heure()`.
- Plugin images : `image_gamma` → `image_luminosite`. SVP : `entite2charset()` → `entite2utf8()`. medias : `logo_migrer_en_base()`, `type_du_logo()`.
- Plugin sites : `analyser_backend($rss, $url_syndic)` → tableau `['url_syndic' => …]` ; constantes `_SYNDICATION_DEREFERENCER_URL`
  (→ paramètre `spip.sites.rss_dereferencer_url`) et `_SYNDICATION_MAX_ITEMS` (→ option `max_items`).
- `PluginManifest::getPlugins()` → `getPaths()` ; `SpipRelativeDirectories::getRootDir()` → `getProjectDir()`.

### API des events : erreurs dans la doc

- **`PreEditionEvent` / `PostEditionEvent` n'ont ni `getTable()` ni `setField()`** (exemple de `guides/pipelines-plugins.md`,
  « Flux structuré args / data ») : fatal error. API réelle (`EditionObjetPipelineTrait`) : `getSqlTable()`, `getTableObjet()`,
  `getSpipTableObjet()`, `getObjet()`, `getIdObjet()`, `getChampsAnciens()`, `getAction()`, `getServeur()`, `getDesc()`,
  `getChampsOption()`, `getOptionData()` ; les champs se modifient via `getSubject()` / `setSubject()`.
- `getTable()` / `setField()` existent en revanche sur `PreInsertionEvent` (et `getTable()`, `getInsertedId()` sur `PostInsertionEvent`).
- Méthodes non documentées : `AffichageFinalEvent::setHtml()`, `prependToHead()`, `replacePattern()` ;
  `InsertHeadEvent::addInlineScript()`, `addPrefetch()`, `append()`, `getContent()/setContent()` ;
  `InsertHeadCssEvent::addMediaStylesheet()` ; `FormulaireChargerEvent::getFormArgs()`, `getValues()/setValues()`, `removeValue()` ;
  `FormulaireVerifierEvent::getErrors()/setErrors()`, `hasError()`, `getError()`, `removeError()`, `clearErrors()` ;
  `PipelineEvent::mergeArguments()` ; `StyliserEvent::getContexte()`, `getExtension()/setExtension()`, `getSource()`, `isFond()`.

### Fonctions `generer_*_entite` : « mêmes arguments » est faux

`guides/migration/php-api.md` (section « Fonctions `generer_{x}_entite` ») affirme que les remplaçantes ont les mêmes
arguments. Les wrappers dépréciés de 4.4.23 (`ecrire/inc/utils.php`, `inc/urls.php`) montrent le contraire :

- `generer_url_entite($id, $entite, $args, $ancre, $public, $type)` : si `$public` est une chaîne (connexion distante),
  elle devient le 7e argument de `generer_objet_url()` et `$public` passe à `true` ;
- `generer_url_entite_absolue(…, $connect)` : `$connect` passe du 5e au 7e argument ;
- `generer_url_ecrire_objet($objet, $id, …)` → `generer_objet_url_ecrire($id, $objet, …)` : les deux premiers arguments
  sont inversés, et l'exemple de la doc (`generer_url_ecrire_objet(3, 'article')`) part d'un appel déjà faux ;
- les remplaçantes typent `$args`, `$ancre`, `$type`, `$connect` en `string` : un `null` que le wrapper convertissait
  en `''` lève une `TypeError`.

Par ailleurs, la table des constantes recommande `param('spip.routes.back_office')` pour `_DIR_RESTREINT_ABS` sans
distinguer l'usage : pour une URL `?exec=`, `generer_url_ecrire()` est la bonne réponse (la valeur est le chemin
relatif `ecrire/`).

### Versions

- En 5.0, `$spip_version_branche` vaut `SpipVersion::VERSION` (version complète, ex. `5.0.0-beta`), pas la branche
  (`ecrire/bootstrap/config/globals.php:48`, annoté `@deprecated SPIP 5.x`). La doc indique `SpipVersion::branch()` comme remplaçant,
  sans signaler que la valeur de la globale a changé.
- `{groupby}`, `{groupby_supprimer}`, `{collate}` n'existent pas en 4.4 : un squelette commun 4.4/5.0 doit garder
  `{fusion}`, `{fusion_supprimer}`, `{collecte}` (dépréciés en 5.0).
