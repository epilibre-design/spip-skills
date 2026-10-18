# Requirements and fresh install

Verified 2026-10-18, `spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`.

## Requirements

| Item | Value | Source |
|---|---|---|
| PHP | `^8.4` (`composer.json`); below, every hit is a 500 whose body reads `Composer detected issues in your platform: Your Composer dependencies require a PHP version ">= 8.4.1"` | run with PHP 8.3 |
| Extensions required by the packages | `dom`, `filter`, `iconv`, `json`, `libxml`, `mbstring`, `pcre`, `phar`, `sodium`, `tokenizer`, `xml`, `xmlwriter`, `zip`, `zlib` | `composer check-platform-reqs` |
| Database | MySQL/MariaDB (`mysqli`) or SQLite (`pdo_sqlite`); PostgreSQL is gone (`ecrire/req/`) | source |
| Framework | Symfony 8.1 components, resolved by Composer | `composer show` |
| Distribution | Composer only: `spip_loader.api` lists 4.4.28 (latest 4.4), 4.3.9, 4.2.17 as archives and 5.0 only as `dev` | `https://www.spip.net/spip_loader.api` |

Check PHP for the web SAPI (PHP-FPM pool or Apache module), not only `php -v` in the shell. Not covered (not
verified): distribution package names, and extensions beyond those Composer requires (e.g. `gd` for image filters,
`curl`, `intl`); check them with the site's needs.

## Install

```bash
composer create-project --no-dev --repository=https://get.spip.net/composer spip/spip /var/www/site 5.0.x-dev
cd /var/www/site
composer update --no-dev            # create-project installs the lock of spip/spip, behind spip/ecrire
composer outdated 'spip/*'          # expect nothing
```

- Without `--no-dev`, `phpunit/phpunit` and `symfony/var-dumper` are installed.
- On 2026-10-18 the lock gave `spip/ecrire` `2cd5843f` and four plugins-dist behind; `composer update` moved
  `ecrire` to `26bfe470`.
- Created: `config/` (0755), `IMG/`, `local/`, `tmp/`, `tmp/cache/`, `tmp/log/` (0777), `.env.dist`. No `plugins/`
  (create it for third-party plugins), no `.env`.
- Document root = the project root (`index.php` → `spip.php` → Symfony Runtime).
- Before install: `/` answers 503, `ecrire/` redirects to `ecrire/?exec=install`.

### Web installer

No `bin/spip` command installs a site or activates a plugin; spip-cli does not run on 5.0. Open `ecrire/`:

1. directory check: `IMG/`, `tmp/`, `local/` and `config/` writable by the web server user (`config/` may be made
   read-only after the install);
2. database server (`mysql` or `sqlite3`), then database and table prefix (`spip`);
3. webmaster (name, e-mail, login, password);
4. installation of the plugins-dist;
5. end: `config/connect.php`, `config/chmod.php`, `config/cles.php`, `config/bases/<db>.sqlite` (SQLite) and
   `var/state/plugins.php` exist; `ecrire/?exec=accueil` opens.

Then publish a first article from the back office (Édition → rubrique → article → « publié en ligne »).

## Files that must not be served

At the end of the installer SPIP writes a deny `.htaccess` in `tmp/`, then `config/`, then `vendor/`, checking each
with an HTTP request (expects 403) and **stopping at the first failure**. Under nginx (or any server that ignores
`.htaccess`) only `tmp/.htaccess` is written and the installer shows « htaccess inopérant »; its advice to set
`_DIR_TMP` / `_DIR_CONNECT` in `mes_options.php` is obsolete in 5.0 (HTTP 500, see `configuration.md`). The cron
task that rewrites them (`MaintenanceTask`) never runs in the beta (queue bug).

Measured on a 5.0 site served by Apache:

| Path | No `config/.htaccess` | After an Apache install | + `htaccess.txt` → `.htaccess` |
|---|---|---|---|
| `config/bases/spip.sqlite` (the database) | **200, downloadable** | 403 | 403 |
| `vendor/…` | 200 | 403 | 403 |
| `var/state/plugins.php`, `bin/spip` | 200 | **200** | **200** |
| `.env.dist`, `composer.json` | 200 | 200 | 404 |
| `tmp/log/…` | 403 | 403 | 403 |

Check after any install, upgrade or copy:

```bash
for p in config/bases/ config/connect.php vendor/autoload.php var/state/plugins.php bin/spip .env.dist composer.json tmp/log/; do
  printf '%s %s\n' "$(curl -s -o /dev/null -w '%{http_code}' "https://site.example/$p")" "$p"; done
```

(for SQLite, request the actual `config/bases/<name>.sqlite`). Expect 403 or 404 everywhere.

Apache: copy `htaccess.txt` to `.htaccess` (URL rewriting, dotfiles and `composer.*` 404) and add, in the vhost or
the root `.htaccess`, a deny for the directories SPIP does not protect:

```apache
RedirectMatch 404 ^/(var|bin)/
```

(run on Apache 2.4 below the `composer.*` line of `htaccess.txt`: `var/state/plugins.php` and `bin/spip` 404, `/`
200, `ecrire/` 302.)

nginx, run with PHP-FPM 8.4 (all paths above 403/404, `/` 200, `ecrire/` 302):

```nginx
location ~ /\.(?!well-known/) { return 404; }
location ~ ^/(config|tmp|var|vendor|bin)/ { deny all; }
location ~ ^/composer\.(json|lock|phar)$ { return 404; }
location ~ ^/(IMG|local)/.*\.(php[0-9]?|phtml)$ { deny all; }
location / { try_files $uri $uri/ /spip.php?$args; }
location ~ \.php$ {
  try_files $uri =404;
  include fastcgi_params;
  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  fastcgi_pass unix:/run/php/php8.4-fpm.sock;
}
```
