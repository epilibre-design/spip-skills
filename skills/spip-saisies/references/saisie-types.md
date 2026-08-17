# Field types — reference

Upstream: https://contrib.spip.net/Reference-des-saisies (first published 2010). Useful as a
tour, but it predates the current major by several versions. **The plugin ships its own
reference, generated from the type definitions it installs — prefer it.**

Checked against Saisies 6.3.4.

---

## Where the truth is, in order

1. **`ecrire/?exec=saisies_doc`** — private area, *Development* → *Documentation des saisies*.
   Every configurable type, every option, every explanation, built at runtime from the
   installed `.yaml` files (`prive/squelettes/contenu/saisies_doc.html`, via
   `saisies_generer_aide()`). Always in sync with the installed version. Needs the **yaml**
   plugin active.
2. **`saisies/<type>.yaml`** in the plugin sources — the same data as a file, with the default
   values (`defaut.options`, `defaut.verifier`) and the SQL column definition used by Champs
   Extras (`defaut.options.sql`).
3. **`saisies/<type>.html`** — what the type really renders. The only source for the types that
   ship without a `.yaml`.

Never guess an option name. `grep -n "ENV{" saisies/<type>.html` settles it in one command.

---

## Two families of types

A type is one `saisies/<type>.html` file. Adding a `<type>.yaml` sibling is what makes it
**configurable in the form builder** — `saisies_lister_disponibles()` only keeps types that
have both files (`inc/saisies_lister_disponibles.php`). Types without a `.yaml` still work when
declared in PHP or called with `#SAISIE{…}`; they are simply invisible to the builder.

Saisies 6.3.4 ships 44 types, split evenly: 22 with a `.yaml`, 22 without.

### Configurable types (with `.yaml`), by category

Categories come from `saisies_lister_categories()`; `rang` orders types inside a category.

| Category                      | Types                                                                                                                                        |
|-------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| `libre` — free input          | `input` (1), `email` (10), `date` (20), `textarea` (1)                                                                                       |
| `choix` — choices             | `case` (1), `checkbox` (1), `oui_non` (1), `radio` (1), `selection` (1), `selection_multiple` (1), `destinataires` (30), `choix_grille` (50) |
| `structure` — layout          | `fieldset`, `explication` (1), `conteneur_inline` (50)                                                                                       |
| `objet` — SPIP objects        | `auteurs` (1), `mot` (1), `selecteur_article` (1), `selecteur_rubrique` (1), `selecteur_rubrique_article` (1), `selecteur_document` (1)      |
| `defaut` — always listed last | `hidden` (1)                                                                                                                                 |

All of them carry a `defaut.options.sql` definition — so they can back a Champs Extras column —
except the three that store nothing: `fieldset`, `explication` and `conteneur_inline`.

Two are flagged `obsolete: true` in their `.yaml` and are pushed out of the default builder
listing: **`oui_non`** (its PHP delegates to `case`) and **`selection_multiple`** (covered by
`selection` with the `multiple` option). They still work — do not reach for them in new code.

**Saisies 6.0 changed `date`**: it now relies on the HTML5 `type="date"` input, so the browser
posts `YYYY-MM-DD` where it used to post `DD-MM-YYYY`. Add the matching verification rather
than reformatting by hand (`UPGRADE_6.0.md` in the plugin sources):

```php
'verifier' => [['type' => 'date', 'options' => ['format' => 'amj', 'normaliser' => 'date_ou_datetime']]],
```

### Types shipped without a `.yaml`

`articles_originaux`, `champ`, `choisir_objet`, `choisir_objets`, `choisir_objets_edit`,
`couleur`, `date_jour_mois_annee`, `groupe_mots`, `police`,
`position_construire_formulaire`, `recherche`, `secteur`, `selecteur`, `selecteur_langue`,
`selecteur_site`, `selection_par_groupe`, `statuts_auteurs`, `statuts_objet`, `telephone`,
`true_false`, `type_mime`, `url`.

### Types that delegate to another type

Several types — from both families — are thin wrappers around another one, usually adding a
fixed HTML `type` attribute. They accept the same options as their host, so look the options up
there:

| Type                                                                                | Delegates to       |
|-------------------------------------------------------------------------------------|--------------------|
| `couleur`, `date`, `date_jour_mois_annee`, `email`, `recherche`, `telephone`, `url` | `saisies/input`    |
| `type_mime`                                                                         | `saisies/checkbox` |

Writing yours the same way is one `<INCLURE>` line — see `custom-saisies.md`.

Rendering fragments are shared the same way: `radio`, `checkbox` and `selection` all include
`saisies/_base/choix_alternatif` to offer the "other, please specify" entry. So
`saisies/_base/` holds two different things — YAML option fragments, and that HTML fragment.

---

## Options every field understands

Rendered by the shared wrapper `saisies/_base.html`, so they work on any non-autonomous type:

| Option                           | Effect                                                                                                                 |
|----------------------------------|------------------------------------------------------------------------------------------------------------------------|
| `nom`                            | Field name. **Mandatory** — `_base.html` renders nothing without it. Slash notation (`adresse/ville`) for nested names |
| `label`                          | Label text                                                                                                             |
| `valeur`                         | Current value (passed automatically by `#SAISIE`)                                                                      |
| `defaut`                         | Value used when `valeur` is empty                                                                                      |
| `obligatoire`                    | Any value other than `non` marks the field required                                                                    |
| `info_obligatoire`               | Text appended to the label of a required field                                                                         |
| `facultatif` / `info_facultatif` | The mirror pair, used when the form sets `obligatoire_defaut`                                                          |
| `erreur_obligatoire`             | Custom message when the required check fails                                                                           |
| `explication`                    | Help text below the label, wired to `aria-describedby`                                                                 |
| `explication_apres`              | Help text after the field                                                                                              |
| `attention`                      | Emphasised warning                                                                                                     |
| `disable`                        | Renders the field disabled; nothing is posted                                                                          |
| `disable_avec_post`              | Same, but posts the value in a hidden input                                                                            |
| `readonly`                       | Field is not editable but is posted                                                                                    |
| `conteneur_class`                | CSS class on the wrapper (`li_class` is the legacy alias)                                                              |
| `label_class`                    | CSS class on the label                                                                                                 |
| `class`                          | CSS class on the input itself (handled by each type, not by `_base.html`)                                              |
| `afficher_si`                    | Conditional display, see below                                                                                         |
| `aide`                           | SPIP help id, rendered with `#AIDER`                                                                                   |

Developer options, grouped under `options_dev` in the builder
(`saisies/_base/options_dev.yaml`): `inserer_debut`, `inserer_fin`, `id`, `attributs`,
`attributs_data`, `label_class`, `hors_vue`, `env`, `ajax`.

- `attributs` takes a raw attribute string; `attributs_data` takes an array merged into it as
  `data-*` attributes (`saisies_afficher_normaliser_options_attributs()`).
- `hors_vue` hides the field from `#VOIR_SAISIES` output.
- `id` overrides the generated HTML id — which otherwise is `champ_<nom>`.

### Conditional display

`afficher_si` compares fields with `@nom@` placeholders:

```php
'afficher_si' => '@type_contact@ == "pro"',
'afficher_si' => '@_options_globales[obligatoire_defaut]@',   // reads a global form option
```

Operators: `==`, `!=`, `>`, `>=`, `<`, `<=`, `IN`, `!IN`, `MATCH`, `!MATCH`, combined with
`&&`, `||` and `!`. Two companion options refine the behaviour
(`saisies/_base/afficher_si.yaml`):

- `afficher_si_remplissage_uniquement` — the condition only applies while filling the form;
- `afficher_si_avec_post` — the hidden field still posts its value.

Hidden fields are skipped by verification; by default their value is reset to an empty string
(`saisies_verifier($formulaire, $saisies_masquees_empty_string = true, …)`).

---

## Global form options

Declared under the `options` key at the root of the `saisies()` array. The full list is
`saisies_options_globales_lister_disponibles()` in `inc/saisies_options_globales.php` — that
function exists precisely so `afficher_si` can reference them, and is the list to trust:

`previsualisation_mode`, `texte_submit`, `afficher_si_submit`, `squelettes_boutons`,
`etapes_activer`, `etapes_presentation`, `etapes_suivant`, `etapes_precedent`,
`etapes_precedent_suivant_titrer`, `etapes_ignorer_recapitulatif`, `ajax`, `conteneur_class`,
`conteneur_id`, `inserer_debut`, `inserer_fin`, `prefixe_id`, `verifier_valeurs_acceptables`,
`afficher_si_avec_post`, `obligatoire_defaut`, `inserer_a_la_place`,
`chercher_formulaire_cache`, `afficher_si_conteneur_extra`.

Two worth knowing:

- `obligatoire_defaut` flips the polarity — every field becomes required and you mark the
  exceptions with `facultatif`.
- `verifier_valeurs_acceptables` rejects a posted value that was never offered by the field,
  which is the cheap defence against a tampered `<select>`.

---

## Read-only views

`saisies-vues/<type>.html` renders a submitted value instead of an input, and is what
`#VOIR_SAISIES` and `#VOIR_SAISIE` use. Saisies ships 24 of them.

A view is optional: the shared wrapper `saisies-vues/_base.html` tests
`#CHEMIN{saisies-vues/<type>.html}` and falls back to a generic rendering when the file is
missing. Write one when the stored value is not what a human should read — an id, a serialised
array, a code.

Options handled by the view wrapper: `valeur_uniquement` (drop the label and the value
wrapper), `sans_reponse` (text shown when the field is empty), `vue_class`, `conteneur_class`,
and `hors_vue` on the field itself to skip it entirely.

---

## Template tags

| Tag                                               | Use                                                |
|---------------------------------------------------|----------------------------------------------------|
| `#SAISIE{type, nom, option=valeur, …}`            | Render one field in a template                     |
| `#GENERER_SAISIES{#ENV{_saisies}}`                | Render a whole declared form (custom-markup route) |
| `#VOIR_SAISIES{saisies, env}` / `#VOIR_SAISIE{…}` | Render submitted values read-only                  |
| `#CONFIGURER_SAISIE{…}`                           | Render the builder widget for one field            |
