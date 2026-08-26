# SPIP Saisies Skill — Design Specification

## Goal

Create a Claude Code skill (`spip-saisies`) covering the **Saisies** plugin as a subject of its
own: the catalogue of field types, writing a custom type, the pipelines it exposes, and its PHP
API.

**Target: Saisies 6.x**, written and verified against 6.3.4.

---

## Why a separate skill

`spip-formulaires` already carries `references/plugin-saisies.md`, which documents Saisies **as
a way to build a CVT form**: the empty-HTML method, global form options, `afficher_si`,
Verifier integration. That framing is correct for a form skill, and it stays.

But Saisies is used well outside a CVT form:

- as a **form builder** stored in configuration (`saisies_construire_formulaire_config`),
- as the description language of **plugin configuration pages**,
- as the column definitions behind **Champs Extras** (`defaut.options.sql`),
- as a rendering layer for read-only views (`#VOIR_SAISIES`, `saisies-vues/`).

None of those triggers a skill described as being about CVT forms, and none of them is served
by what that reference contains. Two areas were also plainly under-covered: the field-type
catalogue (absent) and the pipelines (one of thirteen).

---

## Audience

PHP developers and template authors who already build SPIP plugins. They know the CVT contract;
what they lack is the Saisies-specific surface — option names, file conventions for a new type,
which hook to use, which helper avoids hand-walking a nested array.

Not for: form markup and the `charger`/`verifier`/`traiter` contract → `spip-formulaires`;
plugin architecture and `paquet.xml` → `spip-plugins`.

---

## Trigger conditions

Load when:

- a plugin in context has a `saisies/` or `saisies-vues/` directory,
- a `formulaires_*_saisies()` function is being written or read,
- a template uses `#SAISIE`, `#GENERER_SAISIES`, `#VOIR_SAISIES` or `#CONFIGURER_SAISIE`,
- a `saisies_*` pipeline is declared or implemented,
- the question is "which field type / which option",
- the user invokes `/spip-saisies`.

---

## Sourcing policy

Three contrib.spip.net pages are the usual entry points and are cited at the top of the
matching reference file:

| Page                              | Published              | Covers                 |
|-----------------------------------|------------------------|------------------------|
| `Reference-des-saisies`           | 2010                   | field types            |
| `Creer-ses-propres-saisies`       | 2021, for Saisies 3.50 | custom types           |
| `Les-pipelines-du-plugin-saisies` | January 2025           | 12 of the 13 pipelines |

Two of the three predate the current major by several versions, so **the installed plugin is
the source of truth** and every statement in the skill was checked against it. The pages are
referenced, not copied.

The skill deliberately does **not** transcribe the options of all 44 shipped types. The plugin
generates that reference itself at `ecrire/?exec=saisies_doc`
(`prive/squelettes/contenu/saisies_doc.html`), always in sync with the installed version, and
the same data sits in `saisies/<type>.yaml`. Teaching the lookup beats freezing a copy that
rots at the next release.

---

## File structure

```
skills/spip-saisies/
  SKILL.md                        # scope, routing, guardrails, boundary (< 500 words)
  references/
    saisie-types.md               # catalogue, shared options, global form options, views
    custom-saisies.md             # the four files, _base.html contract, YAML, inheritance, 6 PHP hooks
    pipelines.md                  # the 13 pipelines, flux shapes
    api-php.md                    # inc/saisies* — listing, manipulating, verifying, rendering
evals/spip-saisies/
  evals.json                      # 5 cases: 2 nominal, 2 traps, 1 edge
  README.md
```

`api-php.md` goes beyond the three contrib pages on purpose: no existing skill documents the
PHP API, and manipulating a field array by hand — instead of `saisies_inserer()` /
`saisies_modifier()` / `saisies_chercher()` — is the most common way to get nested fieldsets
wrong.

---

## Changes to `spip-formulaires`

- `SKILL.md`: two routing rows and a scope-boundary sentence pointing here.
- `references/plugin-saisies.md`: the *Custom saisie type* and *Pipeline* sections are reduced
  to a pointer, so the two skills cannot drift apart; a note flags that the global-options
  table is a subset of the twenty-two the plugin declares. The CVT-facing material is
  untouched.

---

## Success criteria

- Naming a field type or an option is always backed by `?exec=saisies_doc` or a `.yaml`, never
  by recall.
- A custom type is scaffolded with the right basenames and does not re-render the label, the
  error block or the container.
- The right hook is picked between `formulaire_saisies`, `saisies_construire_formulaire_config`
  and `formulaire_verifier_post_saisies`.
- A nested field's posted value is read with `saisies_request()`.
