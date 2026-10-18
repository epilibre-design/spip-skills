<?php

use Spip\Afficher\Minipage\Admin as MinipageAdmin;

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2015                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
 * \***************************************************************************/

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

// Demarrer un site dans le sous-repertoire des sites
// Options :
// creer_site => on va creer les repertoires qui vont bien (defaut: false)
// cookie_prefix, table_prefix => regler les prefixes (defaut: true)
// https://code.spip.net/@demarrer_site
function demarrer_site($site = '', $options = []) {
	if (!$site) {
		return;
	}

	$options = array_merge(
		[
			'creer_site' => false,
			'creer_base' => false,
			'creer_user_base' => false,
			'mail' => '',
			'code' => 'ecureuil', // code d'activation par defaut
			'table_prefix' => false,
			'cookie_prefix' => false,
			'repertoire' => 'sites',
			'utiliser_panel' => false,
			'url_img_courtes' => false,
			'annonce' => '',
		],
		$options
	);

	$GLOBALS['mutualisation_dir'] = $options['repertoire'];

	// definir une constante qui contient l'adresse du repertoire du site mutualise
	// peut servir dans les fichiers mes_options des sites inclus
	// par exemple avec  $GLOBALS['dossier_squelettes'] = _DIR_SITE . 'squelettes:' . _DIR_SITE . 'cheznous:dist';
	define('_DIR_SITE', _DIR_RACINE . $GLOBALS['mutualisation_dir'] . '/' . $site . '/');

	if ($options['cookie_prefix']) {
		$GLOBALS['cookie_prefix'] = _INSTALL_SITE_PREF;
	}
	if ($options['table_prefix']) {
		$GLOBALS['table_prefix'] = _INSTALL_SITE_PREF;
	}

	/*
	 * Si le dossier du site n'existe pas
	 * ou si le fichier de connexion a la bdd est absent,
	 * le site n'est pas totalement installe.
	 *
	 * Il faut lancer la creation de mutualisation
	 */
	if ($a_installer = (!is_dir(_DIR_SITE) or !(
		defined('_DIR_CONNECT') ?
			(defined('_FILE_CONNECT_INS') ?
				file_exists(_DIR_CONNECT . _FILE_CONNECT_INS . '.php') :
				file_exists(_DIR_CONNECT . 'connect.php')) :
			(defined('_FILE_CONNECT_INS') ?
				file_exists(_DIR_SITE . _NOM_PERMANENTS_INACCESSIBLES . _FILE_CONNECT_INS . '.php') :
				file_exists(_DIR_SITE . _NOM_PERMANENTS_INACCESSIBLES . 'connect.php'))
	))
	) {
		/*
		 * - Recuperer les identifiants manquants
		 *
		 * Nota :
		 * Il faut que _INSTALL_(NAME|USER|PASS) soient definis ici
		 * et non dans le fichier mutualiser_creer() car
		 * ces constantes sont necessaires a l'installation de SPIP
		 * (ecrire/?exec=install). Dans le cas contraire, le formulaire
		 * d'installation n'est pas prerempli.
		 *
		 *
		 * > Cas de la gestion d'un pannel
		 * Recuperer les mots de passes du futur compte dans une table speciale
		 * Voir https://contrib.spip.net/Service-d-hebergement-mutualise
		 */
		if ($options['utiliser_panel']) {
			if (defined(_INSTALL_PANEL_HOST_DB)) {
				include_spip('base/abstract_sql');
				include_once __DIR__ . '/base/abstract_mutu.php';

				// On cherche en BD si le site est enregistre et on recupere
				// password et code d'activation
				$link = @mutu_connect_db(
					_INSTALL_PANEL_HOST_DB,
					0,
					_INSTALL_PANEL_USER_DB,
					_INSTALL_PANEL_PASS_DB,
					'',
					_INSTALL_SERVER_DB
				);
				@sql_selectdb(_INSTALL_PANEL_NAME_DB, _INSTALL_SERVER_DB);
				$result = @sql_query('SELECT '
					. _INSTALL_PANEL_FIELD_CODE . ' AS code,'
					. _INSTALL_PANEL_FIELD_PASS . ' AS pass,'
					. _INSTALL_PANEL_FIELD_SITE . ' AS site'
					. ' FROM ' . _INSTALL_PANEL_NAME_TABLE
					. ' WHERE ' . _INSTALL_PANEL_FIELD_SITE . " = '$site'", _INSTALL_SERVER_DB);
				if (sql_count($result) > 0) {
					$data = sql_fetch($result);
					$options['code'] = $data['code'];
					define('_INSTALL_NAME_DB', _INSTALL_NAME_DB);
					define('_INSTALL_USER_DB', _INSTALL_NAME_DB);
					define('_INSTALL_PASS_DB', $data['pass']);
				} else {
					$minipage = new MinipageAdmin();
					echo $minipage->page('', ['title' => _L('<h2>Erreur 404 : page inexistante</h2>')]);
					exit;
				}
			} else {
				if (is_dir($options['repertoire'] . '/' . _SITES_ADMIN_MUTUALISATION)) {
					include_spip('base/abstract_sql');
					define('_FILE_CONNECT', $options['repertoire'] . '/' . _SITES_ADMIN_MUTUALISATION . '/config/connect.php');
					$mutu = sql_fetsel('id_admin,id_mutu,statut', 'spip_mutus', 'url=' . sql_quote($site));
					$options['id_mutu'] = $mutu['id_mutu'];
					$options['statut'] = $mutu['statut'];
					$options['login'] = sql_getfetsel('login', 'spip_auteurs', 'id_auteur=' . intval($mutu['id_admin']));
					define('_FILE_CONNECT', '');
				}
			}
			/*
			 * > Cas de creation d'utilisateur de la base SQL
			 * (nom et pass non attribuees par un panel)
			 */
		} elseif ($options['creer_user_base']) {
			// nom d'utilisateur et mot de passe
			if (!defined('_INSTALL_USER_DB')) {
				define('_INSTALL_USER_DB', _INSTALL_NAME_DB);
			}
			/*
			 * _INSTALL_PASS_DB_ROOT : secret du site
			 * $_SERVER['HTTP_HOST'] : un truc variable, mais reutilisable site par site
			 * _INSTALL_USER_DB : un autre truc variable
			 */
			define('_INSTALL_PASS_DB', substr(md5(_INSTALL_PASS_DB_ROOT . $_SERVER['HTTP_HOST'] . _INSTALL_USER_DB), 0, 8));
		}

		/*
		 * Si l'installation n'est pas faite,
		 * il faut soit creer le site mutualise
		 * soit lancer la procedure d'installation de SPIP
		 * si le site a deja ete cree
		 */
		if ($a_installer) {
			/*
			 * Pour savoir si l'installation de la mutu est terminee,
			 * on verifie que le fichier d'installation a bien ete supprime
			 * sinon, c'est qu'il reste quelque chose a faire.
			 */
			define('_MUTU_INSTALLATION_FILE', 'mutu_tmp_install.txt');

			if (!is_dir(_DIR_SITE) || is_file(_DIR_SITE . _NOM_TEMPORAIRES_INACCESSIBLES . _MUTU_INSTALLATION_FILE)) {
				spip_initialisation(
					(_DIR_RACINE . _NOM_PERMANENTS_INACCESSIBLES),
					(_DIR_RACINE . _NOM_PERMANENTS_ACCESSIBLES),
					(_DIR_RACINE . _NOM_TEMPORAIRES_INACCESSIBLES),
					(_DIR_RACINE . _NOM_TEMPORAIRES_ACCESSIBLES)
				);
				include_once __DIR__ . '/mutualiser_creer.php';
				mutualiser_creer(_DIR_SITE, $options);
				exit;
			}
		}
	}

	/*
	 * Tout est pret, on execute la mutualisation.
	 */
	define(
		'_SPIP_PATH',
		_DIR_SITE . ':' .
		_DIR_RACINE . ':' .
		_DIR_RACINE . 'squelettes-dist/:' .
		_DIR_RACINE . 'prive/:' .
		_DIR_RESTREINT
	);

	if (is_dir(_DIR_SITE . 'squelettes')) {
		$GLOBALS['dossier_squelettes'] = $options['repertoire'] . '/' . $site . '/squelettes';
	}

	if (is_readable($f = _DIR_SITE . _NOM_PERMANENTS_INACCESSIBLES . _NOM_CONFIG . '.php')) {
		include $f;
	} // attention cet include n'est pas en globals

	if (isset($options['avant_initialisation']) && is_callable($options['avant_initialisation'])) {
		$options['avant_initialisation']();
	}

	$init = function_exists('spip_initialisation_core')
		? 'spip_initialisation_core' // mieux pour la 2.0, mais absente avant...
		: 'spip_initialisation';
	$init(
		(_DIR_SITE . _NOM_PERMANENTS_INACCESSIBLES),
		(_DIR_SITE . _NOM_PERMANENTS_ACCESSIBLES),
		(_DIR_SITE . _NOM_TEMPORAIRES_INACCESSIBLES),
		(_DIR_SITE . _NOM_TEMPORAIRES_ACCESSIBLES)
	);

	/*
	 *Traiter l'exec=mutualisation
	 */
	mutualisation_traiter_exec($site);

	/*
	 * Gestion des url d'images courtes
	 * sites/nom/IMG/image.jpg -> IMG/image.jpg
	 *
	 * Ne fonctionne que pour de la mutualisation
	 * sur des noms de domaines.
	 *
	 * Une mutualisation de repertoire
	 * ne pourra fonctionner car les fichiers
	 * .htaccess de /IMG et /local n'ont pas
	 * connaissance du nom du repertoire.
	 *
	 * A mettre au debut du pipe pour compatibilite avec fastcache
	 */
	if ($options['url_img_courtes']) {
		$pre_affichage_final = $GLOBALS['spip_pipeline']['affichage_final'] ?? '';
		$GLOBALS['spip_pipeline']['affichage_final']
			= '|mutualisation_url_img_courtes'
			. $pre_affichage_final;
	}
}

// Ajouter le chemin vers l'exec=mutualisation pour le site maitre
// et seulement pour lui (pour en mettre plusieurs, les separer par
// des virgules) Voit le fichier mes_options.php
function mutualisation_traiter_exec($site) {
	if (_request('exec') === 'mutualisation') {
		if (!defined('_SITES_ADMIN_MUTUALISATION')
			or in_array($site, explode(',', _SITES_ADMIN_MUTUALISATION))
		) {
			require_once __DIR__ . '/exec/mutualisation.php';
		}

		// Si un upgrade est demande dans le site fils, et securise par md5
		// depuis le panneau de controle, le faire directement
		if (_request('upgrade') == 'oui') {
			require_once __DIR__ . '/mutualiser_upgrade.php';
			mutualiser_upgrade();
		}
		// Si un upgrade des plugins est demande dans le site fils, et securise par md5
		// depuis le panneau de controle, le faire directement
		if (_request('upgradeplugins') == 'oui') {
			require_once __DIR__ . '/mutualiser_upgradeplugins.php';
			mutualiser_upgradeplugins();
		}
		if (_request('renouvelle_alea') == 'yo') {
			include_spip('inc/headers');
			http_response_code(204); // No Content
			header('Connection: close');
			include_spip('inc/acces');
			renouvelle_alea();
			die;
		}
		if (_request('dirliste') == 'oui') {
			if (isset($_GET['dir'])) {
				require_once __DIR__ . '/inc/dirliste.php';
				echo dirliste($_GET['dir']);
			} else {
				echo '-1';
			}
			die;
		}
		if (_request('dirsize') == 'oui') {
			if (isset($_GET['dir'])) {
				require_once __DIR__ . '/inc/dirsize.php';
				echo dirsize($_GET['dir'], $_GET['taille_max']);
			} else {
				echo '-1';
			}
			die;
		}
	}
}

/**
 * Transformer les sites/truc/IMG/rtf/chose.rtf en /IMG/...
 *
 * @param string $flux
 *        L'URL à réécrire
 */
function mutualisation_url_img_courtes($flux) {
	if (strpos($flux, (string) _DIR_IMG)
		or strpos($flux, (string) _DIR_VAR)
	) {
		require_once __DIR__ . '/mutualiser_gerer_img.php';

		return mutualisation_traiter_url_img_courtes($flux);
	}

	return $flux;
}

/**
 * Cette fonction cree un nom d'ut acceptable par MySQL a partir du nom
 * du site ; a utiliser comme prefixe des tables, comme suffixe du nom
 * de la base de donnees, comme suffixe du nom d'utilisateur MySQL créé
 * ou comme prefixe des cookies... unicite quasi garantie
 * Max 12 caracteres a-z0-9, qui ressemblent au domaine et ne commencent
 * pas par un chiffre.
 *
 * Pour rappel :
 * - Les noms d'utilisateurs MySQL peuvent avoir jusqu'à 16 caractères
 * (cf. http://dev.mysql.com/doc/refman/5.0/fr/user-names.html)
 * - Les noms de bases de données MySQL peuvent avoir jusqu'à 64 caractères
 * (cf. http://dev.mysql.com/doc/refman/5.0/fr/legal-names.html)
 *
 * @param string $site
 *        Le nom du site
 */
function prefixe_mutualisation($site) {
	static $prefix = [];
	$max = 12;
	if (defined('_INSTALL_PREFIX_DB') && strlen(_INSTALL_PREFIX_DB) > 0) {
		$max = 15 - strlen(_INSTALL_PREFIX_DB);
	}
	if (!isset($prefix[$site])) {
		$p = preg_replace(',^www\.|[^a-z0-9],', '', strtolower($site));
		// si c'est plus long que $max on coupe et on pose un md5 d'unicite
		// meme chose si ca contenait un caractere autre que [a-z0-9],
		// afin d'eviter de se faire chiper c.a.domaine.tld par ca.domaine.tld
		if (strlen($p) > $max or $p != $site) {
			$p = substr($p, 0, ($max - 4)) . substr(md5($site), -4);
		}
		// si ca commence par un chiffre on ajoute a
		if (ord($p[0]) < 58) {
			$p = 'a' . $p;
		}
		$prefix[$site] = $p;
	}

	return $prefix[$site];
}
