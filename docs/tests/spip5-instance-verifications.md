# spip5-instance — verifications

Date: 2026-10-18

Every command the skill contains was run here first. Each result says whether it comes from a **run** on a disposable
copy or a **source read**; "How" names the command or the file read.

Revisions (upstream heads on 2026-10-18, `composer outdated 'spip/*'` empty on the reference instance):
`spip/spip` `c8e329575` (2026-09-22), `spip/ecrire` `26bfe470` (2026-10-07), `spip/framework` `d0e8c97e`. PHP 8.4.25
(CLI and built-in server), SQLite. Upstream documentation: `spip/docs` `ac8c932` (2026-09-01, up to date).

Disposable copies (deleted at the end; the reference instances `/src/jetables/v5.0-dev` and `/src/jetables/v4.4.21`
are not modified):

| Copy | From | Port | Use |
|---|---|---|---|
| `/src/jetables/jetable-spip5-instance-copie` | `v5.0-dev` | 18941 | configuration, `bin/spip`, caches, logs, diagnostics |
| `/src/jetables/jetable-spip5-instance-upgrade` | `v4.4.21` | 18942 | 4.4 → 5.0 upgrade and rollback |
| `/src/jetables/jetable-spip5-instance-neuf` | `composer create-project` | 18943 | fresh install |

A copied 5.0 site keeps the source's absolute paths in `tmp/cache/` (`boot/options.php`, `boot/fonctions.php`,
`paths/`, `calcul/`): purge `tmp/cache/` and run `bin/spip spip:cache:warmup --clear` after copying or moving a site.
**Run**: `grep -rl v5.0-dev tmp/` on the copy, before and after.

Web servers for the HTTP checks, run as the user and stopped at the end: PHP built-in server with
`PHP_CLI_SERVER_WORKERS=4` (a single worker deadlocks when a page fetches one of its own URLs, e.g. `loader.svg`
after `spip:cache:clear --all`: 53 s, `ECHEC chinoiserie` in `distant.log`), Apache 2.4.52 + PHP-FPM 8.4 on 18944,
nginx on 18945, both on `jetable-spip5-instance-copie`.

## Requirements

| Question | Result | How |
|---|---|---|
| PHP | `^8.4` in `composer.json`; `config.platform.php` `8.4.22` | source read |
| Symfony | `symfony/runtime` `^8.1` required; installed `dependency-injection`, `http-foundation`, `console` 8.1.9, `dotenv` 8.1.6, `event-dispatcher` 8.1.5 | `composer show` |
| PHP extensions required by the packages | `dom`, `filter`, `iconv`, `json`, `libxml`, `mbstring`, `pcre`, `phar`, `sodium`, `tokenizer`, `xml`, `xmlwriter`, `zip`, `zlib` | `composer check-platform-reqs` |
| Database servers | `ecrire/req/`: `mysql.php` (extension `mysqli`, MySQL/MariaDB) and `sqlite3.php` (PDO SQLite); no PostgreSQL. The installer lists the drivers whose `spip_versions_<srv>()` succeeds | `ecrire/req/`, `install_select_serveur()` |
| Upgrade path | database upgrade from 3.2+ only, 4.1+ advised, 4.4 ideally (`prerequis.md`, `UPGRADE_5.0.md`) | doc read |

## Environment (`.env*`, `APP_ENV`)

| Question | Result | How |
|---|---|---|
| Dotenv order | `.env`, or `.env.dist` when `.env` is absent (`.env.dist` is ignored as soon as `.env` exists); then `.env.local`, `.env.<APP_ENV>`, `.env.<APP_ENV>.local`; the last one wins | **run**: a `MARKER` variable in each file, read by `config/custom.php` (`$_SERVER['MARKER']`), `spip:config:dump --prefix=spip.test` after deleting `tmp/cache/boot/` each time |
| `.env.dist` | tracked by `spip/spip` (`git checkout .env.dist` restores it): replaced on update, local values go in `.env.local` | **run** |
| Real environment variable | wins over the dotenv files: `APP_ENV=prod bin/spip …` with `.env.local` `APP_ENV=dev` → `prod`; `-e prod` too | **run** |
| Values read at compile time | a parameter set from `$_SERVER` in `config/custom.php` is frozen in the compiled container: changing `.env.local` has no effect until `tmp/cache/boot/` is rebuilt | **run**: marker unchanged across 5 edits without warmup |
| Web | `.env.local` `APP_ENV=dev` switches public pages and `ecrire/` to `dev`; a server variable wins only if PHP puts it in `$_SERVER` | `docs/tests/ticket-28-verifications.md` (same revisions) |
| `kernel.environment` | not shown by the default `spip:config:dump` (filter `spip.`): `spip:config:dump --prefix=kernel` | **run** |

## Directories (`SPIP_ETC_DIR`, `SPIP_CACHE_DIR`, `spip.dirs.tmp`)

| Question | Result | How |
|---|---|---|
| `SPIP_ETC_DIR=/abs/path/` (in `.env.local`) | Works: `config/` removed from the project, `connect.php`, `cles.php`, `bases/` and `custom.php` read from the new directory; public 200, `ecrire/` 302 to login, `config:lire nom_site` OK | **run** |
| `SPIP_CACHE_DIR=/abs/path/` alone | **Breaks every HTTP hit (500)**: `bin/spip` compiles `boot/` and the container into `SPIP_CACHE_DIR`, but HTTP reads `_DIR_CACHE_BOOT` = `<spip.dirs.tmp>cache/boot/` (`LegacyDirectoryConstants::defineAll()`); "Artefacts boot absents : options.php introuvable" (`BootOrchestrator.php:32`) | **run** + source read |
| `spip.dirs.tmp` absolute, outside the project (`config/custom.php`) | **Breaks (500)**: `SpipDirectories::relative()` turns `/src/…/tmpext/` into `src/…/tmpext/` (leading `/` dropped), and the container stays in the project's `tmp/cache/` (`SpipContainerFactory::cacheDir()` ignores `spip.dirs.tmp`) | **run**: debug line in `BootOrchestrator` on the copy, removed after |
| Working combination | `spip.dirs.tmp` **relative** (`'../site-tmp/'`) in `config/custom.php` **and** `SPIP_CACHE_DIR=<same directory, absolute>/cache/` in `.env.local`, then `spip:cache:warmup --clear`: public 200, `ecrire/` 302, `?page=backend` 200, login 200; project `tmp/` removed and not recreated; cache, logs, sessions, `cron.lock` written outside; `spip:cache:clear --all` purges the outside cache | **run** |
| `spip:info` after the move | `_DIR_TMP` `../<dir>/` (relative), `spip:config:dump` `spip.dirs.tmp` `'../<dir>/'`, `spip.dirs.cache` `'/abs/<dir>/cache/'`; back to `tmp/` after removing both settings and warmup | **run** (2026-10-19) |
| Either setting alone with the relative `spip.dirs.tmp` | without `SPIP_CACHE_DIR`: container compiled in the project's `tmp/cache/`, HTTP 500 | **run** |

## Parameters (`config/custom.php`, `spip:config:dump`)

| Question | Result | How |
|---|---|---|
| Effective values | `bin/spip spip:config:dump` (48 parameters; `--prefix=` to filter); `spip.dirs.cache`, `spip.dirs.etc`, `spip.dirs.project` absolute | **run** |
| Dev-only override | `if ($container->env() === 'dev') { … ->set('spip.logger.max_level', 'debug'); }` in `config/custom.php`: `prod` → `notice`, `dev` → `debug`, one container per environment (`container.dev.php`, `container.prod.php`) | **run** with `-e prod` / `-e dev` |
| Derived parameters | setting `spip.dirs.cache`, `spip.dirs.project` or `spip.logger.max_size_bytes` in `custom.php` is silently ignored (no error, values unchanged) | **run** |
| `spip.guard.load_threshold` | integer (`int $loadThreshold`); `0` disables. Above the threshold, only bots that are not "friends", on GET, are shed at random (probability grows with load²): 429 `Retry-After: 300`. **Run** with `1` at load 2.1: `MJ12bot` 8 × 429 out of 10, a browser user agent 10 × 200 | **run** + `LoadSheddingRule.php` |
| Guard rules from the CLI | no `bin/spip` command lists them | `bin/spip list` |

## `bin/spip`

`bin/spip list --raw` (`spip/spip` `c8e329575`): `about`, `completion`, `hello`, `help`, `list`, `config:ecrire`,
`config:lire`, `database:optimize` (alias `sql:optimize`), `routes:list`, `sites:syndics:maj`, `spip:cache:clear`
(alias `cache:clear`), `spip:cache:warmup` (alias `cache:warmup`), `spip:config:dump`, `spip:container:debug`,
`spip:info`, `spip:pipeline:events`, `spip:pipeline:listeners`. No install, plugin activation or database upgrade
command.

| Command | Result | How |
|---|---|---|
| `about` | `SPIP` `dev` (not the release), PHP, number of parameters | **run** |
| `spip:info` | `Version SPIP` `5.0.0-beta`, `_DIR_TMP`, `_DIR_ETC`; `Plugins actifs` `n/a` | **run** |
| `spip:cache:clear` | default `--boot`: deletes `tmp/cache/boot/`; the next HTTP hit rebuilds it (200) | **run** |
| `spip:cache:clear --all` | empties `tmp/cache/` (page cache, `skel/`, `paths/`…), `boot/` rebuilt | **run** |
| `spip:cache:warmup --clear` | recompiles `boot/` and the container of the environment given by `-e` / `APP_ENV`; with `-e dev` it removes `container.prod.*`, rebuilt by the next prod HTTP hit (200) | **run** |
| `config:lire <key> [--json]`, `config:ecrire <key>:<value>` | read / write `spip_meta`; needs the database (in `spip.console.requires_spip_commands`) | **run** |
| `database:optimize [--reorder\|--clean\|--all]` | optimises every table, exit 0 | **run** |
| `spip:container:debug` | compiled file, `SpipCompiledContainer` | **run** |
| `routes:list` | 6 routes: `/spip.php`, `/ecrire/{path}`, `/ecrire`, `/ecrire/`, `/ecrire/prive.php`, `/{path}` | **run** |
| `-e <env>` | default `prod` in the help text, but `APP_ENV` from the dotenv files applies when `-e` is not given | **run** |

## Caches and logs

| Question | Result | How |
|---|---|---|
| Levels | default `notice`: notice and above are written; a `notice` line is labelled `!INFO` (legacy label), `warning` `WARNING`. With `debug`: `debug` and `info` lines too. With `warning`: only `WARNING` | **run**: squelette with one `#EVAL{spip_logger('bsk')->…}` per level, `tmp/log/bsk.log` |
| `_LOG_FILTRE_GRAVITE` (4.x) | `define('_LOG_FILTRE_GRAVITE', 7)` in `config/mes_options.php`, level `notice`: still no `debug` / `info` line; `ecrire/inc_version.php` defines it from `spip.logger.max_level` when absent | **run** |
| Files | `tmp/log/<channel>.log` (`spip.log`, `serializer.log`, `distant.log`, `flock.log`…) | **run** |
| A copied or moved site | `tmp/cache/` keeps the source's absolute paths (`boot/options.php`, `boot/fonctions.php`, `paths/`, `calcul/`): purge `tmp/cache/` and warmup after copying | **run** (see top) |

## Entry point and web server

`index.php` includes `spip.php`, which returns a Symfony Runtime closure booting `SpipHttpKernel`
(`vendor/autoload_runtime.php`). The document root is the project root.

HTTP status of sensitive paths on the copy (5.0 install with only `tmp/.htaccess`):

| Path | Apache, no root `.htaccess` | Apache, `htaccess.txt` → `.htaccess` | nginx, rules below |
|---|---|---|---|
| `config/bases/spip.sqlite` | **200 (2.5 MB database downloaded)** | **200** | 403 |
| `config/connect.php`, `cles.php`, `chmod.php` | 200 (executed, empty) | 200 | 403 |
| `.env.dist` | 200 | 404 | 404 |
| `composer.json`, `composer.lock` | 200 | 404 | 404 |
| `var/state/plugins.php` | 200 | 200 | 403 |
| `vendor/autoload.php`, `vendor/composer/installed.json` | 200 | 200 | 403 |
| `bin/spip` | 200 | 200 | 403 |
| `tmp/log/spip.log`, `tmp/cache/boot/container.prod.php` | 403 (`tmp/.htaccess`) | 403 | 403 |
| `/`, `ecrire/` | 200, 302 | 200, 302 | 200, 302 |

The core writes the deny `.htaccess` of `tmp/`, `config/` (`_DIR_CONNECT`) and `vendor/` at the end of the
installer (`install_verifier_htaccess()`, `ecrire/install/etape_fin.php`) and in `MaintenanceTask`
(`ecrire/src/Cron/Task/MaintenanceTask.php`), a `#[AsCronTask]` task that the 5.0.0-beta queue never runs
(`docs/notes/spip-bugs.md` entry 1, branch `docs/notes-spip-bugs`). `var/` and `bin/` get none. The copied instance
had only `tmp/.htaccess`; whether the 5.0 installer writes the others is checked in "Fresh install".

nginx rules used (PHP-FPM 8.4):

```nginx
location ~ /\.(?!well-known/) { return 404; }
location ~ ^/(config|tmp|var|vendor|bin)/ { deny all; }
location ~ ^/composer\.(json|lock|phar)$ { return 404; }
location ~ ^/(IMG|local)/.*\.(php[0-9]?|phtml)$ { deny all; }
location / { try_files $uri $uri/ /spip.php?$args; }
location ~ \.php$ { try_files $uri =404; include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.4-fpm.sock; }
```

## Fresh install

| Step | Result | How |
|---|---|---|
| `composer create-project --repository=https://get.spip.net/composer spip/spip <dir> 5.0.x-dev` | creates the project with the **lock of `spip/spip` `c8e329575`**: `spip/ecrire` `2cd5843f`, not the current `26bfe470`; `spip/medias`, `porte-plume`, `sites`, `urls` behind (`composer outdated 'spip/*'`). Installs `require-dev` too (`phpunit/phpunit`, `symfony/var-dumper`) unless `--no-dev`. Creates `config/` (0755), `local/`, `tmp/`, `IMG/`, `tmp/cache/`, `tmp/log/` (0777); no `plugins/`, no `.env` (only `.env.dist`) | **run** (7 s, Composer cache warm) |
| `composer update` (in the project) | `spip/ecrire` `2cd5843f` → `26bfe470`, the four plugins-dist and Symfony 8.1.x updated; `composer outdated 'spip/*'` empty | **run** |
| Before install | public `/` 503, `ecrire/` 302 → `ecrire/?exec=install` | **run** |
| Writable directories checked by the installer | `IMG/`, `tmp/`, `local/` (`$GLOBALS['test_dirs']`, `spip_initialisation_core()`) and `config/` during the install (`_DIR_CONNECT`, `_DIR_CHMOD`); `config/` may be made read-only afterwards (comment in `initialization.php`) | source read: `ecrire/install/etape_chmod.php` |
| Web installer | `etape=chmod` → `1` (server: `mysql` or `sqlite3`) → `2` (database name, table prefix `spip`) → `3` / `3b` (webmaster name, e-mail, login, password) → `4` (installs the plugins-dist: Big Upload, Compagnon, Mots…) → `fin`. Files created: `config/connect.php`, `config/chmod.php` (renamed from `*.tmp.php` at `fin`), `config/cles.php`, `config/bases/spip.sqlite`, `var/state/plugins.php` (24 active paths, all `plugins-dist/spip/*`) | **run**: curl, SQLite, twice (built-in server, then Apache) |
| After install | public 200, `ecrire/` 302 to login; `bin/spip spip:info` `5.0.0-beta`; `config:lire version_installee` `2026080300` | **run** |
| Deny `.htaccess` at `fin` | `install_verifier_htaccess()` writes `tmp/.htaccess`, then checks it with an HTTP `HEAD` on `tmp/.ok` (expects 403), then `config/`, then `vendor/`, stopping at the first failure. **Apache** (`AllowOverride All`): all three written, install ends on `ecrire/?exec=accueil`. **PHP built-in server** (ignores `.htaccess`, same as nginx): only `tmp/.htaccess` written, page "htaccess inopérant", `config/` and `vendor/` left without one | **run** + `ecrire/install/etape_fin.php`, `verifier_htaccess()` in `ecrire/inc/acces.php` |
| "htaccess inopérant" message | tells to set `_DIR_TMP` and `_DIR_CONNECT` in `mes_options.php`: obsolete in 5.0 (`_DIR_*` are defined by the kernel before `mes_options.php`); the 5.0 ways are `spip.dirs.tmp` + `SPIP_CACHE_DIR` and `SPIP_ETC_DIR` (see Directories) or web server rules | **run** + `environnement-kernel.md` |
| Apache install, sensitive paths | `config/bases/spip.sqlite`, `config/connect.php`, `vendor/…`, `tmp/log/…` 403; `var/state/plugins.php` and `bin/spip` 200; `.env.dist`, `composer.json` 200 without the root `.htaccess`, 404 with `htaccess.txt` copied to `.htaccess` | **run** |
| Apache rule for `var/` and `bin/` | `RedirectMatch 404 ^/(var\|bin)/` added after the `composer.*` line of the root `.htaccess`: `var/state/plugins.php`, `bin/spip` 404; `composer.json` 404; `config/bases/spip.sqlite`, `vendor/autoload.php` 403; `/` 200, `ecrire/` 302 | **run** (Apache, fresh install) |
| Install from the CLI | impossible: no `bin/spip` command (see `bin/spip`) | **run** |

## Upgrade 4.4 → 5.0 and rollback

Site: copy of the 4.4.21 instance (SQLite, 12 346 articles, 261 authors, 2 181 documents; 11 plugins in `plugins/`,
one in `plugins/auto/ppp/v2.1.0/`; 21 plugins-dist in flat `plugins-dist/<name>/`; `config/ecran_securite.php`; no
`mes_options.php`, no `squelettes/`). Not a Git checkout: installed from the archive, with a 4.4 `composer.json`
(`spip/ecrire` `^4.4`).

| Question | Result | How |
|---|---|---|
| Distribution channels | `https://www.spip.net/spip_loader.api`: 4.4.28 (latest 4.4), 4.3.9, 4.2.17 as archives; 5.0 only as `dev` (`spip-master.zip`, PHP 8.4.0 minimum). No 5.0 release archive: 5.0 comes from Composer (`spip/spip` `5.0.x-dev`) | **run**: `curl` of the API |
| Core database schema | 4.4.21 `$spip_version_base = 2026_08_03_00`; 5.0.0-beta `SpipVersion::BASE = 2026_08_03_00`: same value, no core database upgrade from 4.4.21 | source read |
| `config/ecran_securite.php` | not loaded by 5.0 (no include in `ecrire/`, `vendor/spip*`): Guard replaces it; `spip.guard.load_threshold` replaces `ECRAN_SECURITE_LOAD` (`parametres-spip.md`) | source read |
| Procedure run (side by side) | 1. backup: `sqlite3 config/bases/spip.sqlite ".backup '<backup>/spip.sqlite'"` (`pragma integrity_check` ok) + `tar` of the site without `tmp/cache`; 2. `composer create-project --no-dev --repository=https://get.spip.net/composer spip/spip <new> 5.0.x-dev`, then `composer update --no-dev` (→ `ecrire` `26bfe470`, no `vendor/phpunit`); 3. copy from the 4.4 site: `config/connect.php`, `config/cles.php`, `config/chmod.php`, `config/bases/`, `IMG/`, `plugins/` (not `ecran_securite.php`, `local/`, `tmp/`); 4. `bin/spip spip:cache:warmup --clear`; 5. serve the new directory; 6. log in to `ecrire/` as webmaster, open `ecrire/?exec=admin_plugin` | **run** |
| First boot | warmup: 24 active plugins (the plugins-dist); `spip:info` `5.0.0-beta`. Public pages identical to 4.4 (articles 13, 14, 76, rubrique 1, an `IMG/` document: 200 on both) | **run** |
| Plugins in `plugins/` | on the first webmaster visit, the 7 plugins with `compatibilite` `[…;4.*]` are refused: "Impossible d'activer le plugin ../plugins/<name>", "Nécessite SPIP en version ≤ 4.* minimum" (`adminer`, `coloration_code`, `crayons`, `inserer_modeles`, `saisies`, `verifier`, `auto/ppp/v2.1.0`); the 4 with `[…;5.*]` stay active and appear in `var/state/plugins.php` (`yaml`, `tiptap_spip`, `tiptap_spip_faq`, `tts_test`) | **run**: `exec=admin_plugin`, manifest |
| Plugin schema upgrades | run automatically on the first webmaster hit of `ecrire/`, not on public hits: `medias_base_version` 1.8.1 → 1.9.2, logged in `tmp/log/maj.log` (`medias_base_version_maj_1.9.0: ok`); the other plugins-dist schemas are unchanged from 4.4 | **run** |
| Metas rewritten | after the 5.0 visit, `spip_meta.plugin` is JSON (`[{"SPIP":{"nom":"SPIP",…`), not PHP-serialised | **run**: `sqlite3` |
| Old flat `plugins-dist/<name>/` left in place | test with the 4.4 `plugins-dist/forum/` copied into the 5.0 site: ignored, the manifest keeps `plugins-dist/spip/forum/`, no error; the documentation says to delete them (`prerequis.md`) | **run** |
| 4.4 code on the database touched by 5.0 | pages still 200, but the log fills with errors (`pipeline desactive`, `spip_connect … non trouve`): code-only rollback is not enough once 5.0 has written the database | **run**: 5.0 database copied into the 4.4 site |
| Rollback | stop serving the 5.0 directory, serve the 4.4 directory, restore the database backup (`sqlite3 <backup>/spip.sqlite ".backup 'config/bases/spip.sqlite'"`), purge `tmp/cache/`: `pragma integrity_check` ok, `medias_base_version` 1.8.1, `plugin` serialised again, 12 346 articles, public 200, `ecrire/` 302, `spip_version_branche` `4.4.21`; the remaining log lines (`spip_connect … non trouve`) also appear on the untouched reference 4.4.21 instance | **run** |
| Deny `.htaccess` after the copy | copying only `connect.php`, `cles.php`, `chmod.php`, `bases/` leaves the new `config/` without the 4.4 `.htaccess`; on Apache, copy `config/.htaccess` too or check `config/bases/*.sqlite` returns 403 (see Fresh install) | **run** |

## Diagnostics

Reproduced on `jetable-spip5-instance-copie` (and on the upgraded copy for plugins). "Symptom" is what an operator
sees; "Evidence" is where the cause shows.

| Cause | Symptom | Evidence | Fix verified | How |
|---|---|---|---|---|
| `vendor/` missing | HTTP 500, empty body | PHP-FPM / web server error log: `Class "Symfony\Component\Runtime\SymfonyRuntime" not found` or failed `require` in `spip.php`; CLI: `Dependencies are missing. Try running "composer install".` (`bin/spip:13`) | `vendor/` back → 200 | **run** (Apache + PHP-FPM) |
| PHP < 8.4 | HTTP 500, body "Composer detected issues in your platform: Your Composer dependencies require a PHP version ">= 8.4.1"" | same text in CLI | — | **run**: PHP 8.3.35 built-in server and CLI |
| Site copied or moved without purging `tmp/cache/` | public pages 500, `ecrire/` still 302 | `PHP Fatal error: Cannot redeclare function barre_outils_css_icones() (previously declared in <old path>/plugins-dist/spip/porte-plume/porte_plume_fonctions.php:33)`: the boot cache loads code from the old directory | `rm -rf tmp/cache/*` + `bin/spip spip:cache:warmup --clear` → 200, no old path left in `tmp/cache` | **run**: copy of the copy on another port |
| `tmp/cache/` not writable (empty boot cache) | HTTP 500 "An Error Occurred: Internal Server Error" | log: `Uncaught Warning: mkdir(): Permission denied`; `spip:cache:warmup` fails with `ErrorException` | write permission back + warmup → 200 | **run** |
| `tmp/`, `var/`, `var/state/` not writable while their content exists | no visible effect (200) | — | — | **run** |
| `SPIP_CACHE_DIR` or absolute `spip.dirs.tmp` | HTTP 500 on every hit | "Artefacts boot absents : options.php introuvable…" | see Directories | **run** |
| `_DIR_*` redefined in `config/mes_options.php` | HTTP 500 | `Uncaught ErrorException: Warning: Constant _DIR_TMP already defined in …/config/mes_options.php:1` | remove the `define()` | **run** |
| Guard `BotAbuseUrlRule` | 403, body `You are not authorized to view this page (robot agenda/double pagination)`, only for bot user agents with two `debut_*` (or a repeated `calendrier_annee`) | nothing in `tmp/log/`: Guard does not log; the reason is in the response body | — | **run**: `MJ12bot` 403, browser 200 on `?page=sommaire&debut_a=10&debut_b=20` |
| Guard `LoadSheddingRule` | 429 `Retry-After: 300` for bots above `spip.guard.load_threshold` | idem; a non-integer threshold logs `Deprecated: Implicit conversion from float 0.01 to int` | `0` (default) disables | **run** |
| Plugin refused after an upgrade | not active; `ecrire/?exec=admin_plugin` "Impossible d'activer le plugin ../plugins/<name>", "Nécessite SPIP en version ≤ 4.* minimum" | `compatibilite` in its `paquet.xml`; absent from `var/state/plugins.php` | a 5.0-compatible version of the plugin | **run** (upgrade) |

### `mes_options.php`

| Question | Result | How |
|---|---|---|
| `ecrire/mes_options.php` | ignored: `_MA_CONSTANTE` not defined | **run**: squelette `[(#EVAL{defined('_MA_CONSTANTE') ? _MA_CONSTANTE : 'non definie'})]` |
| `config/mes_options.php` | read on every hit (`_FILE_OPTIONS` = its path in `spip:info`); a change applies without warmup | **run** |
| `_DIR_*` in it | fatal, see above (`environnement-kernel.md`: constants frozen at boot) | **run** |
| `_NOM_TEMPORAIRES_INACCESSIBLES` in it (4.x way to move `tmp/`) | HTTP 500, nothing created at the target: the kernel defines the `_NOM_*` and `_DIR_*` before `mes_options.php` (`ecrire/inc_version.php:72`) | **run** |

### Plugin activation from the back office

Deactivating then reactivating `tts_test` in `ecrire/?exec=admin_plugin` (form `admin_plugin`, `actions[off][<id>]`
/ `actions[on][<id>]`): `spip_paquets.actif` `non` / `oui`, `var/state/plugins.php` rewritten without / with
`plugins/tts_test/`, `tmp/cache/boot/container.prod.php` recompiled at the same second. **Run**: no warmup needed after
an activation from the back office; a class added to an active plugin still needs `spip:cache:warmup --clear`
(`spip5-plugins`).

## MySQL backup and restore

Disposable MySQL 8.0.46 server (`mysqld --initialize-insecure`, own datadir and socket, port 18950); the fresh 5.0
site reinstalled on it through the web installer behind Apache (database `spipjetable`, user `spip` with rights on
it only): 28 tables, `version_installee` `2026080300`, public 200. The installer echoes the database password in a
hidden field of its step 2 form (same as 4.x).

| Command | Result | How |
|---|---|---|
| option file `[client] user/password/host/port`, mode 600 | read by both clients; no password on the command line | **run** |
| `mysqldump --defaults-extra-file=<f> --single-transaction --routines --triggers --no-tablespaces <db> > site.sql` | exit 0, 28 `CREATE TABLE`, last line `-- Dump completed on …` | **run** |
| same without `--no-tablespaces` | prints `Access denied; you need (at least one of) the PROCESS privilege(s) for this operation' when trying to dump tablespaces` for a user limited to its database | **run** |
| `mysql --defaults-extra-file=<f> <db> < site.sql` | after `drop table spip_mots` and a changed `nom_site`: 28 tables and the original `nom_site` back, public 200 | **run** |


## Review fixes (2026-10-19)

Same revisions (`git ls-remote`: `spip/spip` `HEAD` `c8e329575`, `spip/ecrire` `5.x` `26bfe470`, `4.4` `5f67f441b`).

| Question | Result | How |
|---|---|---|
| Published nginx block, as written in `prerequis.md` (only the socket path and an absolute `fastcgi_params` include changed for the test prefix) | `/` 200, `ecrire/` 302, `spip.php?page=sommaire` 200, `config/bases/spip.sqlite`, `var/state/plugins.php`, `bin/spip` 403, `composer.json`, `.env.dist` 404 | **run**: nginx + PHP-FPM 8.4 on a fresh copy of `v5.0-dev`, deleted after |
| Same block without `index index.php;` | `/` and `ecrire/` 403 (nginx default index), other paths unchanged | **run** |
| nginx maintenance rule (`set $maintenance` / `if ($remote_addr = …)` / `return 503`) + published block | operator (127.0.0.1): `/` 200, `ecrire/` 302, `config/bases/spip.sqlite` 403; other address (127.0.0.2, `curl --interface`): 503 on all | **run** |
| Apache maintenance rule (`RewriteCond %{REMOTE_ADDR} !^…$`, `RewriteRule ^ - [R=503,L]`) at the top of the 5.0 `htaccess.txt` copied to `.htaccess` | operator: request goes on to the SPIP rules; other address: 503 on `/`, `ecrire/`, `article1.html`, `composer.json`; without the rule 200 | **run**: Apache 2.4.52, static docroot |
| `spip_loader.api` lookup | `jq -r '.versions \| keys[] \| select(startswith("4.4."))' \| sort -V \| tail -1` → `4.4.28`; `.requirements.php` → `{"master":"8.4.0","4.4":"7.4.0",…}`; `.default_branch` `4.4` | **run** |
