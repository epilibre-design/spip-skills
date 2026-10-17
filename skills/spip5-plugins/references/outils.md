# Tools and diagnosis

---

## Console `bin/spip`

SPIP 5.0 ships `bin/spip` at the project root (`spip-league/console`, Symfony Console) — not spip-cli, not
`bin/console`. `bin/spip list` shows what the instance provides.

| Command | Use |
|---|---|
| `bin/spip spip:cache:warmup --clear` | purge boot caches, recompile container, classmap, menus |
| `bin/spip spip:cache:clear --all` | purge all of `tmp/cache/` (page cache included) |
| `bin/spip spip:pipeline:listeners` | listeners registered per pipeline |
| `bin/spip spip:pipeline:events` | pipelines that have a typed event, with the class |
| `bin/spip spip:config:dump` | effective `spip.*` parameters |
| `bin/spip spip:container:debug` | container debug |
| `bin/spip config:lire` / `config:ecrire` | read / write `spip_meta` |
| `bin/spip about` | environment |

Global options: `-e/--env` (default `prod`, or `APP_ENV`), `--with-spip` (full SPIP load). No command activates a
plugin in 5.0.0-beta: use the back office.

## Listener / rule / task not called — checklist

1. Plugin active: its path is in `var/state/plugins.php` (`paths`).
2. Class under the PSR-4 prefix of the plugin `composer.json`, file path matching the namespace.
3. `config/services.php` has `autowire()` + `autoconfigure()` and a `load()` that covers the class.
4. Exact attribute: `SpipLeague\Bridge\Pipeline\Attribute\AsPipelineListener` (not Symfony `AsEventListener`),
   `SpipLeague\Bridge\Pipeline\Attribute\AsCronTask`, `SpipLeague\Component\Guard\Attribute\AsGuard`. A wrong `use`
   raises no error: the attribute is ignored.
5. Argument typed with the exact event class, or `PipelineEvent` **with** the pipeline name.
6. `bin/spip spip:cache:warmup --clear` — a class added after activation is unknown until then (classmap). Read the
   output: an autowiring error stops everything.
7. `bin/spip spip:pipeline:listeners` lists the method.
8. Output cached in pages (`insert_head`, `recuperer_fond`): `?var_mode=recalcul` or `spip:cache:clear --all`.
9. The same pipeline is not also handled by a legacy function that overwrites the result.
10. Task: see the 5.0.0-beta bug in `cron.md` (attribute tasks are not run by the queue).

## Logs

`tmp/log/<channel>.log`; default level `notice`. In development, the site's `config/custom.php`:

```php
return static function (ContainerConfigurator $container): void {
	$container->parameters()->set('spip.logger.max_level', 'debug');
};
```

then warmup.

## Deprecations

5.0 marks them with `#[\Deprecated(since: '5.0')]` and `trigger_deprecation('spip', '5.0', …)`; search both in the
5.0 source. Static analysis: PHPStan against the 5.0 sources; Rector rules in `spip-league/rector`.
