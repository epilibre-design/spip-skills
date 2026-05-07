---
name: spip-formulaires
description: Use when creating or reviewing SPIP CVT forms, especially the standard
  HTML form structure (`formulaire_spip`, `.editer-groupe`, `.editer`, `reponse_formulaire`),
  global form messages, and field-level errors.
---

# SPIP CVT forms - Standard structure

Reference skill for the standard SPIP form structure used in private and public templates.

## Start here

Use this routing before reading anything else:

- First understand the canonical SPIP form HTML structure -> `references/form-structure.md`
- Then understand the CVT contract and lifecycle -> `references/cvt-formulaires.md`
- Only then decide whether the plugins Saisies or Verifier are useful for the specific form

Default routing rule:
- Start with `references/form-structure.md` for any form template work.
- Then read `references/cvt-formulaires.md` for any form behavior implemented in PHP.
- Use `references/plugin-saisies.md` only when declarative field definitions, repeated field structures, `#SAISIE`, `#GENERER_SAISIES`, or custom saisie types would materially simplify the form.
- Use `references/plugin-verifier.md` only when CVT validation needs reusable validator rules, normalisation, file validation, or cross-field comparison helpers.

Practical rule of thumb:
- Do not introduce Saisies for a very small form with one or two straightforward fields unless it clearly improves maintainability.
- Do not introduce Verifier when a tiny form only needs one or two obvious inline CVT checks.
- Consider Saisies and Verifier as optional improvements over the base SPIP structure and base CVT mechanism, not as mandatory defaults.

## SPIP-specific terms (always kept in original form)

| Term | Meaning |
|---|---|
| **formulaire CVT** | `charger` / `verifier` / `traiter` lifecycle for SPIP forms |
| **formulaire_spip** | Main wrapper class for a SPIP form block |
| **editer-groupe** | Group container for editable fields |
| **editer** | Per-field wrapper block |
| **message_ok** | Global success message returned by CVT |
| **message_erreur** | Global error message returned by CVT |
| **erreurs** | Environment array containing field-level errors |
| **erreur_message** | CSS class used to render a field error message |
| **boutons** | Wrapper for submit/action controls |

## Decision tree - I want to...

| Goal | Read |
|---|---|
| Understand canonical SPIP form HTML structure | `references/form-structure.md` |
| Implement a full CVT PHP contract (`charger`/`verifier`/`traiter`) | `references/cvt-formulaires.md` |
| Build a new CVT form with correct wrappers and messages | `references/form-structure.md` |
| Render field-level errors correctly | `references/form-structure.md` |
| Reload only the form block with AJAX | `references/form-structure.md` |
| Verify CSS classes expected by SPIP forms | `references/form-structure.md` |
| Handle CVT advanced features (multi-step, autosave, identifier, pipelines) | `references/cvt-formulaires.md` |
| Decide whether Saisies is actually useful for this form | `references/form-structure.md` + `references/cvt-formulaires.md` + `references/plugin-saisies.md` |
| Build fields with standard Saisies in YAML and `#SAISIE` when the form is field-driven | `references/plugin-saisies.md` |
| Render a full field list with `#GENERER_SAISIES` when repeated declarative fields are useful | `references/plugin-saisies.md` |
| Create a custom saisie type | `references/plugin-saisies.md` |
| Decide whether Verifier is actually useful for this validation logic | `references/cvt-formulaires.md` + `references/plugin-verifier.md` |
| Apply field validation with the Verifier plugin when reusable rules are helpful | `references/plugin-verifier.md` |
| Normalize field values before storage with Verifier when needed | `references/plugin-verifier.md` |
| Compare two posted fields (confirmation, bounds, equality) with Verifier when helpful | `references/plugin-verifier.md` |

## Workflow index

### Choosing the right implementation surface

1. `references/form-structure.md` -> establish the canonical SPIP form markup first
2. `references/cvt-formulaires.md` -> establish the standard CVT PHP mechanism second
3. `references/plugin-saisies.md` -> only if declarative field generation would simplify the form enough to justify the dependency
4. `references/plugin-verifier.md` -> only if reusable validators or normalisation would simplify CVT verification enough to justify the dependency

### Creating or updating a CVT form template

1. `references/form-structure.md` -> apply the canonical wrapper and field layout
2. `references/cvt-formulaires.md` -> define PHP contract functions and naming (`_dist`)
3. `references/form-structure.md` -> add global messages (`message_ok`, `message_erreur`)
4. `references/form-structure.md` -> wire field-level errors with `#ENV**{erreurs}`
5. `references/form-structure.md` -> validate submit controls and type-based input classes

### Implementing CVT logic safely

1. `references/cvt-formulaires.md` -> implement `charger()` return values and reserved keys
2. `references/cvt-formulaires.md` -> implement `verifier()` errors and global message behavior
3. `references/cvt-formulaires.md` -> implement `traiter()` result keys and redirect strategy
4. `references/cvt-formulaires.md` -> add transaction handling for partial failures
5. `references/cvt-formulaires.md` -> add `identifier()` when multiple form instances coexist

### Building forms with Saisies and Verifier

1. `references/form-structure.md` -> confirm the plain SPIP structure would be repetitive enough to justify Saisies
2. `references/cvt-formulaires.md` -> confirm the base CVT verification would benefit from reusable validators
3. `references/plugin-saisies.md` -> define standard fields in YAML or arrays when the form is sufficiently field-driven
4. `references/plugin-saisies.md` -> choose between `#SAISIE` and `#GENERER_SAISIES`
5. `references/plugin-verifier.md` -> choose validation rules, comparison rules, and normalisation strategy when the base CVT verifier becomes repetitive
6. `references/plugin-saisies.md` -> extend with custom saisies when standard field types are not enough

### Adding validation to an existing form

1. `references/cvt-formulaires.md` -> start from the existing CVT `verifier()` logic
2. `references/plugin-verifier.md` -> choose the smallest built-in validator only if it is clearer than an inline CVT check
3. `references/plugin-saisies.md` -> declare the validator inline only if the form already uses Saisies or would clearly benefit from it
4. `references/cvt-formulaires.md` -> attach field errors and optional `message_erreur`

### Reviewing a form that fails UX/CSS conventions

1. `references/form-structure.md` -> run the quick checklist section
2. `references/form-structure.md` -> verify `.editer-groupe` / `.editer` nesting
3. `references/form-structure.md` -> check global and field-level message rendering

### Enabling partial AJAX reload for a form balise

1. `references/form-structure.md` -> wrap the `#FORMULAIRE_*` balise in `<div class="ajax">`
2. `references/form-structure.md` -> verify only the form block reloads after submit

## Minimal template pattern

```html
<div class="formulaire_spip formulaire_editer formulaire_editer_x formulaire_editer_x-#ENV{id_x,nouveau}">
  [<div class="reponse_formulaire reponse_formulaire_ok">(#ENV*{message_ok})</div>]
  [<div class="reponse_formulaire reponse_formulaire_erreur">(#ENV*{message_erreur})</div>]

  <form method="post" action="#ENV{action}">
    #ACTION_FORMULAIRE{#ENV{action}}
    <div class="editer-groupe">
      <div class="editer editer_titre obligatoire[ (#ENV**{erreurs}|table_valeur{titre}|oui)erreur]">
        <label for="titre"><:info_titre:></label>
        [<span class="erreur_message">(#ENV**{erreurs}|table_valeur{titre})</span>]
        <input type="text" class="text" name="titre" id="titre" value="[(#ENV**{titre})]" />
      </div>
    </div>
    <p class="boutons">
      <input type="submit" class="submit" value="<:bouton_enregistrer:>" />
    </p>
  </form>
</div>
```

## Source docs

- Detailed structure and variants: `references/form-structure.md`
- CVT contract and advanced behavior: `references/cvt-formulaires.md`
- Saisies reference when the plugin is pertinent: `references/plugin-saisies.md`
- Verifier reference when the plugin is pertinent: `references/plugin-verifier.md`

Scope note: this skill adds dedicated optional references for Saisies and Verifier only (no dedicated Nospam reference).

Not for general plugin architecture work -> use the `spip-plugins` skill.
