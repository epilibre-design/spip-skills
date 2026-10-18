<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Filtre : température actuelle pour une ville, via l'API météo du site.
 *
 * Usage : [(#TITRE|meteo_temperature)]
 */
function filtre_meteo_temperature_dist(string $ville): string {
	include_spip('inc/distant');
	$res = recuperer_url('https://meteo.example.org/api?ville=' . rawurlencode($ville));
	if (!$res || empty($res['page'])) {
		return '';
	}
	$data = json_decode($res['page'], true);
	return isset($data['temp']) ? $data['temp'] . ' °C' : '';
}
