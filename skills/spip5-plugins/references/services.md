# Services to inject

Inside a class (listener, task, Guard rule, service), take dependencies in the constructor. The `Spip::` façade is
for procedural code only (`*_fonctions.php`, filters, `genie_*()`, `action/`, `formulaires/*.php`).

---

## What autowires, what needs `#[Autowire]` (verified on 5.0.0-beta)

| Need | Constructor argument | How |
|---|---|---|
| Logger on the plugin channel | `Psr\Log\LoggerInterface $logger` | `#[Autowire(service: 'spip.acme.logger')]` + channel declared in `services.php` |
| Main logger | `Psr\Log\LoggerInterface $logger` | `#[Autowire(service: 'spip.logger')]` |
| Config (metas) | `SpipLeague\Component\Config\Repository\ConfigRepositoryInterface $config` | `#[Autowire(service: 'spip.config')]` |
| All config stores | `SpipLeague\Component\Config\Manager\ConfigManagerInterface $configs` | `#[Autowire(service: 'spip.config.manager')]` |
| Logger registry | `SpipLeague\Component\Logger\LoggerRegistry $loggers` | `#[Autowire(service: 'spip.logger_registry')]` |
| Request | `Symfony\Component\HttpFoundation\RequestStack $requestStack` | by type |
| Inputs (`_request()` replacement) | `SpipLeague\Bridge\Http\Inputs $inputs` | by type — `get($key)`, `has($key)`, `set($key, $val)` |
| Platform (CLI, cwd, log dir) | `SpipLeague\Component\Kernel\PlatformInterface $platform` | by type |
| Directories | `SpipLeague\Component\Kernel\Directory\SpipDirectoriesInterface $dirs` | by type |
| Another class of the plugin | `Acme\Import\Importeur $importeur` | by type, if covered by `load()` |

`#[Autowire]` is `Symfony\Component\DependencyInjection\Attribute\Autowire`. A plain `LoggerInterface` or
`ConfigRepositoryInterface` argument fails with "Cannot autowire service … no such service exists".

## Example (verified)

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
			$this->logger->error('acme/url_flux non configuré');   // → tmp/log/acme.log
			return false;
		}
		// …
		return true;
	}
}
```

Log channel, in the plugin's `config/services.php`:

```php
$services->set('spip.acme.logger', LoggerInterface::class)
	->factory([service('spip.logger_registry'), 'get'])
	->args(['acme']);
```

Levels are PSR-3 (`debug` … `emergency`). Default `spip.logger.max_level` is `notice`: `info()` and `debug()` are
dropped unless the site sets `spip.logger.max_level` in `config/custom.php`.

## Procedural code

| Need | Façade |
|---|---|
| log | `Spip::logger('acme')->error($msg)` |
| config | `Spip::config('acme/url_flux', $defaut)`, `Spip::configs()->set('acme/x', $v)` |
| input | `Spip::input('id_article')`, `Spip::request()` |
| directories, platform | `Spip::directories()->getProjectDir()`, `Spip::platform()->isCli()` |
| back/front office | `Spip::isBackOffice()`, `Spip::isFrontOffice()` |

`Spip` is `Spip\Framework\Spip` (global alias `Spip`). `Spip::param('spip.x')` and `Spip::service('id')` exist for
procedural code (only public services); in a class, inject the parameter (`#[Autowire(param: 'spip.x')]`) or the
service. The global helpers `SpipLeague\Component\Kernel\param()` / `service()` are deprecated.
