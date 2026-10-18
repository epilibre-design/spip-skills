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

---

# spip-formulaires — callout v2 (AJAX charset difference)

---

## spip-formulaires v2, cas 5 — site avec `ecrire/src/SpipVersion.php`

#### Contrôle de l'email dans `verifier()`

**Version du site : SPIP 5.0.** `ecrire/src/SpipVersion.php` déclare `VERSION = '5.0.0-beta'`. Le code ci-dessous utilise donc `Spip::input()` au lieu de `_request()`, qui est dépréciée en 5.0. `Spip` est un alias global, il n'y a pas de `use` à ajouter.

Fichier `squelettes/formulaires/inscription_lettre.php` modifié :

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
	} else {
		include_spip('inc/filtres');
		if (!email_valide($email)) {
			$erreurs['email'] = _T('form_email_non_valide');
		}
	}

	return $erreurs;
}

function formulaires_inscription_lettre_traiter_dist() {
	sql_insertq('spip_inscrits', [
		'nom' => Spip::input('nom'),
		'email' => trim((string) Spip::input('email')),
	]);

	return ['message_ok' => _T('inscription_lettre:merci')];
}
```

##### Fonctionnement

- `verifier()` est appelée seulement quand le formulaire est envoyé, avant `traiter()`. Elle renvoie un tableau d'erreurs indexé par nom de champ. Tant que la clé `email` y figure, `traiter()` ne s'exécute pas : rien n'est inséré dans `spip_inscrits`. SPIP affiche alors le formulaire avec les valeurs saisies et ajoute un message d'erreur général calculé à partir du nombre d'erreurs.
- Le message se place sur le champ grâce à la clé `email`. Le squelette l'affiche déjà dans `<span class="erreur_message">` et ajoute la classe `erreur` sur `.editer_email`.
- Les deux contrôles :
  - Champ vide : erreur avec `_T('info_obligatoire')`. On applique `trim()` avant, pour qu'un champ qui ne contient que des espaces compte comme vide.
  - Adresse renseignée : on la valide avec `email_valide()`, une fonction du noyau définie dans `inc/filtres`. En cas d'échec : `_T('form_email_non_valide')`.
- Les deux messages viennent des chaînes de langue du noyau. Pour un texte propre au formulaire, créez `inscription_lettre:erreur_email_invalide` dans votre fichier de langue `inscription_lettre`, que le formulaire utilise déjà pour `merci`.
- `traiter()` passe aussi à `Spip::input()` et enregistre l'email après `trim()`, pour stocker exactement la valeur qui a été vérifiée.
- `type="email"` dans le HTML ne protège de rien côté serveur : un client peut l'ignorer. C'est pourquoi le contrôle se fait dans `verifier()`.
- Le plugin Verifier n'est pas installé sur le site. Pour un contrôle aussi simple, le CVT seul suffit.

##### Squelette `inscription_lettre.html` (facultatif, mais recommandé)

L'affichage des erreurs fonctionne déjà. Le squelette ne suit pourtant pas tout à fait les conventions. Voici les deux blocs à remplacer :

```html
	[<div class="reponse_formulaire reponse_formulaire_ok" role="status">(#ENV*{message_ok})</div>]
	[<div class="reponse_formulaire reponse_formulaire_erreur" role="alert">(#ENV*{message_erreur})</div>]
```

```html
			<div class="editer editer_email obligatoire[ (#ENV*{erreurs/email}|oui)erreur]">
				<label for="email">Email</label>
				[<span class="erreur_message">(#ENV*{erreurs/email})</span>]
				<input type="email" class="text" name="email" id="email" value="[(#ENV{email})]" required />
			</div>
```

Pourquoi ces changements :
- Une seule `*` partout, jamais `**`. Cela vaut pour `message_ok` et pour les erreurs.
- La forme `#ENV*{erreurs/email}` remplace `|table_valeur{email}`.
- `role="status"` et `role="alert"` sont ajoutés sur les messages généraux.
- Le bloc du champ `nom` s'écrit de la même façon, avec `erreurs/nom`.

---

## spip-formulaires v2, cas 6 — plugin 4.4 + 5.0

#### Contrôle de l'email dans `verifier()`

**Version ciblée :** le `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"`. Le plugin doit donc tourner sur SPIP 4.4 **et** 5.0. Je garde `_request()`, la seule API disponible dans les deux versions. `Spip::input()` n'existe qu'à partir de 5.0 et casserait le plugin sous 4.4.

Le contrôle se fait en CVT simple, sans ajouter de dépendance au plugin Verifier. Le plugin n'en dépend pas, et deux tests aussi courts n'en justifient pas l'ajout.

##### `formulaires/inscription_lettre.php` (fonction modifiée)

```php
function formulaires_inscription_lettre_verifier_dist() {
	$erreurs = [];

	$email = trim((string) _request('email'));
	if ($email === '') {
		$erreurs['email'] = _T('info_obligatoire');
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$erreurs['email'] = _T('inscription_lettre:erreur_email_invalide');
	}

	return $erreurs;
}
```

- Le message est placé sous la clé `email`, celle du champ. SPIP l'affiche donc sur le champ et génère seul le bandeau d'erreur global.
- Si `$erreurs` n'est pas vide, `traiter()` n'est pas appelé et rien n'est inséré dans `spip_inscrits`.
- `info_obligatoire` est une chaîne du core. Pour le format invalide, ajoutez la clé dans le module de langue du plugin, celui que `traiter()` utilise déjà (`inscription_lettre:merci`) :

```php
// lang/inscription_lettre_fr.php
'erreur_email_invalide' => 'Cette adresse email n’est pas valide.',
```

Point facultatif : `verifier()` ne vérifie que l'email, mais `traiter()` relit `_request('email')` sans `trim()`. Pour enregistrer exactement la valeur contrôlée, faites aussi le `trim()` dans `traiter()` :

```php
'email' => trim((string) _request('email')),
```

##### Gabarit `formulaires/inscription_lettre.html` (corrections conseillées)

L'erreur s'affichera déjà avec le gabarit actuel. Il lit toutefois les erreurs et `message_ok` avec `#ENV**`, ce qui contourne la protection `interdire_scripts`. La forme canonique utilise une seule étoile et la notation `erreurs/champ`. Il faut aussi ajouter les rôles ARIA sur les messages globaux :

```html
<div class="formulaire_spip formulaire_#FORM">
	[<div class="reponse_formulaire reponse_formulaire_ok" role="status">(#ENV*{message_ok})</div>]
	[<div class="reponse_formulaire reponse_formulaire_erreur" role="alert">(#ENV*{message_erreur})</div>]
	[(#ENV{editable}|oui)
	<form method="post" action="#ENV{action}"><div>
		#ACTION_FORMULAIRE
		<div class="editer-groupe">
			<div class="editer editer_nom[ (#ENV*{erreurs/nom}|oui)erreur]">
				<label for="nom">Nom</label>
				[<span class="erreur_message">(#ENV*{erreurs/nom})</span>]
				<input type="text" class="text" name="nom" id="nom" value="#ENV{nom}" />
			</div>
			<div class="editer editer_email obligatoire[ (#ENV*{erreurs/email}|oui)erreur]">
				<label for="email">Email</label>
				[<span class="erreur_message">(#ENV*{erreurs/email})</span>]
				<input type="email" class="text" name="email" id="email" value="#ENV{email}" required />
			</div>
		</div>
		<p class="boutons"><input type="submit" class="btn submit" value="S'inscrire" /></p>
	</div></form>
	]
</div>
```

L'attribut `required` et `type="email"` n'apportent qu'un confort côté navigateur. Le vrai contrôle reste celui de `verifier()`, côté serveur.

---

# spip-squelettes

---

## spip-squelettes, cas 8 — site avec `vendor/spip/framework/`

#### Version de SPIP

Le site tourne sous **SPIP 5.0**. Je m'appuie sur la présence de `vendor/spip/framework/` (paquet `spip/framework`). J'en tiens compte dans le code ajouté et dans tout le squelette modifié :

- `{fusion}` est déprécié en 5.0. On utilise `{groupby}` à la place.
- `<INCLURE{…}></INCLURE>` est supprimé en 5.0. On écrit `<INCLURE{…} />` (les deux lignes `inclure/head` et `inclure/pied` sont corrigées).
- Le pseudo-filtre de logo `[(#LOGO_MOT|right)]` est supprimé en 5.0. On écrit `[(#LOGO_MOT{right})]`.

#### Principe

Une boucle `RUBRIQUES{id_mot}` ne convient pas ici. Elle donnerait les rubriques auxquelles le mot est lié directement, pas celles qui contiennent des articles liés au mot.

On part donc des **articles** liés au mot (`ARTICLES{id_mot}`) et on les regroupe par rubrique avec `{groupby id_rubrique}`. On obtient ainsi une seule ligne par rubrique. Une boucle `RUBRIQUES{id_rubrique}` imbriquée affiche ensuite le titre et l'URL de cette rubrique.

Comme toute boucle ARTICLES, celle-ci ne prend que les articles publiés. Une rubrique qui ne contient que des brouillons liés au mot n'apparaît donc pas, ce qui est le comportement voulu sur le site public.

#### Squelette `squelettes/mot.html` modifié

```html
<BOUCLE_mot(MOTS){id_mot}>
<!DOCTYPE html>
<html lang="#LANG">
<head>
	<title>[(#TITRE|textebrut)] - [(#NOM_SITE_SPIP|textebrut)]</title>
	<INCLURE{fond=inclure/head}{env} />
</head>
<body class="page_mot">
	<h1>[(#LOGO_MOT{right})]#TITRE</h1>
	[<div class="descriptif">(#DESCRIPTIF)</div>]

	<B_articles>
	<ul class="liste-articles">
	<BOUCLE_articles(ARTICLES){id_mot}{par date}{inverse}>
		<li><a href="#URL_ARTICLE">#TITRE</a></li>
	</BOUCLE_articles>
	</ul>
	</B_articles>

	<B_rubriques_mot>
	<h2>Rubriques concernées</h2>
	<ul class="liste-rubriques">
	<BOUCLE_rubriques_mot(ARTICLES){id_mot}{groupby id_rubrique}>
		<BOUCLE_rub(RUBRIQUES){id_rubrique}>
		<li><a href="#URL_RUBRIQUE">#TITRE</a></li>
		</BOUCLE_rub>
	</BOUCLE_rubriques_mot>
	</ul>
	</B_rubriques_mot>

	<INCLURE{fond=inclure/pied}{env} />
</body>
</html>
</BOUCLE_mot>
```

#### Remarques

- `{id_mot}` vient de la boucle englobante `_mot`. `{id_rubrique}` dans `_rub` vient de la ligne en cours de `_rubriques_mot`, c'est-à-dire de la rubrique de l'article.
- La partie conditionnelle `<B_rubriques_mot>…</B_rubriques_mot>` n'affiche le titre et la liste que s'il y a au moins un résultat.
- **Attention si le squelette doit aussi tourner sous SPIP 4.4** : `{groupby}` n'y existe pas. Il faudrait alors garder `{fusion id_rubrique}`, qui fonctionne encore en 5.0 mais y est déprécié. Les nouvelles formes de `INCLURE` et de logo fonctionnent déjà en 4.4.
- La liste des rubriques suit l'ordre des articles regroupés. Pour un tri alphabétique strict par titre de rubrique, il faudrait une autre construction. Dites-le-moi si c'est nécessaire.

---

## spip-squelettes, cas 9 — plugin 4.4 + 5.0

#### Version ciblée

Le `paquet.xml` du plugin déclare `compatibilite="[4.4.0;5.*]"` : le squelette doit tourner **à la fois sur SPIP 4.4 et sur SPIP 5.0**. Ça a deux conséquences :

1. Pour dédoublonner, j'utilise le critère `{fusion}`. En 5.0 il est déprécié au profit de `{groupby}`, mais `{groupby}` n'existe pas en 4.4 ; un squelette 4.4 + 5.0 doit donc garder `{fusion}` (qui fonctionne encore en 5.0).
2. Deux syntaxes déjà présentes dans `mot.html` sont **supprimées en 5.0**, je les corrige au passage (les nouvelles formes marchent déjà en 4.4) :
   - `<INCLURE{…}></INCLURE>` → `<INCLURE{…} />`
   - `[(#LOGO_MOT|right)]` → `[(#LOGO_MOT{right})]`

#### Principe

On part des articles liés au mot (`ARTICLES{id_mot}`, publiés par défaut), on les regroupe par rubrique avec `{fusion id_rubrique}` : chaque rubrique n'apparaît donc qu'une fois. Une boucle `RUBRIQUES{id_rubrique}` imbriquée récupère ensuite le titre et l'URL de la rubrique.

#### Squelette modifié (`mot.html`)

```html
<BOUCLE_mot(MOTS){id_mot}>
<!DOCTYPE html>
<html lang="#LANG">
<head>
	<title>[(#TITRE|textebrut)] - [(#NOM_SITE_SPIP|textebrut)]</title>
	<INCLURE{fond=inclure/head}{env} />
</head>
<body class="page_mot">
	<h1>[(#LOGO_MOT{right})]#TITRE</h1>
	[<div class="descriptif">(#DESCRIPTIF)</div>]

	<B_articles>
	<ul class="liste-articles">
	<BOUCLE_articles(ARTICLES){id_mot}{par date}{inverse}>
		<li><a href="#URL_ARTICLE">#TITRE</a></li>
	</BOUCLE_articles>
	</ul>
	</B_articles>

	<B_rubriques_mot>
	<h2>Rubriques</h2>
	<ul class="liste-rubriques">
	<BOUCLE_rubriques_mot(ARTICLES){id_mot}{fusion id_rubrique}>
		<BOUCLE_rubrique_mot(RUBRIQUES){id_rubrique}>
		<li><a href="#URL_RUBRIQUE">#TITRE</a></li>
		</BOUCLE_rubrique_mot>
	</BOUCLE_rubriques_mot>
	</ul>
	</B_rubriques_mot>

	<INCLURE{fond=inclure/pied}{env} />
</body>
</html>
</BOUCLE_mot>
```

#### Remarques

- `{id_mot}` est hérité de la boucle `_mot` englobante ; seuls les articles publiés sont pris en compte (statut par défaut de la boucle ARTICLES), donc une rubrique n'apparaît que si elle contient au moins un article **publié** lié au mot.
- Le bloc `<B_rubriques_mot>…</B_rubriques_mot>` (titre `<h2>` et `<ul>`) ne s'affiche que s'il y a au moins une rubrique.
- L'ordre des rubriques suit celui des articles regroupés (pas d'ordre alphabétique garanti sur le titre de rubrique, puisque le tri porte sur la table ARTICLES).
- Le jour où le plugin abandonne SPIP 4.4 (compatibilité à partir de 5.0), remplacer `{fusion id_rubrique}` par `{groupby id_rubrique}`.
- Le libellé « Rubriques » est en dur ; idéalement le passer dans un fichier de langue du plugin (`<:acme:rubriques_du_mot:>`).

---

## spip-squelettes, cas 10 — plugin 4.x

Le plugin cible SPIP 4.x : le `paquet.xml` déclare `compatibilite="[4.2.0;4.4.*]"`. J'ai donc utilisé la syntaxe 4.x.

#### Principe

- Une boucle `ARTICLES{id_mot}` récupère les articles liés au mot-clé. Elle hérite de `id_mot` grâce à la boucle `_mot` qui l'englobe.
- Le critère `{fusion id_rubrique}` regroupe ces articles par rubrique (un `GROUP BY` SQL). On obtient **une ligne par rubrique**, même si la rubrique contient plusieurs articles avec ce mot.
- Une boucle `RUBRIQUES{id_rubrique}` imbriquée affiche ensuite le titre et le lien de la rubrique de chaque ligne.

Comme dans toutes les boucles SPIP publiques, seuls les articles et rubriques **publiés** sont pris en compte (statut par défaut). Les parties `<B_rubriques_mot>` / `</B_rubriques_mot>` font que le bloc n'est pas affiché si aucune rubrique ne correspond.

#### Squelette `mot.html` modifié

```html
<BOUCLE_mot(MOTS){id_mot}>
<!DOCTYPE html>
<html lang="#LANG">
<head>
	<title>[(#TITRE|textebrut)] - [(#NOM_SITE_SPIP|textebrut)]</title>
	<INCLURE{fond=inclure/head}{env}></INCLURE>
</head>
<body class="page_mot">
	<h1>[(#LOGO_MOT|right)]#TITRE</h1>
	[<div class="descriptif">(#DESCRIPTIF)</div>]

	<B_articles>
	<ul class="liste-articles">
	<BOUCLE_articles(ARTICLES){id_mot}{par date}{inverse}>
		<li><a href="#URL_ARTICLE">#TITRE</a></li>
	</BOUCLE_articles>
	</ul>
	</B_articles>

	<B_rubriques_mot>
	<h2>Rubriques concernées</h2>
	<ul class="liste-rubriques">
	<BOUCLE_rubriques_mot(ARTICLES){id_mot}{fusion id_rubrique}>
		<BOUCLE_rub(RUBRIQUES){id_rubrique}>
		<li><a href="#URL_RUBRIQUE">#TITRE</a></li>
		</BOUCLE_rub>
	</BOUCLE_rubriques_mot>
	</ul>
	</B_rubriques_mot>

	<INCLURE{fond=inclure/pied}{env}></INCLURE>
</body>
</html>
</BOUCLE_mot>
```

#### Remarques

- **Ordre d'affichage** : les rubriques sortent dans l'ordre du regroupement SQL, pas dans l'ordre alphabétique. Le tri `{par titre}` s'applique ici aux titres des *articles* et ne trie pas les rubriques. Si l'ordre alphabétique des rubriques compte, il faut une autre boucle (par exemple parcourir `RUBRIQUES{par titre}` et ne garder une rubrique que si elle contient un article lié au mot). Cette solution est plus coûteuse sur un gros site.
- **Libellé** : le texte « Rubriques concernées » est écrit en dur. Si le site est multilingue, remplacez-le par une chaîne de langue du plugin, par exemple `<:acme:rubriques_concernees:>`, à déclarer dans `lang/acme_fr.php`.
- **SPIP 5.0** : le plugin vise 4.2 à 4.4, donc j'ai gardé `{fusion}` (`{groupby}` n'existe pas en 4.4) ainsi que la syntaxe actuelle du fichier : `<INCLURE{…}></INCLURE>` et `|right`. Si le plugin passe un jour à SPIP 5.0, il faudra remplacer :
  - `{fusion id_rubrique}` par `{groupby id_rubrique}` ;
  - `<INCLURE{…}></INCLURE>` par `<INCLURE{…} />` ;
  - `[(#LOGO_MOT|right)]` par `[(#LOGO_MOT{right})]`.

  Les deux dernières formes fonctionnent déjà en 4.4. On peut donc les changer dès maintenant si on le souhaite.

---

# spip-lang — premier passage, projets sans fichiers de langue (remplacé)

---

## spip-lang, cas 11 — site avec `plugins-dist/spip/`

#### Version de SPIP

Le site tourne sous **SPIP 5.0**. Je me suis fié à ce repère : les plugins sont rangés dans `plugins-dist/spip/` (par exemple `plugins-dist/spip/mots/paquet.xml`, en `version="5.0.0-dev"` avec `compatibilite="[5.0.0-dev;5.*]"`). Ça a deux effets sur le code :

1. **Pour obtenir une chaîne en anglais dans un seul appel**, SPIP 5.0 utilise l'option `lang` dans le 3e argument de `_T()` : `_T($cle, [], ['lang' => 'en'])`. L'ancienne forme `['spip_lang' => 'en']` dans les arguments est dépréciée. Le site n'a pas besoin de rester compatible avec la 4.4, l'option `lang` convient donc. (En 4.4, cette option serait ignorée sans erreur. Il faudrait alors passer par `lang_select('en')` … `lang_select()`.)
2. **Le corps contenait un bug bloquant.** `_T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article')` passe une chaîne en 3e argument. En SPIP 5.0, ce paramètre est typé `array`, et cet appel déclenche une `TypeError` : aucun email ne part. Le texte du corps ne change pas, mais il faut corriger l'appel sous la forme `['class' => 'titre_article']`. Cette forme est déjà valide en 4.4.

#### Code modifié (`squelettes/mes_fonctions.php`)

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * Sujet bilingue : langue du visiteur, puis « / », puis anglais.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Sujet dans la langue courante (celle du visiteur)
	$sujet_local = _T('acme:email_proposition_sujet');
	// Même sujet forcé en anglais (option `lang`, SPIP 5.0)
	$sujet_en = _T('acme:email_proposition_sujet', [], ['lang' => 'en']);
	$sujet = $sujet_local . ' / ' . $sujet_en;

	// Corps inchangé ; 3e argument passé en tableau (une chaîne lève une TypeError en SPIP 5.0)
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], ['class' => 'titre_article']);

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

#### Remarques

- **Il faut le fichier de langue anglais.** La clé `email_proposition_sujet` doit exister dans `lang/acme_en.php`. Sinon `_T()` ne signale aucune erreur : il prend la langue du site, puis `fr`. Le sujet afficherait alors deux fois le même texte français. Exemple :

  ```php
  <?php
  // This is a SPIP language file  --  Ceci est un fichier langue de SPIP
  return [
    // E
    'email_proposition_sujet' => 'An article has been submitted for publication',
  ];
  ```

- **Si le visiteur navigue déjà en anglais**, le sujet affichera « X / X ». Pour l'éviter, testez `$GLOBALS['spip_lang'] === 'en'` et n'envoyez alors que la version anglaise. Je n'ai pas fait ce changement parce que vous ne l'avez pas demandé.

---

## spip-lang, cas 12 — plugin 4.4 + 5.0

#### Version ciblée

Le `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"` : le plugin doit fonctionner **à la fois sous SPIP 4.4 et sous SPIP 5.0**. Ça a deux conséquences directes :

1. **Pour obtenir le sujet en anglais, on ne peut pas utiliser l'option `lang` de `_T()`** (`_T($k, [], ['lang' => 'en'])`). Elle n'existe qu'en 5.0 : SPIP 4.4 l'ignore sans rien signaler et renverrait le sujet dans la langue du visiteur. L'ancienne forme `['spip_lang' => 'en']` est dépréciée en 5.0. La solution qui marche sur les deux versions est `lang_select('en')` … `lang_select()`.
2. **Le 3ᵉ argument de `_T()` sur la ligne du corps doit être corrigé**, même si vous ne m'avez pas demandé de toucher au corps. En 5.0, cet argument est typé `array`. La forme chaîne actuelle (`'titre_article'`) y déclenche une `TypeError`, donc la fonction planterait avant même l'envoi du mail. La forme `['class' => 'titre_article']` est déjà valide en 4.4. Le texte du corps reste le même.

#### Code modifié — `inc/acme_notifications.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * Sujet bilingue : langue du visiteur, puis « / », puis anglais.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Sujet dans la langue courante (celle du visiteur)
	$sujet = _T('acme:email_proposition_sujet');

	// Même sujet en anglais : lang_select() fonctionne en SPIP 4.4 et 5.0
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select(); // restaure la langue précédente (ne pas réutiliser la valeur de retour)

	$sujet = $sujet . ' / ' . $sujet_en;

	// Corps inchangé ; 3e argument passé en tableau (obligatoire en SPIP 5.0, valide en 4.4)
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], ['class' => 'titre_article']);

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

#### Points d'attention

- **Où placer `lang_select()`** : `lang_select('en')` est appelé seulement autour du sujet anglais. Le corps est calculé après le retour à la langue du visiteur, donc il n'est pas affecté. Pour revenir à la langue précédente, j'appelle `lang_select()` sans argument. Il ne faut pas réutiliser la valeur de retour de `lang_select('en')` : elle vaut `'en'`, pas l'ancienne langue.
- **La traduction anglaise doit exister** : il faut que `lang/acme_en.php` contienne la clé `email_proposition_sujet`. Sinon, `_T()` ne signale aucune erreur : il retombe sur la langue du site puis sur `fr`. Vous auriez alors deux fois la même phrase de part et d'autre du « / ». Le dossier fourni ne contient ni `lang/` ni de `<traduire module="acme" reference="fr" />` dans `paquet.xml`. Si le module de langue `acme` n'est pas déclaré et ses fichiers absents, ajoutez-les, par exemple :

  ```php
  // lang/acme_en.php
  <?php
  // This is a SPIP language file  --  Ceci est un fichier langue de SPIP
  return [
    // E
    'email_proposition_corps' => 'The article "@titre@" has been submitted for publication.',
    'email_proposition_sujet' => 'Article submitted for publication',
  ];
  ```

  Et dans `paquet.xml` :

  ```xml
  <traduire module="acme" reference="fr" />
  ```

- **Visiteur déjà en anglais** : le sujet donnera « Article submitted for publication / Article submitted for publication ». Si vous voulez éviter ce doublon, n'ajoutez la partie anglaise que lorsqu'elle diffère :

  ```php
  $sujet = ($sujet_en !== $sujet) ? $sujet . ' / ' . $sujet_en : $sujet;
  ```

---

## spip-lang, cas 13 — plugin 4.x

**Version ciblée :** le `paquet.xml` du plugin déclare `compatibilite="[4.2.0;4.4.*]"`. C'est donc du SPIP 4.x, et c'est ce repère qui guide le choix ci-dessous.

Pour obtenir le sujet en anglais, il faut changer de langue le temps d'un appel à `_T()` avec `lang_select('en')`, puis revenir à la langue précédente avec `lang_select()` (sans argument). En 4.x, `_T()` ne fournit pas d'option fiable pour forcer la langue d'un seul appel. L'option `lang` de SPIP 5 est **ignorée** en 4.4. `lang_select()` fonctionne en 4.x et en 5.0.

##### Code modifié : `inc/acme_notifications.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * Le sujet est bilingue : langue du visiteur, puis « / », puis anglais.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Sujet dans la langue courante (celle du visiteur)
	$sujet = _T('acme:email_proposition_sujet');

	// Même sujet en anglais : on bascule temporairement la langue
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select(); // restaure la langue précédente (ne pas réutiliser la valeur de retour de lang_select('en'))

	$sujet = $sujet . ' / ' . $sujet_en;

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Le corps n'a pas changé : il est toujours calculé dans la langue du visiteur, car l'appel a lieu après le retour à la langue d'origine.

##### Points à vérifier

- **La traduction anglaise doit exister.** Ajoutez la clé `email_proposition_sujet` dans `lang/acme_en.php`. Je ne vois pas de dossier `lang/` dans le plugin fourni. Sans cette traduction, `_T()` ne signale aucune erreur : il reprend la langue du site puis le `fr`. Le sujet afficherait alors deux fois le texte français, par exemple `Article proposé / Article proposé`. Exemple :
  ```php
  <?php
  // This is a SPIP language file  --  Ceci est un fichier langue de SPIP
  return [
    // E
    'email_proposition_sujet' => 'Article submitted for publication',
  ];
  ```
  Le module doit aussi être déclaré dans `paquet.xml` avec `<traduire module="acme" reference="fr" />`.
- **Visiteur anglophone :** si le visiteur navigue déjà en anglais, le sujet contiendra deux fois le texte anglais (`X / X`). Si vous préférez un sujet simple dans ce cas, ajoutez un test : `if ($sujet_en !== $sujet) { $sujet .= ' / ' . $sujet_en; }`.
- **Pour un futur passage à SPIP 5.0 (pas nécessaire ici) :** la ligne du corps transmet un 3e argument chaîne à `_T()` (`'titre_article'`). En 5.0, ce code provoque une `TypeError`, et il faudra écrire `['class' => 'titre_article']`, forme déjà acceptée en 4.4. Comme vous avez demandé de ne pas toucher au corps et que le plugin vise 4.2–4.4, je l'ai laissée telle quelle.

---

# spip-lang — avec les fichiers de langue fr/en dans les projets

---

## spip-lang, cas 11 — tirage 1

#### Version de SPIP

Le site tourne sous **SPIP 5.0**. Je l'ai vu à deux indices : les plugins sont rangés dans `plugins-dist/spip/` (par exemple `plugins-dist/spip/mots/`), et `plugins-dist/spip/mots/paquet.xml` indique `version="5.0.0-dev"` et `compatibilite="[5.0.0-dev;5.*]"`.

Deux conséquences pour `_T()` en 5.0 :

- **Pour obtenir une chaîne dans une autre langue**, on écrit `_T($cle, [], ['lang' => 'en'])`. L'ancienne forme `['spip_lang' => 'en']` passée dans les arguments est dépréciée. L'option `lang` est ignorée en 4.4, mais ce site est en 5.0, donc on peut s'en servir.
- **Le 3e argument de `_T()` doit être un tableau.** La ligne actuelle du corps, `_T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article')`, passe une chaîne et provoque une `TypeError` en 5.0. La fonction plante donc avant même d'envoyer le mail. Je corrige cette ligne aussi, en écrivant la même classe sous la forme `['class' => 'titre_article']`. Le texte du corps ne change pas.

#### Code modifié : `squelettes/mes_fonctions.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * Sujet bilingue : langue du visiteur, puis « / », puis anglais.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	$sujet_visiteur = _T('acme:email_proposition_sujet');
	$sujet_en = _T('acme:email_proposition_sujet', [], ['lang' => 'en']);
	$sujet = $sujet_visiteur . ' / ' . $sujet_en;

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], ['class' => 'titre_article']);

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

#### Détails

- Le premier `_T()`, appelé sans option, renvoie le texte dans la langue courante, c'est-à-dire celle du visiteur. Le second force l'anglais avec l'option `lang` et ne touche pas à la langue courante. Il n'y a donc rien à rétablir après, alors qu'avec `lang_select()` il faudrait appeler `lang_select()` une seconde fois pour revenir à la langue d'origine.
- Les fichiers de langue ne changent pas : la clé `email_proposition_sujet` existe déjà dans `squelettes/lang/acme_fr.php` et dans `squelettes/lang/acme_en.php`.
- Résultat pour un visiteur francophone : `Article proposé à la publication / Article submitted for publication`.
- Pour un visiteur anglophone, le sujet apparaît deux fois en anglais (`Article submitted for publication / Article submitted for publication`). C'est bien ce que demande la consigne. Si vous préférez dans ce cas n'afficher qu'une seule fois le sujet, ajoutez `if ($sujet_visiteur !== $sujet_en)` avant la concaténation.

---

## spip-lang, cas 11 — tirage 2

J'ai modifié `squelettes/mes_fonctions.php`. Le sujet est maintenant au format « sujet dans la langue du visiteur / sujet en anglais ». Le texte du corps reste le même, mais j'ai dû corriger son appel `_T()`, qui planterait sur ce site.

**Version de SPIP.** Le site tourne en SPIP 5.0 : les plugins sont rangés dans `plugins-dist/spip/`, et `plugins-dist/spip/mots/paquet.xml` déclare `version="5.0.0-dev"` et `compatibilite="[5.0.0-dev;5.*]"`. Ce point compte pour le choix de l'appel `_T()`.

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);
	// Sujet bilingue : langue du visiteur, puis la même chaîne en anglais
	$sujet = _T('acme:email_proposition_sujet')
		. ' / '
		. _T('acme:email_proposition_sujet', [], ['lang' => 'en']);
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], ['class' => 'titre_article']);

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Avec un visiteur francophone, le sujet devient :
`Article proposé à la publication / Article submitted for publication`

Les deux chaînes existent déjà dans `squelettes/lang/acme_fr.php` et `squelettes/lang/acme_en.php`. Aucun fichier de langue n'est à modifier.

**Ce qui a changé :**

1. **Sujet en anglais :** j'utilise l'option `['lang' => 'en']`, en 3e argument de `_T()`. C'est la bonne écriture en SPIP 5.0. L'ancienne forme `_T($cle, ['spip_lang' => 'en'])` y est dépréciée. Le premier `_T()` n'a pas d'option, il reste donc dans la langue du visiteur.
2. **Correction obligatoire sur la ligne du corps :** l'appel passait `'titre_article'` sous forme de chaîne en 3e argument. En SPIP 5.0, ce paramètre doit être un tableau, et une chaîne provoque une `TypeError`. L'envoi de l'email planterait donc. Je l'ai remplacé par `['class' => 'titre_article']`. Le texte du corps envoyé ne change pas.

**Si ce code doit aussi tourner en SPIP 4.4** (par exemple un plugin partagé) : en 4.4, `_T()` ignore l'option `lang` sans rien signaler, et la seconde moitié du sujet resterait en français. Il faut alors utiliser `lang_select()`, qui fonctionne sur les deux versions :

```php
	$sujet = _T('acme:email_proposition_sujet');
	lang_select('en');
	$sujet .= ' / ' . _T('acme:email_proposition_sujet');
	lang_select(); // revient à la langue du visiteur
```

Pour ce site, qui est en 5.0, la version avec `['lang' => 'en']` suffit.

---

## spip-lang, cas 11 — tirage 3

#### Version du site

Le site est en **SPIP 5.0** : les plugins-dist sont rangés dans `plugins-dist/spip/` (par ex. `plugins-dist/spip/mots/paquet.xml`, `compatibilite="[5.0.0-dev;5.*]"`). Je m'appuie sur ce repère.

Conséquences en 5.0 :
- pour obtenir une chaîne dans une autre langue en un seul appel, on écrit `_T($cle, [], ['lang' => 'en'])` (l'ancienne forme `['spip_lang' => 'en']` dans les arguments est dépréciée) ;
- le 3e argument de `_T()` est typé `array` : la ligne actuelle du corps, `_T(..., ['titre' => $titre], 'titre_article')`, lève une `TypeError` en 5.0. Je la corrige donc aussi en `['class' => 'titre_article']`, même si on ne m'a pas demandé de toucher au corps. Le texte du corps ne change pas.

#### Code modifié — `squelettes/mes_fonctions.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Sujet bilingue : langue du visiteur (langue courante), puis anglais
	$sujet = _T('acme:email_proposition_sujet')
		. ' / '
		. _T('acme:email_proposition_sujet', [], ['lang' => 'en']);

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], ['class' => 'titre_article']);

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

#### Remarques

- Aucun fichier de langue à modifier : `acme_fr.php` et `acme_en.php` contiennent déjà `email_proposition_sujet`. Un visiteur francophone produira :
  `Article proposé à la publication / Article submitted for publication`.
- Si le visiteur navigue déjà en anglais, le sujet sera répété (`Article submitted for publication / Article submitted for publication`). Comme vous avez demandé « le même sujet en anglais », j'ai gardé ce comportement. Si vous préférez éviter le doublon, ajoutez un test sur `$GLOBALS['spip_lang'] !== 'en'`.
- Attention : l'option `lang` est **ignorée** par `_T()` en SPIP 4.4. Si ce code devait aussi tourner en 4.4, il faudrait encadrer l'appel anglais par `lang_select('en')` … `lang_select()` (valable dans les deux versions) :

  ```php
  $sujet_visiteur = _T('acme:email_proposition_sujet');
  lang_select('en');
  $sujet_en = _T('acme:email_proposition_sujet');
  lang_select(); // restaure la langue précédente
  $sujet = $sujet_visiteur . ' / ' . $sujet_en;
  ```

---

## spip-lang, cas 12

#### Version ciblée

Le `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"` : le plugin doit fonctionner **à la fois sur SPIP 4.4 et sur SPIP 5.0**. C'est ce qui oriente la façon d'obtenir la version anglaise :

- `_T($cle, [], ['lang' => 'en'])` est la forme 5.0, mais SPIP 4.4 **ignore** l'option `lang` (on aurait le sujet deux fois dans la langue du visiteur, sans erreur).
- `_T($cle, ['spip_lang' => 'en'])` marche en 4.4 mais est déprécié en 5.0.
- `lang_select('en')` … `lang_select()` marche dans les deux versions : c'est ce que j'utilise.

#### Code modifié — `inc/acme_notifications.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * Le sujet est bilingue : langue du visiteur, puis « / », puis anglais.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Sujet dans la langue courante (celle du visiteur)
	$sujet_visiteur = _T('acme:email_proposition_sujet');

	// Même sujet en anglais (compatible SPIP 4.4 et 5.0)
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select(); // restaure la langue précédente

	$sujet = $sujet_visiteur . ' / ' . $sujet_en;

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], ['class' => 'titre_article']);

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Avec un visiteur francophone, le sujet devient :
`Article proposé à la publication / Article submitted for publication`

#### Points à noter

- **Le corps est inchangé** (même clé, même langue, mêmes arguments). Seule l'écriture du 3ᵉ argument a été corrigée : `'titre_article'` (chaîne) devient `['class' => 'titre_article']`. En SPIP 5.0, ce 3ᵉ argument est typé `array` et l'ancienne forme chaîne lève une `TypeError` ; la forme tableau est déjà valide en 4.4, le comportement est donc identique, mais la fonction ne plante plus sous SPIP 5.
- `lang_select()` est appelé **sans argument** pour restaurer la langue : il ne faut pas stocker la valeur de retour de `lang_select('en')` (elle renvoie `'en'`, pas la langue précédente).
- Aucun fichier de langue à modifier : la clé `email_proposition_sujet` existe déjà dans `lang/acme_fr.php` et `lang/acme_en.php`.
- Si le visiteur est lui-même anglophone, le sujet sera `Article submitted for publication / Article submitted for publication`. Si vous préférez dans ce cas un sujet simple, il suffit de remplacer la ligne de concaténation par :

  ```php
  $sujet = ($sujet_visiteur === $sujet_en) ? $sujet_en : $sujet_visiteur . ' / ' . $sujet_en;
  ```

---

## spip-lang, cas 13

#### Sujet bilingue dans `acme_notifier_webmestre()`

**Version ciblée :** le `paquet.xml` déclare `compatibilite="[4.2.0;4.4.*]"`. Le plugin vise donc SPIP 4.x, pas 5.0.

Pour le sujet en anglais, je n'ai pas utilisé `_T('acme:email_proposition_sujet', [], ['lang' => 'en'])`. En 4.4, `_T()` ignore cette option sans rien signaler, et on obtiendrait deux fois le sujet dans la langue du visiteur. La méthode qui marche en 4.x (et aussi en 5.0) est d'encadrer l'appel par `lang_select('en')` puis `lang_select()`. Le premier appel bascule en anglais, le second rétablit la langue d'avant. Il ne faut pas garder la valeur de retour de `lang_select('en')` pour la restaurer : elle vaut `'en'`, pas l'ancienne langue.

Les deux traductions existent déjà (`lang/acme_fr.php` et `lang/acme_en.php`), donc aucun fichier de langue ne change.

##### `inc/acme_notifications.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * Sujet bilingue : langue du visiteur, puis « / », puis anglais.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Sujet dans la langue courante (celle du visiteur)
	$sujet = _T('acme:email_proposition_sujet');

	// Même sujet en anglais : on bascule temporairement la langue
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select(); // restaure la langue précédente (celle du visiteur)

	$sujet .= ' / ' . $sujet_en;

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Exemple pour un visiteur francophone :
`Article proposé à la publication / Article submitted for publication`

Si le visiteur est déjà anglophone, le sujet sera `Article submitted for publication / Article submitted for publication`. C'est ce que demande la consigne à la lettre. Si vous préférez ne pas répéter le sujet dans ce cas, il suffit d'ajouter `if ($sujet_en !== $sujet)` avant la concaténation.

##### Ce que je n'ai pas changé

- **Le corps de l'email** : sa ligne est identique, comme demandé.
- **La chaîne `'titre_article'` passée en 3ᵉ argument de `_T()` pour le corps** : ça fonctionne en 4.x. En revanche, si le plugin passe un jour en SPIP 5.0, ce 3ᵉ argument doit être un tableau, sinon on obtient un `TypeError`. Il faudra alors écrire `['class' => 'titre_article']`, une forme déjà acceptée en 4.4. Comme le plugin est encore compatible 4.2, je l'ai laissée telle quelle.

---

## spip-plugins, cas 14 — site avec `ecrire/src/SpipVersion.php`

### Plugin `acme_bandeau`

#### Version de SPIP du site

Le site tourne sous **SPIP 5.0**. Je m'appuie sur `ecrire/src/SpipVersion.php`, qui déclare `VERSION = '5.0.0-beta'`.

Ça compte pour deux points :

- **Le modèle de plugin** : `<pipeline>` dans `paquet.xml`, une fonction `prefixe_pipeline()` et le pipeline `affichage_final` existent toujours en 5.0, tout comme `$GLOBALS['html']`. J'utilise donc ce modèle classique, qui marche aussi en 4.4. Je n'utilise pas le modèle propre à 5.0 (listeners, `config/services.php`).
- **La compatibilité** : sur une version exacte `5.0.0-beta`, l'intervalle `[5.0.0;5.*]` refuserait le plugin. J'écris `[4.4.0;5.*]` : l'intervalle accepte la beta 5.0 et le plugin reste installable sur un SPIP 4.4.

#### Fonctionnement

Le pipeline `affichage_final` reçoit la page HTML complète juste avant son envoi au navigateur. Il ne s'exécute que sur les pages publiques ; l'espace privé a son propre pipeline, `affichage_final_prive`. Le plugin :

1. ne touche pas les réponses qui ne sont pas du HTML (`$GLOBALS['html']` vaut faux pour les flux RSS, le JSON, etc.) ;
2. repère la **première** balise `<body…>`, attributs compris. Dans votre `squelettes/sommaire.html`, la balise est `<body class="page_sommaire">` : un simple `str_replace('<body>', …)` ne la trouverait pas ;
3. insère le bandeau juste après cette balise ;
4. ne l'ajoute pas une seconde fois s'il est déjà dans la page.

Comme `affichage_final` s'exécute à chaque affichage, même quand la page vient du cache, vous n'avez rien à vider : le bandeau apparaît dès que le plugin est activé.

#### Arborescence

```
plugins/acme_bandeau/
├── paquet.xml
└── acme_bandeau_pipelines.php
```

#### `plugins/acme_bandeau/paquet.xml`

```xml
<paquet
	prefix="acme_bandeau"
	categorie="communication"
	version="1.0.0"
	etat="stable"
	compatibilite="[4.4.0;5.*]"
>
	<nom>Acme Bandeau</nom>
	<!-- Insère un bandeau d'annonce en haut de chaque page publique -->
	<auteur>Acme</auteur>
	<licence>GNU/GPL</licence>

	<pipeline nom="affichage_final" inclure="acme_bandeau_pipelines.php" />
</paquet>
```

#### `plugins/acme_bandeau/acme_bandeau_pipelines.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Insère le bandeau d'annonce juste après la balise <body> des pages publiques.
 *
 * @param string $texte Page HTML complète
 * @return string Page HTML modifiée
 */
function acme_bandeau_affichage_final($texte) {
	// Ne traiter que les réponses HTML (pas les flux RSS, le JSON, etc.)
	if (empty($GLOBALS['html']) || !is_string($texte)) {
		return $texte;
	}

	// Ne pas insérer le bandeau deux fois
	if (str_contains($texte, 'class="acme-bandeau"')) {
		return $texte;
	}

	$bandeau = '<div class="acme-bandeau">Maintenance prévue le 3 novembre</div>';

	// Première balise <body>, avec ou sans attributs (ex. <body class="page_sommaire">)
	$resultat = preg_replace('/<body\b[^>]*>/i', '$0' . $bandeau, $texte, 1);

	return $resultat ?? $texte;
}
```

#### Mise en service

1. Copiez le dossier dans `plugins/acme_bandeau/`.
2. Activez le plugin dans l'espace privé, sous Configuration > Gestion des plugins.
3. Affichez une page publique, par exemple le sommaire : le code source doit contenir `<body class="page_sommaire"><div class="acme-bandeau">Maintenance prévue le 3 novembre</div>`.

#### Remarques

- Le texte est écrit en dur, comme demandé : la date ou le message ne se changent qu'en modifiant le fichier PHP. Pour un texte multilingue, il faudrait un fichier `lang/acme_bandeau_fr.php` et `_T('acme_bandeau:…')`. Pour un message modifiable depuis l'espace privé, il faudrait une page de configuration, à lire avec `lire_config()` et jamais avec `$GLOBALS['meta']` en SPIP 5.0.
- Le plugin n'apporte aucun style. Pour mettre le bandeau en forme, ciblez `.acme-bandeau` dans la feuille de style de vos squelettes, ou demandez-moi d'ajouter une CSS au plugin via le pipeline `insert_head_css`.

---

## spip-plugins, cas 15 — tirage 1

### Bandeau `acme-bandeau` après `<body>`

#### Version visée

Le `paquet.xml` d'acme déclare `compatibilite="[4.4.0;5.*]"`. Le plugin doit donc fonctionner sur SPIP 4.4 **et** sur SPIP 5.0. C'est ce `compatibilite` qui m'a servi de repère. Conséquences :

- On garde le modèle classique : `<pipeline>` dans `paquet.xml` et une fonction `acme_<pipeline>()`. `affichage_final` et `$GLOBALS['html']` n'ont pas changé en 5.0. On n'ajoute **pas** de listener 5.0 en plus, pour ne pas enregistrer deux fois le même traitement.
- Il faut corriger le helper existant `acme_config()`, que je réutilise. Il fait `unserialize($GLOBALS['meta']['acme'])`. Or en SPIP 5.0, la meta est enregistrée en JSON dès que le formulaire `configurer_acme` est sauvegardé une fois. `unserialize()` renvoie alors `false`, et le bandeau disparaîtrait sans erreur, tout comme le thème sombre actuel. Il faut lire les réglages avec `lire_config('acme')`, qui marche en 4.4 comme en 5.0.

#### Choix du pipeline

`insert_head` / `insert_head_css` ne servent qu'à remplir le `<head>`. Pour placer du HTML juste après `<body>` sur **chaque page publique**, on passe par `affichage_final`. Ce pipeline reçoit la page HTML complète juste avant son envoi au navigateur, et seulement côté public (le privé a son propre pipeline, `affichage_final_prive`). On vérifie `$GLOBALS['html']` pour ne toucher qu'aux réponses `text/html`, et pas aux flux RSS, au JSON, etc.

`affichage_final` s'exécute à chaque affichage, même quand la page vient du cache. Une modification de `texte_bandeau` est donc visible tout de suite, sans vider le cache.

#### Modifications

##### `paquet.xml`

```xml
<paquet
	prefix="acme"
	categorie="communication"
	version="1.3.0"
	etat="stable"
	compatibilite="[4.4.0;5.*]"
>
	<nom>Acme</nom>
	<auteur>Acme</auteur>
	<licence>GPL 3</licence>

	<pipeline nom="insert_head_css" inclure="acme_pipelines.php" />
	<pipeline nom="affichage_final" inclure="acme_pipelines.php" />
</paquet>
```

(La version passe à 1.3.0 parce qu'on ajoute une fonctionnalité. Le plugin n'a pas de `schema`, donc aucune mise à jour de base n'est nécessaire.)

##### `acme_pipelines.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Réglages du plugin, enregistrés par le formulaire configurer_acme (casier « acme »).
 *
 * Lecture via lire_config() : fonctionne en SPIP 4.4 (meta sérialisée)
 * comme en SPIP 5.0 (meta stockée en JSON), contrairement à
 * unserialize($GLOBALS['meta']['acme']).
 */
function acme_config(): array {
	$config = lire_config('acme', []);

	return is_array($config) ? $config : [];
}

function acme_insert_head_css($flux) {
	$config = acme_config();
	if (($config['theme'] ?? '') === 'sombre') {
		$flux .= '<link rel="stylesheet" href="' . find_in_path('css/acme_sombre.css') . '" type="text/css" />' . "\n";
	}

	return $flux;
}

/**
 * Insère le bandeau acme juste après la balise <body> des pages publiques.
 *
 * Aucun bandeau si le réglage texte_bandeau est vide.
 */
function acme_affichage_final($texte) {
	// Uniquement les réponses HTML (pas les flux RSS, JSON, etc.)
	if (empty($GLOBALS['html'])) {
		return $texte;
	}

	$config = acme_config();
	$bandeau = trim((string) ($config['texte_bandeau'] ?? ''));
	if ($bandeau === '') {
		return $texte;
	}

	$html = '<div class="acme-bandeau">'
		. htmlspecialchars($bandeau, ENT_QUOTES, 'UTF-8')
		. '</div>';

	// Après la première balise <body ...> (attributs éventuels compris), une seule fois
	return preg_replace('/<body\b[^>]*>/i', '$0' . "\n" . str_replace(['\\', '$'], ['\\\\', '\\$'], $html), $texte, 1);
}
```

Remarques :

- **Échappement** : le texte saisi est échappé avec `htmlspecialchars()`, ce qui évite d'injecter du HTML ou du script dans toutes les pages publiques. `str_replace(['\\', '$'], …)` empêche `preg_replace` de prendre un `$1` ou un `\1` présent dans le texte pour une référence arrière.
- **`<body>` avec attributs** : l'expression `<body\b[^>]*>` accepte `<body class="page_article">` et les variantes du même genre. Si la page n'a pas de `<body>` (fragment HTML, réponse AJAX), `preg_replace` ne trouve rien et la page reste telle quelle.
- **Texte vide** : un champ vide ou ne contenant que des espaces ne produit aucun bandeau.

##### `formulaires/configurer_acme.html`

Aucune modification. Le champ `texte_bandeau` existe déjà, et le casier `_meta_casier=acme` enregistre la valeur sous `acme/texte_bandeau`, qui est la clé lue par `lire_config('acme')`.

##### Chaînes de langue (à vérifier)

Le formulaire utilise `<:acme:label_texte_bandeau:>`. Le plugin que vous m'avez donné n'a pas de dossier `lang/` ni de `<traduire>` dans `paquet.xml`. Si le fichier `lang/acme_fr.php` existe ailleurs, vérifiez qu'il contient `label_texte_bandeau` (ainsi que `titre_configurer`, `label_theme`, `theme_clair`, `theme_sombre`). Sinon, le libellé apparaîtra sous forme de clé brute dans le formulaire.

#### Pour tester

1. Rechargez la page d'administration des plugins pour que SPIP prenne en compte le nouveau pipeline déclaré dans `paquet.xml`.
2. Dans la configuration d'acme, saisissez un texte de bandeau et enregistrez.
3. Ouvrez une page publique : `<div class="acme-bandeau">…</div>` doit apparaître juste après `<body …>`.
4. Videz le champ et enregistrez : le bandeau ne doit plus apparaître.
5. Sur un site en SPIP 5.0, enregistrez le formulaire puis vérifiez que le bandeau **et** le thème sombre fonctionnent toujours. C'est le cas que la correction de `acme_config()` règle.

---

## spip-plugins, cas 15 — tirage 2

### Bandeau après `<body>` dans le plugin acme

#### Vérification de version

Le `paquet.xml` d'acme déclare `compatibilite="[4.4.0;5.*]"` : le plugin vise donc SPIP 4.4 **et** SPIP 5.0. J'en tire deux règles :

1. On garde le modèle classique : `<pipeline>` dans `paquet.xml` et une fonction `acme_<pipeline>()`. Ce modèle fonctionne sur les deux versions. Je n'utilise ni listener ni `config/services.php`.
2. On ne lit **jamais** les réglages dans `$GLOBALS['meta']`. Sur 5.0, la meta `acme` est stockée en JSON dès que le formulaire `configurer_acme` est enregistré. `unserialize()` renvoie alors `false`, et le bandeau (comme le thème sombre actuel) disparaîtrait sans erreur. Il faut lire avec `lire_config('acme/…')`, qui marche sur 4.4 et sur 5.0. Le helper existant `acme_config()` a ce défaut. Comme je le réutilise, je le corrige au passage.

#### Choix du pipeline

Le pipeline `affichage_final` reçoit la page HTML complète juste avant l'envoi au navigateur. Il ne concerne que l'espace public : l'espace privé a son propre `affichage_final_prive`. Il s'exécute à chaque affichage, après le cache. Une modification du réglage s'applique donc tout de suite, sans vider le cache des squelettes. On vérifie `$GLOBALS['html']` pour ne pas toucher aux réponses qui ne sont pas du HTML (flux RSS, JSON…).

On insère le bandeau juste après la balise ouvrante `<body …>`, qui peut porter des attributs (`class`, `dir`…). Le texte est échappé, puisqu'il vient d'un champ libre saisi dans le formulaire.

#### Modifications

##### `paquet.xml`

```xml
<paquet
	prefix="acme"
	categorie="communication"
	version="1.3.0"
	etat="stable"
	compatibilite="[4.4.0;5.*]"
>
	<nom>Acme</nom>
	<auteur>Acme</auteur>
	<licence>GPL 3</licence>

	<pipeline nom="insert_head_css" inclure="acme_pipelines.php" />
	<pipeline nom="affichage_final" inclure="acme_pipelines.php" />
</paquet>
```

(La version passe de 1.2.0 à 1.3.0 pour une nouvelle fonctionnalité. Le plugin n'a pas de `schema`, donc aucune mise à jour de base n'est nécessaire.)

##### `acme_pipelines.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Réglages du plugin, enregistrés par le formulaire configurer_acme (casier « acme »).
 *
 * Lecture via lire_config() : compatible SPIP 4.4 (meta sérialisée) et 5.0 (meta JSON).
 * Ne pas lire $GLOBALS['meta']['acme'] directement.
 */
function acme_config(): array {
	$config = lire_config('acme', []);

	return is_array($config) ? $config : [];
}

function acme_insert_head_css($flux) {
	if (lire_config('acme/theme') === 'sombre') {
		$flux .= '<link rel="stylesheet" href="' . find_in_path('css/acme_sombre.css') . '" type="text/css" />' . "\n";
	}

	return $flux;
}

/**
 * Insère le bandeau acme juste après la balise <body> des pages publiques.
 * Rien n'est inséré si le réglage texte_bandeau est vide.
 */
function acme_affichage_final($texte) {
	if (empty($GLOBALS['html'])) {
		return $texte; // pas une page HTML
	}

	$bandeau = trim((string) lire_config('acme/texte_bandeau', ''));
	if ($bandeau === '') {
		return $texte;
	}

	$html = '<div class="acme-bandeau">' . spip_htmlspecialchars($bandeau) . '</div>';

	// Après la première balise ouvrante <body ...> (attributs éventuels compris)
	return preg_replace('/<body\b[^>]*>/i', '$0' . "\n" . str_replace(['\\', '$'], ['\\\\', '\\$'], $html), $texte, 1);
}
```

Points à noter :

- **`lire_config('acme/texte_bandeau', '')`** lit la clé `texte_bandeau` du casier `acme`, que le formulaire remplit grâce à `_meta_casier=acme`. Elle renvoie `''` si le réglage n'existe pas encore.
- **`trim()` puis le test `=== ''`** : un réglage vide, ou fait uniquement d'espaces, ne produit aucun bandeau, comme demandé.
- **`spip_htmlspecialchars()`** échappe le texte saisi pour éviter l'injection de HTML ou de JavaScript.
- **`preg_replace(..., 1)`** ne fait qu'un remplacement. Le motif `<body\b[^>]*>` accepte `<body>` comme `<body class="page_article">`. Les `$` et `\` du texte sont protégés pour ne pas être lus comme des références de remplacement.
- Si la page n'a pas de `<body>` (fragment AJAX, par exemple), `preg_replace` ne trouve rien et la page est renvoyée telle quelle.
- `acme_insert_head_css()` lit maintenant `lire_config('acme/theme')` au lieu de `unserialize($GLOBALS['meta']['acme'])`. Sans ce changement, le thème sombre cesserait de fonctionner sur SPIP 5.0 après le premier enregistrement du formulaire. `acme_config()` est conservé pour un éventuel autre appelant, mais il passe lui aussi par `lire_config()`.

##### Style (facultatif)

Le bandeau a la classe `acme-bandeau`. Vous pouvez la styler dans une feuille du plugin, ajoutée par `insert_head_css` de la même façon que `css/acme_sombre.css`.

#### Vérification

1. Videz le cache (ou relancez la page avec `var_mode=recalcul`) pour que SPIP prenne en compte le nouveau pipeline déclaré dans `paquet.xml`. Sur la page d'administration des plugins, un simple rechargement suffit à recompiler la liste des pipelines.
2. Remplissez « texte_bandeau » dans le formulaire de configuration d'acme et enregistrez. Toute page publique doit alors commencer par `<div class="acme-bandeau">…</div>` juste après `<body…>`.
3. Videz le champ et enregistrez : le bandeau disparaît.
4. L'espace privé n'est pas touché, puisque `affichage_final` ne s'exécute que côté public.

---

## spip-plugins, cas 16 — plugin 4.x

#### Version ciblée

Le `paquet.xml` d'acme déclare `compatibilite="[4.2.0;4.4.*]"` : le plugin vise SPIP 4.2 à 4.4, pas SPIP 5.0. Je reste donc sur le modèle 4.x : une déclaration `<pipeline>` dans `paquet.xml` et une fonction `acme_<pipeline>()`.

#### Approche

- **Point d'injection : le pipeline `affichage_final`.** Il reçoit la page HTML publique complète juste avant l'envoi au navigateur. On insère le bandeau juste après la balise ouvrante `<body …>`, quels que soient ses attributs. Ce pipeline ne concerne que l'espace public : l'espace privé passe par `affichage_final_prive`, donc le bandeau n'y apparaît pas.
- **Vérification de `$GLOBALS['html']`.** Elle permet de ne modifier que les réponses HTML et de laisser intacts les flux RSS, le JSON, etc.
- **Lecture du réglage avec `lire_config('acme/texte_bandeau')`.** Le formulaire `configurer_acme` enregistre ses champs dans le casier `acme` (`_meta_casier`). `lire_config()` sait lire ce casier sur SPIP 4.4 comme sur 5.0. Je n'ai pas utilisé `unserialize($GLOBALS['meta']['acme'])`, ce qu'utilise le helper actuel `acme_config()`. Sur SPIP 5.0, ce tableau est réécrit en JSON dès le premier enregistrement du formulaire : `unserialize()` renverrait alors `false` et le réglage disparaîtrait sans erreur. Puisque je modifie ce fichier, j'ai aussi corrigé `acme_config()` pour qu'il passe par `lire_config()`. Le comportement ne change pas sur 4.x, et le code ne cassera pas le jour où la compatibilité sera étendue.
- **Bandeau vide → rien n'est inséré.** On applique `trim()` au réglage. S'il est vide, la page est renvoyée sans modification.
- **Échappement :** le texte est saisi dans un champ libre, donc on l'échappe avec `htmlspecialchars()` avant de l'insérer dans le HTML.

#### Modifications

##### `paquet.xml`

Il faut déclarer le pipeline `affichage_final`, qui pointe vers le même fichier :

```xml
<paquet
	prefix="acme"
	categorie="communication"
	version="1.3.0"
	etat="stable"
	compatibilite="[4.2.0;4.4.*]"
>
	<nom>Acme</nom>
	<auteur>Acme</auteur>
	<licence>GPL 3</licence>

	<pipeline nom="insert_head_css" inclure="acme_pipelines.php" />
	<pipeline nom="affichage_final" inclure="acme_pipelines.php" />
</paquet>
```

(La version passe de 1.2.0 à 1.3.0 pour signaler la nouvelle fonctionnalité. Le plugin n'a pas d'attribut `schema`, donc rien à migrer.)

##### `acme_pipelines.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Réglages du plugin, enregistrés par le formulaire configurer_acme (casier « acme »).
 *
 * Lecture via lire_config() plutôt que unserialize($GLOBALS['meta']['acme']) :
 * fonctionne en 4.x comme en 5.0 (où la meta est stockée en JSON).
 */
function acme_config(): array {
	include_spip('inc/config');
	$config = lire_config('acme', []);

	return is_array($config) ? $config : [];
}

function acme_insert_head_css($flux) {
	$config = acme_config();
	if (($config['theme'] ?? '') === 'sombre') {
		$flux .= '<link rel="stylesheet" href="' . find_in_path('css/acme_sombre.css') . '" type="text/css" />' . "\n";
	}

	return $flux;
}

/**
 * Insère le bandeau acme juste après <body> sur chaque page publique.
 * Aucun bandeau si le réglage texte_bandeau est vide.
 */
function acme_affichage_final($texte) {
	// Ne toucher qu'aux réponses HTML (pas aux flux RSS, JSON, etc.)
	if (empty($GLOBALS['html'])) {
		return $texte;
	}

	include_spip('inc/config');
	$bandeau = trim((string) lire_config('acme/texte_bandeau', ''));
	if ($bandeau === '') {
		return $texte;
	}

	$html = '<div class="acme-bandeau">' . htmlspecialchars($bandeau, ENT_QUOTES, 'UTF-8') . '</div>';

	// Juste après la balise ouvrante <body ...>, une seule fois
	return preg_replace('/<body\b[^>]*>/i', '$0' . "\n" . str_replace(['\\', '$'], ['\\\\', '\\$'], $html), $texte, 1);
}
```

Remarques :

- `preg_replace(…, 1)` limite l'insertion à la première balise `<body>`. Le `str_replace` protège les caractères `\` et `$` du texte saisi : sans lui, `preg_replace` les interpréterait comme des références arrière.
- Une page sans `<body>` (fragment, page partielle) est renvoyée telle quelle, puisque le motif ne trouve rien à remplacer.
- Le pipeline renvoie toujours `$texte`, même sans modification, pour ne pas casser la chaîne des autres plugins.

#### Après la modification

1. Videz le cache des plugins : rechargez la page **Configuration > Gestion des plugins** pour que SPIP prenne en compte le nouveau `<pipeline>` déclaré dans `paquet.xml`.
2. Renseignez « texte du bandeau » dans le formulaire de configuration d'acme, puis affichez une page publique. Le `<div class="acme-bandeau">` doit apparaître juste après `<body>`. Videz le champ : le bandeau doit disparaître.
3. Pour le style, ajoutez les règles `.acme-bandeau` dans la CSS du plugin, par exemple une `css/acme.css` chargée par `insert_head_css` comme `acme_sombre.css`.

---

## spip-testing, cas 17 — plugin 5.0 — tirage 1

### Test d'intégration de `verifier()` pour `inscription_lettre`

#### Version de SPIP ciblée : 5.0

Le plugin cible SPIP 5.0, d'après deux marqueurs :

- `paquet.xml` : `compatibilite="[5.0.0-dev;5.*]"` ;
- `composer.json` : `"spip/framework": "^5.x-dev"`.

Ce choix a deux conséquences pour le test.

1. **Le bootstrap d'intégration actuel ne marche pas sous 5.0.** `tests/bootstrap_integration.php` charge `ecrire/inc_version.php` sans démarrer le Kernel. Sous 5.0, il s'arrête sur `Undefined constant "_FILE_OPTIONS"`. Il faut démarrer le Kernel puis empiler une `Request`. Sans `Request`, `set_request()` lève une `LogicException`.
2. **Les entrées du formulaire se passent par `set_request()`.** Le formulaire lit `Spip::input('email')`. Sous 5.0, `_request()` et `Spip::input()` ne voient pas un `$_POST` ou un `$_GET` rempli par le test. Le motif `$_POST['email'] = …` que l'on trouve dans beaucoup d'exemples 4.x ne fonctionnerait donc pas ici. J'utilise `set_request('email', $valeur)`, qui marche en 4.x comme en 5.0.

#### Fichiers livrés

##### 1. `tests/integration/InscriptionLettreVerifierTest.php` (nouveau)

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration de formulaires_inscription_lettre_verifier_dist().
 *
 * Il tourne sur un SPIP réel, chargé par tests/bootstrap_integration.php :
 * _T(), email_valide() et set_request() sont les vraies fonctions du cœur.
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Charge charger/verifier/traiter du formulaire du plugin.
        // _ECRIRE_INC_VERSION est défini par le bootstrap, le garde du fichier laisse passer.
        include_once dirname(__DIR__, 2) . '/formulaires/inscription_lettre.php';
    }

    protected function setUp(): void
    {
        // Point de départ propre : aucune valeur email dans la requête.
        set_request('email', null);
    }

    protected function tearDown(): void
    {
        set_request('email', null);
    }

    public function testEmailInvalideProduitUneErreurSurLeChampEmail(): void
    {
        set_request('email', 'pas-une-adresse');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs, 'Une adresse invalide doit produire une erreur sur « email »');
        $this->assertNotSame('', (string) $erreurs['email'], 'Le message d\'erreur ne doit pas être vide');
        $this->assertSame(['email'], array_keys($erreurs), 'Seul le champ email doit être en erreur');
    }

    public function testEmailValideNeProduitAucuneErreur(): void
    {
        set_request('email', 'lecteur@example.org');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs, 'Une adresse valide ne doit produire aucune erreur');
    }
}
```

Notes :

- J'utilise `TestCase` et non `SquelettesTestCase`, puisque le test appelle directement une fonction PHP.
- `include_once` charge le fichier du plugin par un chemin absolu. Le test ne dépend donc ni de `find_in_path` ni de l'activation du plugin pour trouver la fonction.
- Le test vérifie aussi que **seul** `email` est en erreur. Le formulaire ne contrôle aucun autre champ, donc cette assertion détectera une régression qui ajouterait une erreur ailleurs.

##### 2. `tests/bootstrap_integration.php` (modifié pour SPIP 5.0)

```php
<?php
declare(strict_types=1);

$spipRoot = dirname(__DIR__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}

// SPIP 5.0 : démarrer le Kernel AVANT inc_version.php, sinon
// « Undefined constant "_FILE_OPTIONS" ».
// Test sur la présence du framework, jamais sur class_exists(Kernel::class) :
// SPIP 4.4 possède aussi cette classe, avec un boot() privé.
$spip5 = is_dir($spipRoot . '/vendor/spip/framework');
if ($spip5) {
    \SpipLeague\Component\Kernel\Kernel::boot();
}

require_once $spipRoot . '/ecrire/inc_version.php';

// SPIP 5.0 : il faut une Request courante, sinon set_request() lève une LogicException.
if ($spip5 && \Spip::service('request_stack')->getCurrentRequest() === null) {
    \Spip::service('request_stack')->push(\Symfony\Component\HttpFoundation\Request::create('/'));
}

include_spip('inc/plugin');
_chemin(dirname(__DIR__));   // ajoute la racine du plugin au chemin SPIP
actualise_plugins_actifs();  // active la chaîne de plugins

// NE PAS inclure tests/bootstrap.php ici : ses mocks (sql_countsel, autoriser…)
// entrent en conflit avec les vraies fonctions de SPIP chargées ci-dessus.
```

Le plugin ne vise que 5.x, mais j'ai gardé la condition `$spip5`. Elle ne coûte rien et laisse le bootstrap fonctionner si vous élargissez un jour la compatibilité à 4.4.

#### Lancement

```bash
composer install
composer tests-integration
# ou directement :
vendor/bin/phpunit --colors --testsuite integration --bootstrap tests/bootstrap_integration.php \
  --filter InscriptionLettreVerifierTest
```

#### Problème à corriger : l'installation du SPIP de test

Avant de lancer le test, sachez que `scripts/install-spip-test.sh` installe un **SPIP 4.4** (`core:telecharger … -b 4.4`) avec spip-cli. Ce script ne convient pas à un plugin 5.0, pour deux raisons :

- spip-cli (`core:telecharger`, `core:installer`, `plugins:activer`, …) est un outil 4.x ;
- le `bin/spip` de 5.0 n'a pas de commande d'installation ni d'activation de plugin.

Il ne suffit pas de changer la branche passée à `-b`. Il faut remplacer l'étape de téléchargement. Un SPIP 5.0 de test s'obtient avec Composer (PHP 8.4 requis) :

```bash
composer create-project --repository=https://get.spip.net/composer spip/spip vendor/spip/spip 5.0.x-dev
```

Il faut ensuite installer ce site (base SQLite et compte admin) sans `core:installer`, car `bin/spip` n'offre pas cette commande en 5.0. Il faut aussi activer le plugin autrement que par `plugins:activer`. La documentation dont je dispose ne décrit pas de procédure scriptée équivalente pour 5.0. Je ne vous propose donc pas de script réécrit qui n'aurait pas été vérifié. L'installation et l'activation par l'interface du site de test sont une solution sûre.

Le script `tests-integration` de `composer.json` exige `vendor/spip/spip/config/connect.php`. Le SPIP de test doit donc être réellement installé. Le test de `verifier()` en lui-même n'écrit rien en base : il n'appelle pas `traiter()`, donc il ne touche pas à `spip_inscrits`.

---

## spip-testing, cas 17 — tirage 2

### Test d'intégration de `verifier()` pour `inscription_lettre`

#### Version de SPIP visée : 5.0

Le plugin cible **SPIP 5.0**. Deux indices le montrent :

- `paquet.xml` déclare `compatibilite="[5.0.0-dev;5.*]"` ;
- `composer.json` exige `spip/framework` (`^5.x-dev`).

Le formulaire lit d'ailleurs ses saisies avec `Spip::input('email')`, l'API de SPIP 5.

Sur SPIP 5.0, trois choses changent par rapport à la configuration actuelle du plugin :

1. **Le bootstrap d'intégration actuel ne fonctionne pas sur 5.0.** `tests/bootstrap_integration.php` charge directement `ecrire/inc_version.php`. Sur SPIP 5.0, cela plante avec `Undefined constant "_FILE_OPTIONS"`. Il faut démarrer le Kernel avant ce chargement, puis ajouter une `Request`. Sans cette `Request`, `set_request()` lève une `LogicException`.
2. **Remplir `$_POST` ne sert à rien en 5.0.** `Spip::input()` et `_request()` ne voient pas un `$_POST` ou un `$_GET` modifié par le test. Le test transmet donc la saisie avec `set_request('email', …)`, qui marche aussi en 4.x.
3. **Le script `scripts/install-spip-test.sh` installe un SPIP 4.4.** Il utilise spip-cli (`core:telecharger -b 4.4`, `plugins:activer`, `plugins:svp:*`, `plugins:maj:bdd`), un outil réservé à SPIP 4.x. Le `bin/spip` de SPIP 5.0 n'a aucune commande pour installer le site ou activer un plugin. Changer de branche avec `-b` ne suffit donc pas : il faut remplacer la façon d'obtenir le SPIP de test. En 5.0, on l'obtient avec `composer create-project` (PHP 8.4).

#### Fichiers livrés

##### 1. `tests/integration/InscriptionLettreVerifierTest.php` (nouveau)

```php
<?php
declare(strict_types=1);

namespace Acme\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Teste verifier() du formulaire CVT inscription_lettre sur une vraie instance SPIP.
 *
 * Sur SPIP 5.0, Spip::input() ne lit pas $_POST : on transmet les saisies avec set_request().
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Ce fichier définit formulaires_inscription_lettre_{charger,verifier,traiter}_dist().
        include_once dirname(__DIR__, 2) . '/formulaires/inscription_lettre.php';
    }

    protected function tearDown(): void
    {
        // Ne pas laisser la saisie d'un test fuir dans le suivant.
        set_request('email', null);
        parent::tearDown();
    }

    public static function emailsInvalides(): iterable
    {
        yield 'sans arobase' => ['pas-une-adresse'];
        yield 'sans domaine' => ['jean@'];
        yield 'sans partie locale' => ['@example.org'];
        yield 'avec espace' => ['jean dupont@example.org'];
    }

    #[DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurLeChampEmail(string $email): void
    {
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs);
        $this->assertSame(_T('form_prop_indiquer_email'), $erreurs['email']);
        // Le message doit porter uniquement sur le champ email.
        $this->assertSame(['email'], array_keys($erreurs));
    }

    public function testEmailValideNeProduitAucuneErreur(): void
    {
        set_request('email', 'jean.dupont@example.org');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }
}
```

`verifier()` n'écrit pas en base, donc ce test n'a rien à nettoyer en fin de classe. La comparaison avec `_T('form_prop_indiquer_email')` vérifie que l'erreur vient de la branche « adresse invalide » et non de la branche « champ obligatoire ».

##### 2. `tests/bootstrap_integration.php` (modifié pour SPIP 5.0)

```php
<?php
declare(strict_types=1);

$spipRoot = dirname(__DIR__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}

// SPIP 5.0 : démarrer le Kernel AVANT inc_version.php
// (sinon : Undefined constant "_FILE_OPTIONS").
// Ne pas tester class_exists(Kernel::class) : la classe existe aussi en 4.4,
// avec une méthode boot() privée.
$spip5 = is_dir($spipRoot . '/vendor/spip/framework');
if ($spip5) {
    \SpipLeague\Component\Kernel\Kernel::boot();
}

require_once $spipRoot . '/ecrire/inc_version.php';

// SPIP 5.0 : sans Request courante, set_request() lève une LogicException.
if ($spip5 && \Spip::service('request_stack')->getCurrentRequest() === null) {
    \Spip::service('request_stack')->push(\Symfony\Component\HttpFoundation\Request::create('/'));
}

include_spip('inc/plugin');
_chemin(dirname(__DIR__));   // ajoute la racine du plugin au chemin SPIP
actualise_plugins_actifs();  // active la chaîne de plugins

// NE PAS inclure tests/bootstrap.php ici : ses mocks (sql_countsel, autoriser…)
// entrent en conflit avec les vraies fonctions SPIP déjà chargées.
```

Le garde `$spip5` permet aussi de lancer les tests sur un SPIP 4.4, si vous en avez un jour besoin.

##### 3. `scripts/install-spip-test.sh` (remplacé pour SPIP 5.0)

```sh
#!/usr/bin/env sh
set -eu

# SPIP de test pour un plugin SPIP 5.0.
# spip-cli (core:telecharger, plugins:activer…) ne fonctionne qu'avec SPIP 4.x,
# et le bin/spip de SPIP 5.0 n'a pas de commande d'installation : on passe par composer.
ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SPIP_ROOT="$ROOT_DIR/vendor/spip/spip"

if [ ! -f "$SPIP_ROOT/ecrire/inc_version.php" ]; then
    mkdir -p "$ROOT_DIR/vendor/spip"
    composer create-project \
        --repository=https://get.spip.net/composer \
        spip/spip "$SPIP_ROOT" 5.0.x-dev
fi

echo "SPIP 5.0 téléchargé dans $SPIP_ROOT (PHP 8.4 requis)."
```

#### Lancer les tests

```bash
composer install
composer install-spip-test
composer tests-integration
```

Pour ne lancer que ce test :

```bash
vendor/bin/phpunit --testsuite integration \
  --bootstrap tests/bootstrap_integration.php \
  --filter InscriptionLettreVerifierTest
```

#### Points à vérifier de votre côté

- **Création de la base du site de test.** La documentation dont je dispose ne donne aucune commande `bin/spip` de SPIP 5.0 pour créer la base. L'ancienne étape `core:installer` (SQLite) n'a donc pas d'équivalent dans le script ci-dessus. Le script `tests-integration` de `composer.json` refuse de démarrer tant que `vendor/spip/spip/config/connect.php` n'existe pas. Il faut donc faire l'installation une fois à la main, par exemple avec l'installateur web du SPIP de test, ou adapter ce contrôle. `verifier()` lui-même n'utilise pas la base : il appelle seulement `email_valide()` et `_T()`.
- **Dépendance `spip/spip-cli`.** Elle reste dans les `require-dev` de `composer.json`, mais elle ne sert plus pour SPIP 5.0. Vous pouvez la retirer.
- **Bootstrap déclaré dans `phpunit.xml`.** Le fichier pointe vers `tests/bootstrap.php`, qui n'existe pas. Ce n'est pas gênant pour l'intégration, car `composer tests-integration` passe `--bootstrap tests/bootstrap_integration.php`. En revanche, `composer tests-unit` échouera tant que ce fichier n'existe pas.
- **Tests non exécutés.** Je n'ai lancé aucun de ces tests : je n'ai pas d'instance SPIP 5.0 installée sous la main. Ils restent à valider de votre côté.

---

## spip-testing, cas 18 — plugin 4.4 + 5.0 — tirage 1

### Test d'intégration de `verifier()` pour `inscription_lettre`

#### Version de SPIP ciblée

Le `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"`. Le plugin vise donc **SPIP 4.4 et SPIP 5.0**, et le test doit tourner sur les deux versions. Cela a trois conséquences :

1. **Lecture des champs** : en 5.0, `_request()` ne voit pas un `$_POST` rempli par le test. Le test passe donc les valeurs avec `set_request('email', …)`, qui fonctionne en 4.4 comme en 5.0. Je n'utilise jamais `$_POST`.
2. **Bootstrap** : sur 5.0, le `tests/bootstrap_integration.php` actuel s'arrête sur `Undefined constant "_FILE_OPTIONS"`. Il faut démarrer le Kernel et empiler une `Request`. La correction est en 2) ci-dessous. Elle est protégée par la variable `$spip5`, si bien que le même fichier marche toujours en 4.4.
3. **SPIP de test** : `scripts/install-spip-test.sh` utilise spip-cli (`core:telecharger -b 4.4`, `plugins:activer`…), qui ne sert qu'avec SPIP 4.x. Il reste valable pour la campagne 4.4. Pour 5.0, il faut **remplacer** ce script, pas le pointer vers une autre branche `-b` : le `bin/spip` de 5.0 n'a pas de commande d'installation ni d'activation de plugin. Un site de test 5.0 se crée avec :
   ```bash
   composer create-project --repository=https://get.spip.net/composer spip/spip vendor/spip/spip 5.0.x-dev
   ```
   Il faut PHP 8.4. Il faut ensuite installer ce site et y activer `acme` par un autre moyen que spip-cli.

#### Fichiers livrés

##### 1) `tests/integration/InscriptionLettreVerifierTest.php` (nouveau)

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration de formulaires_inscription_lettre_verifier_dist().
 * Le test tourne contre un vrai SPIP (tests/bootstrap_integration.php).
 * Les valeurs sont passées avec set_request(), valable en SPIP 4.4 et 5.0.
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Le formulaire est livré par le plugin : on charge son fichier PHP.
        include_once dirname(__DIR__, 2) . '/formulaires/inscription_lettre.php';
    }

    protected function setUp(): void
    {
        set_request('email', null);
        set_request('nom', null);
    }

    protected function tearDown(): void
    {
        set_request('email', null);
        set_request('nom', null);
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'     => ['pas-une-adresse'],
            'sans domaine'     => ['jean@'],
            'sans partie locale' => ['@example.org'],
            'espace interne'   => ['jean dupont@example.org'],
        ];
    }

    /**
     * @dataProvider emailsInvalides
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurEmail(string $email): void
    {
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs, "Aucune erreur pour « $email »");
        $this->assertNotSame('', (string) $erreurs['email']);
    }

    public function testEmailValideNeProduitAucuneErreur(): void
    {
        set_request('nom', 'Jean Dupont');
        set_request('email', 'jean.dupont@example.org');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }

    public function testEmailValideEntoureDEspacesEstAccepte(): void
    {
        // verifier() fait un trim() avant de valider l'adresse
        set_request('email', '  jean.dupont@example.org  ');

        $this->assertSame([], formulaires_inscription_lettre_verifier_dist());
    }
}
```

Remarques :
- J'utilise `TestCase` et non `SquelettesTestCase`, parce que le test appelle directement une fonction PHP sans calculer de squelette.
- La classe porte à la fois l'attribut `#[DataProvider]` et l'annotation `@dataProvider`. PHPUnit 13, celui du `composer.json`, ne lit que l'attribut. L'annotation sert si l'on passe à une version plus ancienne.
- Le test vérifie que la clé `email` est présente, sans comparer le libellé. Le texte de `_T('form_prop_indiquer_email')` dépend de la langue et de la version du cœur.
- `set_request($cle, null)` dans `setUp()` et `tearDown()` évite qu'une valeur passe d'un test à l'autre.

##### 2) `tests/bootstrap_integration.php` (modifié pour SPIP 5.0)

Seul le bloc de chargement de SPIP change : on démarre le Kernel entre `vendor/autoload.php` et `ecrire/inc_version.php`, puis on empile une `Request`. Sans cette `Request`, `set_request()` lève une `LogicException` en 5.0.

```php
<?php
declare(strict_types=1);

$spipRoot = dirname(__DIR__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}

// SPIP 5.0 : démarrer le Kernel avant inc_version.php.
// Ne pas tester class_exists(Kernel::class) : la classe existe aussi en 4.4, avec un boot() privé.
$spip5 = is_dir($spipRoot . '/vendor/spip/framework');
if ($spip5) {
    \SpipLeague\Component\Kernel\Kernel::boot();
}
require_once $spipRoot . '/ecrire/inc_version.php';
if ($spip5 && \Spip::service('request_stack')->getCurrentRequest() === null) {
    \Spip::service('request_stack')->push(\Symfony\Component\HttpFoundation\Request::create('/'));
}

include_spip('inc/plugin');
_chemin(dirname(__DIR__));   // add plugin root to SPIP path
actualise_plugins_actifs();  // activate the plugin chain

// DO NOT require tests/bootstrap.php here: its mocks (sql_countsel, autoriser…)
// conflict with the real SPIP functions already loaded above.
```

On peut supprimer `tests/integration/.gitkeep` une fois le test ajouté.

#### Lancer le test

```bash
composer install
composer install-spip-test     # 4.4 uniquement (SPIP 5.0 : voir plus haut)
composer tests-integration
# ou seulement ce test :
vendor/bin/phpunit --colors --testsuite integration --bootstrap tests/bootstrap_integration.php \
  --filter InscriptionLettreVerifierTest
```

Résultat attendu : 6 tests verts. Ce sont les 4 cas invalides du data provider, le cas valide et le cas avec des espaces autour de l'adresse.

#### Point annexe

`phpunit.xml` déclare `bootstrap="tests/bootstrap.php"`, mais ce fichier n'existe pas dans le plugin. Cela ne gêne pas `composer tests-integration`, qui remplace ce bootstrap avec `--bootstrap tests/bootstrap_integration.php`. En revanche, `composer tests-unit` échouera tant que ce bootstrap de mocks n'aura pas été créé.

---

## spip-testing, cas 18 — tirage 2

Voici le test d'intégration de `verifier()` pour `inscription_lettre`, avec la correction du bootstrap dont il a besoin.

#### Version de SPIP visée : 4.4 **et** 5.0

Le `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"`, et c'est sur ce repère que je me fonde : le plugin vise aussi SPIP 5.0. Cela change deux choses pour ce test :

1. **Le bootstrap d'intégration actuel échoue sur 5.0** avec `Undefined constant "_FILE_OPTIONS"`. Il faut démarrer le Kernel entre `vendor/autoload.php` et `ecrire/inc_version.php`, puis ajouter une `Request` à la pile. Sans elle, `set_request()` lève une `LogicException`. La condition `$spip5` garde le même fichier valable sur 4.4.
2. **Le test passe les valeurs avec `set_request()`, pas en remplissant `$_POST`.** En 5.0, `_request()` ne lit pas un `$_POST` modifié par le test, donc l'adresse email resterait vide et le test n'aurait aucun sens. `set_request()` fonctionne en 4.4 comme en 5.0.

#### 1. `tests/integration/InscriptionLettreVerifierTest.php` (nouveau)

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Intégration : verifier() du formulaire CVT inscription_lettre,
 * exécuté contre un vrai SPIP (vendor/spip/spip).
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // _ECRIRE_INC_VERSION est défini par le bootstrap : le garde du fichier laisse passer.
        include_once __DIR__ . '/../../formulaires/inscription_lettre.php';
        include_spip('inc/filtres'); // email_valide()
    }

    protected function tearDown(): void
    {
        // Ne pas laisser de saisie d'un test à l'autre.
        set_request('email', '');
        set_request('nom', '');
        parent::tearDown();
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'        => ['pas-un-email'],
            'sans domaine'        => ['jean@'],
            'sans partie locale'  => ['@example.org'],
            'espace interne'      => ['jean dupont@example.org'],
        ];
    }

    #[DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurLeChampEmail(string $email): void
    {
        set_request('nom', 'Jean');
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs, "« $email » aurait dû être refusé");
        $this->assertSame(_T('form_prop_indiquer_email'), $erreurs['email']);
        $this->assertCount(1, $erreurs, 'Seul le champ email doit être en erreur');
    }

    public static function emailsValides(): array
    {
        return [
            'simple'              => ['jean@example.org'],
            'sous-adresse'        => ['jean.dupont+lettre@example.org'],
            'espaces autour'      => ['  jean@example.org  '], // verifier() fait un trim()
        ];
    }

    #[DataProvider('emailsValides')]
    public function testEmailValideNeProduitAucuneErreur(string $email): void
    {
        set_request('nom', 'Jean');
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }
}
```

Le test ne vérifie pas seulement la présence de la clé `email` : il compare aussi le message à `_T('form_prop_indiquer_email')`. Le cas « champ vide », qui renvoie `info_obligatoire`, passe par l'autre branche de `verifier()` et ne peut donc pas faire passer ce test par erreur. `verifier()` n'écrit pas en base, donc il n'y a rien à nettoyer en dehors des saisies.

#### 2. `tests/bootstrap_integration.php` (modifié)

Seul le bloc de chargement de SPIP change. Le reste est identique au fichier actuel.

```php
<?php
declare(strict_types=1);

$spipRoot = dirname(__DIR__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}

// SPIP 5.0 : démarrer le Kernel avant inc_version.php, sinon
// « Undefined constant "_FILE_OPTIONS" ». Garde sur le dossier, pas sur
// class_exists(Kernel::class) : la classe existe en 4.4, avec un boot() privé.
$spip5 = is_dir($spipRoot . '/vendor/spip/framework');
if ($spip5) {
    \SpipLeague\Component\Kernel\Kernel::boot();
}
require_once $spipRoot . '/ecrire/inc_version.php';
// SPIP 5.0 : sans Request dans la pile, set_request() lève une LogicException.
if ($spip5 && \Spip::service('request_stack')->getCurrentRequest() === null) {
    \Spip::service('request_stack')->push(\Symfony\Component\HttpFoundation\Request::create('/'));
}

include_spip('inc/plugin');
_chemin(dirname(__DIR__));   // add plugin root to SPIP path
actualise_plugins_actifs();  // activate the plugin chain

// DO NOT require tests/bootstrap.php here: its mocks (sql_countsel, autoriser…)
// conflict with the real SPIP functions already loaded above.
```

`composer.json` et `phpunit.xml` restent inchangés : la suite `integration` récupère déjà `tests/integration/*Test.php`, et `composer tests-integration` passe le bon bootstrap.

#### Lancer le test

```bash
composer install
composer install-spip-test     # première fois : SPIP 4.4 + SQLite dans vendor/spip/spip/
composer tests-integration
# ou seulement ce test :
vendor/bin/phpunit --colors --testsuite integration \
  --bootstrap tests/bootstrap_integration.php \
  --filter InscriptionLettreVerifierTest
```

#### Limite : l'instance de test installée reste une SPIP 4.4

`scripts/install-spip-test.sh` télécharge SPIP 4.4 (`core:telecharger -b 4.4`) avec spip-cli, qui est un outil de SPIP 4.x. Il ne suffit pas de lui passer une autre branche `-b` pour installer une SPIP 5.0 : le `bin/spip` de SPIP 5.0 n'a aucune commande d'installation ni d'activation de plugin. Pour exécuter ce test sur 5.0, il faut un site de test créé autrement, puis remplacer le script d'installation de la 4.4 par la séquence équivalente :

```bash
composer create-project --repository=https://get.spip.net/composer spip/spip vendor/spip/spip 5.0.x-dev   # PHP 8.4
```

Ensuite, le bootstrap ci-dessus détecte `vendor/spip/framework` et démarre le Kernel tout seul. Le test lui-même ne change pas.

Je n'ai pas exécuté ces tests : aucune instance SPIP n'est installée dans `vendor/spip/spip/` dans le dépôt fourni.

---

## spip-testing, cas 19 — plugin 4.x

#### Version de SPIP visée

Le plugin cible **SPIP 4.x** : `paquet.xml` déclare `compatibilite="[4.2.0;4.4.*]"`, et `composer.json` ne requiert pas `spip/framework`. Le socle d'intégration existant convient donc tel quel : SPIP 4.4 installé par `scripts/install-spip-test.sh` dans `vendor/spip/spip/`, et `tests/bootstrap_integration.php` sans démarrage du Kernel. Je ne modifie ni l'un ni l'autre.

Pour injecter les saisies, j'utilise `set_request()` plutôt que d'écrire dans `$_POST`. Ça fonctionne déjà en 4.x. Ça continuera de fonctionner si le plugin passe un jour en 5.0, où `_request()` ne lit plus le `$_POST` qu'un test remplit.

#### Fichier livré

Il n'y a qu'un fichier à ajouter : `tests/integration/InscriptionLettreVerifierTest.php`. Le suffixe `Test.php` et ce dossier correspondent à la testsuite `integration` de `phpunit.xml`.

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration de formulaires_inscription_lettre_verifier_dist().
 *
 * Utilise un vrai SPIP (vendor/spip/spip/, SQLite3) chargé par
 * tests/bootstrap_integration.php : _request(), email_valide() et _T()
 * sont donc les vraies fonctions de SPIP, et non des mocks.
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Le bootstrap ajoute la racine du plugin au chemin SPIP (_chemin()),
        // ce qui permet de charger le formulaire du plugin par find_in_path.
        $fichier = find_in_path('formulaires/inscription_lettre.php', '', true);
        self::assertNotEmpty($fichier, 'formulaires/inscription_lettre.php introuvable dans le chemin SPIP');
        self::assertTrue(
            function_exists('formulaires_inscription_lettre_verifier_dist'),
            'formulaires_inscription_lettre_verifier_dist() non définie'
        );
    }

    protected function setUp(): void
    {
        $this->viderSaisies();
    }

    protected function tearDown(): void
    {
        $this->viderSaisies();
    }

    private function viderSaisies(): void
    {
        // set_request(nom, null) retire la valeur de la requête.
        set_request('email', null);
        set_request('nom', null);
    }

    public function testEmailInvalideProduitUneErreurSurEmail(): void
    {
        set_request('nom', 'Jean Dupont');
        set_request('email', 'pas-une-adresse');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs, 'Une adresse invalide doit produire une erreur sur le champ email');
        $this->assertNotSame('', trim((string) $erreurs['email']), 'Le message d\'erreur ne doit pas être vide');
        $this->assertSame(['email'], array_keys($erreurs), 'Seul le champ email doit être en erreur');
    }

    public function testEmailValideNeProduitAucuneErreur(): void
    {
        set_request('nom', 'Jean Dupont');
        set_request('email', 'jean.dupont@example.org');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs, 'Une adresse valide ne doit produire aucune erreur');
    }
}
```

##### Choix du test

- **Niveau intégration, classe `TestCase`.** On appelle directement une fonction PHP, donc `SquelettesTestCase` ne sert à rien ici. Le test exécute le vrai `email_valide()` de SPIP, pas un mock. C'est la différence avec un test unitaire.
- **Chargement par `find_in_path(..., true)`.** Le formulaire fait partie du plugin, et le bootstrap ajoute déjà la racine du plugin au chemin SPIP. `setUpBeforeClass()` vérifie que la fonction existe : si le chemin est mal configuré, le test échoue avec un message clair au lieu d'une erreur « call to undefined function ».
- **Saisies remises à zéro avant et après chaque test.** Cela évite qu'un test hérite de la valeur laissée par le précédent.
- **Les assertions portent sur la clé `email`, pas sur le texte du message.** Ce texte vient de `_T('form_prop_indiquer_email')` et change selon la langue. Le test exige seulement qu'il ne soit pas vide et que le champ `email` soit le seul en erreur.
- Je n'ai pas couvert le cas « champ vide » (`info_obligatoire`), qui n'était pas demandé. Pour l'ajouter, il suffit d'une troisième méthode identique avec `set_request('email', '')`.

#### Lancer les tests

```bash
composer install              # PHPUnit, spip/tests, spip-cli
composer install-spip-test    # la première fois seulement : SPIP 4.4 + SQLite3 + activation d'acme
composer tests-integration
# ou directement :
vendor/bin/phpunit --colors --testsuite integration --bootstrap tests/bootstrap_integration.php \
  --filter InscriptionLettreVerifierTest
```

Le `phpunit.xml` déclare `bootstrap="tests/bootstrap.php"` (le bootstrap des tests unitaires), mais le dépôt ne contient pas ce fichier. Il ne gêne pas la commande ci-dessus, car `--bootstrap tests/bootstrap_integration.php` le remplace. En revanche, `composer tests-unit` échouera tant que ce fichier n'existera pas. Ce point n'a pas de lien avec ce test.

Ne vous fiez pas au message final du script d'installation pour savoir si `acme` est actif : vérifiez-le avec `vendor/bin/spip plugins:lister`, lancé depuis `vendor/spip/spip/`.

---

## spip-testing, après revue (`Spip::setInput()` pour la 5.0 seule) — cas 17, tirage 1

### Test d'intégration de `verifier()` pour `inscription_lettre`

#### Version de SPIP visée : 5.0 uniquement

Je me suis appuyé sur deux indices :

- `paquet.xml` : `compatibilite="[5.0.0-dev;5.*]"` ;
- `composer.json` : `"require": { "spip/framework": "^5.x-dev" }`.

Le plugin vise donc **SPIP 5.0 seulement**. Trois conséquences :

1. **Les entrées du formulaire.** En 5.0, `_request()` et `Spip::input()` ne voient pas ce qu'un test met dans `$_POST` ou `$_GET`. Le formulaire lit déjà `Spip::input('email')`, donc le test passe les valeurs avec `Spip::setInput('email', $valeur)` (et `null` pour effacer). Je n'utilise pas `set_request()`, qui est déprécié en 5.0 et n'est utile que pour un plugin qui doit aussi tourner en 4.4.
2. **Le bootstrap d'intégration actuel plante en 5.0** (`Undefined constant "_FILE_OPTIONS"`). Il faut démarrer le Kernel entre `vendor/autoload.php` et `ecrire/inc_version.php`. Il faut aussi empiler une `Request`, sans quoi `Spip::setInput()` lève une `LogicException`. Je corrige donc `tests/bootstrap_integration.php`.
3. **Le SPIP de test.** `scripts/install-spip-test.sh` repose sur spip-cli, qui est un outil 4.x (`core:telecharger -b 4.4`, `core:installer`, `plugins:activer`…), et le `bin/spip` de 5.0 n'a aucune commande d'installation ni d'activation de plugin. Ce script ne peut donc pas produire un SPIP 5.0, et le pointer sur une autre branche avec `-b` ne marcherait pas non plus : **il faut le remplacer**. En 5.0, le site de test s'obtient avec `composer create-project` (PHP 8.4, ce que `composer.json` exige déjà).

#### Fichiers livrés

##### 1. `tests/integration/InscriptionLettreVerifierTest.php` (nouveau)

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integration test (real SPIP 5.0): formulaires_inscription_lettre_verifier_dist().
 *
 * The form reads its values through Spip::input(): in SPIP 5.0 it never sees
 * $_POST / $_GET, so inputs are injected with Spip::setInput().
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // The plugin's PHP form, loaded directly (no dependency on the SPIP path)
        include_once dirname(__DIR__, 2) . '/formulaires/inscription_lettre.php';
    }

    protected function setUp(): void
    {
        Spip::setInput('email', null);
    }

    protected function tearDown(): void
    {
        Spip::setInput('email', null);
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'        => ['pas-une-adresse'],
            'sans domaine'        => ['jean@'],
            'sans partie locale'  => ['@example.org'],
            'espace au milieu'    => ['jean dupont@example.org'],
        ];
    }

    #[DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurLeChampEmail(string $email): void
    {
        Spip::setInput('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs, "Aucune erreur pour l'adresse invalide « $email »");
        $this->assertNotSame('', (string) $erreurs['email']);
        $this->assertSame(['email'], array_keys($erreurs), 'Seul le champ email doit être en erreur');
    }

    public function testEmailValideNeProduitAucuneErreur(): void
    {
        Spip::setInput('email', 'jean.dupont@example.org');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }
}
```

Remarques :

- C'est un `PHPUnit\Framework\TestCase` simple : on appelle une fonction PHP, il n'y a pas de squelette à rendre, donc `SquelettesTestCase` n'est pas nécessaire.
- C'est un test d'intégration parce qu'il tourne contre un vrai SPIP : `Spip::input()`, `email_valide()` (`inc/filtres`) et `_T()` sont les vraies fonctions, pas des mocks.
- Pour une adresse invalide, je ne compare pas le texte exact du message : il vient de `_T('form_prop_indiquer_email')` et dépend de la langue. Le test vérifie seulement que l'erreur porte sur `email`.
- `setUp()` et `tearDown()` effacent `email` avec `Spip::setInput('email', null)` pour qu'un test ne pollue pas le suivant.

##### 2. `tests/bootstrap_integration.php` (modifié : démarrage SPIP 5.0)

```php
<?php
declare(strict_types=1);

$spipRoot = dirname(__DIR__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}

// SPIP 5.0: boot the Kernel BEFORE inc_version.php,
// otherwise: Undefined constant "_FILE_OPTIONS".
$spip5 = is_dir($spipRoot . '/vendor/spip/framework');
if ($spip5) {
    \SpipLeague\Component\Kernel\Kernel::boot();
}
require_once $spipRoot . '/ecrire/inc_version.php';

// SPIP 5.0: without a current Request, Spip::setInput() / set_request()
// throw a LogicException.
if ($spip5 && \Spip::service('request_stack')->getCurrentRequest() === null) {
    \Spip::service('request_stack')->push(\Symfony\Component\HttpFoundation\Request::create('/'));
}

include_spip('inc/plugin');
_chemin(dirname(__DIR__));   // add plugin root to SPIP path
actualise_plugins_actifs();  // activate the plugin chain

// DO NOT require tests/bootstrap.php here: its mocks (sql_countsel, autoriser…)
// conflict with the real SPIP functions already loaded above.
```

Le garde `$spip5` (présence de `vendor/spip/framework`) ne coûte rien et laisse ce bootstrap utilisable si un jour le plugin vise aussi la 4.4. Il ne faut pas le remplacer par `class_exists(Kernel::class)` : cette classe existe aussi en 4.4, mais avec un `boot()` privé.

##### 3. `scripts/install-spip-test.sh` (à remplacer)

Le script actuel télécharge un SPIP **4.4** avec spip-cli (`core:telecharger -d "$SPIP_ROOT" -b 4.4`). Ça ne peut pas fonctionner pour un plugin qui exige `[5.0.0-dev;5.*]`. En 5.0, le cœur s'installe avec Composer :

```sh
#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SPIP_ROOT="$ROOT_DIR/vendor/spip/spip"

mkdir -p "$ROOT_DIR/vendor/spip"

# SPIP 5.0 (PHP 8.4): spip-cli (core:telecharger, core:installer, plugins:activer…)
# is a 4.x tool, and SPIP 5.0's bin/spip has no install or plugin-activation command.
if [ ! -f "$SPIP_ROOT/ecrire/inc_version.php" ]; then
    composer create-project --repository=https://get.spip.net/composer \
        spip/spip "$SPIP_ROOT" 5.0.x-dev
fi

if [ ! -f "$SPIP_ROOT/config/connect.php" ]; then
    echo "SPIP 5.0 downloaded into $SPIP_ROOT but not installed." >&2
    echo "Install it (SQLite3 database) and activate the acme plugin, then rerun composer tests-integration." >&2
    exit 1
fi

echo "Integration environment ready."
```

**Limite à connaître.** Ma documentation dit seulement que le `bin/spip` de 5.0 n'a pas de commande d'installation ni d'activation de plugin. Elle ne décrit pas comment créer la base SQLite et activer `acme` sans interface sur un SPIP 5.0. Je n'ai pas voulu inventer de commande : le script s'arrête donc proprement tant que `config/connect.php` n'existe pas. Il faut faire une fois l'installation et l'activation du plugin (par exemple avec l'installateur web du SPIP de test), ou compléter le script quand une commande 5.0 existera. Pour la même raison, `spip/spip-cli` dans `require-dev` ne sert plus à rien pour ce plugin.

Pour ce test précis, on n'a pas besoin que le plugin soit activé en base : le formulaire est chargé avec `include_once`. Il faut en revanche que SPIP soit installé, puisque `composer tests-integration` vérifie la présence de `config/connect.php`.

#### Lancer le test

```bash
composer install
composer install-spip-test     # SPIP 5.0 in vendor/spip/spip/ (see the limit above)
composer tests-integration
# or just this class:
vendor/bin/phpunit --colors --testsuite integration \
  --bootstrap tests/bootstrap_integration.php \
  --filter InscriptionLettreVerifierTest
```

Résultat attendu : 5 tests verts (4 adresses invalides fournies par le data provider, plus l'adresse valide).

Je n'ai pas exécuté ces tests : aucun SPIP 5.0 n'est installé dans `vendor/spip/spip/` pour l'instant.

Détail sans conséquence : `phpunit.xml` déclare `bootstrap="tests/bootstrap.php"`, qui n'existe pas dans le dépôt. Ce n'est pas gênant ici parce que `composer tests-integration` passe `--bootstrap tests/bootstrap_integration.php`. En revanche, il faudra créer ce fichier avant de lancer `composer tests-unit`.

---

## spip-testing, après revue — cas 17, tirage 2

### Test d'intégration de `verifier()` pour `inscription_lettre`

#### Version ciblée : SPIP 5.0

Le plugin cible **SPIP 5.0 seul**. Deux indices le montrent :

- `paquet.xml` déclare `compatibilite="[5.0.0-dev;5.*]"` ;
- `composer.json` requiert `spip/framework` (`^5.x-dev`).

Le formulaire lit aussi ses saisies avec `Spip::input('email')`, qui est l'API 5.0.

Cela a trois conséquences :

1. **Les saisies du test** : en 5.0, `_request()` et `Spip::input()` ne lisent jamais les valeurs qu'un test place dans `$_POST` ou `$_GET`. Le test passe donc par `Spip::setInput('email', $v)`, et `Spip::setInput('email', null)` remet le champ à zéro entre deux tests. Je n'utilise pas `set_request()` : il est déprécié en 5.0, et ce plugin n'a pas besoin de tourner aussi sur 4.4.
2. **Le bootstrap d'intégration** : `tests/bootstrap_integration.php` est la version 4.x. En 5.0, il s'arrête avec `Undefined constant "_FILE_OPTIONS"`. Il faut démarrer le Kernel entre `vendor/autoload.php` et `ecrire/inc_version.php`. Il faut ensuite placer une `Request` dans la pile, faute de quoi `Spip::setInput()` lève une `LogicException`.
3. **L'installation du SPIP de test** : `scripts/install-spip-test.sh` télécharge SPIP 4.4 avec spip-cli (`core:telecharger -b 4.4`). spip-cli est un outil 4.x. En 5.0, `bin/spip` n'a ni commande d'installation ni commande d'activation de plugin. Il ne suffit donc pas de changer la branche passée à `-b` : il faut remplacer ce script (voir plus bas).

#### Fichiers livrés

##### 1. `tests/integration/InscriptionLettreVerifierTest.php` (nouveau)

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // SPIP est déjà chargé par le bootstrap d'intégration (_ECRIRE_INC_VERSION défini).
        // On charge le formulaire du plugin directement, sans dépendre du chemin SPIP.
        include_once dirname(__DIR__, 2) . '/formulaires/inscription_lettre.php';
    }

    protected function tearDown(): void
    {
        // SPIP 5.0 : null efface la saisie, pour ne pas polluer le test suivant
        Spip::setInput('email', null);
        parent::tearDown();
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'  => ['pas-un-email'],
            'sans domaine'  => ['jean@'],
            'sans local'    => ['@example.org'],
            'avec espace'   => ['jean dupont@example.org'],
        ];
    }

    #[DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurEmail(string $email): void
    {
        Spip::setInput('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertArrayHasKey('email', $erreurs);
        $this->assertSame(_T('form_prop_indiquer_email'), $erreurs['email']);
    }

    public function testEmailValideNeProduitAucuneErreur(): void
    {
        Spip::setInput('email', 'jean.dupont@example.org');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }
}
```

Le test appelle `formulaires_inscription_lettre_verifier_dist()` directement, avec une classe `TestCase` simple. `SquelettesTestCase` ne servirait à rien ici, puisqu'on ne rend aucun squelette. On reste au niveau intégration parce que `verifier()` utilise de vraies fonctions SPIP : `include_spip()`, `email_valide()`, `_T()` et `Spip::input()`. Les tests vérifient exactement ce que vous avez demandé : une adresse invalide donne une erreur sur `email`, une adresse valide n'en donne aucune. Le cas du champ vide (`info_obligatoire`) n'est pas couvert, mais un test de plus suffirait si vous le voulez.

##### 2. `tests/bootstrap_integration.php` (à corriger pour 5.0)

```php
<?php
declare(strict_types=1);

$spipRoot = dirname(__DIR__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}

// SPIP 5.0 : démarrer le Kernel AVANT inc_version.php (sinon « Undefined constant _FILE_OPTIONS »)
$spip5 = is_dir($spipRoot . '/vendor/spip/framework');
if ($spip5) {
    \SpipLeague\Component\Kernel\Kernel::boot();
}
require_once $spipRoot . '/ecrire/inc_version.php';

// SPIP 5.0 : une Request doit être dans la pile, sinon Spip::setInput() lève une LogicException
if ($spip5 && \Spip::service('request_stack')->getCurrentRequest() === null) {
    \Spip::service('request_stack')->push(\Symfony\Component\HttpFoundation\Request::create('/'));
}

include_spip('inc/plugin');
_chemin(dirname(__DIR__));   // ajoute la racine du plugin au chemin SPIP
actualise_plugins_actifs();  // active la chaîne des plugins

// NE PAS inclure tests/bootstrap.php ici : ses mocks entrent en conflit
// avec les vraies fonctions SPIP chargées ci-dessus.
```

Le garde `$spip5` teste la présence de `vendor/spip/framework`. Il ne faut pas le remplacer par `class_exists(Kernel::class)` : cette classe existe aussi en 4.4, avec une méthode `boot()` privée. Le garde ne coûte rien et permet de garder le même bootstrap sur les deux versions.

##### 3. `scripts/install-spip-test.sh` : la partie téléchargement doit changer

Le script actuel ne peut pas fournir un SPIP 5.0 : il télécharge SPIP 4.4 avec `core:telecharger -b 4.4`, puis active le plugin avec `plugins:activer`. En 5.0, le site de test s'obtient ainsi (PHP 8.4 requis) :

```sh
composer create-project --repository=https://get.spip.net/composer \
    spip/spip vendor/spip/spip 5.0.x-dev
```

Ce que ma documentation ne couvre pas :

- **L'installation de la base et l'activation du plugin en 5.0.** Ma documentation dit seulement que `bin/spip` (5.0) n'a pas de commande pour ça, sans donner de remplacement. Je ne vous propose donc pas de script complet. Je ne recopierais pas non plus les étapes `core:installer`, `plugins:activer` et `plugins:maj:bdd` de la version 4.x : ce serait faux en 5.0.
- **La garde de `composer tests-integration`.** Elle vérifie la présence de `vendor/spip/spip/config/connect.php`. Si l'installation 5.0 ne crée pas ce fichier, la garde bloquera les tests. À vérifier une fois l'installation 5.0 en place.

Ce test-ci dépend peu de ces deux points. Le bootstrap ajoute la racine du plugin au chemin SPIP, et le test charge le formulaire par `include_once`. `verifier()` ne fait ni requête SQL, ni appel à une autorisation : il lui faut seulement un SPIP 5.0 qui démarre.

#### Lancer le test

```bash
composer install
composer tests-integration
# ou seulement ce test :
vendor/bin/phpunit --colors --bootstrap tests/bootstrap_integration.php \
    --filter InscriptionLettreVerifierTest
```

Je n'ai pas exécuté ces tests (je n'ai pas lancé d'environnement). Pensez à lancer la commande pour confirmer qu'ils passent.

---

## spip-testing, après revue — cas 18

#### Version de SPIP visée

Le `paquet.xml` du plugin déclare `compatibilite="[4.4.0;5.*]"` : le plugin vise **SPIP 4.4 et SPIP 5.0**. Deux conséquences pour le test :

1. **Saisie des champs** : en 5.0, `_request()` ne lit plus les `$_POST` / `$_GET` qu'un test remplit. La seule API qui existe dans les deux versions est `set_request('email', $valeur)`, et c'est elle que j'utilise. Je n'utilise pas `Spip::setInput()`, qui n'existe qu'en 5.0, ni `$_POST`, que 5.0 ignore.
2. **Bootstrap d'intégration** : l'actuel `tests/bootstrap_integration.php` s'arrête sur 5.0 avec `Undefined constant "_FILE_OPTIONS"`. Je le corrige pour démarrer le Kernel et empiler une `Request` quand SPIP 5 est détecté, sans rien changer pour 4.4.

#### 1. Nouveau fichier : `tests/integration/InscriptionLettreVerifierTest.php`

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration de formulaires_inscription_lettre_verifier_dist().
 * Il tourne sur un vrai SPIP (vendor/spip/spip) : email_valide() et _T() sont les vraies fonctions.
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Le bootstrap a ajouté la racine du plugin au chemin SPIP (_chemin()) :
        // on charge le formulaire tel que SPIP le trouve.
        $fichier = find_in_path('formulaires/inscription_lettre.php', '', true);
        self::assertNotEmpty($fichier, 'formulaires/inscription_lettre.php introuvable dans le chemin SPIP');
    }

    protected function setUp(): void
    {
        // set_request() existe en 4.4 et en 5.0. Avec null, il efface la valeur.
        set_request('email', null);
    }

    protected function tearDown(): void
    {
        set_request('email', null);
    }

    public function testEmailInvalideProduitUneErreurSurLeChampEmail(): void
    {
        set_request('email', 'pas-une-adresse');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs);
        $this->assertNotSame('', (string) $erreurs['email']);
        // Le message d'erreur est le texte de form_prop_indiquer_email.
        $this->assertSame(_T('form_prop_indiquer_email'), $erreurs['email']);
    }

    public function testEmailValideNeProduitAucuneErreur(): void
    {
        set_request('email', 'lecteur@example.org');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }
}
```

#### 2. Fichier modifié : `tests/bootstrap_integration.php`

Seul le bloc de chargement de SPIP change. Le garde `$spip5` teste la présence de `vendor/spip/framework` et non `class_exists(Kernel::class)` : la classe `Kernel` existe aussi en 4.4, mais sa méthode `boot()` y est privée.

```php
<?php
declare(strict_types=1);

$spipRoot = dirname(__DIR__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}

// SPIP 5.0 : il faut démarrer le Kernel avant inc_version.php...
$spip5 = is_dir($spipRoot . '/vendor/spip/framework');
if ($spip5) {
    \SpipLeague\Component\Kernel\Kernel::boot();
}
require_once $spipRoot . '/ecrire/inc_version.php';
// ... puis empiler une Request, sans quoi set_request() lève une LogicException.
if ($spip5 && \Spip::service('request_stack')->getCurrentRequest() === null) {
    \Spip::service('request_stack')->push(\Symfony\Component\HttpFoundation\Request::create('/'));
}

include_spip('inc/plugin');
_chemin(dirname(__DIR__));   // ajoute la racine du plugin au chemin SPIP
actualise_plugins_actifs();  // active la chaîne de plugins

// NE PAS inclure tests/bootstrap.php ici : ses mocks (sql_countsel, autoriser…)
// entrent en conflit avec les vraies fonctions SPIP chargées ci-dessus.
```

#### Lancer le test

```bash
composer install
composer install-spip-test   # la première fois seulement
composer tests-integration   # ou : vendor/bin/phpunit --testsuite integration --bootstrap tests/bootstrap_integration.php --filter InscriptionLettreVerifierTest
```

Je n'ai pas lancé ces commandes. Les deux tests n'ont donc pas encore été exécutés.

#### Limite à connaître : le SPIP de test est un 4.4

`scripts/install-spip-test.sh` installe un SPIP **4.4** avec spip-cli (`core:telecharger -b 4.4`). Ces tests vérifient donc le formulaire sur 4.4 seulement. spip-cli est un outil de SPIP 4.x, et le `bin/spip` de SPIP 5.0 n'a aucune commande pour installer un site ou activer un plugin. Passer `-b` à une branche 5.x ne suffit pas : pour valider aussi sur 5.0, il faut remplacer ce script d'installation par une installation faite avec
`composer create-project --repository=https://get.spip.net/composer spip/spip <dir> 5.0.x-dev` (PHP 8.4), puis activer le plugin autrement. Le bootstrap et le test ci-dessus fonctionnent déjà sur 5.0. Il ne manque que l'installation du site de test.

---

## spip-testing, après revue — cas 19

### Test d'intégration de `verifier()` du formulaire `inscription_lettre`

#### Version de SPIP visée

Le plugin vise **SPIP 4.x**. Deux indices le montrent :

- dans `paquet.xml`, `compatibilite="[4.2.0;4.4.*]"` n'atteint pas 5.x ;
- `composer.json` ne requiert pas `spip/framework`.

Le `tests/bootstrap_integration.php` existant (boot classique via `ecrire/inc_version.php`) et `scripts/install-spip-test.sh` (spip-cli, `-b 4.4`) conviennent donc tels quels. Je n'y touche pas.

Pour simuler la saisie, j'utilise `set_request()`. Sur 4.x, `_request()` lit cette valeur, et c'est aussi la seule API qui marchera encore sous 5.0 si la compatibilité du plugin s'élargit un jour. Sous 5.0, `_request()` ne voit plus un `$_POST` rempli par un test.

#### Fichier livré

Un seul fichier nouveau, `tests/integration/InscriptionLettreVerifierTest.php`. Son nom se termine par `Test.php`, donc la suite `integration` de `phpunit.xml` le prend en compte.

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration de formulaires_inscription_lettre_verifier_dist().
 *
 * Tourne contre le SPIP réel de vendor/spip/spip/ (bootstrap_integration.php) :
 * email_valide() et _T() sont les vraies fonctions de SPIP, sans mock.
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Le bootstrap ajoute la racine du plugin au chemin SPIP (_chemin()) :
        // on charge le formulaire du plugin par le chemin SPIP.
        $fichier = find_in_path('formulaires/inscription_lettre.php', '', true);
        self::assertNotFalse($fichier, 'formulaires/inscription_lettre.php introuvable dans le chemin SPIP');
        self::assertTrue(
            function_exists('formulaires_inscription_lettre_verifier_dist'),
            'formulaires_inscription_lettre_verifier_dist() non définie'
        );
    }

    protected function setUp(): void
    {
        $this->viderSaisie();
    }

    protected function tearDown(): void
    {
        $this->viderSaisie();
    }

    private function viderSaisie(): void
    {
        set_request('email', null);
        set_request('nom', null);
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'       => ['pas-une-adresse'],
            'sans domaine'       => ['jean@'],
            'sans partie locale' => ['@example.org'],
            'avec espace'        => ['jean dupont@example.org'],
        ];
    }

    #[DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurLeChampEmail(string $email): void
    {
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs, "Aucune erreur pour l'adresse invalide « $email »");
        $this->assertNotSame('', trim((string) $erreurs['email']), "Message d'erreur vide pour « $email »");
        $this->assertSame(['email'], array_keys($erreurs), "Seul le champ email doit porter une erreur");
    }

    public static function emailsValides(): array
    {
        return [
            'simple'                  => ['jean@example.org'],
            'avec point et sous-dom.' => ['jean.dupont@mail.example.org'],
            'avec espaces autour'     => ['  jean@example.org  '], // verifier() fait un trim()
        ];
    }

    #[DataProvider('emailsValides')]
    public function testEmailValideNeProduitAucuneErreur(string $email): void
    {
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs, "Erreur inattendue pour l'adresse valide « $email »");
    }
}
```

#### Lancer les tests

```bash
composer install              # PHPUnit, spip/tests, spip-cli
composer install-spip-test    # la première fois seulement : SPIP 4.4 + SQLite dans vendor/spip/spip/, plugin acme activé
composer tests-integration    # = vendor/bin/phpunit --colors --testsuite integration --bootstrap tests/bootstrap_integration.php
```

Ne vous fiez pas au message final de `install-spip-test` : vérifiez que le plugin est bien actif.

```bash
vendor/bin/spip plugins:lister | grep -E "^[[:space:]]*acme[[:space:]]"
```

#### Remarques

- **Pourquoi un test d'intégration.** `verifier()` appelle `email_valide()` (dans `inc/filtres`) et `_T()`. Ici on teste les vraies fonctions de SPIP, pas des mocks. On vérifie seulement qu'il y a une erreur sur `email` et que son message n'est pas vide. On ne compare pas le texte exact, qui dépend de la langue et des fichiers de langue.
- **`traiter()` n'est pas couvert**, comme demandé. Il insère dans `spip_inscrits`, et cette table ne semble pas déclarée dans le plugin livré (pas de `base/` ni de `_administrations.php`). Un futur test de `traiter()` lui-même devra d'abord régler ce point.
- **`phpunit.xml` déclare `bootstrap="tests/bootstrap.php"` par défaut, mais ce fichier n'existe pas.** Ça ne gêne pas `composer tests-integration`, qui passe `--bootstrap tests/bootstrap_integration.php`. En revanche, `composer tests-unit` ou un `vendor/bin/phpunit` sans option échoueront tant que ce bootstrap de mocks n'aura pas été créé.
- **Le cas « champ vide »** (`info_obligatoire`) n'était pas demandé. Pour l'ajouter, il suffit d'une méthode qui appelle `verifier()` sans `set_request('email', …)` puis vérifie `assertArrayHasKey('email', $erreurs)`.

---

## plugin-escal, premier encadré — cas 20, tirage 1

### Installer Escal sur ce site : ce n'est pas possible tel quel

#### Ce site est un SPIP 5.0

Avant de donner des commandes, j'ai vérifié la version du site. Le repère utilisé est **`bin/spip`** :

- le fichier `bin/spip` existe à la racine du site ;
- c'est la console de SPIP 5.0 (`SpipLeague\Component\Console\Application`, démarrée par `SpipLeague\Component\Kernel\Kernel::boot()`), et non spip-cli, l'outil de SPIP 4.x.

Le site ne contient pas grand-chose d'autre : `plugins/` et `squelettes/` sont vides, et les fichiers `ecrire/`, `vendor/` et `config/connect.php` ne sont pas présents dans la copie que j'ai examinée. Je n'ai donc pas pu lire `ecrire/src/SpipVersion.php` pour confirmer. Mais `bin/spip` suffit : ce fichier n'existe que sur SPIP 5.0.

#### Escal ne fonctionne pas sur SPIP 5.0

- La dernière version d'Escal (5.6.2, publiée le 2026-10-15) déclare `compatibilite="[4.1.0;4.*]"`. SPIP 5.0 refuse ce plugin et ne l'active jamais. Même si vous le déposez dans `plugins/`, il n'apparaîtra pas comme activable.
- La recette d'installation habituelle (`spip plugins:svp:telecharger …` puis `spip plugins:activer …`) passe par **spip-cli**, l'outil de SPIP 4.x. Elle ne marche pas ici : le `bin/spip` de SPIP 5.0 n'a aucune commande pour télécharger ou activer un plugin, ni de commande `php:eval`.

Je ne vous donne donc pas de commandes d'installation : elles échoueraient, ou laisseraient le site dans un état incohérent.

**À ne pas faire :** forcer l'activation, par exemple en modifiant la balise `compatibilite` dans le `paquet.xml` d'Escal ou en définissant `_DEV_VERSION_SPIP_COMPAT`. Escal n'a pas été conçu pour SPIP 5.0, et ce contournement casserait le site, puis serait perdu à la prochaine mise à jour du plugin.

#### Les options possibles

1. **Si vous voulez Escal maintenant, installez le site en SPIP 4.4.** Escal 5.6.x est compatible `[4.1.0;4.*]`. Avec spip-cli, la recette est :

   ```bash
   # à lancer depuis la racine d'un site SPIP 4.4 (le dossier qui contient config/connect.php)
   mkdir -p plugins/auto && chmod u+rwx plugins/auto
   spip plugins:svp:depoter https://plugins.spip.net/depots/principal.xml
   spip plugins:svp:telecharger saisies verifier yaml agenda fullcalendarcompat \
        svpstats nospam facteur tri_par_rubrique aide escal -y
   spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
        svpstats nospam facteur tri_par_rubrique aide escal -y
   spip plugins:maj:bdd
   spip plugins:lister --short   # chaque préfixe ci-dessus doit apparaître, plus calendriermini
   spip cache:vider
   ```

   Deux points à vérifier sur ce site 4.4 :
   - **La version de spip-cli.** Sans le correctif MR !91, `plugins:svp:telecharger` affiche des succès sans rien télécharger. Pour savoir si votre copie est corrigée, cherchez `UPPER(pl.prefixe) = UPPER` dans `src/Command/PluginsSvpTelecharger.php`. Fiez-vous ensuite à `plugins:lister --short`, jamais aux messages de la commande de téléchargement.
   - **Les contenus de départ.** Une installation par la ligne de commande laisse les trois articles créés par Escal (*Édito*, *Accès direct*, *Mentions légales*) dans l'état `prepa`, c'est-à-dire non publiés. Publiez-les ensuite depuis l'espace privé.

2. **Si le site doit rester en SPIP 5.0, attendez une version d'Escal compatible.** Il faut que sa balise `compatibilite` couvre 5.x. Lisez le `paquet.xml` de chaque nouvelle version pour le savoir.

3. **Ou choisissez un autre squelette, compatible SPIP 5.0.**

#### Si Escal avait déjà été installé avant le passage en 5.0

Ce n'est pas le cas ici, mais pour mémoire : sur un site mis à jour vers 5.0, Escal est désactivé et le site revient aux squelettes par défaut. Ses réglages restent en base, dans la méta `escal`. Pour les consulter avec la console de SPIP 5.0 :

```bash
bin/spip config:lire escal --json
```

Ces réglages vous resserviront si le site revient en 4.4 ou si une version d'Escal compatible 5.x sort.

---

## plugin-escal, premier encadré — cas 20, tirage 2

Je ne peux pas vous donner de commandes qui installent et activent Escal sur ce site, parce que **ce site tourne sous SPIP 5.0 et Escal ne fonctionne pas sous SPIP 5.0**.

#### Comment j'ai identifié SPIP 5.0

À la racine du site se trouve **`bin/spip`**, la console de SPIP 5.0. C'est un script Symfony qui démarre `SpipLeague\Component\Kernel\Kernel` et `SpipLeague\Component\Console\Application`. Sur SPIP 4.x, ce fichier n'existe pas : on utilise spip-cli, un outil installé à part. Ce seul indice suffit à classer le site en 5.0.

Ce que j'ai sous les yeux est incomplet : il n'y a ni `ecrire/`, ni `vendor/`, ni `config/`. Seuls `bin/spip`, un dossier `plugins/` vide et un dossier `squelettes/` vide sont présents. Je n'ai donc pas pu lire la révision exacte du cœur (`ecrire/src/SpipVersion.php`). Si vous pensez que le site est en 4.4, envoyez-moi le contenu de ce fichier.

#### Pourquoi Escal ne s'active pas sous 5.0

- Sa dernière version (5.6.2, du 2026-10-15) déclare dans son `paquet.xml` la compatibilité `compatibilite="[4.1.0;4.*]"`. SPIP 5.0 refuse ce plugin, donc il ne sera jamais activé. Le site garde alors les squelettes par défaut.
- La procédure habituelle (`spip plugins:svp:telecharger …`, puis `spip plugins:activer …`, puis `spip plugins:maj:bdd`) passe par **spip-cli**, un outil de SPIP 4.x. Elle ne s'applique pas ici. Le `bin/spip` de SPIP 5.0 ne sait pas télécharger un plugin, ni l'activer, ni exécuter du PHP (`php:eval`).
- **Ne forcez pas la compatibilité**, ni en modifiant `compatibilite` dans le `paquet.xml` d'Escal, ni avec la constante `_DEV_VERSION_SPIP_COMPAT`. Escal n'est pas prévu pour SPIP 5.0.

#### Ce que vous pouvez faire

1. **Garder ou remettre le site en SPIP 4.4**, puis installer Escal avec spip-cli. Je vous donnerai alors la procédure complète :
   - dépôt SVP ;
   - téléchargement d'Escal et de ses dépendances ;
   - activation ;
   - `plugins:maj:bdd` ;
   - publication des articles de démarrage.
2. **Attendre une version d'Escal dont la `compatibilite` couvre SPIP 5.x.** Avant toute installation, lisez le `paquet.xml` de la version que vous avez.
3. **Choisir un autre squelette** qui est compatible SPIP 5.0.

#### Commandes utiles dès maintenant sur ce site (5.0)

Le `bin/spip` de SPIP 5.0 a quand même deux commandes utiles. La première vérifie qu'il ne reste pas de configuration Escal en base (cas d'un site migré depuis la 4.x) :

```bash
# Depuis la racine du site
bin/spip config:lire escal --json
# Le JSON est indenté ("cle": null) : on teste l'absence ainsi
bin/spip config:lire escal --json | grep -Eq '": ?null' && echo "pas de config Escal en base" || echo "config Escal conservée en base"
```

La seconde vide le cache :

```bash
bin/spip spip:cache:clear
```

Dites-moi laquelle des trois options vous choisissez. Si c'est le retour en 4.4, je vous donne la procédure d'installation complète.

---

## plugin-escal, premier encadré — cas 21, tirage 1

### Pourquoi le look Escal a disparu et comment le récupérer

#### En bref

Votre site est passé en **SPIP 5.0**, et **Escal ne fonctionne pas sous SPIP 5.0**. Ce n'est pas un réglage perdu. SPIP refuse d'activer Escal, donc la page d'accueil utilise les squelettes par défaut. Aucune commande ne peut « réactiver » Escal sur ce site tant qu'il reste en 5.0.

#### Ce que j'ai constaté dans le site

1. **Le site est en SPIP 5.0.** Le dossier `vendor/spip/framework/` est présent (paquet Composer `spip/framework`, qui demande PHP `^8.4`). C'est l'un des signes qui identifient un SPIP 5.0. Un SPIP 4.x n'a pas ce dossier.
2. **La version d'Escal installée ne déclare pas SPIP 5 comme compatible.** C'est la dernière version, 5.6.2, dans `plugins/auto/escal/v5.6.2/paquet.xml` :

   ```xml
   <paquet
       prefix="escal"
       categorie="squelette"
       version="5.6.2"
       schema="1.0.17"
       etat="stable"
       compatibilite="[4.1.0;4.*]"
   ```

   La plage `[4.1.0;4.*]` s'arrête à SPIP 4.x. SPIP 5.0 n'active donc pas le plugin. Les squelettes d'Escal ne sont plus utilisés et le site affiche les squelettes par défaut. **Votre configuration Escal n'est pas perdue :** la méta `escal` (blocs, mise en page, couleurs…) reste en base.

#### Vérifier vous-même (commandes SPIP 5.0)

Sous SPIP 5.0, l'outil en ligne de commande est `bin/spip`. L'ancien `spip-cli` de SPIP 4 ne s'applique pas ici. `bin/spip` n'a d'ailleurs pas de commande pour télécharger ou activer un plugin. Depuis la racine du site :

```bash
# 1. Confirmer la version de SPIP (affiche VERSION = '5.…')
grep -n "VERSION" ecrire/src/SpipVersion.php
ls bin/spip vendor/spip/framework/   # présents seulement en SPIP 5.0

# 2. Lire la compatibilité déclarée par Escal
grep -n 'compatibilite=' plugins/auto/escal/*/paquet.xml | head -1

# 3. Vérifier que vos réglages Escal sont toujours en base
bin/spip config:lire escal --json
# Le JSON est indenté : on teste l'absence avec '": ?null'
bin/spip config:lire escal --json | grep -Eq '": ?null' \
  && echo "aucune config Escal" || echo "config Escal conservée"
```

#### Les solutions possibles

**À ne pas faire :** forcer l'activation, par exemple en changeant `compatibilite` dans `paquet.xml` ou en définissant `_DEV_VERSION_SPIP_COMPAT`. Escal n'a pas été écrit pour SPIP 5.0, ce qui risque de casser le site. En plus, la prochaine mise à jour du plugin effacera la modification.

Il reste trois possibilités :

1. **Revenir en SPIP 4.4 (le plus rapide pour retrouver Escal).** Restaurez la sauvegarde d'avant la mise à jour : les fichiers et la base. Votre réglage Escal étant toujours en base, l'apparence revient telle quelle. Une fois de retour en 4.4, avec spip-cli :

   ```bash
   spip plugins:lister --short     # « escal » doit apparaître
   # s'il n'est pas actif :
   spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
        svpstats nospam facteur tri_par_rubrique aide escal -y
   spip plugins:maj:bdd
   spip cache:vider
   ```

2. **Rester en SPIP 5.0 et attendre une version d'Escal compatible**, c'est-à-dire dont le `paquet.xml` déclare une `compatibilite` qui couvre 5.x. Avant d'installer une nouvelle version, contrôlez-le avec la commande `grep` du point 2 ci-dessus. Vos réglages, toujours en base, seront alors repris. Après l'installation, videz le cache :

   ```bash
   bin/spip spip:cache:clear
   ```

3. **Choisir un autre jeu de squelettes compatible SPIP 5.0**, si vous devez rester en 5.0 sans attendre.

Si le site est en production et que l'apparence compte, je recommande la solution 1 : restaurer la 4.4 et attendre qu'Escal annonce sa compatibilité avec SPIP 5 avant de refaire la migration.

---

## plugin-escal, premier encadré — cas 21, tirage 2

### Pourquoi Escal ne s'affiche plus, et ce que vous pouvez faire

#### Diagnostic : la mise à jour a fait passer le site en SPIP 5.0

Votre site tourne désormais sous **SPIP 5.0**. Je m'appuie sur ce repère : le répertoire
`vendor/spip/framework/` existe. Son `composer.json` est celui de `spip/framework`, qui exige
`php ^8.4`. Ce paquet n'existe pas sous SPIP 4.x.

La version d'Escal présente, `plugins/auto/escal/v5.6.2/paquet.xml`, déclare :

```xml
version="5.6.2"
compatibilite="[4.1.0;4.*]"
```

Cette plage s'arrête à la branche 4. **SPIP 5.0 refuse donc d'activer Escal.** Le site retombe
sur les squelettes par défaut de SPIP, d'où la page d'accueil sans le look Escal. Vos réglages
Escal sont toujours en base (la meta `escal`, casier `escal/config`) : rien n'est perdu.

**Aucune commande ne réactive Escal sur ce site en 5.0.** En particulier :

- **ne forcez pas** l'activation, ni en modifiant `compatibilite` dans le `paquet.xml`, ni avec
  `_DEV_VERSION_SPIP_COMPAT`. Le plugin n'a pas été écrit pour 5.0 : vous obtiendriez des erreurs
  ou un site cassé, et la prochaine mise à jour d'Escal écraserait de toute façon la modification ;
- les commandes spip-cli habituelles (`spip plugins:activer escal`, `plugins:svp:telecharger`,
  `php:eval`) **ne s'appliquent pas** à un site 5.0. spip-cli est un outil de SPIP 4.x, et le
  `bin/spip` de SPIP 5.0 n'a pas de commande de téléchargement, d'activation de plugin ni
  `php:eval`.

#### Les options réalistes

1. **Revenir en SPIP 4.4** (recommandé si le look Escal est indispensable tout de suite) :
   restaurez la sauvegarde d'avant la mise à jour (fichiers et base). Escal 5.6.2 y fonctionne
   (`[4.1.0;4.*]`) et retrouve sa configuration.
2. **Rester en 5.0 et attendre** une version d'Escal dont le `paquet.xml` déclare une
   compatibilité qui couvre 5.x. Avant d'installer une nouvelle version, ouvrez son `paquet.xml`
   et lisez l'attribut `compatibilite`.
3. **Choisir un autre squelette** compatible avec SPIP 5.0.

#### Commandes utiles en attendant (SPIP 5.0, `bin/spip`)

Lancez-les depuis la racine du site.

```bash
# Vérifier que la configuration Escal est toujours en base (lecture seule)
bin/spip config:lire escal --json

# Tester la présence d'une clé : la sortie JSON est indentée ("cle": null),
# il faut donc un motif qui tolère l'espace
bin/spip config:lire escal_base_version --json | grep -Eq '": ?null' \
  && echo "absente" || echo "présente"

# Après un retour en 4.4 ou l'installation d'une version compatible 5.x : vider le cache
bin/spip spip:cache:clear
```

Si vous revenez en **SPIP 4.4**, ce sont alors les commandes spip-cli 4.x qui s'appliquent, par
exemple :

```bash
spip plugins:lister --short     # escal doit apparaître dans la liste
spip plugins:activer escal -y   # seulement s'il n'est pas actif
spip cache:vider
```

Pensez aussi à prévenir votre hébergeur ou la personne qui a fait la mise à jour : avec Escal, le
site doit rester en 4.4 tant qu'aucune version compatible 5.x n'est sortie.

---

## plugin-escal, premier encadré — cas 22

#### Ce que j'ai vérifié sur le site

**Version de SPIP : 4.4.22.** Je me suis appuyé sur `ecrire/inc_version.php`, qui déclare `$spip_version_branche = '4.4.22'` et `_SPIP_VERSION_ID = 40422`. Aucun des marqueurs de SPIP 5.0 n'est présent : pas de `ecrire/src/SpipVersion.php`, pas de `bin/spip`, pas de `vendor/spip/framework/` ni de `plugins-dist/spip/`. Escal 5.6.x (`compatibilite="[4.1.0;4.*]"`) peut donc être installé ici.

Point à surveiller : ce `inc_version.php` utilise le noyau `SpipLeague\Component\Kernel` (`app()`, `param()`), ce qu'on ne s'attend pas à trouver dans un SPIP 4.4 classique. Si `spip plugins:activer` refuse Escal pour cause d'incompatibilité, le cœur est en réalité un 5.x. Dans ce cas, n'essayez pas de forcer (ne modifiez ni `compatibilite` ni `_DEV_VERSION_SPIP_COMPAT`). Escal ne fonctionne pas sur SPIP 5.0.

**État actuel :** `plugins/` et `squelettes/` sont vides (Escal n'est pas encore installé). Je ne vois pas de `config/connect.php` : le site doit d'abord avoir été installé (base configurée) pour que spip-cli puisse fonctionner. Lancez toutes les commandes **depuis la racine du site**, c'est-à-dire le dossier qui contient `ecrire/` et `config/connect.php`.

Escal est un **plugin** de catégorie « squelette », de préfixe `escal`. On ne le copie donc pas dans `squelettes/` : on le télécharge et on l'active comme un plugin. Le dossier `squelettes/` vous servira plus tard pour les personnalisations.

#### 0. Vérifier que votre spip-cli sait télécharger

La version 2.0.1 de spip-cli, sans correctif, ne télécharge rien avec `plugins:svp:telecharger`, mais **affiche quand même un succès** (corrigé par la MR !91). Dans le dossier source de spip-cli :

```bash
grep -q 'UPPER(pl.prefixe) = UPPER' src/Command/PluginsSvpTelecharger.php \
  && echo "patché (MR!91)" || echo "NON patché — les téléchargements échoueront en silence"
```

Si ce n'est pas corrigé, déposez les plugins à la main dans `plugins/` (ou passez par l'espace privé, menu Plugins → Ajouter). Utilisez ensuite spip-cli seulement pour l'étape 3 (activation).

#### 1. Préparer le dossier et le dépôt de plugins

```bash
# SVP décompresse dans plugins/auto/, que SPIP ne crée pas de lui-même
mkdir -p plugins/auto && chmod u+rwx plugins/auto

# Déclarer le dépôt officiel (une seule fois)
spip plugins:svp:depoter https://plugins.spip.net/depots/principal.xml
```

#### 2. Télécharger Escal et ses dépendances

```bash
spip plugins:svp:telecharger saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y
```

`calendriermini` est aussi téléchargé comme dépendance indirecte : attendez-vous à 11 paquets. SVP les range par version, par exemple `plugins/auto/escal/v5.6.x/`.

**Ne vous fiez pas au message de cette commande.** Vérifiez que les dossiers sont bien arrivés :

```bash
ls plugins/auto/
```

#### 3. Activer (dépendances d'abord), puis mettre à jour la base

```bash
spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y

spip plugins:maj:bdd        # doit afficher « Installation du plugin Escal … MAJ 1.0.17 »
spip plugins:lister --short # tous les préfixes ci-dessus doivent y figurer, escal compris
```

À l'activation, `escal_upgrade()` crée la meta `escal` (casier `escal/config`), les groupes de mots-clés `affichage` et `Agenda_couleur`, une rubrique cachée et trois articles de départ. N'écrivez jamais la meta `escal` à la main pour « installer » Escal.

Vérifier que l'installation a bien eu lieu :

```bash
spip config:lire escal_base_version --json   # {"escal_base_version":null} => PAS installé
```

#### 4. Publier les contenus de départ (indispensable après une installation en ligne de commande)

Quand on installe depuis spip-cli, aucun auteur n'est connecté. Les articles *Édito*, *Accès direct* et *Mentions légales* restent donc « en cours de rédaction » (`prepa`), et les blocs correspondants s'affichent vides. On les publie en ouvrant une session webmestre dans la CLI :

```bash
spip php:eval 'include_spip("action/editer_objet"); include_spip("base/abstract_sql");
$GLOBALS["visiteur_session"] = sql_fetsel("*", "spip_auteurs", "statut=" . sql_quote("0minirezo"));
$GLOBALS["visiteur_session"]["webmestre"] = "oui";
foreach (sql_allfetsel("id_article", "spip_articles", "statut=" . sql_quote("prepa")) as $x) {
  objet_instituer("article", $x["id_article"], ["statut" => "publie"]);
}'
```

Attention : ce script publie **tous** les articles en `prepa`. Sur un site qui contient déjà des brouillons, limitez la requête aux articles créés par Escal.

#### 5. Vider le cache

```bash
spip cache:vider
```

#### Ensuite

- La configuration se fait dans **Espace privé → Squelettes → Escal** (réservé aux webmestres) : mise en page, choix des blocs dans chaque emplacement, etc.
- Plugins facultatifs qu'Escal reconnaît s'ils sont présents : `palette`, `spip_400`, `orr`, `Shoutbox`, `reservation_evenement`, `reservations_multiples`.
- Pour personnaliser l'apparence, ne modifiez jamais les fichiers dans `plugins/auto/escal/` : une mise à jour les écraserait. Utilisez `squelettes/styles/perso.css`, `squelettes/persoconfig.css.html`, ou surchargez un bloc avec `squelettes/inclusions/inc-‹nom›.html`.

---

## plugin-escal, encadré final (test d'absence sur une seule clé) — cas 20

Je ne vais pas te donner de commandes d'installation : **ce site est en SPIP 5.0, et Escal ne fonctionne pas sous SPIP 5.0.**

#### Pourquoi je dis que c'est du 5.0

L'indice que j'ai utilisé, c'est la présence de **`bin/spip`** à la racine du site. C'est la console de SPIP 5.0 : un script PHP basé sur `SpipLeague\Component\Console\Application` et `SpipLeague\Component\Kernel\Kernel`, qui charge `vendor/autoload_runtime.php`. SPIP 4.x n'a pas ce fichier, il utilise l'outil externe spip-cli (commande `spip`).

Le reste du dossier est presque vide : seulement `bin/`, `plugins/` (vide) et `squelettes/` (vide). Il n'y a ni `ecrire/`, ni `vendor/`, ni `config/connect.php`. Je n'ai donc pas pu lire `ecrire/src/SpipVersion.php` pour confirmer le numéro de version. Pour vérifier de ton côté :

```bash
grep VERSION ecrire/src/SpipVersion.php   # attendu : VERSION = '5.…'
ls -d vendor/spip/framework plugins-dist/spip 2>/dev/null
```

#### Pourquoi Escal ne peut pas être activé

- La dernière version d'Escal (5.6.2, du 2026-10-15) déclare `compatibilite="[4.1.0;4.*]"` dans son `paquet.xml`. SPIP 5.0 refuse d'activer un plugin dont la plage de compatibilité ne couvre pas la 5.x. Escal ne sera donc jamais activé, quelle que soit la méthode utilisée (espace privé ou ligne de commande).
- Les commandes habituelles (`spip plugins:svp:telecharger`, `spip plugins:activer`, `spip plugins:maj:bdd`, `spip php:eval`) viennent de spip-cli, un outil de SPIP 4.x. Le `bin/spip` de SPIP 5.0 n'a **aucune** commande pour télécharger ou activer un plugin, ni de `php:eval`. Il propose seulement `config:lire`, `config:ecrire` et `spip:cache:clear`.
- **Ne force pas l'installation** : ne modifie pas le `compatibilite` du `paquet.xml`, et ne définis pas `_DEV_VERSION_SPIP_COMPAT`. Escal n'a pas été écrit pour SPIP 5.0, donc tu aurais un site cassé, et ces modifications seraient perdues à chaque mise à jour.

Si tu récupères une archive d'Escal, lis d'abord son `paquet.xml` :

```bash
grep -o 'compatibilite="[^"]*"' chemin/vers/escal/paquet.xml
```

Tant que la plage ne va pas jusqu'à `5.*`, rien ne change.

#### Ce que tu peux faire

1. **Rester sur SPIP 4.4 (ou y revenir)** si Escal est indispensable. Sur un site 4.4 avec spip-cli, l'installation se fait avec `plugins:svp:telecharger` puis `plugins:activer` (Escal et ses dépendances : saisies, verifier, yaml, agenda, fullcalendarcompat, svpstats, nospam, facteur, tri_par_rubrique, aide), suivis de `plugins:maj:bdd`. Je peux te détailler la marche à suivre si tu choisis cette option.
2. **Attendre une version d'Escal** dont la `compatibilite` inclut SPIP 5.x.
3. **Choisir un autre squelette** compatible SPIP 5.0.

#### Si le site vient d'une mise à jour d'un site 4.x qui utilisait Escal

Après le passage en 5.0, le site utilise les squelettes par défaut, mais les réglages d'Escal (meta `escal`) restent dans la base. Pour savoir si Escal a déjà été installé, interroge seulement la clé de version (et jamais toute la meta `escal`, où n'importe quel réglage `null` donnerait un faux résultat) :

```bash
if bin/spip config:lire escal_base_version --json | grep -Eq '": ?null'; then
  echo "Escal n'a jamais été installé sur cette base"
else
  echo "Escal a été installé ; ses réglages sont conservés"
  bin/spip config:lire escal --json   # pour consulter les réglages conservés
fi
```

Ces réglages seront réutilisés si tu reviens en 4.4, ou quand une version d'Escal compatible avec SPIP 5 sortira.

---

## plugin-escal, encadré final — cas 21, tirage 1

### Pourquoi le site n'a plus le look Escal

Désolé, mais ça ne se règle pas avec une commande. Votre mise à jour a fait passer le site en **SPIP 5.0**, et **Escal ne fonctionne pas sous SPIP 5.0**. SPIP n'a donc pas activé le squelette et affiche à la place ses squelettes par défaut. C'est pour ça que la page d'accueil a changé d'apparence.

#### Ce que j'ai vérifié

1. **Le site tourne sous SPIP 5.0.** Le dossier `vendor/spip/framework/` existe, et on ne le trouve que sur une installation 5.0. Son `composer.json` demande d'ailleurs `"php": "^8.4"` et les paquets `spip-league/*` du nouveau noyau.
2. **La version d'Escal installée refuse SPIP 5.** Le fichier `plugins/auto/escal/v5.6.2/paquet.xml` indique :

   ```xml
   <paquet prefix="escal" categorie="squelette" version="5.6.2" schema="1.0.17"
           compatibilite="[4.1.0;4.*]" ...>
   ```

   La 5.6.2 (sortie le 2026-10-15) est la dernière version d'Escal, et elle ne déclare que la branche 4.x. Avec `[4.1.0;4.*]`, SPIP 5.0 n'active pas le plugin. Le site affiche donc les squelettes par défaut.

**Bonne nouvelle : vos réglages ne sont pas perdus.** La configuration d'Escal (la meta `escal` : blocs, mise en page, couleurs…) reste dans la base. Elle sera à nouveau utilisée dès que le site tournera avec une version de SPIP qu'Escal accepte.

#### Ce qu'il ne faut pas faire

Ne forcez pas l'activation. Ne modifiez pas `compatibilite` dans le `paquet.xml` et ne définissez pas `_DEV_VERSION_SPIP_COMPAT`. Escal n'a pas été écrit pour SPIP 5.0, qui a changé ou retiré plusieurs API et syntaxes de squelettes. Vous auriez au mieux un site à moitié cassé, au pire des erreurs PHP. Et ces bricolages seraient de toute façon écrasés à la prochaine mise à jour du plugin.

Les commandes spip-cli qu'on trouve dans les tutoriels Escal (`spip plugins:activer escal`, `spip plugins:svp:telecharger …`) ne servent pas non plus. spip-cli est un outil pour SPIP 4.x. L'outil de SPIP 5.0, `bin/spip`, n'a pas de commande pour télécharger ou activer un plugin.

#### Commandes de vérification (SPIP 5.0)

À lancer depuis la racine du site, si `bin/spip` est présent dans votre installation. Je ne l'ai pas trouvé dans les fichiers que j'ai pu consulter.

```bash
# 1. Confirmer la version de SPIP
ls -d vendor/spip/framework bin/spip plugins-dist/spip 2>/dev/null
grep -n "VERSION" ecrire/src/SpipVersion.php 2>/dev/null

# 2. Confirmer la compatibilité déclarée par Escal
grep -n 'compatibilite=' plugins/auto/escal/*/paquet.xml

# 3. Vérifier que les réglages Escal sont toujours en base
bin/spip config:lire escal --json

# 4. Vérifier qu'Escal a bien été installé un jour (lire UNE seule clé)
bin/spip config:lire escal_base_version --json \
  | grep -Eq '": ?null' && echo "Escal jamais installé" || echo "Escal installé, réglages conservés"
```

N'appliquez le test `null` de l'étape 4 qu'à la clé `escal_base_version`. Sur toute la meta `escal`, n'importe quel réglage vide donnerait un faux résultat.

#### Les solutions possibles

1. **Revenir à SPIP 4.4. C'est la seule façon de retrouver Escal tout de suite.** Restaurez la sauvegarde d'avant la mise à jour (fichiers et base), ou réinstallez le site en SPIP 4.4. Escal se réactive alors avec la configuration que vous aviez. Ensuite, sous 4.4, videz le cache :

   ```bash
   spip cache:vider
   ```

2. **Rester en SPIP 5.0 et attendre une version d'Escal compatible**, c'est-à-dire une version dont le `paquet.xml` déclare une `compatibilite` qui va jusqu'à 5.x. Pour savoir si elle est sortie, regardez la page du plugin (<https://contrib.spip.net/Escal-4077>) ou relancez la commande 2 après avoir mis le plugin à jour. Vos réglages, restés en base, seront repris. Après la mise à jour du plugin, videz le cache :

   ```bash
   bin/spip spip:cache:clear
   ```

3. **Rester en SPIP 5.0 et choisir un autre squelette** qui est compatible avec SPIP 5.0.

Si le site doit garder son apparence Escal dès maintenant, je vous conseille la solution 1 : revenir à SPIP 4.4 en attendant qu'une version d'Escal compatible avec SPIP 5 soit publiée.

---

## plugin-escal, encadré final — cas 21, tirage 2

### Pourquoi le look Escal a disparu, et ce qu'on peut faire

#### Diagnostic : la mise à jour a fait passer le site en SPIP 5.0

Le dossier du site contient `vendor/spip/framework/`. Ce dossier n'existe que sur SPIP 5.0 : votre mise à jour a donc fait passer le site de SPIP 4.x à **SPIP 5.0**.

Escal est bien téléchargé, dans `plugins/auto/escal/v5.6.2/`. Mais son `paquet.xml` déclare :

```xml
<paquet prefix="escal" categorie="squelette" version="5.6.2" ... compatibilite="[4.1.0;4.*]">
```

La version 5.6.2 est la dernière publiée d'Escal (2026-10-15). Elle se déclare compatible avec SPIP 4.1 à 4.x seulement, et **SPIP 5.0 refuse d'activer un plugin dont la compatibilité n'inclut pas 5.x**. Escal n'est donc plus actif, et SPIP affiche ses squelettes par défaut : c'est ce que vous voyez sur la page d'accueil.

Vos réglages Escal ne sont pas perdus. La méta `escal` (casier `escal/config` : blocs, layout, couleurs…) reste en base. Elle ne sert plus à rien tant qu'Escal n'est pas actif.

**Escal ne peut donc pas être réactivé sur ce site en SPIP 5.0.** Aucune commande ne le permet proprement, et `bin/spip` (la console de SPIP 5.0) n'a pas de commande pour activer ou télécharger un plugin.

À ne pas faire : modifier `compatibilite` dans le `paquet.xml` d'Escal, ou forcer avec `_DEV_VERSION_SPIP_COMPAT`. Escal n'a pas été écrit pour SPIP 5.0 : vous auriez au mieux un site instable, au pire des erreurs PHP. Et la prochaine mise à jour du plugin écraserait votre modification.

#### Vérifications (lecture seule)

À lancer depuis la racine du site :

```bash
# 1. Confirmer la version de SPIP (marqueurs de SPIP 5.0)
ls -d vendor/spip/framework bin/spip 2>/dev/null
grep -h "VERSION" ecrire/src/SpipVersion.php 2>/dev/null

# 2. Vérifier ce que déclare la version d'Escal présente
grep -o 'compatibilite="[^"]*"' plugins/auto/escal/*/paquet.xml | head -1
#  → compatibilite="[4.1.0;4.*]" : pas de 5.x, donc pas activable en 5.0

# 3. Vérifier qu'Escal avait bien été installé (une seule clé, en JSON)
bin/spip config:lire escal_base_version --json
bin/spip config:lire escal_base_version --json | grep -Eq '": ?null' \
  && echo "Escal jamais installé" || echo "Escal installé auparavant : réglages conservés en base"

# 4. (facultatif) Sauvegarder vos réglages Escal pour plus tard
bin/spip config:lire escal --json > escal-config-sauvegarde.json
```

#### Les options possibles

##### Option 1 (recommandée si le look Escal doit revenir vite) : revenir en SPIP 4.4

Restaurez le site en SPIP 4.4 à partir de la sauvegarde faite avant la mise à jour : fichiers **et** base de données. Une fois revenu en 4.x, Escal est de nouveau compatible. S'il n'est pas actif après la restauration, réactivez-le avec spip-cli, l'outil de SPIP 4.x, depuis la racine du site :

```bash
spip plugins:lister --short | grep -w escal || \
  spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
       svpstats nospam facteur tri_par_rubrique aide escal -y
spip plugins:maj:bdd
spip plugins:lister --short     # escal et ses dépendances doivent apparaître
spip cache:vider
```

Les réglages repartent de la méta `escal`, conservée en base : vous retrouvez votre mise en page.

##### Option 2 : rester en SPIP 5.0 et attendre une version d'Escal compatible

Surveillez la page du plugin (https://contrib.spip.net/Escal-4077). Avant de réactiver quoi que ce soit, lisez le `paquet.xml` de chaque nouvelle version : il faut que `compatibilite` couvre la 5.x. En attendant, le site garde l'apparence par défaut. Après chaque changement de squelette, videz le cache :

```bash
bin/spip spip:cache:clear
```

##### Option 3 : rester en SPIP 5.0 avec un autre squelette

Choisissez un jeu de squelettes dont le `paquet.xml` déclare une compatibilité 5.x. Vos réglages Escal resteront en base, sans effet, au cas où vous reviendriez à Escal plus tard.

#### En résumé

- La disparition du look Escal vient du passage en SPIP 5.0, pas d'une erreur de réglage.
- Escal 5.6.2 (`[4.1.0;4.*]`) ne peut pas tourner sur SPIP 5.0, et il ne faut pas le forcer.
- Pour retrouver le look tout de suite : restaurer le site en SPIP 4.4 (option 1).
- Sinon : rester en 5.0 avec l'apparence par défaut ou un autre squelette, en attendant une version d'Escal compatible 5.x.

## plugin-escal, encadré après revue (retour en 4.4 = sauvegarde fichiers et base) — cas 20

Je ne vais pas vous donner de commandes d'installation pour Escal : **votre site tourne sous SPIP 5.0, et Escal ne fonctionne pas sur SPIP 5.0.**

## Pourquoi je dis que le site est en SPIP 5.0

Le repère que j'ai utilisé, c'est le fichier `bin/spip` à la racine du site. C'est la console de SPIP 5.0 : il démarre `SpipLeague\Component\Kernel\Kernel` et `SpipLeague\Component\Console\Application`. Un site SPIP 4.x n'a pas ce fichier. Il s'administre en ligne de commande avec spip-cli, qui est un outil séparé.

Remarque : le dossier que j'ai pu consulter ne contient que `bin/`, `plugins/` et `squelettes/` (ces deux derniers sont vides). `ecrire/` et `vendor/` sont absents, donc je n'ai pas pu lire le numéro de version exact dans `ecrire/src/SpipVersion.php`. Si vous pensez que le site est en réalité en 4.x, vérifiez ce fichier : la ligne `VERSION = '5.…'` tranche la question.

## Pourquoi Escal ne s'installera pas

- La dernière version d'Escal (5.6.2, du 15/10/2026) déclare dans son `paquet.xml` une compatibilité `[4.1.0;4.*]`. SPIP 5.0 refuse donc de l'activer.
- Il ne faut pas contourner ce blocage, ni en modifiant `compatibilite` dans le `paquet.xml`, ni avec `_DEV_VERSION_SPIP_COMPAT`. Le squelette n'a pas été prévu pour 5.0 et le site risquerait de casser.
- Les recettes d'installation habituelles (`spip plugins:svp:telecharger …`, `spip plugins:activer …`) viennent de spip-cli, qui ne sert qu'en 4.x. Le `bin/spip` de SPIP 5.0 n'a aucune commande pour télécharger ou activer un plugin.

## Ce que vous pouvez faire

1. **Garder un site en SPIP 4.4** si Escal est indispensable. Pour un nouveau site, cela veut dire l'installer directement en 4.4. Si ce site a été migré depuis une 4.4, le seul retour possible passe par la **sauvegarde d'avant la migration, fichiers et base de données ensemble**. Ne réinstallez pas une 4.4 sur la base déjà migrée : SPIP 5.0 réécrit les réglages en JSON, que la 4.4 ne sait pas relire, et vous perdriez vos réglages Escal. Sans cette sauvegarde, je ne connais pas de retour vérifié qui conserve les réglages.
2. **Attendre une version d'Escal** dont la compatibilité inclut SPIP 5.x. Vérifiez le champ `compatibilite` du `paquet.xml` de chaque nouvelle version avant d'essayer.
3. **Choisir un autre squelette** compatible SPIP 5.0.

## Les seules commandes utiles aujourd'hui

À lancer depuis la racine du site, une fois `vendor/` installé (`composer install`). Elles ne font que lire :

```bash
# Escal a-t-il déjà été installé sur ce site (par exemple avant une migration) ?
bin/spip config:lire escal_base_version --json \
  | grep -Eq '": ?null' && echo "Escal n'a jamais été installé" || echo "Escal a déjà été installé"

# Si oui, voir les réglages Escal conservés en base
bin/spip config:lire escal --json
```

Lancez ce test sur la seule clé `escal_base_version`, pas sur toute la méta `escal` : n'importe quel réglage vide (`null`) donnerait sinon un faux résultat. Si Escal a déjà été installé, ses réglages restent en base même s'il n'est pas actif. Le site utilise alors les squelettes par défaut de SPIP.

Dites-moi quelle option vous choisissez (rester en 4.4, attendre ou changer de squelette) et je vous donnerai la marche à suivre et les commandes correspondantes.

## plugin-escal, encadré après revue (retour en 4.4 = sauvegarde fichiers et base) — cas 21, tirage 1

Bonjour,

Mauvaise nouvelle d'abord : aucune commande ne réactive Escal sur ce site, et il ne faut pas essayer de le forcer. Voici pourquoi, puis ce que vous pouvez faire.

## Ce qui s'est passé

**Votre site est passé en SPIP 5.0.** Je m'appuie sur ce repère : le dossier `vendor/spip/framework/` est présent (son `composer.json` décrit le « Framework pour les applications SPIP » et demande PHP ^8.4). Ce dossier n'existe qu'en SPIP 5.0.

**Escal ne fonctionne pas sous SPIP 5.0.** La version installée, `plugins/auto/escal/v5.6.2/` (c'est aussi la dernière parue, le 2026-10-15), déclare dans son `paquet.xml` :

```xml
compatibilite="[4.1.0;4.*]"
```

SPIP 5.0 refuse donc de l'activer. Le site affiche alors les squelettes par défaut de SPIP, et c'est pour ça que votre page d'accueil n'a plus le look Escal. Rien n'est cassé dans votre configuration : SPIP a simplement désactivé un plugin qu'il ne sait pas faire tourner.

Vos réglages Escal (mise en page, noisettes, couleurs) sont restés dans la base, dans la méta `escal`. Ils sont seulement inutilisés.

## Ce qu'il ne faut pas faire

- Ne modifiez pas la ligne `compatibilite` du `paquet.xml` d'Escal.
- Ne définissez pas `_DEV_VERSION_SPIP_COMPAT` pour contourner le contrôle.

Avec l'une ou l'autre, SPIP chargerait un squelette écrit pour 4.x sur un cœur dont l'API a changé. Vous risquez des pages en erreur, et une prochaine mise à jour du plugin écraserait de toute façon la modification.

## Vos trois options

1. **Revenir en SPIP 4.4** et retrouver Escal tel qu'il était. Il faut pour cela **restaurer la sauvegarde faite avant la migration, à la fois les fichiers et la base de données**. Ne réinstallez surtout pas SPIP 4.4 par-dessus la base migrée : SPIP 5.0 a réécrit les réglages au format JSON, que SPIP 4.4 ne sait pas relire, et vos réglages Escal seraient perdus. **Si vous n'avez pas de sauvegarde d'avant la migration, je ne connais pas de retour vérifié vers 4.4 qui conserve vos réglages.**
2. **Rester en SPIP 5.0 et attendre une version d'Escal compatible**, c'est-à-dire une version dont le `paquet.xml` annonce une `compatibilite` qui inclut 5.x. Vos réglages, toujours en base, seront alors disponibles. Pour suivre les annonces : <https://contrib.spip.net/Escal-4077>.
3. **Rester en SPIP 5.0 et choisir un autre squelette**, compatible 5.0.

## Commandes utiles en attendant

Sous SPIP 5.0, l'outil en ligne de commande est `bin/spip`, à lancer depuis la racine du site. Les commandes `spip plugins:activer` et `spip php:eval` qu'on trouve dans les tutoriels Escal viennent de spip-cli, un outil de SPIP 4.x : elles ne s'appliquent pas ici, et `bin/spip` n'a aucune commande pour activer un plugin. (Le dossier `bin/` n'apparaît pas dans la copie du site que j'ai consultée : lancez les commandes sur le serveur.)

Pour vérifier la version d'Escal présente et sa compatibilité :

```bash
grep -E 'version=|compatibilite=' plugins/auto/escal/*/paquet.xml | head -4
```

Pour voir les réglages Escal conservés en base :

```bash
bin/spip config:lire escal --json
```

Pour savoir si Escal a déjà été installé sur cette base (le test porte sur la seule clé `escal_base_version`) :

```bash
if bin/spip config:lire escal_base_version --json | grep -Eq '": ?null'; then
  echo "Escal n'a jamais été installé sur cette base"
else
  echo "Réglages Escal présents en base"
fi
```

Pour vider le cache après tout changement de squelette (par exemple le jour où une version d'Escal compatible 5.x sera installée) :

```bash
bin/spip spip:cache:clear
```

Si vous avez encore la sauvegarde d'avant la migration, l'option 1 vous rend votre site Escal tout de suite. Dites-moi laquelle des trois options vous choisissez et je détaille la marche à suivre.

## plugin-escal, encadré après revue (retour en 4.4 = sauvegarde fichiers et base) — cas 21, tirage 2

Bonjour,

Je vous réponds tout de suite : sur votre site tel qu'il est aujourd'hui, **aucune commande ne peut réactiver Escal**. Voici pourquoi, et les solutions qui existent.

## Ce qui s'est passé

Votre mise à jour a fait passer le site en **SPIP 5.0**. Je l'ai vu au dossier `vendor/spip/framework/` présent à la racine du site : il n'existe qu'en SPIP 5.0.

La version d'Escal installée, `plugins/auto/escal/v5.6.2/` (la dernière, publiée le 2026-10-15), indique dans son `paquet.xml` :

```xml
compatibilite="[4.1.0;4.*]"
```

Escal ne fonctionne donc qu'avec SPIP 4.1 à 4.x. SPIP 5.0 refuse d'activer un plugin qui ne déclare pas sa compatibilité avec la version 5. Il a donc laissé Escal désactivé et affiche ses squelettes par défaut. C'est pour cela que la page d'accueil n'a plus du tout l'apparence d'Escal. Vos réglages Escal (la meta `escal`) sont en principe toujours dans la base, mais plus rien ne les utilise.

## Ce qu'il ne faut pas faire

- Ne modifiez pas la ligne `compatibilite` du `paquet.xml` et n'utilisez pas la constante `_DEV_VERSION_SPIP_COMPAT` pour forcer l'activation. Escal n'a pas été écrit pour SPIP 5.0, et le forcer peut casser le site.
- Ne réinstallez pas SPIP 4.4 par-dessus la base actuelle. Lors de la migration, SPIP 5.0 a réécrit les réglages au format JSON, et SPIP 4.4 ne sait pas les relire : vos réglages Escal ne reviendraient pas.

## Les trois solutions

1. **Revenir à SPIP 4.4 avec la sauvegarde d'avant la mise à jour.** Il faut restaurer **les fichiers et la base de données** de cette sauvegarde, pas seulement les fichiers. C'est la seule solution qui rend Escal tout de suite, avec tous vos réglages. Si vous n'avez pas de sauvegarde complète d'avant la migration, je ne connais aucune méthode vérifiée pour revenir en arrière en gardant les réglages.
2. **Rester en SPIP 5.0 et attendre une version d'Escal compatible**, c'est-à-dire une version dont le `paquet.xml` déclare une compatibilité avec la version 5. Vous pouvez suivre les nouvelles versions sur <https://contrib.spip.net/Escal-4077>. Avant d'installer une nouvelle version, ouvrez son `paquet.xml` pour vérifier sa compatibilité.
3. **Choisir un autre squelette** qui fonctionne déjà avec SPIP 5.0.

## Les commandes utiles en SPIP 5.0

Les commandes `spip plugins:svp:telecharger`, `spip plugins:activer` ou `spip php:eval` qu'on trouve dans les tutoriels Escal sont celles de spip-cli, l'outil de SPIP 4.x. Elles ne s'appliquent pas à votre site. Le `bin/spip` de SPIP 5.0 ne sait ni télécharger ni activer un plugin. Il vous permet en revanche de vérifier que vos réglages Escal sont bien conservés, ce qui compte si vous choisissez la solution 2. À lancer depuis la racine du site :

```bash
# Escal a-t-il bien été installé sur ce site ? (lit une seule clé)
bin/spip config:lire escal_base_version --json | grep -Eq '": ?null' \
  && echo "Escal n'a jamais été installé" \
  || echo "Escal a été installé : ses réglages sont en base"

# Afficher les réglages Escal conservés
bin/spip config:lire escal --json
```

Quand une version d'Escal compatible avec SPIP 5.0 sera publiée et activée, videz le cache pour que l'accueil se recompose :

```bash
bin/spip spip:cache:clear
```

Pour résumer : si le site doit retrouver l'apparence d'Escal rapidement, la seule solution est de restaurer la sauvegarde complète en SPIP 4.4 (fichiers et base). Sinon, restez en 5.0 et attendez une version d'Escal compatible, ou changez de squelette.

## plugin-escal, encadré après revue (retour en 4.4 = sauvegarde fichiers et base) — cas 21, tirage 3

Bonjour,

En bref : sur ce site, il n'existe aucune commande qui réactive Escal. Le site est passé en SPIP 5.0 et Escal ne fonctionne pas sous SPIP 5.0. Voici ce que j'ai constaté et les solutions possibles.

## Ce que j'ai constaté

- **Le site tourne sous SPIP 5.0.** Le répertoire `vendor/spip/framework/` est présent, et il n'existe qu'à partir de SPIP 5.0. Son `composer.json` demande d'ailleurs PHP `^8.4`.
- **Votre version d'Escal ne prend pas en charge SPIP 5.** Le fichier `plugins/auto/escal/v5.6.2/paquet.xml` déclare :
  ```xml
  version="5.6.2"
  compatibilite="[4.1.0;4.*]"
  ```
  Escal 5.6.2 est la dernière version publiée (15/10/2026). Elle n'accepte que SPIP 4.1 à 4.x. SPIP 5.0 refuse donc de l'activer et le site affiche les squelettes par défaut. C'est pour cela que la page d'accueil a perdu son apparence Escal.
- Vos réglages Escal ne sont pas perdus pour autant. Ils restent dans la base, dans la méta `escal`, même quand le plugin est inactif.

## Ce qu'il ne faut pas faire

- **Ne forcez pas l'activation.** Modifier `compatibilite` dans `paquet.xml` ou définir `_DEV_VERSION_SPIP_COMPAT` « débloquerait » le plugin sur un cœur pour lequel il n'est pas fait. Vous auriez des erreurs, et la mise à jour suivante d'Escal écraserait de toute façon la modification.
- **Ne réinstallez pas SPIP 4.4 par-dessus la base actuelle.** Lors de la migration, SPIP 5.0 a réécrit les réglages de type tableau au format JSON, et SPIP 4.4 ne sait pas les relire. Vous retrouveriez un Escal sans ses réglages.
- Les commandes `spip plugins:activer …` que l'on trouve dans les tutoriels Escal sont des commandes spip-cli pour SPIP 4.x. Elles ne s'appliquent pas ici : `bin/spip`, l'outil de SPIP 5.0, n'a pas de commande pour télécharger ou activer un plugin.

## Les commandes utiles sur ce site (SPIP 5.0)

Lancez-les depuis la racine du site. Elles ne font que lire :

```bash
# Vérifier qu'Escal a bien été installé un jour (les réglages sont-ils en base ?)
bin/spip config:lire escal_base_version --json | grep -Eq '": ?null' \
  && echo "Escal n'a jamais été installé sur cette base" \
  || echo "Réglages Escal présents en base"

# Afficher les réglages Escal conservés (à garder de côté)
bin/spip config:lire escal --json > reglages-escal.json
```

Plus tard, une fois une solution choisie, videz le cache :

```bash
bin/spip spip:cache:clear
```

## Les solutions

1. **Revenir à SPIP 4.4** si vous voulez retrouver Escal tout de suite. Le seul retour fiable consiste à restaurer la sauvegarde faite **avant** la mise à jour, avec **les fichiers et la base de données ensemble**. Si cette sauvegarde existe, dites-moi où elle se trouve et je vous donne la procédure de restauration. Si vous n'en avez pas, il n'existe pas de moyen vérifié de revenir à SPIP 4.4 en gardant vos réglages Escal.
2. **Rester en SPIP 5.0 et attendre une version d'Escal compatible**, c'est-à-dire une version dont le `paquet.xml` déclare une compatibilité qui inclut 5.x. Surveillez la page <https://contrib.spip.net/Escal-4077>. Pendant ce temps, le site garde les squelettes par défaut, mais vos réglages restent en base.
3. **Rester en SPIP 5.0 et choisir un autre squelette** déjà compatible avec SPIP 5.0.

Si votre site doit garder l'apparence Escal dès maintenant, la solution 1 (restauration de la sauvegarde) est la seule possible. Avez-vous une sauvegarde complète faite avant la mise à jour ?

## spip-mutualisation-maintenance, avec encadré — cas 23, tirage 1

# Mise à jour des plugins partagés : je ne prépare pas de procédure pour cette ferme

**En bref :** la copie de la racine partagée montre une ferme en **SPIP 5.0.0-beta**. La mutualisation n'est pas encore prise en charge en 5.0, et Mutualisation facile 2.0.1 ne sépare pas les sites sur ce noyau. Je ne rédige donc ni procédure de mise à jour, ni plan de sauvegarde, ni plan de retour arrière. Je propose seulement un inventaire en lecture seule, et au plus un essai sur une copie isolée, hors production.

## Constats (sur la copie de la racine partagée)

| Fait vérifié | Preuve |
|---|---|
| Le noyau est en 5.0 | `ecrire/src/SpipVersion.php` : `VERSION = '5.0.0-beta'` (`CODE = 2026_09_22`, `BASE = 2026_08_03_00`, `PHP_MIN = '8.4.0'`) |
| Les autres signes de la 5.0 sont présents | `bin/spip` (console `SpipLeague\Component\Console\Application`) et `vendor/spip/framework/composer.json` |
| La ferme utilise Mutualisation facile **2.0.1**, compatible `[4.2.0;4.*]` | `mutualisation/paquet.xml`. Le plugin est chargé par `config/mes_options.php`, qui fait `require _DIR_RACINE.'mutualisation/mutualiser.php'` |
| Configuration effective | `demarrer_site($site, …)` avec `'repertoire' => 'sites'`, `table_prefix` à false, `cookie_prefix` à true, `url_img_courtes` à true. `_SITES_ADMIN_MUTUALISATION` vaut `admin-ferme.example`. `$site` vient de `HTTP_HOST` sans le `www.` |
| Deux sites existent sous `sites/` | `asso-exemple.org` et `mairie-exemple.fr`, chacun avec `config/connect.php`, `IMG/`, `local/` et `tmp/`. Je n'ai pas ouvert les fichiers de connexion |
| Un seul plugin partagé est visible | `plugins/auto/cextras/v4.3.3/paquet.xml` : cextras 4.3.3, compatible `[4.1.0;4.*]`. Il nécessite `saisies` et `yaml`, qui sont absents de la copie |

**Ce qu'on ne sait pas encore :**
- comment le cœur a été installé (Git, Composer ou archive) ;
- les modifications locales ;
- le serveur web et PHP-FPM ;
- le moteur de base de chaque site ;
- quels plugins sont actifs sur chaque site ;
- l'emplacement de `saisies` et `yaml`, et des plugins distribués avec le cœur. Ni `plugins-dist/` ni `plugins-dist/spip/` ne figurent dans la copie ;
- l'existence d'une sauvegarde 4.4 faite avant la migration.

## Risques et points bloquants

1. **La mutualisation n'est pas encore prise en charge en 5.0.** La documentation 5.0 marque le point d'entrée mutualisé (`sites/default/public/index.php`) « expérimental — todo ». Il n'existe aucune procédure pour une ferme.
2. **Mutualisation facile 2.0.1 ne sépare pas les sites sur ce noyau.** Le noyau 5.0 définit `_DIR_IMG`, `_DIR_TMP`, `_DIR_VAR` et `_DIR_CONNECT` avant que `config/mes_options.php` s'exécute. `demarrer_site()` ne règle alors que `_DIR_SITE` et `_SPIP_PATH`. Conséquence : chaque site utilise la connexion, les `IMG/`, `local/` et `tmp/` de la racine partagée. L'impact concerne **toute la ferme**, pas un seul site.
3. **Mettre à jour un plugin partagé exécute ses migrations de schéma.** Avec la connexion partagée, ces migrations ne s'appliqueraient pas aux bases de chaque site comme prévu. Un retour arrière des fichiers deviendrait alors incohérent avec les bases.
4. **Les paquets installés ne déclarent pas la 5.0.** cextras 4.3.3 déclare `[4.1.0;4.*]` et Mutualisation facile `[4.2.0;4.*]`. Les mettre à jour sur cette ferme reviendrait à appliquer un plan 4.x à un noyau 5.0.
5. **Un identifiant SQL est en clair dans `config/mes_options.php`.** Cet identifiant est partagé, et je ne le recopie pas ici. Toute personne à qui ce fichier ou une sauvegarde est transmis y a accès. Il faut en tenir compte pour la diffusion des copies.

## Procédure proposée (lecture seule uniquement)

1. Confirmer sur le serveur réel que la production tourne bien en 5.0 :
   - le marqueur de version ;
   - l'existence de `bin/spip`, de `vendor/spip/framework/` et de `plugins-dist/spip/`.
2. Si les deux sites affichent le même contenu, ou des médias et une base communs, ce n'est pas une erreur de configuration. C'est la conséquence décrite au point 2 des risques. La marche à suivre est alors :
   - geler les écritures ;
   - conserver l'état actuel ;
   - restaurer la **sauvegarde 4.4 d'avant la mise à niveau** : fichiers partagés **et** base de chaque site.

   Cette restauration demande sa propre préparation, à partir de cette sauvegarde. Il faudra me transmettre son emplacement, son type et la preuve de son intégrité.
3. Si l'objectif est de tester des versions de plugins sous 5.0, on peut le faire au plus **sur une copie isolée, hors production** : autre hôte ou autre racine, autres bases, sans trafic réel. Rien de ce qu'on y observe ne vaut validation pour la ferme.
4. Pour compléter l'inventaire, voici les commandes en lecture seule que vous pouvez lancer. Elles ne renvoient ni contenu de fichier de connexion ni secret :

```bash
spip_root=/chemin/verifie/de/la/racine   # à remplacer par le chemin réel
spip_root="$(realpath -e -- "$spip_root")"; test "$spip_root" != /

rg -n "VERSION = " "$spip_root/ecrire/src/SpipVersion.php"
test -e "$spip_root/bin/spip" && echo bin/spip
test -d "$spip_root/vendor/spip/framework" && echo vendor/spip/framework
test -d "$spip_root/plugins-dist/spip" && echo plugins-dist/spip

find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -maxdepth 4 \
  -type f -name paquet.xml -print 2>/dev/null \
  | while read -r f; do echo "== $f"; rg -n '^[[:space:]]*(prefix|version|compatibilite)=' "$f"; done

git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null
git -C "$spip_root" status --short 2>/dev/null | head -50
ls -l "$spip_root/composer.json" "$spip_root/composer.lock" 2>/dev/null

find -P "$spip_root/sites" -xdev -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort
df -hP -- "$spip_root"; df -iP -- "$spip_root"
```

## Commandes de modification

**Aucune.** Je ne propose aucune commande qui modifie l'état de la ferme (sauvegarde, mise à jour, purge de cache ou restauration). Le tableau du plan d'action est donc vide : il n'y a pas de mutation à recenser.

## Validation (lecture seule)

Pour **chacun** des sites `asso-exemple.org` et `mairie-exemple.fr`, et pour le site d'administration `admin-ferme.example` :
- vérifier que le site affiche son propre contenu et ses propres médias ;
- comparer le titre du site, le dernier article et une URL d'image, sans écrire.

Si le contenu est identique d'un site à l'autre, on est dans le cas du point 2 de la procédure.

## Retour arrière

Aucune modification n'est proposée, donc il n'y a rien à annuler dans cette réponse.

Le seul retour arrière pertinent sur cette ferme est le **retour à la 4.4**, à partir de la sauvegarde faite avant la migration. Il se déclenche si les sites partagent un contenu ou des médias, ou si un site est cassé depuis le passage en 5.0. L'unité à restaurer est l'ensemble des fichiers partagés plus la base de chaque site.

Il se prépare à part. Pour cela, il me faut :
- l'emplacement de cette sauvegarde, son type (archive, Git ou Composer) ;
- la preuve de son intégrité (somme de contrôle) ;
- si possible, un test de restauration fait sur une copie isolée.

Une sauvegarde qui existe mais n'a pas été vérifiée ne suffit pas pour un retour arrière.

## spip-mutualisation-maintenance, avec encadré — cas 23, tirage 2

# Mise à jour des plugins partagés : je ne prépare pas de procédure, cette ferme tourne sous SPIP 5.0

Je ne vous donne ni procédure de mise à jour, ni plan de sauvegarde, ni plan de retour arrière. La copie de la racine partagée montre une ferme **SPIP 5.0.0-beta**. Sous SPIP 5.0, la mutualisation est encore expérimentale et il n'existe aucune procédure de ferme. De plus, Mutualisation facile 2.0.1 n'isole pas les sites sous 5.0. Mettre à jour les plugins partagés de cette ferme ne la remettrait pas dans un état sain. Je vous propose seulement un inventaire en lecture seule, à lancer vous-même sur le serveur. Je n'ai rien exécuté et je n'ai pas accès au serveur réel.

## Constats

Vérifiés sur la copie de la racine partagée :

| Constat | Preuve |
|---|---|
| Le cœur est en version 5.0 | `ecrire/src/SpipVersion.php` : `VERSION = '5.0.0-beta'`, `ID = 50000`, `PHP_MIN = '8.4.0'` |
| D'autres marqueurs de 5.0 sont présents | `bin/spip` (console `SpipLeague\Component\Console\Application`, `Kernel::boot()`) et `vendor/spip/framework/composer.json` (`spip/framework`, PHP `^8.4`) |
| Mutualisation facile est en 2.0.1 et prévue pour 4.x uniquement | `mutualisation/paquet.xml` : `version="2.0.1"`, `compatibilite="[4.2.0;4.*]"` |
| Le plugin de mutualisation est bien chargé | `config/mes_options.php` fait `require _DIR_RACINE.'mutualisation/mutualiser.php'` (répertoire `mutualisation/` à la racine, pas sous `plugins/`) |
| Le répertoire des sites est `sites` | `demarrer_site($site, [... 'repertoire' => 'sites' ...])` dans `config/mes_options.php`. `$site` vient de `HTTP_HOST`, avec redirection qui retire `www.` |
| Accès au tableau de bord limité à un site | `_SITES_ADMIN_MUTUALISATION = 'admin-ferme.example'` |
| Options de `demarrer_site()` | `table_prefix => false`, `cookie_prefix => true`, `url_img_courtes => true`, `creer_site => true`, `creer_base => true`, `code => 'ecureuil'` |
| Deux sites sur disque | `sites/asso-exemple.org/` et `sites/mairie-exemple.fr/`. Chacun a `config/connect.php`, `IMG/`, `local/` et `tmp/` |
| Un seul plugin partagé trouvé | `plugins/auto/cextras/v4.3.3/paquet.xml` : Champs Extras `4.3.3`, `compatibilite="[4.1.0;4.*]"`. Il exige `saisies [4.2.0;]` et `yaml [3.0.0;]`, et utilise `verifier` et `crayons` si présents |

Ce que je n'ai pas pu établir :

- **Moteur de base de chaque site.** La recherche filtrée sur `connect.php` (elle ne renvoie que `mysql`, `sqlite3` ou `unknown`) donne `unknown` pour les deux sites.
- **Origine de l'installation** (Git, Composer, archive/SVP) du cœur et de `plugins/auto/` : la copie ne contient ni `composer.json`/`composer.lock` à la racine, ni dépôt Git.
- **Plugins actifs par site** ; présence de `saisies` et `yaml`, alors que `cextras` en dépend ; présence de `plugins-dist/` (absent de la copie).
- **Serveur web et PHP** (Apache ou Nginx/PHP-FPM, versions de PHP).
- **Exposition de `?exec=mutualisation`** sur les autres hôtes que le site d'administration.
- **Sauvegarde 4.4** d'avant la montée en version : je ne sais pas si elle existe ni si elle a été vérifiée.

## Risques

- **Les sites ne sont probablement pas isolés (toute la ferme est touchée).** Sous 5.0, le noyau définit `_DIR_IMG`, `_DIR_TMP`, `_DIR_VAR` et `_DIR_CONNECT` avant l'exécution de `config/mes_options.php`. `demarrer_site()` ne règle alors que `_DIR_SITE` et `_SPIP_PATH`. Tous les sites utilisent donc la connexion, `IMG/`, `local/` et `tmp/` de la racine partagée, et non ceux de `sites/<domaine>/`. Si les sites affichent le même contenu, c'est cette cause, pas une erreur de configuration.
- **Plugins incompatibles.** `cextras` 4.3.3 (`[4.1.0;4.*]`) et Mutualisation facile 2.0.1 (`[4.2.0;4.*]`) ne déclarent pas SPIP 5.0. Une « mise à jour des plugins partagés » ne réglerait ni l'incompatibilité du plugin de mutualisation, ni le défaut d'isolation.
- **Création automatique de sites.** `creer_site` et `creer_base` sont à `true`, avec le code d'activation par défaut `ecureuil`. Un nom d'hôte inconnu qui arrive sur la ferme peut lancer la création d'un site et d'une base. Avec l'isolation cassée en 5.0, on ne peut pas prévoir où cette création écrirait.
- **Identifiants en clair.** `config/mes_options.php` contient en clair des identifiants de base de données (`_INSTALL_USER_DB` / `_INSTALL_PASS_DB`). Je ne les reproduis pas. Ne collez pas ce fichier dans un ticket ni dans une conversation.
- **Points d'entrée sans authentification.** Dans `?exec=mutualisation`, seules les actions `upgrade` et `upgradeplugins` demandent un secret. `renouvelle_alea`, `dirliste` et `dirsize` s'exécutent sans authentification sur chaque site.
- **Ce qui bloque :** version 5.0, origine de l'installation inconnue, moteurs de base inconnus, aucune sauvegarde 4.4 vérifiée.

## Procédure proposée

Uniquement des observations en lecture seule. Aucune mutation n'est proposée, donc le plan d'actions ci-dessous est vide.

1. Confirmer la version sur le serveur réel (commandes 1 à 3 de la section « Commandes »).
2. Vérifier si les sites sont isolés : comparer les pages publiques des deux sites et regarder où arrivent les fichiers récents (commande 5).
3. Inventorier les plugins partagés et leur compatibilité déclarée (commande 4).
4. Établir l'origine de l'installation et le moteur de base de chaque site, avec une sortie filtrée (commandes 6 et 7).
5. Rechercher la sauvegarde 4.4 d'avant la montée en version et dire si elle a été vérifiée et testée en restauration. **Ne rien restaurer à ce stade.**
6. Me renvoyer les sorties. La suite dépend de ce qu'elles montrent (voir « Retour arrière »).

## Commandes

Plan d'actions (mutations) : **aucune ligne.** Sur une ferme 5.0, je ne propose aucune mise à jour de plugin, aucune purge de cache, aucun `upgradeplugins` et aucun « Upgrader tout ».

| Action | Objectif | Préconditions | Périmètre exact | Impact | Sauvegarde/preuve | Commande proposée | Vérification | Retour arrière |
|---|---|---|---|---|---|---|---|---|
| — | — | — | — | — | — | — | — | — |

Observations en lecture seule, à lancer vous-même (**proposées — non exécutées**). Remplacez `spip_root` par le chemin réel de la racine partagée :

```bash
# 1. Racine partagée, chemin réel
spip_root="$(realpath -e -- /chemin/vers/racine_partagee)"
test -n "$spip_root" && test "$spip_root" != / && echo "$spip_root"

# 2. Marqueurs de version (cœur 5.0 : SpipVersion.php ; 4.x : inc_version.php)
grep -nE "const (string|int) (VERSION|ID|PHP_MIN|PHP_MAX)" "$spip_root/ecrire/src/SpipVersion.php" 2>/dev/null
grep -nE '\$spip_version_(affichee|branche)' "$spip_root/ecrire/inc_version.php" 2>/dev/null
ls -d "$spip_root/bin/spip" "$spip_root/vendor/spip/framework" "$spip_root/plugins-dist/spip" 2>/dev/null

# 3. Version de Mutualisation facile réellement chargée
grep -nE '^[[:space:]]*(prefix|version|compatibilite)=' "$spip_root/mutualisation/paquet.xml"

# 4. Plugins partagés : préfixe, version, compatibilité, dépendances
find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -maxdepth 4 -name paquet.xml -print 2>/dev/null \
  | while read -r p; do echo "== $p"; grep -nE '^[[:space:]]*(prefix|version|compatibilite)=|<(necessite|utilise) ' "$p"; done

# 5. Isolation : fichiers récents dans les IMG/ et tmp/ de la racine et des sites
find -P "$spip_root/IMG" "$spip_root/tmp" "$spip_root/sites" -xdev -maxdepth 3 -type f -mmin -1440 \
  -printf '%TY-%Tm-%Td %TH:%TM %p\n' 2>/dev/null | sort | tail -50

# 6. Origine de l'installation (sans afficher de remote, qui peut contenir un jeton)
git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null; git -C "$spip_root" status --short 2>/dev/null | head
ls -l "$spip_root/composer.json" "$spip_root/composer.lock" 2>/dev/null

# 7. Moteur de base par site, sortie filtrée (n'affiche jamais connect.php)
for c in "$spip_root"/sites/*/config/connect.php "$spip_root/config/connect.php"; do
  [ -f "$c" ] || continue
  if grep -q "'sqlite3'" "$c"; then e=sqlite3; elif grep -q "'mysql'" "$c"; then e=mysql; else e=unknown; fi
  echo "$c: $e"
done
```

## Validation

Il n'y a pas de mutation à valider. Pour chacun des deux sites (`asso-exemple.org`, `mairie-exemple.fr`) et pour le site d'administration `admin-ferme.example`, relevez en lecture seule :

- la page d'accueil publique : contenu propre au site, ou contenu commun ;
- l'espace privé : version de SPIP affichée, plugins actifs ;
- le journal d'erreurs PHP de l'hôte virtuel ;
- le résultat des commandes 4, 5 et 7.

## Retour arrière

Je ne rédige pas de plan de retour arrière pour une ferme 5.0. Voici seulement ce qui le déclencherait :

- **Si les commandes montrent que les sites partagent le contenu, les fichiers ou la base de la racine** (défaut d'isolation en 5.0), ce n'est pas un problème de plugin. Dans ce cas : geler les écritures, conserver l'état actuel, puis restaurer **la sauvegarde 4.4 d'avant la montée en version**. L'unité à restaurer est l'ensemble des fichiers partagés plus la base de chaque site. Cette restauration se prépare séparément, une fois établies l'existence et la validité de cette sauvegarde. Elle ne se fait pas à partir de ce document.
- **Si le but est d'exploiter la ferme en 5.0**, ce n'est pas encore pris en charge. Le mieux que je puisse proposer est un essai sur une copie isolée, hors production.

Une fois la ferme revenue en SPIP 4.4 et l'inventaire complété (origine de l'installation, moteurs de base, plugins actifs par site, sauvegarde vérifiée), je pourrai préparer la mise à jour des plugins partagés. Elle se ferait site par site, avec point de contrôle, et un plan d'actions complet.

**Vérification de couverture :** cette réponse ne propose aucune action qui modifie l'état du serveur. Les sept commandes sont en lecture seule.

## spip-mutualisation-maintenance, avec encadré — cas 24

# Diagnostic : tous les sites de la ferme affichent le même contenu

**En bref :** la « dernière mise à jour » a fait passer le noyau partagé en **SPIP 5.0.0-beta**. Or Mutualisation facile 2.0.1 ne sait pas isoler les sites sous SPIP 5.0. Il n'y a pas de réglage de configuration à corriger : il faut figer les écritures, conserver l'état actuel comme preuve, puis **restaurer la sauvegarde 4.4 prise avant la mise à jour** (fichiers partagés **et** base de chaque site). Ne tentez ni de « réparer » la mutualisation sous 5.0, ni de rétrograder le code à la main (Composer, copie de fichiers 4.4 par-dessus).

J'ai seulement lu la copie de la racine partagée (`farm/`). Je n'ai exécuté aucune commande sur le serveur.

---

## 1. Constats

Faits vérifiés dans la copie de la racine partagée :

| # | Constat | Preuve |
|---|---|---|
| F1 | Le noyau partagé est en **SPIP 5.0.0-beta** | `ecrire/src/SpipVersion.php` : `VERSION = '5.0.0-beta'`, `ID = 50000`, `CODE = 2026_09_22`, `BASE = 2026_08_03_00`, PHP 8.4.0 à 8.5.99. Deux autres indices de 5.0 le confirment : `bin/spip` (console `SpipLeague`) et `vendor/spip/framework/` |
| F2 | Le noyau est installé par Composer (au moins en partie) | présence de `vendor/` et de `bin/spip`. Je n'ai pas de `composer.json` ni de `composer.lock` racine dans la copie : la provenance exacte reste à confirmer |
| F3 | Mutualisation facile **2.0.1** déclare `compatibilite="[4.2.0;4.*]"` | `mutualisation/paquet.xml`. C'est hors de sa plage sous 5.0 |
| F4 | Le plugin est chargé par `config/mes_options.php` avec `require _DIR_RACINE.'mutualisation/mutualiser.php'`. `$site` vient de `HTTP_HOST` (le préfixe `www.` est retiré avec redirection, le `:port` est normalisé). L'appel est `demarrer_site($site, [... 'repertoire' => 'sites', 'cookie_prefix' => true, 'table_prefix' => false, 'url_img_courtes' => true ...])`, avec `_SITES_ADMIN_MUTUALISATION = 'admin-ferme.example'` | `config/mes_options.php` |
| F5 | `demarrer_site()` définit seulement `_DIR_SITE` (l. 51) et `_SPIP_PATH` (l. 184-191). Il compte sur le noyau pour dériver ensuite `_DIR_IMG`, `_DIR_TMP`, `_DIR_VAR` et `_DIR_CONNECT` à partir du site | `mutualisation/mutualiser.php` |
| F6 | Deux sites existent dans `sites/` : `asso-exemple.org` et `mairie-exemple.fr`. Chacun a `config/connect.php`, `IMG/`, `local/` et `tmp/` | arborescence de `sites/` |
| F7 | Le plugin partagé `plugins/auto/cextras/v4.3.3` déclare `[4.1.0;4.*]`. Il est donc lui aussi incompatible avec 5.0 | son `paquet.xml` |

**Cause.** Le noyau 5.0 définit `_DIR_IMG`, `_DIR_TMP`, `_DIR_VAR` et `_DIR_CONNECT` **avant** d'exécuter `config/mes_options.php`. Quand `demarrer_site()` s'exécute, ces constantes pointent déjà vers la racine partagée. Le plugin ne pose que `_DIR_SITE` et `_SPIP_PATH`. Résultat : chaque domaine utilise **la connexion SQL, `IMG/`, `local/` et `tmp/` de la racine partagée**, et plus ceux de `sites/<domaine>/`. Cela explique que tous les sites affichent le même contenu et les mêmes documents. C'est une limite connue : la mutualisation est encore expérimentale en 5.0 (la documentation 5.0 marque le point d'entrée mutualisé « expérimental — todo ») et aucune procédure de ferme 5.0 n'existe.

**Inconnues à lever** (lecture seule, section 3) :

- U1 : la provenance exacte du code avant et après la mise à jour (Git, Composer ou archive), et l'existence d'un `composer.lock` ;
- U2 : la connexion SQL que 5.0 utilise réellement. La copie ne contient pas de `config/connect.php` racine, alors qu'il faut bien une connexion pour qu'un contenu s'affiche. Si cette connexion pointe vers la base de l'un des sites, **cette base a probablement été migrée au schéma 5.0** ;
- U3 : le moteur SQL de chaque site. Les `connect.php` de la copie sont des fichiers de deux lignes sans moteur détectable, donc il faut vérifier sur le serveur ;
- U4 : l'existence d'une **sauvegarde 4.4 d'avant la mise à jour** qui soit vérifiée et restaurable, avec son manifeste (révision du code, base de chaque site, fichiers persistants) ;
- U5 : le serveur web (Apache ou Nginx/PHP-FPM) et la version de PHP-FPM ;
- U6 : ce qui a été écrit depuis la mise à jour (articles, documents téléversés, inscriptions, formulaires). Ces écritures sont parties dans la base et le `IMG/` **partagés**, pas dans ceux du site concerné ;
- U7 : la liste complète des sites. La copie n'en montre que deux, et le site d'administration `admin-ferme.example` n'apparaît pas dans `sites/`.

## 2. Risques

- **Portée : toute la ferme**, c'est-à-dire tous les domaines servis par cette racine partagée.
- **Fuite et mélange de données entre sites.** Chaque visiteur voit le contenu d'une seule base, la même pour tous. Si `cookie_prefix` n'est pas appliqué avant l'ouverture de la session, les sessions et les droits d'auteur peuvent eux aussi se mélanger entre domaines. Toute écriture faite maintenant aggrave le mélange (U6).
- **Base peut-être déjà migrée en 5.0** (U2). Si c'est le cas, remettre seulement les fichiers 4.4 ne suffit pas : on ne revient jamais au code 4.4 avec une base au schéma 5.0. Le code partagé et **chaque** base doivent revenir au **même** point de sauvegarde.
- **Incompatibilités en cascade** : Mutualisation facile 2.0.1 (F3) et Champs Extras 4.3.3 (F7) déclarent tous deux `4.*`. Rester en 5.0 n'est pas une option pour la production.
- **Bloquant** : sans sauvegarde 4.4 vérifiée et restaurable (U4), **arrêtez-vous après les lignes 1 à 3 du plan**. Gardez le gel et revenez vers moi : on ne reconstruit pas une 4.4 à la main par-dessus une ferme 5.0.
- **Secrets** : `config/mes_options.php` contient des identifiants SQL d'installation en clair. Ne collez ce fichier, ni aucun `connect.php`, dans un ticket ou un échange. Je ne les ai pas reproduits ici.

## 3. Procédure proposée

Commencez par ces observations en **lecture seule**. Elles ne modifient rien. Renvoyez-moi uniquement les sorties filtrées.

```bash
spip_root=/chemin/verifie/de/la/racine/partagee   # à renseigner
spip_root="$(realpath -e -- "$spip_root")" && test "$spip_root" != / || echo STOP

# Version effective (confirme F1 sur le serveur réel)
grep -nE "VERSION =|BASE =" "$spip_root/ecrire/src/SpipVersion.php"
ls -d "$spip_root/bin/spip" "$spip_root/vendor/spip/framework" "$spip_root/plugins-dist/spip" 2>&1

# Provenance (U1)
git -C "$spip_root" rev-parse --short HEAD 2>&1; git -C "$spip_root" status --short 2>&1 | head
ls -l "$spip_root/composer.json" "$spip_root/composer.lock" 2>&1

# Connexion utilisée par 5.0 (U2) : présence seulement, sans afficher le contenu
ls -l "$spip_root/config/connect.php" 2>&1
grep -oE "'(mysql|sqlite3)'" "$spip_root/config/connect.php" 2>/dev/null | sort -u

# Liste des sites et moteur de chacun (U3, U7) : seul le jeton du moteur est affiché
for d in "$spip_root"/sites/*/; do
  printf '%s ' "$(basename "$d")"
  grep -oE "'(mysql|sqlite3)'" "$d/config/connect.php" 2>/dev/null | sort -u | tr '\n' ' '; echo
done

# Écritures depuis la mise à jour dans les dossiers partagés (U6)
maj='AAAA-MM-JJ HH:MM'   # heure de la mise à jour
find -P "$spip_root/IMG" "$spip_root/local" "$spip_root/tmp" -xdev -type f -newermt "$maj" 2>/dev/null | wc -l
find -P "$spip_root/IMG" -xdev -type f -newermt "$maj" -printf '%TY-%Tm-%Td %TH:%TM %s %p\n' 2>/dev/null | head -50

# Pile web (U5)
command -v nginx >/dev/null && nginx -T 2>/dev/null | sed -E 's/[[:space:]]*#.*$//' \
  | awk '$1=="server_name"||$1=="root"||$1=="fastcgi_pass"{print}'
command -v apache2ctl >/dev/null && apache2ctl -S 2>&1 | grep -E 'namevhost|alias'
php -v | head -1

# Sauvegarde 4.4 d'avant la mise à jour (U4) : manifeste et sommes de contrôle
ls -l /chemin/des/sauvegardes/<horodatage-pre-maj>/
sha256sum -c /chemin/des/sauvegardes/<horodatage-pre-maj>/SHA256SUMS
```

Ensuite :

1. Appliquez **tout de suite** la ligne 1 du plan (gel des écritures), avant même d'avoir toutes les réponses : chaque minute d'ouverture mélange davantage les données.
2. Avec les sorties ci-dessus, on fixe la liste exacte des sites, le moteur de chacun, la base que 5.0 a touchée et le jeu de sauvegarde 4.4 à utiliser.
3. Suivez les lignes 2 et 3 dans l'ordre (conservation de l'état 5.0 actuel).
4. Suivez la ligne 4 (vérification et restauration d'essai de la sauvegarde 4.4). **Si elle échoue ou s'il n'y a pas de sauvegarde : arrêt, gel maintenu.**
5. Suivez les lignes 5 à 7 dans l'ordre (restauration des fichiers partagés, des bases, puis des fichiers par site si besoin).
6. Faites la validation de la section 5 sur **chaque** site, puis la ligne 8 (levée du gel).
7. Plus tard, en dehors de l'urgence : ligne 9 (reprise des contenus saisis pendant l'incident).

Le passage de la ferme en 5.0 n'est pas pris en charge pour l'instant. Au mieux, on peut le tester sur une copie isolée, hors production, quand une procédure de ferme 5.0 existera.

## 4. Commandes : plan d'action

Toutes les commandes sont **proposées, non exécutées**. Les valeurs entre `<…>` sont à remplacer par des valeurs vérifiées à l'étape 3. Les sauvegardes vont **hors** de la racine web, avec `umask 077`.

| # | Action | Objectif | Préconditions | Portée exacte | Impact | Sauvegarde / preuve | Commande proposée — non exécutée | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Geler les écritures (maintenance 503) | Arrêter le mélange de données et la fuite entre sites | Liste des vhosts de la ferme connue (U5, U7) | Tous les vhosts servis par `<spip_root>` (hôte web) | Tous les sites de la ferme indisponibles | Copie de la conf vhost avant modification ; heure du gel notée | Nginx : ajouter `return 503;` (ou `include <snippet-maintenance>;`) dans chaque `server` de la ferme, puis `nginx -t && systemctl reload nginx`. Apache : `RewriteRule ^ - [R=503,L]` dans chaque vhost, puis `apache2ctl configtest && systemctl reload apache2` | `curl -sI https://<chaque-domaine>/` renvoie `503` pour tous les domaines ; aucune nouvelle ligne `POST` dans les journaux d'accès | Retirer la règle ou le snippet, `-t`/`configtest`, recharger : c'est la ligne 8 |
| 2 | Archiver l'état 5.0 actuel des fichiers partagés | Garder la preuve et les contenus écrits depuis la mise à jour (U6) | Ligne 1 active ; `backup_root` hors de `spip_root`, résolu et différent de `/` | `<spip_root>` entier, y compris `IMG/`, `local/`, `tmp/`, `config/`, `vendor/` et `sites/` | Lecture seule sur la source ; consomme de l'espace disque | Archive et `SHA256SUMS` | `(umask 077; tar -C "$(dirname <spip_root>)" -cpf <backup_root>/etat-5.0/racine.tar "$(basename <spip_root>)") && sha256sum <backup_root>/etat-5.0/racine.tar >> <backup_root>/etat-5.0/SHA256SUMS` | `tar -tf … >/dev/null` réussit ; `sha256sum -c` OK ; taille cohérente avec `du -s <spip_root>` | Rien à défaire. Ne supprimer l'archive qu'après clôture de l'incident |
| 3 | Exporter l'état actuel de **chaque** base (celle de la racine 5.0 et celle de chaque site) | Garder la preuve et permettre de revenir sur les lignes 6 et 9 | Ligne 1 active ; moteur connu par base (U2, U3). MySQL : fichier d'options `0600` préparé par vous, sans mot de passe en argument | Chaque base listée à l'étape 3 | Lecture seule ; charge SQL ponctuelle | Un export par base et `SHA256SUMS` | MySQL : `(umask 077; set -C; <mariadb-dump|mysqldump> --defaults-file=<opts.cnf> --no-login-paths --single-transaction --quick --routines --events --triggers --no-create-db <base> > <backup_root>/etat-5.0/<site>.sql)` en vérifiant le code de sortie. SQLite : `sqlite3 <fichier.sqlite> ".timeout 5000" ".backup '<backup_root>/etat-5.0/<site>.sqlite'"` | Code de sortie 0 ; fichier non vide ; SQLite : `PRAGMA integrity_check;` = `ok` | Rien à défaire. Ces exports servent de retour arrière aux lignes 6 et 9 |
| 4 | Vérifier la sauvegarde 4.4 d'avant la mise à jour et faire une restauration d'essai isolée | Prouver qu'elle est **restaurable** et cohérente (code et bases au même point) | Jeu `<horodatage-pre-maj>` identifié (U4) ; serveur ou instance d'essai isolé, sans accès à la production | Le jeu de sauvegarde 4.4 ; l'environnement d'essai uniquement | Aucun sur la production | Résultats de `sha256sum -c`, journal de l'essai | `sha256sum -c <pre-maj>/SHA256SUMS` ; `tar -tf <pre-maj>/racine.tar >/dev/null` ; import de chaque base dans une base d'essai pré-créée, avec un compte limité à cette base (recette « Restore verification ») ; essai de 2 sites au moins sur la copie isolée | Sommes OK ; `inc_version.php` de la copie en 4.4.x ; `spip_meta` présent ; les 2 sites d'essai affichent **chacun** leur propre contenu | Supprimer l'environnement d'essai après accord. **En cas d'échec : stop, gel maintenu, pas de ligne 5** |
| 5 | Mettre de côté le code 5.0 et remettre les fichiers partagés 4.4 | Revenir au code partagé et au plugin de mutualisation d'avant la mise à jour | Lignes 1 à 4 réussies ; `<spip_root>` résolu, ≠ `/`, sans lien symbolique inattendu | `<spip_root>` (code partagé, `config/`, `mutualisation/`, `plugins/`) | Toute la ferme ; le gel reste actif | Ligne 2 (état 5.0) ; manifeste 4.4 | `mv -- <spip_root> <spip_root>.5.0-incident-<date>` puis `mkdir <spip_root> && tar -C "$(dirname <spip_root>)" -xpf <pre-maj>/racine.tar` (ou checkout/`composer install` exactement à la révision du manifeste 4.4, avec **une seule** méthode : celle de la provenance d'origine) | `grep spip_version_affichee <spip_root>/ecrire/inc_version.php` donne la 4.4.x du manifeste ; `<spip_root>/ecrire/src/SpipVersion.php`, `bin/spip` et `vendor/spip/framework` absents ; `mutualisation/paquet.xml` = 2.0.1 ; propriétaire et droits identiques au manifeste | `rm -r` du seul `<spip_root>` restauré (chemin vérifié), puis `mv <spip_root>.5.0-incident-<date> <spip_root>` |
| 6 | Restaurer la base de **chaque** site depuis le même point 4.4 | Remettre des schémas et des données qui correspondent au code 4.4 | Ligne 5 réussie ; base cible vérifiée par site ; export de la ligne 3 présent | Chaque base de site, et la base que 5.0 a utilisée (U2) | Données écrites depuis la mise à jour retirées des bases (elles restent dans les lignes 2 et 3) | Ligne 3 ; manifeste 4.4 | MySQL : `<mariadb|mysql> --defaults-file=<opts.cnf> --no-login-paths <base> < <pre-maj>/databases/<site>.sql` (sur une base vidée ou recréée selon votre recette validée en ligne 4). SQLite : copier `<pre-maj>/databases/<site>.sqlite` vers un nouveau nom, `PRAGMA integrity_check`, puis le substituer au fichier du site | `SELECT valeur FROM <prefixe>_meta WHERE nom='version_installee'` correspond au manifeste 4.4 pour chaque base ; SQLite : `integrity_check` = `ok` | Réimporter l'export de la ligne 3 pour cette base (en même temps que le retour arrière de la ligne 5) |
| 7 | (Conditionnel) Restaurer `config/` et `IMG/` d'un site s'ils diffèrent du point 4.4 | Revenir à des fichiers persistants cohérents avec la base | Comparaison avec le manifeste 4.4 qui montre un écart pour ce site | `<spip_root>/sites/<site>/config/` et `IMG/` du seul site concerné | Un site | Ligne 2 | `tar -C <spip_root> -xpf <pre-maj>/sites-<site>.tar sites/<site>/config sites/<site>/IMG` | Les sommes de contrôle des fichiers restaurés correspondent au manifeste | Ré-extraire `sites/<site>/…` depuis l'archive de la ligne 2 |
| 8 | Lever le gel | Rouvrir la ferme | Validation de la section 5 réussie sur **chaque** site | Les mêmes vhosts que la ligne 1 | Sites de nouveau publics | Heure de levée notée | Retirer la règle ou le snippet de la ligne 1, puis `nginx -t && systemctl reload nginx` (ou `apache2ctl configtest && systemctl reload apache2`) | `curl -sI` renvoie `200` ou `301` sur chaque domaine, et chaque domaine montre son propre contenu | Remettre la règle de la ligne 1 et recharger |
| 9 | (Plus tard) Reprendre les contenus saisis pendant l'incident | Ne pas perdre les articles ou documents publiés depuis la mise à jour | Lignes 1 à 8 terminées ; inventaire des écritures U6 tiré des archives des lignes 2 et 3 | Contenus repérés, site par site | Ressaisie manuelle par les rédacteurs | Lignes 2 et 3 | Pas de commande automatique : on identifie dans l'export 5.0 les objets créés après `<maj>` (et les fichiers de l'`IMG/` racine archivé), puis chaque site les ressaisit dans son espace privé | Les rédacteurs confirment chaque contenu repris | Dépublier ou supprimer le contenu ressaisi depuis l'espace privé du site |

## 5. Validation

À faire en lecture seule, **sur chaque site** de la liste vérifiée (au minimum `asso-exemple.org`, `mairie-exemple.fr` et le site d'administration `admin-ferme.example`), avant la ligne 8 :

- les contrôles de réussite des lignes 5, 6 et 7 sont tous au vert ;
- la page d'accueil publique de chaque domaine affiche **son propre** titre et ses derniers articles. Comparez au moins deux sites entre eux ;
- un document récent de chaque site s'affiche, et son URL se résout dans `sites/<site>/IMG/` (`url_img_courtes` est actif, donc l'URL publique est `/IMG/…`) ;
- la connexion à l'espace privé fonctionne pour un compte de chaque site, et ce compte ne voit que les objets de son site ;
- la page Plugins de chaque site ne montre aucune erreur d'incompatibilité, et Champs Extras 4.3.3 est actif là où il l'était avant ;
- `?exec=mutualisation`, sur le site d'administration uniquement, liste tous les sites sans demande de mise à jour de base inattendue ;
- les journaux PHP et SPIP de chaque `sites/<site>/tmp/log/` ne montrent aucune erreur fatale depuis la restauration.

## 6. Retour arrière

- **Déclencheurs :** un contrôle de réussite échoue aux lignes 5, 6 ou 7 ; un site affiche encore le contenu d'un autre ; une base refuse le code 4.4 ; ou des erreurs fatales apparaissent dans la validation.
- **Unité de restauration :** le couple *code partagé + toutes les bases + fichiers persistants des sites* d'un **même** point de sauvegarde. On ne restaure jamais une base seule depuis un autre point que le code.
- **Ordre :** gardez la ligne 1 active. Défaites la ligne 7, puis la 6 (réimport de la ligne 3), puis la 5, chacune selon sa colonne « Retour arrière ». Vous revenez alors à l'état 5.0 conservé par les lignes 2 et 3, sans aucune perte supplémentaire. Le gel reste en place tant qu'aucune sauvegarde 4.4 restaurable n'est disponible.

**Vérification de couverture :** les seules actions qui modifient l'état dans cette réponse sont le gel et la levée du gel, l'archivage des fichiers et l'export des bases, la restauration d'essai isolée, la mise de côté du code 5.0 avec la remise des fichiers 4.4, la restauration des bases, la restauration conditionnelle des fichiers d'un site, et la reprise des contenus. Chacune a une ligne complète (1 à 9) dans le plan d'action.

## spip-mutualisation-maintenance, avec encadré — cas 25 (contrôle 4.4)

# Mise à jour des plugins partagés de la ferme : préparation

J'ai lu uniquement la copie de la racine partagée (`farm/`) et le skill. Je n'ai rien exécuté sur le serveur. Les commandes ci-dessous sont **proposées, pas exécutées** : c'est vous qui les relisez et les lancez.

**Conclusion :** je ne peux pas encore vous donner une procédure prête à lancer. Plusieurs conditions d'arrêt du skill restent ouvertes : la version cible n'est pas définie, la provenance n'est pas vérifiée, les dépendances sont absentes de la copie, le moteur de base de chaque site est inconnu et il n'existe aucune sauvegarde vérifiée. Vous trouverez ci-dessous ce qui est établi, les observations en lecture seule qui lèvent ces blocages, puis le plan d'actions complet. Chaque ligne du plan est verrouillée par ses préconditions.

---

## 1. Constats

### Faits vérifiés (dans la copie)

| Élément | Constat | Preuve |
|---|---|---|
| Version du cœur | SPIP **4.4.22** (branche 4.4), donc dans le périmètre du skill | `ecrire/inc_version.php:464` : `$spip_version_branche = '4.4.22'` ; `_SPIP_VERSION_ID` 40422 ; `$spip_version_base = 2026_08_03_00` |
| Marqueurs SPIP 5.0 | Aucun : pas de `ecrire/src/SpipVersion.php`, de `bin/spip`, de `vendor/spip/framework/` ni de `plugins-dist/spip/` | Arborescence de la copie |
| Mutualisation facile | Version **2.0.1**, compatible `[4.2.0;4.*]`, installée dans `mutualisation/` à la racine (et non sous `plugins/`) | `mutualisation/paquet.xml` |
| Chargement | `config/mes_options.php` fait un `require _DIR_RACINE.'mutualisation/mutualiser.php'` | `config/mes_options.php`, lignes 14-18 |
| `repertoire` | `'sites'` | Appel `demarrer_site()` dans `config/mes_options.php` |
| Site d'administration | `_SITES_ADMIN_MUTUALISATION` = `admin-ferme.example` | `config/mes_options.php:11` |
| Options | `table_prefix => false`, `cookie_prefix => true`, `url_img_courtes => true`, `creer_site => true`, `creer_base => true` | Même appel |
| Sites présents | `sites/asso-exemple.org/` et `sites/mairie-exemple.fr/`, chacun avec `config/connect.php`, `IMG/`, `local/` et `tmp/` | Arborescence |
| Chemins des plugins | `_SPIP_PATH` = `_DIR_SITE : _DIR_RACINE : squelettes-dist/ : prive/ : ecrire/`. Ni `_DIR_PLUGINS_SUPPL` ni `dossier_squelettes` ne sont surchargés dans la config globale. Aucun site n'a de répertoire `plugins/` ou `squelettes/` propre | `mutualisation/mutualiser.php:183-195`, recherche dans `config/` et `sites/` |
| Plugins partagés | Un seul : **Champs Extras (`cextras`) 4.3.3**, dans `plugins/auto/cextras/v4.3.3/` (disposition typique de SVP). Compatible `[4.1.0;4.*]`, sans attribut `schema`. `necessite` : `saisies [4.2.0;]` et `yaml [3.0.0;]`. `utilise` : `verifier [1.16.2;]` et `crayons [2.3.0;]` | `plugins/auto/cextras/v4.3.3/paquet.xml` |

Portée : le répertoire `plugins/auto/` est **partagé par toute la ferme**. Le modifier touche tous les sites qui activent le plugin. Un « pilote » sur un seul site en production est impossible.

### Inconnues (bloquantes)

1. **Version cible** : vous ne l'avez pas indiquée, et je n'ai pas consulté le dépôt amont. Il faut l'archive officielle exacte et son `paquet.xml` réel. Un extrait de catalogue ne suffit pas.
2. **Dépendances absentes de la copie** : ni `saisies` ni `yaml` n'y figurent. Ils ne font pas partie des `plugins-dist` de SPIP 4.4. Trois possibilités :
   - la copie est incomplète ;
   - ils sont installés dans un chemin que je n'ai pas vu ;
   - `cextras` ne peut s'activer sur aucun site.

   Il faut trancher avant toute chose, car une nouvelle version de `cextras` peut exiger une version plus récente de `saisies`. La mise à jour deviendrait alors un **ensemble coordonné**.
3. **Provenance** : la copie ne contient ni `.git`, ni `composer.json`/`composer.lock`, ni `spip.php`, ni `plugins-dist/`, ni `squelettes-dist/`. Elle est donc partielle. La disposition `auto/<préfixe>/v<version>` suggère SVP, ce qui reste à confirmer sur le serveur.
4. **Moteur de base de chaque site** : j'ai vérifié les deux `config/connect.php` par une liste blanche de jetons, sans les afficher. Aucun ne contient d'appel `spip_connect_db` ni de type `mysql`/`sqlite3`. Soit la copie a été tronquée ou assainie, soit la connexion est déplacée (`_DIR_CONNECT` / `_FILE_CONNECT_INS`). La valeur `_INSTALL_SERVER_DB = 'mysql'` de la config globale vaut pour l'installation et ne prouve rien pour ces sites. Le préfixe de tables est probablement `spip` (`_INSTALL_TABLE_PREFIX`), à confirmer.
5. **Plugins actifs par site** : les répertoires `tmp/` de la copie sont vides (pas de `meta_cache.php`). On ne sait donc pas quels sites activent `cextras`.
6. **Site d'administration** : `admin-ferme.example` n'apparaît pas dans `sites/`. Il se trouve peut-être à la racine, mais le commentaire du fichier demande alors `''`. Il faut savoir d'où partira le tableau de bord `?exec=mutualisation`.
7. **Serveur web, PHP CLI/FPM, espace disque et inodes, sauvegardes existantes** : inconnus.

### Anomalies relevées (hors mise à jour, à traiter séparément)

- `config/mes_options.php` contient en clair des identifiants SQL d'installation (`_INSTALL_USER_DB` / `_INSTALL_PASS_DB`). Je ne les reproduis pas. Ces valeurs sont **identiques à celles du fichier d'exemple** `mutualisation/mes_options.php.txt` : le seul écart entre les deux fichiers est le domaine d'administration. Vérifiez si ce compte existe réellement. Dans tous les cas, toute archive contenant ce fichier doit être traitée comme un secret.
- `creer_site => true` et `creer_base => true` avec le code d'activation par défaut `'ecureuil'` : un nom d'hôte qui pointe vers la ferme peut déclencher la création d'un site.
- Les routes `renouvelle_alea`, `dirliste` et `dirsize` de `?exec=mutualisation` ne sont pas authentifiées et répondent sur chaque site. Vérifiez les journaux d'accès (lecture seule, observation O9).
- Note de transparence : pendant l'inventaire, j'ai affiché `config/mes_options.php` en entier dans mon contexte, contrairement à la règle du skill. Je n'en recopie aucun secret ici.

---

## 2. Risques

- **Rayon d'impact** : toute la ferme (au moins `asso-exemple.org`, `mairie-exemple.fr` et le site d'administration). Le code sous `plugins/auto/` est commun.
- **Versions de répertoire de SVP** : chaque site enregistre dans sa meta `plugin` le chemin `auto/cextras/v4.3.3`. Si l'on déplace ou supprime ce répertoire, chaque site doit retrouver le plugin dans `v<cible>` lors de l'actualisation des plugins. Ce comportement est à **prouver en préproduction** (ligne 6) avant la production.
- **Compatibilité** : à vérifier dans le `paquet.xml` de l'archive cible, pour SPIP 4.4.22, `saisies`, `yaml`, `verifier` et `crayons`. Si une dépendance doit être mise à jour, on traite un lot coordonné avec des points de contrôle distincts.
- **Schéma** : `cextras` 4.3.3 ne déclare pas de `schema`. En revanche, les champs extras modifient les tables des sites (`ALTER TABLE` par les plugins déclarants), et la cible peut introduire une mise à niveau. Après une écriture ou une migration, revenir au seul code ne suffit plus : il faut aussi restaurer la base correspondante de chaque site.
- **Purge large** : `?exec=mutualisation&upgradeplugins=oui&secret=…` vide `_DIR_CACHE`, les caches CSS/JS, les squelettes compilés, `plugin_xml_cache.gz`, etc. Cela compte comme une mutation et intervient après la capture des journaux du site. Le bouton **« Upgrader tout »** est exclu : il agit sur tous les sites d'un coup, sans point de contrôle.
- **Modifications locales** du plugin : inconnues tant que l'observation O4 n'est pas faite.
- **Bloquants actuels** : inconnues 1 à 6, et absence de sauvegarde **vérifiée et restaurée**.

---

## 3. Procédure proposée

### A. Observations en lecture seule (à lancer par vous, sur le serveur)

Commencez par fixer et vérifier la racine :

```bash
spip_root=/chemin/vérifié/de/la/ferme      # à remplacer
spip_root="$(realpath -e -- "$spip_root")" && test "$spip_root" != / && test -f "$spip_root/ecrire/inc_version.php" && test -f "$spip_root/spip.php"
```

- **O1. Version et marqueurs 5.0 :**
  ```bash
  rg -n '\$spip_version_branche' "$spip_root/ecrire/inc_version.php"
  ls -d "$spip_root/ecrire/src/SpipVersion.php" "$spip_root/bin/spip" "$spip_root/vendor/spip/framework" "$spip_root/plugins-dist/spip" 2>/dev/null
  ```
- **O2. Tous les plugins du serveur, partagés ou distribués** (on cherche surtout `saisies`, `yaml`, `verifier` et `crayons`) :
  ```bash
  find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -maxdepth 4 -type f -name paquet.xml -print0 \
    | xargs -0 rg -n -H '^(<paquet|[[:space:]]*(prefix|version|compatibilite|schema)=|[[:space:]]*<(necessite|utilise))'
  ```
- **O3. Chemins de plugins supplémentaires et répertoires propres aux sites :**
  ```bash
  rg -n '_DIR_PLUGINS_SUPPL|_SPIP_PATH|dossier_squelettes' "$spip_root/config" "$spip_root/sites" --glob '*.php'
  find -P "$spip_root/sites" -xdev -mindepth 2 -maxdepth 2 -type d \( -name plugins -o -name squelettes \) -print
  ```
- **O4. Provenance :**
  ```bash
  git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null
  git -C "$spip_root/plugins/auto/cextras/v4.3.3" status --short 2>/dev/null
  test -f "$spip_root/composer.lock" && composer --working-dir="$spip_root" show --locked
  ls -la "$spip_root/plugins/auto/" "$spip_root/plugins/auto/cextras/"
  ```
  Si l'on confirme SVP ou une archive, comparez le répertoire installé à l'archive officielle 4.3.3 (sommes de contrôle des fichiers) pour détecter des modifications locales.
- **O5. Moteur de base par site, sans afficher le fichier.** Seul l'un des mots `mysql`, `sqlite3` ou `unknown` doit sortir :
  ```bash
  for f in "$spip_root"/sites/*/config/connect.php; do
    e=$(rg -o "'(mysql|sqlite3)'" "$f" | head -1 | tr -d "'"); printf '%s: %s\n' "$(basename "$(dirname "$(dirname "$f")")")" "${e:-unknown}"
  done
  ls -1 "$spip_root"/sites/*/config/bases/ 2>/dev/null
  ```
  Si le résultat est `unknown`, cherchez `_DIR_CONNECT` ou `_FILE_CONNECT_INS` (noms de fichiers seulement, avec `rg -l`).
- **O6. Plugins actifs par site.** Sur chaque site, consultez la page `ecrire/?exec=admin_plugin`, ou interrogez `spip_meta` (`nom='plugin'`) en lecture seule. Datez le relevé. Un `tmp/meta_cache.php` ne sert qu'à donner une indication.
- **O7. Emplacement du site d'administration** `admin-ferme.example` (vhost et répertoire).
- **O8. Pile web et capacité :**
  ```bash
  apache2ctl -S 2>&1 | rg 'namevhost|alias'      # ou nginx -T filtré sur server_name/root/fastcgi_pass
  php -v
  df -hP -- "$spip_root"; df -iP -- "$spip_root"
  ```
- **O9. Journaux d'accès** : appels à `exec=mutualisation` sur les autres vhosts que le site d'administration.

### B. Préparation (lecture et planification)

1. Fixez la **version cible exacte** de `cextras` et, si l'observation O2 l'exige, celles de `saisies`/`yaml`. Récupérez l'archive officielle et sa somme de contrôle, puis lisez le `paquet.xml` réel (`compatibilite`, `necessite`, `utilise`, `schema`).
2. Remplissez la matrice d'impact ci-dessous. Une ligne `inconnu` bloque la suite.

   | Plugin | Actuel → cible | Chemin chargé | Provenance | Sites actifs | Compat. SPIP 4.4.22 | Dépendances | Modifs locales | Schéma |
   |---|---|---|---|---|---|---|---|---|
   | cextras | 4.3.3 → `<cible>` | `plugins/auto/cextras/v4.3.3` | SVP ? (O4) | ? (O6) | `[4.1.0;4.*]` → ? | saisies, yaml (absents de la copie) | ? | aucun → ? |
   | saisies / yaml | ? | ? (O2) | ? | ? | ? | ? | ? | ? |

3. Une fois tous les bloquants levés, suivez les **lignes 1 à 6** du plan d'actions dans l'ordre : sauvegarde, exercice de restauration, préproduction.
4. Si la préproduction (ligne 6) est concluante, suivez les **lignes 7 à 12** dans l'ordre. La ligne 10 s'exécute un site après l'autre et s'arrête à la première erreur inexpliquée.

---

## 4. Commandes : plan d'actions

Tout est **proposé, non exécuté**. Les valeurs entre `<…>` sont à remplacer par des valeurs vérifiées. Chaque ligne commence par ce bloc de garde :

```bash
spip_root="$(realpath -e -- "<racine vérifiée>")" && test "$spip_root" != /
backup_root=/srv/backups/spip-ferme/<AAAA-MM-JJTHHMMSSZ>     # hors racine web
backup_parent="$(realpath -e -- "$(dirname -- "$backup_root")")" && test "$backup_parent" != /
case "$backup_parent" in "$spip_root"|"$spip_root"/*) echo 'STOP: sauvegarde dans la racine SPIP' >&2; exit 1;; esac
target=<version cible vérifiée, ex. 4.x.y>; case "$target" in ''|*[!0-9.]*) echo 'STOP: cible invalide' >&2; exit 1;; esac
```

| # | Action | Objectif | Préconditions | Portée exacte | Impact | Sauvegarde / preuve | Commande proposée (non exécutée) | Contrôle de succès | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Créer le répertoire de sauvegarde | Disposer d'un emplacement privé, hors racine web | Bloc de garde valide ; espace disque suffisant (O8) | `$backup_root` uniquement | Aucun sur les sites | Manifeste créé (UTC, hôte, versions, chemins) | `( umask 077; mkdir -- "$backup_root" "$backup_root/databases" "$backup_root/files" )` | `stat -c '%a %U' "$backup_root"` renvoie `700` et le bon propriétaire | `rmdir` des répertoires s'ils sont encore vides |
| 2 | Archiver le code partagé et la configuration globale | Rendre possible la restauration exacte de la version actuelle | Ligne 1 faite ; provenance établie (O4) | `plugins/auto/` en entier, plus les dépendances trouvées en O2, `mutualisation/` et `config/mes_options.php` | Lecture seule sur les sites | `SHA256SUMS`. L'archive contient des identifiants : c'est un secret, à copier hors de l'hôte | `tar -C "$spip_root" -cf "$backup_root/files/partage.tar" plugins/auto mutualisation config/mes_options.php <chemins O2> && sha256sum -- "$backup_root/files/partage.tar" >>"$backup_root/SHA256SUMS"` | `tar -tf … >/dev/null` puis `sha256sum -c "$backup_root/SHA256SUMS"` réussissent | Aucune (rien n'est modifié en production) |
| 3 | Archiver les fichiers persistants de chaque site | Sauvegarder le contenu propre à chaque site | Ligne 1 faite ; liste des sites confirmée (O6, O7) | Pour chaque `<site>` : `sites/<site>/config/`, `IMG/` (et `squelettes/`/`plugins/` s'ils existent). Pas `local/` ni `tmp/` | Lecture seule | Une archive par site, ajoutée à `SHA256SUMS` | `tar -C "$spip_root/sites" -cf "$backup_root/files/<site>.tar" <site>/config <site>/IMG && sha256sum -- "$backup_root/files/<site>.tar" >>"$backup_root/SHA256SUMS"` | `tar -tf` et `sha256sum -c` réussissent ; taille cohérente avec `du -s` | Aucune |
| 4 | Sauvegarder la base de `asso-exemple.org` | Disposer d'un point de restauration de la base de ce site | Moteur connu (O5) ; nom de base et préfixe vérifiés ; fichier d'options `0600` préparé par vous ; moteurs de tables inspectés | Base de `asso-exemple.org` uniquement | Lecture ; écritures gelées si une table n'est pas InnoDB | `databases/asso-exemple.org.sql` ou `.sqlite`, ajouté à `SHA256SUMS` | MySQL : `( umask 077; set -C; "$dump_client" --defaults-file=<opts> --no-login-paths --single-transaction --quick --routines --events --triggers --no-create-db <db> >"$backup_root/databases/asso-exemple.org.sql" )`, puis test du code de sortie. SQLite : `sqlite3 <db.sqlite> ".timeout 5000" ".backup '$backup_root/databases/asso-exemple.org.sqlite'"` | Code de sortie 0 et fichier non vide ; SQLite : `PRAGMA integrity_check` renvoie `ok` | Aucune (lecture seule) |
| 5 | Sauvegarder la base de `mairie-exemple.fr` (et celle du site d'administration, sur une ligne distincte une fois localisé en O7) | Idem, pour chaque autre site | Idem ligne 4 | Base de ce site uniquement | Idem | `databases/mairie-exemple.fr.*` | Même forme que la ligne 4, avec le nom de ce site | Idem ligne 4 | Aucune |
| 6 | Exercice de restauration et test de la mise à jour en préproduction | Prouver que les sauvegardes 2 à 5 sont restaurables et que la cible fonctionne, y compris le comportement de SVP face au répertoire `v<version>` | Lignes 2 à 5 vérifiées ; instance isolée, sans route ni identifiants vers la production ; même version SPIP 4.4.22 et mêmes chemins | Copie isolée de la ferme et ses bases de test uniquement | Aucun sur la production | Journal de l'exercice : restauration, activation, mise à niveau, journaux, puis nouvelle restauration | Restauration selon `backup-rollback.md` §3 (MySQL) ou §4 (SQLite) sur l'instance isolée, puis lignes 9 et 10 rejouées sur cette copie | Tous les sites de la copie passent la validation (§5) ; le retour arrière est rejoué avec succès | Supprimer l'instance de test, sur votre accord, une fois les preuves consignées |
| 7 | Mettre la ferme entière en maintenance | Geler les écritures pendant la bascule | Ligne 6 concluante ; fenêtre annoncée ; mécanisme de maintenance connu (O8) | Tous les vhosts de la ferme | Indisponibilité ou lecture seule pour tous les sites | Heure UTC consignée | `<commande de maintenance propre à votre pile, à définir après O8>` | Chaque vhost renvoie la page de maintenance (contrôle `curl -sI`) | Lever la maintenance (inverse de la commande) |
| 8 | Préparer la cible hors du chemin chargé | Contrôler l'archive avant la bascule | Archive officielle et somme de contrôle obtenues ; `paquet.xml` relu | Répertoire temporaire hors racine web, par exemple `$backup_root/staging/` | Aucun | Somme de contrôle de l'archive consignée | `( umask 022; mkdir -- "$backup_root/staging" && <unzip ou tar> <archive> -d "$backup_root/staging" )`, puis `rg -n '^(<paquet\|[[:space:]]*(prefix\|version\|compatibilite\|schema)=\|[[:space:]]*<(necessite\|utilise))' "$backup_root/staging/<dir>/paquet.xml"` | `prefix="cextras"` et `version="$target"` ; compatibilité qui couvre 4.4.22 ; dépendances satisfaites | Supprimer `$backup_root/staging/` |
| 9 | Basculer le code de `cextras` (une seule fois) | Installer la cible et garder l'ancienne version récupérable | Lignes 7 et 8 OK ; procédure validée en ligne 6 (en particulier : faut-il retirer `v4.3.3` ou le conserver à côté ?) | `plugins/auto/cextras/` uniquement. Si une dépendance change, le lot coordonné passe sur des lignes séparées | Toute la ferme | Ligne 2 ; ancienne version déplacée, jamais supprimée | `test ! -e "$spip_root/plugins/auto/cextras/v$target" && cp -a -- "$backup_root/staging/<dir>" "$spip_root/plugins/auto/cextras/v$target"` puis, seulement si la ligne 6 l'a validé : `mv -- "$spip_root/plugins/auto/cextras/v4.3.3" "$backup_root/files/cextras-v4.3.3.retire"` | `rg -n 'version=' "$spip_root/plugins/auto/cextras/v$target/paquet.xml"` affiche la cible ; propriétaire et droits identiques à ceux de l'ancien répertoire | Remettre `v4.3.3` en place (`mv` inverse), puis retirer `v$target` vers `$backup_root`. Valable tant qu'aucune ligne 10 n'a écrit ; sinon, voir la ligne 10 |
| 10 | Lancer la mise à niveau des plugins **site par site** : `asso-exemple.org`, puis `mairie-exemple.fr`, puis le site d'administration (sous-lignes 10a, 10b, 10c) | Appliquer les éventuelles mises à niveau de base et actualiser les plugins de chaque site actif | Ligne 9 OK ; journaux de ce site (`sites/<site>/tmp/log/`) copiés dans `$backup_root/evidence/<site>/` ; sauvegarde de sa base (lignes 4 et 5) vérifiée | Un seul site à la fois | Purge large des caches de ce site ; migration possible de sa base | Journaux et capture de `spip_meta` (version du plugin) avant et après | Depuis le site d'administration, en session : `https://admin-ferme.example/ecrire/?exec=mutualisation&upgradeplugins=oui&secret=<valeur générée par le tableau de bord pour ce site>`. Ou, sur le site concerné, `ecrire/?exec=admin_plugin` puis la mise à jour. **Jamais « Upgrader tout »** | Validation §5 de ce site réussie avant de passer au suivant ; arrêt à la première erreur inexpliquée | Si la base de ce site a migré ou reçu des écritures : maintenance maintenue, restauration de **sa** base issue de la ligne 4 ou 5, plus le code de la ligne 2 (ligne 9 inversée), pour **tous** les sites déjà passés |
| 11 | Lever la maintenance | Remettre la ferme en service | Lignes 10a à 10c validées ; sites inactifs vérifiés (§5) | Tous les vhosts | Retour en production | Heure UTC consignée | Inverse de la ligne 7 | Codes HTTP 200 sur chaque site ; aucune erreur nouvelle dans les journaux PHP et SPIP | Remettre en maintenance (ligne 7) et appliquer le retour arrière (§6) |
| 12 | Retirer définitivement l'ancienne version | Faire le ménage une fois la stabilité acquise | Période d'observation écoulée ; aucune alerte ; votre accord explicite | `$backup_root/files/cextras-v4.3.3.retire` uniquement | Supprime le retour arrière rapide (l'archive de la ligne 2 reste) | Ligne 2 conservée hors de l'hôte | `test -d "$backup_root/files/cextras-v4.3.3.retire" && rm -r -- "$backup_root/files/cextras-v4.3.3.retire"` | Le chemin n'existe plus ; `sha256sum -c` de la ligne 2 réussit toujours | Restaurer depuis l'archive de la ligne 2 |

---

## 5. Validation (lecture seule, pour **chaque** site, pas seulement le site d'administration)

Pour `asso-exemple.org`, `mairie-exemple.fr`, `admin-ferme.example` et tout autre site trouvé en O6/O7 :

| Site | Plugin actif | Version observée | Dépendances actives | Schéma à jour | Contrôles fonctionnels | Journaux propres | Résultat |
|---|---|---|---|---|---|---|---|
| … | | | | | | | |

- Partie publique : `curl -sS -o /dev/null -w '%{http_code}\n' https://<site>/` renvoie 200, et une page avec champs extras s'affiche correctement.
- Espace privé : connexion, `ecrire/?exec=admin_plugin` sans alerte, version de `cextras` = cible. Édition d'un objet doté de champs extras (formulaire et enregistrement) sur une copie de test, ou en lecture seule en production.
- Base : consultation en lecture seule de `spip_meta` (version installée du plugin, `version_installee`).
- Journaux : `sites/<site>/tmp/log/` et journal PHP-FPM, sans nouvelle erreur depuis l'heure de la ligne 9.
- Sites où `cextras` est **inactif** : vérifier qu'ils ne régressent pas (partie publique et espace privé).
- Contrôles propres aux mutations : voir les lignes 1 à 12, colonne « Contrôle de succès ».

---

## 6. Retour arrière

**Déclencheurs :** erreur PHP ou SPIP inexpliquée, plugin désactivé ou introuvable sur un site, échec de mise à niveau de base, fonctionnalité de champs extras cassée, incident de sécurité qui chevauche la fenêtre.

**Unité de restauration :** le code partagé de la ligne 2 (`plugins/auto/` et dépendances), plus **pour chaque site déjà traité en ligne 10** sa base (ligne 4 ou 5) et ses fichiers (ligne 3), tous issus **du même manifeste**. Ne mélangez jamais une base d'un point de contrôle avec le code d'un autre.

| Étape atteinte | Action |
|---|---|
| Lignes 1 à 8 seulement | Rien à restaurer en production ; lever la maintenance (inverse de la ligne 7) |
| Ligne 9 faite, aucune ligne 10 | Retour arrière de la ligne 9, puis inverse de la ligne 7 |
| Une ou plusieurs lignes 10 faites | Maintenance maintenue. Retour arrière de la ligne 10 pour chaque site déjà passé, dans l'ordre inverse, puis retour arrière de la ligne 9 |
| État incertain | Restaurer le code connu (ligne 2) et **toutes** les bases et fichiers des lignes 3 à 5. Conserver à part les preuves de l'état courant |

Validez ensuite chaque site selon le §5 avant de lever la maintenance.

---

**Vérification de couverture :** toutes les actions qui modifient un état dans cette réponse ont leur ligne complète dans le plan. Il s'agit de la création du répertoire de sauvegarde, des archives, des sauvegardes de base, de l'exercice et de la préproduction, de la maintenance (entrée et sortie), de la préparation de la cible, de la bascule du code, des mises à niveau par site et du retrait final. La procédure, la validation et le retour arrière n'y font référence que par numéro de ligne. Si le site d'administration ou des dépendances à mettre à jour sont confirmés, ils recevront leurs propres lignes (5 bis, 9 bis) avant toute exécution.

**Prochaine étape demandée :** envoyez-moi les sorties de O1 à O9 (assainies) et la version cible. Je transformerai alors ce plan en procédure définitive, ou je vous signalerai le blocage restant.

## spip-mutualisation-maintenance, avec encadré — cas 26, tirage 1

# Passage de la ferme à SPIP 5.0 : à ne pas préparer pour la production

Je ne vais pas préparer de passage à SPIP 5.0 pour cette ferme. Deux raisons :

1. **SPIP 5.0 ne gère pas encore la mutualisation.** Sa documentation marque le point d'entrée mutualisé (`sites/default/public/index.php`) « expérimental — todo », et il n'existe aucune procédure de migration pour une ferme.
2. **Mutualisation facile 2.0.1, installée ici, ne fonctionne pas sous 5.0.** Elle ne déclare que `[4.2.0;4.*]`. Sous 5.0, elle ne sépare plus les sites : le noyau définit `_DIR_IMG`, `_DIR_TMP`, `_DIR_VAR` et `_DIR_CONNECT` avant que `config/mes_options.php` s'exécute. `demarrer_site()` ne peut donc plus fixer que `_DIR_SITE` et `_SPIP_PATH`. Résultat : **tous les sites utiliseraient la connexion, `IMG/`, `local/` et `tmp/` de la racine partagée.** Ils afficheraient le même contenu et leurs écritures se mélangeraient.

Je peux au mieux vous préparer **un essai sur une copie isolée, hors production**, mais seulement si vous le demandez à part (voir la fin). Avant cela, une anomalie de version doit être levée (constat 2).

Je n'ai travaillé que sur la copie de la racine partagée, sans accès au serveur. Je n'ai rien exécuté et je ne propose ici aucune commande qui modifie quoi que ce soit.

---

## Constats

**Ce qui est vérifié dans la copie :**

| # | Constat | Preuve |
|---|---|---|
| 1 | La version déclarée est **SPIP 4.4.22** | `ecrire/inc_version.php` : `$spip_version_branche = '4.4.22'`, `_SPIP_VERSION_ID 40422`, `$spip_version_code = 2026_09_01`, `$spip_version_base = 2026_08_03_00` |
| 2 | **Anomalie : ce même fichier contient du code de démarrage de SPIP 5.0** | `use function SpipLeague\Component\Kernel\{app, param}` ; garde `!function_exists('SpipLeague\Component\Kernel\app')` ; `_DIR_RESTREINT` calculé par `param('spip.dirs.core')` ; `_ROOT_RESTREINT` tiré de `app()->getCwd()`. Ce fichier ne ressemble pas à celui d'une 4.4 publiée. |
| 3 | Les autres marqueurs de 5.0 sont absents **de la copie** : pas de `ecrire/src/SpipVersion.php`, de `bin/spip`, de `vendor/spip/framework/` ni de `plugins-dist/spip/` | Cela ne prouve rien : la copie est partielle (`ecrire/` ne contient que `inc_version.php`, et `spip.php` n'y est pas) |
| 4 | Mutualisation facile **2.0.1**, compatibilité `[4.2.0;4.*]` | `mutualisation/paquet.xml` |
| 5 | Elle est chargée par `config/mes_options.php`, via `require _DIR_RACINE.'mutualisation/mutualiser.php'` | `config/mes_options.php` |
| 6 | Réglages de `demarrer_site()` : `repertoire` = `sites`, `creer_site` = true, `creer_base` = true, `code` = la valeur par défaut, `table_prefix` = false, `cookie_prefix` = true, `url_img_courtes` = true | `config/mes_options.php` |
| 7 | Le site d'administration est `_SITES_ADMIN_MUTUALISATION` = `admin-ferme.example`. `$site` vient de `HTTP_HOST`, sans `www.` ; un port autre que 80 devient `_port` | `config/mes_options.php` |
| 8 | Deux sites existent dans la copie : `asso-exemple.org` et `mairie-exemple.fr`. Chacun a `config/connect.php`, `IMG/`, `local/` et `tmp/` | `sites/*/` |
| 9 | Un seul plugin partagé : Champs Extras **4.3.3**, compatibilité `[4.1.0;4.*]`. Il exige `saisies [4.2.0;]` et `yaml [3.0.0;]` | `plugins/auto/cextras/v4.3.3/paquet.xml` |
| 10 | `config/mes_options.php`, partagé par toute la ferme, contient **en clair** les identifiants MySQL d'installation (`_INSTALL_USER_DB` / `_INSTALL_PASS_DB`) | Je ne les recopie pas ici |

**Ce qui reste inconnu :**

- **La version réellement installée sur le serveur.** Les constats 1 et 2 se contredisent. Il peut s'agir d'une mise à jour partielle ou interrompue, d'un fichier modifié à la main, ou d'un défaut de la copie.
- La provenance du cœur (Git, Composer ou archive) et les modifications locales.
- Le moteur de base de chaque site. Dans la copie, les deux `connect.php` sont des fichiers de 52 octets sans appel `spip_connect_db`, donc non représentatifs. `_INSTALL_SERVER_DB = 'mysql'` ne vaut que pour les futurs sites, pas pour les sites existants.
- Où se trouvent `saisies` et `yaml`. Ils ne sont pas dans `plugins/auto/` de la copie, et je n'ai vu aucun `plugins-dist/`.
- Les plugins actifs sur chaque site, la pile web et PHP, l'espace disque.
- Si `?exec=mutualisation` répond sur d'autres domaines que le site d'administration.

## Risques

- **Toute la ferme est concernée.** Le cœur, `mutualisation/` et `plugins/` sont partagés. Changer de version touche les deux sites et la racine en même temps. On ne peut pas tester sur un seul site en production.
- **Les sites cesseraient d'être séparés sous 5.0.** C'est le point 2 de l'introduction, et c'est ce qui bloque tout.
- **Les plugins ne sont pas compatibles.** Mutualisation facile (`[4.2.0;4.*]`) et Champs Extras (`[4.1.0;4.*]`) ne déclarent pas la 5.0. Pour `saisies` et `yaml`, il faut d'abord savoir où ils sont et en quelle version.
- **Le constat 2 est peut-être déjà un début de 5.0.** Si le serveur exécute vraiment le démarrage 5.0, la ferme ne sépare peut-être déjà plus ses sites. Le contrôle 6 ci-dessous le vérifie. Si c'est le cas, voici ce qu'il faut faire, dans cet ordre :
  1. geler les écritures ;
  2. conserver l'état actuel ;
  3. restaurer la sauvegarde 4.4 d'avant la mise à jour, c'est-à-dire les fichiers partagés **et** la base de chaque site.

  Cette procédure n'est pas rédigée ici : elle doit faire l'objet d'une demande à part, à partir des résultats.
- **Sécurité, indépendamment de la 5.0 :**
  - Des identifiants en clair sont dans un fichier partagé (constat 10).
  - `creer_site` et `creer_base` sont activés avec le code d'activation par défaut, qui est public. Un domaine inconnu qui pointe vers la ferme peut donc ouvrir le formulaire de création de site.
  - Sans authentification, `renouvelle_alea`, `dirliste` et `dirsize` de `?exec=mutualisation` s'exécutent sur tous les sites. Seuls `upgrade` et `upgradeplugins` demandent un secret.

  Ce sont des constats. Les corriger (changer les identifiants, désactiver la création de site, filtrer l'accès) ferait l'objet d'actions à planifier à part. Je n'en propose aucune ici.
- **On ne peut pas préparer de modification.** Plusieurs conditions d'arrêt sont réunies : version et provenance incertaines, moteur de chaque site inconnu, compatibilité 5.0 absente, aucune sauvegarde vérifiée et restaurable.

## Procédure proposée

Ce sont uniquement des observations, sans aucune modification. Lancez les commandes de la section Commandes et renvoyez-moi leur sortie.

1. **Situer la racine partagée.** Récupérer son chemin réel et vérifier qu'elle contient bien un SPIP (contrôle 1).
2. **Lever l'anomalie de version.** Relever les marqueurs 4.4 et 5.0 sur le serveur (contrôles 2 et 3). Si l'un des marqueurs 5.0 est présent, la ferme est à traiter comme une 5.0 : on s'arrête à l'inventaire, et on passe au point 6 avant toute autre chose.
3. **Connaître la provenance du cœur.** Git, Composer ou archive, et modifications locales éventuelles (contrôle 4).
4. **Lister les sites et le moteur de chaque base,** sans afficher les fichiers de connexion (contrôle 5).
5. **Faire l'inventaire des plugins partagés et de ceux propres à chaque site,** y compris `saisies` et `yaml` (contrôle 7).
6. **Vérifier que chaque site reste séparé** : lire la page d'accueil de chacun et comparer (contrôle 6).
7. **Ensuite seulement,** et si vous le demandez à part : préparer un essai 5.0 sur une copie isolée, hors production. Pour la production, en l'état actuel, il n'y a rien à suivre : le tableau des actions est vide.

## Commandes

### Observations en lecture seule (à lancer par vous)

Ces commandes ne modifient rien. Remplacez `/chemin/racine/ferme` par le vrai chemin. Aucune n'affiche de fichier de connexion ni de configuration complète.

```bash
# 1. Racine partagée
spip_root="$(realpath -e -- /chemin/racine/ferme)"
test -n "$spip_root" && test "$spip_root" != / && echo "racine: $spip_root"
test -f "$spip_root/ecrire/inc_version.php" && test -f "$spip_root/spip.php" && echo "marqueurs SPIP: ok"

# 2. Marqueurs de la version 4.4
grep -nE '\$spip_version_(branche|affichee) *=|_SPIP_VERSION_ID|SpipLeague\\Component\\Kernel' \
  "$spip_root/ecrire/inc_version.php"

# 3. Marqueurs de la version 5.0 (un seul suffit)
grep -n "VERSION *=" "$spip_root/ecrire/src/SpipVersion.php" 2>/dev/null
for p in bin/spip vendor/spip/framework plugins-dist/spip; do
  test -e "$spip_root/$p" && echo "PRESENT: $p" || echo "absent: $p"
done

# 4. Provenance du cœur
git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null && git -C "$spip_root" status --short | head -50
ls -l "$spip_root/composer.json" "$spip_root/composer.lock" 2>/dev/null

# 5. Sites et moteur de chaque base (n'affiche que mysql/sqlite3/unknown)
for c in "$spip_root"/sites/*/config/connect.php; do
  e=unknown
  grep -qE "spip_connect_db\(.*'sqlite3'" "$c" && e=sqlite3
  grep -qE "spip_connect_db\(.*'mysql'" "$c" && e=mysql
  echo "$(basename "$(dirname "$(dirname "$c")")"): $e"
done
find -P "$spip_root/sites" -xdev -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort
find -P "$spip_root/sites" -xdev -mindepth 2 -maxdepth 2 -type l -printf '%p -> %l\n'

# 6. Les sites sont-ils encore séparés ? (comparer les titres et les empreintes)
for h in asso-exemple.org mairie-exemple.fr admin-ferme.example; do
  printf '%s  ' "$h"; curl -fsS "https://$h/" | tee >(sha256sum | cut -c1-16 >&2) | grep -o '<title>[^<]*</title>' | head -1
done

# 7. Plugins partagés et chemins ajoutés
find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -mindepth 1 -maxdepth 3 \
  -name paquet.xml -print 2>/dev/null
grep -rlE '_DIR_PLUGINS_SUPPL|_SPIP_PATH' "$spip_root/config" "$spip_root/sites" --include='*.php' 2>/dev/null
```

### Tableau des actions

Il est **vide**. Cette réponse ne propose aucune action qui modifie un fichier, une base ou la configuration.

| # | Action | Objectif | Préconditions | Périmètre exact | Impact | Sauvegarde / preuve | Commande proposée | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| — | Aucune | Passer la ferme en production à 5.0 n'est pas pris en charge (mutualisation expérimentale, sites non séparés) | — | — | — | — | Aucune : rien à exécuter | — | — |

## Validation

Les contrôles 1 à 7 ci-dessus suffisent. Ils portent sur **chaque site** (`asso-exemple.org`, `mairie-exemple.fr`) et sur le site d'administration, pas seulement sur ce dernier. Dans leurs résultats, je regarderai :

- **la version** : un `4.4.x` cohérent et **aucun** marqueur 5.0 ;
- **la séparation** : une page différente et un titre différent pour chaque domaine ;
- **les bases** : un moteur identifié pour chaque site ;
- **les plugins** : tous localisés, y compris `saisies` et `yaml`.

Il n'y a pas de contrôle de réussite propre à une modification, puisque le tableau des actions n'en contient aucune.

## Retour arrière

Comme rien n'est modifié, il n'y a rien à annuler.

Un seul cas déclenche une restauration : si les contrôles 3 ou 6 montrent que le serveur exécute déjà la 5.0, ou que les sites affichent le même contenu. Il faut alors :

1. geler les écritures ;
2. conserver l'état actuel ;
3. restaurer la sauvegarde 4.4 d'avant la mise à jour.

Cette restauration doit couvrir **ensemble** les fichiers partagés (cœur, `mutualisation/`, `plugins/`) **et la base de chaque site**. Restaurer seulement les fichiers, alors que les bases ont déjà migré, ne marche pas. La procédure détaillée, avec son tableau d'actions, sera rédigée dans une demande à part, d'après vos résultats.

---

**Pour la suite**, envoyez-moi la sortie des contrôles 1 à 7. Si la ferme est bien une 4.4 cohérente et que vous voulez quand même évaluer la 5.0, je peux préparer, dans une demande à part, **un essai sur une copie isolée** : autre machine ou autre hôte virtuel, bases copiées, aucun domaine de production, sans rien changer à la production. Cet essai sert à observer le comportement de la 5.0. Il ne prépare pas une mise en production : celle-ci restera impossible tant que SPIP 5.0 ne gère pas la mutualisation et que Mutualisation facile ne déclare pas la 5.0.

## spip-mutualisation-maintenance, avec encadré — cas 26, tirage 2

# Passage de la ferme à SPIP 5.0 : pas de procédure possible aujourd'hui

Réponse courte : je ne prépare pas de plan de migration de cette ferme vers SPIP 5.0. La mutualisation n'est pas encore prise en charge par SPIP 5.0, et le plugin Mutualisation facile installé ne fonctionne pas sur cette version. Ci-dessous, ce que j'ai vérifié, pourquoi c'est bloquant, et ce qu'on peut faire sans risque pour la production.

## Constats

Tout ce qui suit vient de la copie de la racine partagée. Je n'ai rien observé sur le serveur.

| Élément | Constat | Preuve |
|---|---|---|
| Version du cœur partagé | **SPIP 4.4.22** (`_SPIP_VERSION_ID` 40422, `$spip_version_code` 2026_09_01) | `ecrire/inc_version.php:464-473` |
| Marqueurs 5.0 | Aucun : pas de `ecrire/src/SpipVersion.php`, pas de `bin/spip`, pas de `vendor/spip/framework/`, pas de `plugins-dist/spip/` | absence de ces chemins à la racine |
| Mutualisation facile | Version **2.0.1**, `compatibilite="[4.2.0;4.*]"`, chargée par `require _DIR_RACINE.'mutualisation/mutualiser.php'` (répertoire `mutualisation/` à la racine, pas sous `plugins/`) | `mutualisation/paquet.xml`, `config/mes_options.php` |
| Configuration effective | `demarrer_site($site, …)` avec `'repertoire' => 'sites'`, `creer_site` et `creer_base` à `true`, `cookie_prefix` à `true`, `table_prefix` à `false`, `url_img_courtes` à `true`. `$site` est dérivé de `HTTP_HOST` (on retire `www.`, puis `:port` devient `_port`, sauf le port 80) | `config/mes_options.php` |
| Site d'administration | `_SITES_ADMIN_MUTUALISATION` = `admin-ferme.example` | `config/mes_options.php` |
| Sites présents dans `sites/` | `asso-exemple.org` et `mairie-exemple.fr`, chacun avec `config/connect.php`, `IMG/`, `local/`, `tmp/` | arborescence de `sites/` |
| Plugin partagé | `plugins/auto/cextras/v4.3.3` : Champs Extras 4.3.3, `compatibilite="[4.1.0;4.*]"`, nécessite `saisies` et `yaml` | `plugins/auto/cextras/v4.3.3/paquet.xml` |

**Inconnues :**
- **Moteur de base de chaque site.** Les deux `connect.php` de la copie ne contiennent pas d'appel `spip_connect_db` exploitable. Je ne peux donc pas dire s'il s'agit de MySQL/MariaDB ou de SQLite.
- **Origine de l'installation** (Git, Composer ou archive/SVP) et modifications locales : rien ne permet de le savoir à partir de la copie.
- **Pile web/PHP** (Apache ou Nginx/PHP-FPM, versions de PHP) : à relever sur le serveur.
- **Plugins `saisies` et `yaml`.** `cextras` en a besoin, mais ils ne sont pas dans la copie de `plugins/`. Ils sont peut-être ailleurs (`plugins-dist/`, chemin supplémentaire), ou la copie est incomplète.
- **Site d'administration.** `admin-ferme.example` n'apparaît pas dans `sites/`. Il est peut-être servi depuis la racine, ou ailleurs.

## Risques

1. **SPIP 5.0 ne gère pas encore la mutualisation (bloquant).** Dans la documentation 5.0, le point d'entrée mutualisé (`sites/default/public/index.php`) est marqué « expérimental — todo ». Il n'existe aucune procédure officielle de migration d'une ferme. Toute la ferme est concernée : le cœur partagé, les deux sites connus et le site d'administration.
2. **Mutualisation facile 2.0.1 ne sépare plus les sites sous 5.0 (bloquant).** Le plugin déclare `[4.2.0;4.*]`. Sous 5.0, le noyau définit `_DIR_IMG`, `_DIR_TMP`, `_DIR_VAR` et `_DIR_CONNECT` avant l'exécution de `config/mes_options.php`. `demarrer_site()` ne fixe alors plus que `_DIR_SITE` et `_SPIP_PATH`. Résultat : **tous les sites utiliseraient la connexion, `IMG/`, `local/` et `tmp/` de la racine partagée**. Ils afficheraient le même contenu et pourraient écrire dans les mêmes données.
3. **Champs Extras 4.3.3** déclare `[4.1.0;4.*]`. Il n'est pas compatible 5.0 d'après sa déclaration.
4. **`creer_site` et `creer_base` sont à `true`.** Si le domaine d'un site n'est pas reconnu après un changement du cœur, la mutualisation peut lancer la création d'un site ou d'une base au lieu de servir l'existant. C'est une raison de plus pour ne faire aucun essai en production.
5. **Identifiants en clair (point de sécurité, indépendant de la migration).** `config/mes_options.php` contient en clair l'utilisateur et le mot de passe MySQL utilisés pour créer les bases (`_INSTALL_USER_DB` / `_INSTALL_PASS_DB`). Je ne les reproduis pas ici. Gardez ce fichier hors de toute copie partagée ou de tout ticket. Si ce mot de passe est encore valable, il faut prévoir sa rotation séparément.

## Procédure proposée

Aucune action qui modifie la ferme n'est proposée. Pour cette demande, la seule réponse correcte est de **rester en SPIP 4.4** en production.

Ce qu'on peut faire sans risque :

1. **Finir l'inventaire en lecture seule** sur le serveur, avec les commandes de la section suivante : origine de l'installation, moteur de base par site, pile web/PHP, emplacement de `saisies` et `yaml`, site d'administration.
2. **Garder la ferme à jour dans la branche 4.4** (corrections de sécurité de 4.4.x et de Mutualisation facile 2.x). Ce serait une demande distincte, qui aurait sa propre préparation : sauvegarde vérifiée et restaurable, puis plan d'action.
3. **Surveiller l'avancement.** Il faudra une version de Mutualisation facile (ou un mécanisme de mutualisation du cœur 5.0) qui déclare la compatibilité 5.0, et une procédure de ferme documentée. Il faudra aussi des versions 5.0 de `cextras`, `saisies` et `yaml`. Un vrai plan de migration ne sera possible qu'à ce moment-là.
4. **Si vous voulez tester quand même : seulement un essai sur une copie isolée**, hors production. Cela veut dire un autre hôte ou conteneur, des copies des bases, aucun DNS de production, et `creer_site`/`creer_base` désactivés. Cet essai sert à observer, pas à préparer une bascule. Le symptôme attendu, d'après le risque 2, est que les sites partagent le même contenu. Je peux en préparer le cadre si vous le souhaitez, comme une demande à part.

## Commandes

**Aucune commande modifiant l'état n'est proposée.** Le tableau du plan d'action est donc vide.

| Action | Objectif | Préconditions | Périmètre exact | Impact | Sauvegarde/preuve | Commande proposée | Contrôle de succès | Retour arrière |
|---|---|---|---|---|---|---|---|---|
| — | — | — | — | — | — | — | — | — |

Observations en lecture seule, à lancer par vos soins (remplacez `/chemin/racine` par la racine partagée réelle) :

```bash
spip_root="$(realpath -e -- /chemin/racine)"; test -n "$spip_root" && test "$spip_root" != /

# Version du cœur et absence de marqueurs 5.0
rg -n '\$spip_version_(affichee|branche)|_SPIP_VERSION_ID' "$spip_root/ecrire/inc_version.php"
ls -d "$spip_root/ecrire/src/SpipVersion.php" "$spip_root/bin/spip" \
      "$spip_root/vendor/spip/framework" "$spip_root/plugins-dist/spip" 2>&1

# Origine de l'installation
git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null && git -C "$spip_root" status --short
test -f "$spip_root/composer.json" && test -f "$spip_root/composer.lock" && echo composer

# Moteur de base par site : n'affiche que le nom du moteur, jamais le contenu du fichier
for f in "$spip_root"/sites/*/config/connect.php; do
  printf '%s: %s\n' "$(basename "$(dirname "$(dirname "$f")")")" \
    "$(grep -oE "'(mysql|sqlite3)'" "$f" | sort -u | tr -d "'" | tr '\n' ' ' || echo unknown)"
done

# Emplacement des plugins (saisies, yaml) et chemins supplémentaires
find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -mindepth 1 -maxdepth 3 \
  -type f -name paquet.xml -print 2>/dev/null
rg -n '_DIR_PLUGINS_SUPPL|_SPIP_PATH' "$spip_root/config" "$spip_root/sites" --glob '*.php' 2>/dev/null

# Pile PHP
php -v | head -1
```

## Validation

Vérifications à faire, toutes en lecture seule :
- La racine partagée annonce bien `4.4.22` et aucun marqueur 5.0 n'est présent.
- Chaque site répond avec **son propre** contenu : `asso-exemple.org`, `mairie-exemple.fr`, et le site d'administration `admin-ferme.example` (vérifiez où il est servi). Faites aussi le contrôle par une requête HTTP vers chaque domaine, sans vous fier seulement au site d'administration.
- Notez le moteur de base de chaque site, ainsi que l'origine du cœur.

## Retour arrière

Sans objet : rien n'est modifié.

Pour l'avenir, si quelqu'un passe la racine partagée en 5.0 malgré tout, le symptôme est que **tous les sites affichent le même contenu**. Dans ce cas :
1. bloquer les écritures (mode maintenance, ou blocage côté serveur web) ;
2. conserver l'état courant (fichiers et bases) pour analyse ;
3. restaurer la sauvegarde 4.4 prise avant la mise à jour : les fichiers partagés **et la base de chaque site**.

Restaurer seulement les fichiers ne suffit pas si des bases ont déjà migré.

## spip-mutualisation-maintenance, encadré après revue (cause confirmée avant restauration, conservation permise) — cas 23

# Mise à jour des plugins partagés : préparation

**Je ne prépare pas la mise à jour des plugins : cette ferme tourne sous SPIP 5.0.0-beta, pas sous 4.x.** La mutualisation n'est pas encore prise en charge sous SPIP 5.0. La documentation 5.0 marque le point d'entrée mutualisé (`sites/default/public/index.php`) « expérimental — todo », et aucune procédure de ferme n'existe. Je ne rédige donc pas de plan de mise à jour pour une ferme 5.0.

Voici ce que je prépare à la place, et qui reste utile :

- l'inventaire en lecture seule à lancer sur le serveur ;
- une **sauvegarde conservatoire cohérente** de l'état actuel : fichiers partagés et base de chaque site ;
- son test de restauration, et le retour arrière vers cet état.

Si vous voulez quand même essayer de mettre à jour les plugins, je peux au plus proposer un essai sur une copie isolée, hors production.

Tout ce qui suit est **proposé — non exécuté**. Je n'ai lu que la copie de la racine partagée, pas le serveur réel.

---

## 1. Constats

### Faits vérifiés dans la copie de la racine partagée

| Constat | Preuve | Portée |
|---|---|---|
| Le cœur est en SPIP **5.0.0-beta** | `ecrire/src/SpipVersion.php` : `VERSION = '5.0.0-beta'`, `CODE = 2026_09_22`, `BASE = 2026_08_03_00`, PHP 8.4.0 à 8.5.99 | ferme entière |
| Autres marqueurs 5.0 | `bin/spip` existe (console SpipLeague) ; `vendor/spip/framework/composer.json` existe | ferme entière |
| Le cœur est installé par Composer | `vendor/` et `bin/spip`, qui exige `vendor/autoload_runtime.php`. Mais il n'y a **ni `composer.json` ni `composer.lock` à la racine** de la copie | ferme entière |
| Chargement de la mutualisation | `config/mes_options.php` fait `require _DIR_RACINE.'mutualisation/mutualiser.php'`. Le module se trouve dans `mutualisation/` à la racine, pas dans `plugins/` | ferme entière |
| Version de Mutualisation facile | `mutualisation/paquet.xml` : `version="2.0.1"`, `compatibilite="[4.2.0;4.*]"`. Elle n'est **pas compatible 5.0** | ferme entière |
| Appel effectif de `demarrer_site()` | `repertoire` vaut `'sites'`, `table_prefix` vaut `false`, `cookie_prefix` vaut `true`, `url_img_courtes` vaut `true`, `creer_site` et `creer_base` valent `true`, `code` vaut `'ecureuil'` | ferme entière |
| Calcul de `$site` | `HTTP_HOST` sans `www.`, avec redirection canonique et réécriture `:` → `_` pour les ports | ferme entière |
| Site d'administration | `_SITES_ADMIN_MUTUALISATION` vaut `'admin-ferme.example'` | ferme entière |
| Sites présents sous `sites/` | `asso-exemple.org` et `mairie-exemple.fr`. Chacun a `config/connect.php`, `IMG/`, `local/` et `tmp/` | 2 sites |
| Plugins partagés présents | Un seul : `plugins/auto/cextras/v4.3.3/` (Champs Extras 4.3.3), avec `compatibilite="[4.1.0;4.*]"`. Il **n'est pas compatible 5.0**. Il exige `saisies [4.2.0;]` et `yaml [3.0.0;]`, qui sont **absents** de la copie | tous les sites qui l'activent |
| Pas de `plugins-dist/spip/` dans la copie | Arborescence de la copie | — |
| Pas de site `admin-ferme.example` sous `sites/` dans la copie | Arborescence de la copie | — |

### Inconnues, toutes à lever par l'inventaire (§3)

- Les révisions exactes du cœur et des paquets Composer. L'étiquette « 5.0.0-beta » ne suffit pas. Il faut aussi savoir où se trouvent `composer.json` et `composer.lock`.
- La provenance de `plugins/auto/cextras` (SVP, archive ou Git), ses éventuelles modifications locales, et les sites qui l'activent.
- Le moteur de base de chaque site. Les deux `connect.php` de la copie ne contiennent aucun type reconnaissable (ni `mysql`, ni `sqlite3`, ni `pg`), donc le moteur reste inconnu. `_INSTALL_SERVER_DB` vaut `'mysql'` dans `config/mes_options.php`, mais ce réglage ne sert qu'à créer les sites. Ce n'est pas une preuve du moteur de chaque site.
- Les noms réels des bases. Ils viennent de `_INSTALL_NAME_DB` (`mu_` suivi de `prefixe_mutualisation($site)`) et doivent être confirmés par l'inventaire, pas recalculés de tête.
- L'existence d'un `config/connect.php` à la racine, et des écritures récentes dans les `IMG/` et `tmp/` de la racine (voir le risque R2).
- La pile web et PHP, le compte d'exécution de PHP, l'espace disque et les inodes.

## 2. Risques

| # | Risque | Rayon d'impact |
|---|---|---|
| R1 | **Mettre à jour un plugin partagé sur cette ferme modifie le code de tous les sites d'un coup.** On ne peut pas tester la mise à jour sur un seul site de production. Sous 5.0, la mise à jour par site (`?exec=mutualisation&upgradeplugins=…`) vient de Mutualisation 2.0.1, qui ne déclare que `[4.2.0;4.*]`. Son comportement sous 5.0 n'est pas établi. | ferme entière |
| R2 | **Les sites ne sont peut-être pas isolés.** Le noyau 5.0 définit `_DIR_IMG`, `_DIR_TMP`, `_DIR_VAR` et `_DIR_CONNECT` avant `config/mes_options.php`. `demarrer_site()` ne fixe alors que `_DIR_SITE` et `_SPIP_PATH`, et chaque site risque d'utiliser la connexion, les `IMG/`, `local/` et `tmp/` de la **racine**. Il faut le vérifier en lecture seule (§3, O6 à O8) avant toute autre décision. S'il est confirmé, c'est un incident : geler les écritures, conserver l'état, puis restaurer la sauvegarde cohérente d'avant le passage en 5.0. Ce traitement sort de ce plan. | ferme entière, toutes les bases |
| R3 | `cextras` 4.3.3 n'est pas déclaré compatible 5.0, et ses dépendances `saisies` et `yaml` manquent dans la copie. Les « mises à jour » qu'on trouve seraient vraisemblablement des versions 4.x. Aucune version cible compatible 5.0 n'est vérifiée. | sites qui activent cextras |
| R4 | La provenance du cœur est incomplète : il y a `vendor/` mais ni `composer.json` ni `composer.lock` à la racine de la copie. Un `composer update` lancé sans le lock d'origine changerait des dépendances non maîtrisées. | ferme entière |
| R5 | `config/mes_options.php` contient **en clair** les identifiants SQL d'installation (`_INSTALL_USER_DB` et `_INSTALL_PASS_DB`). Je ne les recopie pas. Toute archive de `config/` est donc un secret. | hôte, bases |
| R6 | `creer_site` et `creer_base` valent `true`, avec le code d'activation par défaut `'ecureuil'`. Tout domaine routé vers la ferme peut lancer la création d'un site et de sa base. Par ailleurs, `?exec=mutualisation` expose sans authentification `renouvelle_alea`, `dirliste` et `dirsize` sur chaque site. Bloquer ces points d'accès serait une mutation à part, que ce plan ne propose pas. | ferme entière |
| R7 | `url_img_courtes` vaut `true` : la mutualisation écrit des `.htaccess` dans les `IMG/` et `local/` de la racine. Il faut donc les sauvegarder aussi. | ferme entière |

**Blocages pour la mise à jour des plugins :** version 5.0 (mutualisation non prise en charge), pas de version cible compatible, provenance inconnue, et aucune sauvegarde vérifiée et restaurable pour l'instant. La seule partie à préparer maintenant est la sauvegarde conservatoire.

## 3. Procédure proposée

### Observations en lecture seule

L'opérateur les lance et me renvoie seulement les résultats filtrés.

```bash
# O0 — fixer et valider la racine (chemin réel donné par l'opérateur)
spip_root=<racine_ferme_vérifiée>
spip_root="$(realpath -e -- "$spip_root")" && test -n "$spip_root" && test "$spip_root" != / || echo STOP

# O1 — marqueurs de version 5.0 sur le serveur réel
rg -n "VERSION =|CODE =|BASE =" "$spip_root/ecrire/src/SpipVersion.php"
for p in bin/spip vendor/spip/framework plugins-dist/spip; do test -e "$spip_root/$p" && echo "présent: $p"; done

# O2 — provenance Composer et Git, révisions exactes (consigner date et hash)
date -u +%FT%TZ
ls -l -- "$spip_root"/composer.json "$spip_root"/composer.lock 2>&1
test -f "$spip_root/composer.lock" && composer --working-dir="$spip_root" show --locked
git -C "$spip_root" rev-parse HEAD 2>/dev/null; git -C "$spip_root" status --short 2>/dev/null

# O3 — plugins partagés et leurs déclarations
find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -mindepth 1 -maxdepth 4 -type f -name paquet.xml -print 2>/dev/null
rg -n '^(<paquet|[[:space:]]*(prefix|version|compatibilite|schema)=|[[:space:]]*<(necessite|utilise))' \
  "$spip_root/plugins/auto/cextras/v4.3.3/paquet.xml"
git -C "$spip_root/plugins/auto/cextras/v4.3.3" status --short 2>/dev/null
rg -n '_DIR_PLUGINS_SUPPL|_SPIP_PATH|dossier_squelettes' "$spip_root/config" "$spip_root/sites" --glob '*.php' -l

# O4 — sites et état d'installation
find -P "$spip_root/sites" -xdev -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort
find -P "$spip_root/sites" -xdev -mindepth 2 -maxdepth 2 -type l -printf '%p -> %l\n'

# O5 — moteur de base par site, filtré : seuls les mots de la liste autorisée sortent
for f in "$spip_root"/sites/*/config/connect.php "$spip_root"/config/connect.php; do
  test -f "$f" || continue
  e="$(grep -oE "'(mysql|sqlite3|sqlite2|pg)'" -- "$f" | tr -d "'" | sort -u | paste -sd, -)"
  printf '%s: %s\n' "${f#"$spip_root"/}" "${e:-unknown}"
done
find -P "$spip_root"/sites/*/config/bases -maxdepth 1 -type f -name '*.sqlite' -printf '%p %s\n' 2>/dev/null

# O6 — R2 : une connexion à la racine existe-t-elle ? (existence seulement, jamais le contenu)
test -f "$spip_root/config/connect.php" && echo 'connect.php présent à la racine'

# O7 — R2 : où vont les écritures récentes ?
for d in "$spip_root/IMG" "$spip_root/tmp" "$spip_root"/sites/*/IMG "$spip_root"/sites/*/tmp; do
  test -d "$d" && printf '%s %s\n' "$(find -P "$d" -xdev -type f -mmin -1440 | wc -l)" "$d"
done

# O8 — chaque domaine répond-il avec son propre contenu ?
for h in asso-exemple.org mairie-exemple.fr admin-ferme.example; do
  printf '%s ' "$h"; curl -sS -o /dev/null -w '%{http_code}\n' "https://$h/"
done

# O9 — capacité (la sauvegarde doit tenir hors de la racine web)
df -hP -- "$spip_root" <parent_sauvegarde>; df -iP -- <parent_sauvegarde>
du -sh -- "$spip_root" "$spip_root"/sites/* 2>/dev/null

# O10 — moteur des tables, MySQL seulement, par base (fichier d'options 0600 préparé par l'opérateur)
"$sql_client" --defaults-file=/etc/mysql/spip-backup.cnf --no-login-paths --batch --skip-column-names \
  -e "SELECT COALESCE(ENGINE,'VIEW'), COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() GROUP BY ENGINE" \
  <nom_base_vérifié>
```

### Planification

1. **Arrêt sur R2.** Si O6 à O8 montrent des écritures dans `IMG/` ou `tmp/` à la racine, une connexion partagée, ou des domaines qui servent le même contenu, ne continuez pas ce plan. Traitez la situation comme un incident (gel des écritures, conservation des preuves), puis demandez-moi la procédure d'incident.
2. **Liste des sites et des bases.** Établissez la liste exacte à partir de O4 à O6. Elle doit inclure la base de la racine si `config/connect.php` y existe.
3. **Mode de cohérence.** Choisissez-le selon O10 : `--single-transaction` si toutes les tables sont en InnoDB ; sinon, gel des écritures (ligne 5).
4. **Puis suivez les lignes du plan d'action dans cet ordre :** 1, 5 (si O10 l'exige), 2, 3, 4, levée de la ligne 5, puis 6, 7 et 8. La ligne 9 ne s'utilise que sur l'un des déclencheurs du §6.

## 4. Commandes : plan d'action

Toutes les commandes sont **proposées — non exécutées**. Les `<…>` sont des valeurs à vérifier ; ce ne sont pas des valeurs par défaut.

| # | Action | Objectif | Préconditions | Portée exacte | Impact | Sauvegarde / preuve | Commande proposée | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Créer le répertoire de sauvegarde | Avoir une destination sûre hors de la racine web | O0 et O9 faits ; parent hors de `$spip_root` ; assertions de chemin du bloc C1 réussies | `$backup_root` et ses sous-dossiers `files/` et `databases/` | Hôte uniquement, aucun effet sur les sites | Sortie de `ls -ld` | Bloc C1 | `stat -c '%a %U' "$backup_root"` renvoie `700` et le compte attendu | `rmdir` des trois dossiers vides (seulement s'ils sont vides) |
| 2 | Archiver les fichiers partagés | Garder l'état exact du code partagé (cœur 5.0, `vendor/`, plugins, mutualisation, configuration globale, `.htaccess` de la racine) | Ligne 1 faite ; liste des entrées confirmée par O1 à O3 et O6 ; aucune mise à jour en cours | Entrées listées de `$spip_root`, `sites/` exclu | Lecture du disque uniquement | L'archive elle-même, et `composer show --locked` (O2) joint au manifeste | Bloc C2 | `tar -tzf` renvoie 0 ; taille non nulle | Supprimer `files/partage.tar.gz` (ce n'est qu'un artefact de sauvegarde) |
| 3 | Archiver les fichiers persistants de chaque site | Garder `config/`, `IMG/`, et `squelettes/` ou `plugins/` s'ils existent, plus `IMG/` à la racine (R2, R7) | Ligne 1 faite ; liste des sites issue de O4 | `sites/<site>/config`, `sites/<site>/IMG`, etc. pour chaque site listé ; `IMG/` à la racine | Lecture du disque uniquement | Une archive par site | Bloc C3 | `tar -tzf` renvoie 0 pour chaque archive | Supprimer l'archive concernée |
| 4 | Sauvegarder la base de chaque site, et celle de la racine si O6 est positif | Avoir un point de restauration de la base pour chaque site | Ligne 1 faite ; moteur et nom de base vérifiés (O5, O10) ; fichier d'options 0600 préparé par l'opérateur, sans mot de passe en argument ; mode de cohérence choisi | Une base par site listé | Lecture SQL, charge passagère | Le dump ou la copie `.sqlite` | Bloc C4a (MySQL) ou C4b (SQLite) | Code retour 0 ; fichier non vide ; pour SQLite, `PRAGMA integrity_check` renvoie `ok` | Supprimer le dump concerné |
| 5 | *(Conditionnelle)* Geler les écritures de la ferme | Rendre la sauvegarde cohérente si des tables ne sont pas en InnoDB (O10) | Créneau annoncé ; mécanisme de maintenance du serveur web déjà relu par l'opérateur ; la configuration passe le test de syntaxe | Tous les vhosts de la ferme | **Tous les sites indisponibles** ou en lecture seule pendant les lignes 2 à 4 | Copie de la configuration du vhost avant modification | Bloc C5 | Chaque domaine renvoie la page de maintenance (503) | Remettre la configuration copiée, `configtest`, rechargement ; les domaines renvoient de nouveau 200 |
| 6 | Calculer les sommes de contrôle et écrire le manifeste | Rendre les sauvegardes vérifiables et identifiables | Lignes 2 à 4 réussies | `$backup_root` | Aucun | `SHA256SUMS` et `MANIFEST.txt` (date UTC, hôte, révisions de O2, sites, bases, moteurs, tailles, versions des outils, **aucun identifiant**) | Bloc C6 | `sha256sum -c` : tout est OK | Régénérer le manifeste |
| 7 | Copier la sauvegarde hors de l'hôte | Survivre à la perte de l'hôte | Ligne 6 faite ; destination chiffrée ou à accès restreint (la sauvegarde contient des secrets, R5) | `$backup_root`, vers `<hôte_sauvegarde>:<chemin>` | Réseau uniquement | Somme de contrôle distante | Bloc C7 | `sha256sum -c` lancé sur la destination : tout est OK | Supprimer la copie distante |
| 8 | Tester la restauration dans un environnement isolé | Prouver que la sauvegarde est **restaurable** : son existence ne suffit pas | Ligne 6 faite ; hôte de test sans accès réseau ni identifiants vers la production ; mêmes PHP (8.4–8.5) et mêmes révisions qu'en O2 ; base de test pré-créée, avec un compte limité à cette seule base | Environnement de test uniquement | Aucun effet sur la production | Journal du test, résultats de `CHECK TABLE` et `integrity_check`, captures HTTP du site restauré | Bloc C8 | Importation sans erreur ; `spip_meta` présent et OK ; chaque site restauré affiche son propre contenu en public et en privé | Détruire l'environnement de test après avoir consigné les résultats, avec l'accord de l'opérateur |
| 9 | *(Conditionnelle, sur déclencheur du §6)* Restaurer l'état sauvegardé en production | Revenir à l'état exact du manifeste | Déclencheur avéré ; ligne 8 réussie avec ce même manifeste ; **état courant conservé d'abord** (lignes 1 à 4 dans un nouveau `$backup_root`) ; ligne 5 active ; `sha256sum -c` OK | Code partagé, persistants de **tous** les sites et **toutes** les bases du même manifeste, jamais de mélange | **Ferme entière** indisponible pendant l'opération | Nouvelle sauvegarde de l'état courant (preuve) | Bloc C9 | Validation du §5 sur chaque site ; versions et `BASE` identiques au manifeste | Restaurer la sauvegarde de l'état courant prise juste avant, avec le même bloc C9 et l'autre manifeste |

### Blocs de commandes (proposés — non exécutés)

```bash
# Variables communes, vérifiées avant chaque bloc
spip_root="$(realpath -e -- <racine_ferme_vérifiée>)" || { echo STOP; exit 1; }
backup_root=<parent_sauvegarde>/spip-ferme/<AAAA-MM-JJTHHMMSSZ>
backup_parent="$(realpath -e -- "$(dirname -- "$backup_root")")" || { echo 'STOP: parent absent'; exit 1; }
test "$spip_root" != / && test "$backup_parent" != / || { echo STOP; exit 1; }
case "$backup_root" in *'/../'*|*/..|*'//'*) echo 'STOP: chemin invalide'; exit 1;; esac
case "$backup_parent" in "$spip_root"|"$spip_root"/*) echo 'STOP: sauvegarde dans la racine web'; exit 1;; esac
```

```bash
# C1 — ligne 1
( umask 077; mkdir -- "$backup_root" && mkdir -- "$backup_root/files" "$backup_root/databases" )
chmod 700 -- "$backup_root"
```

```bash
# C2 — ligne 2 (ne garder dans la liste que les entrées dont O1 à O3 et O6 ont confirmé l'existence)
shared=(ecrire vendor bin plugins mutualisation config <plugins-dist> <squelettes-dist> <prive> <spip.php> <index.php> <.htaccess> <composer.json> <composer.lock>)
out="$backup_root/files/partage.tar.gz"
test ! -e "$out" || { echo 'STOP: archive déjà présente'; exit 1; }
( umask 077; tar --create --gzip --file "$out" -C "$spip_root" -- "${shared[@]}" )
```

```bash
# C3 — ligne 3 (sites issus de O4 ; ajouter squelettes/ ou plugins/ seulement s'ils existent)
for site in asso-exemple.org mairie-exemple.fr; do
  case "$site" in ''|*[!A-Za-z0-9._-]*) echo 'STOP: clé de site invalide'; exit 1;; esac
  out="$backup_root/files/site-$site.tar.gz"
  test ! -e "$out" || { echo "STOP: $out existe"; exit 1; }
  ( umask 077; tar --create --gzip --file "$out" -C "$spip_root/sites" -- "$site/config" "$site/IMG" )
done
( umask 077; tar --create --gzip --file "$backup_root/files/racine-IMG.tar.gz" -C "$spip_root" -- IMG )
```

```bash
# C4a — ligne 4, MySQL/MariaDB, une fois par base (fichier d'options 0600 ; jamais de -p<motdepasse>)
# Détecter sql_client et dump_client, et vérifier --defaults-file et --no-login-paths,
# comme dans la procédure de sauvegarde de référence.
db_name=<nom_base_vérifié>; site_key=<site>
case "$db_name" in ''|*[!A-Za-z0-9_]*) echo STOP; exit 1;; esac
case "$site_key" in ''|*[!A-Za-z0-9._-]*) echo STOP; exit 1;; esac
dump_path="$backup_root/databases/$site_key.sql"
test ! -e "$dump_path" || { echo STOP; exit 1; }
( umask 077; set -C
  "$dump_client" --defaults-file=/etc/mysql/spip-backup.cnf --no-login-paths \
    --single-transaction --quick --routines --events --triggers --no-create-db \
    "$db_name" >"$dump_path" ) || { echo 'STOP: dump échoué — fichier partiel refusé'; exit 1; }
test -s "$dump_path" || { echo 'STOP: dump vide'; exit 1; }
```

```bash
# C4b — ligne 4, SQLite (chemin issu de O5 ; jamais de cp à chaud)
sqlite_db="$(realpath -e -- <chemin_sqlite_vérifié>)"; site_key=<site>
out="$backup_root/databases/$site_key.sqlite"; test ! -e "$out" || { echo STOP; exit 1; }
sqlite3 "$sqlite_db" ".timeout 5000" ".backup '$out'" || { echo STOP; exit 1; }
test "$(sqlite3 "$out" 'PRAGMA integrity_check;')" = ok || { echo 'STOP: intégrité'; exit 1; }
test -z "$(sqlite3 "$out" 'PRAGMA foreign_key_check;')" || { echo 'STOP: clés étrangères'; exit 1; }
```

```bash
# C5 — ligne 5 (mécanisme propre à votre serveur web, à faire relire ; exemple Nginx)
cp -a -- <fichier_vhost> "$backup_root/vhost-avant-gel.conf"
# appliquer <règle de maintenance 503 relue> dans <fichier_vhost>
nginx -t && systemctl reload nginx        # ou : apache2ctl configtest && systemctl reload apache2
# levée : remettre "$backup_root/vhost-avant-gel.conf", puis nginx -t && systemctl reload nginx
```

```bash
# C6 — ligne 6
( cd -- "$backup_root" && find files databases -type f -print0 | sort -z | xargs -0 sha256sum ) >"$backup_root/SHA256SUMS"
for a in "$backup_root"/files/*.tar.gz; do gzip -t -- "$a" && tar -tzf "$a" >/dev/null || echo "ÉCHEC $a"; done
( cd -- "$backup_root" && sha256sum -c SHA256SUMS )
# MANIFEST.txt : date UTC, hostname, révisions O2, liste des sites, bases et moteurs,
# préfixe de table, tailles, versions de tar, gzip, sqlite3 et du client de dump — aucun identifiant
```

```bash
# C7 — ligne 7
rsync -a --protect-args -- "$backup_root/" <hôte_sauvegarde>:<chemin_distant>/
ssh <hôte_sauvegarde> "cd <chemin_distant> && sha256sum -c SHA256SUMS"
```

```bash
# C8 — ligne 8, sur l'hôte de test isolé uniquement
# Suivre les gardes de la procédure de sauvegarde de référence : identité du serveur de test
# différente de la source, fichier d'options dédié, rejet des USE et CREATE/DROP DATABASE dans le dump.
tar -xzf partage.tar.gz -C <racine_test_vide>
tar -xzf site-<site>.tar.gz -C <racine_test_vide>/sites
"$sql_client" --defaults-file=/etc/mysql/spip-restore-drill.cnf --no-login-paths <base_test> < databases/<site>.sql
"$sql_client" --defaults-file=/etc/mysql/spip-restore-drill.cnf --no-login-paths -e 'CHECK TABLE spip_meta' <base_test>
# puis ouvrir chaque site de test : public, connexion privée, contenus et images représentatifs
```

```bash
# C9 — ligne 9 : ne pas lancer sans nouvelle décision ; à faire relire avant usage
# 1. Lignes 1 à 4 vers un NOUVEAU backup_root (conserver l'état courant), puis ligne 5 active.
# 2. Code partagé : extraire dans un répertoire frère, puis basculer chaque entrée par renommage.
mkdir -- "$spip_root.restore" && tar -xzf <manifeste>/files/partage.tar.gz -C "$spip_root.restore"
#    pour chaque entrée E de C2 : mv -- "$spip_root/E" "$spip_root.avant-restore-E" && mv -- "$spip_root.restore/E" "$spip_root/E"
# 3. Persistants : même principe pour sites/<site>/config et sites/<site>/IMG, et pour IMG/ à la racine.
# 4. Bases : importer le dump du MÊME manifeste dans chaque base (le dump contient DROP TABLE IF EXISTS) ;
#    lister les tables absentes du dump et les signaler sans les supprimer.
# 5. Lever la ligne 5 seulement après la validation du §5.
```

## 5. Validation

Ce sont des contrôles en lecture seule, à faire **sur chaque site** (`asso-exemple.org`, `mairie-exemple.fr`, `admin-ferme.example` et tout site révélé par O4), pas seulement sur le site d'administration.

- Avant les lignes 2 à 4, puis après la ligne 6 (et après la levée de la ligne 5 si elle a servi) : le code HTTP de chaque domaine (bloc O8) doit rester identique.
- Chaque site doit afficher **son propre** contenu public. L'espace privé doit accepter la connexion.
- Aucun fichier du code partagé ne doit avoir changé : `tar -dzf "$backup_root/files/partage.tar.gz" -C "$spip_root"` ne doit signaler aucune différence.
- Pour les étapes elles-mêmes, voir les contrôles de réussite des lignes 1, 2, 3, 4, 6 et 7, dans cet ordre.
- La sauvegarde ne compte comme **restaurable** qu'après le contrôle de réussite de la ligne 8.
- Après une éventuelle restauration, appliquer le contrôle de réussite de la ligne 9 sur chaque site : HTTP public et privé, `spip_meta` et `BASE`, plugins actifs, médias, formulaires, tâches planifiées, journaux PHP et SPIP.

Tableau à remplir :

| Site | Moteur | Archive fichiers | Base sauvegardée | Test de restauration | Contenu propre au site | Résultat |
|---|---|---|---|---|---|---|

## 6. Retour arrière

**Unité de restauration.** C'est le jeu complet d'**un seul** manifeste : l'archive du code partagé, les persistants de **chaque** site et **chaque** base, pris ensemble. Ne restaurez jamais la base d'un site à partir d'un autre point de sauvegarde que le code partagé qu'elle attend : sous 5.0, `BASE` vaut `2026_08_03_00`.

**Si une étape de sauvegarde échoue,** la production n'a pas changé. Ces lignes n'écrivent que dans `$backup_root`. Défaites seulement les étapes concernées, avec le retour arrière de la ligne en échec (1, 2, 3, 4, 6 ou 7). Si le gel est actif, levez-le avec le retour arrière de la ligne 5.

**Déclencheurs de la ligne 9 :**

- R2 confirmé puis traité comme incident, après conservation des preuves ;
- une mise à jour faite malgré ce plan, qui casse un site ;
- une corruption constatée.

Dans ces cas, suivez la ligne 9, qui commence par conserver l'état courant. Si cette restauration elle-même échoue, appliquez le retour arrière de la ligne 9.

**Limites :**

- Cette sauvegarde fixe l'état actuel en **5.0.0-beta**. Elle ne permet pas de revenir en 4.4.
- Un retour en 4.4 exige la sauvegarde cohérente prise **avant** le passage en 5.0 : version 4.x, fichiers partagés et base de chaque site. Pouvez-vous me dire si elle existe et où elle se trouve ?

---

**Couverture.** Chaque action qui modifie un état dans cette réponse a sa ligne complète dans le plan d'action :

- création du répertoire : ligne 1 ;
- archives : lignes 2 et 3 ;
- dumps : ligne 4 ;
- gel et levée : ligne 5 ;
- manifeste : ligne 6 ;
- copie hors de l'hôte : ligne 7 ;
- test de restauration et destruction de l'environnement de test : ligne 8 ;
- restauration en production : ligne 9.

La mise à jour des plugins elle-même n'est volontairement pas proposée, puisque la ferme est en 5.0.

## spip-mutualisation-maintenance, encadré après revue (cause confirmée avant restauration, conservation permise) — cas 24, tirage 1

# Diagnostic : tous les sites de la ferme affichent le même contenu

**En bref :** la racine partagée tourne désormais sous **SPIP 5.0.0-beta**, et Mutualisation facile 2.0.1 ne sépare pas les sites sous SPIP 5.0. Cela explique très probablement le symptôme : chaque site lit la même connexion SQL et les mêmes dossiers `IMG/`, `local/` et `tmp/`, ceux de la racine. Ce n'est pas encore prouvé : une erreur de routage des vhosts produirait le même effet. Il faut d'abord le confirmer par les observations en lecture seule ci-dessous. Une fois la cause confirmée : geler les écritures, conserver l'état actuel, puis restaurer la sauvegarde cohérente prise avant la mise à jour (le code partagé et **toutes** les bases). La mutualisation n'a pas de procédure prise en charge sous SPIP 5.0 : je ne propose ni correctif sur place, ni migration de la ferme vers 5.0.

Je n'ai rien exécuté. J'ai seulement lu la copie de la racine partagée. Toutes les commandes ci-dessous sont à relire et à lancer par vous.

---

## Constats

### Faits vérifiés (sur la copie de la racine partagée)

| # | Constat | Preuve |
|---|---|---|
| F1 | La racine partagée tourne sous **SPIP 5.0** : `VERSION = '5.0.0-beta'` (`ID = 50000`, `BASE = 2026_08_03_00`, `PHP_MIN = 8.4.0`). | `ecrire/src/SpipVersion.php`, plus trois autres marqueurs 5.0 : `bin/spip` (console `SpipLeague\Component\Console\Application`) et `vendor/spip/framework/composer.json` sont présents. Il n'y a pas de `plugins-dist/` à la racine de la copie. |
| F2 | Mutualisation facile est chargée par `config/mes_options.php`, avec `require _DIR_RACINE.'mutualisation/mutualiser.php'`. Elle n'est pas dans `plugins/`. | `config/mes_options.php`, bloc `require` juste après `_SITES_ADMIN_MUTUALISATION` |
| F3 | Le paquet chargé est la **version 2.0.1, compatibilité `[4.2.0;4.*]`**. SPIP 5.0 sort de cet intervalle. | `mutualisation/paquet.xml` |
| F4 | Configuration effective : `$site` vient de `HTTP_HOST`, sans `www.`, et le `:` devient `_` quand un port est indiqué. Options : `repertoire => 'sites'`, `cookie_prefix => true`, `table_prefix => false`, `url_img_courtes => true`, `creer_site => true`, `creer_base => true`. Site d'administration : `_SITES_ADMIN_MUTUALISATION = 'admin-ferme.example'`. | `config/mes_options.php`, appel `demarrer_site()` |
| F5 | `demarrer_site()` définit seulement `_DIR_SITE` et `_SPIP_PATH`, puis appelle `spip_initialisation_core(_DIR_SITE…)`. Sous SPIP 5.0, le noyau a déjà défini `_DIR_IMG`, `_DIR_TMP`, `_DIR_VAR` et `_DIR_CONNECT` avant que `config/mes_options.php` ne s'exécute. Résultat : tous les sites utilisent la connexion, `IMG/`, `local/` et `tmp/` de la racine. | `mutualisation/mutualiser.php`, lignes 24-51 et 184-213 ; comportement du noyau 5.0 décrit dans le skill |
| F6 | Deux répertoires de sites existent dans `sites/` : `mairie-exemple.fr` et `asso-exemple.org`. Chacun contient `config/connect.php`, `IMG/`, `local/` et `tmp/`, mais ces trois derniers ne contiennent qu'un `.gitkeep`. | `sites/*/` |
| F7 | Plugin partagé : `plugins/auto/cextras/v4.3.3`, compatibilité `[4.1.0;4.*]`. Il est donc lui aussi incompatible avec 5.0. | `plugins/auto/cextras/v4.3.3/paquet.xml` |

### Anomalies

- **A1 :** les deux `sites/*/config/connect.php` font 52 octets et ne contiennent aucun appel `spip_connect_db`. Je ne peux donc pas déterminer le moteur SQL (MariaDB/MySQL ou SQLite) de ces sites. Cela peut venir de la copie, ou être un vrai fichier de connexion vide ou altéré. À vérifier sur le serveur (observation O5).
- **A2 :** la copie ne contient pas de `config/connect.php`, de `IMG/`, de `tmp/` ni de `local/` à la racine, ni de `spip.php` ou `ecrire/inc_version.php`. La copie est donc partielle. Je ne peux pas dire d'ici où vont réellement la connexion et les écritures.
- **A3 :** il n'existe pas de `sites/admin-ferme.example/`, alors que c'est le site déclaré dans `_SITES_ADMIN_MUTUALISATION`. Soit le site d'administration est ailleurs, soit le tableau de bord n'est pas installé.
- **A4 :** `config/mes_options.php` contient des identifiants SQL d'installation en clair (`_INSTALL_USER_DB` / `_INSTALL_PASS_DB`). Je ne les reproduis pas. Ce fichier doit rester hors des sauvegardes lisibles et des tickets.

### Inconnues qui bloquent

- **I1 :** de quelle « mise à jour du serveur » s'agit-il, et à quelle date ? Mise à jour Composer, `git pull`, paquets système ? Et quelle était la version de SPIP juste avant ?
- **I2 :** comment le cœur est installé (Git, Composer ou archive), et s'il a des modifications locales.
- **I3 :** la pile web (Apache ou Nginx + PHP-FPM) et le routage des vhosts de chaque domaine.
- **I4 :** le moteur SQL et la base de chaque site, et s'il existe une connexion à la racine.
- **I5 :** la liste complète des sites. La copie n'en montre que deux, plus le site d'administration qui manque.
- **I6 :** s'il existe une **sauvegarde cohérente d'avant la mise à jour** (code partagé et toutes les bases du même instant), vérifiée et restaurable.

---

## Risques

- **Portée : toute la ferme.** Le code partagé (`ecrire/`, `vendor/`, `mutualisation/`, `plugins/`) sert tous les sites. Une seule restauration du code concerne donc tous les sites en même temps.
- **Mélange de données en cours.** Tant que la ferme reste ouverte, tout ce qui est créé (articles, documents, comptes, messages de forum, inscriptions) part dans la base et le `IMG/` de la racine, **quel que soit le site** qui l'a produit. Chaque minute aggrave ce mélange. Il y a aussi un risque de confidentialité : chaque domaine montre le contenu, et peut-être les comptes, d'un autre site.
- **Base de la racine possiblement migrée.** Si SPIP 5.0 a mis à jour le schéma de la base que la racine utilise, revenir seulement au code 4.x ne suffit pas. Il faut restaurer cette base au même point de sauvegarde (voir la matrice de retour arrière).
- **Contenu créé depuis la mise à jour.** La restauration le fait disparaître des sites. Il doit être **conservé** (lignes 2 et 3 du plan), puis réattribué à chaque site par une procédure séparée, que je ne propose pas ici.
- **Plugins incompatibles.** `cextras` 4.3.3 (`[4.1.0;4.*]`) et Mutualisation 2.0.1 (`[4.2.0;4.*]`) ne déclarent pas SPIP 5.0. Rester en 5.0 n'est pas une option pour cette ferme.
- **Points d'accès sans authentification.** `?exec=mutualisation&renouvelle_alea`, `dirliste` et `dirsize` répondent sans authentification sur tous les sites. Pendant la période sous 5.0, il faut regarder les journaux d'accès (O8).
- **Blocages.** Les lignes 4 à 8 du plan ne sont **pas prêtes à lancer** tant que I1, I2, I4, I5 et I6 ne sont pas levées. Il faut notamment une sauvegarde d'avant la mise à jour **vérifiée par une restauration de test**. Une sauvegarde qui existe ne suffit pas.

---

## Procédure proposée

### Étape 0 : observations en lecture seule (vous les lancez, vous me renvoyez le résultat)

Les commandes sont dans la section « Commandes en lecture seule » plus bas.

- **O1 :** confirmer sur le serveur la version et ses marqueurs.
- **O2 :** établir l'historique de la mise à jour (I1, I2).
- **O3 :** vérifier que la mutualisation est bien chargée en production.
- **O4 :** vérifier le routage des vhosts : chaque domaine doit arriver sur la ferme avec son propre `HTTP_HOST` (I3).
- **O5 :** identifier la connexion effective et le moteur de chaque site, avec un affichage filtré (I4, A1).
- **O6 :** voir où partent les écritures récentes : racine ou `sites/<domaine>/`.
- **O7 :** lister les sites et repérer les sauvegardes d'avant la mise à jour (I5, I6).
- **O8 :** chercher dans les journaux d'accès les appels `exec=mutualisation` depuis la mise à jour.

**Comment lire les résultats :**

- Si les vhosts pointent vers la bonne racine avec le bon `Host`, et que les fichiers récents sont dans `IMG/` et `tmp/` à la racine, vides dans `sites/*/` : la cause « Mutualisation 2.0.1 sous SPIP 5.0 » est confirmée. Passez à l'étape 1.
- Si un vhost pointe ailleurs, ou réécrit `Host` : c'est un problème de routage. Arrêtez-vous là et envoyez-moi la correspondance des vhosts, sans les secrets. Le plan ci-dessous ne s'applique pas.

### Étape 1 : geler et conserver

Appliquez les lignes 1, 2 et 3 du plan, dans cet ordre, dès que O3 à O6 confirment la cause. Elles exigent seulement de connaître la pile web et le moteur de chaque base.

### Étape 2 : prouver que le retour arrière est possible

Appliquez la ligne 4 : vérifier la sauvegarde d'avant la mise à jour et la restaurer à titre de test, sur une copie isolée. **Si cette sauvegarde n'existe pas, ou si la restauration de test échoue : arrêtez-vous.** Laissez la ferme gelée (ligne 1) et revenez vers moi avec le résultat. Il faudra alors une procédure séparée pour reconstituer chaque site, ce qui sort du cadre de cette réponse.

### Étape 3 : restaurer

Appliquez les lignes 5, 6, 7 et 8, dans cet ordre, en vous référant au manifeste vérifié à la ligne 4.

### Étape 4 : valider puis rouvrir

Faites les contrôles de la section Validation pour **chaque** site, puis appliquez la ligne 9.

### Étape 5 : suite (procédure séparée)

La réattribution du contenu créé pendant la période 5.0, conservé par les lignes 2 et 3, ne fait pas partie de ce plan. Si vous voulez essayer SPIP 5.0 plus tard, ce sera seulement sur une copie isolée, hors production : la mutualisation n'y est pas encore prise en charge.

---

## Commandes

### Commandes en lecture seule (à lancer par vous ; elles ne modifient rien)

```bash
spip_root=/chemin/verifie/de/la/racine/partagee      # à remplacer
spip_root="$(realpath -e -- "$spip_root")" && test "$spip_root" != / || echo 'STOP: racine invalide'

# O1 — version et marqueurs 5.0
rg -n "const string VERSION" "$spip_root/ecrire/src/SpipVersion.php"
for m in bin/spip vendor/spip/framework plugins-dist/spip; do test -e "$spip_root/$m" && echo "present: $m"; done

# O2 — historique de la mise à jour
git -C "$spip_root" log -5 --format='%h %ci %s' 2>/dev/null; git -C "$spip_root" status --short 2>/dev/null | head -50
ls -la --time-style=full-iso "$spip_root"/composer.json "$spip_root"/composer.lock "$spip_root"/vendor/composer/installed.json 2>/dev/null
zgrep -h -E 'Start-Date|Commandline' /var/log/apt/history.log* 2>/dev/null | tail -20

# O3 — mutualisation effectivement chargée
rg -n "require.*mutualiser\.php|'repertoire'" "$spip_root/config/mes_options.php"
rg -n '^[[:space:]]*(version|compatibilite)=' "$spip_root/mutualisation/paquet.xml"

# O4 — routage des vhosts (seules les directives de topologie sont affichées)
command -v nginx >/dev/null && nginx -T 2>/dev/null | sed -E 's/[[:space:]]*#.*$//' \
  | awk '$1=="server_name"||$1=="root"||$1=="fastcgi_pass"{print}'
command -v apache2ctl >/dev/null && apache2ctl -S 2>&1 \
  | rg '(^VirtualHost configuration:|namevhost|alias|\(.*\.conf:[0-9]+\))'
# Empreinte de la page d'accueil de chaque domaine (identique partout = symptôme)
for d in mairie-exemple.fr asso-exemple.org admin-ferme.example; do
  printf '%s ' "$d"; curl -s --max-time 10 "https://$d/" | sha256sum
done

# O5 — connexion effective : présence et moteur seulement (6e argument de spip_connect_db), jamais le contenu
for f in "$spip_root/config/connect.php" "$spip_root"/sites/*/config/connect.php; do
  printf '%s : ' "$f"
  if test -f "$f"; then
    printf '%s octets, moteur=' "$(stat -c %s -- "$f")"
    rg -o --pcre2 "spip_connect_db\((?:\s*'[^']*'\s*,){5}\s*'\K[a-z0-9]+" -- "$f" | head -1 || echo inconnu
  else echo absent; fi
done
find -P "$spip_root" -xdev -maxdepth 4 -path '*/config/bases/*' -type f -printf '%TY-%Tm-%Td %s %p\n' 2>/dev/null

# O6 — où partent les écritures depuis la mise à jour
upgrade_since='AAAA-MM-JJ HH:MM:SS'   # date tirée de O2
for d in "$spip_root/IMG" "$spip_root/tmp" "$spip_root/local" "$spip_root"/sites/*/IMG "$spip_root"/sites/*/tmp "$spip_root"/sites/*/local; do
  test -d "$d" && printf '%6d fichiers récents  %s\n' "$(find -P "$d" -xdev -type f -newermt "$upgrade_since" | wc -l)" "$d"
done

# O7 — liste des sites et sauvegardes d'avant la mise à jour
find -P "$spip_root/sites" -xdev -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort
find -P "$spip_root/sites" -xdev -mindepth 2 -maxdepth 2 -type l -printf '%p -> %l\n'
ls -la --time-style=full-iso /chemin/des/sauvegardes/   # à remplacer ; manifeste + SHA256SUMS attendus

# O8 — accès aux points exec=mutualisation pendant la période 5.0
zgrep -h 'exec=mutualisation' /var/log/<nginx|apache2>/*access*.log* 2>/dev/null | awk '{print $1, $4, $7}' | tail -50
```

### Plan d'action (toutes les commandes : **proposées, non exécutées**)

Remplacez les variables (`$spip_root`, `$backup_root`, `$incident_root`, `$site_key`, `$db_name`…) par des valeurs vérifiées. Chaque commande s'arrête d'elle-même si un chemin est vide, vaut `/`, ou sort de son parent.

| # | Action | Objectif | Préconditions | Portée exacte | Impact | Sauvegarde / preuve | Commande proposée — **non exécutée** | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Geler les écritures : page de maintenance sur tous les vhosts de la ferme | Empêcher que le mélange de données s'aggrave | Cause confirmée par O4 à O6 ; pile web connue (O4) | Tous les vhosts qui servent `$spip_root`, et seulement eux | Tous les sites indisponibles au public | Copie de la configuration des vhosts avant modification (`cp -a <fichier_vhost> <fichier_vhost>.avant-gel`) | Selon la pile vue en O4. Nginx : ajouter `return 503;` (ou une page de maintenance statique) dans le `server` de chaque domaine, puis `nginx -t && systemctl reload nginx`. Apache : règle `RewriteRule ^ - [R=503,L]` dans chaque vhost, puis `apache2ctl configtest && systemctl reload apache2` | `curl -sI https://<domaine>/` renvoie `503` pour chaque domaine | Remettre `<fichier_vhost>.avant-gel`, tester la configuration, recharger |
| 2 | Conserver l'état fichiers de l'incident | Garder la preuve et le contenu créé sous 5.0 (racine `IMG/`, `tmp/`, `local/`, `config/`, code 5.0, `sites/`) | Ligne 1 faite ; `$incident_root` hors de la racine web, sur un disque avec assez de place (`df -hP`) | Liste explicite : `ecrire/ vendor/ bin/ config/ mutualisation/ plugins/ IMG/ tmp/ local/ sites/` (plus `composer.*`, `spip.php`, `index.php` s'ils existent) sous `$spip_root` | Lecture seule pour la source ; écriture dans `$incident_root` seulement | C'est cette ligne qui produit la preuve | `(umask 077; mkdir -p -- "$incident_root" && cd -- "$spip_root" && tar --numeric-owner -cpf "$incident_root/ferme-etat-5.0.tar" -- ecrire vendor bin config mutualisation plugins IMG tmp local sites composer.json composer.lock spip.php index.php 2>/dev/null; sha256sum -- "$incident_root/ferme-etat-5.0.tar" >>"$incident_root/SHA256SUMS")` | `tar -tf` sans erreur ; `sha256sum -c "$incident_root/SHA256SUMS"` OK ; taille cohérente avec `du -sh` des sources | Aucune modification de la source. L'archive se supprime seulement après la clôture de l'incident, avec votre accord |
| 3 | Sauvegarder **toutes** les bases dans leur état actuel | Garder le contenu écrit sous 5.0 et l'état de chaque base avant la restauration | Ligne 1 faite ; moteur et nom de chaque base connus (O5) ; fichier d'options SQL `0600` déjà préparé par vous | La base de la racine (si O5 en montre une) et la base de chaque site | Lecture seule sur les bases | C'est cette ligne qui produit la preuve | MariaDB/MySQL, une fois par base : `(umask 077; set -C; "$dump_client" --defaults-file=/etc/mysql/spip-backup.cnf --no-login-paths --single-transaction --quick --routines --events --triggers --no-create-db "$db_name" >"$incident_root/databases/$site_key.sql")`, puis contrôle du code de retour. SQLite : `sqlite3 "$sqlite_db" ".timeout 5000" ".backup '$incident_root/databases/$site_key.sqlite'"` (recettes complètes avec garde-fous : `backup-rollback.md` §3-4) | Code de retour 0 ; fichier non vide ; SQLite : `PRAGMA integrity_check` = `ok` ; empreinte ajoutée à `SHA256SUMS` | Aucune modification des bases ; les vidages se suppriment seulement avec votre accord |
| 4 | Vérifier la sauvegarde d'avant la mise à jour par une restauration de test isolée | Prouver que le retour arrière est possible avant de toucher à la production | Sauvegarde trouvée en O7 ; un environnement isolé, sans route ni identifiants vers la production | Copie isolée seulement (serveur ou instance de test, bases de test créées à part) | Aucun impact sur la production | Manifeste d'avant la mise à jour, `SHA256SUMS` | `sha256sum -c "$backup_root/SHA256SUMS"` ; puis import de chaque vidage dans une base de test pré-créée, avec un compte limité à cette base (`backup-rollback.md` §3, « Restore verification ») ; SQLite : copie sous un nouveau nom et `PRAGMA integrity_check` ; puis le code 4.x et un site au moins lancés sur la copie | Empreintes OK ; `spip_meta` présente et `CHECK TABLE` OK ; le site de test s'affiche en 4.x avec **son** propre contenu | Supprimer l'environnement de test après avoir noté les résultats et avec votre accord |
| 5 | Restaurer le code partagé à la version exacte d'avant la mise à jour | Faire tourner la ferme sous SPIP 4.x, où Mutualisation 2.0.1 isole les sites | Lignes 1 à 4 réussies ; installation (I2) et version d'origine (I1) connues ; aucun mélange Git, Composer et archive | `$spip_root` : `ecrire/`, `vendor/`, `bin/`, `prive/`, `squelettes-dist/`, `plugins-dist/`, `composer.*`, fichiers racine du cœur ; `mutualisation/` et `plugins/` s'ils ont changé | Toute la ferme | Ligne 2 (code 5.0 archivé) | Selon l'installation, **une seule méthode** : Git, `git -C "$spip_root" checkout <tag_ou_commit_4.x_verifie>` ; Composer, remettre `composer.json` et `composer.lock` d'avant la mise à jour depuis le manifeste, puis `composer install --no-dev` ; archive, extraire l'archive d'avant la mise à jour aux chemins du manifeste (`tar -xpf "$backup_root/code-partage.tar" -C "$spip_root" -- <liste explicite>`) | `rg -n '\$spip_version_affichee' "$spip_root/ecrire/inc_version.php"` montre la version 4.x attendue ; `bin/spip` et `vendor/spip/framework` absents si la 4.x ne les avait pas | Réappliquer l'archive de la ligne 2 (code 5.0), la ferme restant gelée |
| 6 | Restaurer chaque base au point de sauvegarde d'avant la mise à jour | Remettre chaque base dans le schéma 4.x attendu par le code de la ligne 5 | Ligne 5 faite ; ligne 4 validée pour **ce** vidage ; ligne 3 faite pour cette base | Chaque base touchée : celle de la racine si elle existait avant, et celle de chaque site dont le schéma ou le contenu a changé (les sites seulement si O6 ou la ligne 3 le montrent ; au moindre doute, toutes) | Le contenu créé sous 5.0 disparaît des sites (il est gardé par la ligne 3) | Ligne 3 (état actuel), ligne 4 (vidage vérifié) | MariaDB/MySQL : import dans la base cible, vidée selon une méthode validée en test, `"$sql_client" --defaults-file=<options_0600> --no-login-paths "$db_name" <"$backup_root/databases/$site_key.sql"` ; SQLite : remettre le fichier `.sqlite` d'avant la mise à jour à son chemin, ferme gelée, l'actuel ayant été conservé à la ligne 3 | `spip_meta` : `version_installee` de la 4.x ; nombre de lignes cohérent avec le manifeste | Réimporter le vidage de la ligne 3 pour cette base |
| 7 | Mettre en quarantaine les fichiers de `IMG/` racine créés sous 5.0 | Rendre au `IMG/` racine son état d'avant la mise à jour, sans rien détruire | Lignes 2 et 5 faites ; liste O6 des fichiers récents relue | Seulement les fichiers de `$spip_root/IMG` plus récents que `upgrade_since` | Ces documents ne sont plus servis (ils sont gardés) | Ligne 2 | `qdir="$incident_root/quarantaine-IMG"; (umask 077; mkdir -p -- "$qdir"); cd -- "$spip_root/IMG" && find -P . -xdev -type f -newermt "$upgrade_since" -print0 \| rsync -a --from0 --files-from=- --remove-source-files ./ "$qdir/"` | `find "$spip_root/IMG" -type f -newermt "$upgrade_since" \| wc -l` = 0 ; les fichiers sont dans `$qdir` | `rsync -a "$qdir/" "$spip_root/IMG/"` |
| 8 | Écarter les caches générés sous 5.0 (`tmp/` et `local/` de la racine et des sites) | Empêcher que le code 4.x relise des caches ou squelettes compilés par la 5.0 | Ligne 2 faite (ces caches y sont archivés) ; ligne 5 faite | Contenu de `$spip_root/tmp/`, `$spip_root/local/` et `sites/*/tmp/` (cache seulement), modifié après `upgrade_since` | Caches recalculés au premier affichage (lenteur passagère) | Ligne 2 | Même déplacement que la ligne 7, vers `$incident_root/quarantaine-caches/<chemin>`, en excluant `tmp/dump/`, `tmp/upload/` et les journaux `tmp/log/` (journaux à garder sur place). Ni `rm -rf`, ni `find -delete` | Pas de fichier récent restant dans ces dossiers ; le site s'affiche après recalcul | Remettre les fichiers depuis la quarantaine avec `rsync` |
| 9 | Lever la page de maintenance | Rouvrir les sites | Validation complète faite sur **chaque** site | Les vhosts modifiés par la ligne 1 | Les sites sont de nouveau publics | `<fichier_vhost>.avant-gel` (ligne 1) | Remettre la configuration d'avant le gel : `cp -a <fichier_vhost>.avant-gel <fichier_vhost>`, puis `nginx -t && systemctl reload nginx` ou `apache2ctl configtest && systemctl reload apache2` | `curl -sI` renvoie 200 et la Validation est verte pour chaque domaine | Réappliquer la ligne 1 |

---

## Validation

À faire pour **chaque** site : `mairie-exemple.fr`, `asso-exemple.org`, le site d'administration `admin-ferme.example`, et tout autre site trouvé en O7. Pas seulement le site d'administration.

1. **Pendant le gel :** les contrôles de réussite des lignes 1 à 3 sont verts avant de passer à la ligne 4.
2. **Après les lignes 5 à 8, avant la ligne 9.** Testez en contournant la page de maintenance, par exemple depuis votre IP autorisée ou avec `curl --resolve`.
   - La version est de nouveau en 4.x (contrôle de la ligne 5), et aucun marqueur 5.0 ne reste (relancer O1).
   - Les empreintes de la page d'accueil (O4) sont **différentes d'un domaine à l'autre**, et chaque `<title>` correspond au site.
   - Un document de chaque site est servi depuis `sites/<domaine>/IMG/` (ou `/IMG/` réécrit si `url_img_courtes` est actif) et lui appartient.
   - La connexion à l'espace privé de chaque site affiche **ses** auteurs et **ses** rubriques.
   - Écriture de test sur un site : une fois le dépôt fait, relancer O6. Le nouveau fichier doit apparaître dans `sites/<domaine>/IMG/` ou `sites/<domaine>/tmp/`, et **aucun** nouveau fichier dans `IMG/` ou `tmp/` de la racine.
   - `spip_meta` de chaque base montre la version 4.x (contrôle de la ligne 6), et les plugins actifs sont ceux d'avant.
   - Les journaux PHP et SPIP (`sites/<domaine>/tmp/log/`) ne montrent pas d'erreur fatale.
3. **Après la ligne 9 :** le contrôle de réussite de la ligne 9 est vert sur chaque domaine.

---

## Retour arrière

**Déclencheurs :**

- un contrôle de réussite échoue sur l'une des lignes 5 à 8 ;
- un site montre encore le contenu d'un autre ;
- une erreur fatale apparaît ;
- `spip_meta` n'est pas cohérente avec la version du code.

**Ce qu'on restaure, toujours ensemble :** la version du code partagé, plus la base et les fichiers persistants de **chaque** site touché, pris au **même** point de sauvegarde. On ne restaure jamais une base depuis un autre point que celui du code qu'elle attend. L'état est incertain, et l'incident se superpose à la mise à jour : la matrice de retour arrière donne donc « version partagée connue bonne + toutes les bases touchées + tous les fichiers persistants touchés ». Les preuves sont gardées à part (lignes 2 et 3).

**Ordre :** la ferme reste gelée (ligne 1) pendant tout le retour arrière. Défaire dans l'ordre inverse : ligne 8, ligne 7, ligne 6 (pour chaque base), puis ligne 5, en appliquant la colonne « Retour arrière » de chaque ligne. Le contenu des lignes 2 et 3 n'est jamais supprimé sans votre accord explicite.

---

*Vérification de couverture : chaque action qui modifie un état, dans toute cette réponse, a une ligne complète dans le plan. C'est le cas du gel, des sauvegardes de l'incident, de la restauration de test, de la restauration du code et des bases, des deux mises en quarantaine et de la réouverture. La réattribution du contenu créé sous 5.0 et un éventuel essai 5.0 isolé ne sont **pas** proposés ici : ils demanderont une procédure séparée.*

## spip-mutualisation-maintenance, encadré après revue (cause confirmée avant restauration, conservation permise) — cas 24, tirage 2

# Ferme SPIP : tous les sites affichent le même contenu depuis la mise à jour

Résumé : la mise à jour a fait passer la racine partagée de la ferme à **SPIP 5.0.0-beta**. Or Mutualisation facile 2.0.1 ne fonctionne que sur 4.x (`[4.2.0;4.*]`). Sous 5.0, elle n'isole plus les sites : le noyau définit `_DIR_IMG`, `_DIR_TMP`, `_DIR_VAR` et `_DIR_CONNECT` avant de lire `config/mes_options.php`. Du coup, `demarrer_site()` ne règle plus que `_DIR_SITE` et `_SPIP_PATH`, et tous les sites passent par la connexion, `IMG/`, `local/` et `tmp/` de la racine. Le symptôme correspond à cette cause, mais il ne la prouve pas : un mauvais routage des vhosts donnerait le même résultat. Il faut donc d'abord confirmer en lecture seule, puis geler les écritures, conserver l'état de l'incident, et revenir à la sauvegarde cohérente faite avant la mise à jour. La mutualisation est expérimentale en 5.0 et aucune procédure de ferme n'existe : je ne propose ni migration ni « réparation » en 5.0.

Je n'ai lancé aucune commande sur le serveur. J'ai lu uniquement la copie de la racine partagée que vous m'avez fournie.

## Constats

Faits vérifiés dans la copie :

| Fait | Preuve |
|---|---|
| La racine partagée est en SPIP **5.0.0-beta** | `ecrire/src/SpipVersion.php` : `VERSION = '5.0.0-beta'`, `BASE = 2026_08_03_00`, `PHP_MIN = '8.4.0'`. Autres indices 5.0 : `bin/spip` (console `SpipLeague\…\Kernel`) et `vendor/spip/framework/composer.json` existent. |
| La mutualisation est bien chargée depuis la racine | `config/mes_options.php` contient `require _DIR_RACINE.'mutualisation/mutualiser.php'`, puis appelle `demarrer_site($site, …)`. |
| Mutualisation facile **2.0.1**, déclarée `[4.2.0;4.*]` | `mutualisation/paquet.xml`. Elle n'est donc pas compatible avec 5.0. |
| Elle ne gère plus les répertoires sous 5.0 | `mutualisation/mutualiser.php` définit `_DIR_SITE` et `_SPIP_PATH`, puis appelle `spip_initialisation_core(_DIR_SITE …)`. En 5.0, les constantes de répertoires sont déjà définies à ce moment-là : cet appel ne change plus rien. |
| Calcul de `$site` : `HTTP_HOST` sans `www.`, redirection vers le domaine sans www, `:` remplacé par `_` (le port 80 est retiré) | `config/mes_options.php` |
| `repertoire` = `sites`, `cookie_prefix` = true, `table_prefix` = false (une base par site, préfixe `spip`), `url_img_courtes` = true | Options passées à `demarrer_site()` |
| Base prévue : `_INSTALL_SERVER_DB` = `mysql`, nom de base `mu_` + `prefixe_mutualisation($site)` | `config/mes_options.php` |
| `_SITES_ADMIN_MUTUALISATION` = `admin-ferme.example` | `config/mes_options.php` |
| Sites présents dans la copie : `sites/mairie-exemple.fr`, `sites/asso-exemple.org`. Chacun a `config/connect.php`, `IMG/`, `local/` et `tmp/`, mais ces trois dossiers sont vides dans la copie. | Arborescence |
| Plugin partagé : `plugins/auto/cextras/v4.3.3`, déclaré `[4.1.0;4.*]`. Lui non plus n'est pas compatible 5.0. | `paquet.xml` |

Inconnues et anomalies à lever sur le serveur :

- **La copie est incomplète.** Elle ne contient ni `ecrire/inc_version.php`, ni `spip.php`, ni `plugins-dist/`, ni `composer.json`/`composer.lock` à la racine, ni `vendor/autoload_runtime.php`. Elle ne contient pas non plus de **`config/connect.php`, `IMG/`, `local/` ou `tmp/` à la racine**. Or, selon l'hypothèse 5.0, c'est justement là que tous les sites lisent et écrivent. Il faut vérifier sur le serveur (étape O4) : si la racine n'a vraiment pas de `connect.php`, l'hypothèse 5.0 seule n'explique pas qu'un contenu s'affiche, et le routage des vhosts devient la piste principale.
- **Moteur de base des sites : inconnu.** Les deux `sites/*/config/connect.php` font 52 octets et ne contiennent pas d'appel `spip_connect_db` (je l'ai compté sans afficher le fichier). Ce sont peut-être des fichiers expurgés pour la copie. À vérifier avec le filtre de l'étape O4.
- **Le site d'administration `admin-ferme.example` n'a pas de dossier** dans `sites/`.
- **Provenance du cœur et de la mise à jour inconnues** : Git, Composer ou archive ? Quand exactement, et par quel outil ? A-t-elle aussi changé la version de PHP (5.0 exige PHP ≥ 8.4) ?
- **Sauvegarde d'avant la mise à jour : existence, date et intégrité inconnues.** C'est un **bloquant** pour la restauration (lignes 5 à 8).
- `config/mes_options.php` contient en clair un identifiant et un mot de passe SQL (`_INSTALL_USER_DB` / `_INSTALL_PASS_DB`). Je ne les recopie pas. Traitez ce fichier comme un secret dans toutes les copies et sauvegardes.

## Risques

- **Impact : toute la ferme.** La racine partagée sert tous les sites, donc chaque visite, chaque contribution et chaque tâche cron mélange en ce moment les données de tous les sites dans un seul ensemble (connexion, `IMG/` et `tmp/` de la racine). Plus vous attendez, plus il y aura de contenu à reprendre à la main.
- **Fuite entre sites** : des documents, des comptes et des sessions d'un site peuvent être visibles sur les autres. Même avec `cookie_prefix`, la base des auteurs est désormais commune. À traiter comme un incident de confidentialité.
- **Migration de schéma possible** : en 5.0, une visite de l'espace privé peut avoir migré la base visée par la connexion de la racine (`version_installee` passe à `2026080300`). Si vous remettez le code 4.x sur une base déjà migrée, le site casse. Il faut donc toujours restaurer **code et bases au même point de sauvegarde**.
- **Code incompatible** : Mutualisation 2.0.1 et cextras 4.3.3 n'acceptent pas 5.0. Aucune correction « sur place » en 5.0 n'est envisageable.
- **Création de site non voulue** : `creer_site` = true avec le code d'activation par défaut (`ecureuil`). N'importe quel `HTTP_HOST` qui arrive sur la ferme peut lancer la création d'un site. C'est à garder en tête pendant le gel ; je ne propose pas de modification ici.
- **`?exec=mutualisation`** : `renouvelle_alea`, `dirliste` et `dirsize` répondent sans authentification sur tous les sites. Regardez les journaux d'accès (O7).
- **Bloquants** avant toute restauration : moteur et nom de base de chaque site, provenance et version exacte d'avant la mise à jour, sauvegarde vérifiée et restaurable, compatibilité entre le PHP actuel et la version 4.x restaurée.

## Procédure proposée

Observations en lecture seule, à lancer par vous. Remplacez les valeurs entre `<…>` par des valeurs vérifiées. Ne me collez que les sorties filtrées.

**O1. Confirmer la version et le chargement de la mutualisation**

```bash
farm_root="$(realpath -e -- <racine_partagee_verifiee>)"; test -n "$farm_root" && test "$farm_root" != /
rg -n "const string VERSION" "$farm_root/ecrire/src/SpipVersion.php"
ls -d "$farm_root/bin/spip" "$farm_root/vendor/spip/framework" "$farm_root/plugins-dist/spip" 2>&1
rg -n 'require.*mutualiser\.php' "$farm_root/config/mes_options.php"
rg -n '^[[:space:]]*(version|compatibilite)=' "$farm_root/mutualisation/paquet.xml"
```

**O2. Dater la mise à jour et établir la provenance**

```bash
ls -l --time-style=full-iso -- "$farm_root/ecrire/src/SpipVersion.php" "$farm_root/composer.json" "$farm_root/composer.lock" 2>&1
git -C "$farm_root" log -1 --format='%H %ci' 2>/dev/null; git -C "$farm_root" status --short 2>/dev/null | head -n 20
zgrep -h ' upgrade ' /var/log/dpkg.log* 2>/dev/null | rg 'php|nginx|apache|mariadb|mysql' | tail -n 30
php -v | head -n 1
```

Consignez la date et l'heure de la bascule (`since`) et la version 4.x exacte qui tournait avant.

**O3. Vérifier que chaque domaine arrive sur la ferme avec son propre `HTTP_HOST`** (sortie filtrée, sans secret)

```bash
command -v nginx >/dev/null && nginx -T 2>/dev/null | sed -E 's/[[:space:]]*#.*$//' \
  | awk '$1=="server_name"||$1=="root"||$1=="fastcgi_pass"||($1=="fastcgi_param"&&$2 ~ /HTTP_HOST|SERVER_NAME/){print}'
command -v apache2ctl >/dev/null && apache2ctl -S 2>&1 | rg '(namevhost|alias|\(.*\.conf:[0-9]+\))'
```

Si tous les domaines tombent sur un même vhost par défaut, ou si `HTTP_HOST` est forcé à une valeur fixe, c'est le routage qui est en cause. Arrêtez-vous alors et revenez vers moi avant les lignes 1 à 10.

**O4. Voir où vont réellement la connexion et les écritures** (le filtre ne renvoie que le moteur)

```bash
for f in "$farm_root/config/connect.php" "$farm_root"/sites/*/config/connect.php; do
  if test -f "$f"; then printf '%s ' "$f"; rg -o -m1 "'(mysql|sqlite3|pg)'" "$f" || echo unknown; else echo "$f ABSENT"; fi
done
since='<AAAA-MM-JJ HH:MM de O2>'
find -P "$farm_root/IMG" "$farm_root/local" "$farm_root/tmp" -xdev -type f -newermt "$since" \
  -printf '%TY-%Tm-%Td %TH:%TM %p\n' 2>/dev/null | sort | tail -n 50
find -P "$farm_root/sites" -xdev -mindepth 3 -type f -newermt "$since" \( -path '*/IMG/*' -o -path '*/tmp/*' \) \
  -printf '%TY-%Tm-%Td %TH:%TM %p\n' | sort | tail -n 50
```

La cause 5.0 est confirmée si : la racine a un `config/connect.php`, des fichiers récents apparaissent dans `IMG/` et `tmp/` de la racine, et presque rien n'apparaît dans `sites/<domaine>/` depuis `since`. Ouvrez `config/connect.php` de la racine **vous-même, en local**, pour noter le nom de la base visée. Ne me le collez pas avec les identifiants.

**O5. Savoir quelles bases ont été écrites ou migrées par 5.0**, pour chaque base `mu_*` et pour celle de la connexion racine (fichier d'options client en mode 0600, comme dans la référence sauvegarde) :

```bash
"$sql_client" --defaults-file="$client_opts" --no-login-paths --batch --skip-column-names \
  -e "SELECT valeur FROM spip_meta WHERE nom='version_installee'" "<base_verifiee>"
```

**O6. Trouver la sauvegarde d'avant la mise à jour** : son manifeste, son horodatage UTC (il doit précéder `since`), puis `sha256sum -c "<backup_pre_maj>/SHA256SUMS"`. Elle doit contenir le code partagé, `config/mes_options.php`, `mutualisation/`, chaque `sites/<domaine>/` (`config`, `IMG`, `squelettes`, `plugins`) et **chaque base**.

**O7. Chercher dans les journaux d'accès** (format vérifié) les appels `exec=mutualisation` depuis `since`, ainsi que les créations de site (`mutualiser_creer`).

**O8. Recenser les tâches planifiées qui sollicitent la ferme** : `crontab -l -u <compte_php>`, `/etc/cron.d/`, timers systemd, en filtrant sur `spip|curl|wget`.

Ensuite, si O3 et O4 confirment la cause 5.0 :

1. Suivez les lignes 1 et 2 du plan d'action (gel des écritures).
2. Suivez les lignes 3 et 4 (conservation de l'état de l'incident). Ne videz **aucun** cache avant.
3. Suivez la ligne 5 (contrôle et test de restauration de la sauvegarde). Si elle échoue, arrêtez-vous : la ferme reste en maintenance et on revoit le plan.
4. Suivez les lignes 6 à 8 dans l'ordre (restauration du code et des bases au même point, puis bascule).
5. Faites la validation ci-dessous, puis suivez la ligne 9 (reprise des contenus saisis pendant la période 5.0), la ligne 10 (réouverture) et, si la provenance est Composer, la ligne 11.

## Commandes

Plan d'action. Toutes les commandes sont **proposées et non exécutées**.

| # | Action | Objectif | Préconditions | Portée exacte | Impact | Sauvegarde / preuve | Commande proposée (non exécutée) | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Maintenance sur tous les vhosts de la ferme | Arrêter le mélange des écritures | O3 et O4 confirment la cause ; liste des vhosts issue de O3 | Tous les `server_name` de la ferme, accès réservé à l'IP de l'opérateur | Ferme entière indisponible (503) | Copie des fichiers vhost modifiés, `nginx -T` filtré avant/après | Ajouter dans chaque `server{}` un `include <snippet_maintenance_verifie>;` (`if ($remote_addr != <ip_operateur>) { return 503; }`), puis `nginx -t && systemctl reload nginx` (pour Apache : équivalent `RewriteRule` + `apache2ctl configtest && systemctl reload apache2`) | `curl -sI https://<chaque_domaine>/` renvoie 503 hors IP opérateur | Retirer l'`include`, puis `nginx -t && systemctl reload nginx` |
| 2 | Suspendre les tâches planifiées trouvées en O8 | Plus aucune écriture par cron pendant le gel | Liste O8 | Seulement les entrées qui touchent la ferme | Tâches différées | Copie de la crontab ou du fichier avant modification | Commenter les lignes concernées (`crontab -e -u <compte_php>`) ou `systemctl stop <timer_verifie>` | `crontab -l` ou `systemctl list-timers` ne les montre plus actives | Remettre la crontab sauvegardée, ou `systemctl start <timer>` |
| 3 | Archiver l'état de l'incident (fichiers) | Garder des preuves et pouvoir reprendre les contenus de la période 5.0 | Ligne 1 faite ; dossier `incident` hors racine web, vérifié avec les assertions de la référence sauvegarde §2 | Toute la racine `"$farm_root"` (code 5.0, `config/`, `IMG/`, `local/`, `tmp/` racine, `sites/`) | Lecture du disque seulement | Ce qui est produit ici + `SHA256SUMS` | `( umask 077; mkdir -p -- "$incident/files" "$incident/databases" )` puis `tar -C "$(dirname -- "$farm_root")" --xattrs --acls -cpf "$incident/files/farm-root.tar" -- "$(basename -- "$farm_root")"` puis `sha256sum -- "$incident/files/farm-root.tar" >>"$incident/SHA256SUMS"` | `tar -tf … >/dev/null` et `sha256sum -c` OK ; une copie hors serveur existe | Rien à défaire (création seulement). L'archive reste un secret, à conserver. |
| 4 | Exporter **chaque** base dans son état d'incident | Garder des preuves ; pouvoir reprendre les contenus après coup | Moteur et nom de chaque base vérifiés (O4, O5) ; fichier d'options en 0600 | Base de la connexion racine + chaque `mu_*` de `sites/` | Lecture seule pour la base (`--single-transaction` si tout est en InnoDB, sinon gel déjà en place) | `$incident/databases/<site_key>.sql` + somme de contrôle | Forme de dump de la référence sauvegarde §3 : `"$dump_client" --defaults-file="$client_opts" --no-login-paths --single-transaction --quick --routines --events --triggers --no-create-db "<base>" >"$incident/databases/<site_key>.sql"`, avec les garde-fous (`set -C`, contrôle du code retour, fichier non vide). En SQLite : `.backup` (§4). | Code retour 0, fichier non vide, `sha256sum -c` OK | Rien à défaire (création seulement) |
| 5 | Vérifier la sauvegarde d'avant la mise à jour et la tester sur un environnement isolé | Avoir un point de retour prouvé | O6 : manifeste horodaté avant `since` ; serveur de test isolé, sans accès à la production | Instance de test isolée uniquement | Aucun sur la production | Journal du test, sommes de contrôle | `sha256sum -c "<backup_pre_maj>/SHA256SUMS"`, puis test de restauration selon la référence sauvegarde §3 (« Restore verification ») ou §4, pour **chaque** base, plus le code 4.x sur une copie isolée | `CHECK TABLE` (ou `PRAGMA integrity_check`) OK ; `version_installee` correspond à la 4.x ; un site test s'affiche avec ses propres contenus | Supprimer l'environnement de test après accord, une fois les résultats consignés |
| 6 | Restaurer le code partagé 4.x dans un **nouveau** dossier | Préparer l'ancienne version sans écraser l'état 5.0 | Ligne 5 réussie ; version et provenance exactes (O2) ; PHP de production compatible avec cette version 4.x | `<parent_verifie>/<nom>-4x-restore` (nouveau dossier, hors `"$farm_root"`) | Aucun tant que la ligne 8 n'est pas faite | Manifeste de la sauvegarde | `( umask 027; mkdir -- "$farm_root_4x" ) && tar -C "$farm_root_4x" -xpf "<backup_pre_maj>/files/<archive_code_et_sites>.tar"`, puis créer des `tmp/` et `local/` vides pour la racine et pour chaque site, avec le propriétaire relevé dans l'inventaire | `rg -n spip_version_affichee "$farm_root_4x/ecrire/inc_version.php"` donne la version 4.x attendue ; `paquet.xml` mutualisation = 2.0.1 ; les `sites/*/config/connect.php` existent | Supprimer ce nouveau dossier, cible résolue et vérifiée, jamais avec un `rm -rf` sur une variable non résolue |
| 7 | Restaurer **chaque** base depuis la même sauvegarde | Remettre des bases cohérentes avec le code 4.x | Lignes 4 et 5 faites ; liste des bases (O5) ; maintenance active | Chaque `mu_*`, plus la base de la connexion racine si elle figure dans la sauvegarde | Réécrit les tables présentes dans le dump | Dumps d'incident (ligne 4) | `"$sql_client" --defaults-file="$client_opts" --no-login-paths "<base>" <"<backup_pre_maj>/databases/<site_key>.sql"`, une base après l'autre, après le contrôle anti-`USE`/`CREATE DATABASE` de §3 | `version_installee` = valeur 4.x de la sauvegarde ; `CHECK TABLE <prefixe>_meta` OK ; les tables en plus de celles du dump sont listées (ne pas les supprimer) | Réimporter le dump d'incident de la ligne 4 dans la même base |
| 8 | Basculer la racine des vhosts vers le dossier 4.x | Remettre la ferme en 4.x | Lignes 6 et 7 réussies ; mode de pointage relevé en O3 (lien symbolique ou `root`) | Vhosts de la ferme | Ferme servie en 4.x (toujours en maintenance) | Ancien `root` ou ancienne cible du lien, notés | Si c'est un lien : `ln -sfn -- "$farm_root_4x" "<lien_docroot>.new" && mv -Tf -- "<lien_docroot>.new" "<lien_docroot>"`. Sinon : modifier `root` (ou `DocumentRoot`), puis test de config et reload. Recharger PHP-FPM si son cache d'opcodes ne se vide pas. | `curl` depuis l'IP opérateur : chaque domaine affiche son propre contenu (Validation) | Repointer vers l'ancienne cible, puis reload. La maintenance reste active. |
| 9 | Reprendre les contenus saisis pendant la période 5.0 | Ne pas perdre ce qui a été saisi depuis `since` | Validation OK ; liste des objets et documents créés depuis `since`, tirée des lignes 3 et 4 | Site par site, objets identifiés seulement | Modifie le contenu des sites concernés | Lignes 3 et 4 | Ressaisie manuelle dans l'espace privé du bon site, ou import réfléchi au cas par cas. Rien en masse. | Chaque élément repris figure dans un registre (site, objet, date) | Supprimer l'élément repris dans l'espace privé ; dans le pire cas, restaurer la base du site avec la ligne 7 |
| 10 | Lever la maintenance et réactiver les tâches | Rouvrir la ferme | Validation OK sur **tous** les sites | Vhosts et tâches des lignes 1 et 2 | Ferme ouverte au public | Fichiers vhost et crontab sauvegardés aux lignes 1 et 2 | Retour arrière des lignes 1 puis 2 : retirer l'`include` de maintenance, `nginx -t && systemctl reload nginx`, remettre la crontab ou `systemctl start <timer>` | Les domaines répondent en 200 ; nouvelles écritures dans `sites/<domaine>/` (Validation) | Refaire la ligne 1 |
| 11 | (Si la provenance est Composer) Empêcher qu'une mise à jour remonte en 5.0 | Éviter que l'incident se reproduise | O2 indique Composer ; lignes 8 et 10 faites | `"$farm_root_4x/composer.json"` uniquement | Aucun en exécution | Copie de `composer.json` et `composer.lock` | Fixer la contrainte du cœur sur la branche 4.x restaurée (ex. `~4.4.<n>`), puis `composer validate`. **Ne pas** lancer `composer update`. | `composer validate` OK ; un `composer update --dry-run` ne propose aucun paquet 5.x | Remettre le `composer.json` sauvegardé |

## Validation

Contrôles en lecture seule après la ligne 8, pour **chaque** site (`mairie-exemple.fr`, `asso-exemple.org`, le site d'administration et tous les autres sites trouvés en O4), pas seulement pour l'administration :

- `curl -s https://<domaine>/ | rg -o '<title>[^<]*'` : chaque domaine a son propre titre ; les URL de documents sont du type `sites/<domaine>/IMG/…` ou des URL courtes qui renvoient vers le bon site.
- Connexion à l'espace privé de chaque site : on y trouve ses propres auteurs et ses propres contenus.
- O5 sur chaque base : `version_installee` est bien la valeur 4.x de la sauvegarde ; les plugins actifs sont cohérents (lus dans l'espace privé, pas dans un cache).
- Journaux PHP et SPIP de chaque `sites/<domaine>/tmp/log/` : pas d'erreur fatale.
- Après la ligne 10 : `find -P "$farm_root_4x/sites" -newermt '<heure_reouverture>' -path '*/tmp/*'` montre des écritures par site ; `find -P "$farm_root_4x/IMG" "$farm_root_4x/tmp" -newermt '<heure_reouverture>'` ne montre rien de nouveau à la racine.
- Les contrôles de réussite propres à chaque action sont ceux des lignes 6, 7, 8, 10 et 11.

## Retour arrière

- **Déclencheurs** : le test isolé de la ligne 5 échoue ; un site affiche encore le contenu d'un autre après la ligne 8 ; une base n'a pas `version_installee` en 4.x ; une erreur fatale apparaît dans les journaux.
- **Unité de restauration** : le manifeste de la sauvegarde d'avant la mise à jour, identifié par ses sommes de contrôle (code partagé, `config/mes_options.php`, `mutualisation/`, chaque `sites/<domaine>/`, **chaque** base). Ne jamais mélanger une base d'un point de sauvegarde avec le code d'un autre.
- Pendant tout le retour arrière, la maintenance de la ligne 1 reste active. Ordre en cas d'échec : revenir sur la ligne 8 (repointer), puis sur la ligne 7 (réimporter les dumps d'incident de la ligne 4), puis sur la ligne 6 (supprimer le dossier 4.x). Les archives des lignes 3 et 4 sont conservées et ne servent pas de cible.
- Ne relancez pas la montée en 5.0 sur la ferme. La mutualisation y est encore « expérimentale — todo ». Au mieux, on peut faire un essai sur une copie isolée, hors production, quand une procédure de ferme existera.

**Contrôle de couverture** : toutes les actions qui modifient l'état et qui sont proposées dans cette réponse ont une ligne complète dans le plan d'action. Il s'agit du gel (1, 2), de la conservation des preuves (3, 4), du test de restauration (5), de la restauration du code (6), de celle des bases (7), de la bascule (8), de la reprise des contenus (9), de la réouverture (10) et du blocage de la contrainte Composer (11). Le reste n'est que de la lecture.
