# Moving a plugin to the 5.0 model

This file maps **4.x constructs to their 5.0 replacement**. The 5.0 model itself (files, `services.php`, event
classes and methods, tasks, injection, Guard, menus) is described once, in the **`spip5-plugins`** skill: load it
for the target code. Real example to imitate: `plugins-dist/spip/bigup/` (5.0).

---

## Mapping

| 4.x | 5.0 | `spip5-plugins` reference |
|---|---|---|
| `compatibilite="[4.x;…]"` | `[5.0.0-dev;5.*]` (`[5.0.0;5.*]` is refused by 5.0.0-beta), or `[4.4.0;5.*]` for dual compat | `squelette-plugin.md` |
| no `composer.json` | plugin `composer.json`, `"type": "spip-plugin"`, `autoload.psr-4` | `squelette-plugin.md` |
| `<pipeline nom="x" inclure="…">` + `prefix_x($flux)` | `#[AsPipelineListener]` method typed with the event, in a class loaded by `config/services.php` | `listeners-events.md` |
| `<genie>` / `taches_generales_cron` + `genie/x.php` returning `1` | `#[AsCronTask(name, periode)]` service returning **`0`** — but see the beta bug below | `cron.md` |
| `<menu>` in `paquet.xml`, `bando_*` parents | `config/menus.php`, `menu_*` parents | `espace-prive.md` |
| `<script>` / `<style>` in `paquet.xml` | listener on `insert_head(_css)` / `header_prive(_css)` | `espace-prive.md` |
| `spip_log()`, `lire_config()`, `_request()` in a class | injected logger / config / `Inputs` | `services.md` |
| `$GLOBALS['meta']` | `ConfigRepositoryInterface` / `Spip::config()` | `config-plugin.md`, and `config-metas.md` here |
| `ecran_securite.php`, custom checks in `options.php` | Guard rule `#[AsGuard]` | `guard.md` |
| `$GLOBALS['spip_pipeline']`, `_DIR_*` redefinitions in `options.php` | ignored in 5.0: `config/custom.php` (site) or `config/services.php` (plugin) | — |

## Pipeline function → listener

```php
// 4.x: <pipeline nom="affichage_final" inclure="acme_pipelines.php" />
function acme_affichage_final($html) {
	return str_replace('</body>', '<!-- acme --></body>', $html);
}

// 5.0
#[AsPipelineListener]
public function onAffichageFinal(AffichageFinalEvent $event): void
{
	if (!$event->isHtmlPage()) {
		return;
	}
	$event->appendToBody('<!-- acme -->');
}
```

- Not a strict equivalent: without `</body>` (RSS, JSON, AJAX fragment) the 4.x `str_replace()` changed nothing,
  while `appendToBody()` appends at the end of the output. Hence the `isHtmlPage()` test.
- The method returns `void` and changes the event. `$flux['data']` → `getSubject()` / `setSubject()`,
  `$flux['args']['x']` → `getArgument('x')` or the event's own getters (`getSqlTable()`, `getAction()`, …).
- `pre_edition`: `$flux['args']['table']` → `getSqlTable()` — `PreEditionEvent` has no `getTable()` / `setField()`.
- Then **remove** the `<pipeline>` line and the function (keep a thin function only if third-party code calls it).
  Never keep both registrations for the same behaviour.
- `bin/spip spip:cache:warmup --clear`, then `bin/spip spip:pipeline:listeners`.

## Cron: return code inverted, and a beta bug

- 4.x `genie_*()`: `>= 0` success, `< 0` failure. 5.0 `#[AsCronTask]` service: **`0` success, `> 0` failure**
  (re-planned immediately). Rewriting `return 1;` unchanged turns every success into a failure.
- `genie_x($t)` → `__invoke()`: the queue still passes `$t` (date of the previous run) as the first argument;
  declare `__invoke(int $t = 0)` if the code uses it.
- A failure is retried at the next cron hit, with no delay and no limit, in both conventions: `spip5-plugins`
  `cron.md`, *When a task fails*, shows how to space retries.
- On **5.0.0-beta the queue never runs `#[AsCronTask]` services** (core tasks included; `ecrire` `2cd5843f` and
  `26bfe470`). Until fixed, keep the legacy `genie/` function and declare it with a `taches_generales_cron`
  listener; `spip5-plugins` `cron.md` has the check and the verified workaround.

## Injection: aliases are missing

A plain `LoggerInterface $logger` or `ConfigRepositoryInterface $config` constructor argument **breaks the container
compilation** (no alias on 5.0.0-beta). Use `#[Autowire(service: 'spip.acme.logger')]` and
`#[Autowire(service: 'spip.config')]`. `RequestStack`, `Inputs`, `PlatformInterface`, `SpipDirectoriesInterface`
autowire by type. Table in `spip5-plugins` `services.md`.

## `options.php` limits

`options.php` still runs, but **after** the container is compiled: redefining `_DIR_*`, writing
`$GLOBALS['spip_pipeline']` or other "frozen" declarations has no effect.

Site-wide settings go in `config/custom.php` (never in `config/spip/*.php`):

```php
return static function (ContainerConfigurator $container): void {
	$container->parameters()->set('spip.logger.max_level', 'debug');
};
```
