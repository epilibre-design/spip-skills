# Standard SPIP form structure

This document summarizes the expected HTML structure for SPIP forms.

## 1) Base skeleton

```html
<div class="formulaire_spip formulaire_editer formulaire_editer_nom formulaire_editer_nom-#ENV{id,nouveau}">
  [<div class="reponse_formulaire reponse_formulaire_ok">(#ENV*{message_ok})</div>]
  [<div class="reponse_formulaire reponse_formulaire_erreur">(#ENV*{message_erreur})</div>]

  <a id="nomformulaire" name="nomformulaire"></a>
  <form method="post" action="#ENV{action}">
    #ACTION_FORMULAIRE{#ENV{action}}

    <div class="editer-groupe">
      <div class="editer editer_nomchamp obligatoire[ (#ENV**{erreurs}|table_valeur{nomchamp}|oui)erreur]">
        <label for="nomchamp">Libelle</label>
        [<p class="explication">Texte d explication</p>]
        [<span class="erreur_message">(#ENV**{erreurs}|table_valeur{nomchamp})</span>]
        <input type="text" class="text" name="nomchamp" id="nomchamp" value="[(#ENV**{nomchamp})]" />
      </div>
    </div>

    <p class="boutons">
      <input type="submit" class="submit" value="<:bouton_enregistrer:>" />
    </p>
  </form>
</div>
```

Notes:
- `fieldset` is allowed but optional.
- Since SPIP 3.1, the convention is to use `div` (not `ul/li`) for `.editer-groupe` and `.editer`.

## 2) Special classes

- `explication`: global or local helper text
- `attention`: caution/warning message
- `obligatoire`: required field marker (on parent `.editer` block)
- `erreur`: error state marker (on parent `.editer` block)
- `erreur_message`: field error message

## 3) Global messages (CVT)

Standard CVT feedback should be rendered:

```html
[<div class="reponse_formulaire reponse_formulaire_ok">(#ENV*{message_ok})</div>]
[<div class="reponse_formulaire reponse_formulaire_erreur">(#ENV*{message_erreur})</div>]
```

## 4) Field-level errors

Read a field error:

```spip
[(#ENV**{erreurs}|table_valeur{nom_du_champ})]
```

Conditionally apply the `erreur` class:

```spip
<div class="editer editer_titre[ (#ENV**{erreurs}|table_valeur{titre}|oui)erreur]">
```

Conditionally render the error text:

```spip
[<span class="erreur_message">(#ENV**{erreurs}|table_valeur{titre})</span>]
```

## 5) CSS/HTML specifics

- Every non-`hidden` `<input>` should have a class equal to its `type`.
- Submit controls should be in `<p class="boutons">`.
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
- `message_ok` / `message_erreur` are rendered.
- All fields are inside `.editer-groupe`.
- Each field uses `.editer editer_fieldname`.
- Field errors use `#ENV**{erreurs}|table_valeur{...}`.
- Error texts are in `.erreur_message`.
- `input` elements have a class matching their type.
- Submit control is inside `.boutons`.
- AJAX behavior (when needed) wraps the balise with `<div class="ajax">`.
