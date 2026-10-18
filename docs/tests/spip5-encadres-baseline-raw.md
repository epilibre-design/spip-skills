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

---

## spip-logs, cas 4 — site avec `bin/spip` (code de `squelettes/`)

Voici le filtre modifié. Il écrit dans un fichier de log dédié, `tmp/log/meteo.log`, avec la gravité `_LOG_ERREUR`.

#### `squelettes/mes_fonctions.php`

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
		spip_log(
			'API météo injoignable pour la ville « ' . $ville . ' » : ' . $url
				. (is_array($res) && isset($res['status']) ? ' (HTTP ' . $res['status'] . ')' : ''),
			'meteo.' . _LOG_ERREUR
		);
		return '';
	}

	$data = json_decode($res['page'], true);
	if (!isset($data['temp'])) {
		spip_log(
			'Réponse inattendue de l\'API météo pour « ' . $ville . ' » : ' . substr($res['page'], 0, 200),
			'meteo.' . _LOG_AVERTISSEMENT
		);
		return '';
	}

	return $data['temp'] . ' °C';
}
```

#### Ce que fait ce code

- **`'meteo.' . _LOG_ERREUR`** : le deuxième argument de `spip_log()` donne à la fois le nom du fichier (`meteo`, donc `tmp/log/meteo.log`) et la gravité (`_LOG_ERREUR` = 3).
- **La gravité compte.** Par défaut, le seuil `_LOG_FILTRE_GRAVITE` vaut `_LOG_INFO_IMPORTANTE` (5). Un simple `spip_log($msg, 'meteo')` prendrait la gravité `_LOG_INFO` (6) et **ne serait pas écrit**. Avec `_LOG_ERREUR`, le message est écrit sans rien changer à la configuration.
- **Réponse invalide.** J'ai aussi ajouté une trace pour le cas où l'API répond sans `temp` (JSON invalide ou format changé). Ce n'est pas une panne, donc la gravité est `_LOG_AVERTISSEMENT` (4), qui est elle aussi écrite par défaut. Cette branche est facultative.
- **Copie dans `spip.log`.** Les messages envoyés dans un log nommé sont aussi copiés dans `tmp/log/spip.log`.

#### Pour lire le log

```bash
tail -f tmp/log/meteo.log
```

Chaque ligne a la forme `AAAA-MM-JJ HH:MM:SS IP (pid N) :Pub: … : API météo injoignable pour la ville « Paris » : https://…`. Le fichier tourne automatiquement : au-delà de 100 Ko, il devient `meteo.log.1`, et SPIP garde 4 anciens fichiers.

#### Points d'attention

- **Une trace à chaque appel.** Le filtre est appelé à chaque calcul du squelette. Si l'API reste hors service, une ligne s'ajoute à chaque recalcul. SPIP limite toutefois à 100 le nombre de lignes par fichier et par processus PHP (`_MAX_LOG`).
- **Droits d'écriture.** `tmp/log/` doit être accessible en écriture. Sinon `spip_log()` ne fait rien et ne signale aucune erreur.
- **Version de SPIP à vérifier.** Votre site contient un `bin/spip` qui démarre un `SpipLeague\Component\Kernel\Kernel` via Composer. Cela laisse penser à une installation SPIP 5, alors que ma documentation ne couvre que SPIP 4.1 à 4.4. L'API de log a pu changer en SPIP 5. Si c'est bien un SPIP 5, vérifiez que `spip_log()` et les constantes `_LOG_*` y existent toujours et ne sont pas dépréciées avant de déployer ce code.

---

# spip-formulaires

---

## spip-formulaires, cas 5 — site avec `ecrire/src/SpipVersion.php`

Voici la fonction `verifier()` modifiée dans `squelettes/formulaires/inscription_lettre.php`. Le reste du fichier ne change pas.

```php
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
```

Fonctionnement :

- `verifier()` n'est appelée qu'à la soumission, avant `traiter()`. Elle lit la saisie avec `_request()`.
- Si elle renvoie un tableau non vide, `traiter()` n'est pas exécutée et l'`INSERT` dans `spip_inscrits` n'a pas lieu. Le formulaire se réaffiche avec la valeur saisie.
- La clé `email` du tableau correspond au nom du champ. Le message s'affiche donc à côté du champ, dans le `<span class="erreur_message">` que le squelette prévoit déjà, et le bloc `.editer_email` reçoit la classe `erreur`.
- Comme `message_erreur` n'est pas fourni, SPIP affiche son bandeau d'erreur global par défaut. Pour un texte personnalisé, ajoutez par exemple `$erreurs['message_erreur'] = '…'` quand `$erreurs` n'est pas vide.
- `email_valide()` est la fonction du noyau SPIP qui vérifie la syntaxe d'une adresse email. Pour une vérification aussi simple, inutile d'ajouter le plugin Vérifier. S'il est déjà installé sur le site, on peut écrire à la place :

  ```php
  $verifier = charger_fonction('verifier', 'inc');
  if ($msg = $verifier(_request('email'), 'email', ['mode' => 'strict'])) {
  $erreurs['email'] = $msg;
  }
  ```

  Ce contrôle ne couvre pas le champ vide : gardez le test `info_obligatoire` avant.

Point à vérifier : d'après `ecrire/src/SpipVersion.php`, le site tourne sous **SPIP 5.0.0-beta**. Ma documentation ne précise pas si `email_valide()` et les chaînes de langue `info_obligatoire` et `info_email_invalide` sont toujours là, à l'identique, en 5.0. Testez le formulaire avec une adresse vide, puis avec une adresse invalide. Si une chaîne de langue n'apparaît pas, remplacez-la par une clé de votre fichier de langue `inscription_lettre`, par exemple `_T('inscription_lettre:erreur_email')`.

Remarques facultatives sur le squelette `inscription_lettre.html`. Il fonctionne avec ce `verifier()`, mais il s'écarte de la forme canonique d'un formulaire CVT :

- Pour les erreurs de champ, préférez `#ENV*{erreurs/email}`, avec une seule `*`, à `#ENV**{erreurs}|table_valeur{email}`. Par exemple : `[ (#ENV*{erreurs/email}|oui)erreur]` et `[<span class="erreur_message">(#ENV*{erreurs/email})</span>]`. Même chose pour `nom`.
- Pour le message de succès, utilisez `#ENV*{message_ok}` au lieu de `#ENV**{message_ok}`. Ajoutez aussi `role="status"` sur le bloc de succès et `role="alert"` sur le bloc d'erreur.
- Pour la condition d'affichage, la forme canonique est `[(#ENV{editable}|oui) … ]`.

---

## spip-formulaires, cas 6 — plugin 4.4 + 5.0

#### Contrôle de l'email dans `verifier()`

Le contrôle va dans `formulaires_inscription_lettre_verifier_dist()`. Il se fait en deux temps :

1. **Champ obligatoire** : si le champ est vide (espaces retirés), on renvoie `_T('info_obligatoire')`.
2. **Format valide** : sinon, on teste l'adresse avec `email_valide()`, une fonction du cœur de SPIP chargée par `include_spip('inc/filtres')`. Si l'adresse est invalide, on renvoie `_T('info_email_invalide')`.

L'erreur est indexée sur la clé `email`, donc elle s'affiche **sur le champ** dans le squelette. `traiter()` n'est appelé que si `verifier()` renvoie un tableau vide, donc plus rien d'invalide n'arrive dans `spip_inscrits`.

Je n'ai pas utilisé le plugin Verifier ici. Pour un seul contrôle aussi simple, le CVT de base suffit, et cela évite d'ajouter une dépendance `<necessite nom="verifier" …/>` dans `paquet.xml`.

##### `formulaires/inscription_lettre.php` (modifié)

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
	} else {
		include_spip('inc/filtres');
		if (!email_valide($email)) {
			$erreurs['email'] = _T('info_email_invalide');
		} else {
			// on enregistre la valeur sans les espaces autour
			set_request('email', $email);
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

Seule `verifier()` change. `charger()` et `traiter()` restent identiques.

Quelques points sur ce code :
- `verifier()` tourne **avant** `charger()` au moment du POST. Il ne voit donc que `_request()` et les arguments de la balise, ce qui suffit ici.
- `set_request('email', $email)` remplace la valeur reçue par l'adresse sans espaces autour. C'est cette valeur que `traiter()` enregistre.
- Je n'ai pas ajouté de `message_erreur` : quand il est absent, SPIP génère lui-même le bandeau global. Si vous voulez un texte précis, ajoutez `if ($erreurs) { $erreurs['message_erreur'] = '…'; }`.
- Si vous préférez des messages propres au plugin, remplacez les deux `_T()` par vos clés (par exemple `_T('inscription_lettre:erreur_email_invalide')`) et déclarez-les dans votre fichier de langue.

##### Squelette `formulaires/inscription_lettre.html` : facultatif mais conseillé

Le squelette affiche déjà l'erreur du champ `email` : le bloc `.editer_email` reçoit la classe `erreur` et le message apparaît dans un `.erreur_message`. Vous n'avez donc rien à y changer pour que le contrôle marche. Il utilise cependant `#ENV**` (double étoile) pour les erreurs et pour `message_ok`. La double étoile désactive aussi la protection `interdire_scripts`, alors qu'une seule étoile suffit. Je vous conseille de corriger ces lignes au passage, et d'utiliser la syntaxe actuelle `erreurs/champ` avec les rôles d'accessibilité :

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
		<!--extra-->
		<p class="boutons"><input type="submit" class="btn submit" value="S'inscrire" /></p>
	</div></form>
	]
</div>
```

L'attribut HTML `required` et `type="email"` ne servent qu'au confort de saisie dans le navigateur. La vraie protection est le contrôle fait côté serveur dans `verifier()`.

---

## spip-formulaires, cas 7 — plugin 4.x

J'ai ajouté le contrôle dans `formulaires_inscription_lettre_verifier_dist()`, en CVT simple. Avec deux règles sur un seul champ, la dépendance au plugin Verifier n'apporte rien (votre `paquet.xml` ne l'exige d'ailleurs pas).

#### Code modifié : `formulaires/inscription_lettre.php`

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
		// format de l'adresse
		include_spip('inc/filtres');
		if (!email_valide($email)) {
			$erreurs['email'] = _T('form_email_non_valide');
		}
	}

	return $erreurs;
}

function formulaires_inscription_lettre_traiter_dist() {
	sql_insertq('spip_inscrits', [
		'nom' => _request('nom'),
		'email' => trim((string) _request('email')),
	]);

	return ['message_ok' => _T('inscription_lettre:merci')];
}
```

#### Fonctionnement

- L'erreur est rangée sous la clé `email` du tableau renvoyé par `verifier()`. C'est une erreur **de champ** : votre squelette l'affiche déjà dans le `<span class="erreur_message">` du bloc `editer_email` et ajoute la classe `erreur` à ce bloc.
- Je n'ai pas mis de `message_erreur`. Quand il est absent, SPIP génère lui-même le bandeau global à partir du nombre d'erreurs.
- Tant que `verifier()` renvoie un tableau non vide, `traiter()` n'est **pas** appelé, donc rien n'est inséré dans `spip_inscrits`.
- Le `trim()` évite qu'un email composé uniquement d'espaces passe pour rempli. Je l'applique aussi dans `traiter()` pour enregistrer l'adresse sans espaces autour.
- `email_valide()` est la fonction de validation d'adresse du core, définie dans `inc/filtres`, d'où le `include_spip()`. `info_obligatoire` et `form_email_non_valide` sont des chaînes de langue du core. Si vous préférez vos propres messages, créez des clés dans `lang/inscription_lettre_fr.php`, par exemple `_T('inscription_lettre:erreur_email_obligatoire')` et `_T('inscription_lettre:erreur_email_invalide')`.
- `type="email"` sur l'`<input>` ne sert que de garde-fou côté navigateur. Le contrôle dans `verifier()` est le seul qui compte.

Attention : la skill dont je dispose ne documente ni `email_valide()` ni ces deux clés de langue. Je les cite d'après ce que je sais du core SPIP. Vérifiez-les sur votre version (4.2 à 4.4) avant de livrer.

##### Variante si le plugin Verifier est déjà installé

Pour bénéficier de ses messages standards, déclarez la dépendance `<necessite nom="verifier" compatibilite="[3.6.1;]" />` dans `paquet.xml`, puis :

```php
function formulaires_inscription_lettre_verifier_dist() {
	$erreurs = [];
	$email = trim((string) _request('email'));

	if ($email === '') {
		// Verifier considère une valeur vide comme valide : l'obligation reste à notre charge
		$erreurs['email'] = _T('info_obligatoire');
	} else {
		$verifier = charger_fonction('verifier', 'inc');
		if ($msg = $verifier($email, 'email', ['mode' => 'strict'])) {
			$erreurs['email'] = $msg;
		}
	}

	return $erreurs;
}
```

#### En passant : le squelette `inscription_lettre.html`

Le squelette affiche déjà l'erreur, mais il a quelques écarts avec la structure canonique. Je ne les ai pas corrigés, ils sortent de votre demande :

- `#ENV**{message_ok}` devrait être `#ENV*{message_ok}`, car `**` désactive aussi la protection `interdire_scripts`. Les bandeaux devraient aussi porter `role="status"` et `role="alert"`.
- Pour les erreurs de champ, l'écriture recommandée est `#ENV*{erreurs/email}` (une seule étoile), plutôt que `#ENV**{erreurs}|table_valeur{email}`.
- `[(#EDITABLE|oui) …]` s'écrit normalement `[(#ENV{editable}|oui) …]`.
- Il manque le marqueur `<!--extra-->` avant les boutons, et le bouton devrait avoir la classe `btn submit`.
