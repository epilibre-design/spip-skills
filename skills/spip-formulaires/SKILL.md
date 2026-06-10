---
name: spip-formulaires
description: Use when creating, reviewing, or debugging SPIP CVT forms — canonical HTML
  wrappers, field/global errors, `charger`/`verifier`/`traiter`/`identifier`, or when
  deciding whether Saisies or Verifier are appropriate.
---

# SPIP CVT forms

A **formulaire CVT** is a SPIP form built around the PHP contract
`charger` / `verifier` / `traiter`, with an optional `identifier`.

## Quick routing

| Goal | Read |
|---|---|
| Build or review a standard SPIP form template | `references/form-structure.md` |
| Render global messages and field-level errors correctly | `references/form-structure.md` |
| Implement `charger()`, `verifier()`, `traiter()`, or `identifier()` | `references/cvt-formulaires.md` |
| Check `charger()` return values (`array`, `false`, `string`) and result keys | `references/cvt-formulaires.md` |
| Decide whether Saisies is worth introducing | `references/form-structure.md` + `references/plugin-saisies.md` |
| Use the recommended Saisies method | `references/plugin-saisies.md` |
| Add reusable validation or normalisation rules | `references/plugin-verifier.md` |
| Review an existing form mixing template and PHP issues | `references/form-structure.md` + `references/cvt-formulaires.md` |

## Guardrails

- Start with the plain SPIP structure and the base CVT contract before adding plugin
  dependencies.
- Canonical HTML uses `formulaire_spip`, `.editer-groupe`, `.editer`,
  `reponse_formulaire`, `.erreur_message`, and `.boutons`.
- Global banners use `#ENV*{message_ok}` / `#ENV*{message_erreur}`.
- Field errors use `#ENV*{erreurs/champ}` (single `*`, never `**`; equivalent to
  `#ENV*{erreurs}|table_valeur{champ}`) and the conditional class
  `[ (#ENV*{erreurs/champ}|oui)erreur]`.
- `charger()` may return an `array`, `false`, or a `string`; `verifier()` returns an
  errors array; `traiter()` returns a result array.
- Use Saisies when the form is field-driven enough to justify the dependency. Preferred
  method: declare fields in `formulaires_<nom>_saisies()` and leave the HTML file empty.
- Treat `#GENERER_SAISIES` as the rare custom-markup exception, not the default.
- Use Verifier for reusable format/range/normalisation rules, not for tiny obvious checks
  that plain CVT handles clearly.

## Minimal template pattern

```html
<div class="formulaire_spip formulaire_editer formulaire_editer_x formulaire_editer_x-#ENV{id_x,nouveau}">
  [<div class="reponse_formulaire reponse_formulaire_ok">(#ENV*{message_ok})</div>]
  [<div class="reponse_formulaire reponse_formulaire_erreur">(#ENV*{message_erreur})</div>]
  <form method="post" action="#ENV{action}">
    #ACTION_FORMULAIRE{#ENV{action}}
    <div class="editer-groupe">
      <div class="editer editer_titre obligatoire[ (#ENV*{erreurs/titre}|oui)erreur]">
        <label for="titre"><:info_titre:></label>
        [<span class="erreur_message">(#ENV*{erreurs/titre})</span>]
        <input type="text" class="text" name="titre" id="titre" value="[(#ENV**{titre})]" />
      </div>
    </div>
    <p class="boutons">
      <input type="submit" class="submit" value="<:bouton_enregistrer:>" />
    </p>
  </form>
</div>
```

## Scope boundary

For broader plugin architecture, SQL API, pipelines, or `paquet.xml` work, use
`spip-plugins`.
