# Standard SPIP form structure

This document summarizes the expected HTML structure for SPIP forms,
as practiced by the core forms in `prive/formulaires/` (the reference
implementation — e.g. `editer_article.html`).

## 1) Base skeleton

```html
<div class="formulaire_spip formulaire_editer formulaire_editer_nom formulaire_editer_nom-#ENV{id_nom,nouveau}">
  [<div class="reponse_formulaire reponse_formulaire_ok" role="status">(#ENV*{message_ok})</div>]
  [<div class="reponse_formulaire reponse_formulaire_erreur" role="alert">(#ENV*{message_erreur})</div>]
  [(#ENV{editable}|oui)
  <form method='post' action='#ENV{action}'><div>
    #ACTION_FORMULAIRE
    <input type='hidden' name='id_nom' value='#ENV{id_nom}'>
    <div class="editer-groupe">
      <div class="editer editer_nomchamp obligatoire[ (#ENV*{erreurs/nomchamp}|oui)erreur]">
        <label for="nomchamp">Libellé</label>
        [<p class="explication">Texte d'explication</p>]
        [<span class='erreur_message'>(#ENV*{erreurs/nomchamp})</span>]
        <input type='text' class='text' name='nomchamp' id='nomchamp' value="[(#ENV{nomchamp})]">
      </div>
    </div>
    <!--extra-->
    <p class='boutons'><input type='submit' class='btn submit' value='<:bouton_enregistrer:>'></p>
  </div></form>
  ]
</div>
```

Notes:
- The whole `<form>` sits inside the `[(#ENV{editable}|oui) … ]` conditional: when
  `charger()`/`traiter()` returns a falsy `editable`, only the wrapper and the
  messages render (read-only state).
- `#ACTION_FORMULAIRE` is written **without argument**: its two optional
  arguments default to `#ENV{action}` and `#ENV{form}`
  (`balise_ACTION_FORMULAIRE()`, `ecrire/public/balises.php`). It also outputs
  the `_hidden` HTML provided by `charger()`.
- Field values use plain `#ENV{nomchamp}` — never `#ENV**`, which would bypass
  the protections.
- `<!--extra-->` is the marker where plugins inject extra fields; keep it
  right before the buttons.
- `fieldset` is allowed but optional (wrapped in `.editer … fieldset`).
- Since SPIP 3.1, the convention is to use `div` (not `ul/li`) for `.editer-groupe` and `.editer`.
- The old `<a id="nomformulaire" name="nomformulaire">` anchor from legacy docs
  is not used by any `prive/formulaires` template — don't add it.

## 2) Special classes

- `explication`: global or local helper text
- `attention`: caution/warning message
- `obligatoire`: required field marker (on parent `.editer` block)
- `erreur`: error state marker (on parent `.editer` block)
- `erreur_message`: field error message

## 3) Global messages (CVT)

Standard CVT feedback should be rendered with their accessibility roles:

```html
[<div class="reponse_formulaire reponse_formulaire_ok" role="status">(#ENV*{message_ok})</div>]
[<div class="reponse_formulaire reponse_formulaire_erreur" role="alert">(#ENV*{message_erreur})</div>]
```

## 4) Field-level errors

Read a field error — single `*`, never `**`: errors may contain HTML (the core
wraps them in `<span role='alert'>`), so entity encoding must be disabled while
`interdire_scripts` stays active:

```spip
[(#ENV*{erreurs/nom_du_champ})]
```

This path notation is what `prive/formulaires` uses everywhere;
`[(#ENV*{erreurs}|table_valeur{nom_du_champ})]` is an equivalent older spelling.

Conditionally apply the `erreur` class:

```spip
<div class="editer editer_titre[ (#ENV*{erreurs/titre}|oui)erreur]">
```

`|oui` returns a space (` `) if the value is non-empty/non-null, empty string otherwise — equivalent to `|?{' ',''}`. This is intentional: it produces a non-empty value so that the surrounding `[ ...]` conditional block renders, adding the ` erreur` class.

Conditionally render the error text:

```spip
[<span class='erreur_message'>(#ENV*{erreurs/titre})</span>]
```

## 5) CSS/HTML specifics

- Text-like `<input>` elements carry `class='text'` (historic rule: class = type).
- Submit buttons use `class='btn submit'` inside `<p class='boutons'>`.
- For radio/checkbox fields, wrap each option in `.choix`.

Radio example:

```html
<div class="editer editer_syndication">
  <div class="choix">
    <input type="radio" class="radio" name="syndication" value="non" id="syndication_non" />
    <label for="syndication_non"><:bouton_radio_non_syndication:></label>
  </div>
  <div class="choix">
    <input type="radio" class="radio" name="syndication" value="oui" id="syndication_oui" />
    <label for="syndication_oui"><:bouton_radio_syndication:></label>
  </div>
</div>
```

## 6) AJAX wrapper for form balise

Wrap the form balise with a container using class `ajax` to let SPIP reload only the form block instead of the full page:

```html
<div class="ajax">
  #FORMULAIRE_DEMO
</div>
```

Use the same pattern with any form balise (for example `#FORMULAIRE_FORUM`, `#FORMULAIRE_RECHERCHE`, or custom CVT forms).

## 7) Quick review checklist

- The main wrapper has `formulaire_spip`.
- `message_ok` / `message_erreur` are rendered, with `role="status"` / `role="alert"`.
- The `<form>` is wrapped in the `[(#ENV{editable}|oui) … ]` conditional.
- `#ACTION_FORMULAIRE` is called without argument.
- All fields are inside `.editer-groupe`.
- Each field uses `.editer editer_fieldname`.
- Field errors use `#ENV*{erreurs/...}` (single `*`, never `#ENV**`).
- Error texts are in `.erreur_message`.
- Field values use plain `#ENV{...}`, never `#ENV**{...}`.
- Text inputs have `class='text'`; the submit has `class='btn submit'` inside `.boutons`.
- `<!--extra-->` is present before the buttons.
- AJAX behavior (when needed) wraps the balise with `<div class="ajax">`.
