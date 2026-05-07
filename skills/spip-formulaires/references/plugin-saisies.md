# Saisies for SPIP forms - actionable reference

This plugin provides declarative field definitions, `#SAISIE`, `#GENERER_SAISIES`, and helper APIs around form field rendering and validation.
You must install it manually or specify the dependency with the tag `<necessite nom="saisie" compatibilite="[3.3.0;]" />` in your `paquet.xml`.

## What to use when

Use Saisies in 3 modes:
1. Single field rendering in a template with #SAISIE
2. Whole form rendering from a list with #GENERER_SAISIES
3. CVT validation from the same list with saisies_verifier()

If you need dynamic conditions between fields, use option afficher_si.
If you need reusable business checks, use verifier definitions per field.

## Canonical data model for one field

A field declaration is an associative array with this shape:

```php
[
	'saisie' => 'input',
	'options' => [
		'nom' => 'email',
		'label' => 'Email',
		'obligatoire' => 'on',
		'placeholder' => 'name@example.net',
	],
	'verifier' => [
		'type' => 'email',
		'options' => [],
	],
]
```

Notes:
- saisie is the field type
- options.nom is mandatory for real input fields
- verifier can be a single object or a list of verifier objects

## Quick start 1: render one field with #SAISIE

Template call:

```spip
#SAISIE{input,email,
	label=Email,
	obligatoire=on,
	placeholder=name@example.net}
```

Operational behavior:
- #SAISIE injects standard context automatically:
	- nom
	- valeur from #ENV*{nom}
	- erreurs
	- type_saisie
	- fond=saisies/_base
- You can pass extra options directly in call arguments

Use this when:
- You need 1 to a few fields
- You do not need a centralized list

## Quick start 2: render many fields with #GENERER_SAISIES

In PHP (form saisies function):

```php
function formulaires_mon_form_saisies_dist(): array {
	return [
		[
			'saisie' => 'input',
			'options' => [
				'nom' => 'nom',
				'label' => 'Nom',
				'obligatoire' => 'on',
			],
		],
		[
			'saisie' => 'input',
			'options' => [
				'nom' => 'email',
				'label' => 'Email',
			],
			'verifier' => [
				'type' => 'email',
			],
		],
	];
}
```

In template:

```spip
#GENERER_SAISIES{#ENV{_saisies}}
```

Equivalent shortcut:
`#GENERER_SAISIES{#TABLEAU}` == `#INCLURE{fond=inclure/generer_saisies,env,saisies=#TABLEAU}`

## CVT pattern ready to copy

PHP file:

```php
if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_mon_form_charger_dist(): array {
	return [];
}

function formulaires_mon_form_saisies_dist(): array {
	return [
		[
			'saisie' => 'input',
			'options' => [
				'nom' => 'email',
				'label' => 'Email',
				'obligatoire' => 'on',
			],
			'verifier' => [
				'type' => 'email',
			],
		],
		[
			'saisie' => 'textarea',
			'options' => [
				'nom' => 'message',
				'label' => 'Message',
			],
			'verifier' => [
				'type' => 'taille',
				'options' => ['min' => 10],
			],
		],
	];
}

function formulaires_mon_form_verifier_dist(): array {
	include_spip('inc/saisies');
	$saisies = formulaires_mon_form_saisies_dist();
	return saisies_verifier($saisies);
}

function formulaires_mon_form_traiter_dist(): array {
	return [
		'message_ok' => 'OK',
	];
}
```

Template file:

```spip
<div class="formulaire_spip formulaire_mon_form">
	[<div class="reponse_formulaire reponse_formulaire_ok">(#ENV*{message_ok})</div>]
	[<div class="reponse_formulaire reponse_formulaire_erreur">(#ENV*{message_erreur})</div>]
	<form method="post" action="#ENV{action}">
		#ACTION_FORMULAIRE{#ENV{action}}
		<div class="editer-groupe">
			#GENERER_SAISIES{#ENV{_saisies}}
		</div>
		<p class="boutons">
			<input type="submit" class="submit" value="Envoyer" />
		</p>
	</form>
</div>
```

## YAML and array: same semantics

For agents, treat YAML as another syntax for the same field declaration tree.

YAML example:

```yaml
- saisie: input
	options:
		nom: email
		label: Email
		obligatoire: on
	verifier:
		type: email

- saisie: textarea
	options:
		nom: message
		label: Message
	verifier:
		type: taille
		options:
			min: 10
```

Equivalent PHP array is accepted directly by `#GENERER_SAISIES` and `saisies_verifier()`.

## Conditional display with afficher_si

Attach condition in `options.afficher_si`:

```php
[
	'saisie' => 'input',
	'options' => [
		'nom' => 'societe',
		'label' => 'Societe',
		'afficher_si' => '@type_contact@ == "pro"',
	],
]
```

Useful operators:
- `==`, `!=`, `>`, `>=`, `<`, `<=`
- `IN`, `!IN`
- `MATCH`, `!MATCH`
- boolean style tests with `@champ@`

Operational effect in verification:
- hidden fields by afficher_si are excluded from checks
- at end of successful validation flow, masked fields can be set to empty string

## Verifier integration rules

One verifier:

```php
'verifier' => [
	'type' => 'email',
	'options' => [],
]
```

Multiple verifiers:

```php
'verifier' => [
	['type' => 'email'],
	['type' => 'taille', 'options' => ['max' => 180]],
]
```

Behavior:
- obligatory check runs first
- each verifier is applied
- normalized values can be written back into request context
- errors are returned as standard CVT erreurs array by field name

## Global options on the form list

The form list may contain a top-level options key:

```php
[
	'options' => [
		'ajax' => true,
		'conteneur_class' => 'mon_formulaire',
		'obligatoire_defaut' => true,
		'verifier_valeurs_acceptables' => true,
	],
	[ 'saisie' => 'input', 'options' => ['nom' => 'titre', 'label' => 'Titre'] ],
]
```

Frequent useful options:
- ajax
- conteneur_class
- obligatoire_defaut
- verifier_valeurs_acceptables

## Create a custom saisie type

Minimum runtime requirement (usable by the plugin #SAISIE):
1. create saisies/mon_type.html

To make it configurable/listed by Saisies builders:
1. create saisies/mon_type.yaml
2. keep same basename as HTML file
3. provide title/description/options in yaml

Recommended files:
1. saisies/mon_type.html
2. saisies/mon_type.yaml
3. saisies/mon_type.php (optional helper/filter functions)
4. saisies-vues/mon_type.html (if you need a dedicated read-only view)

Minimal mon_type.yaml:

```yaml
titre: 'Mon type'
description: 'Champ personnalise'
categorie:
	type: 'libre'
	rang: 10
options:
	-
		saisie: fieldset
		options:
			nom: description
			label: 'Description'
		saisies:
			-
				saisie: input
				options:
					nom: label
					label: 'Label'
					obligatoire: 'on'
defaut:
	options:
		label: 'Mon type'
```

Minimal mon_type.html:

```spip
<input
	type="text"
	name="#ENV{nom}"
	id="#ENV{id}"
	class="text[ (#ENV{class})]"
	[value="(#ENV{valeur,#ENV{defaut}}|attribut_html)"]
	[(#ENV{disable}|oui)disabled="disabled"]
	[(#ENV{readonly}|oui)readonly="readonly"]
	[aria-describedby="(#ENV{describedby})"]
/>
```

## Agent checklist before shipping a form

1. Every real field has `options.nom`
2. `#GENERER_SAISIES` receives a valid list (array)
3. verifier uses `saisies_verifier()` on the same list
4. Required fields use `obligatoire` and show errors in template
5. `afficher_si` conditions only target existing field names
6. For custom types, html and yaml basenames match
7. If using builder/listing, YAML plugin availability is assumed

## Extension points to know

Main pipelines for advanced integration:
- `formulaire_saisies`
- `saisies_lister_disponibles`
- `saisies_verifier_lister_disponibles`
- `saisies_afficher_si_saisies`
- `saisies_verifier`
