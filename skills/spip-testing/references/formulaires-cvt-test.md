# Testing SPIP CVT forms

Cross-reference: `spip-formulaires` covers the CVT contract (charger/verifier/traiter); this document covers **how to test** it.

---

## Which tier?

| What to test | Tier | Base class |
|---|---|---|
| `charger()` with no DB (returns static data) | Unit | `TestCase` |
| `charger()` touching DB | Integration | `TestCase` |
| `verifier()` (pure validation logic) | Unit | `TestCase` |
| `traiter()` (writes DB, sends email…) | Integration | `TestCase` |
| Form `.html` template rendering | Integration | `SquelettesTestCase` |
| Parent squelette with `#FORMULAIRE_CVTFORM{nom}` | Integration | `SquelettesTestCase` |

---

## Testing charger / verifier / traiter directly

### Load the form PHP

For files in `tests/fixtures/formulaires/`, use `include_once` directly — `find_in_path` is not suitable here because the fixtures directory is not in SPIP's search path by default:

```php
public static function setUpBeforeClass(): void
{
    parent::setUpBeforeClass();
    include_once __DIR__ . '/../fixtures/formulaires/mon_form.php';
    // Defines formulaires_mon_form_charger_dist(), _verifier_dist(), _traiter_dist()
}
```

`find_in_path('formulaires/mon_form.php', '', true)` is the alternative when the form ships with the plugin and lives where SPIP can find it (e.g., the plugin's `formulaires/` directory is in the SPIP path). For test fixtures, `include_once` is simpler and has no path dependency.

### Simulate POST data

`_request()` reads from `$_GET` / `$_POST`. Set and clear around each test:

```php
protected function setUp(): void
{
    $_POST = [];
}

protected function tearDown(): void
{
    $_POST = [];
}
```

Then in each test method:

```php
$_POST['titre'] = 'Mon titre valide';
$_POST['texte'] = 'Contenu suffisamment long.';
$errors = formulaires_mon_form_verifier_dist();
$this->assertSame([], $errors);
```

Or use SPIP's `set_request()` helper (available in integration tier, requires `inc/utils.php`):

```php
set_request('titre', 'Mon titre valide');
set_request('texte', 'Contenu suffisamment long.');
$errors = formulaires_mon_form_verifier_dist();
```

### Testing charger()

```php
$valeurs = formulaires_mon_form_charger_dist($id_objet);

// Normal load: must return array with expected keys
$this->assertIsArray($valeurs);
$this->assertArrayHasKey('titre', $valeurs);

// Inapplicable form: must return false or a string
$valeurs = formulaires_mon_form_charger_dist(0 /* no object */);
$this->assertFalse($valeurs);
```

### Testing verifier()

```php
// Invalid: titre too short
$_POST['titre'] = 'ab';
$errors = formulaires_mon_form_verifier_dist($id_objet);
$this->assertArrayHasKey('titre', $errors);

// Valid: no errors
$_POST['titre'] = 'Titre valide';
$errors = formulaires_mon_form_verifier_dist($id_objet);
$this->assertSame([], $errors);
```

### Testing traiter()

`traiter()` has side effects (DB write, email, …). Clean up in `tearDownAfterClass`:

```php
public static function tearDownAfterClass(): void
{
    if (self::$insertedId) {
        sql_delete('spip_monplugin', 'id_monplugin=' . intval(self::$insertedId));
    }
    parent::tearDownAfterClass();
}

public function testTraiterInserts(): void
{
    $_POST['titre'] = 'Titre valide';
    $result = formulaires_mon_form_traiter_dist();
    $this->assertArrayHasKey('id_monplugin', $result);
    self::$insertedId = $result['id_monplugin'];

    // row must exist
    $row = sql_fetsel('titre', 'spip_monplugin', 'id_monplugin=' . intval(self::$insertedId));
    $this->assertSame('Titre valide', $row['titre']);
}

public function testTraiterFailure(): void
{
    // simulate an unrecoverable condition — traiter() must return message_erreur
    $_POST['titre'] = '';   // force a failure path
    $result = formulaires_mon_form_traiter_dist();
    $this->assertArrayHasKey('message_erreur', $result);
}
```

---

## Testing the form `.html` template rendering

`Templating::fromString()->render($source, $env)` compiles a template string and renders it with a given ENV. The ENV must mirror what SPIP normally passes via the CVT machinery.

### Pattern — render form HTML with simulated ENV

```php
use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

final class MonFormRenduTest extends SquelettesTestCase
{
    private const FIXTURE_HTML = __DIR__ . '/../fixtures/formulaires/mon_form.html';
    private const FIXTURE_PHP  = __DIR__ . '/../fixtures/formulaires/mon_form.php';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        include_once self::FIXTURE_PHP; // defines charger/verifier/traiter _dist functions
    }

    private function render(array $overrides = []): string
    {
        // charger() returns the initial ENV that SPIP would pass to the template
        $env = array_merge(formulaires_mon_form_charger_dist(), $overrides);
        $raw = Templating::fromString()->render(
            file_get_contents(self::FIXTURE_HTML),
            $env
        );
        return (string) preg_replace('/<!--.*?-->/s', '', $raw);
    }

    public function testRenduSansErreurs(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('class="formulaire_spip', $html);
        $this->assertStringNotContainsString('erreur_message', $html);
    }

    public function testRenduAvecErreurChampTitre(): void
    {
        $html = $this->render(['erreurs' => ['titre' => 'Le titre est obligatoire.']]);
        $this->assertStringContainsString('erreur_message', $html);
        $this->assertStringContainsString('Le titre est obligatoire.', $html);
    }

    public function testRenduAvecMessageOk(): void
    {
        $html = $this->render(['message_ok' => 'Formulaire envoyé avec succès.']);
        $this->assertStringContainsString('reponse_formulaire_ok', $html);
        $this->assertStringContainsString('Formulaire envoyé avec succès.', $html);
    }
}
```

### Alternative — inject CVT functions inline via `fonctions`

`Templating::fromString([fonctions => ...])` creates a `_fonctions.php` alongside the compiled template that is auto-included. Use it to inject CVT PHP without loading a file, or to load the fixture PHP file:

```php
// Inline stub (for isolated unit rendering):
$templating = Templating::fromString([
    'fonctions' => <<<PHP
        function formulaires_mon_form_charger_dist() {
            return ['message_ok' => '', 'titre' => ''];
        }
    PHP,
]);
$html = $templating->render('#FORMULAIRE_{mon_form}', []);
$this->assertNotEmpty($html);

// Or load the actual fixture PHP:
$templating = Templating::fromString([
    'fonctions' => file_get_contents(__DIR__ . '/../fixtures/formulaires/mon_form.php'),
]);
$html = $templating->render('#FORMULAIRE_{mon_form}', []);
```

> **Note:** `#FORMULAIRE_{mon_form}` (not `#FORMULAIRE_CVTFORM{mon_form}`) is used here. `FORMULAIRE_CVTFORM` requires `find_in_path` to locate both the `.php` and `.html` files. See the next section.

---

## Testing a parent squelette with `#FORMULAIRE_CVTFORM{nom}`

When a parent squelette contains `#FORMULAIRE_CVTFORM{nom}`, SPIP needs to resolve two files via `find_in_path`:
1. `formulaires/nom.php` — loads charger/verifier/traiter
2. `formulaires/nom.html` — renders the form template

Both must be discoverable in SPIP's search path. Files in `tests/fixtures/` are not there by default.

### How SPIP's path works (internals summary)

`find_in_path($file)` iterates `creer_chemin()` → each entry is checked as `app()->getCwd() . '/' . $entry . $file`. `app()->getCwd()` is the `chdir` target from the test bootstrap:

```php
// bootstrap_integration.php
$spipRoot = dirname(__FILE__) . '/vendor/spip/spip';
chdir($spipRoot); // CWD during all integration tests
```

So CWD = `tests/vendor/spip/spip/`. SPIP only finds files accessible via a **relative path from that CWD**.

`$GLOBALS['dossier_squelettes']` is prepended to the search path by `_chemin()`. Setting it to a relative path (from CWD) injects a new search root. Changing the value triggers automatic recalculation in both `find_in_path` and `creer_chemin()`.

### Relative path from CWD to `tests/fixtures/`

```
CWD:      tests/vendor/spip/spip/
fixtures: tests/fixtures/
relative: ../../../fixtures
```

Verify: `tests/vendor/spip/spip/` → up 3 → `tests/` → `fixtures/` ✓

### Setup pattern

```php
final class PageAvecFormulaireTest extends SquelettesTestCase
{
    private const PARENT_SKEL   = __DIR__ . '/../fixtures/pageAvecFormulaire.html';
    // fixtures/formulaires/nom.php and nom.html are looked up via find_in_path
    // relative to CWD = vendor/spip/spip/  →  ../../../fixtures
    private const FIXTURES_PATH = '../../../fixtures';

    private static string $savedDossier = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$savedDossier = $GLOBALS['dossier_squelettes'];
        $GLOBALS['dossier_squelettes'] = self::FIXTURES_PATH;
        // find_in_path detects dossier_squelettes change on next call → creer_chemin()
        // rebuilds path_full as: [../../../fixtures/] + path_base
        // new path_sig → path_files cache invalidated → fresh find_in_path lookups
    }

    public static function tearDownAfterClass(): void
    {
        $GLOBALS['dossier_squelettes'] = self::$savedDossier;
        // _chemin('') rebuilds path_full from path_base + restored dossier_squelettes
        // path_sig reverts → clean state for subsequent test classes
        parent::tearDownAfterClass();
    }

    private function render(array $env = []): string
    {
        $raw = Templating::fromString()->render(
            file_get_contents(self::PARENT_SKEL),
            $env
        );
        return (string) preg_replace('/<!--.*?-->/s', '', $raw);
    }

    public function testFormulairePresent(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('class="formulaire_spip', $html);
    }
}
```

### Required file layout in `tests/fixtures/`

```
tests/fixtures/
  pageAvecFormulaire.html          ← parent squelette: #FORMULAIRE_CVTFORM{nom}
  formulaires/
    nom.php                        ← charger_dist() / verifier_dist() / traiter_dist()
    nom.html                       ← form HTML template
```

### Caveats

- **`dossier_squelettes` accepts colon-separated paths**: `'../../../fixtures:../../../other'` adds multiple dirs.
- **`include_once` cache**: once `nom.php` is loaded, its functions remain defined for the PHP process lifetime even after restore. This is expected — PHP cannot undefine functions.
- **`find_in_path`'s static `$dirs` cache** stores `is_dir()` results per path key. New paths added during setup are checked fresh (not in cache yet). Restoring doesn't cause stale `$dirs` entries because the keys include the directory prefix.
- **If `dossier_squelettes` was already set** (e.g., a previous test class set it), the save/restore pattern preserves the original value correctly.

---

## `Templating::fromFile()` — scope and limitation

`Templating::fromFile()` uses `FileLoader`, which computes the fond name by stripping `_SPIP_TEST_CHDIR` prefix from the given absolute path:

```php
// FileLoader::getSourceFile($name)
$fond = pathinfo($name)['dirname'] . '/' . pathinfo($name)['filename'];
return substr($fond, strlen(_SPIP_TEST_CHDIR) + 1);
// → fond name relative to vendor/spip/spip/
```

**`fromFile()` only works for files inside `_SPIP_TEST_CHDIR`** (= `vendor/spip/spip/`). Test fixtures in `tests/fixtures/` are outside that tree — `substr` produces a garbage fond name.

For form fixtures in `tests/fixtures/`, always use `Templating::fromString()` + `file_get_contents()`:

```php
// ✓ correct for tests/fixtures/
Templating::fromString()->render(file_get_contents(self::FIXTURE_HTML), $env);

// ✗ wrong for tests/fixtures/ — fond name is garbage
Templating::fromFile()->render(self::FIXTURE_HTML, $env);
```

---

## Minimal full example

Unit test that covers `charger()` and `verifier()` for a form with no DB dependency:

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MonFormUnitTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        include_once __DIR__ . '/../../formulaires/mon_form.php';
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testChargerReturnsTitre(): void
    {
        $v = formulaires_mon_form_charger_dist();
        $this->assertArrayHasKey('titre', $v);
    }

    public function testVerifierRequiresTitre(): void
    {
        $errors = formulaires_mon_form_verifier_dist();
        $this->assertArrayHasKey('titre', $errors);
    }

    public function testVerifierPassesWithValidTitre(): void
    {
        $_POST['titre'] = 'Titre valide';
        $errors = formulaires_mon_form_verifier_dist();
        $this->assertSame([], $errors);
    }
}
```

---

## Common mistakes

| Mistake | Fix |
|---|---|
| Calling `verifier()` without populating `$_POST` | Set `$_POST` in `setUp()` before each test |
| Forgetting to clear `$_POST` between tests | Use `tearDown(): void { $_POST = []; }` |
| Not cleaning up rows inserted by `traiter()` | Delete in `tearDownAfterClass()`, child rows first |
| Testing `traiter()` in unit tier when it uses `sql_insertq` | Move to integration tier |
| Using `SquelettesTestCase` to test PHP functions | Use plain `TestCase` for direct function calls |
| `Templating::fromFile()` for fixtures in `tests/fixtures/` | Use `fromString()` + `file_get_contents()` instead |
| `find_in_path` to load a fixture PHP file | Use `include_once __DIR__ . '/../fixtures/formulaires/nom.php'` |
| Absolute path in `dossier_squelettes` | Only relative paths work — `find_in_path` always prefixes with `app()->getCwd()` |
