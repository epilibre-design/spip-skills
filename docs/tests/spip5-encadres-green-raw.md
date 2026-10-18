# SPIP 5.0 callouts — GREEN raw answers

Verbatim answers of the runs with the callout. Scoring: `spip5-encadres-green.md`.

# spip-logs — callout v1 (full level table and parameters)

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

---

# spip-logs — callout v2 (levels and parameters moved to the catalogue)

---

## spip-logs v2, cas 1 — plugin 5.0 seul

#### Version ciblée : SPIP 5.0

Avant d'écrire le log, j'ai vérifié la version visée par le plugin. Le `paquet.xml` d'acme déclare :

```xml
compatibilite="[5.0.0-dev;5.*]"
```

La compatibilité commence à 5.0 : le plugin vise **SPIP 5.0 uniquement**. Dans ce cas, on n'utilise pas `spip_log()`, qui est dépréciée en 5.0. On passe par le logger PSR-3 de SPIP 5 :

- `Spip::logger('acme')` écrit dans un fichier propre au plugin, `tmp/log/acme.log`.
- Pour une erreur, on appelle `->error($msg)`. C'est l'équivalent 5.0 de `spip_log($msg, 'acme.' . _LOG_ERREUR)`.
- N'utilisez pas `spip_logger()` : cette fonction est dépréciée en 5.0 et n'existe pas en 4.4.

#### Code modifié : `inc/acme_import.php`

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
		Spip::logger('acme')->error(
			'acme_importer : flux distant injoignable ou vide (url=' . $url
			. ', status=' . (is_array($res) ? ($res['status'] ?? '?') : 'échec') . ')'
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

L'entrée apparaîtra dans `tmp/log/acme.log`. Pour la suivre en direct :

```bash
tail -f tmp/log/acme.log
```

#### Remarques

- **Pas de réglage du seuil à prévoir.** En 5.0, `_LOG_FILTRE_GRAVITE` et les réglages de rotation de SPIP 4 n'ont aucun effet. La configuration passe par les paramètres `spip.logger.*`. Avec ces réglages, un `error()` est bien écrit.
- **Import de la classe.** La documentation dont je dispose donne l'appel sous la forme `Spip::logger('acme')` sans préciser le `use` ni l'espace de noms de la classe `Spip`. Vérifiez ce point dans la doc de migration SPIP 5 (`spip-migration-5`, `references/catalogue.md`) si l'appel n'est pas résolu dans votre fichier.
- **Si un jour vous rendez le plugin compatible 4.4 et 5.0** (par exemple `compatibilite="[4.4.0;5.*]"`), il faudra revenir à `spip_log($msg, 'acme.' . _LOG_ERREUR)`. C'est la seule API disponible dans les deux versions. Elle écrit aussi dans `tmp/log/acme.log`.
- Plus tard, si vous déplacez cette logique dans une classe, injectez-y le logger plutôt que d'appeler `Spip::logger()` (voir `spip5-plugins`, `references/services.md`).
- L'URL du flux est journalisée. Elle ne doit pas contenir de jeton ni d'identifiants. Si c'est le cas, retirez-la du message.

---

## spip-logs v2, cas 2 — plugin 4.4 + 5.0

#### Version ciblée

Votre `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"` : le plugin doit tourner **à la fois sous SPIP 4.4 et SPIP 5.x**. Dans ce cas, j'utilise `spip_log()`. C'est la seule API de log disponible dans les deux versions. En 5.0, `spip_log()` est déprécié au profit de `Spip::logger('acme')->error()`, mais cette méthode n'existe pas en 4.4. Je n'utilise pas non plus `spip_logger()`, qui est absent en 4.4 et déprécié en 5.0.

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
		$status = is_array($res) ? ($res['status'] ?? '?') : 'aucune réponse';
		spip_log(
			"acme_importer : flux distant injoignable ou vide (url=$url, status=$status)",
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

#### Explications

- **Fichier propre au plugin** : le préfixe `'acme'` dans le second argument envoie le message dans `tmp/log/acme.log`. Ce fichier tourne en `acme.log.1`, `.2`, etc. au-delà de 100 Ko, avec 4 anciens fichiers conservés par défaut. Le message est aussi recopié dans `spip.log`.
- **Gravité `_LOG_ERREUR` (3)** : il faut la préciser. Sans elle, un `spip_log($msg, 'acme')` est écrit en `_LOG_INFO` (6). Or le seuil par défaut `_LOG_FILTRE_GRAVITE` vaut `_LOG_INFO_IMPORTANTE` (5), donc ce message serait ignoré sans avertissement. Avec `'acme.' . _LOG_ERREUR`, l'erreur est écrite même sans modifier la configuration.
- **Contexte utile** : le message contient l'URL et le statut HTTP quand il est disponible, ce qui permet de distinguer un serveur hors ligne d'une réponse 404 ou 500. Le message ne contient aucune donnée sensible. Si l'URL du flux porte un jeton dans sa query string, retirez-le avant de la logger.

#### Vérifier

```bash
tail -f tmp/log/acme.log
```

Si rien n'apparaît, vérifiez que le dossier `tmp/log/` est accessible en écriture. Quand il ne l'est pas, `spip_log()` n'écrit rien et ne signale aucune erreur.

#### Quand le plugin passera en SPIP 5.0 uniquement

Si un jour `compatibilite` commence à 5.0 (abandon de la 4.4), remplacez l'appel par :

```php
Spip::logger('acme')->error("acme_importer : flux distant injoignable ou vide (url=$url, status=$status)");
```

Le message ira toujours dans `tmp/log/acme.log`.

---

## spip-logs v2, cas 3 — plugin 4.x

**Version ciblée.** Le `paquet.xml` d'acme déclare `compatibilite="[4.2.0;4.4.*]"` : le plugin vise SPIP 4.x, pas 5.0. J'utilise donc `spip_log()`, l'API de log de SPIP 4.x. (`Spip::logger()` n'existe qu'en 5.0, et `spip_logger()` est absent en 4.4.)

**Ce qui change :**

- **Un fichier de log propre au plugin.** Le premier argument de `$name` est `'acme'`, donc les messages vont dans `tmp/log/acme.log`, avec rotation automatique en `acme.log.1`, `.2`, etc.
- **Une gravité qui s'écrit sans réglage.** J'utilise `_LOG_ERREUR` (3). Le seuil par défaut (`_LOG_FILTRE_GRAVITE` = `_LOG_INFO_IMPORTANTE`, 5) laisse passer ce niveau, alors qu'un simple `spip_log($msg, 'acme')` (gravité `_LOG_INFO`, 6) serait ignoré sans le moindre message.
- **Deux cas d'échec distincts :**
  - le flux est injoignable : `recuperer_url()` renvoie un résultat vide ou une page vide ;
  - le flux répond, mais avec un JSON invalide.
- **Le cas où l'URL n'est pas configurée** est consigné aussi : sinon, l'appel échouerait en silence.

##### `inc/acme_import.php` modifié

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
	if (!$res || empty($res['page'])) {
		$status = (is_array($res) && isset($res['status'])) ? $res['status'] : 'aucune réponse';
		spip_log("acme_importer : flux distant injoignable ou vide ($url) — statut : $status", 'acme.' . _LOG_ERREUR);
		return 0;
	}

	$items = json_decode($res['page'], true);
	if (!is_array($items)) {
		spip_log("acme_importer : réponse JSON invalide depuis $url — " . json_last_error_msg(), 'acme.' . _LOG_ERREUR);
		return 0;
	}

	foreach ($items as $item) {
		sql_insertq('spip_acme_items', ['titre' => $item['titre'] ?? '']);
	}
	return count($items);
}
```

##### Pour lire le log

```bash
tail -f tmp/log/acme.log
```

Une ligne ressemble à ceci :
`2026-10-18 10:12:03 1.2.3.4 (pid 12345) :Pri: ERREUR: acme_importer : flux distant injoignable ou vide (https://…) — statut : aucune réponse`

**À noter :**

- Chaque message de `acme.log` est aussi copié dans `tmp/log/spip.log`.
- `spip_log()` ne lève jamais d'exception. Si le répertoire `tmp/log/` n'est pas accessible en écriture, il n'écrit rien. Vérifiez les droits en production.
- Ne mettez dans ce log aucun jeton ni identifiant. Si l'URL du flux contient une clé d'API, masquez-la avant de la logger.
- Si le plugin devient un jour compatible avec SPIP 5.0 (`compatibilite` jusqu'à `5.*`), gardez `spip_log()`, seule API commune à 4.4 et 5.0. Pour du code réservé à 5.0, l'équivalent serait `Spip::logger('acme')->error($msg)`.

---

## spip-logs v2, cas 4 — site avec `bin/spip`

#### Version de SPIP : 5.0

Avant d'écrire le log, j'ai vérifié la version, parce que l'API de log a changé en 5.0. Votre site a un fichier **`bin/spip`** (la console SPIP 5, qui démarre `SpipLeague\Component\Kernel\Kernel`). Sa présence suffit à dire que le site est en **SPIP 5.0**.

Conséquences en 5.0 :
- `spip_log()` est **déprécié**. On utilise `Spip::logger('canal')`, une API au format PSR-3.
- `spip_logger()` est aussi déprécié en 5.0 (et absent en 4.4) : à ne pas utiliser.
- Les gravités deviennent des méthodes. `_LOG_ERREUR` devient `->error()`.
- `Spip::logger('meteo')` écrit dans son propre fichier, **`tmp/log/meteo.log`**. C'est le fichier de log dédié demandé.

#### Code modifié — `squelettes/mes_fonctions.php`

```php
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
	$url = 'https://meteo.example.org/api?ville=' . rawurlencode($ville);
	$res = recuperer_url($url);
	if (!$res || empty($res['page'])) {
		// API injoignable ou réponse vide : on laisse une trace dans tmp/log/meteo.log
		Spip::logger('meteo')->error(
			'API météo injoignable ou réponse vide pour la ville « ' . $ville . ' » (' . $url . ')'
			. (isset($res['status']) ? ' — statut HTTP ' . $res['status'] : '')
		);
		return '';
	}
	$data = json_decode($res['page'], true);
	return isset($data['temp']) ? $data['temp'] . ' °C' : '';
}
```

Le comportement ne change pas : le filtre renvoie toujours une chaîne vide en cas d'échec, donc la page s'affiche sans température. Mais chaque échec est maintenant enregistré comme erreur dans **`tmp/log/meteo.log`**.

#### À savoir

- **Rien à configurer pour la gravité.** En 5.0, `_LOG_FILTRE_GRAVITE` et les réglages de rotation de SPIP 4 n'ont **aucun effet**. Ne les ajoutez pas dans `mes_options.php` en pensant faire apparaître le message. Le niveau et la rotation se règlent avec les paramètres `spip.logger.*` de SPIP 5.
- **Classe `Spip` et namespace :** ma documentation donne l'appel `Spip::logger('meteo')->error($msg)` sans préciser de namespace ni de `use`. Si PHP répond « Class "Spip" not found », ajoutez en tête du fichier l'import qui correspond à votre installation.
- **Pour vérifier :** coupez l'accès à l'API, ou mettez temporairement une URL invalide, puis affichez une page qui utilise le filtre. Ensuite lisez `tmp/log/meteo.log`, par exemple avec `tail -f tmp/log/meteo.log`.
- **Compatibilité 4.x :** ce code est fait pour SPIP 5.0 uniquement. Si un jour il doit tourner aussi sur un SPIP 4.4, il faudra revenir à `spip_log($msg, 'meteo.' . _LOG_ERREUR)`, la seule API présente dans les deux versions.

---

# spip-logs — callout v3 (global `Spip` alias)

---

## spip-logs v3, cas 1 — plugin 5.0 seul

#### Version ciblée

Le `paquet.xml` d'acme déclare `compatibilite="[5.0.0-dev;5.*]"` : le plugin cible **SPIP 5.0 uniquement**. C'est ce marqueur qui détermine l'API à employer :

- en 5.0, `spip_log()` est déprécié ; on passe par le logger PSR-3 : `Spip::logger('acme')->error($msg)` ;
- le canal `'acme'` écrit dans un fichier propre au plugin, `tmp/log/acme.log` ;
- `Spip` est un alias global de `Spip\Framework\Spip` : aucun `use` n'est nécessaire dans ce fichier de fonctions ;
- ne pas utiliser `spip_logger()` (déprécié en 5.0).

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
		Spip::logger('acme')->error(sprintf(
			'acme_importer : flux distant injoignable ou vide (url=%s, status=%s)',
			$url !== '' ? $url : '(non configurée)',
			$res['status'] ?? 'aucune réponse'
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

#### Notes

- Le message part au niveau `error()` (équivalent de l'ancienne gravité `_LOG_ERREUR`) : il est donc bien écrit, sans réglage particulier.
- Les constantes de seuil 4.x (`_LOG_FILTRE_GRAVITE`, etc.) n'ont **aucun effet** en 5.0 : inutile de les toucher dans `mes_options.php`.
- Pour vérifier : couper l'accès au flux (ou mettre une URL bidon dans `acme/url_flux`), lancer l'import, puis `tail -f tmp/log/acme.log`.
- Si un jour vous élargissez la compatibilité pour supporter aussi SPIP 4.4 (`compatibilite="[4.4.0;5.*]"`), il faudra revenir à `spip_log($msg, 'acme.' . _LOG_ERREUR)`, seule API commune aux deux versions (même fichier `tmp/log/acme.log`).
- Si la logique d'import migre plus tard dans une classe, injectez-y le logger plutôt que d'appeler `Spip::logger()`.

---

## spip-logs v3, cas 4 — site avec `bin/spip`

#### Version de SPIP : 5.0

Votre site contient un fichier `bin/spip` (la console SPIP, qui démarre via `SpipLeague\Component\Kernel\Kernel`). C'est ce fichier qui m'indique un SPIP 5.0. J'ai donc utilisé l'API de log de SPIP 5.0 plutôt que `spip_log()`, qui est dépréciée dans cette version :

- `Spip::logger('meteo')` renvoie un logger PSR-3 qui écrit dans le fichier dédié **`tmp/log/meteo.log`**.
- `->error(...)` correspond à l'ancienne gravité `_LOG_ERREUR`.
- `Spip` est un alias global de `Spip\Framework\Spip`, donc aucun `use` n'est nécessaire dans `mes_fonctions.php`.
- N'utilisez pas `spip_logger()`, qui est dépréciée en 5.0.

#### Code modifié : `squelettes/mes_fonctions.php`

```php
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
	$url = 'https://meteo.example.org/api?ville=' . rawurlencode($ville);
	$res = recuperer_url($url);
	if (!$res || empty($res['page'])) {
		Spip::logger('meteo')->error(
			'API météo injoignable ou réponse vide pour la ville "' . $ville . '"'
			. ' (URL : ' . $url
			. ', statut HTTP : ' . ($res['status'] ?? 'aucun') . ')'
		);
		return '';
	}
	$data = json_decode($res['page'], true);
	return isset($data['temp']) ? $data['temp'] . ' °C' : '';
}
```

#### Remarques

- Le comportement affiché ne change pas : le filtre renvoie toujours une chaîne vide. La différence est que chaque échec est maintenant enregistré dans `tmp/log/meteo.log`.
- En SPIP 5.0, `_LOG_FILTRE_GRAVITE` et les réglages de rotation de SPIP 4.x n'ont **aucun effet**. Avec les réglages par défaut, un message de niveau `error()` est bien écrit, donc vous n'avez rien à configurer.
- Pour suivre les erreurs en direct : `tail -f tmp/log/meteo.log`.
- Si vous voulez aussi tracer un JSON reçu mais sans clé `temp`, vous pouvez ajouter un `Spip::logger('meteo')->warning(...)` dans ce cas. C'est l'équivalent de l'ancien `_LOG_AVERTISSEMENT`.

---

# spip-formulaires

---

## spip-formulaires, cas 5 — site avec `ecrire/src/SpipVersion.php`

#### Contrôle de l'email dans `verifier()`

**Version du site : SPIP 5.0.** `ecrire/src/SpipVersion.php` contient `VERSION = '5.0.0-beta'`. En 5.0, `_request()` est déprécié, donc on lit la saisie avec `Spip::input('email')`. `Spip` est un alias global de `Spip\Framework\Spip`, il n'y a pas de `use` à ajouter.

Pour une vérification aussi simple, inutile d'ajouter le plugin Verifier : le CVT de base suffit. `verifier()` renvoie un tableau d'erreurs indexé par nom de champ. La clé `email` affiche donc le message sur ce champ, et SPIP génère tout seul le bandeau global `message_erreur`. Tant que ce tableau n'est pas vide, `traiter()` n'est pas appelé et rien n'est inséré en base.

##### `squelettes/formulaires/inscription_lettre.php` (modifié)

```php
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
	$erreurs = [];

	$email = trim((string) Spip::input('email'));
	if ($email === '') {
		$erreurs['email'] = _T('info_obligatoire');
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$erreurs['email'] = _T('inscription_lettre:erreur_email_invalide');
	} else {
		// on stocke la valeur nettoyée (sans espaces) pour traiter()
		Spip::setInput('email', $email);
	}

	return $erreurs;
}

function formulaires_inscription_lettre_traiter_dist() {
	sql_insertq('spip_inscrits', [
		'nom' => Spip::input('nom'),
		'email' => Spip::input('email'),
	]);

	return ['message_ok' => _T('inscription_lettre:merci')];
}
```

Ce qui change :
- **`verifier()`** : si l'email est vide, l'erreur est « obligatoire » (chaîne du noyau `info_obligatoire`). Si le format est invalide, c'est un message dédié. Si l'email est valide, on remet la valeur sans espaces avec `Spip::setInput()`, pour que `traiter()` enregistre une adresse propre.
- **`traiter()`** : `_request()` est remplacé par `Spip::input()`, puisque `_request()` est déprécié en SPIP 5.0. Si ce code doit aussi tourner sur SPIP 4.4, gardez `_request()` / `set_request()` partout, car ce sont les seules fonctions disponibles dans les deux versions.

##### Chaîne de langue à ajouter

Le formulaire utilise déjà le module `inscription_lettre` (`inscription_lettre:merci`). Ajoutez la clé suivante dans `lang/inscription_lettre_fr.php`, à côté de `merci` :

```php
'erreur_email_invalide' => 'Cette adresse email n’est pas valide.',
```

##### Le squelette (aucun changement obligatoire)

Le message s'affiche déjà sur le champ : le squelette a un `<span class="erreur_message">` sur `email` et ajoute la classe `erreur` au bloc `.editer_email`. Vous pouvez quand même le simplifier et le sécuriser plus tard :
- `[(#ENV*{erreurs/email})]` remplace `#ENV**{erreurs}|table_valeur{email}` (une seule `*`, jamais `**`) ;
- `#ENV*{message_ok}` remplace `#ENV**{message_ok}` ;
- `role="status"` / `role="alert"` à ajouter sur les bandeaux ;
- `#ENV{editable}` à la place de `#EDITABLE`.

Le bloc email corrigé ressemblerait à ceci :

```html
<div class="editer editer_email obligatoire[ (#ENV*{erreurs/email}|oui)erreur]">
	<label for="email">Email</label>
	[<span class="erreur_message">(#ENV*{erreurs/email})</span>]
	<input type="email" class="text" name="email" id="email" value="#ENV{email}" />
</div>
```

À noter : `type="email"` dans le HTML ne protège pas le serveur, car un envoi forgé passe outre. C'est le contrôle dans `verifier()` qui compte.

---

## spip-formulaires, cas 6 — plugin 4.4 + 5.0

#### Version SPIP visée

Dans `paquet.xml`, `compatibilite="[4.4.0;5.*]"` : le plugin vise **à la fois SPIP 4.4 et SPIP 5.0**. Dans ce cas, je garde `_request()`. C'est la seule API de lecture des requêtes disponible dans les deux versions. `Spip::input()` n'existe qu'en 5.0 et ferait planter le plugin sous 4.4.

#### `formulaires/inscription_lettre.php` modifié

Seule `verifier()` change. `charger()` et `traiter()` restent identiques.

```php
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
	$erreurs = [];

	$email = trim((string) _request('email'));
	if ($email === '') {
		$erreurs['email'] = _T('info_obligatoire');
	} elseif (!email_valide($email)) {
		$erreurs['email'] = _T('info_email_invalide');
	}

	return $erreurs;
}

function formulaires_inscription_lettre_traiter_dist() {
	sql_insertq('spip_inscrits', [
		'nom' => _request('nom'),
		'email' => _request('email'),
	]);

	return ['message_ok' => _T('inscription_lettre:merci')];
}
```

Fonctionnement :

- `verifier()` renvoie un tableau d'erreurs indexé par nom de champ. La clé `email` affiche donc le message **sur le champ email**. Tant que ce tableau n'est pas vide, `traiter()` ne s'exécute pas : rien n'est inséré dans `spip_inscrits`.
- Je n'ai pas défini `message_erreur`. SPIP génère alors automatiquement le bandeau global d'erreur.
- `email_valide()` est la fonction de validation d'adresse du core SPIP. Elle n'est pas documentée dans la doc dont je dispose : vérifiez qu'elle est bien chargée dans votre contexte.
- Les chaînes `info_obligatoire` (« Cette information est obligatoire ») et `info_email_invalide` viennent du core. Comme pour `email_valide()`, je ne peux pas confirmer `info_email_invalide` avec ma doc : vérifiez qu'elle existe. Si vous voulez vos propres libellés, ajoutez par exemple `email_obligatoire` et `email_invalide` dans `lang/inscription_lettre_fr.php` et utilisez `_T('inscription_lettre:email_invalide')`.
- Le plugin Verifier n'est pas nécessaire pour un contrôle aussi simple. S'il est déjà installé, vous pouvez remplacer le `elseif` par `$verifier = charger_fonction('verifier', 'inc/'); if ($msg = $verifier($email, 'email', ['mode' => 'strict'])) { $erreurs['email'] = $msg; }`.

#### Corrections conseillées dans le squelette (facultatives)

Le message s'affiche déjà avec le squelette actuel. Mais `formulaires/inscription_lettre.html` utilise `#ENV**{erreurs}|table_valeur{email}` : la double étoile désactive la sécurisation du texte. La forme canonique est la suivante :

```html
<div class="editer editer_email obligatoire[ (#ENV*{erreurs/email}|oui)erreur]">
	<label for="email">Email</label>
	[<span class="erreur_message">(#ENV*{erreurs/email})</span>]
	<input type="email" class="text" name="email" id="email" value="#ENV{email}" />
</div>
```

Faites la même chose pour le champ `nom` (`#ENV*{erreurs/nom}`). Deux autres corrections sont recommandées :

- remplacer `#ENV**{message_ok}` par `#ENV*{message_ok}` ;
- ajouter `role="status"` au bandeau `message_ok` et `role="alert"` au bandeau `message_erreur`.

---

## spip-formulaires, cas 7 — plugin 4.x

**Version visée** : le `paquet.xml` du plugin déclare `compatibilite="[4.2.0;4.4.*]"`, donc SPIP 4.x uniquement. On garde `_request()`, qui est l'API de lecture de la requête en SPIP 4 (`Spip::input()` ne concerne que SPIP 5.0).

#### `formulaires/inscription_lettre.php` modifié

Seule `verifier()` change. `charger()` et `traiter()` restent identiques.

```php
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
	$erreurs = [];

	$email = trim((string) _request('email'));

	if ($email === '') {
		// champ obligatoire
		$erreurs['email'] = _T('info_obligatoire');
	} else {
		// format d'adresse
		include_spip('inc/filtres');
		if (!email_valide($email)) {
			$erreurs['email'] = _T('info_email_invalide');
		}
	}

	return $erreurs;
}

function formulaires_inscription_lettre_traiter_dist() {
	sql_insertq('spip_inscrits', [
		'nom' => _request('nom'),
		'email' => _request('email'),
	]);

	return ['message_ok' => _T('inscription_lettre:merci')];
}
```

Ce qui se passe :

- `verifier()` renvoie un tableau d'erreurs indexé par nom de champ. La clé `email` affiche le message **sous le champ email**. Si le tableau n'est pas vide, `traiter()` n'est pas appelé et rien n'est inséré dans `spip_inscrits`. SPIP ajoute aussi un message global d'erreur en haut du formulaire.
- `verifier()` s'exécute avant `charger()`, au moment du POST. Il ne dispose donc que de `_request()`, et c'est bien ce qu'on utilise.
- `email_valide()` est la fonction du core SPIP qui valide une adresse (déclarée dans `inc/filtres`, d'où l'`include_spip`). Un contrôle aussi simple ne justifie pas d'ajouter une dépendance au plugin Vérifier.
- `info_obligatoire` et `info_email_invalide` sont des chaînes de langue du core. Pour un texte propre au plugin, créez par exemple `inscription_lettre:erreur_email_invalide` dans votre fichier de langue.
- Le `trim()` évite qu'une saisie faite seulement d'espaces passe pour une adresse remplie. Si vous voulez enregistrer la version nettoyée, appliquez aussi `trim()` dans `traiter()`.

#### À corriger aussi dans le squelette (recommandé)

Le squelette `formulaires/inscription_lettre.html` affiche déjà l'erreur du champ email, donc le message apparaîtra sans rien changer. En revanche, il utilise `#ENV**{erreurs}|table_valeur{email}` (double étoile). La forme canonique est `#ENV*{erreurs/email}`, avec une seule étoile et jamais `**`. Il est aussi conseillé d'utiliser `#ENV*` plutôt que `#ENV**` pour `message_ok` et d'ajouter `role="status"` / `role="alert"` aux messages globaux :

```html
	[<div class="reponse_formulaire reponse_formulaire_ok" role="status">(#ENV*{message_ok})</div>]
	[<div class="reponse_formulaire reponse_formulaire_erreur" role="alert">(#ENV*{message_erreur})</div>]
...
			<div class="editer editer_email obligatoire[ (#ENV*{erreurs/email}|oui)erreur]">
				<label for="email">Email</label>
				[<span class="erreur_message">(#ENV*{erreurs/email})</span>]
				<input type="email" class="text" name="email" id="email" value="#ENV{email}" required />
			</div>
```

(Faites la même chose pour le bloc `nom` avec `#ENV*{erreurs/nom}`.) Les attributs HTML `type="email"` et `required` aident l'utilisateur, mais ils se contournent facilement. Le contrôle qui protège vraiment l'inscription est celui de `verifier()`.
