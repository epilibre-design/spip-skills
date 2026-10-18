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
- Return code, **Unix convention**: `0` = success; `> 0` = failure (see *When a task fails*). The legacy `genie_*()`
  convention is the opposite (`>= 0` success, `< 0` failure).
- The queue calls the method with one positional argument, the legacy `$t` (date of the previous run, a Unix
  timestamp): declare `__invoke(int $t = 0)` to read it, or leave it out — PHP drops the extra argument. Read in
  `ecrire/inc/queue.php`, not run (see the bug below).
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

Verified: with `APP_ENV=dev` the task disappears from `spip.genie_registry`; in `prod` it stays. How to switch a site
to `dev`: `outils.md`, *Environment*.

Test the environment in the **site's** `config/custom.php`, never in the plugin's `config/services.php`: there
`$container->env()` returns `null` (the plugin loader gets no environment). The site file is loaded after every
plugin, so a parameter it sets wins over the plugin's own value (verified on `acme.*` parameters).

## When a task fails

Read in `queue_close_job()` and verified with a legacy task returning `-1`, three times in a row:

- the job is put back **at once** (date = now), its priority lowered by 1 at each failure (`0`, `-1`, `-2`, …);
- it runs again at the next cron hit — any page view, at most one per second: no delay, **no retry limit**. A remote
  service that is down gets called on every visit;
- on the retry, `$t` is no longer a date: it holds the absolute value of the return code (`1` here).

To space the retries, the return value sets the "previous run" date; the next run is that date + the period. Wait
600 s after a failure:

```php
// legacy genie_*(), declared with a period of 3600 s
return -(time() - 3600 + 600);    // verified: job dated now + 600 s

// #[AsCronTask] service, same rule with a positive value (from the code, not run: see the bug below)
return time() - 3600 + 600;
```

## Known bug in 5.0.0-beta — attribute tasks never run

The queue does **not** execute `#[AsCronTask]` services, core tasks included: `queue_start_job()` returns `false`
for every job with `inclure = 'service:'`. Two causes in the core (`ecrire/inc/queue.php` tests
`function_exists('app')`, false since `app()` is namespaced; task services are private so `$container->has()` is
false). Seen on `spip/spip` `c8e329575` (`ecrire` `2cd5843f`), still present on `ecrire` `26bfe470`, the upstream
head on 2026-10-18.

Check before relying on a task:

```php
include_spip('inc/queue');
var_dump(queue_genie_can_use_service_registry());   // must be true
```

and look for `start (cron service)` in `tmp/log/queue.log` after the task's date.

Workaround while the bug is present (verified on the beta) — the legacy task, declared by a listener. The genie can
call a plugin service through `Spip::service()` as long as that service is **public**:

```php
// config/services.php — inside the closure of squelette-plugin.md
$container->parameters()->set('acme.synchro.enabled', true);   // the site may override it in config/custom.php
$services->load('Acme\\', '../src/');
$services->set(\Acme\Import\Importeur::class)->public();       // reachable by Spip::service()
```

```php
<?php
// genie/acme_synchro.php — legacy convention: >= 0 success, < 0 failure

function genie_acme_synchro_dist($t) {
	$importeur = Spip::service(\Acme\Import\Importeur::class);
	return $importeur->importer() ? 1 : -1;
}
```

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
		if ($this->synchroActive) {
			$event->addTask('acme_synchro', 86400);
		}
	}
}
```

Without `->public()`, `Spip::service()` throws "service … has been removed or inlined": the genie dies and its job
stays *pending*. The first call to `queue_update_next_job_time()` once it is more than 180 s old closes it with the
default result `0` — a success for the legacy convention — so the task is replanned one **normal period after that
recovery**, not at once as after `return -1` (verified with a pending job dated 200 s back: replanned at recovery +
3600 s, priority 0). The recovery writes `queue_close_job car _JQ_PENDING depuis +180s` to `tmp/log/job_mort.log`;
the fatal error itself goes to the PHP error log.

`TachesGeneralesCronEvent` is `@deprecated` in favour of `#[AsCronTask]`: PHPStan flags the listener, nothing is
logged at runtime. Expected while the workaround is in place; it goes away with it.

Use one or the other, never both for the same name. Tell the user which one the code uses and why.
`spip.cron.<name>.*` parameters act on the attribute registry only: they do not disable the legacy workaround — the
listener skips `addTask()` on the plugin parameter instead, as above.
