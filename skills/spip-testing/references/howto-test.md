# Testing a SPIP plugin with PHPUnit

## Architecture: two test tiers

Each plugin is tested in isolation, from its own root, with no dependency on a pre-existing SPIP site:

```
monplugin/
├── composer.json                  ← require-dev: phpunit, spip/tests, spip/spip-cli
├── phpunit.xml                    ← two testsuites: unit + integration
├── scripts/
│   └── install-spip-test.sh      ← spip-cli driven SPIP download + setup
└── tests/
    ├── bootstrap.php              ← unit mocks (no real SPIP)
    ├── bootstrap_integration.php  ← loads real SPIP from vendor/spip/spip
    ├── unit/                      ← extends TestCase, pure PHP, no DB
    │   └── MonFiltreTest.php
    └── integration/               ← extends TestCase, real SPIP + SQLite3
        └── MonObjetIntegrationTest.php
```

**Unit tests** run with lightweight stubs instead of SPIP. They are fast and require only `composer install`.

**Integration tests** run against a full SPIP site (SQLite3) installed inside `vendor/spip/spip/` by `spip-cli`. They exercise real pipelines, SQL, CVT forms, and squelettes.

---

## Setup

### composer.json

```json
{
    "name": "mon-organisation/monplugin",
    "type": "spip-plugin",
    "require": {
        "php": "^8.2"
    },
    "require-dev": {
        "phpunit/phpunit": "^13.0",
        "spip/spip-cli": "dev-master",
        "spip/tests": "dev-master"
    },
    "repositories": [
        {
            "name": "spip",
            "type": "composer",
            "url": "https://get.spip.net/composer"
        },
        {
            "name": "spip-cli",
            "type": "vcs",
            "url": "https://git.spip.net/spip/spip-cli.git"
        },
        {
            "name": "spip-tests",
            "type": "vcs",
            "url": "https://git.spip.net/spip/tests.git"
        }
    ],
    "scripts": {
        "install-spip-test": "sh scripts/install-spip-test.sh",
        "tests-unit": "vendor/bin/phpunit --colors --testsuite unit",
        "tests-integration": [
            "if [ ! -f vendor/spip/spip/config/connect.php ]; then echo 'SPIP local non installé.' >&2; if [ -t 0 ]; then printf 'Lancer composer install-spip-test ? [Y/n] ' >&2; read -r r; case \"$r\" in n|N) exit 1 ;; *) composer install-spip-test || exit 1 ;; esac; else echo 'Lancez: composer install-spip-test' >&2; exit 1; fi; fi",
            "vendor/bin/phpunit --colors --testsuite integration --bootstrap tests/bootstrap_integration.php"
        ]
    },
    "config": {
        "sort-packages": true
    }
}
```

### phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    cacheDirectory=".phpunit.cache"
>
    <testsuites>
        <testsuite name="unit">
            <directory suffix="Test.php">tests/unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory suffix="Test.php">tests/integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

---

## Unit tests (no SPIP required)

### Unit bootstrap — `tests/bootstrap.php`

Define stubs for every SPIP global function your plugin calls. Use `if (!function_exists())` guards so the file is safe to `require_once` even if SPIP is later loaded (e.g. in integration context).

Expose mock return values through `$GLOBALS['_test_*']` so each test can inject the exact value it needs.

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('_ECRIRE_INC_VERSION')) {
    define('_ECRIRE_INC_VERSION', 'test');
}

if (!function_exists('include_spip')) {
    function include_spip(string $path): void {}
}

if (!function_exists('_request')) {
    function _request(string $name): mixed {
        return $GLOBALS['_test_request'][$name] ?? null;
    }
}

if (!function_exists('_T')) {
    function _T(string $key): string { return $key; }
}

if (!function_exists('sql_quote')) {
    function sql_quote(string $value): string {
        return "'" . addslashes($value) . "'";
    }
}

if (!function_exists('sql_countsel')) {
    function sql_countsel(string $table, string $where = ''): int {
        return (int) ($GLOBALS['_test_sql_countsel'][$table] ?? 0);
    }
}

if (!function_exists('lire_config')) {
    function lire_config(string $key): mixed {
        return $GLOBALS['_test_config'][$key] ?? null;
    }
}

if (!function_exists('autoriser')) {
    function autoriser(string $faire, string $type = '', int $id = 0, array $qui = [], array $opt = []): bool {
        return (bool) ($GLOBALS['_test_autoriser'] ?? true);
    }
}
```

### Run

```bash
composer install
composer tests-unit
# or directly:
vendor/bin/phpunit --colors --testsuite unit
```

---

### Example 1 — Test a PHP filtre or helper

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Load the file under test directly; no find_in_path needed in unit context
require_once dirname(__DIR__, 2) . '/inc/monplugin_filtres.php';

final class MonFiltreTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_test_config'] = [];
    }

    /**
     * @dataProvider providerMonFiltre
     */
    public function testMonFiltre(string $expected, string $input): void
    {
        $this->assertSame($expected, mon_filtre($input));
    }

    public static function providerMonFiltre(): array
    {
        return [
            'nominal'       => ['hello', 'HELLO'],
            'empty string'  => ['', ''],
        ];
    }

    public function testMonFiltreAvecConfig(): void
    {
        $GLOBALS['_test_config']['monplugin/mode'] = 'strict';
        $this->assertSame('strict:hello', mon_filtre_avec_config('hello'));
    }
}
```

**Key points:**
- `require_once` the plugin file directly from its path — no SPIP path resolution needed.
- `$GLOBALS['_test_*']` lets each test control what the mock functions return.
- Reset `$GLOBALS` in `setUp()` to keep tests independent.

---

### Example 2 — Test an autorisation

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/monplugin_autorisations.php';

final class AutorisationsTest extends TestCase
{
    private static function redacteur(): array { return ['statut' => '1comite', 'id_auteur' => 5]; }
    private static function visiteur(): array  { return ['statut' => '6forum',  'id_auteur' => 0]; }

    public function testRedacteurPeutVoir(): void
    {
        $this->assertTrue(
            autoriser_monobjet_voir_dist('voir', 'monobjet', 1, self::redacteur(), [])
        );
    }

    public function testVisiteurNePeutPasModifier(): void
    {
        $GLOBALS['_test_autoriser'] = false;
        $this->assertFalse(
            autoriser_monobjet_modifier_dist('modifier', 'monobjet', 1, self::visiteur(), [])
        );
    }
}
```

---

## Integration tests (real SPIP)

### Install the SPIP test environment

```bash
composer install-spip-test
```

This runs `scripts/install-spip-test.sh`, which:
1. Downloads SPIP core into `vendor/spip/spip/` via `spip core:telecharger`
2. Prepares it (`spip core:preparer`)
3. Installs it with SQLite3 (`spip core:installer`)
4. Registers the SVP depot and downloads plugin dependencies
5. Activates the plugin itself

The SPIP environment persists in `vendor/spip/spip/`. Subsequent runs skip steps that are already done.

### `scripts/install-spip-test.sh` template

```sh
#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SPIP_ROOT="$ROOT_DIR/vendor/spip/spip"
SPIP_BIN="$ROOT_DIR/vendor/bin/spip"
DEPOT_PRINCIPAL="https://plugins.spip.net/depots/principal.xml"
PLUGIN_PREFIX="monplugin"
# Space-separated list of plugin prefixes that must be active before your plugin
PLUGIN_DEPS=""

is_plugin_active() {
    "$SPIP_BIN" plugins:lister | grep -Eiq "^[[:space:]]*$1[[:space:]]"
}

activate_or_install_plugin() {
    plugin_prefix="$1"
    is_plugin_active "$plugin_prefix" && return 0
    "$SPIP_BIN" plugins:activer "$plugin_prefix" -y && is_plugin_active "$plugin_prefix" && return 0
    echo "Downloading $plugin_prefix via SVP..." >&2
    "$SPIP_BIN" plugins:svp:telecharger "$plugin_prefix" -y || true
    "$SPIP_BIN" plugins:activer "$plugin_prefix" -y || true
    is_plugin_active "$plugin_prefix"
}

mkdir -p "$ROOT_DIR/vendor/spip"

# Download SPIP core if missing
if [ ! -f "$SPIP_ROOT/ecrire/inc_version.php" ]; then
    "$SPIP_BIN" core:telecharger -d "$SPIP_ROOT" -b 4.4
fi

cd "$SPIP_ROOT"
"$SPIP_BIN" core:preparer

# Install (SQLite3, no network required for DB)
if [ ! -f "$SPIP_ROOT/config/connect.php" ]; then
    "$SPIP_BIN" core:installer \
        --db-server=sqlite3 \
        --db-host='' --db-login='' --db-pass='' \
        --db-database='spip_test' \
        --db-prefix=spip \
        --admin-nom='Admin Test' \
        --admin-login='admin' \
        --admin-email='admin@example.test' \
        --admin-pass='adminadmin' \
        --adresse-site='http://localhost'
fi

# Register depot and activate dependencies
"$SPIP_BIN" plugins:svp:depoter "$DEPOT_PRINCIPAL" || true

for plugin_dep in $PLUGIN_DEPS; do
    activate_or_install_plugin "$plugin_dep" || { echo "Failed: $plugin_dep" >&2; exit 1; }
done

"$SPIP_BIN" plugins:activer "$PLUGIN_PREFIX" -y
echo "Integration environment ready."
```

**Adapt:** set `PLUGIN_PREFIX` to your plugin's prefix and `PLUGIN_DEPS` to space-separated dependency prefixes (e.g. `"saisies verifier"`).

### Integration bootstrap — `tests/bootstrap_integration.php`

```php
<?php
declare(strict_types=1);

$spipRoot = dirname(__DIR__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}
require_once $spipRoot . '/ecrire/inc_version.php';

include_spip('inc/plugin');
_chemin(dirname(__DIR__));   // add plugin root to SPIP path
actualise_plugins_actifs();  // activate the plugin chain

// DO NOT require tests/bootstrap.php here: its mocks (sql_countsel, autoriser…)
// conflict with the real SPIP functions already loaded above.
```

### Run

```bash
# First time only:
composer install-spip-test

# Every time:
composer tests-integration
# or directly:
vendor/bin/phpunit --colors --testsuite integration --bootstrap tests/bootstrap_integration.php
```

`composer tests-integration` checks for `vendor/spip/spip/config/connect.php` and auto-proposes `install-spip-test` if the environment is missing.

---

### Example 3 — Verify the plugin is active

A smoke test that confirms the integration environment is correctly set up:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SpipTestingPluginTest extends TestCase
{
    public function testSpipIsBootstrapped(): void
    {
        $this->assertTrue(defined('_SPIP_TEST_INC'), 'SPIP bootstrap not loaded');
    }

    public function testPluginIsActive(): void
    {
        include_spip('inc/plugin');
        $plugins = liste_plugins_actifs();
        $this->assertArrayHasKey('monplugin', $plugins, 'Plugin monplugin is not active');
    }
}
```

---

### Example 4 — Test an SQL query or a CVT form (integration)

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MonObjetSqlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Load plugin files through the real SPIP path
        include_spip('base/abstract_sql');
        find_in_path('inc/monplugin_utils.php', '', true);
    }

    public function testInsertEtLire(): void
    {
        $id = sql_insertq('spip_mon_objet', [
            'titre'    => 'Test objet',
            'statut'   => 'publie',
            'id_secteur' => 0,
        ]);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $row = sql_fetsel('titre', 'spip_mon_objet', 'id_mon_objet=' . (int) $id);
        $this->assertSame('Test objet', $row['titre'] ?? null);

        sql_delete('spip_mon_objet', 'id_mon_objet=' . (int) $id);
    }

    public function testCvtVerifier(): void
    {
        // CVT verifier functions are loaded by SPIP automatically when the plugin is active
        $_POST  = ['titre' => ''];
        $erreurs = formulaires_monplugin_verifier_dist('new');
        $this->assertArrayHasKey('titre', $erreurs);
    }
}
```

---

## Testing squelettes and `#BALISE`

### Two base classes

| Use case | Base class |
|---|---|
| Test a PHP function (filtre, autorisation, inc…) | `PHPUnit\Framework\TestCase` |
| Test a squelette, a `#BALISE`, or a `\|filtre` in a template | `Spip\Test\SquelettesTestCase` |

`SquelettesTestCase` is provided by `spip/tests`. It requires the integration environment (real SPIP loaded).

---

### Example 5 — Test a `#BALISE` or a squelette

```php
<?php
declare(strict_types=1);

namespace MonOrganisation\Plugin\Monplugin\Tests\Integration;

use Spip\Test\SquelettesTestCase;

class MaBaliseTest extends SquelettesTestCase
{
    public function testBaliseRetourneValeur(): void
    {
        // Template must output a string starting with 'ok' (case-insensitive)
        $this->assertOkCode('[(#MA_BALISE|=={attendu}|oui)ok]');
    }

    public function testBaliseVideSiAbsente(): void
    {
        $this->assertEmptyCode('[(#MA_BALISE_ABSENTE)]');
    }

    public function testBaliseAvecContexte(): void
    {
        $this->assertEqualsCode(
            'Hello world',
            '[(#ENV{salut})]',
            ['salut' => 'Hello world']
        );
    }

    public function testSqueletteFichier(): void
    {
        $this->assertOkSquelette(__DIR__ . '/data/mon_squelette_test.html', ['id' => 1]);
    }
}
```

Place `SquelettesTestCase` tests in `tests/integration/` — they need real SPIP.

### `SquelettesTestCase` assertions

| Method | What it checks |
|---|---|
| `assertOkCode($code, $ctx)` | Inline rendering starts with `OK` |
| `assertNotOkCode($code, $ctx)` | Inline rendering starts with `NOK` |
| `assertEmptyCode($code, $ctx)` | Inline rendering is empty |
| `assertNotEmptyCode($code, $ctx)` | Inline rendering is not empty |
| `assertEqualsCode($expected, $code, $ctx)` | Strict equality |
| `assertNotEqualsCode($unexpected, $code, $ctx, $message)` | Inline rendering does NOT equal expected string |
| `assertOkSquelette($path, $ctx)` | Same as `assertOkCode` but from a `.html` file |

---

## Reference

### Composer scripts

| Command | What it does |
|---|---|
| `composer install` | Install PHPUnit, spip-cli, spip/tests into `vendor/` |
| `composer tests-unit` | Run unit testsuite with mock bootstrap |
| `composer tests-integration` | Run integration testsuite (auto-proposes `install-spip-test` if SPIP missing) |
| `composer install-spip-test` | Download + configure SPIP + activate plugin in `vendor/spip/spip/` |

### `$GLOBALS` mock injection (unit tests)

| Global | Mock function it drives |
|---|---|
| `$GLOBALS['_test_request']['key']` | `_request('key')` |
| `$GLOBALS['_test_sql_countsel']['spip_table']` | `sql_countsel('spip_table', ...)` |
| `$GLOBALS['_test_config']['plugin/key']` | `lire_config('plugin/key')` |
| `$GLOBALS['_test_autoriser']` | `autoriser(...)` (bool) |

Reset all `_test_*` globals in `setUp()` to prevent state leakage between tests.

### SPIP helpers available in integration tests

| Helper | Usage |
|---|---|
| `find_in_path('inc/file.php', '', true)` | Load a file through the SPIP path |
| `include_spip('inc/file')` | Equivalent without extension |
| `charger_fonction('name', 'inc')` | Load and return an inc function |
| `sql_fetsel(...)` | SQL read |
| `sql_insertq(...)` | SQL insert, returns new id |
| `sql_delete(...)` | SQL delete |
| `autoriser($faire, $quoi, $id)` | Call the real autorisations engine |
| `liste_plugins_actifs()` | Return the map of active plugins |
