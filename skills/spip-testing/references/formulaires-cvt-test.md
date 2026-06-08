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
| `#FORMULAIRE_{nom}` rendering in a template | Integration | `SquelettesTestCase` |

---

## Testing charger / verifier / traiter directly

### Load the form PHP

In `setUpBeforeClass()`, load the form file so the `_dist` functions are defined:

```php
public static function setUpBeforeClass(): void
{
    parent::setUpBeforeClass();
    find_in_path('formulaires/mon_form.php', '', true);
}
```

For a unit test, `find_in_path` is available only if the bootstrap loads it. If not, use a plain `include`.

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

## Testing the rendered template (#FORMULAIRE_{nom})

Use `SquelettesTestCase` + `Templating::fromString` to inject the CVT functions inline:

```php
use Spip\Test\SquelettesTestCase;
use Spip\Test\Templating;

final class MonFormRenduTest extends SquelettesTestCase
{
    public function testFormulaireRendu(): void
    {
        $templating = Templating::fromString([
            'fonctions' => <<<PHP
                function formulaires_mon_form_charger_dist() {
                    return ['message_ok' => 'OK', 'titre' => ''];
                }
            PHP
            ,
        ]);

        $skel = '#FORMULAIRE_{mon_form}';
        $this->assertNotEmptyTemplate($templating, $skel);
    }

    public function testFormulaireAbsent(): void
    {
        // charger() returns false → balise renders nothing
        $templating = Templating::fromString([
            'fonctions' => <<<PHP
                function formulaires_absent_charger_dist() {
                    return false;
                }
            PHP
            ,
        ]);

        $this->assertEmptyTemplate($templating, '#FORMULAIRE_{absent}');
    }
}
```

To test the actual `.html` template file (error classes, `message_erreur` blocks…), place the form in `tests/fixtures/formulaires/` and use `Templating::fromFile()` with the path injected into SPIP's search path — or place a minimal template inline via `fromString` and verify HTML landmarks.

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
