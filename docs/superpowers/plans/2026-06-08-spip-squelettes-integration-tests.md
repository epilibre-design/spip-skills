# SPIP Squelettes Integration Tests — Q1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a PHPUnit integration test suite under `tests/` that verifies Q1 from `docs/tests/spip-squelettes-green.md` — a BOUCLE ARTICLES ordered by date descending — and documents the combined SQL-setup + SquelettesTestCase pattern as a new `references/squelettes-test.md` in the `spip-testing` skill.

**Architecture:** The test project lives at `tests/` with its own `composer.json`. SPIP core is copied from `tests/readonly-src/spip/` (already present, no network download). `spip-cli.patch` is applied to the installed spip-cli before the DB install step. Integration tests use `Spip\Core\Testing\SquelettesTestCase` from `spip/tests` with SQL fixtures inserted in `setUpBeforeClass`.

**Tech Stack:** PHP 8.2+, PHPUnit 13, spip/tests (SquelettesTestCase), spip/spip-cli (patched), SQLite3.

---

## File Map

| File | Status | Responsibility |
|---|---|---|
| `tests/composer.json` | CREATE | PHPUnit + spip/tests + spip/spip-cli deps |
| `tests/phpunit.xml` | CREATE | Integration testsuite config |
| `tests/bootstrap_integration.php` | CREATE | Load SPIP from `vendor/spip/spip/` |
| `tests/scripts/install-spip-test.sh` | CREATE | Copy readonly-src → vendor, patch spip-cli, install DB |
| `tests/integration/BoucleArticlesOrderTest.php` | CREATE | Q1 test: correct BOUCLE passes, wrong BOUCLE fails |
| `home/tgce/.claude/skills/spip-testing/references/squelettes-test.md` | CREATE | Document the combined SQL + SquelettesTestCase pattern |

---

## Task 1: Scaffold — composer.json + phpunit.xml

**Files:**
- Create: `tests/composer.json`
- Create: `tests/phpunit.xml`

- [ ] **Step 1.1: Create `tests/composer.json`**

```json
{
    "name": "spip-skill/squelettes-tests",
    "description": "Integration tests for SPIP squelettes skill verification",
    "type": "project",
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
        "tests-integration": [
            "if [ ! -f vendor/spip/spip/config/connect.php ]; then echo 'SPIP non installé.' >&2; composer install-spip-test || exit 1; fi",
            "vendor/bin/phpunit --colors --testsuite integration --bootstrap bootstrap_integration.php"
        ]
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "composer/installers": true
        }
    }
}
```

- [ ] **Step 1.2: Create `tests/phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="bootstrap_integration.php"
    cacheDirectory=".phpunit.cache"
>
    <testsuites>
        <testsuite name="integration">
            <directory suffix="Test.php">integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 1.3: Run composer install**

```bash
cd /src/spip/tests
composer install
```

Expected: `vendor/bin/phpunit` and `vendor/bin/spip` created.  
If VCS access fails for `spip/tests` or `spip/spip-cli`, check network access.

- [ ] **Step 1.4: Commit scaffold**

```bash
git add tests/composer.json tests/phpunit.xml tests/composer.lock
git commit -m "test: scaffold PHPUnit project for squelettes integration tests"
```

---

## Task 2: Install script — copy SPIP + apply patch + install DB

**Files:**
- Create: `tests/scripts/install-spip-test.sh`

- [ ] **Step 2.1: Create install script**

```sh
#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SPIP_ROOT="$ROOT_DIR/vendor/spip/spip"
SPIP_CLI_DIR="$ROOT_DIR/vendor/spip/spip-cli"
SPIP_BIN="$ROOT_DIR/vendor/bin/spip"
SPIP_SRC="$ROOT_DIR/readonly-src/spip"
PATCH_FILE="$ROOT_DIR/spip-cli.patch"

# 1. Copy SPIP core from readonly-src (no network download)
if [ ! -f "$SPIP_ROOT/ecrire/inc_version.php" ]; then
    echo "Copying SPIP from $SPIP_SRC ..." >&2
    mkdir -p "$(dirname "$SPIP_ROOT")"
    cp -a "$SPIP_SRC/." "$SPIP_ROOT/"
fi

# 2. Apply spip-cli patch (idempotent: patch --forward exits 0 if already applied)
if [ -f "$PATCH_FILE" ] && [ -d "$SPIP_CLI_DIR" ]; then
    echo "Applying spip-cli.patch ..." >&2
    patch --forward --directory="$SPIP_CLI_DIR" -p1 < "$PATCH_FILE" || true
fi

cd "$SPIP_ROOT"

# 3. Prepare SPIP (creates dirs, sets permissions)
"$SPIP_BIN" core:preparer

# 4. Install DB (SQLite3, idempotent)
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

echo "SPIP integration environment ready." >&2
```

- [ ] **Step 2.2: Make executable**

```bash
chmod +x /src/spip/tests/scripts/install-spip-test.sh
```

- [ ] **Step 2.3: Run the install script**

```bash
cd /src/spip/tests
composer install-spip-test
```

Expected output ends with: `SPIP integration environment ready.`  
Verify: `ls vendor/spip/spip/config/connect.php` should exist.

- [ ] **Step 2.4: Commit**

```bash
git add tests/scripts/install-spip-test.sh
git commit -m "test: add SPIP install script (copy readonly-src, apply spip-cli patch, install SQLite DB)"
```

---

## Task 3: Bootstrap

**Files:**
- Create: `tests/bootstrap_integration.php`

- [ ] **Step 3.1: Create bootstrap**

```php
<?php
declare(strict_types=1);

$spipRoot = dirname(__FILE__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}
require_once $spipRoot . '/ecrire/inc_version.php';

include_spip('inc/plugin');
actualise_plugins_actifs();
```

Note: No `_chemin()` call needed here — we are not testing a plugin, only inline BOUCLE rendering.

- [ ] **Step 3.2: Verify bootstrap loads**

```bash
cd /src/spip/tests
vendor/bin/php -r "require 'bootstrap_integration.php'; echo 'OK' . PHP_EOL;"
```

Expected: `OK` (no fatal errors).

- [ ] **Step 3.3: Commit**

```bash
git add tests/bootstrap_integration.php
git commit -m "test: add SPIP integration bootstrap"
```

---

## Task 4: Write the Q1 test

**Files:**
- Create: `tests/integration/BoucleArticlesOrderTest.php`

The test must:
1. In `setUpBeforeClass`: insert one rubrique + 6 articles with distinct, known dates.
2. `testBoucleArticlesOrdreChronologiqueInverse`: verify the correct BOUCLE (with `{par date}{inverse}{limit 5}`) returns the 5 newest articles in newest-first order.
3. `testBoucleArticlesSansTriEstFausse`: verify the wrong BOUCLE (missing `{par date}{inverse}`) produces a **different** order — proving the sort critères are not optional.
4. In `tearDownAfterClass`: delete all inserted rows.

- [ ] **Step 4.1: Write the failing test for the wrong BOUCLE**

Create `tests/integration/BoucleArticlesOrderTest.php`:

```php
<?php
declare(strict_types=1);

use Spip\Core\Testing\SquelettesTestCase;

/**
 * Q1 — BOUCLE listing last 5 articles from a rubrique, newest first.
 *
 * Correct:  {id_rubrique=N}{par date}{inverse}{limit 5}
 * Wrong:    {id_rubrique=N}{limit 5}             — missing sort critères
 */
final class BoucleArticlesOrderTest extends SquelettesTestCase
{
    private static int $rubriqueId = 0;
    /** @var int[] */
    private static array $articleIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Insert a rubrique
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
        foreach (self::$articleIds as $id) {
            sql_delete('spip_articles', 'id_article = ' . (int) $id);
        }
        sql_delete('spip_rubriques', 'id_rubrique = ' . self::$rubriqueId);
        parent::tearDownAfterClass();
    }

    /**
     * Correct BOUCLE: {par date}{inverse}{limit 5} must return articles
     * in newest-first order (articles 6, 5, 4, 3, 2 — skipping the oldest).
     */
    public function testBoucleArticlesOrdreChronologiqueInverse(): void
    {
        // Expected: last 5 IDs in reverse order (newest first)
        $expectedIds = array_slice(array_reverse(self::$articleIds), 0, 5);
        $expected    = implode(',', $expectedIds) . ',';

        $boucle = sprintf(
            '<BOUCLE_arts(ARTICLES){id_rubrique=%d}{par date}{inverse}{limit 5}>#ID_ARTICLE,</BOUCLE_arts>',
            self::$rubriqueId
        );

        $this->assertEqualsCode($expected, $boucle);
    }

    /**
     * Wrong BOUCLE: missing {par date}{inverse} must NOT produce newest-first order.
     * This is the intentionally broken version from the Q1 green-test scenario.
     */
    public function testBoucleArticlesSansTriEstFausse(): void
    {
        // What the correct template produces
        $correctIds = array_slice(array_reverse(self::$articleIds), 0, 5);
        $correctOut = implode(',', $correctIds) . ',';

        $wrongBoucle = sprintf(
            '<BOUCLE_arts(ARTICLES){id_rubrique=%d}{limit 5}>#ID_ARTICLE,</BOUCLE_arts>',
            self::$rubriqueId
        );

        // The wrong template must produce a different result.
        // We assert it does NOT equal the correct newest-first output.
        $wrongOut = $this->renderCode($wrongBoucle);
        $this->assertNotSame(
            $correctOut,
            $wrongOut,
            'La BOUCLE sans {par date}{inverse} ne doit pas produire le même ordre que la version correcte.'
        );
    }
}
```

**⚠ Note on `renderCode()`:** The method name to get the raw rendered string from `SquelettesTestCase` needs verification. After `composer install`, check:

```bash
grep -r 'function render\|function eval\|function compile' \
  /src/spip/tests/vendor/spip/tests/src/ 2>/dev/null | head -20
```

If `renderCode()` does not exist, use `evalCode()`, `compileCode()`, or wrap the call in a try/catch on `assertEqualsCode` after capturing output via `ob_start()`. Adjust the method name in Step 4.1 before running.

- [ ] **Step 4.2: Run test — expect failure on wrong-BOUCLE test**

```bash
cd /src/spip/tests
vendor/bin/phpunit --colors --testsuite integration --bootstrap bootstrap_integration.php \
  --filter BoucleArticlesOrderTest
```

Expected at this stage:
- `testBoucleArticlesOrdreChronologiqueInverse` → **PASS** (correct BOUCLE works)
- `testBoucleArticlesSansTriEstFausse` → may **ERROR** if `renderCode()` method name is wrong

If ERROR on method name, run the grep from Step 4.1 to find the correct method and fix the test.

- [ ] **Step 4.3: Confirm both tests pass with correct method name**

After fixing the render method name if needed, re-run:

```bash
vendor/bin/phpunit --colors --testsuite integration --bootstrap bootstrap_integration.php \
  --filter BoucleArticlesOrderTest
```

Expected: **2 passed** (green).  
The wrong-BOUCLE test passes because the broken template produces a different order than expected.

- [ ] **Step 4.4: Commit**

```bash
git add tests/integration/BoucleArticlesOrderTest.php
git commit -m "test(squelettes): add Q1 BOUCLE ordering integration test — correct vs wrong critères"
```

---

## Task 5: Document the new pattern in spip-testing skill

**Files:**
- Create: `/home/tgce/.claude/skills/spip-testing/references/squelettes-test.md`
- Modify: `/home/tgce/.claude/skills/spip-testing/SKILL.md` (add entry to decision tree)

This task fills the gap identified before implementation: the `spip-testing` skill had no example combining SQL fixture setup with `SquelettesTestCase`.

- [ ] **Step 5.1: Create `references/squelettes-test.md`**

Write the reference file documenting:
- The combined pattern: `setUpBeforeClass` with `sql_insertq` + `SquelettesTestCase` assertions
- How to test BOUCLE ordering (correct vs wrong template)
- The `renderCode()` / `evalCode()` method for raw string comparison
- Teardown pattern with `sql_delete`
- Full working example (copy from `BoucleArticlesOrderTest.php` above, with verified method name)

- [ ] **Step 5.2: Update decision tree in `SKILL.md`**

Add row to the decision tree table:

```markdown
| Test a BOUCLE loop with DB fixtures (ordering, doublons, pagination) | `references/squelettes-test.md` |
```

- [ ] **Step 5.3: Commit**

```bash
git add \
  /home/tgce/.claude/skills/spip-testing/references/squelettes-test.md \
  /home/tgce/.claude/skills/spip-testing/SKILL.md
git commit -m "docs(spip-testing): add squelettes-test.md — SQL fixtures + SquelettesTestCase pattern"
```

---

## Self-Review

**Spec coverage:**
- ✅ Wrong Q1 example (missing `{par date}{inverse}`) created and tested
- ✅ Test goes in `tests/` arbo as requested
- ✅ Does not use spip-squelettes skill
- ✅ Gap identified (renderCode method) is documented with a resolution step
- ✅ Skill improvement (new reference file) planned in Task 5

**Placeholder scan:**
- Step 4.1 note on `renderCode()` is actionable (grep command provided)
- Step 5.1 says "write the reference file documenting" without full content — this is intentional since the content depends on the verified method name found in Task 4. The engineer must fill it after Task 4.

**Type consistency:**
- `self::$rubriqueId` is `int` throughout
- `self::$articleIds` is `int[]` throughout
- `$expected` is a string of comma-separated IDs, consistent between correct/wrong tests
