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

---

# spip-squelettes

---

## spip-squelettes, cas 8 — site avec `vendor/spip/framework/`

Voici `squelettes/mot.html` modifié. J'ajoute un bloc « Rubriques concernées » sous la liste des articles.

**Principe :** on parcourt les articles liés au mot (`ARTICLES{id_mot}`). Pour chacun, une boucle `RUBRIQUES{id_rubrique}` imbriquée affiche sa rubrique. Le critère `{doublons}` sur cette boucle interne fait qu'une rubrique déjà affichée ne revient pas. Chaque rubrique sort donc une seule fois, même si elle contient plusieurs articles portant ce mot.

Pourquoi ne pas écrire directement `RUBRIQUES{id_mot}` : ce critère renverrait les rubriques auxquelles le mot-clé est attaché **elles-mêmes**, pas celles qui contiennent des articles portant ce mot.

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

	<B_arts_mot>
	<h2>Rubriques concernées</h2>
	<ul class="liste-rubriques">
	<BOUCLE_arts_mot(ARTICLES){id_mot}{par date}{inverse}>
		<BOUCLE_rub_mot(RUBRIQUES){id_rubrique}{doublons}>
		<li><a href="#URL_RUBRIQUE">#TITRE</a></li>
		</BOUCLE_rub_mot>
	</BOUCLE_arts_mot>
	</ul>
	</B_arts_mot>

	<INCLURE{fond=inclure/pied}{env}></INCLURE>
</body>
</html>
</BOUCLE_mot>
```

Comment ça marche :

- `BOUCLE_arts_mot` reprend la sélection de la liste existante : les articles publiés liés au mot courant (`{id_mot}` vient de `BOUCLE_mot`). Elle ne produit rien elle-même.
- Dans `BOUCLE_rub_mot`, `{id_rubrique}` vient de l'article en cours. Elle renvoie donc la rubrique qui contient cet article.
- `{doublons}` mémorise les rubriques déjà affichées sur la page et les écarte ensuite. Aucune autre boucle `RUBRIQUES` de la page ne porte `{doublons}`, donc rien d'autre n'est affecté. Si vous en ajoutez une plus tard, par exemple dans un menu, utilisez un doublon nommé pour garder les deux ensembles séparés : `{doublons rub_mot}`.
- Les sections `<B_arts_mot>` … `</B_arts_mot>` (titre `<h2>` et `<ul>`) ne s'affichent que si le mot a au moins un article. Sans article, la page n'a ni titre vide ni liste vide.
- Les rubriques sortent dans l'ordre du premier article rencontré : celle de l'article le plus récent d'abord. Pour un ordre alphabétique, il faudrait une autre approche, car ici le tri ne peut se faire que sur les articles.

Variante plus compacte : le critère SQL de regroupement `{fusion id_rubrique}` garde un seul article par rubrique. Il n'est pas décrit dans ma documentation, à vérifier donc sur votre version :

```html
<BOUCLE_arts_mot(ARTICLES){id_mot}{fusion id_rubrique}>
	<BOUCLE_rub_mot(RUBRIQUES){id_rubrique}>
	<li><a href="#URL_RUBRIQUE">#TITRE</a></li>
	</BOUCLE_rub_mot>
</BOUCLE_arts_mot>
```

La version avec `{doublons}` ci-dessus n'utilise que des critères documentés. C'est celle que je recommande.

---

## spip-squelettes, cas 9 — plugin 4.4 + 5.0

Voici `mot.html` modifié. J'ai ajouté un bloc « Rubriques concernées » entre la description du mot et la liste des articles.

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

	<B_rubriques_mot>
	<h2>Rubriques concernées</h2>
	<ul class="liste-rubriques">
	<BOUCLE_rubriques_mot(ARTICLES){id_mot}>
		<BOUCLE_rubrique_article(RUBRIQUES){id_rubrique}{doublons rub_mot}>
		<li><a href="#URL_RUBRIQUE">#TITRE</a></li>
		</BOUCLE_rubrique_article>
	</BOUCLE_rubriques_mot>
	</ul>
	</B_rubriques_mot>

	<B_articles>
	<ul class="liste-articles">
	<BOUCLE_articles(ARTICLES){id_mot}{par date}{inverse}>
		<li><a href="#URL_ARTICLE">#TITRE</a></li>
	</BOUCLE_articles>
	</ul>
	</B_articles>

	<INCLURE{fond=inclure/pied}{env}></INCLURE>
</body>
</html>
</BOUCLE_mot>
```

#### Fonctionnement

- **Boucle extérieure `BOUCLE_rubriques_mot(ARTICLES){id_mot}`** : elle parcourt les articles publiés liés au mot. `{id_mot}` vient de la boucle `BOUCLE_mot` qui l'entoure.
- **Boucle intérieure `BOUCLE_rubrique_article(RUBRIQUES){id_rubrique}`** : pour chaque article, elle récupère la rubrique qui le contient, puisque `{id_rubrique}` est repris de l'article en cours. `#TITRE` et `#URL_RUBRIQUE` sont donc ceux de la rubrique.
- **`{doublons rub_mot}`** : c'est ce critère qui affiche chaque rubrique une seule fois. Quand trois articles du mot sont dans la même rubrique, celle-ci ne sort qu'au premier passage. J'ai nommé le jeu de doublons (`rub_mot`) pour qu'il reste séparé de tout autre `{doublons}` éventuel de la page. La liste d'articles existante n'est donc pas touchée.
- **`<B_rubriques_mot>` … `</B_rubriques_mot>`** : le titre « Rubriques concernées » et le `<ul>` ne s'affichent que si le mot a au moins un article. Sinon la page n'affiche aucune liste vide.

#### Remarques

- Le critère `{id_mot}` sur `RUBRIQUES` ne convient pas ici : il renverrait les rubriques auxquelles le mot-clé est attaché directement, pas celles qui contiennent un article portant ce mot.
- L'ordre des rubriques suit l'ordre dans lequel les articles sont lus. Pour les avoir à peu près par ordre alphabétique, on ne peut pas trier la boucle intérieure (elle ne renvoie qu'une ligne à chaque passage). On peut en revanche trier la boucle extérieure. Une autre possibilité est de regrouper les articles par rubrique avec `{fusion id_rubrique}` sur la boucle extérieure, à la place des doublons. Je n'ai pas retenu cette variante parce que ma documentation ne décrit `{fusion}` que pour les boucles `DATA` : testez-la avant de vous en servir.
- Le texte « Rubriques concernées » est écrit en dur. Si le squelette est multilingue, remplacez-le par une chaîne de langue du plugin, par exemple `<:acme:rubriques_concernees:>`.

---

## spip-squelettes, cas 10 — plugin 4.x

Voici `mot.html` modifié. J'ai ajouté un bloc « Rubriques concernées » après la liste des articles :

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

	<B_articles_rubs>
	<h2>Rubriques concernées</h2>
	<ul class="liste-rubriques">
	<BOUCLE_articles_rubs(ARTICLES){id_mot}{par date}{inverse}>
		<BOUCLE_rub_du_mot(RUBRIQUES){id_rubrique}{doublons rubmot}>
		<li><a href="#URL_RUBRIQUE">#TITRE</a></li>
		</BOUCLE_rub_du_mot>
	</BOUCLE_articles_rubs>
	</ul>
	</B_articles_rubs>

	<INCLURE{fond=inclure/pied}{env}></INCLURE>
</body>
</html>
</BOUCLE_mot>
```

#### Comment ça marche

- **`BOUCLE_articles_rubs(ARTICLES){id_mot}`** : `{id_mot}` reprend le mot de la boucle englobante `BOUCLE_mot`. On obtient donc les articles liés à ce mot-clé. Comme d'habitude, la boucle ARTICLES ne renvoie que les articles publiés.
- **`BOUCLE_rub_du_mot(RUBRIQUES){id_rubrique}`** : pour chaque article, `{id_rubrique}` reprend la rubrique de l'article en cours. `#TITRE` et `#URL_RUBRIQUE` sont alors ceux de la rubrique.
- **`{doublons rubmot}`** : une rubrique déjà affichée n'est plus affichée par les passages suivants. Ainsi, si dix articles du mot sont dans la même rubrique, elle n'apparaît **qu'une fois**. Les doublons portent un nom (`rubmot`). Ils sont donc indépendants de tout autre `{doublons}` de la page ou des inclusions.
- **Partie conditionnelle `<B_articles_rubs> … </B_articles_rubs>`** : le titre `<h2>` et le `<ul>` ne s'affichent que si le mot a au moins un article. Dans ce cas, il y a forcément au moins une rubrique, donc pas de `<ul>` vide.

#### À savoir

- **Ordre** : les rubriques sortent dans l'ordre où elles apparaissent pour la première fois dans la liste des articles (du plus récent au plus ancien). Ce n'est pas un ordre alphabétique. Un `{par titre}` sur la boucle RUBRIQUES ne servirait à rien, car elle ne renvoie qu'une rubrique à chaque passage.
- **Rubrique directe seulement** : on liste la rubrique *qui contient* l'article, pas ses rubriques parentes.
- Je n'ai pas modifié `BOUCLE_articles`. J'ai ajouté une deuxième boucle ARTICLES distincte (`_articles_rubs`) pour ne pas mélanger les deux affichages. La requête de plus est légère.
- Variante plus courte, si vous préférez dédoublonner en SQL : `<BOUCLE_articles_rubs(ARTICLES){id_mot}{fusion id_rubrique}>`, qui regroupe les articles par rubrique (GROUP BY). Avec cette variante, le `{doublons rubmot}` de la boucle interne n'est plus nécessaire. Attention : ma documentation ne décrit `{fusion}` que pour les boucles DATA. Je recommande donc la version avec `{doublons}` ci-dessus, qui est la forme documentée.

---

# spip-lang — scénario A (email toujours en anglais), abandonné : pas d’échec

---

## spip-lang A, site avec `plugins-dist/spip/`

#### Pourquoi l'email part dans la langue du visiteur

`_T()` traduit toujours dans la **langue active** au moment de l'appel. Quand `acme_notifier_webmestre()` est déclenchée par la proposition d'un article, la langue active est celle du visiteur qui a fait la demande. Le sujet et le corps sont donc traduits dans sa langue.

Pour forcer l'anglais, on passe temporairement en anglais avec `lang_select('en')` avant les appels à `_T()`. Ensuite, on restaure la langue précédente avec `lang_select()`, sans argument. Il faut la restaurer : sinon, la suite de la requête (affichage, autres traitements) resterait en anglais.

#### Code modifié (`squelettes/mes_fonctions.php`)

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * L'email est toujours rédigé en anglais, quelle que soit la langue
 * du visiteur qui a proposé l'article.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Passer en anglais uniquement le temps de construire le message
	lang_select('en');
	$sujet = _T('acme:email_proposition_sujet');
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre]);
	// Restaurer la langue précédente (ne pas réutiliser la valeur de retour de lang_select('en'))
	lang_select();

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

#### Points d'attention

1. **Ne pas faire `$old = lang_select('en'); … lang_select($old);`**. `lang_select('en')` renvoie `'en'`, la langue qu'on lui passe, et non la langue précédente. Le second appel laisserait donc l'anglais actif. Il faut restaurer avec `lang_select()` ou `lang_select(null)`.

2. **Troisième argument de `_T()` supprimé.** L'ancien code passait la chaîne `'titre_article'` en troisième paramètre. Or la signature est `_T(string $cle, array $args = [], array $options = [])` : ce paramètre attend un tableau d'options (`force`, `sanitize`), pas une chaîne. Je l'ai retiré. S'il servait à quelque chose de précis, dites-le-moi.

3. **Le fichier de langue anglais doit exister.** Il faut un `lang/acme_en.php` avec les clés `email_proposition_sujet` et `email_proposition_corps`, ce dernier contenant le placeholder `@titre@`. Si une clé manque en anglais, `_T()` ne signale aucune erreur : il se rabat silencieusement sur la langue du site, puis sur `fr`. Le webmestre recevrait alors un email en français sans aucun avertissement. Le module `acme` ne fait pas partie des fichiers que j'ai pu consulter, donc vérifiez ce fichier. Exemple :

   ```php
   <?php
   // This is a SPIP language file  --  Ceci est un fichier langue de SPIP
   return [
       // E
       'email_proposition_corps' => 'The article "@titre@" has been submitted for publication.',
       'email_proposition_sujet' => 'New article submitted for publication',
   ];
   ```

4. **Échappement du titre (optionnel).** Par défaut, `_T()` échappe le HTML dans les valeurs des placeholders (`sanitize` vaut `true`). Pour un email en texte brut, un titre contenant `&` ou `<` peut donc apparaître sous forme d'entités. Si c'est gênant, utilisez `_T('acme:email_proposition_corps', ['titre' => $titre], ['sanitize' => false])`. Ne le faites que si le corps n'est jamais interprété comme du HTML.

5. **Version de SPIP.** Le site tourne en SPIP 5.0 : les plugins-dist sont en `5.0.0-dev`. Ma documentation décrit `lang_select()` et `_T()` sans signaler de changement pour la 5.0, mais elle ne couvre pas cette version explicitement. Testez donc l'envoi une fois, par exemple en proposant un article depuis une session en français, et vérifiez que l'email arrive bien en anglais.

---

## spip-lang A, plugin 4.4 + 5.0

#### Forcer l'anglais pour l'email au webmestre

##### Cause

`_T()` traduit dans la langue **courante** de la requête. Quand un visiteur propose un article, c'est sa langue qui est active, donc l'email part dans sa langue. Pour forcer une autre langue, il faut l'empiler avec `lang_select('en')` autour des appels `_T()`, puis restaurer la langue précédente avec `lang_select(null)`.

Attention à un piège : `lang_select('en')` renvoie `'en'`, pas la langue précédente. Il ne faut donc pas garder sa valeur de retour pour restaurer ensuite. On restaure toujours avec `lang_select(null)` ou `lang_select()`.

##### Code modifié : `inc/acme_notifications.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * L'email est toujours rédigé en anglais, quelle que soit la langue
 * du visiteur qui a proposé l'article.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Bascule en anglais uniquement le temps de construire le message
	lang_select('en');
	$sujet = _T('acme:email_proposition_sujet');
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre]);
	lang_select(null); // restaure la langue du visiteur

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Ce qui change :

1. **`lang_select('en')` / `lang_select(null)`** encadrent les deux `_T()`. On restaure la langue juste après, pour que le reste de la requête (la page affichée au visiteur, les autres traitements) reste dans sa langue.
2. **Troisième argument de `_T()`** : l'ancien code passait la chaîne `'titre_article'`. La signature est `_T(string $cle, array $args = [], array $options = [])` : le troisième paramètre est un tableau d'options (`force`, `sanitize`), pas une chaîne. Je l'ai retiré, car il n'apporte rien ici. *(Ce point vient de la signature documentée, je ne l'ai pas vérifié sur une installation.)*

##### Indispensable : le fichier de langue anglais

Le plugin n'a aujourd'hui **aucun dossier `lang/`**. Les clés `acme:email_proposition_*` n'existent donc dans aucune langue. Or `_T()` ne signale pas une clé manquante : il essaie la langue du site, puis `fr`, et en dernier recours il **renvoie le nom brut de la clé** (`email_proposition_sujet`). Sans fichier anglais, le webmestre recevrait ce nom de clé, ou du français si seul `acme_fr.php` existait, même avec `lang_select('en')`.

Il faut donc créer au minimum le fichier de référence et sa traduction anglaise, avec les mêmes clés.

`lang/acme_fr.php` (référence) :

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
	// E
	'email_proposition_corps' => 'L’article « @titre@ » vient d’être proposé à la publication.',
	'email_proposition_sujet' => 'Nouvel article proposé à la publication',
];
```

`lang/acme_en.php` :

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
	// E
	'email_proposition_corps' => 'The article “@titre@” has just been submitted for publication.',
	'email_proposition_sujet' => 'New article submitted for publication',
];
```

Les fichiers sont à enregistrer en UTF-8 sans BOM. Le paramètre `@titre@` doit figurer dans les deux langues.

Déclarez aussi le module dans `paquet.xml`, avec le français comme langue de référence :

```xml
<paquet
	prefix="acme"
	categorie="communication"
	version="1.0.1"
	etat="stable"
	compatibilite="[4.4.0;5.*]"
>
	<nom>Acme</nom>
	<auteur>Acme</auteur>
	<licence>GPL 3</licence>

	<traduire module="acme" reference="fr" />
</paquet>
```

Le plugin manque aussi des chaînes obligatoires `lang/paquet-acme_fr.php` (`acme_description`, `acme_slogan`) pour le gestionnaire de plugins. Elles ne concernent pas l'email, mais c'est le moment de les ajouter.

##### Point à surveiller (facultatif)

Par défaut, `_T()` échappe le HTML dans les valeurs des paramètres (`sanitize => true`). Un titre contenant `&` ou `<` apparaîtra donc échappé, par exemple `&amp;`, dans le corps de l'email. Comme l'email est en texte brut, vous pouvez passer `['sanitize' => false]` en troisième argument :

```php
$corps = _T('acme:email_proposition_corps', ['titre' => $titre], ['sanitize' => false]);
```

##### Vérification

Proposez un article depuis une session dans une autre langue (par exemple en français) : l'email reçu doit être en anglais, et la page affichée ensuite au visiteur doit rester en français. Si le sujet apparaît sous la forme `email_proposition_sujet`, le fichier `lang/acme_en.php` n'est pas trouvé : vérifiez son nom et son emplacement.

---

## spip-lang A, plugin 4.x

Les appels `_T()` utilisent la langue courante, c'est-à-dire celle du visiteur qui a proposé l'article. Pour forcer l'anglais, il faut passer en anglais avec `lang_select('en')` juste avant de construire le sujet et le corps, puis revenir à la langue précédente avec `lang_select(null)`.

Attention : `lang_select()` renvoie la langue qu'on lui passe, pas la langue précédente. Il ne faut donc pas faire `$old = lang_select('en'); ... lang_select($old);`, sinon on ne restaure rien. Il faut appeler `lang_select(null)` (ou `lang_select()` sans argument), qui dépile et remet la langue précédente.

#### Code corrigé — `inc/acme_notifications.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * Le webmestre ne lit que l'anglais : l'email part toujours en anglais,
 * quelle que soit la langue du visiteur qui a proposé l'article.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// On passe en anglais le temps de traduire les chaînes de l'email...
	lang_select('en');
	$sujet = _T('acme:email_proposition_sujet');
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre]);
	// ...puis on revient à la langue précédente (celle du visiteur)
	lang_select(null);

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

#### Ce qui change

1. **`lang_select('en')` / `lang_select(null)`** entourent les deux appels `_T()`. Le sujet et le corps sont donc traduits en anglais. La langue du visiteur est rétablie aussitôt après, donc le reste de la page n'est pas touché.
2. **J'ai corrigé un bug au passage** : le 3e argument de `_T()` était la chaîne `'titre_article'`. Or la signature est `_T(string $cle, array $args = [], array $options = [])`, et le 3e paramètre doit être un tableau d'options (`force`, `sanitize`). Passer une chaîne à un paramètre typé `array` provoque une `TypeError` en PHP 8. Je l'ai donc supprimé : le placeholder `@titre@` est déjà fourni par `['titre' => $titre]`.

#### À vérifier : la chaîne doit exister en anglais

`lang_select('en')` ne suffit pas tout seul. Si le plugin n'a pas les clés en anglais, `_T()` ne signale aucune erreur. Il cherche la clé dans la langue du site (`langue_site`), puis en `fr`, et en dernier recours il affiche la clé brute. L'email partirait alors en français, ou avec le texte `email_proposition_sujet`.

Dans le plugin tel qu'il est, je ne vois **ni dossier `lang/` ni déclaration `<traduire>` dans `paquet.xml`**. Pour que l'email parte vraiment en anglais, il faut :

**`paquet.xml`** : déclarer le module de langue.

```xml
<paquet
	prefix="acme"
	categorie="communication"
	version="1.0.0"
	etat="stable"
	compatibilite="[4.2.0;4.4.*]"
>
	<nom>Acme</nom>
	<auteur>Acme</auteur>
	<licence>GPL 3</licence>
	<traduire module="acme" reference="fr" />
</paquet>
```

**`lang/acme_fr.php`** (langue de référence) :

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
	// E
	'email_proposition_corps' => 'L’article « @titre@ » vient d’être proposé à la publication.',
	'email_proposition_sujet' => 'Nouvel article proposé à la publication',
];
```

**`lang/acme_en.php`** (c'est ce fichier que l'email utilisera) :

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
	// E
	'email_proposition_corps' => 'The article “@titre@” has just been submitted for publication.',
	'email_proposition_sujet' => 'New article submitted for publication',
];
```

Si ces fichiers existent déjà ailleurs dans votre projet, vérifiez seulement que `acme_en.php` contient bien les deux clés `email_proposition_sujet` et `email_proposition_corps`. Les textes ci-dessus ne sont que des exemples : adaptez-les.

#### Remarque sur le titre

Par défaut, `_T()` échappe le HTML des valeurs passées en `$args` (`sanitize => true`). Comme l'email est en texte brut, un titre qui contient `&` ou `<` peut apparaître échappé. Si c'est gênant, utilisez `_T('acme:email_proposition_corps', ['titre' => $titre], ['sanitize' => false])`, mais seulement parce que le corps n'est pas affiché comme du HTML.

---

# spip-lang — scénario B (sujet bilingue), cas 11 à 13

---

## spip-lang, cas 11 — site avec `plugins-dist/spip/`

Voici la modification de `squelettes/mes_fonctions.php`.

On calcule d'abord le sujet dans la langue courante, qui est celle du visiteur. Ensuite, `lang_select('en')` passe temporairement en anglais pour calculer le second sujet, et `lang_select()` sans argument rétablit la langue d'avant. Il ne faut pas stocker la valeur de retour de `lang_select('en')` pour la restaurer : la fonction renvoie `'en'`, pas la langue précédente.

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
	$sujet_visiteur = _T('acme:email_proposition_sujet');

	// Même sujet en anglais : on bascule, puis on restaure la langue précédente
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select();

	$sujet = $sujet_visiteur . ' / ' . $sujet_en;

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Le corps est calculé exactement comme avant. Il reste dans la langue du visiteur, parce que la langue est restaurée avant son calcul.

##### Points à vérifier

- **La clé doit exister en anglais.** Il faut une entrée `'email_proposition_sujet'` dans `lang/acme_en.php`. Sinon, `_T()` ne signale aucune erreur : il retombe sur la langue du site, puis sur `fr`, et vous obtiendrez le même sujet en français des deux côtés du « / ». Pour vérifier d'où vient une chaîne, ajoutez `?var_mode=traduction` à l'URL.
- **Visiteur anglophone.** Le sujet donnera « Subject / Subject ». C'est conforme à la demande. Si vous préférez éviter le doublon, remplacez la concaténation par :
  ```php
  $sujet = ($sujet_visiteur === $sujet_en) ? $sujet_visiteur : $sujet_visiteur . ' / ' . $sujet_en;
  ```
- **Troisième argument de `_T()` dans le corps (non modifié, à signaler).** L'appel `_T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article')` passe la chaîne `'titre_article'` comme options. Or la signature documentée est `_T(string $cle, array $args = [], array $options = [])`, où les options sont un tableau (`force`, `sanitize`). Je ne l'ai pas modifié puisque le corps ne devait pas changer. Le site tourne en SPIP 5.0 (le plugin `mots` est en version 5.0.0-dev) et ma documentation couvre SPIP 4.x : je ne peux donc pas confirmer qu'une chaîne y est encore acceptée à cet endroit. Vérifiez-le. Si seule la substitution de `@titre@` vous intéresse, il suffit d'écrire `_T('acme:email_proposition_corps', ['titre' => $titre])`.

---

## spip-lang, cas 12 — plugin 4.4 + 5.0

Voici la modification de `inc/acme_notifications.php`. Le sujet est d'abord calculé dans la langue courante, celle du visiteur. Ensuite, on passe temporairement en anglais avec `lang_select('en')` pour recalculer la même chaîne, puis on revient à la langue précédente avec `lang_select()` sans argument. Le corps reste calculé comme avant, après la restauration, donc toujours dans la langue du visiteur.

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

	// Sujet dans la langue du visiteur (langue courante)
	$sujet = _T('acme:email_proposition_sujet');

	// Même sujet en anglais
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select(); // restaure la langue du visiteur (ne pas réutiliser la valeur de retour)

	// Inutile de doubler si le visiteur est déjà en anglais
	if ($sujet_en !== $sujet) {
		$sujet .= ' / ' . $sujet_en;
	}

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Quelques remarques :

- **Ne gardez pas la valeur de retour de `lang_select('en')` pour revenir en arrière.** Elle renvoie la langue passée en argument (`'en'`), pas la précédente. Pour restaurer la langue d'origine, il faut appeler `lang_select()` ou `lang_select(null)`.
- **Le corps est calculé après la restauration**, il reste donc dans la langue du visiteur, sans changement.
- **Si le visiteur navigue déjà en anglais**, les deux sujets sont identiques. Le test `$sujet_en !== $sujet` évite alors un sujet du type « X / X ». Si vous voulez toujours le format « X / X », supprimez ce test et concaténez directement.
- **Il faut un fichier `lang/acme_en.php`** qui contient la clé `email_proposition_sujet`. Sans traduction anglaise, `_T()` ne signale rien : il se rabat sur la langue du site, puis sur `fr`. La partie « anglaise » serait alors en français.
  ```php
  <?php
  // This is a SPIP language file  --  Ceci est un fichier langue de SPIP
  return [
    // E
    'email_proposition_sujet' => 'Article submitted for publication',
  ];
  ```
  Adaptez le texte à votre clé `fr`. Vérifiez aussi que le module de langue est bien déclaré dans `paquet.xml`. Le fichier actuel n'a pas de balise `<traduire>` : ajoutez `<traduire module="acme" reference="fr" />` si vos fichiers de langue ne sont pas encore pris en charge.
- **Point hors demande, que je n'ai pas modifié :** l'appel du corps passe une chaîne (`'titre_article'`) en troisième argument de `_T()`. Or la signature documentée est `_T(string $cle, array $args = [], array $options = [])`, qui attend un tableau d'options. Comme le plugin vise aussi SPIP 5, il vaudrait la peine de vérifier ce troisième argument.

---

## spip-lang, cas 13 — plugin 4.x

Voici la modification de `inc/acme_notifications.php`.

Au moment où la fonction s'exécute, la langue active est celle du visiteur, donc le premier `_T()` la produit sans rien changer. Pour la version anglaise, on bascule temporairement en anglais avec `lang_select('en')`, on appelle `_T()`, puis on restaure la langue précédente avec `lang_select(null)`.

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

	// Même sujet en anglais : on bascule, puis on restaure la langue précédente
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select(null);

	// Évite « Subject / Subject » si le visiteur est déjà en anglais
	if ($sujet_en !== $sujet) {
		$sujet .= ' / ' . $sujet_en;
	}

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Points importants :

- **Restaurer avec `lang_select(null)`.** N'utilisez pas `$save = lang_select('en'); … lang_select($save);` : `lang_select()` renvoie la langue qu'on lui passe (`'en'`), pas la langue précédente. La langue ne serait donc jamais restaurée et le corps, calculé ensuite, partirait en anglais. Le corps est calculé après la restauration, il reste donc dans la langue du visiteur, comme avant.
- **La chaîne anglaise doit exister.** Si `lang/acme_en.php` est absent ou ne contient pas `email_proposition_sujet`, `_T()` ne signale aucune erreur. Il se rabat sur la langue du site puis sur `fr`, et la seconde moitié du sujet sortirait en français. Le plugin que j'ai reçu ne contient pas de dossier `lang/`. Vérifiez que `lang/acme_en.php` contient bien la clé, par exemple :
  ```php
  'email_proposition_sujet' => 'New article submitted for publication',
  ```
  Autre point : `paquet.xml` ne déclare pas `<traduire module="acme" reference="fr" />`. Si les fichiers de langue d'acme se trouvent ailleurs, ajoutez cette déclaration.
- **Le cas d'un visiteur déjà en anglais.** Le test `$sujet_en !== $sujet` évite un sujet du type « X / X ». Si vous voulez toujours les deux moitiés, retirez le `if` et gardez seulement `$sujet .= ' / ' . $sujet_en;`.

Remarque hors du périmètre (je n'y ai pas touché, puisque le corps ne doit pas changer) : la ligne du corps passe `'titre_article'`, une chaîne, comme 3e argument de `_T()`. Or la signature documentée est `_T(string $cle, array $args = [], array $options = [])`, avec seulement les options `force` et `sanitize`. Ce 3e argument ne sert probablement à rien, et vous pouvez sans doute le supprimer. Je n'ai pas pu vérifier, à partir de la documentation dont je dispose, si une chaîne y est encore tolérée.
