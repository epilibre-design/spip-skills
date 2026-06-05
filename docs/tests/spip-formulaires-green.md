# spip-formulaires Skill — GREEN Verification

Date: 2026-06-05
Score: 4/4

## Results

| # | Question | Score | Source |
|---|---|---|---|
| 1 | Required CSS classes on the outer form wrapper | PASS | `references/form-structure.md` §1 |
| 2 | Render a field-level error for `titre` | PASS | `references/form-structure.md` §4 |
| 3 | What each CVT function returns | PASS | `references/cvt-formulaires.md` charger/verifier/traiter sections |
| 4 | Display global CVT success and error messages | PASS | `references/form-structure.md` §3 |

---

## Answers

### Q1 — Required CSS classes on the outer wrapper `<div>`

From `references/form-structure.md` §1:

```html
<div class="formulaire_spip formulaire_editer formulaire_editer_nom formulaire_editer_nom-#ENV{id,nouveau}">
```

Four classes are required:
- `formulaire_spip` — marks the block as a SPIP form (JS hooks, CSS resets)
- `formulaire_editer` — signals an edit-style form
- `formulaire_editer_nom` — names the form type (replace `nom` with the form identifier)
- `formulaire_editer_nom-#ENV{id,nouveau}` — scopes the form to the edited object ID (or `nouveau` for creation)

Fields and submit go inside inside a `<form method="post" action="#ENV{action}">` with `#ACTION_FORMULAIRE{#ENV{action}}` as the first child.

---

### Q2 — Rendering a field-level validation error for `titre`

From `references/form-structure.md` §4:

**Three coordinated pieces:**

```html
<!-- 1. Wrapper: conditionally add 'erreur' class -->
<div class="editer editer_titre[ (#ENV**{erreurs}|table_valeur{titre}|oui)erreur]">

  <label for="titre">Titre</label>

  <!-- 2. Error text: render only when present -->
  [<span class="erreur_message">(#ENV**{erreurs}|table_valeur{titre})</span>]

  <!-- 3. Field value: re-populated from request on error -->
  <input type="text" class="text" name="titre" id="titre" value="[(#ENV**{titre})]" />
</div>
```

Key points:
- `#ENV**{erreurs}` (double-star) prevents HTML escaping of the error array
- `table_valeur{titre}` extracts the per-field error string
- `|oui` converts a non-empty string to `1` for use in the conditional class
- CSS class on the error text must be `erreur_message`, not `error-message`

---

### Q3 — What each CVT function returns

From `references/cvt-formulaires.md`:

**`charger()`** — called on every page load (not just POST):

| Return type | Meaning |
|---|---|
| `array` | Values passed as context to the squelette template |
| `false` | Form not applicable — renders nothing |
| `string` | Form not applicable — renders the string as explanation |

Reserved `_*` keys in the array control framework behaviour (`_hidden`, `_etapes`, `_autosave_id`, …); all other keys are field defaults re-populated from `_request()` on POST.

**`verifier()`** — called only after POST:

Returns `array` of errors keyed by field name. Empty array = proceed to `traiter()`.
Special key `message_erreur` overrides the automatic global error banner.

**`traiter()`** — called only when `verifier()` returns `[]`:

| Key | Effect |
|---|---|
| `redirect` | Redirect URL after success |
| `message_ok` | Success message shown after redirect |
| `message_erreur` | Treat as failure; show error banner, no redirect |

---

### Q4 — Display global CVT success and error messages

From `references/form-structure.md` §3:

```html
[<div class="reponse_formulaire reponse_formulaire_ok">(#ENV*{message_ok})</div>]
[<div class="reponse_formulaire reponse_formulaire_erreur">(#ENV*{message_erreur})</div>]
```

Both lines use:
- `<div>` not `<p>`
- `reponse_formulaire` as the shared class, plus a state-specific modifier
- `#ENV*{…}` (single star) to avoid double-escaping already-safe HTML content
- The optional `[…]` SPIP wrapper so neither block renders when the key is empty

These blocks go immediately inside the outer wrapper div, before the `<form>` tag.

---

## Verdict

**PASS — 4/4**

The skill closes all four gaps identified in the baseline:
1. Canonical four-class wrapper (`formulaire_spip formulaire_editer formulaire_editer_nom formulaire_editer_nom-ID`)
2. Double-star `**`, `table_valeur`, `erreur_message`, and conditional `erreur` class
3. `charger()` returning `false`/string, and reserved `_*` keys
4. Correct `reponse_formulaire reponse_formulaire_ok/erreur` classes and `*` output modifier
