# SPIP 5.0 callouts — GREEN raw answers

Verbatim answers of the runs with the callout. Scoring: `spip5-encadres-green.md`.

---

## spip-logs, cas 1 — plugin 5.0 seul (`[5.0.0-dev;5.*]`)

#### Version ciblée

Le `paquet.xml` d'acme déclare `compatibilite="[5.0.0-dev;5.*]"` : le plugin vise **uniquement SPIP 5.0**. C'est sur ce repère que je me base. En 5.0, `spip_log()` est déprécié (et `spip_logger()` aussi, à ne pas utiliser). On passe donc par l'API logger de SPIP 5 : `Spip::logger('acme')->error($msg)`. Le canal `'acme'` écrit dans un fichier propre au plugin, **`tmp/log/acme.log`**.

#### Code modifié — `inc/acme_import.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Importe le flux distant configuré dans acme/url_flux.
 *
 * @return int Nombre d'éléments importés
 */
function acme_importer(): int {
	include_spip('inc/distant');
	$url = lire_config('acme/url_flux', '');

	if ($url === '') {
		Spip::logger('acme')->error('acme_importer : aucune URL de flux configurée (acme/url_flux)');
		return 0;
	}

	$res = recuperer_url($url);
	if (!$res || empty($res['page'])) {
		Spip::logger('acme')->error(sprintf(
			'acme_importer : flux distant injoignable ou vide (url=%s, status=%s)',
			$url,
			is_array($res) ? ($res['status'] ?? '?') : 'échec de la requête'
		));
		return 0;
	}

	$items = json_decode($res['page'], true) ?: [];
	foreach ($items as $item) {
		sql_insertq('spip_acme_items', ['titre' => $item['titre'] ?? '']);
	}
	return count($items);
}
```

#### Points à noter

- **Fichier de log** : `tmp/log/acme.log` (le nom du canal passé à `Spip::logger()`).
- **Niveau** : `error()` (équivalent 5.0 de `_LOG_ERREUR`). Il est écrit avec la configuration par défaut, car le seuil `spip.logger.max_level` vaut `notice` par défaut et `error` est plus grave.
- **Configuration** : en 5.0, `_LOG_FILTRE_GRAVITE`, `_MAX_LOG`, `$nombre_de_logs` et `$taille_des_logs` n'ont **plus d'effet**. Pour changer le seuil ou la rotation, réglez `spip.logger.max_level`, `.max_log`, `.max_files`, `.max_size` dans le `config/custom.php` du site.
- J'ai aussi tracé le cas « URL non configurée », qui échouait lui aussi sans rien dire. Retirez ce bloc si vous ne voulez logger que l'injoignabilité du flux.
- Si un jour le plugin doit aussi tourner sous SPIP 4.4 (`compatibilite` partant de 4.x), il faudra revenir à `spip_log($msg, 'acme.' . _LOG_ERREUR)`, seule API disponible dans les deux versions. `Spip::logger()` n'existe pas en 4.4.
- Si vous déplacez cette logique dans une classe (service), injectez-y le logger plutôt que d'appeler `Spip::logger()`.

---

## spip-logs, cas 2 — plugin 4.4 + 5.0 (`[4.4.0;5.*]`)

#### Version ciblée

Le `paquet.xml` d'acme déclare `compatibilite="[4.4.0;5.*]"` : le plugin vise **à la fois SPIP 4.4 et SPIP 5.0**. Dans ce cas, il faut garder `spip_log()`, la seule API de log présente dans les deux versions :

- `Spip::logger('acme')->error()` n'existe qu'en 5.0, donc le plugin planterait sous 4.4 ;
- `spip_logger()` est absent en 4.4 et déprécié en 5.0, donc à éviter ;
- `spip_log()` est déprécié en 5.0 mais fonctionne encore. Ce n'est qu'en passant le plugin en 5.0 uniquement (compatibilité qui démarre à 5.0) qu'on le remplacera par `Spip::logger('acme')->error($msg)`.

#### Code modifié — `inc/acme_import.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Importe le flux distant configuré dans acme/url_flux.
 *
 * @return int Nombre d'éléments importés
 */
function acme_importer(): int {
	include_spip('inc/distant');
	$url = lire_config('acme/url_flux', '');
	$res = recuperer_url($url);
	if (!$res || empty($res['page'])) {
		$statut = (is_array($res) && isset($res['status'])) ? $res['status'] : 'aucune réponse';
		spip_log(
			"acme_importer : flux distant injoignable ou vide (url='$url', statut=$statut)",
			'acme.' . _LOG_ERREUR
		);
		return 0;
	}
	$items = json_decode($res['page'], true) ?: [];
	foreach ($items as $item) {
		sql_insertq('spip_acme_items', ['titre' => $item['titre'] ?? '']);
	}
	return count($items);
}
```

#### Pourquoi c'est écrit ainsi

- **Fichier propre au plugin** : le second argument `'acme.' . _LOG_ERREUR` fixe à la fois le nom du fichier (préfixe `acme` → `tmp/log/acme.log`) et la gravité.
- **Gravité `_LOG_ERREUR` (3)** : elle est inférieure au seuil par défaut `_LOG_FILTRE_GRAVITE` (`_LOG_INFO_IMPORTANTE`, 5), donc le message est **écrit sans aucune configuration**. Un simple `spip_log($msg, 'acme')` aurait pris la gravité par défaut `_LOG_INFO` (6) et n'aurait **rien écrit**, ce qui reproduirait exactement le problème « pas de trace ».
- **Contexte utile** : l'URL et le statut HTTP renvoyé par `recuperer_url()` (s'il existe) permettent de distinguer un serveur injoignable, une erreur HTTP ou un flux vide.

#### Lire le log

```bash
tail -f tmp/log/acme.log
```

Les fichiers de `tmp/log/` sont tournés automatiquement par SPIP (`acme.log`, `acme.log.1`, …).

Remarque : quand le site passera en SPIP 5.0, la rotation et le seuil ne se règleront plus avec `_LOG_FILTRE_GRAVITE` / `_MAX_LOG` (ils n'ont plus d'effet), mais avec `spip.logger.max_level` (par défaut `notice`), `.max_files`, `.max_size` dans `config/custom.php`. Un `error` reste au-dessus du seuil par défaut, donc la trace sera toujours écrite dans `tmp/log/acme.log`.

---

## spip-logs, cas 3 — plugin 4.x (`[4.2.0;4.4.*]`)

#### Version ciblée

Le `paquet.xml` d'acme déclare `compatibilite="[4.2.0;4.4.*]"` : le plugin cible **SPIP 4.x** (et non 5.0). L'API de log à utiliser est donc `spip_log()`. (Pas de `Spip::logger()`, propre à 5.0, et surtout pas `spip_logger()`, absent en 4.4.)

#### Principe

- **Fichier propre au plugin** : on passe un type égal au préfixe du plugin, `'acme'`, ce qui écrit dans `tmp/log/acme.log` (avec rotation automatique `acme.log.1`, `.2`…). Les lignes sont aussi dupliquées dans `spip.log`.
- **Gravité erreur** : `'acme.' . _LOG_ERREUR` (3). Important : un simple `spip_log($msg, 'acme')` utiliserait la gravité par défaut `_LOG_INFO` (6), qui est **filtrée par défaut** (seuil `_LOG_FILTRE_GRAVITE` = 5) : rien ne serait écrit. Avec `_LOG_ERREUR`, le message est écrit sans rien configurer.
- On distingue les cas d'échec pour que le log soit exploitable : URL non configurée, flux injoignable (`recuperer_url()` renvoie `false`), réponse vide, JSON invalide.

#### Code modifié — `inc/acme_import.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Importe le flux distant configuré dans acme/url_flux.
 *
 * @return int Nombre d'éléments importés
 */
function acme_importer(): int {
	include_spip('inc/distant');
	$url = lire_config('acme/url_flux', '');

	if (!$url) {
		spip_log('acme_importer : aucune URL de flux configurée (acme/url_flux)', 'acme.' . _LOG_ERREUR);
		return 0;
	}

	$res = recuperer_url($url);

	if (!$res) {
		// recuperer_url() renvoie false si le serveur distant est injoignable
		spip_log('acme_importer : flux distant injoignable : ' . $url, 'acme.' . _LOG_ERREUR);
		return 0;
	}

	if (empty($res['page'])) {
		$status = $res['status'] ?? '?';
		spip_log("acme_importer : réponse vide du flux (HTTP $status) : " . $url, 'acme.' . _LOG_ERREUR);
		return 0;
	}

	$items = json_decode($res['page'], true);
	if (!is_array($items)) {
		spip_log('acme_importer : flux non décodable en JSON (' . json_last_error_msg() . ') : ' . $url, 'acme.' . _LOG_ERREUR);
		return 0;
	}

	foreach ($items as $item) {
		sql_insertq('spip_acme_items', ['titre' => $item['titre'] ?? '']);
	}
	return count($items);
}
```

#### Lire le log

```bash
tail -f tmp/log/acme.log
```

Une ligne ressemble à :

```
2026-10-18 10:12:03 127.0.0.1 (pid 12345) :Pri: ERREUR: acme_importer : flux distant injoignable : https://exemple.org/flux.json
```

Remarques :

- Si l'URL du flux contient un jeton ou une clé d'API en paramètre, ne la loguez pas en clair (masquez la query string) : on ne met jamais de secrets dans les logs.
- `spip_log()` ne lève jamais d'exception ; si `tmp/log/` n'est pas inscriptible, rien n'est écrit. Vérifiez les droits en production.
- Un même processus PHP écrit au plus `_MAX_LOG` lignes (100 par défaut) par fichier : sans incidence ici.
- Le jour où le plugin passera à SPIP 5.0 (`compatibilite` qui commence à 5.0), l'équivalent sera `Spip::logger('acme')->error($msg)`, qui écrit toujours dans `tmp/log/acme.log`. Si le plugin vise à la fois 4.4 et 5.0, gardez `spip_log()`, la seule API disponible dans les deux.
