# PHP API

A field array is a plain nested PHP array, so it is tempting to edit it by hand. Don't: every
helper below walks into `saisies` sub-arrays (fieldsets, steps) that hand-written code forgets,
and addresses a field by **name, id or path** indifferently.

Load with `include_spip('inc/saisies')`, which pulls in the whole family. Checked against
Saisies 6.3.4; the signatures below are the ones in `inc/saisies_*.php`.

---

## Addressing a field

Most functions take `$id_ou_nom_ou_chemin`: the field's `nom`, its stable `identifiant`, or an
explicit path as an array of keys. Two notations coexist for names and both are accepted:

| Notation       | Example          | Where it appears              |
|----------------|------------------|-------------------------------|
| SPIP, slashes  | `adresse/ville`  | `options.nom`, error keys     |
| HTML, brackets | `adresse[ville]` | the rendered `name`, `$_POST` |

`saisie_nom2name()` and `saisie_name2nom()` convert between them; `saisies_cles_nom2name()`
does it on the keys of an error array.

---

## Reading posted values

```php
saisies_request($champ, $valeurs = null)          // _request() that understands both notations
saisies_set_request($champ, $valeur, $valeurs = null)
saisies_get_valeur_saisie($saisie, $valeurs = null)
saisies_request_from_FILES($champ)                // the $_FILES sub-array, CVT-Upload aware
saisies_request_property_from_FILES($champ, $property = 'name')
```

Use `saisies_request()` rather than `_request()` as soon as a field is nested: `_request()`
returns the whole `adresse` array and leaves you to dig, `saisies_request('adresse/ville')`
returns the value.

---

## Listing and searching

```php
saisies_lister_par_nom($contenu, $avec_conteneur = true)     // flat map nom => saisie
saisies_lister_par_identifiant($contenu, $avec_conteneur = true)
saisies_lister_par_type($contenu)
saisies_lister_champs($contenu, $avec_conteneur = true)      // just the names
saisies_lister_labels($contenu, $avec_conteneur = false)
saisies_lister_valeurs_defaut($contenu)
saisies_lister_avec_type($saisies, $type, $tri = 'nom', $avec_conteneur = false)
saisies_lister_avec_option($option, $saisies, $tri = 'nom')
saisies_lister_avec_sql($saisies, $tri = 'nom')
saisies_lister_finales($saisies)                             // leaves only, no containers
saisies_lister_par_etapes($saisies, $check_only = false, ?array $env = [])
saisies_lister_champs_par_section(array $saisies, array $options = [])
saisies_chercher($saisies, $id_ou_nom_ou_chemin, $retourner_chemin = false)
saisies_dont_avec_option(?array $saisies = [], string $option = '')  // bool
saisies_comparer($anciennes, $nouvelles, $avec_conteneur = true, $tri = 'nom')
```

`$avec_conteneur` decides whether `fieldset`-like fields count as fields. Turn it off when you
want the actual inputs and nothing else.

`saisies_chercher()` with `$retourner_chemin = true` gives the path to feed the manipulation
helpers — that pairing is the normal way to modify something deep in a form.

---

## Manipulating

```php
saisies_inserer($saisies, $saisie, $id_ou_nom_ou_chemin = [])
saisies_inserer_avant($saisies, $saisie, $id_ou_nom_ou_chemin)
saisies_inserer_apres($saisies, $saisie, $id_ou_nom_ou_chemin)
saisies_supprimer($saisies, $id_ou_nom_ou_chemin)
saisies_modifier($saisies, $id_ou_nom_ou_chemin, $modifs, $fusion = false)
saisies_dupliquer($saisies, $id_ou_nom_ou_chemin)
saisies_deplacer($saisies, $id_ou_nom_ou_chemin, $ou, $avant_ou_apres = 'avant')
```

All of them return a new field array; none modifies in place.

`saisies_modifier()` replaces the options it is given, or merges them with `$fusion = true`.
Changing a field's type goes at the root of `$modifs` as `nouveau_type_saisie` — the old
placement inside `options` was deprecated in 5.0 and removed in 6.0.

Bulk rewrites, useful when embedding a form inside another:

```php
saisies_transformer_noms($saisies, $masque, $remplacement)
saisies_transformer_noms_auto($formulaire, $saisies)
saisies_encapsuler_noms(array $saisies, string $prefixe, bool $recursif = true)
saisies_prefixer_id(array $saisies, string $prefixe)
saisies_transformer_option($saisies, $option, $masque, $remplacement, $recursif = true)
saisies_mapper_option($saisies, $options, $callback, $args = [], $recursif = true)
saisies_mapper_verifier($saisies, $callback, $args = [], $recursif = true)
saisies_supprimer_option($saisies, $option, $recursif = true)
saisies_wrapper_fieldset(array $saisies, array $options)
saisies_fieldsets_en_onglets($saisies, $identifiant_prefixe = '', $vertical = false)
saisies_inserer_html($saisie, $insertion, $ou = 'fin')
```

Pruning a form against actual answers, for recaps and read-only views:

```php
saisies_supprimer_sans_reponse(array $saisies, ?array $tableau = null)
saisies_saisie_possede_reponse(array $saisie, $tableau = null)
saisies_supprimer_depublie(array $saisies)
saisies_supprimer_depublie_sans_reponse(array $saisies, $reponses = null)
saisies_supprimer_callback(array $saisies, callable $callback)
```

---

## Verifying

```php
saisies_verifier($formulaire, $saisies_masquees_empty_string = true, $etape = null, $valeurs = null)
saisies_verifier_valeurs_acceptables($saisies_par_nom, $erreurs, $saisies_toutes = [])
saisies_saisie_verifier_obligatoire(array $saisie, $valeur)
saisies_appliquer_depublie_recursivement(array $saisies, string $depublie = '')
```

`saisies_verifier()` is called for you when the form declares its fields through
`formulaires_<form>_saisies()`. Call it explicitly only on the custom-markup route
(`#GENERER_SAISIES`), from the form's `verifier()`.

---

## Rendering

```php
saisies_generer_html($champ, $env = [])                        // one field, editable
saisies_generer_vue($saisie, $env = [], $env_obligatoire = []) // one field, read-only
saisie_editable($champ, $env, $utiliser_editable = true)
saisies_trouver_erreur(?array $erreurs, string $nom_ou_name)   // accepts all three error shapes
saisies_afficher_normaliser_options_attributs(array $options)
```

---

## Introspection

```php
saisies_saisie_est_tabulaire($saisie)          // posts an array?
saisies_saisie_est_fichier($saisie)            // fills $_FILES?
saisies_saisie_est_gelee(array $description)   // disabled or readonly?
saisies_saisie_est_champ(array $saisie)
saisies_saisie_est_labelisable(array $saisie)
saisies_saisie_est_avec_sous_saisies(array $saisie)
saisies_saisie_get_label(array $saisie)
saisies_saisie_get_markup(array $saisie)
```

Each of these consults the type's own `saisies/<type>.php` hook when there is one — see
`custom-saisies.md`. `saisies_saisie_est_gelee()` superseded `saisie_verifier_gel_saisie()`,
deprecated in 5.0 and gone in 6.x.

---

## Types available on this installation

```php
saisies_lister_disponibles($saisies_repertoire = 'saisies', $inclure_obsoletes = true)
saisies_lister_disponibles_par_categories($options = [])
saisies_lister_disponibles_sql($saisies_repertoire = 'saisies', $inclure_obsoletes = true)
saisies_charger_infos($type_saisie, $saisies_repertoire = 'saisies')
saisies_lister_categories()
saisies_autonomes()
```

These read the `.yaml` files and therefore **throw an exception when the yaml plugin is
absent**. Guard with `defined('_DIR_PLUGIN_YAML')` in code that must survive without it.

---

## Identifiers and data helpers

```php
saisie_identifier($saisie, $regenerer = false)     // stable id, survives renaming
saisies_identifier($saisies, $regenerer = false)
saisies_supprimer_identifiants($saisies)

saisies_chaine2tableau($chaine, $separateur = "\n")  // the "cle|label" option format
saisies_tableau2chaine($tableau)
saisies_valeur2tableau($valeur, $data = [])
saisies_aplatir_tableau($tab, $masquer_sous_groupe = false)
saisies_normaliser_liste_choix($liste)
saisies_trouver_data($description, $disable_choix = false)
```

`data` options are written as one `cle|label` per line in the builder and read back as arrays
by `saisies_chaine2tableau()` — that is the conversion to reuse instead of exploding strings by
hand.
