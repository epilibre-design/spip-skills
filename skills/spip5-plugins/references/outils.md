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

## Environment (`APP_ENV`)

The environment (`prod`, `dev`, `test`) comes from `APP_ENV`. Symfony Runtime (`spip.php`, which `index.php` and
`ecrire/index.php` include, and `bin/spip`) loads the dotenv files of the project root: `.env` — or, when it does not
exist, `.env.dist`, shipped with `APP_ENV=prod` — then `.env.local`, `.env.<env>`, `.env.<env>.local`. Each
environment has its own compiled container, `tmp/cache/boot/container.<env>.php`.

| Where | How | Verified |
|---|---|---|
| Web, whole site | `.env.local` at the project root: `APP_ENV=dev` | public pages and `ecrire/` switch to `dev`; `container.dev.php` compiled on the first request |
| Web, server variable | `fastcgi_param APP_ENV dev`, `SetEnv APP_ENV dev`: wins over the dotenv files only if PHP puts it in `$_SERVER` / `$_ENV` | a process variable not copied to `$_SERVER` (`php -S`, default `variables_order`) is overridden by `.env.dist` → `prod` |
| CLI | `bin/spip -e dev …` | — |

Read it in code with `#[Autowire(param: 'kernel.environment')]` (class) or `Spip::param('kernel.environment')`;
in the site's `config/custom.php` with `$container->env()` — `null` in a plugin's `config/services.php` (`cron.md`).

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
5.0 source. Logged at runtime once `spip.logger.max_level` is `debug`.

## Static analysis: PHPStan and Rector

Tools in the plugin's `require-dev`, pointed at a 5.0 instance. Run from the plugin directory:

```bash
composer config repositories.spip composer https://get.spip.net/composer
composer config minimum-stability dev && composer config prefer-stable true
composer require --dev phpstan/phpstan:^2 phpstan/phpstan-deprecation-rules:^2 rector/rector:^2 spip-league/rector:dev-main
```

`phpstan.neon` of the plugin:

```neon
includes:
    - vendor/phpstan/phpstan-deprecation-rules/rules.neon

parameters:
    level: 1                       # level 0 does not report the removed constants
    phpVersion: 80400
    paths: [src, genie, inc]       # existing directories only: a missing path is an error
    bootstrapFiles:
        - /path/to/spip5/vendor/autoload.php
    scanDirectories:
        - /path/to/spip5/ecrire
        - /path/to/spip5/prive
```

`vendor/bin/phpstan analyse`. Tools installed in a separate directory instead: run
`/path/to/tools/vendor/bin/phpstan analyse` (and `…/rector`) from the plugin directory — both pick up the config of
the current directory — and change the include to `/path/to/tools/vendor/phpstan/phpstan-deprecation-rules/rules.neon`.

Verified on 5.0.0-beta (`ecrire` `26bfe470`), PHPStan 2.3.1:

| Reported | Not reported |
|---|---|
| `#[\Deprecated]` functions and methods: `spip_log()`, `Kernel::getRootDir()`… | deprecations raised only by `trigger_deprecation()`: `_request()`, `\SpipLeague\Component\Kernel\param()`, argument forms (`lire_config(,, false)`, `_T(…, ['spip_lang' => …])`) → grep and runtime log |
| removed functions, as `function.notFound`: `generer_url_entite()`… | |
| removed constants, as `constant.notFound` (from level 1): `_DIR_RESTREINT_ABS`, `_ROOT_RACINE`, `_IS_CLI` | |
| `@deprecated` classes: `TachesGeneralesCronEvent` | |

Rector (`spip-league/rector` `dev-main`, `4f8dcb2`, 2025-03-06) has **no** 5.0 rules: `SpipSetList::SPIP_50` is empty.
`SpipLevelSetList::UP_TO_SPIP_50` only adds the 4.1 renames and the PHP 8.2 set (`readonly` classes…). Its
`generer_url_entite*` → `generer_objet_url*` rule is a plain rename: it leaves a connection in 5th position
(verified: `generer_url_entite_absolue(1, 'article', '', '', 'distant')` → `generer_objet_url_absolue(…, 'distant')`,
where 5th is `$public`) — fix those calls by hand as in `spip-migration-5` `catalogue.md`. If you run it anyway:

```php
<?php
// rector.php
use Rector\Config\RectorConfig;
use SpipLeague\Component\Rector\Set\SpipLevelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/genie', __DIR__ . '/inc'])
    ->withSets([SpipLevelSetList::UP_TO_SPIP_50]);
```

`vendor/bin/rector process --dry-run` first, and review the diff.
