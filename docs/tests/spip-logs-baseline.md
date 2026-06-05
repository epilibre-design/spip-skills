# spip-logs Skill — Baseline Test (RED)

Date: 2026-06-05

Answers given from training knowledge only (no skill loaded).

---

## Q1: Two SPIP logging functions and their difference

SPIP provides `spip_log()` for writing to log files. For audit trails there may be
a `log()` or similar mechanism, but the primary function is `spip_log()`:

```php
spip_log('Something happened');
```

**Self-assessment:**
- Names `spip_log()`: ✅
- Names `journal()` as the second mechanism: ❌ (unknown or confused with generic PHP logging)
- Explains `spip_log()` writes to files in `tmp/log/`: ❌ (not stated)
- Explains `journal()` writes to DB, visible at `?exec=journal`: ❌ (unknown)
- Result: FAIL

---

## Q2: Which `_LOG_*` constant is NOT written by default, and how to enable it

SPIP log levels are controlled by constants. Debug logging may require enabling verbose mode.
The exact constant name and mechanism are unclear without the skill.

```php
// Guessed answer:
spip_log('debug info', 'monplugin', LOG_DEBUG);  // wrong constant name
```

**Self-assessment:**
- Identifies `_LOG_DEBUG` specifically as not written by default: ❌ (constant name wrong)
- Explains `_LOG_FILTRE_GRAVITE` threshold mechanism: ❌ (unknown)
- Knows default threshold is `_LOG_INFO` (value 6) and `_LOG_DEBUG` is value 8: ❌
- Knows how to raise the threshold to enable debug output: ❌
- Result: FAIL

---

## Q3: Write a warning log call using a plugin-specific log file

```php
// Best guess without skill:
spip_log('Empty result from external API', LOG_WARNING);
```

**Self-assessment:**
- Correct function `spip_log()`: ✅
- Uses the `$type` parameter to create a plugin-specific log file: ❌ (argument order wrong — puts warning level in `$type` slot)
- Uses wrong constant name `LOG_WARNING` instead of `_LOG_AVERTISSEMENT`: ❌
- Correct three-argument signature `($message, $type, $gravite)`: ❌
- Result: FAIL

---

## Q4: Record an audit event in the SPIP private space linked to article #42

```php
// Without the skill, likely to fall back to spip_log():
spip_log('Article #42 published by ' . $id_auteur, 'audit');
```

**Self-assessment:**
- Uses `journal()` function: ❌ (uses `spip_log()` instead)
- Passes `id_objet`, `objet`, `etat` options: ❌ (unknown)
- Entry visible at `?exec=journal` in private space: ❌ (confused with file logs)
- Result: FAIL

---

## Overall Baseline Conclusion

| Q | Result | Key gap |
|---|---|---|
| 1 | FAIL | Unaware of `journal()` and the file vs DB distinction |
| 2 | FAIL | Unknown `_LOG_FILTRE_GRAVITE`, wrong constant names |
| 3 | FAIL | Wrong argument order, wrong constant name for warning level |
| 4 | FAIL | Uses `spip_log()` instead of `journal()`; unaware of `id_objet`/`objet`/`etat` options |

Score: 0/4 full PASS. The skill will provide most value on:
- The `spip_log()` vs `journal()` distinction (files vs DB)
- `_LOG_*` constant names and the `_LOG_FILTRE_GRAVITE` threshold mechanism
- Three-argument `spip_log($message, $type, $gravite)` signature
- `journal()` option keys for linking to content objects
