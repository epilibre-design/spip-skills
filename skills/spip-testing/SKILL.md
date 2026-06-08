---
name: spip-testing
description: Guides automated testing of SPIP plugins and squelettes using PHPUnit — the standard test tool for SPIP. Covers the full self-contained setup (composer, spip-cli), unit tests with lightweight mocks, and integration tests against a real SPIP instance. Use whenever the user wants to test, verify, or validate a SPIP plugin or squelette — even if they don't mention PHPUnit — including questions like "how do I test my plugin", "I want to make sure my filtre works", or "how do I write tests for my autorisation / CVT form / #BALISE".
---

# SPIP testing

PHPUnit is the standard test tool for SPIP plugins and squelettes, used by SPIP core and the official plugin ecosystem.

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
| Squelettes, `#BALISE`, `\|filtre` in templates | `Spip\Test\SquelettesTestCase` |

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
| Test a BOUCLE loop with DB fixtures (ordering, doublons, pagination) | `references/squelettes-test.md` |
