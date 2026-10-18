# Bugs relevés dans le code de SPIP

Bugs du **code** de SPIP et de ses paquets (MR à ouvrir sur `git.spip.net`), relevés en écrivant les skills.
Les erreurs de la **documentation** (`spip/docs`) sont notées à part dans
[2026-10-17-spip-code-hors-doc.md](2026-10-17-spip-code-hors-doc.md).

Chaque entrée indique la version vérifiée, l'emplacement, une reproduction et une piste de correction. Cocher
l'entrée et ajouter le lien de la MR une fois remontée.

---

## 1. Les tâches `#[AsCronTask]` ne sont jamais exécutées par la file

- [ ] MR remontée :

**Dépôt :** `spip/spip` (`ecrire/inc/queue.php`), et `spip-league/pipeline-bridge` pour la seconde cause.
**Vérifié sur :** SPIP 5.0.0-beta, `spip/spip` `c8e329575` (2026-09-22), avec `ecrire` `2cd5843f` (lock de ce
commit) puis `ecrire` `26bfe470` (2026-10-07, dernière révision amont au 2026-10-18), `spip-league/pipeline-bridge`
`b80ffcead`, `spip-league/kernel` `e0e80032b`. Toujours présent le 2026-10-18 : sur `26bfe470`,
`queue_genie_can_use_service_registry()` renvoie `false` et `$c->has('Spip\Cron\Task\OptimiserTask')` renvoie
`false` (reproduction ci-dessous exécutée, hors appel à `queue_start_job()`).

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

**Reproduction** (instance 5.0, PHP 8.4, script amorcé comme `bin/spip`) :

```php
include_spip('base/abstract_sql');
include_spip('inc/queue');
var_dump(queue_genie_can_use_service_registry());          // false
$row = sql_fetsel('*', 'spip_jobs', "fonction='optimiser'");
var_dump(queue_start_job($row));                           // false, tâche non exécutée
$c = \Spip\Framework\Spip::app()->getContainer();
$t = $c->get('spip.genie_registry')->get('optimiser');
var_dump($c->has($t['service']));                          // false
```

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
