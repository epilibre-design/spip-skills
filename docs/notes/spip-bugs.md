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
**Vérifié sur :** SPIP 5.0.0-beta, `ecrire` `c8e329575` (2026-09-22, dernier commit amont au 2026-10-17),
`spip-league/pipeline-bridge` `b80ffcead`, `spip-league/kernel` `e0e80032b`.

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
