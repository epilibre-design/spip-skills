# Saisies pipelines

Upstream: https://contrib.spip.net/Les-pipelines-du-plugin-saisies (January 2025), the freshest
of the three contrib pages. It documents twelve of the thirteen hooks below.

Checked against Saisies 6.3.4 — the list is `<pipeline nom="…">` in the plugin's `paquet.xml`.

---

## Two shapes, and the mistake to avoid

Some Saisies pipelines pass a **bare value**, others the usual **`['args' => …, 'data' => …]`
flux. The handler signature differs accordingly, and getting it wrong fails silently — the form
simply behaves as if the hook were absent.

```php
// bare value: receive it, return it
function monplugin_saisies_autonomes($saisies_autonomes) {
	$saisies_autonomes[] = 'ma_saisie';
	return $saisies_autonomes;
}

// flux: modify $flux['data'], return the whole $flux
function monplugin_formulaire_saisies($flux) {
	if ($flux['args']['form'] === 'mon_form') {
		$flux['data'][] = ['saisie' => 'input', 'options' => ['nom' => 'extra', 'label' => 'Extra']];
	}
	return $flux;
}
```

Declare each hook you implement in your `paquet.xml`:

```xml

<pipeline nom="formulaire_saisies" inclure="monplugin_pipelines.php"/>
```

---

## Building and altering forms

### `formulaire_saisies` — flux

The one to reach for when adding, removing or reordering fields of a form you do not own. Runs
right after `formulaires_<form>_saisies()` returned, before any of the automatic processing
(`obligatoire_defaut`, `prefixe_id`, unpublished-field cleanup).

`args`: `form`, `args` (the form's own arguments), `je_suis_poste`. `data`: the field array.

Use the `saisies_inserer*` / `saisies_modifier` / `saisies_supprimer` helpers rather than raw
array manipulation — see `api-php.md`.

### `saisies_autonomes` — bare value

Adds a type to the list of those rendered **without** the shared `saisies/_base.html`
wrapper. Default list: `fieldset`, `conteneur_inline`, `hidden`, `destinataires`,
`explication`, `champ`. See `custom-saisies.md` before joining it.

### `saisies_lister_disponibles` — bare value

Last word on which types the form builder offers, keyed by type name. Use it to hide a shipped
type, or to expose types that live outside a `saisies/` directory.

### `saisies_lister_categories` — bare value

Adds or renames the categories that group types in the builder. Defaults: `libre`, `choix`,
`structure`, `objet`, `defaut` — `defaut` is forced back to last position after the pipeline
runs, so you cannot reorder it.

### `saisies_construire_formulaire_config` — flux

Adds fields to the **configuration form of a single field** inside the builder: this is how a
plugin attaches its own option to an existing type.

`args`: `identifiant` (the builder instance), `action` (`enregistrer` or `configurer`),
`options`, `nom` (the field being configured), `saisie` (its full description).
`data`: the configuration form's own field array.

The option name must be nested under the field being configured, which is what makes the value
land in the right place when the builder saves:

```php
'nom' => "saisie_modifiee_{$nom_saisie}[options][mon_option]",
```

### `saisies_aide_memoire_inserer_debut` — flux

Prepends content to the `formulaire_aide_memoire` model, the cheat-sheet of `@field@`
placeholders shown next to a form builder. `args`: `type_aide_memoire`, `id_aide_memoire`.

---

## Verification

### `saisies_verifier` — flux

Last word on the error array produced by the declarative verifications, before the form's own
`verifier()` result is merged in. Rich `args`: `formulaire`, `saisies` (current step, after
`afficher_si` resolution, keyed by name), `saisies_par_etapes`, `etape`, `valeurs`, and the
variants listing what `afficher_si` hid. `data`: errors keyed by field name.

### `formulaire_verifier_post_saisies` — flux

Runs **after** the declarative verifications, so it sees values already normalised by Verifier.
This is where cross-field checks belong. Its per-form twin is the CVT function
`formulaires_<form>_verifier_post_saisies()`, which runs just before the pipeline.

`args`: `form`, `args`. `data`: the accumulated errors.

### `formulaire_verifier_etape_post_saisies` — flux

Same thing for one step of a multi-step form. The step number is in `$flux['args']['etape']`.

### `saisies_verifier_lister_disponibles` — flux

Filters the Verifier rules the builder offers for a given field type, and can force some.
`args`: `saisie` (the type name). `data`: `['disponibles' => …, 'obligatoires' => …]`.

### `saisies_afficher_si_saisies` — bare value

Receives the whole field array before `afficher_si` conditions are resolved, and returns it.
Lets you declare fields that only exist as far as the conditions are concerned — useful when a
value comes from outside the form but conditions must be able to reference it.

---

## Field introspection

### `saisie_est_tabulaire` — flux

Says whether a field posts an array rather than a scalar. Default: true for `checkbox`,
`selection_multiple`, `choix_grille`, and for `selection` with the `multiple` option.
`args`: the field description. `data`: the boolean.

### `saisie_est_fichier` — flux

Says whether a field fills `$_FILES`. Default: true for `fichiers`, and for `input` with
`type` set to `file`. Same shape as above.

Both are tested **field by field, not type by type**, precisely because options can flip the
answer — respect that when you hook them.

---

## Standard SPIP pipelines Saisies itself uses

`paquet.xml` also declares `insert_head`, `header_prive`, `affichage_final`,
`formulaire_receptionner`, `formulaire_charger`, `formulaire_verifier`,
`formulaire_verifier_etape`, `styliser` and `formulaire_fond`. These are core SPIP hooks the
plugin consumes, not extension points it offers — `formulaire_fond` is how an empty CVT
template gets replaced by `formulaires/inc-saisies-cvt.html`, and `formulaire_verifier` is
where the declarative verifications are injected.
