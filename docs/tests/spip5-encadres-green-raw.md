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
