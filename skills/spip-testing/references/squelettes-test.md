# Testing BOUCLE loops with SQL fixtures — SquelettesTestCase

## When to use this pattern

Use when you need to test a **BOUCLE loop** that depends on **database content** — ordering, filtering, pagination, `{doublons}`, or any critère that changes results based on actual rows.

Examples:
- "The last 5 articles of a rubrique must appear newest-first"
- "Pagination returns the correct page slice"
- "`{doublons}` correctly excludes already-rendered items"

## Required tier

**Integration only.** `SquelettesTestCase` requires a real SPIP instance with a live database. Run with:

```bash
composer tests-integration
```

Place tests in `tests/integration/`.

---

## The pattern

Three steps, always in this order:

1. **`setUpBeforeClass`** — insert parent rows then child rows with `sql_insertq`; store IDs in static properties.
2. **Test methods** — build the BOUCLE string inline, call `assertEqualsCode` / `assertNotEqualsCode`.
3. **`tearDownAfterClass`** — delete **child rows first**, then parent rows (FK constraint order).

### Namespace

```php
use Spip\Test\SquelettesTestCase;
// NOT Spip\Core\Testing\SquelettesTestCase — that alias may not exist in all versions
```

### Limit critère syntax

Prefer `{0,N}` over `{limit N}` — `{0,N}` is portable across all SPIP 4.x versions:

```
{0,5}   // safe — first 5 rows
{limit 5}  // may not work in all versions
```

### Default ordering without `{par ...}`

Without explicit ordering critères, SPIP returns rows in ascending primary-key order (oldest inserted first). Never rely on this for correctness tests — always specify `{par champ}{inverse}` explicitly.

---

## Full working example

Tests that a BOUCLE listing the 5 newest articles of a rubrique returns them newest-first, and that a broken BOUCLE (missing sort critères) produces a different result.

```php
<?php
declare(strict_types=1);

use Spip\Test\SquelettesTestCase;

/**
 * BOUCLE listing last 5 articles from a rubrique, newest first.
 *
 * Correct:  {id_rubrique=N}{par date}{inverse}{0,5}
 * Wrong:    {id_rubrique=N}{0,5}             — missing sort critères
 */
final class BoucleArticlesOrderTest extends SquelettesTestCase
{
    private static int $rubriqueId = 0;
    /** @var int[] */
    private static array $articleIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Insert a rubrique (parent row first)
        self::$rubriqueId = (int) sql_insertq('spip_rubriques', [
            'titre'  => 'Rubrique test BoucleQ1',
            'statut' => 'publie',
            'lang'   => 'fr',
        ]);

        // Insert 6 articles with strictly ordered dates (oldest → newest)
        $dates = [
            '2020-01-01 00:00:00',
            '2020-03-15 00:00:00',
            '2020-06-30 00:00:00',
            '2021-01-10 00:00:00',
            '2021-08-20 00:00:00',
            '2022-02-14 00:00:00', // newest
        ];
        foreach ($dates as $i => $date) {
            self::$articleIds[] = (int) sql_insertq('spip_articles', [
                'titre'       => 'Article-' . ($i + 1),
                'statut'      => 'publie',
                'id_rubrique' => self::$rubriqueId,
                'date'        => $date,
                'lang'        => 'fr',
            ]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        // Delete child rows BEFORE parent rows — FK constraints require this order
        foreach (self::$articleIds as $id) {
            sql_delete('spip_articles', 'id_article = ' . (int) $id);
        }
        sql_delete('spip_rubriques', 'id_rubrique = ' . self::$rubriqueId);
        parent::tearDownAfterClass();
    }

    /**
     * Correct BOUCLE: {par date}{inverse}{0,5} must return the 5 newest articles
     * in newest-first order.
     */
    public function testBoucleArticlesOrdreChronologiqueInverse(): void
    {
        // Expected: last 5 IDs in reverse order (newest first)
        $expectedIds = array_slice(array_reverse(self::$articleIds), 0, 5);
        $expected    = implode(',', $expectedIds) . ',';

        $boucle = sprintf(
            '<BOUCLE_arts(ARTICLES){id_rubrique=%d}{par date}{inverse}{0,5}>#ID_ARTICLE,</BOUCLE_arts>',
            self::$rubriqueId
        );

        $this->assertEqualsCode($expected, $boucle);
    }

    /**
     * Wrong BOUCLE: missing {par date}{inverse} must NOT produce newest-first order.
     * assertNotEqualsCode() renders the code and asserts the result differs.
     */
    public function testBoucleArticlesSansTriEstFausse(): void
    {
        // What the correct template produces
        $correctIds = array_slice(array_reverse(self::$articleIds), 0, 5);
        $correctOut = implode(',', $correctIds) . ',';

        $wrongBoucle = sprintf(
            '<BOUCLE_arts(ARTICLES){id_rubrique=%d}{0,5}>#ID_ARTICLE,</BOUCLE_arts>',
            self::$rubriqueId
        );

        $this->assertNotEqualsCode(
            $correctOut,
            $wrongBoucle,
            [],
            'La BOUCLE sans {par date}{inverse} ne doit pas produire le même ordre que la version correcte.'
        );
    }
}
```

---

## SquelettesTestCase assertions (complete)

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

## Key pitfalls

| Pitfall | Fix |
|---|---|
| `tearDownAfterClass` deletes rubriques before articles | Always delete child rows (articles) before parent rows (rubriques) |
| Using `{limit N}` for portability | Use `{0,N}` instead |
| Relying on default order in assertions | Always specify `{par champ}` and `{inverse}` explicitly |
| Wrong namespace for `SquelettesTestCase` | Use `Spip\Test\SquelettesTestCase` |
| Storing IDs across tests without static properties | Use `private static int $id` / `private static array $ids` |
| Not calling `parent::setUpBeforeClass()` and `parent::tearDownAfterClass()` | Always call both parent methods |
