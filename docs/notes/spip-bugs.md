# Bugs relevés dans le code de SPIP

Bugs du **code** de SPIP et de ses paquets (MR à ouvrir sur `git.spip.net`), relevés en écrivant les skills.
Les erreurs de la **documentation** (`spip/docs`) sont notées à part dans
[2026-10-17-spip-code-hors-doc.md](2026-10-17-spip-code-hors-doc.md).

Chaque entrée indique la version vérifiée, l'emplacement, une reproduction et une piste de correction. Cocher
l'entrée et ajouter le lien de la MR une fois remontée.

## Revérification du 2026-10-19

L'instance de référence `/src/jetables/v5.0-dev` a été mise à jour avant les essais
(`composer update spip/ecrire --with-dependencies`, puis `bin/spip spip:cache:warmup --clear`).
La constante `Spip\SpipVersion::VERSION` vaut toujours `5.0.0-beta` ; PHP CLI : 8.4.25.

Révisions vérifiées le 2026-10-19 :

| Dépôt / paquet | Révision |
|---|---|
| `spip/spip` (`master`) | `c8e329575c2d4e9aa28853db1606c34214c2845f` |
| `spip/ecrire` (`5.x`) | `218a10d1bd9be718f2e0a1743118bc9d2351f856` (2026-10-19) |
| `spip/framework` | `d0e8c97e62cde5cbf89805e5b874e46d094cd401` |
| `spip-league/kernel` | `e0e80032b7fcb0d6d84f2fc53088119c6f1b2ab3` |
| `spip-league/pipeline-bridge` | `b80ffcead6b188331f98e2d0fc83506d664b512a` |
| `spip-league/plugin-manager` | `689c9253c292353f68755d5b1e95a9a4fadb304a` |
| `spip-league/console` | `c5b9d472aab44437efcbf7e11e48a7fe3f4d918d` |

`git ls-remote` confirme les têtes amont de `spip/spip`, `spip/ecrire` et des quatre paquets
`spip-league` ci-dessus. Après mise à jour, `composer outdated 'spip*' --format=json` renvoie
`{"installed": []}`. Le dépôt racine n'a pas changé ; le cœur `spip/ecrire` a avancé depuis `26bfe470`.

| Entrée | Résultat au 2026-10-19 | Vérification effectuée |
|---|---|---|
| 1 — tâches cron | Toujours reproduit, deux blocages présents | Registre accessible, tâche `optimiser` enregistrée, garde à `false`, service privé inaccessible ; signature de la ligne SQL valide et `queue_start_job()` à `false`. |
| 2 — dépréciation | Toujours présent | Lecture de l'attribut par `ReflectionMethod` et `method_exists(Spip\Framework\Spip::class, 'getProjectDir')` à `false`. |
| 3 — commandes d'installation / activation | Demande d'évolution toujours pertinente | Exécution de `bin/spip list --raw` : aucune commande d'installation ou d'activation de plugin. Le parcours spip-cli n'a pas été réexécuté. |
| 4 — environnement des plugins | Toujours reproduit | Appel réel de `PluginSymfonyServicesCollector::loadIntoContainer()` sur un fichier de services d'essai : `NULL` en `prod` et `dev` ; le même fichier chargé avec un `PhpFileLoader` recevant l'environnement donne respectivement `'prod'` et `'dev'`. |
| 5 — argument vide | Toujours reproduit | Compilation par `recuperer_fond()` : argument omis → même `TypeError` ; argument `''` → URL d'action générée. Pas de nouvel essai HTTP. |
| 6 — répertoires | Incohérences toujours reproduites ; cause 2 précisée ci-dessous | Avec `SPIP_CACHE_DIR`, artefacts créés dans le cache externe mais `_DIR_CACHE_BOOT` reste `tmp/cache/boot/`. Avec `spip.dirs.tmp=/srv/site-tmp/`, `getTmpDir()` donne `<projet>/srv/site-tmp/`, son relatif `srv/site-tmp/`, et le cache reste `<projet>/tmp/cache/`. Pas de nouvel essai HTTP. |
| 7 — fin d'installation | Causes toujours présentes à la lecture | Le `&&` court-circuite encore les écritures ; le conseil `mes_options.php` et les constantes définies par le kernel sont inchangés. Pas de nouvelle installation complète ; le blocage de la tâche de maintenance est confirmé par l'entrée 1. |
| 8 — protection de `var/` et `bin/` | Absence de protection toujours présente à la lecture | Appelants de `verifier_htaccess()` et `htaccess.txt` relus : aucune protection dédiée ; aucun `.htaccess` dans ces deux répertoires sur l'instance. Pas de nouvel essai Apache. |
| 9 — version CLI | Toujours reproduit | `bin/spip about` affiche `dev`, `bin/spip spip:info` affiche `5.0.0-beta`. |

Les essais HTTP et les installations complètes datés du 2026-10-18 dans les entrées suivantes restent
les observations historiques ; le tableau distingue les nouveaux essais des vérifications par lecture.

---

## 1. Les tâches `#[AsCronTask]` ne sont jamais exécutées par la file

- [ ] MR remontée :

**Dépôt :** `spip/spip` (`ecrire/inc/queue.php`), et `spip-league/pipeline-bridge` pour la seconde cause.
**Vérifié sur :** SPIP 5.0.0-beta, `spip/spip` `c8e329575`, `spip/ecrire` `218a10d1`
(tête de `5.x` au 2026-10-19), `spip-league/pipeline-bridge` `b80ffcead`, `spip-league/kernel` `e0e80032b`.
Toujours reproduit le 2026-10-19, y compris l'appel à `queue_start_job()` sur une ligne SQL de signature valide.
Déjà constaté avec `ecrire` `2cd5843f`, puis `26bfe470`.

**Symptôme :** une tâche déclarée par `#[AsCronTask]` est bien enregistrée (`spip.genie_registry`) et planifiée dans
`spip_jobs` (`inclure = 'service:'`), mais `queue_start_job()` renvoie `false` sans l'exécuter. Les tâches du cœur
(`optimiser`, `maintenance`, …) et des plugins-dist (`bigup_nettoyer_repertoire_upload`, …) sont touchées aussi. Rien
n'est journalisé (`queue.log` ne contient jamais `start (cron service)`).

**Deux causes, cumulées :**

1. `queue_genie_can_use_service_registry()` (`ecrire/inc/queue.php`, ligne 400) teste `function_exists('app')`.
   `app()` n'existe que dans l'espace de noms `SpipLeague\Component\Kernel` (`vendor/spip-league/kernel/boot.php`) ;
   le `use function SpipLeague\Component\Kernel\app;` du fichier ne change rien à `function_exists()`, qui attend le
   nom complet. La fonction renvoie donc toujours `false`.
2. Même avec ce test corrigé, `queue_start_cron_service_job()` vérifie `$container->has($task['service'])`. Les
   services de tâches sont privés (chargés par `load()` sans `->public()`) et `SpipGenieTaskPass` ne les rend pas
   publics : `has()` renvoie `false` et `get()` lève « service … has been removed or inlined ».

**Reproduction autonome** (instance 5.0 installée, PHP 8.4, à lancer depuis la racine du site).
Enregistrer ce script sous `repro-cron.php`, puis exécuter `php repro-cron.php` :

```php
<?php
require __DIR__ . '/vendor/autoload.php';
\SpipLeague\Component\Kernel\Kernel::boot();
require __DIR__ . '/ecrire/inc_version.php';
include_spip('base/abstract_sql');
include_spip('inc/queue');

// 1. Le registre contient bien la tâche « optimiser ».
$c = \Spip\Framework\Spip::app()->getContainer();
$tache = $c->get('spip.genie_registry')->get('optimiser');
echo "Tâche enregistrée : ";
var_dump($tache !== null);                           // true

// 2. Premier blocage : la file croit le registre indisponible.
echo "Fonction app globale : ";
var_dump(function_exists('app'));                    // false
echo "Fonction app du kernel : ";
var_dump(function_exists('SpipLeague\\Component\\Kernel\\app')); // true
echo "Registre utilisable par la file : ";
var_dump(queue_genie_can_use_service_registry());     // false

// 3. Second blocage, indépendant : le service n'est pas accessible.
echo "Service accessible depuis le conteneur : ";
var_dump($tache !== null && $c->has($tache['service'])); // false

// 4. Vérifier aussi le point d'entrée sur une tâche réellement planifiée.
$job = sql_fetsel('*', 'spip_jobs', "fonction='optimiser' AND inclure='service:'");
if (!$job) {
    echo "Aucune tâche optimiser/service: en base ; étape 4 non testée.\n";
} elseif (!queue_verify_signed_job($job)) {
    echo "Signature invalide ; étape 4 non concluante.\n";
} elseif (!queue_genie_can_use_service_registry()) {
    echo "Signature valide ; résultat de queue_start_job : ";
    var_dump(queue_start_job($job));                 // false
} else {
    echo "Premier blocage levé ; tâche non lancée par ce diagnostic.\n";
}
```

**Lecture du résultat :** la tâche existe dans le registre (`true`), mais la file refuse de l'utiliser (`false`).
Le `use function …\app` de `queue.php` permet d'appeler `app()` dans ce fichier ; il ne crée pas de fonction
**globale** nommée `app`, celle que cherche `function_exists('app')`. L'étape 3 montre séparément que corriger
ce test ne suffirait pas : le service de la tâche reste privé. L'étape 4 confirme le refus d'exécution dans la
file, après avoir exclu une signature invalide comme cause du `false`.

Sur l'instance testée le 2026-10-19, la ligne SQL existe, sa signature est valide et l'appel renvoie `false`.
Ce script ne crée ni ne retire de job ; dans l'état bogué vérifié, il n'exécute pas la tâche `optimiser`.
Supprimer `repro-cron.php` après l'essai.

**Pistes :** `function_exists('SpipLeague\Component\Kernel\app')` (ou tester directement la classe et le conteneur via
la façade `Spip::app()`) ; dans `SpipGenieTaskPass`, rendre les services tagués `spip.genie_task` publics
(`$container->getDefinition($id)->setPublic(true)`) ou passer par un `ServiceLocator` injecté dans
`spip.genie_registry`.

**Contournement côté plugin** (vérifié) : fonction legacy `genie/acme_xxx.php` (`genie_acme_xxx_dist()`, convention
legacy : `>= 0` succès) déclarée par un listener sur `taches_generales_cron`
(`TachesGeneralesCronEvent::addTask('acme_xxx', 86400)`) ; elle est exécutée (`inclure = 'genie/'`).

---

## 2. Le message de dépréciation de `Kernel::getRootDir()` cite une méthode inexistante

- [ ] MR remontée :

**Dépôt :** `spip-league/kernel` (`src/Kernel.php`, ligne 226). **Vérifié sur :** `spip-league/kernel` `e0e80032b`.

`#[\Deprecated(message: 'use "Spip::getProjectDir()" instead.', since: '5.0')]` : la façade `Spip\Framework\Spip`
n'a pas de méthode `getProjectDir()`. Le remplacement réel est `Spip::directories()->getProjectDir()`.

**Piste :** corriger le message en `use "Spip::directories()->getProjectDir()" instead.`

---

## 3. Pas d'installation ni d'activation de plugin scriptables en 5.0 (demande d'évolution, pas un bug)

- [ ] MR ou ticket remonté :

**Dépôts :** `spip/spip` (installation), `spip-league/console` (`bin/spip`). **Vérifié sur :** SPIP 5.0.0-beta,
`spip/spip` `c8e329575`, `spip/ecrire` `26bfe470` (2026-10-07), `spip/framework` `d0e8c97e` (dernières révisions
amont au 2026-10-18).

**Constat :** `bin/spip list` ne propose aucune commande pour installer le site (base, compte webmestre) ni pour
activer un plugin et lancer ses mises à jour de base : les seules commandes sont `about`, `config:lire` /
`config:ecrire`, `database:optimize`, `routes:list`, `sites:syndics:maj` et les `spip:*` (cache, conteneur,
pipelines). Aucune autre classe `#[AsCommand]` n'existe dans `ecrire/`, `plugins-dist/`, `vendor/spip/` ou
`vendor/spip-league/`. Le `composer.json` racine n'a pas de scripts. L'installation passe uniquement par les étapes
web `ecrire/install/etape_*.php`. spip-cli, qui couvre ces besoins en 4.x (`core:installer`, `plugins:activer`,
`plugins:maj:bdd`), charge `ecrire/inc_version.php` sans démarrer le Kernel (`src/Loader/Spip.php`, `e9043b8`), ce
qui échoue en 5.0 (`Undefined constant "_FILE_OPTIONS"`, constaté avec la même séquence de chargement).

**Conséquence :** un plugin 5.0 ne peut pas monter son environnement de tests d'intégration sans étape manuelle
(installeur web, activation dans l'espace privé). La CI d'un plugin est bloquée. Relevé en écrivant l'encadré 5.0 du
skill `spip-testing`, qui doit présenter ces deux étapes comme manuelles.

**Reproduction :**

```sh
composer create-project --repository=https://get.spip.net/composer spip/spip site 5.0.x-dev
cd site && php bin/spip list   # aucune commande d'installation ni d'activation
```

**Piste :** des commandes `bin/spip` équivalentes à `core:installer` (serveur SQL, base, préfixe, compte webmestre),
`plugins:activer` / `plugins:desactiver` et `plugins:maj:bdd`, utilisables en non interactif.

---

## 4. `$container->env()` vaut `null` dans le `config/services.php` d'un plugin

- [ ] MR remontée :

**Dépôt :** `spip-league/plugin-manager` (`src/Config/PluginSymfonyServicesCollector.php`, ligne 54).
**Vérifié sur :** SPIP 5.0.0-beta, `spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`, `spip-league/plugin-manager`
`689c9253c`, `spip-league/kernel` `e0e80032b` (dernières révisions amont au 2026-10-18).

**Symptôme :** dans le `config/services.php` d'un plugin, `$container->env()` renvoie `null`, quel que soit
`APP_ENV`. Dans le `config/custom.php` du site, il renvoie bien `'prod'` ou `'dev'`. Un plugin ne peut donc pas
adapter ses services ou ses paramètres à l'environnement (`if ($container->env() === 'dev')`), alors que la même
écriture fonctionne côté site.

**Cause :** `PluginSymfonyServicesCollector::loadIntoContainer()` crée le chargeur sans environnement :
`new PhpFileLoader($container, new FileLocator(dirname($configFile)))`. `SpipContainerFactory::createPhpFileLoader()`,
utilisé pour `custom.php` et les services du cœur, passe `$this->getEnvironment()` en troisième argument.

**Reproduction** (exécutée sur l'instance, plugin d'essai activé puis `bin/spip spip:cache:warmup --clear`) :

```php
// plugins/acme/config/services.php
return static function (ContainerConfigurator $container): void {
	$container->parameters()->set('acme.env_vu', var_export($container->env(), true));
	// …
};
```

Un listener `affichage_final` qui injecte `#[Autowire(param: 'acme.env_vu')]` affiche `NULL` ; avec `APP_ENV=dev`
(`.env.local`), `kernel.environment` vaut `dev` mais `acme.env_vu` reste `NULL`. La même ligne dans
`config/custom.php` donne `'prod'`, puis `'dev'`.

**Piste :** passer l'environnement au chargeur des plugins, par exemple
`new PhpFileLoader($container, new FileLocator(dirname($configFile)), $container->getParameter('kernel.environment'))`,
ou réutiliser `SpipContainerFactory::createPhpFileLoader()`.

**Contournement côté plugin :** lire l'environnement à l'exécution avec `#[Autowire(param: 'kernel.environment')]`,
ou laisser le site décider dans son `config/custom.php`, chargé après les plugins.

---

## 5. `#URL_ACTION_AUTEUR` avec un argument vide : erreur fatale au lieu d'une erreur de squelette

- [ ] MR remontée :

**Dépôt :** `spip/ecrire` (`balise/url_.php`, `balise_URL_ACTION_AUTEUR_dist()`).
**Vérifié sur :** SPIP 4.4.21 (`/src/jetables/v4.4.21`) et SPIP 5.0.0-beta (`spip/spip` `c8e329575`, `spip/ecrire`
`26bfe470`, tête amont de `5.x` au 2026-10-18), exécuté sur les deux ; même code (lecture) dans les tags `4.4.28` et
la tête de la branche `4.4`, `5f67f441b` (2026-10-08). PHP 8.4.25.

**Symptôme :** `[(#URL_ACTION_AUTEUR{mon_action,,#SELF})]` (ou `{mon_action,,}`, `{mon_action, ,#SELF}`) fait
échouer la page en HTTP 500 : `Uncaught TypeError: Cannot assign null to property
Spip\Compilateur\Noeud\Champ::$code of type string` (`balise/url_.php`, ligne 371 en 4.4.21, 363 en 4.4.28 et 5.0).
Le débusqueur ne s'affiche pas. Sur les autres balises, le même argument vide donne l'erreur de squelette prévue :
`[(#ENV{a,,b})]` ou `[(#URL_ECRIRE{a,,b})]` affichent « Filtre ,b}) non défini » (HTTP 503, page d'erreur du
squelette, 4.4.21).

**Cause :** `phraser_arg()` (`public/phraser_html.php`) refuse un argument vide : `zbug_erreur_filtre`, puis
`$p->param = false`. La compilation appelle quand même la balise. `interprete_argument_balise(1, $p)` renvoie alors
`null`, et `balise_URL_ACTION_AUTEUR_dist()` l'affecte directement à `$p->code`, typé `string` depuis la 4.x. C'est
la seule affectation de ce genre dans `ecrire/` et `plugins-dist/` sans repli : `public/balises.php` (ligne 1600 en
5.0) écrit `interprete_argument_balise(1, $p) ?? ''`.

**Reproduction** (squelette public, sans session) :

```html
[(#URL_ACTION_AUTEUR{mon_action,,#SELF})]   <!-- HTTP 500 -->
[(#URL_ACTION_AUTEUR{mon_action,'',#SELF})] <!-- spip.php?action=mon_action&arg=&hash=… -->
```

**Piste :** dans `balise_URL_ACTION_AUTEUR_dist()`, `$p->code = interprete_argument_balise(1, $p) ?? "''";`, ou
sortir tôt quand `$p->param === false` pour laisser l'erreur de squelette s'afficher. Plus général : ne pas appeler la
fonction de balise quand `phraser_arg()` a mis `param` à `false`.

**Contournement :** écrire l'argument vide `''`, ou `#ENV{vide}`. Documenté dans `spip-plugins`
(`references/actions.md`).

---

## 6. `SPIP_CACHE_DIR` casse le site, et `spip.dirs.tmp` absolu est tronqué

- [ ] MR remontée :

**Dépôts :** `spip-league/kernel` (`src/Directory/LegacyDirectoryConstants.php`, `src/Directory/SpipDirectories.php`,
`src/SpipContainerFactory.php`). **Vérifié sur :** SPIP 5.0.0-beta, `spip/spip` `c8e329575`, `spip/ecrire`
`26bfe470`, `spip/framework` `d0e8c97e`, `spip-league/kernel` `e0e80032b` (dernières révisions amont au
2026-10-18), PHP 8.4.25. Exécuté sur une copie jetable de l'instance (compte rendu :
`docs/tests/spip5-instance-verifications.md`, section « Directories », branche `feat/spip5-instance`, PR #40).

**Symptôme 1 :** `SPIP_CACHE_DIR=/chemin/absolu/` dans `.env.local` (variable documentée dans
`guides/migration/environnement-kernel.md`), puis `bin/spip spip:cache:warmup --clear` : toutes les requêtes HTTP
renvoient 500, « Artefacts boot absents : options.php introuvable. Le cache boot doit être régénéré en phase
pré-HTTP. » (`vendor/spip/framework/src/Boot/BootOrchestrator.php:32`).

**Cause 1 :** la compilation (`bin/spip`) écrit `boot/` et le conteneur dans `SPIP_CACHE_DIR`
(`SpipDirectories::getCacheDir()`, `SpipContainerFactory::cacheDir()`), mais côté HTTP
`LegacyDirectoryConstants::defineAll()` définit `_DIR_CACHE = $dirTmp . 'cache/'` et `_DIR_CACHE_BOOT =
_DIR_CACHE . 'boot/'` sans tenir compte de `SPIP_CACHE_DIR`. `PluginBootCache::fromGlobals()` cherche donc
`tmp/cache/boot/options.php`.

**Symptôme 2 :** `spip.dirs.tmp` défini en chemin absolu hors du projet dans `config/custom.php` (par exemple
`/srv/site-tmp/`) : 500 également. Une trace temporaire dans `BootOrchestrator` montre
`src/srv/site-tmp/cache/boot/options.php` (le `/` initial a disparu).

**Cause 2 (précisée le 2026-10-19) :** `SpipDirectories::resolveDir()` passe le chemin configuré à
`Path::join($basePath, $relative)`, même s'il est absolu. Avec `/srv/site-tmp/`, cela produit
`<projet>/srv/site-tmp/`. `SpipRelativeDirectories::toRelative()` le relativise ensuite correctement en
`srv/site-tmp/` : la perte du `/` initial observée vient donc de la résolution en amont, pas de cette relativisation.
Par ailleurs `SpipContainerFactory::cacheDir()` ignore `spip.dirs.tmp` : le conteneur reste dans `<site>/tmp/cache`.

**Reproduction :**

```bash
echo 'SPIP_CACHE_DIR=/srv/site-cache/' >> .env.local
bin/spip spip:cache:warmup --clear
curl -s -o /dev/null -w '%{http_code}\n' http://site.local/      # 500
```

**Contournement vérifié :** `spip.dirs.tmp` **relatif** (`'../site-tmp/'`) dans `config/custom.php` et
`SPIP_CACHE_DIR=<même répertoire absolu>/cache/` dans `.env.local`, puis warmup : pages publiques 200, `ecrire/` 302,
plus rien n'est écrit dans le `tmp/` du projet.

**Pistes :** dériver `_DIR_CACHE` / `_DIR_CACHE_BOOT` de `SpipDirectories::getCacheDir()` (relativisé) ; faire
respecter les chemins absolus par `resolveDir()` avant leur relativisation ; faire suivre `spip.dirs.tmp` à `cacheDir()`.

---

## 7. L'installeur s'arrête au premier `.htaccess` inopérant, avec un conseil obsolète

- [ ] MR remontée :

**Dépôt :** `spip/ecrire` (`install/etape_fin.php`, `install_verifier_htaccess()` ; `inc/acces.php`,
`verifier_htaccess()`). **Vérifié sur :** SPIP 5.0.0-beta, `spip/ecrire` `26bfe470`, deux installations neuves
exécutées (SQLite) : serveur PHP intégré, puis Apache 2.4.52 + PHP-FPM 8.4.

**Symptôme :** sous un serveur qui ignore les `.htaccess` (nginx, serveur PHP intégré), la fin d'installation n'écrit
que `tmp/.htaccess` et affiche « htaccess inopérant ». `config/` et `vendor/` n'ont aucun fichier de refus. Sous
Apache, mais sans `config/.htaccess`, une requête directe sur `config/bases/spip.sqlite` télécharge la base (200,
2,5 Mo, en-tête `SQLite format 3`).

**Causes :**

1. `install_verifier_htaccess()` enchaîne `verifier_htaccess(_DIR_TMP, true) && verifier_htaccess(_DIR_CONNECT, true)
   && verifier_htaccess(_DIR_VENDOR, true)` : le `&&` s'arrête au premier échec, donc les deux autres fichiers ne sont
   même pas écrits, alors qu'ils resteraient utiles le jour où le serveur applique les `.htaccess`.
2. Le message (`htaccess_a_simuler`) conseille de définir `_DIR_TMP` et `_DIR_CONNECT` dans `mes_options.php`. En 5.0,
   ces constantes sont définies par le kernel avant `mes_options.php` : les redéfinir provoque une erreur fatale
   (« Constant _DIR_TMP already defined », HTTP 500, vérifié).
3. La tâche qui réécrit ces fichiers (`ecrire/src/Cron/Task/MaintenanceTask.php`) est une tâche `#[AsCronTask]`, que
   la file ne lance pas (entrée 1).

**Pistes :** écrire les trois fichiers sans s'arrêter au premier échec, puis tester ; remplacer le conseil par les
réglages 5.0 (`SPIP_ETC_DIR`, `spip.dirs.*`) ou par une règle de serveur web.

---

## 8. `var/` et `bin/` ne sont protégés par aucun fichier de refus

- [ ] MR ou ticket remonté :

**Dépôts :** `spip/ecrire` (`verifier_htaccess()` appelants), `spip/spip` (`htaccess.txt`). **Vérifié sur :** SPIP
5.0.0-beta, `spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`, Apache 2.4.52, installation neuve complète (les trois
`.htaccess` écrits par l'installeur) et `htaccess.txt` copié en `.htaccess`.

**Constat :** `var/state/plugins.php` (manifeste des plugins actifs, chemins de l'instance) et `bin/spip` répondent
200. Le cœur n'écrit de `.htaccess` que dans `tmp/`, `config/` et `vendor/`, et `htaccess.txt` ne bloque que les
fichiers commençant par un point et `composer.(json|lock|phar)`.

**Contournement vérifié :** `RedirectMatch 404 ^/(var|bin)/` sous la ligne `composer.*` du `.htaccess` : les deux
chemins passent en 404, le site et `ecrire/` restent fonctionnels.

**Piste :** ajouter `var/` et `bin/` à `verifier_htaccess()` (ou la règle ci-dessus à `htaccess.txt`).

---

## 9. `bin/spip about` affiche « dev » au lieu de la version de SPIP

- [ ] MR remontée :

**Dépôt :** `spip-league/console` (`src/Command/AboutCommand.php`). **Vérifié sur :** `spip-league/console`
`c5b9d472a`, SPIP 5.0.0-beta (`spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`).

**Symptôme :** `bin/spip about` affiche `SPIP dev`, alors que `bin/spip spip:info` affiche `Version SPIP
5.0.0-beta`.

**Cause :** `AboutCommand` lit le paramètre `spip.version`, qui n'est défini nulle part (`spip:config:dump` ne le
liste pas), puis se rabat sur `getApplication()->getVersion()`, qui vaut `dev`.

**Piste :** déclarer `spip.version` (depuis `Spip\SpipVersion`) ou lire `SpipVersion` directement.
