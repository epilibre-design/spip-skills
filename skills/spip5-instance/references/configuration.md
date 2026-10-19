# Configuration: `.env*`, `config/custom.php`, parameters, directories

Verified 2026-10-18, `spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`.

## Where a setting goes

| Setting | File | Effect after |
|---|---|---|
| Environment (`APP_ENV`), `SPIP_ETC_DIR`, `SPIP_CACHE_DIR` | `.env.local` at the project root (not versioned), or a real environment variable | `bin/spip spip:cache:warmup --clear` |
| Container parameters `spip.*` (logs, directories, Guard, routes…) | `config/custom.php` | warmup |
| Legacy constants (`define()`) other than `_DIR_*` / `_NOM_*` | `config/mes_options.php` (see below) | next hit, no warmup |
| Never | `ecrire/mes_options.php` (ignored), `config/spip/*.php` (core defaults, replaced on update), `.env.dist` (versioned by `spip/spip`, replaced on update), `_DIR_*` / `_NOM_*` in `mes_options.php` (HTTP 500 « Constant _DIR_TMP already defined ») | — |

## `config/mes_options.php`

```php
<?php
define('_MA_CONSTANTE', 'valeur');
```

`bin/spip spip:info` shows the file actually loaded (`_FILE_OPTIONS` = its absolute path; empty when there is none).
To check a constant from a page, a test squelette `squelettes/test_constante.html` containing
`[(#EVAL{defined('_MA_CONSTANTE') ? _MA_CONSTANTE : 'non definie'})]`, opened as `spip.php?page=test_constante`
(delete it afterwards). If `squelettes/` did not exist, it is found only after `bin/spip spip:cache:clear --all`
(the path cache still lists the old directories: the page renders as the default 404). Measured: `valeur` with the file in `config/`, `non definie` with the same file in `ecrire/`.

## `.env*` and `APP_ENV`

Order (the last loaded wins): `.env`, or `.env.dist` when `.env` does not exist; then `.env.local`, `.env.<APP_ENV>`,
`.env.<APP_ENV>.local`. `.env.dist` ships `APP_ENV=prod`. A real environment variable wins over all files; in a web
request it counts only if PHP puts it in `$_SERVER` (`fastcgi_param APP_ENV dev;`, `SetEnv APP_ENV dev`). `bin/spip
-e <env>` wins in the CLI.

Typical development machine:

```bash
# set APP_ENV=dev, keeping the other lines of .env.local (SPIP_ETC_DIR, SPIP_CACHE_DIR…)
if grep -q '^APP_ENV=' .env.local 2>/dev/null; then sed -i 's/^APP_ENV=.*/APP_ENV=dev/' .env.local; else echo 'APP_ENV=dev' >> .env.local; fi
bin/spip spip:cache:warmup --clear
bin/spip spip:config:dump --prefix=kernel     # kernel.environment 'dev'
```

Each environment has its own compiled container (`tmp/cache/boot/container.<env>.php`). Values read from the
environment inside `config/custom.php` are frozen at compile time: after editing `.env*`, warm up again.

## `config/custom.php`

```php
<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
	$container->parameters()->set('spip.guard.load_threshold', 8);

	if ($container->env() === 'dev') {
		$container->parameters()->set('spip.logger.max_level', 'debug');
	}
};
```

`$container->env()` is the environment in the site's `custom.php` (it is `null` in a plugin's `config/services.php`).
The site's file is loaded after the plugins: it wins over their parameters.

Check the effective values: `bin/spip spip:config:dump` (48 parameters, default filter `spip.`), `-e dev` for the dev
container, `--prefix=spip.logger` to narrow. Derived parameters (`spip.dirs.project`, `spip.dirs.etc`,
`spip.dirs.cache`, `spip.logger.max_size_bytes`, `kernel.environment`) cannot be set: `custom.php` values for them are
ignored **silently**.

| Parameter | Default | Note |
|---|---|---|
| `spip.logger.max_level` | `notice` | PSR-3 level; replaces `_LOG_FILTRE_GRAVITE` |
| `spip.logger.max_files`, `max_size` (KiB), `max_log` | `4`, `100`, `100` | rotation |
| `spip.logger.fileline`, `brut` | `false` | |
| `spip.dirs.tmp`, `doc`, `var` | `tmp/`, `IMG/`, `local/` | see below before changing `tmp` |
| `spip.guard.load_threshold` | `0` (off) | **integer** load average above which bots get 429 (replaces `ECRAN_SECURITE_LOAD`); a float logs a deprecation |
| `spip.routes.front_office`, `back_office` | `/`, `ecrire/` | |

## Moving `config/` out of the project: `SPIP_ETC_DIR`

Works. Move `config/` (with `connect.php`, `cles.php`, `chmod.php`, `bases/`, `custom.php`, `mes_options.php`), then:

```bash
echo 'SPIP_ETC_DIR=/srv/site-etc/' >> .env.local
bin/spip spip:cache:warmup --clear
bin/spip spip:config:dump | grep dirs.etc      # '/srv/site-etc/'
bin/spip config:lire nom_site                  # database reached
```

Public 200, `ecrire/` 302 to login.

## Moving `tmp/` (the cache) out of the web root — beta trap

In 5.0.0-beta:

- `SPIP_CACHE_DIR` **alone** breaks every page (HTTP 500 « Artefacts boot absents : options.php introuvable »):
  `bin/spip` compiles the boot cache into `SPIP_CACHE_DIR`, HTTP still reads `<spip.dirs.tmp>cache/boot/`;
- `spip.dirs.tmp` **absolute** breaks too (the leading `/` is dropped when SPIP makes it relative, and the container
  stays in the project's `tmp/cache/`);
- `_NOM_TEMPORAIRES_INACCESSIBLES` in `mes_options.php` (the 4.x way): HTTP 500.

What works, both settings together, the directory given **relative** to the project in `custom.php` and absolute in
`.env.local`:

```php
// config/custom.php
$container->parameters()->set('spip.dirs.tmp', '../site-tmp/');
```

```bash
mkdir /var/www/site-tmp && cp -a tmp/. /var/www/site-tmp/     # owned by the web server user
echo 'SPIP_CACHE_DIR=/var/www/site-tmp/cache/' >> .env.local
bin/spip spip:cache:warmup --clear
curl -s -o /dev/null -w '%{http_code}\n' https://site.example/          # 200
curl -s -o /dev/null -w '%{http_code}\n' https://site.example/ecrire/   # 302
bin/spip spip:info | grep _DIR_TMP                                       # ../site-tmp/
bin/spip spip:config:dump | grep dirs.cache                              # '/var/www/site-tmp/cache/'
```

Verified: project `tmp/` removed and not recreated; cache, logs, sessions and `cron.lock` written in `site-tmp/`;
`spip:cache:clear --all` purges it. Rollback: remove both settings, put `tmp/` back, warmup.

Simpler alternative, no beta risk: keep `tmp/` and make sure it is not served (`tmp/.htaccess` under Apache returns
403; nginx `deny` in `prerequis.md`).
