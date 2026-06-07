---
name: spip-testing
description: Guides PHPUnit testing of SPIP plugins and squelettes, from a self-contained composer setup to unit tests with lightweight mocks and integration tests against a real SPIP instance installed by spip-cli. Use when the user asks how to write or run tests for a SPIP plugin or squelette, or mentions PHPUnit, SquelettesTestCase, composer tests-unit / tests-integration, or testing filtres, autorisations, CVT forms, or #BALISE tags.
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
