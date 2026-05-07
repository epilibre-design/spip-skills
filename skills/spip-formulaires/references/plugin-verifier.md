# Verifier for SPIP forms - actionable reference

This plugin provides reusable validation and normalisation helpers through the `verifier()` API for field values, file uploads, and cross-field comparisons.
You must install it manually or specify the dependency with the tag `<necessite nom="verifier" compatibilite="[3.6.1;]" />` in your `paquet.xml`.

## Core API

Verifier exposes one main function:

```php
$verifier = charger_fonction('verifier', 'inc');
$erreur = $verifier($valeur, $type, $options, $valeur_normalisee);
```

Signature semantics:
- `$valeur`: input value to validate
- `$type`: validator type, for example `email`, `entier`, `date`, `url`
- `$options`: validator-specific options
- `$valeur_normalisee`: output variable filled when the validator normalizes the value

Return contract:
- `''` means valid
- non-empty string means invalid and contains a user-facing error message

## Operational rules you must know

1. If the value is empty, Verifier returns success by default
2. Empty values are still processed when option `normaliser` is used
3. Verifier only validates one value at a time
4. Form-level error arrays are your responsibility in CVT, or Saisies handles that aggregation for you

Implication:
- Required/mandatory logic should usually be handled by Saisies `obligatoire` or explicit CVT checks
- Verifier is mainly for format, range, syntax, consistency, and normalization

## Quick start 1: direct use in verifier()

```php
function formulaires_mon_form_verifier_dist(): array {
	$erreurs = [];
	$verifier = charger_fonction('verifier', 'inc');

	if ($msg = $verifier(_request('email'), 'email', ['mode' => 'strict'])) {
		$erreurs['email'] = $msg;
	}

	if ($msg = $verifier(_request('age'), 'entier', ['min' => 18])) {
		$erreurs['age'] = $msg;
	}

	return $erreurs;
}
```

Use this when:
- the form does not use Saisies
- you need only a few validations
- validation depends on custom branching in CVT code

## Quick start 2: use via Saisies declarations

```php
[
	'saisie' => 'input',
	'options' => [
		'nom' => 'email',
		'label' => 'Email',
	],
	'verifier' => [
		'type' => 'email',
		'options' => ['mode' => 'strict'],
	],
]
```

Then in CVT:

```php
function formulaires_mon_form_verifier_dist(): array {
	include_spip('inc/saisies');
	$saisies = formulaires_mon_form_saisies_dist();
	return saisies_verifier($saisies);
}
```

Use this when:
- the form is driven by Saisies
- validation should stay next to field declarations
- you want reusable declarative form definitions

## CVT pattern ready to copy

```php
if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_mon_form_verifier_dist(): array {
	$erreurs = [];
	$verifier = charger_fonction('verifier', 'inc');

	$email_normalise = null;
	if ($msg = $verifier(_request('email'), 'email', ['mode' => 'strict'], $email_normalise)) {
		$erreurs['email'] = $msg;
	} elseif (!is_null($email_normalise)) {
		set_request('email', $email_normalise);
	}

	$date_normalisee = null;
	if ($msg = $verifier(_request('date_naissance'), 'date', ['format' => 'jma', 'normaliser' => 'date'], $date_normalisee)) {
		$erreurs['date_naissance'] = $msg;
	} elseif (!is_null($date_normalisee)) {
		set_request('date_naissance', $date_normalisee);
	}

	if ($msg = $verifier(_request('mot_de_passe_confirm'), 'comparaison_champ', [
		'champ' => 'mot_de_passe',
		'comparaison' => 'egal',
		'nom_champ' => 'Mot de passe',
	])) {
		$erreurs['mot_de_passe_confirm'] = $msg;
	}

	return $erreurs;
}
```

## Choose the right validator

Frequent useful types:
- `email`
- `entier`
- `decimal`
- `taille`
- `date`
- `url`
- `telephone`
- `regex`
- `comparaison_champ`
- `slug`
- `fichiers`

Other available built-in types in this plugin snapshot:
- `attribut_class`
- `code_postal`
- `couleur`
- `iban`
- `id_document`
- `id_objet`
- `isbn`
- `siren_siret`

## email

Canonical call:

```php
$msg = $verifier($email, 'email', ['mode' => 'strict']);
```

Useful options:
- `mode`: `normal`, `rfc5322`, `strict`
- `unique`: reject multiple emails in one string
- `disponible`: ensure address is not already used in `spip_auteurs`
- `id_auteur`: exclude one author from availability check

Examples:

```php
['type' => 'email']
['type' => 'email', 'options' => ['mode' => 'strict']]
['type' => 'email', 'options' => ['disponible' => true, 'id_auteur' => 12]]
```

Use `strict` when login/account identity matters.
Use `normal` for general contact forms.

## entier

Canonical call:

```php
$msg = $verifier($age, 'entier', ['min' => 0, 'max' => 120]);
```

Useful options:
- `min`
- `max`

Use for counts, years, ids entered manually, integer quotas.

## decimal

Canonical call:

```php
$normalise = null;
$msg = $verifier($prix, 'decimal', [
	'min' => 0,
	'normaliser' => true,
	'nb_decimales' => 2,
], $normalise);
```

Useful options:
- `min`
- `max`
- `separateur`
- `normaliser`
- `nb_decimales`

Normalization behavior:
- removes spaces
- can transform comma decimal input to dot decimal form
- can simplify locale-formatted numbers before storage

Use when users may type values like `1 300,50`.

## taille

Canonical call:

```php
$msg = $verifier($mot_de_passe, 'taille', ['min' => 12]);
```

Useful options:
- `min`
- `max`
- `egal`

Use for password length, code/token length, short labels, textarea limits.

## date

Canonical call:

```php
$normalisee = null;
$msg = $verifier($date, 'date', [
	'format' => 'jma',
	'normaliser' => 'date',
], $normalisee);
```

Input forms accepted:
- string date
- array with `date` and optional `heure`

Useful options:
- `format`: `jma`, `mja`, `amj`
- `normaliser`: `date`, `datetime`, `date_ou_datetime`, `aucune`
- `heure`
- `fin_de_journee`
- `valeur_vide`
- `vider_date_nulle`

Typical outcomes:
- validate `30-01-2009`
- normalize to SQL `YYYY-MM-DD`
- normalize to SQL `YYYY-MM-DD HH:MM:SS`

Use `normaliser => 'date'` for SQL date columns.
Use `normaliser => 'datetime'` when the stored value must include time.

## url

Canonical call:

```php
$msg = $verifier($url, 'url', [
	'mode' => 'complet',
	'type_protocole' => 'web',
]);
```

Useful options:
- `mode`: `protocole_seul`, `php_filter`, `complet`
- `type_protocole`: `tous`, `web`, `mail`, `ftp`, `exact`
- `protocole`: required when `type_protocole = exact`
- `objet_spip`: allow SPIP implicit object links

Guidance:
- `protocole_seul`: minimal check, weak
- `php_filter`: generic URL validation
- `complet`: stronger syntax validation and usually best default

## regex

Canonical call:

```php
$msg = $verifier($code, 'regex', ['modele' => '/^[A-Z0-9_-]+$/']);
```

Use when built-in validators do not match the domain rule.
Prefer built-in validators before regex when possible.

## comparaison_champ

Use to compare one posted field against another field from request context.

Example: confirm password.

```php
$msg = $verifier(_request('password_confirm'), 'comparaison_champ', [
	'champ' => 'password',
	'comparaison' => 'egal',
	'nom_champ' => 'Mot de passe',
]);
```

Useful options:
- `champ`: other field name in request
- `comparaison`: `egal`, `egal_type`, `different`, `different_type`, `petit`, `petit_egal`, `grand`, `grand_egal`
- `nom_champ`: human-readable field name inserted in message
- `message_erreur`: custom error message

## slug

Use to validate or normalize clean identifiers.

```php
$normalise = null;
$msg = $verifier($slug, 'slug', [
	'normaliser' => true,
	'separateur' => '-',
	'longueur_maxi' => 80,
], $normalise);
```

Useful options:
- `normaliser`
- `normaliser_suggerer`
- `separateur`
- `longueur_maxi`

Use when storing public identifiers in URLs.

## fichiers

Use for uploaded files, typically with Saisies upload fields.

```php
$erreurs_fichiers = null;
$msg = $verifier($_FILES['document'], 'fichiers', [
	'mime' => 'image_web',
	'taille_max' => 2048,
	'largeur_max' => 1600,
	'hauteur_max' => 1600,
], $erreurs_fichiers);
```

Useful options:
- `mime`: `pas_de_verification`, `image_web`, `tout_mime`, `specifique`
- `mime_specifique`: required when `mime = specifique`
- `taille_max`: KiB
- `dimension_max`: array with `largeur`, `hauteur`, `autoriser_rotation`
- shorthand aliases: `largeur_max`, `hauteur_max`, `dimension_autoriser_rotation`

Operational notes:
- supports single or multiple uploads
- returns one joined error string
- may also fill detailed per-file errors by reference

## Normalization pattern

Some validators can rewrite the input into a storage-ready format.

Typical pattern:

```php
$normalisee = null;
$msg = $verifier($valeur, 'decimal', ['normaliser' => true], $normalisee);
if (!$msg && !is_null($normalisee)) {
	set_request('prix', $normalisee);
}
```

Use normalization when:
- users type locale-specific numeric formats
- dates must be stored in SQL format
- slugs must be generated from free text

## Error handling strategy in CVT

Recommended pattern:

```php
$erreurs = [];
if ($msg = $verifier(_request('email'), 'email', ['mode' => 'strict'])) {
	$erreurs['email'] = $msg;
}
if ($msg = $verifier(_request('url_site'), 'url', ['mode' => 'complet'])) {
	$erreurs['url_site'] = $msg;
}
if ($erreurs) {
	$erreurs['message_erreur'] = 'Veuillez corriger les champs en erreur.';
}
return $erreurs;
```

Guideline:
- field-specific messages go on field keys
- `message_erreur` is optional and form-level
- keep Verifier messages intact unless business wording requires otherwise

## Relationship with Saisies

Saisies uses Verifier as the standard validation layer.
When a field declaration contains:

```php
'verifier' => [
	'type' => 'email',
	'options' => ['mode' => 'strict'],
]
```

then `saisies_verifier()` will call Verifier for that field.

Prefer Saisies declarations when the validation rule belongs to one field.
Prefer direct `inc/verifier` calls when validation logic is procedural or cross-field.

## When to choose Verifier vs custom code

Choose Verifier when:
- a field has a reusable validation rule
- you need standard user-facing error messages
- the validator already exists
- normalization is useful

Choose custom CVT code when:
- the rule depends on multiple fields plus business state
- validation requires database workflow beyond simple availability checks
- the error should attach to the form as a whole rather than one field

## Agent checklist before shipping validation

1. Required/empty-state handling is covered separately from format checks
2. The chosen validator type matches the stored data shape
3. Options are minimal and explicit
4. Normalized values are written back when needed
5. Cross-field checks use `comparaison_champ` or explicit CVT logic
6. File validators receive `$_FILES[...]`, not plain strings
7. Form-level `message_erreur` is only added when useful

## Extension point

The `verifier` pipeline runs after the validator function call and receives:
- `valeur`
- `valeur_normalisee`
- `type`
- `options`

Use it only for advanced cross-plugin customization.
