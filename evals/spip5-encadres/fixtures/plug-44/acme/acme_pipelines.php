<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Réglages du plugin, enregistrés par le formulaire configurer_acme (meta « acme »).
 */
function acme_config(): array {
	$config = unserialize($GLOBALS['meta']['acme'] ?? '');

	return is_array($config) ? $config : [];
}

function acme_insert_head_css($flux) {
	$config = acme_config();
	if (($config['theme'] ?? '') === 'sombre') {
		$flux .= '<link rel="stylesheet" href="' . find_in_path('css/acme_sombre.css') . '" type="text/css" />' . "\n";
	}

	return $flux;
}
