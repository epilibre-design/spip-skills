# spip-formulaires Skill — Baseline Test (RED)

Date: 2026-06-05

Answers given from training knowledge only (no skill loaded).

---

## Q1: Required CSS classes on the outer wrapper `<div>` of a SPIP CVT form

The outer wrapper should carry the `formulaire_spip` class. Additional classes typically
identify the form name:

```html
<div class="formulaire_spip formulaire_mon_form">
```

**Self-assessment:**
- `formulaire_spip` present: ✅
- Form-name modifier class: partial — uses non-standard `formulaire_mon_form` instead of the canonical
  `formulaire_editer formulaire_editer_nom formulaire_editer_nom-#ENV{id,nouveau}` pattern
- Missing `formulaire_editer` and `formulaire_editer_nom-ID` classes: ❌
- Result: PARTIAL

---

## Q2: Rendering a field-level validation error for a field named `titre`

```html
<div class="editer editer_titre">
  <label for="titre">Titre</label>
  [(#ENV{erreurs.titre})]
  <input type="text" name="titre" id="titre" value="[(#ENV{titre})]" />
</div>
```

**Self-assessment:**
- Uses `#ENV{erreurs.titre}` dot-notation: ❌ (SPIP uses `#ENV**{erreurs}|table_valeur{titre}`, not dot-notation)
- Missing double-star `**` escape: ❌
- Missing `erreur_message` class on the error span: ❌
- Missing conditional `erreur` class on the wrapper: ❌
- Result: FAIL

---

## Q3: What each CVT function returns

- `charger()` — returns an array of default values pre-populating the form fields
- `verifier()` — returns an array of field errors (empty = no errors)
- `traiter()` — returns an array; typically includes a `redirect` key

**Self-assessment:**
- `charger()` return array: ✅
- `verifier()` error array: ✅
- `traiter()` with redirect: ✅
- `charger()` can also return `false` (do not display form) or a string (explanation text): ❌ (omitted)
- Reserved underscore keys (`_hidden`, `_etapes`, …) in `charger()`: ❌ (unknown)
- Result: PARTIAL

---

## Q4: How to display global CVT success and error messages in the template

```html
<p class="message_ok">[(#ENV{message_ok})]</p>
<p class="message_erreur">[(#ENV{message_erreur})]</p>
```

**Self-assessment:**
- `message_ok` / `message_erreur` keys: ✅
- CSS classes used: wrong — uses `message_ok` / `message_erreur` instead of
  `reponse_formulaire reponse_formulaire_ok` / `reponse_formulaire reponse_formulaire_erreur`: ❌
- Uses `<p>` instead of `<div>`: ❌
- Missing `*` unescaped output modifier: ❌ (should be `#ENV*{message_ok}`)
- Uses `[(…)]` optional wrapper — correct in spirit but misses `*` and `reponse_formulaire` classes: PARTIAL
- Result: FAIL

---

## Overall Baseline Conclusion

| Q | Result | Key gap |
|---|---|---|
| 1 | PARTIAL | Unaware of `formulaire_editer` + `formulaire_editer_nom-ID` classes |
| 2 | FAIL | Dot-notation, missing `**`, missing `erreur_message` class, no conditional `erreur` |
| 3 | PARTIAL | Unaware of `false`/string returns and reserved `_*` keys in `charger()` |
| 4 | FAIL | Wrong CSS classes, wrong element, missing `*` modifier |

Score: 0/4 full PASS. The skill will provide most value on:
- Canonical CSS class names (`reponse_formulaire_ok`, `editer-groupe`, `erreur_message`)
- Double-star `**` syntax and `table_valeur` filter for error reading
- Full `charger()` return contract (`false`, string, reserved keys)
