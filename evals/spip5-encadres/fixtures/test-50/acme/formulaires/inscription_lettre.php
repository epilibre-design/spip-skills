<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_inscription_lettre_charger_dist() {
	return [
		'nom' => '',
		'email' => '',
	];
}

function formulaires_inscription_lettre_verifier_dist() {
	include_spip('inc/filtres');
	$erreurs = [];
	$email = trim((string) Spip::input('email'));
	if ($email === '') {
		$erreurs['email'] = _T('info_obligatoire');
	} elseif (!email_valide($email)) {
		$erreurs['email'] = _T('form_prop_indiquer_email');
	}

	return $erreurs;
}

function formulaires_inscription_lettre_traiter_dist() {
	sql_insertq('spip_inscrits', [
		'nom' => Spip::input('nom'),
		'email' => Spip::input('email'),
	]);

	return ['message_ok' => _T('acme:inscription_merci')];
}
