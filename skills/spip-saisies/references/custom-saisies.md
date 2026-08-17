# Writing your own field type

Upstream: https://contrib.spip.net/Creer-ses-propres-saisies (2021, written for Saisies 3.50).
The file layout it describes still holds; the YAML structure and the PHP hooks below are what
the current code actually reads.

Checked against Saisies 6.3.4.

---

## The four files

```
saisies/mon_type.html        ← required: the field itself
saisies/mon_type.yaml        ← makes the type configurable in the form builder
saisies/mon_type.php         ← optional hooks (see below)
saisies-vues/mon_type.html   ← optional read-only view
```

They are found through the SPIP path, so any plugin — or the site's `squelettes/` folder — can
ship a type, and the first match wins: `squelettes/saisies/input.html` overloads the plugin's
own. Basenames must match across the four files.

Only the `.html` is mandatory. Without a `.yaml` the type still works when declared in PHP or
called with `#SAISIE{mon_type, …}`, but `saisies_lister_disponibles()` skips it, so it never
appears in the form builder.

---

## What the wrapper already does for you

Every non-autonomous type is rendered inside `saisies/_base.html`, which produces the
container, the label, the required/optional marker, the error message, the explanations and the
`aria-describedby` wiring. Your template only renders the input.

`_base.html` includes your file with these already computed:

| `#ENV`                 | Content                                                                            |
|------------------------|------------------------------------------------------------------------------------|
| `nom`                  | The **HTML name**, bracket notation for nested fields — use it verbatim in `name=` |
| `id`                   | The HTML id, either the `id` option or `champ_<nom>`                               |
| `valeur` / `defaut`    | Current value, and the fallback to use when it is empty                            |
| `disable` / `readonly` | Already normalised to `disabled` / `readonly` or an empty string                   |
| `describedby`          | Space-separated ids of the explanation and error blocks                            |

Plus every option of the field. Do not re-render the label or the error: you would get two.

### Minimal template

```spip
#CACHE{24*3600*31,statique}
<input
	type="text"
	name="#ENV{nom}"
	id="#ENV{id}"
	class="text[ (#ENV{class})]"
	[value="(#ENV{valeur,#ENV{defaut}}|attribut_html)"]
	[ disabled="(#ENV{disable})"]
	[ readonly="(#ENV{readonly})"]
	[ aria-describedby="(#ENV{describedby})"]
	[ (#ENV*{attributs})]
/>
```

### Or delegate to an existing type

Several shipped types do exactly this — `email`, `url`, `telephone` and `couleur` are `input`
with a fixed HTML type:

```spip
#CACHE{24*3600*31,statique}
<INCLURE{fond=saisies/input, env, type=tel}>
```

`env` forwards the whole context, so your type inherits every option of the host type.

---

## The `.yaml` — describing the type for the builder

Read `saisies/email.yaml` in the plugin sources as the model. Structure:

```yaml
titre: '<:monplugin:saisie_mon_type_titre:>'
description: '<:monplugin:saisie_mon_type_explication:>'
icone: 'images/mon_type-xx.svg'
categorie:
  type: 'libre'      # libre | choix | structure | objet | defaut
  rang: 10           # order inside the category
options:
  -
    saisie: 'fieldset'
    options: { nom: 'description', label: '<:saisies:option_groupe_description:>' }
    saisies:
      - { saisie: 'input', options: { nom: 'label', obligatoire: 'on', label: '<:saisies:option_label_label:>' } }
      - 'inclure:saisies/_base/explication.yaml'
  -
    saisie: 'fieldset'
    options: { nom: 'conditions', label: '<:saisies:option_groupe_conditions:>' }
    saisies:
      - 'inclure:saisies/_base/afficher_si.yaml'
  -
    saisie: 'fieldset'
    options: { nom: 'validation', label: '<:saisies:option_groupe_validation:>' }
    saisies:
      - 'inclure:saisies/_base/obligatoire.yaml'
options_dev:
  - 'inclure:saisies/_base/options_dev.yaml'
defaut:
  options:
    label: '<:monplugin:saisie_mon_type_titre:>'
    sql: "text DEFAULT '' NOT NULL"   # column definition when used by Champs Extras
  verifier:
    type: 'email'
```

Points that matter:

- The `options` are themselves a Saisies declaration — the builder renders them with the same
  engine. Anything you can declare in a form, you can declare as an option.
- The conventional fieldsets are `description`, `utilisation`, `affichage`, `conditions`,
  `validation`. Keeping those names keeps your type consistent with the shipped ones.
- **`inclure:saisies/_base/<fragment>.yaml` instead of copy-paste.** Available fragments:
  `afficher_si.yaml`, `obligatoire.yaml`, `explication.yaml`, `class.yaml`,
  `options_dev.yaml`, `choix_alternatif.yaml`. They carry options the core code reads, so a
  hand-written copy silently drifts.
- `defaut.options.sql` is what makes the type usable as a Champs Extras column
  (`saisies_lister_disponibles_sql()` keeps only the types that declare it).
- `obsolete: true` keeps a type working but pushes it out of the default builder listing.

### Inheriting from another type

`saisies_recuperer_heritage()` lets a type start from another one's option set instead of
copying it:

```yaml
titre: 'Ville'
heritage:
  parent: 'input'
  enlever_options:
    - 'type'
  modifier_options:
    - { chemin: 'label', options: { explication: 'Nom de la commune' } }
  ajouter_options:
    - { inserer_apres: 'label', saisie: 'input', options: { nom: 'departement', label: 'Département' } }
```

`enlever_options`, `modifier_options` (with `mode: fusionner` to merge rather than replace)
and `ajouter_options` (`chemin`, `inserer_avant`, `inserer_apres`) all address options by name,
id or path. The parent is then merged under the child with `array_replace_recursive()`.

---

## The `.php` — six hooks, all optional

`saisies/mon_type.php` is loaded on demand with `include_spip("saisies/$type")`. Name the
functions `<type>_<hook>`:

| Function                                                        | Returns                                          | Called from                              | Use it when                                                                                              |
|-----------------------------------------------------------------|--------------------------------------------------|------------------------------------------|----------------------------------------------------------------------------------------------------------|
| `mon_type_valeurs_acceptables($valeur, $description, $saisies)` | bool                                             | `verifier/valeurs_acceptables.php`       | The form sets `verifier_valeurs_acceptables` and you must say whether a posted value was really on offer |
| `mon_type_est_champ($saisie)`                                   | bool (default true)                              | `saisies_saisie_est_champ()`             | The type renders no HTML field at all (a separator, a title)                                             |
| `mon_type_est_labelisable($saisie)`                             | bool (default true)                              | `saisies_saisie_est_labelisable()`       | The type cannot carry a label                                                                            |
| `mon_type_est_avec_sous_saisies($saisie)`                       | bool (default false)                             | `saisies_saisie_est_avec_sous_saisies()` | The type contains nested fields, like `fieldset`                                                         |
| `mon_type_get_label($saisie)`                                   | string                                           | `saisies_saisie_get_label()`             | The label lives somewhere other than `options.label`                                                     |
| `mon_type_get_markup($saisie)`                                  | `['conteneur_tag' => …, 'conteneur_label' => …]` | `saisies_saisie_get_markup()`            | The wrapper needs `fieldset`/`legend` instead of the default `div`/`label`                               |

Reusing another type's implementation is normal: `saisies/email.php` simply forwards to
`textarea_valeurs_acceptables()`.

---

## Autonomous types

A type listed by `saisies_autonomes()` is included **without** the `_base.html` wrapper: no
container, no label, no error block, nothing. It receives the raw context and must render
everything itself. The shipped ones are `fieldset`, `conteneur_inline`, `hidden`,
`destinataires`, `explication` and `champ`.

Declare yours through the pipeline:

```php
function monplugin_saisies_autonomes($saisies_autonomes) {
	$saisies_autonomes[] = 'mon_type';
	return $saisies_autonomes;
}
```

Only do this when the standard wrapper genuinely gets in the way — a field that draws its own
layout, or one that is not a field at all. Everything the wrapper provides, including
accessibility wiring, then becomes your responsibility.

---

## Checklist

1. `saisies/mon_type.html` exists; the `.yaml`, `.php` and view share the exact basename.
2. The template renders the input only — no duplicated label or error block.
3. `name="#ENV{nom}"` and `id="#ENV{id}"` verbatim; values escaped with `|attribut_html`.
4. `aria-describedby="#ENV{describedby}"` is forwarded.
5. Shared option fragments come from `inclure:saisies/_base/…`, not from a copy.
6. `defaut.options.sql` declared if the type is meant to back a Champs Extras column.
7. A view is written whenever the stored value is not human-readable.
8. The builder shows the type: check `ecrire/?exec=saisies_doc` and the yaml plugin is active.
