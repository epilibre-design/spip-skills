# Testing a SPIP plugin with PHPUnit

## How it works

SPIP tests rely on the [`spip/tests`](https://git.spip.net/spip/tests) repository, cloned at the root of your SPIP site into `tests/`. SPIP is fully bootstrapped before each suite, so global functions (`include_spip`, `find_in_path`, `sql_*`, `autoriser`, etc.) are available.

The `bin/configure.php` script scans `creer_chemin()` (the SPIP path that includes active plugins) and auto-discovers every `{plugin}/tests/` directory containing at least one `*Test.php` file. Each discovered directory becomes an additional PHPUnit testsuite. No manual suite wiring is required.

```
{monplugin}/
├── paquet.xml
└── tests/
    ├── bootstrap.php          <- optional, loaded before the suite
    ├── MonFiltre/
    │   └── MonFiltreTest.php
    └── Squelettes/
        └── MaBaliseTest.php
```

---

## Install the test runner

```bash
# from the SPIP site root
git clone https://git.spip.net/spip/tests.git tests
cd tests
composer install
```

> The `tests/` repository is separate from this workspace folder `spip/tests/`. It must be cloned at the root of the real SPIP site (where `ecrire/` exists).

---

## Run tests

```bash
# from tests/
make tests                          # configure + run everything

# alternatives without make
php bin/configure.php               # regenerate phpunit.xml and bootstrap_plugins.php
vendor/bin/phpunit --colors tests   # run all tests
vendor/bin/phpunit --colors --filter=MonFiltreTest   # filter by class name
vendor/bin/phpunit --colors --filter=testMonFiltre   # filter by method name
vendor/bin/phpunit --colors --debug --list-suites    # list discovered suites
```

---

## Two base classes

| Use case | Base class |
|---|---|
| Test a PHP function (filtre, autorisation, inc...) | `PHPUnit\Framework\TestCase` |
| Test a squelette, a `#BALISE`, or a `\|filtre` in a template | `Spip\Core\Testing\SquelettesTestCase` |

---

## Example 1 - Test a PHP filtre

```php
<?php
declare(strict_types=1);

namespace Monplugin\Tests\Filtre;

use PHPUnit\Framework\TestCase;

class MonFiltreTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Load the file that declares the function, once before the class
        find_in_path('inc/monplugin_filtres.php', '', true);
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
            'nominal case'    => ['hello', 'HELLO'],
            'already caps'    => ['BONJOUR', 'BONJOUR'],
            'empty string'    => ['', ''],
        ];
    }
}
```

**Key points:**
- `find_in_path('relative/path.php', '', true)` loads the file through the SPIP path (same behavior as production).
- `include_spip('inc/monplugin_filtres')` is the equivalent without extension.
- Loading in `setUpBeforeClass()` avoids repeating it for every test.

---

## Example 2 - Test an autorisation

```php
<?php
declare(strict_types=1);

namespace Monplugin\Tests\Api;

use PHPUnit\Framework\TestCase;

class MonAutorisationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        include_spip('inc/autoriser');
        include_spip('inc/monplugin_autorisations'); // declares autoriser_monobjet_*_dist()
    }

    public function testAutoriserVoirMonObjet(): void
    {
        // Without session: default deny
        $this->assertFalse(autoriser('voir', 'monobjet', 1));
    }

    public function testAutoriserCreerMonObjet(): void
    {
        $this->assertFalse(autoriser('creer', 'monobjet'));
    }
}
```

To test with a logged-in author, call `spip_tests_loger_webmestre()` (defined in `tests/bootstrap.php`) or manually inject `$GLOBALS['visiteur_session']`.

---

## Example 3 - Test a `#BALISE` or a squelette

Extend `Spip\Core\Testing\SquelettesTestCase`. Assertions from this class verify that a squelette fragment compiles and produces the expected output.

```php
<?php
declare(strict_types=1);

namespace Monplugin\Tests\Squelettes;

use Spip\Core\Testing\SquelettesTestCase;
use Spip\Core\Testing\Templating;

class MaBaliseTest extends SquelettesTestCase
{
    // --- Inline string (simplest) ---

    public function testBaliseRetourneValeur(): void
    {
        // assertOkCode: the template must produce a string starting with 'OK' (case-insensitive)
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

    // --- .html file (for complex squelettes) ---

    public function testSqueletteFichier(): void
    {
        // Path can be absolute or relative to SPIP root
        $this->assertOkSquelette(__DIR__ . '/data/mon_squelette_test.html');
    }

    // --- Templating with injected custom PHP functions ---

    public function testAvecFonctionInjectee(): void
    {
        $templating = Templating::fromString([
            'fonctions' => "
                function stub_ma_fonction(): string { return 'stub'; }
            ",
        ]);
        $this->assertOkTemplate($templating, '[(#VAL|stub_ma_fonction|=={stub}|oui)ok]');
    }
}
```

### Available `SquelettesTestCase` assertions

| Method | What it checks |
|---|---|
| `assertOkCode($code, $ctx)` | Inline rendering starts with `OK` |
| `assertNotOkCode($code, $ctx)` | Inline rendering starts with `NOK` |
| `assertEmptyCode($code, $ctx)` | Inline rendering is empty |
| `assertNotEmptyCode($code, $ctx)` | Inline rendering is not empty |
| `assertEqualsCode($expected, $code, $ctx)` | Strict equality |
| `assertOkSquelette($path, $ctx)` | Same as `assertOkCode` but from a file |
| `assertOkTemplate($templating, $code, $ctx)` | Same as `assertOkCode` with explicit `Templating` |

The `OK`/`NOK` convention comes from legacy SPIP tests: the template must output a string that starts with `ok` (case-insensitive) for the assertion to pass.

---

## Example 4 - Test an SQL query (read)

```php
<?php
declare(strict_types=1);

namespace Monplugin\Tests\Sql;

use PHPUnit\Framework\TestCase;

class MonObjetSqlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        find_in_path('base/abstract_sql.php', '', true);
    }

    protected function setUp(): void
    {
        // Skip test if database is not SQLite (useful in CI without MySQL)
        if ($GLOBALS['connexions'][0]['type'] ?? '' !== 'sqlite3') {
            $this->markTestSkipped('Needs a Sqlite database');
        }
    }

    public function testLireMonObjet(): void
    {
        $row = sql_fetsel('titre', 'spip_mon_objet', 'id_mon_objet=1');
        $this->assertIsArray($row);
    }
}
```

> SQL tests require a real database with the plugin schema installed. Prefer unit tests for business logic and reserve SQL tests for CI integration with a dedicated database.

---

## Plugin bootstrap (optional)

If your suite needs to initialize data, force constants, or preload files before all tests, create `tests/bootstrap.php` in your plugin:

```php
<?php
// tests/bootstrap.php for monplugin
// Automatically called by bin/configure.php before plugin tests

// Example: force a test config constant
if (!defined('_MONPLUGIN_TEST_MODE')) {
    define('_MONPLUGIN_TEST_MODE', true);
}

// Example: preload a helper file
find_in_path('inc/monplugin_utils.php', '', true);
```

This file is detected and included automatically by `bin/configure.php` if the plugin `tests/` folder contains at least one `*Test.php`.

---

## Recommended plugin structure

```
monplugin/
├── paquet.xml
├── monplugin_pipelines.php
├── inc/
│   └── monplugin_filtres.php
└── tests/
    ├── bootstrap.php            <- optional
    ├── Filtre/
    │   └── MonFiltreTest.php    <- extends TestCase
    ├── Api/
    │   └── AutorisationTest.php <- extends TestCase
    └── Squelettes/
        ├── data/
        │   └── mon_test.html    <- squelette fragment for assertOkSquelette
        └── MaBaliseTest.php     <- extends SquelettesTestCase
```

### Namespace

There is no required namespace. Conventions seen in SPIP core:

```php
namespace Spip\Core\Tests\Filtre;        // SPIP core
namespace Monplugin\Tests\Filtre;         // plugin (recommended)
```

Declare the namespace in the plugin `composer.json` if present, or let PHPUnit load files through bootstrap.

---

## Ask Claude to create tests

To have an agent generate tests for your plugin, provide context like this:

```
Create PHPUnit tests for plugin {monplugin}.

File to test: {path/to/file.php}
Functions to test: {list of functions}

Conventions:
- Tests in monplugin/tests/{Category}/{NameTest}.php
- Namespace: Monplugin\Tests\{Category}
- Simple PHP functions: extends TestCase + find_in_path() in setUpBeforeClass()
- Balises/squelettes: extends SquelettesTestCase + assertOkCode() / assertEqualsCode()
- Data providers for multiple cases
- Method names like testFunctionNameNominalCase(), testFunctionNameEdgeCase()
```

---

## Quick SPIP helpers reference for tests

| Helper | Usage |
|---|---|
| `find_in_path('inc/file.php', '', true)` | Load a file through the SPIP path |
| `include_spip('inc/file')` | Equivalent without extension |
| `charger_fonction('name', 'inc')` | Load and return an inc function |
| `sql_fetsel(...)` | SQL read (requires active DB) |
| `autoriser($faire, $quoi, $id)` | Call the autorisations engine |
| `spip_tests_loger_webmestre()` | Open a webmestre session (defined in `tests/bootstrap.php`) |
