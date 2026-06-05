# spip-lang Skill — Baseline Test (RED)

Date: 2026-06-05

Answers given from training knowledge only (no skill loaded).

---

## Q1: Correct PHP structure for a SPIP language file

```php
<?php
$GLOBALS['i18n']['monplugin'] = [
    'titre_liste' => 'Liste des objets',
    'bouton_ajouter' => 'Ajouter',
];
```

**Self-assessment:**
- Uses `$GLOBALS['i18n']` assignment: ❌ (SPIP lang files use `return [...]`)
- Missing mandatory header comment: ❌
- Keys use correct `snake_case`: ✅
- No closing `?>`: ✅ (correct, but for wrong reasons)
- Result: FAIL

---

## Q2: Output a translated string in a SPIP squelette

```html
<!-- Guessed approach: -->
[(#VAL{titre_liste}|_T)]
```

**Self-assessment:**
- Uses `|_T` filter form: ✅ (valid but non-idiomatic for simple cases)
- Missing module prefix in the key — should be `monplugin:titre_liste`: ❌
- Unaware of the short `<:module:key:>` syntax which is the recommended form: ❌
- Result: PARTIAL

---

## Q3: Call a translated string with a `@nb@` placeholder in PHP

```php
// Guessed — uncertain about exact placeholder syntax:
echo _T('monplugin:info_nb_objets', ['nb' => $count]);
```

**Self-assessment:**
- Correct function `_T()`: ✅
- Correct `module:key` format: ✅
- Correct `['nb' => $count]` args array: ✅
- Knows placeholder syntax is `@nb@` in the lang file: uncertain
- Knows singular/plural convention (`info_1_objet` / `info_nb_objets`): ❌ (not mentioned)
- Result: PASS (basic call correct; plural convention not known)

---

## Q4: Mandatory keys in `paquet-monplugin_fr.php`

The paquet file probably needs the plugin name and description, but exact key names are unknown:

```php
return [
    'nom'         => 'Mon Plugin',
    'description' => 'Description du plugin',
];
```

**Self-assessment:**
- Key `monplugin_description` (exact name including plugin prefix): ❌ (uses `description`)
- Key `monplugin_slogan` (short tagline): ❌ (uses `nom`, which is wrong)
- Both keys must use `{prefix}_{key}` naming: ❌ (unaware)
- Result: FAIL

---

## Overall Baseline Conclusion

| Q | Result | Key gap |
|---|---|---|
| 1 | FAIL | Uses `$GLOBALS['i18n']` instead of `return [...]`; missing header comment |
| 2 | PARTIAL | Unaware of `<:module:key:>` short syntax; missing module prefix |
| 3 | PASS | Basic `_T()` call correct; singular/plural convention not known |
| 4 | FAIL | Wrong key names — should be `monplugin_description` and `monplugin_slogan` |

Score: 1/4 full PASS. The skill will provide most value on:
- `return [...]` file format with the fixed header comment
- `<:module:key:>` as the recommended squelette syntax
- Singular/plural key convention (`info_1_objet` / `info_nb_objets`)
- `paquet-prefix_XX.php` mandatory key names including the `{prefix}_` prefix
