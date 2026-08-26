# Saisies — reference

This plugin adds a `formulaires_<nom>_saisies()` function to the CVT contract that lets
you declare all fields in PHP. SPIP then generates the complete HTML automatically.

This page covers Saisies **as used inside a CVT form**. For the plugin itself — the catalogue
of field types and their options, writing a custom type, the `saisies_*` pipelines, the
`inc/saisies*` PHP API — use the **`spip-saisies`** skill.

Require it in `paquet.xml`:
```xml
<necessite nom="saisies" compatibilite="[3.3.0;]" />
```

---

## The fundamental rule: empty HTML file

When using the PHP API, **leave the HTML file of the CVT form completely empty.**

```
formulaires/
├── mon_form.php   ← all logic + saisies() function
└── mon_form.html  ← intentionally empty
```

The empty file must exist (SPIP needs it for form routing), but putting anything inside it
disables the automatic scaffolding that gives you multi-step forms, AJAX, error display,
and the submit button — for free.

Only fill the HTML file when you need to take full control of the markup (Method 1b, rare).

---

## Method 1 — PHP API (recommended, covers 99% of cases)

### Canonical data model for one field

```php
[
    'saisie' => 'input',          // field type
    'options' => [
        'nom'          => 'email', // mandatory for real inputs
        'label'        => 'Email',
        'obligatoire'  => 'on',
        'placeholder'  => 'name@example.net',
    ],
    'verifier' => [               // optional — requires plugin Vérifier
        'type'    => 'email',
        'options' => [],
    ],
]
```

### Full CVT pattern (copy-paste ready)

PHP file — `formulaires/mon_form.php`:

```php
<?php
if (!defined('_ECRIRE_INC_VERSION')) { return; }

function formulaires_mon_form_saisies_dist(): array {
    return [
        // Global form options (optional)
        'options' => [
            'texte_submit'       => 'Envoyer',
            'obligatoire_defaut' => false,
            'ajax'               => true,
        ],
        // Fields
        [
            'saisie'  => 'input',
            'options' => [
                'nom'         => 'email',
                'label'       => 'Email',
                'obligatoire' => 'on',
            ],
            'verifier' => ['type' => 'email'],
        ],
        [
            'saisie'  => 'textarea',
            'options' => [
                'nom'   => 'message',
                'label' => 'Message',
            ],
            'verifier' => [
                'type'    => 'taille',
                'options' => ['min' => 10],
            ],
        ],
    ];
}

// charger() is optional — add it when you need default values or extra context variables.
// verifier() is optional for purely declarative validation, but must be implemented
// explicitly whenever custom checks are required beyond what Saisies handles.

function formulaires_mon_form_traiter_dist(): array {
    // read posted values with _request('nom_du_champ')
    return ['message_ok' => 'Formulaire envoyé.'];
}
```

HTML file — `formulaires/mon_form.html`: **leave completely empty**

```html

```

That's all. Saisies generates the full form wrapper, field layout, error messages, and submit
button from the `saisies()` declaration.

---

## Global form options

Place under the `'options'` key at the root of the `saisies()` return array. Twenty-two exist,
covering submit label, AJAX, multi-step mode, and more.

Full list of names — in the **`spip-saisies`** skill, `references/saisie-types.md`. Values and
defaults for a given option are not transcribed there either; check
`saisies_options_globales_lister_disponibles()` in `inc/saisies_options_globales.php` in the
installed plugin.

---

## Nested fields — fieldset

```php
[
    'saisie'  => 'fieldset',
    'options' => [
        'nom'   => 'mon_groupe',
        'label' => 'Mon groupe',
    ],
    'saisies' => [
        [
            'saisie'  => 'input',
            'options' => ['nom' => 'prenom', 'label' => 'Prénom'],
        ],
        // …
    ],
]
```

Nested data uses slash notation for `nom`:
```php
'nom' => 'adresse/ville'
// retrieved in PHP: $adresse = _request('adresse'); $ville = $adresse['ville'];
```

---

## Conditional display with `afficher_si`

```php
'options' => [
    'nom'         => 'siret',
    'label'       => 'SIRET',
    'afficher_si' => '@type_contact@ == "pro"',
]
```

Supported operators: `==`, `!=`, `>`, `>=`, `<`, `<=`, `IN`, `!IN`, `MATCH`, `!MATCH`

Hidden fields are excluded from verification and can be reset to empty string after validation.

---

## Verifier integration

One verifier:
```php
'verifier' => ['type' => 'email']
```

Multiple verifiers (plugin ≥ 3.23.0, use `verifiers` plural key):
```php
'verifiers' => [
    ['type' => 'email'],
    ['type' => 'taille', 'options' => ['max' => 180]],
]
```

Behavior: `obligatoire` check runs first, then each verifier in order. Normalized values are
written back into request context. Errors are returned as a standard CVT `erreurs` array keyed
by field name.

---

## Method 1b — `#GENERER_SAISIES` (exception, not the default)

Use only when the automatic HTML scaffolding is not enough and you need to control the markup
yourself. Start from the stripped-down version of Saisies' own default template
(`formulaires/inc-saisies-cvt.html`):

```spip
[(#ENV**{_saisies/options/ajax}|oui)
<div class="ajax">
]
<div class="
    formulaire_spip
    formulaire_#ENV{form}
    [(#ENV{_etape}|oui)formulaire_multietapes]
    [(#ENV**{_saisies/options/conteneur_class})]"
    [(#ENV{_saisies}|saisies_dont_avec_option{afficher_si}|oui) data-avec-afficher_si="true"]
>
    [<div class="reponse_formulaire reponse_formulaire_ok" role="status">(#ENV**{message_ok})</div>]
    [<div class="reponse_formulaire reponse_formulaire_erreur" role="alert">(#ENV**{message_erreur})</div>]

    [(#ENV{editable}|oui)
    <form method="post"
        action="#ENV{action}"
        enctype="multipart/form-data"
    >
        <div>
            #ACTION_FORMULAIRE
            <div class="editer-groupe">
                #GENERER_SAISIES{#ENV{_saisies}}
            </div>
            <!--extra-->
            <INCLURE{fond=formulaires/inc-saisies-cvt-boutons, env} />
        </div>
    </form>
    ]
</div>
[(#ENV**{_saisies/options/ajax}|oui)
</div>
]
```

Notes:
- `#ENV{_saisies}` with underscore — prevents HTML entity conversion of the array
- The submit button is generated by `inc-saisies-cvt-boutons` via the `texte_submit` option — do not write it manually
- `<!--extra-->` is a marker SPIP uses to inject extra saisies (extra, bigup…) — keep it
- The AJAX wrapper is conditional on the form's `ajax` option
- For multi-step forms, see `formulaires/inc-saisies-cvt-etapes-*.html` in the plugin sources

You must also call `saisies_verifier()` explicitly in `verifier()`:
```php
function formulaires_mon_form_verifier_dist(): array {
    include_spip('inc/saisies');
    return saisies_verifier(formulaires_mon_form_saisies_dist());
}
```

---

## Custom saisie type

Minimum to make `#SAISIE{mon_type, …}` work: one `saisies/mon_type.html` file rendering the
input alone. Add `mon_type.yaml` (same basename) to make the type configurable in the Saisies
builder, `mon_type.php` for the optional per-type hooks, and `saisies-vues/mon_type.html` for
a read-only view.

Full contract — what `saisies/_base.html` passes to your template, the YAML structure, type
inheritance and the six PHP hooks — in the **`spip-saisies`** skill,
`references/custom-saisies.md`.

---

## Display submitted values (read-only)

```spip
[(#EDITABLE|non)
    #VOIR_SAISIES{#ENV{mes_saisies}, #ENV}
]
```

---

## Pipeline — modify fields dynamically

```php
function monplugin_formulaire_saisies($flux) {
    if ($flux['args']['form'] === 'mon_form') {
        $flux['data'][] = [
            'saisie'  => 'input',
            'options' => ['nom' => 'extra', 'label' => 'Extra'],
        ];
    }
    return $flux;
}
```

Saisies offers twelve more hooks — verification, builder, field introspection. Catalogue in
the **`spip-saisies`** skill, `references/pipelines.md`.

---

## Checklist before shipping a form

1. `saisies()` function declared and returns a valid array
2. HTML file exists and is **completely empty** (Method 1) or fully written (Method 1b)
3. Every real field has `options.nom`
4. `obligatoire` is set on required fields
5. `verifier` entries use types available in the Vérifier plugin
6. `afficher_si` conditions only target existing field names
7. For custom types: HTML and YAML basenames match
