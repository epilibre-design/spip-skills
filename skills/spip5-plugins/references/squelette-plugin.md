# A minimal SPIP 5.0 plugin

Real examples to read: `plugins-dist/spip/bigup/`, `plugins-dist/spip/medias/` (5.0).

---

## Tree

```
plugins/acme/
├── paquet.xml
├── composer.json
├── config/
│   ├── services.php          services, listeners, tasks, Guard rules, parameters
│   └── menus.php             optional — private-space menu entries (onglets.php for tabs)
├── src/
│   ├── Pipeline/Listener/    #[AsPipelineListener] classes
│   ├── Cron/Task/            #[AsCronTask] classes
│   └── Guard/                #[AsGuard] rules
├── lang/acme_fr.php          return [ 'cle' => 'Texte', ];
└── prive/squelettes/contenu/ private pages
```

## `paquet.xml`

```xml
<paquet
	prefix="acme"
	categorie="outil"
	version="1.0.0"
	etat="dev"
	compatibilite="[5.0.0-dev;5.*]"
>
	<nom>Acme</nom>
	<auteur>…</auteur>
	<licence>GPL 3</licence>
</paquet>
```

- `[5.0.0;5.*]` is **refused** on 5.0.0-beta (`plugin_version_compatible()` returns `false`); `[5.0.0-dev;5.*]` is
  what the 5.0 plugins-dist use. Tighten after the 5.0.0 release.
- No `<pipeline>`, `<genie>`, `<menu>`, `<script>`, `<style>`: listeners, tasks, `config/menus.php` and
  `insert_head`/`header_prive` listeners replace them.

## `composer.json`

```json
{
  "name": "acme/acme",
  "type": "spip-plugin",
  "require": { "spip/framework": "^5.x-dev" },
  "autoload": { "psr-4": { "Acme\\": "src/" } }
}
```

A plugin activated through the back office (SVP) is **not** installed by Composer: SPIP reads only its
`autoload.psr-4` (`PluginAutoloadCollector`) and compiles a classmap (`tmp/cache/boot/classmap.php`). Its `require`
are not installed — embed a `vendor/` or install the plugin through the site's root `composer.json`. A PSR-4 prefix
already known to Composer is skipped.

Keep `"spip/framework": "^5.x-dev"`: it is what every 5.0 plugins-dist declares. It is ignored when the plugin is
activated through SVP, and constrains the core version when the plugin is installed through Composer.

## `config/services.php`

```php
<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
	$services = $container->services()
		->defaults()
		->autowire()
		->autoconfigure();          // reads #[AsPipelineListener], #[AsCronTask], #[AsGuard]

	$services->load('Acme\\', '../src/');

	$services->set('spip.acme.logger', LoggerInterface::class)   // log channel "acme" → tmp/log/acme.log
		->factory([service('spip.logger_registry'), 'get'])
		->args(['acme']);
};
```

Collected automatically for every **active** plugin: nothing to declare in `paquet.xml`. Without `autoconfigure()`
and a `load()` that covers the class, attributes are never read. Plugin parameters:
`$container->parameters()->set('acme.xxx', …)`, prefixed with the plugin name, read with
`#[Autowire(param: 'acme.xxx')]`. The site's `config/custom.php` is loaded after the plugins and overrides them.
`$container->env()` is `null` in this file: environment-dependent values belong in `config/custom.php`.

A listener, Guard rule or public service that cannot be autowired (e.g. a plain `LoggerInterface` argument) makes
the **whole** container fail to compile, for every page of the site. An unused private service with the same error
is removed silently — so a broken class can also simply do nothing. Run the warmup right after each change and read
its output.

## Activate and rebuild

1. Activate the plugin in the back office (*Configuration → Gestion des plugins*). `bin/spip` has no activation
   command in 5.0.0-beta.
2. `bin/spip spip:cache:warmup --clear` — recompiles the container, the classmap and the boot caches.
3. `bin/spip spip:pipeline:listeners` — the listener must be listed (`Acme\…::onAffichageFinal`).
4. Output already in the page cache (e.g. `insert_head`) shows only after `?var_mode=recalcul` or
   `bin/spip spip:cache:clear --all`.

Active plugins are listed in `var/state/plugins.php` (`paths`).
