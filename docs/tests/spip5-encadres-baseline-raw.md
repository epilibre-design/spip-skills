# SPIP 5.0 callouts — RED raw answers

Verbatim answers of the baseline runs (skill without callout). Scoring: `spip5-encadres-baseline.md`.

---

## spip-logs, cas 1 — plugin 5.0 seul (`[5.0.0-dev;5.*]`)

### Log d'erreur dans `acme_importer()`

Quand `recuperer_url()` échoue ou renvoie une page vide, la fonction fait `return 0` sans rien écrire nulle part. On ajoute un `spip_log()` à cet endroit :

- **fichier propre au plugin** : le premier morceau du second argument donne le nom du fichier. Avec `'acme'`, on écrit dans `tmp/log/acme.log`.
- **gravité `_LOG_ERREUR`** (3), c'est-à-dire « une opération a échoué ». C'est important : sans gravité, `spip_log()` utilise `_LOG_INFO` (6), et ce niveau est **filtré par défaut** (le seuil `_LOG_FILTRE_GRAVITE` vaut `_LOG_INFO_IMPORTANTE` = 5). Un simple `spip_log($msg, 'acme')` n'écrirait donc rien en production.

On en profite aussi pour tracer deux autres cas qui échouent sans bruit : l'URL n'est pas configurée, et le contenu n'est pas du JSON valide.

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
	if (!$res || empty($res['page'])) {
		spip_log(
			'acme_importer : flux distant injoignable ou vide : ' . $url
				. (is_array($res) && isset($res['status']) ? ' (HTTP ' . $res['status'] . ')' : ''),
			'acme.' . _LOG_ERREUR
		);
		return 0;
	}

	$items = json_decode($res['page'], true);
	if (!is_array($items)) {
		spip_log('acme_importer : réponse non JSON pour ' . $url . ' : ' . json_last_error_msg(), 'acme.' . _LOG_ERREUR);
		return 0;
	}

	foreach ($items as $item) {
		sql_insertq('spip_acme_items', ['titre' => $item['titre'] ?? '']);
	}
	return count($items);
}
```

Notes :

- J'ajoute le code HTTP seulement si `recuperer_url()` le renvoie (clé `status`). Si votre version ne le renvoie pas, la ligne n'affiche que l'URL.
- Si l'URL du flux contient un jeton ou une clé d'API, ne la loggez pas en clair : masquez la query string avant de l'écrire.
- Le seul cas demandé est le flux injoignable. Vous pouvez supprimer les deux autres logs (URL vide, JSON invalide) si vous n'en voulez pas.

#### Où lire le log

```bash
tail -f tmp/log/acme.log
```

- Les messages d'un log nommé sont **aussi copiés dans `tmp/log/spip.log`**.
- Les fichiers tournent automatiquement : `acme.log.1`, `.2`… au-delà de 100 Ko par défaut, avec 4 anciens fichiers gardés.
- `spip_log()` ne lève jamais d'exception. Si `tmp/log/` n'est pas accessible en écriture, il n'écrit rien, sans prévenir. Vérifiez donc les droits sur le serveur si rien n'apparaît.
- En développement, vous pouvez aussi ajouter des traces `'acme.' . _LOG_DEBUG`. Pour les voir, mettez `define('_LOG_FILTRE_GRAVITE', _LOG_DEBUG);` dans `config/mes_options.php`, jamais dans le plugin.

---

## spip-logs, cas 2 — plugin 4.4 + 5.0 (`[4.4.0;5.*]`)

Voici `inc/acme_import.php` modifié. Les erreurs sont écrites avec `spip_log()` dans un fichier propre au plugin : `tmp/log/acme.log`. Le type `'acme'` reprend le préfixe du plugin.

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
		spip_log('acme_importer : flux distant injoignable : ' . $url, 'acme.' . _LOG_ERREUR);
		return 0;
	}
	if (empty($res['page'])) {
		spip_log('acme_importer : réponse vide du flux distant : ' . $url, 'acme.' . _LOG_ERREUR);
		return 0;
	}

	$items = json_decode($res['page'], true);
	if (!is_array($items)) {
		spip_log('acme_importer : réponse JSON invalide (' . json_last_error_msg() . ') : ' . $url, 'acme.' . _LOG_ERREUR);
		return 0;
	}

	foreach ($items as $item) {
		sql_insertq('spip_acme_items', ['titre' => $item['titre'] ?? '']);
	}
	return count($items);
}
```

#### Pourquoi c'est écrit ainsi

- **Le fichier propre au plugin** : avec `$name = 'acme.' . _LOG_ERREUR`, le message va dans `tmp/log/acme.log` (type `acme`) avec la gravité `_LOG_ERREUR`. SPIP recopie aussi chaque message d'un log nommé dans `spip.log`.
- **La gravité est indispensable** : un simple `spip_log($msg, 'acme')` prend la gravité par défaut `_LOG_INFO` (6). Or le seuil par défaut `_LOG_FILTRE_GRAVITE` vaut `_LOG_INFO_IMPORTANTE` (5). Un tel message ne serait donc **jamais écrit**, et vous seriez revenu au point de départ. `_LOG_ERREUR` (3) est sous le seuil : le message est écrit sans aucune configuration.
- **Un message distinct pour chaque cas d'échec** : URL non configurée, flux injoignable, réponse vide et JSON invalide (l'ancien `?: []` masquait aussi ce dernier cas). Le log indique ainsi directement l'origine du problème.

#### Lire le log

```bash
tail -f tmp/log/acme.log
```

Une ligne ressemble à :

```
2026-10-18 10:12:03 127.0.0.1 (pid 12345) :Pri: ERREUR: acme_importer : flux distant injoignable : https://exemple.org/flux.json
```

#### À savoir

- `spip_log()` ne lève jamais d'exception. Si `tmp/log/` n'est pas accessible en écriture, il n'écrit rien, sans erreur. En production, vérifiez donc les droits sur ce répertoire.
- Le fichier tourne automatiquement : au-delà de 100 Ko, il est décalé en `acme.log.1`, `.2`, etc., et 4 anciens fichiers sont conservés par défaut.
- Si l'URL du flux contient un jeton ou une clé d'API, ne la loguez pas en entier : retirez la query string avant de l'écrire dans le log.

---

## spip-logs, cas 3 — plugin 4.x (`[4.2.0;4.4.*]`)

Voici `inc/acme_import.php` modifié. Quand le flux est injoignable (ou renvoie une réponse vide), on écrit une erreur dans le log propre au plugin, `tmp/log/acme.log`, avec la gravité `_LOG_ERREUR` :

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
		spip_log(
			'acme_importer : flux distant injoignable ou vide (url=' . $url
				. ', status=' . ($res['status'] ?? 'aucune réponse') . ')',
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

Pourquoi c'est écrit ainsi :

- **Le fichier propre au plugin** : le second argument de `spip_log()` sous la forme `'type.' . gravité` fixe à la fois le fichier et la gravité. `'acme.' . _LOG_ERREUR` écrit dans `tmp/log/acme.log`. La ligne est aussi recopiée dans `tmp/log/spip.log`.
- **La gravité `_LOG_ERREUR` est indispensable** : par défaut, `_LOG_FILTRE_GRAVITE` vaut `_LOG_INFO_IMPORTANTE` (5). Un simple `spip_log($msg, 'acme')` prend la gravité `_LOG_INFO` (6) et **n'écrirait rien** en production. `_LOG_ERREUR` (3) passe le seuil, donc le message est écrit sans aucune configuration.
- **Le contexte dans le message** : l'URL et le statut HTTP éventuel (`$res['status']`) permettent de distinguer un serveur injoignable (`recuperer_url()` renvoie `false`, d'où « aucune réponse ») d'une réponse HTTP en erreur ou vide. Si l'URL du flux contient un jeton ou une clé d'API, retirez-la du message : il ne faut jamais journaliser de secrets.

Pour lire le log sur le serveur :

```bash
tail -f tmp/log/acme.log
```

Remarques :

- `spip_log()` ne lève jamais d'exception. Si `tmp/log/` n'est pas accessible en écriture, il n'écrit rien, sans prévenir. Vérifiez donc les droits sur ce dossier si rien n'apparaît.
- Les fichiers tournent automatiquement : `acme.log.1`, `acme.log.2`, etc., au-delà de 100 Ko par défaut.
- Si vous voulez aussi tracer les imports réussis, utilisez `'acme.' . _LOG_INFO_IMPORTANTE`, par exemple `spip_log('Import : ' . count($items) . ' éléments', 'acme.' . _LOG_INFO_IMPORTANTE);`. Ce message est écrit avec le seuil par défaut, contrairement à `_LOG_INFO`.
