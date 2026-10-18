# spip5-plugins GREEN — Q3 raw answer (re-run 2026-10-18)

Fresh subagent restricted to `skills/spip5-plugins/` at `acfc29b`, prompt of case 3 only, without the expectations.
Verbatim answer below; scoring in `spip5-plugins-green.md`.

---

## Tâche planifiée quotidienne de synchronisation (plugin Acme, SPIP 5.0)

En SPIP 5.0, une tâche planifiée se déclare avec l'attribut `#[AsCronTask]`, posé sur une classe-service. Elle ne passe plus par `<genie>` dans `paquet.xml`, ni par le pipeline `taches_generales_cron`, ni par un fichier `genie/`.

**Attention, point important.** Sur **SPIP 5.0.0-beta** (`ecrire` `c8e329575`), un bug du cœur empêche la file d'attente d'exécuter les tâches déclarées par attribut, y compris celles du cœur. Je vous donne donc deux versions :

1. **La version cible, en 5.0** (`#[AsCronTask]`). C'est celle à utiliser dès que le bug est corrigé.
2. **Un contournement temporaire** pour la beta : une tâche `genie_*` classique, déclarée par un listener. Elle sert uniquement à compenser ce bug du cœur et sera à retirer ensuite.

**N'activez jamais les deux en même temps pour le même nom de tâche (`acme_synchro`).** Le code ci-dessous utilise le **contournement**, parce que c'est le seul qui s'exécute réellement sur la beta.

---

## 0. Socle du plugin

`paquet.xml` (sans `<genie>`, sans `<pipeline>`) :

```xml
<paquet prefix="acme" categorie="outil" version="1.0.0" etat="dev" compatibilite="[5.0.0-dev;5.*]">
	<nom>Acme</nom>
	<auteur>…</auteur>
	<licence>GPL 3</licence>
</paquet>
```

`composer.json` :

```json
{
  "name": "acme/acme",
  "type": "spip-plugin",
  "require": { "spip/framework": "^5.x-dev" },
  "autoload": { "psr-4": { "Acme\\": "src/" } }
}
```

`config/services.php` :

```php
<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
	// Paramètre du plugin : la synchro est active par défaut (un site peut le surcharger).
	$container->parameters()->set('acme.synchro.enabled', true);

	$services = $container->services()
		->defaults()
		->autowire()
		->autoconfigure();          // lit #[AsCronTask], #[AsPipelineListener]

	$services->load('Acme\\', '../src/');

	// Canal de log "acme" → tmp/log/acme.log
	$services->set('spip.acme.logger', LoggerInterface::class)
		->factory([service('spip.logger_registry'), 'get'])
		->args(['acme']);
};
```

---

## 1. Version cible 5.0 : `#[AsCronTask]` (à activer quand le bug sera corrigé)

### Le service qui synchronise

`src/Import/Importeur.php` :

```php
<?php

declare(strict_types=1);

namespace Acme\Import;

use Psr\Log\LoggerInterface;
use SpipLeague\Component\Config\Repository\ConfigRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class Importeur
{
	public function __construct(
		#[Autowire(service: 'spip.acme.logger')]
		private readonly LoggerInterface $logger,
		#[Autowire(service: 'spip.config')]
		private readonly ConfigRepositoryInterface $config,
	) {}

	public function importer(): bool
	{
		$url = (string) $this->config->get('acme/url_flux', '');
		if ($url === '') {
			$this->logger->error('acme/url_flux non configuré');
			return false;
		}

		include_spip('inc/distant');
		$res = recuperer_url($url, ['taille_max' => 5 * 1024 * 1024]);

		if (!$res || (int) ($res['status'] ?? 0) !== 200 || empty($res['page'])) {
			// Échec réseau ou HTTP : on journalise et on signale l'échec à la file
			$this->logger->error('Synchro du flux en échec', [
				'url' => $url,
				'status' => $res['status'] ?? null,
			]);
			return false;
		}

		// … analyse de $res['page'] et enregistrement en base …

		$this->logger->notice('Synchro du flux terminée');
		return true;
	}
}
```

Points à noter :

- Le logger et la config sont **injectés**, avec `#[Autowire(service: …)]`. Un argument `LoggerInterface` ou `ConfigRepositoryInterface` sans cet attribut fait échouer la compilation du conteneur, donc tout le site tombe.
- Dans une classe, on n'utilise pas `spip_log()` ni `_request()`.
- Le niveau de log par défaut est `notice` : un `info()` ou un `debug()` serait ignoré.

### La tâche

`src/Cron/Task/SynchroTask.php` :

```php
<?php

declare(strict_types=1);

namespace Acme\Cron\Task;

use Acme\Import\Importeur;
use SpipLeague\Bridge\Pipeline\Attribute\AsCronTask;

#[AsCronTask(name: 'acme_synchro', periode: 86400)]   // une fois par jour
final class SynchroTask
{
	public function __construct(private readonly Importeur $importeur) {}

	public function __invoke(): int
	{
		// Convention Unix : 0 = succès, > 0 = échec → la tâche est replanifiée
		// immédiatement, avec une priorité plus basse.
		return $this->importeur->importer() ? 0 : 1;
	}
}
```

**Attention, la convention de retour est inversée** par rapport à l'ancien `genie_*()` :

- avec `#[AsCronTask]`, `0` signifie succès et une valeur `> 0` signifie échec ;
- avec `genie_*()`, une valeur `>= 0` signifie succès et une valeur `< 0` signifie échec.

Si le réseau tombe, la tâche renvoie `1` et SPIP la relance au prochain passage du cron. Vous n'avez pas besoin d'écrire de logique de réessai vous-même.

---

## 2. Contournement temporaire pour la 5.0.0-beta (code actif aujourd'hui)

**Le bug.** Sur la 5.0.0-beta, `queue_start_job()` renvoie `false` pour tous les jobs `service:`. Il y a deux causes dans le cœur :

- `ecrire/inc/queue.php` teste `function_exists('app')`, qui vaut `false` depuis que `app()` est dans un namespace ;
- les services de tâche sont privés, donc `$container->has()` renvoie `false`.

Résultat : une tâche `#[AsCronTask]` est bien enregistrée, mais elle **ne s'exécute jamais**. Ce n'est pas une erreur de votre code. Tant que ce bug n'est pas corrigé, on revient à une tâche classique, déclarée par un listener.

**Pendant le contournement**, ne gardez **pas** `SynchroTask` dans `src/`. Vous pouvez la déplacer hors de `src/`, ou retirer son attribut. Sinon le nom `acme_synchro` serait enregistré deux fois.

### La fonction genie

`genie/acme_synchro.php` :

```php
<?php

// Contournement temporaire du bug de la 5.0.0-beta (tâches #[AsCronTask] non exécutées).
// Convention legacy : >= 0 succès, < 0 échec (la tâche sera relancée).
function genie_acme_synchro_dist($t) {
	include_spip('inc/acme');      // code procédural : on passe par la façade Spip::
	return acme_synchroniser() ? 1 : -1;
}
```

### La logique de synchronisation, en procédural

`inc/acme.php` :

```php
<?php

function acme_synchroniser(): bool {
	$url = (string) Spip::config('acme/url_flux', '');
	if ($url === '') {
		Spip::logger('acme')->error('acme/url_flux non configuré');
		return false;
	}

	include_spip('inc/distant');
	$res = recuperer_url($url, ['taille_max' => 5 * 1024 * 1024]);

	if (!$res || (int) ($res['status'] ?? 0) !== 200 || empty($res['page'])) {
		Spip::logger('acme')->error('Synchro du flux en échec : ' . $url . ' (status ' . ($res['status'] ?? '?') . ')');
		return false;
	}

	// … analyse et enregistrement …

	Spip::logger('acme')->notice('Synchro du flux terminée');
	return true;
}
```

Dans ce code procédural, on utilise la façade `Spip::` : `Spip::logger()` et `Spip::config()`. `spip_log()` est déprécié en 5.0.

### Le listener qui déclare la tâche

`src/Pipeline/Listener/TachesListener.php` :

```php
<?php

declare(strict_types=1);

namespace Acme\Pipeline\Listener;

use Spip\Framework\Pipeline\Event\TachesGeneralesCronEvent;
use SpipLeague\Bridge\Pipeline\Attribute\AsPipelineListener;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TachesListener
{
	public function __construct(
		#[Autowire(param: 'acme.synchro.enabled')]
		private readonly bool $synchroActive,
	) {}

	#[AsPipelineListener]
	public function onTachesGeneralesCron(TachesGeneralesCronEvent $event): void
	{
		if (!$this->synchroActive) {
			return;                              // désactivée sur ce site (ex. dev)
		}
		$event->addTask('acme_synchro', 86400);  // une fois par jour
	}
}
```

---

## 3. Désactiver la tâche sur un site de développement

Le réglage se fait dans le fichier **`config/custom.php` du site**. N'utilisez ni `config/spip/*.php`, ni `options.php`, ni le code du plugin. Après chaque modification : `bin/spip spip:cache:warmup --clear`.

### Avec le contournement (situation actuelle sur la beta)

Les paramètres `spip.cron.acme_synchro.*` agissent **uniquement** sur le registre des tâches par attribut. Ils ne désactivent **pas** la tâche déclarée par `taches_generales_cron`. On utilise donc le paramètre du plugin, que le listener lit :

```php
<?php
// config/custom.php du site

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
	if ($container->env() === 'dev') {
		$container->parameters()->set('acme.synchro.enabled', false);
	}
};
```

### Avec la version `#[AsCronTask]` (après correction du bug)

```php
return static function (ContainerConfigurator $container): void {
	if ($container->env() === 'dev') {
		// la tâche est retirée du registre (spip.genie_registry)
		$container->parameters()->set('spip.cron.acme_synchro.enabled', false);
	}
	// Autre option : changer la période sans toucher au plugin
	// $container->parameters()->set('spip.cron.acme_synchro.period', 3600);
};
```

Ce comportement a été vérifié sur une instance : avec `APP_ENV=dev`, la tâche disparaît de `spip.genie_registry`, alors qu'en `prod` elle reste.

Pour couvrir les deux cas pendant la transition, vous pouvez mettre les deux `set()` dans le même bloc `if`.

---

## 4. Vérifier

```bash
bin/spip spip:cache:warmup --clear        # obligatoire après toute nouvelle classe / attribut / services.php
bin/spip spip:pipeline:listeners          # TachesListener::onTachesGeneralesCron doit apparaître
bin/spip spip:config:dump                 # valeur effective de acme.synchro.enabled / spip.cron.*
bin/spip -e dev spip:config:dump          # idem en environnement dev
```

- Les erreurs de synchro s'écrivent dans `tmp/log/acme.log`.
- Pour la version par attribut, une fois le cœur corrigé, `queue_genie_can_use_service_registry()` doit renvoyer `true` :

  ```php
  include_spip('inc/queue');
  var_dump(queue_genie_can_use_service_registry());
  ```

  Ensuite, `start (cron service)` doit apparaître dans `tmp/log/queue.log` après l'heure prévue de la tâche. À ce moment-là :

  1. supprimez `genie/acme_synchro.php`, `inc/acme.php` (si plus utile) et `TachesListener` ;
  2. remettez `SynchroTask` dans `src/` ;
  3. lancez un warmup.

## Gaps

- **`cron.md`**
  - Pour le contournement, le fichier dit « services are not reachable from here » (depuis `genie_*`).
  - Or `services.md` indique que `Spip::service('id')` existe pour les services publics.
  - Cela pose une question non tranchée : peut-on rendre `Importeur` public et l'appeler depuis le genie, pour éviter de dupliquer la logique ? J'ai dupliqué la logique en procédural, par prudence.
- **`cron.md`**
  - Il n'y a pas d'exemple complet de classe listener pour le contournement : seulement la méthode, sans constructeur ni `use`.
  - La lecture d'un paramètre de plugin dans ce listener (`#[Autowire(param: …)]`) est déduite de `services.md`. Elle n'est pas montrée dans `cron.md`.
- **`cron.md` et `squelette-plugin.md`**
  - La surcharge d'un paramètre de plugin (`acme.synchro.enabled`, défini dans le `config/services.php` du plugin) par le `config/custom.php` du site n'est pas documentée : on ne sait pas lequel l'emporte.
  - Seule la surcharge des paramètres `spip.cron.*` est vérifiée.
- **`cron.md` et `outils.md`**
  - On ne sait pas comment passer un site en `APP_ENV=dev` : fichier `.env` ? variable serveur ?
  - Seule l'option `-e/--env` de `bin/spip` est mentionnée. Le comportement de `$container->env()` côté web n'est pas expliqué.
- **`cron.md`**
  - La mécanique de réessai après un échec n'est pas précisée : « re-planned immediately with a lower priority », sans délai ni nombre maximal de tentatives. On ne sait pas s'il y a un risque de marteler un serveur distant en panne.
  - Rien non plus sur la reprogrammation d'un genie legacy qui renvoie une valeur `< 0`.
- **Aucun fichier**
  - Il n'y a pas d'information sur le client HTTP à utiliser en 5.0 : `recuperer_url()` de `inc/distant` existe-t-il toujours ? Symfony HttpClient est-il disponible ou injectable ?
  - J'ai utilisé `recuperer_url()` en m'appuyant sur ma connaissance de SPIP 4.
- **`listeners-events.md`**
  - Le namespace de `TachesGeneralesCronEvent` est supposé être `Spip\Framework\Pipeline\Event\`, d'après la règle générale du tableau.
  - Il n'est pas montré explicitement dans `cron.md`.
