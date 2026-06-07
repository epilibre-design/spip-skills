---
name: spip-testing
description: Use when writing, running, or debugging PHPUnit tests for any SPIP code — plugins (filtres, autorisations, CVT forms, pipelines), squelettes, or `#BALISE` tags. Covers the full self-contained setup (composer.json, phpunit.xml, spip-cli install script), the unit-vs-integration split, lightweight mock bootstraps, SquelettesTestCase, and composer scripts (tests-unit, tests-integration, install-spip-test).
---

# SPIP testing with PHPUnit

## Quick-start

```bash
composer install            # install PHPUnit, spip/tests, spip/spip-cli
composer tests-unit         # unit tests — no SPIP needed, uses mocked stubs
composer install-spip-test  # download + install SPIP into vendor/spip/spip/
composer tests-integration  # integration tests — real SPIP + SQLite3
```

---

## Two test tiers

| Tier | Directory | Bootstrap | Needs SPIP? |
|---|---|---|---|
| **Unit** | `tests/unit/` | `tests/bootstrap.php` (mocks) | No — PHP only |
| **Integration** | `tests/integration/` | `tests/bootstrap_integration.php` | Yes — `vendor/spip/spip/` |

## Two base classes

| Use case | Class |
|---|---|
| PHP functions (filtre, autorisation, inc…) | `PHPUnit\Framework\TestCase` |
| Squelettes, `#BALISE`, `\|filtre` in templates | `Spip\Core\Testing\SquelettesTestCase` |

`SquelettesTestCase` requires the integration tier (real SPIP loaded).

---

## Decision tree — I want to…

| Goal | Read |
|---|---|
| Set up composer.json, phpunit.xml, scripts from scratch | `references/howto-test.md` §Setup |
| Write a unit test (filtre, autorisation — no real SPIP) | `references/howto-test.md` §Unit tests |
| Write a mock bootstrap (`tests/bootstrap.php`) | `references/howto-test.md` §Unit bootstrap |
| Write an integration test (SQL, CVT, pipelines) | `references/howto-test.md` §Integration tests |
| Configure `scripts/install-spip-test.sh` | `references/howto-test.md` §install-spip-test.sh template |
| Test a squelette or `#BALISE` (SquelettesTestCase) | `references/howto-test.md` §Testing squelettes |
| Verify the plugin is active in the integration environment | `references/howto-test.md` §Verify the plugin is active |
