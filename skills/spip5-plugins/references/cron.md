# Scheduled tasks

---

## The 5.0 way

```php
<?php

declare(strict_types=1);

namespace Acme\Cron\Task;

use Acme\Import\Importeur;
use SpipLeague\Bridge\Pipeline\Attribute\AsCronTask;

#[AsCronTask(name: 'acme_synchro', periode: 86400)]
final class SynchroTask
{
	public function __construct(private readonly Importeur $importeur) {}

	public function __invoke(): int
	{
		return $this->importeur->importer() ? 0 : 1;   // 0 = success, > 0 = failure
	}
}
```

- `AsCronTask(string $name, int $periode = 0, int $priorite = 0, string $method = '__invoke')`, repeatable, on a
  class or a method. The class is a service: it must be covered by `load()` in `config/services.php`.
- Return code, **Unix convention**: `0` = success; `> 0` = failure → the job is re-planned immediately with a lower
  priority. The legacy `genie_*()` convention is the opposite (`>= 0` success, `< 0` failure).
- `periode: 0` → registered but **never scheduled**. Keep periods above 30 s.
- No `<genie>` in `paquet.xml`, no `taches_generales_cron` entry, no `genie/` file.

## Parameters (site's `config/custom.php`, then warmup)

```php
return static function (ContainerConfigurator $container): void {
	if ($container->env() === 'dev') {
		$container->parameters()->set('spip.cron.acme_synchro.enabled', false);   // removed from the registry
	}
	// $container->parameters()->set('spip.cron.acme_synchro.period', 3600);     // overrides periode
};
```

Verified: with `APP_ENV=dev` the task disappears from `spip.genie_registry`; in `prod` it stays.

## Known bug in 5.0.0-beta (`c8e329575`) — attribute tasks never run

The queue does **not** execute `#[AsCronTask]` services, core tasks included: `queue_start_job()` returns `false`
for every job with `inclure = 'service:'`. Two causes in the core (`ecrire/inc/queue.php` tests
`function_exists('app')`, false since `app()` is namespaced; task services are private so `$container->has()` is
false). Not fixed upstream as of 2026-10-17.

Check before relying on a task:

```php
include_spip('inc/queue');
var_dump(queue_genie_can_use_service_registry());   // must be true
```

and look for `start (cron service)` in `tmp/log/queue.log` after the task's date.

Workaround while the bug is present (verified on the beta) — the legacy task, declared by a listener:

```php
// genie/acme_synchro.php — legacy convention: >= 0 success, < 0 failure
function genie_acme_synchro_dist($t) {
	include_spip('inc/acme');                 // procedural code: services are not reachable from here
	return acme_synchroniser() ? 1 : -1;
}

// src/Pipeline/Listener/TachesListener.php
#[AsPipelineListener]
public function onTachesGeneralesCron(TachesGeneralesCronEvent $event): void
{
	$event->addTask('acme_synchro', 86400);
}
```

Use one or the other, never both for the same name. Tell the user which one the code uses and why.
`spip.cron.<name>.*` parameters act on the attribute registry only: they do not disable the legacy workaround — make
the listener skip `addTask()` on a plugin parameter instead.
