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

---

# spip-lang — scénario B rejoué avec les fichiers de langue fr/en dans les projets

---

## spip-lang, cas 11 — tirage 1

J'ai modifié `acme_notifier_webmestre()` dans `squelettes/mes_fonctions.php` : le sujet est maintenant « sujet dans la langue du visiteur / sujet en anglais ». Le texte du corps ne change pas. Je n'ai pas exécuté le code.

#### Code modifié

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 * Sujet bilingue : langue du visiteur, puis « / », puis anglais.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Sujet dans la langue courante (celle du visiteur)
	$sujet_visiteur = _T('acme:email_proposition_sujet');

	// Même sujet en anglais : on bascule temporairement la langue, puis on restaure
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select(); // dépile : retour à la langue du visiteur

	$sujet = $sujet_visiteur . ' / ' . $sujet_en;

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre]);

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Exemple : un visiteur francophone produit le sujet « Article proposé à la publication / Article submitted for publication ».

#### Points importants

- **Pas de fichier de langue à modifier.** La clé `email_proposition_sujet` existe déjà dans `squelettes/lang/acme_fr.php` et dans `squelettes/lang/acme_en.php`.
- **Retour à la langue d'origine.** On appelle `lang_select('en')`, puis `lang_select()` sans argument. Ce second appel remet la langue du visiteur pour la suite de la requête. Il ne faut pas réutiliser la valeur renvoyée par `lang_select()` pour revenir en arrière : elle renvoie la langue qu'on vient de passer (`'en'`), pas la précédente.
- **Visiteur anglophone.** Il recevra « Article submitted for publication / Article submitted for publication ». C'est ce que la demande implique. Si vous préférez un seul sujet dans ce cas, il suffit de comparer `$sujet_visiteur` et `$sujet_en` avant de les concaténer.
- **Petite correction sur l'appel du corps.** Le code d'origine passait une chaîne (`'titre_article'`) en troisième argument de `_T()`. Ma documentation donne la signature `_T(string $cle, array $args = [], array $options = [])` : ce troisième argument doit être un tableau d'options (`force`, `sanitize`). Je l'ai donc retiré. Le texte du corps reste le même : même clé, même `@titre@`. Si vous vouliez une option précise, passez-la sous forme de tableau, par exemple `['sanitize' => false]`.
- **Version de SPIP.** Le site semble tourner sous SPIP 5.0 : `plugins-dist/spip/mots/paquet.xml` est en `5.0.0-dev`. Or ma documentation (la skill spip-lang) décrit SPIP 4.x, et je n'ai pas de quoi vérifier ce qui a changé dans `_T()` ou `lang_select()` en 5.0. Après le déploiement, testez un envoi pour confirmer que les deux parties du sujet sont bien traduites. Le débogage avec `?var_mode=traduction` ne sert que pour les pages ; il ne s'applique pas à un email.

---

## spip-lang, cas 11 — tirage 2

Voici la modification de `squelettes/mes_fonctions.php`. Le sujet est d'abord traduit dans la langue courante, celle du visiteur. On bascule ensuite temporairement en anglais avec `lang_select('en')` pour obtenir la version anglaise, puis on revient à la langue précédente avec `lang_select()`. Le corps n'est pas modifié.

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

	// Même sujet en anglais : on empile 'en', puis on restaure la langue précédente
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select();

	$sujet = $sujet_visiteur . ' / ' . $sujet_en;

	// Corps inchangé
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Points à noter :

- **Restauration de la langue.** `lang_select('en')` empile la langue courante avant de passer à l'anglais. `lang_select()`, appelé sans argument (ou avec `null`), la dépile. Il ne faut pas stocker la valeur de retour de `lang_select('en')` pour la restaurer ensuite : elle vaut `'en'`, et non la langue précédente. Le `lang_select()` est placé juste après le `_T()` anglais, si bien que le reste de la requête garde la langue du visiteur.
- **Fichiers de langue.** Rien à ajouter : `squelettes/lang/acme_fr.php` et `squelettes/lang/acme_en.php` définissent déjà tous les deux `email_proposition_sujet`. Pour un visiteur francophone, le sujet devient « Article proposé à la publication / Article submitted for publication ».
- **Visiteur anglophone.** Le sujet affichera deux fois la même phrase (« Article submitted for publication / Article submitted for publication »). C'est ce que demande la consigne à la lettre. Si vous préférez un sujet simple dans ce cas, ajoutez une condition `if ($GLOBALS['spip_lang'] !== 'en')` autour de la concaténation. Dites-moi si vous le voulez.
- **Langue sans traduction.** Si la langue du visiteur n'a pas de fichier `acme_XX.php`, `_T()` se rabat sans erreur sur la langue du site puis sur `fr`. La première moitié du sujet sera donc en français plutôt que dans la langue du visiteur.

**À vérifier : votre site tourne sous SPIP 5.0**, comme l'indique `plugins-dist/spip/mots/paquet.xml` (`version="5.0.0-dev"`, `compatibilite="[5.0.0-dev;5.*]"`). Or ma documentation ne couvre que SPIP 4.x. Je n'ai donc pas pu vérifier deux points sur SPIP 5.0 :

1. Si `lang_select()` reste la bonne méthode pour traduire dans une autre langue, ou si `_T()` y propose une option dédiée pour indiquer la langue.
2. Le troisième argument du `_T()` du corps, la chaîne `'titre_article'`. Ma documentation décrit `$options` comme un tableau (`force`, `sanitize`) et ne dit rien d'une chaîne passée à cet endroit. J'ai laissé cette ligne telle quelle puisque le corps ne devait pas changer. Mais sur SPIP 5.0, cet appel risque de ne pas faire ce qui est attendu, voire d'échouer. Vérifiez-le dans la documentation SPIP 5.0 ou dans `ecrire/inc/utils.php` de votre installation.

---

## spip-lang, cas 11 — tirage 3

Voici la modification de `acme_notifier_webmestre()` dans `squelettes/mes_fonctions.php`.

Le sujet est calculé deux fois :
1. une première fois dans la langue courante, qui est celle du visiteur ;
2. une seconde fois en anglais. Pour cela, on bascule temporairement la langue avec `lang_select('en')`, puis on revient à la langue précédente avec `lang_select()` (sans argument).

Les deux versions sont ensuite jointes par « / ». Le fichier `squelettes/lang/acme_en.php` contient déjà la clé `email_proposition_sujet`, donc les fichiers de langue ne changent pas.

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

	// Même sujet en anglais : on empile la langue 'en', puis on restaure la précédente
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select(); // dépile : retour à la langue du visiteur (ne pas réutiliser la valeur de retour)

	$sujet = $sujet_visiteur . ' / ' . $sujet_en;

	// Corps inchangé
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Exemple pour un visiteur francophone :
`Article proposé à la publication / Article submitted for publication`

##### Points d'attention

- **`lang_select()` doit toujours être appelé par paires.** Il ne faut pas faire `$old = lang_select('en'); … lang_select($old);`. `lang_select()` renvoie la langue qu'on lui a passée, et non la langue précédente. Pour restaurer, on appelle `lang_select()` sans argument ou avec `null`.
- **Visiteur anglophone.** Avec ce code, le sujet devient `Article submitted for publication / Article submitted for publication`. Si vous préférez éviter ce doublon, remplacez la ligne `$sujet = …` par celle-ci :
  ```php
  $sujet = ($sujet_visiteur === $sujet_en) ? $sujet_en : $sujet_visiteur . ' / ' . $sujet_en;
  ```
- **Troisième argument de `_T()` sur la ligne du corps (non modifiée).** L'appel passe la chaîne `'titre_article'` en troisième argument. Or la signature documentée est `_T(string $cle, array $args = [], array $options = [])`, et les seules options documentées sont `force` et `sanitize`, sous forme de tableau. Votre site semble tourner sous SPIP 5.0 (`plugins-dist/spip/mots/paquet.xml` déclare `5.0.0-dev`). Ma documentation porte sur SPIP 4.x et ne précise pas si ce passage d'une chaîne est encore accepté en 5.0. Si l'envoi provoque une erreur de type sur cette ligne, la correction est la suivante :
  ```php
  $corps = _T('acme:email_proposition_corps', ['titre' => $titre]);
  ```
  Comme vous avez demandé de ne pas changer le corps, je ne l'ai pas modifiée.

---

## spip-lang, cas 11 — tirage 4

Voici la modification de `squelettes/mes_fonctions.php`. Le sujet est d'abord traduit dans la langue courante (celle du visiteur), puis j'utilise `lang_select('en')` pour passer en anglais et obtenir la même chaîne, et `lang_select(null)` pour revenir à la langue précédente. Le corps n'est pas modifié.

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Prévient le webmestre qu'un article vient d'être proposé à la publication.
 *
 * Le sujet est bilingue : langue du visiteur / anglais.
 */
function acme_notifier_webmestre(int $id_article): void {
	$titre = sql_getfetsel('titre', 'spip_articles', 'id_article=' . $id_article);

	// Sujet dans la langue du visiteur (langue courante)
	$sujet_visiteur = _T('acme:email_proposition_sujet');

	// Même sujet en anglais
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select(null); // retour à la langue précédente (ne pas réutiliser la valeur de retour de lang_select('en'))

	$sujet = $sujet_visiteur . ' / ' . $sujet_en;

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Par exemple, pour un visiteur francophone, le sujet devient :
`Article proposé à la publication / Article submitted for publication`

Quelques remarques :

- **Pas de nouvelle chaîne de langue.** La clé `email_proposition_sujet` existe déjà dans `squelettes/lang/acme_fr.php` et dans `squelettes/lang/acme_en.php`. Les fichiers de langue ne changent donc pas.
- **Le retour à la langue doit se faire avec `lang_select(null)`.** `lang_select('en')` renvoie `'en'`, pas la langue précédente. Si on stockait cette valeur pour la remettre ensuite, on resterait en anglais. Il faut aussi que `lang_select(null)` soit appelé avant le calcul du corps, pour que le corps reste dans la langue du visiteur, comme aujourd'hui.
- **Visiteur anglophone.** Le sujet sera alors `Article submitted for publication / Article submitted for publication`. La consigne ne dit rien sur ce cas, donc j'ai gardé le comportement littéral. Si vous préférez n'afficher qu'une seule fois le sujet quand les deux textes sont identiques, il suffit d'ajouter `if ($sujet_en !== $sujet_visiteur)` autour de la concaténation.
- **Point à vérifier, version de SPIP.** Le site tourne sous SPIP 5.0 : `plugins-dist/spip/mots/paquet.xml` indique la version `5.0.0-dev` et la compatibilité `[5.0.0-dev;5.*]`. Ma documentation ne couvre que SPIP 4.x. `lang_select()` et `_T('module:cle')` y sont décrits, mais je n'ai pas de quoi confirmer leur fonctionnement sous SPIP 5.0.
- **Le troisième argument de `_T()` dans le corps.** L'appel existant passe une chaîne, `'titre_article'`, comme troisième argument. Ma documentation décrit cet argument comme un tableau d'options (`force`, `sanitize`). Je n'ai pas modifié cette ligne, puisque le corps ne doit pas changer, mais elle mérite d'être vérifiée, surtout sous SPIP 5.0.

---

## spip-lang, cas 12

Voici la modification de `acme_notifier_webmestre()` dans `inc/acme_notifications.php`.

`_T()` traduit dans la langue courante, celle du visiteur. Pour avoir la version anglaise, on passe temporairement en anglais avec `lang_select('en')`, on relit la même clé, puis on rétablit la langue précédente avec `lang_select(null)`.

Attention : `lang_select()` renvoie la langue qu'on lui passe, pas l'ancienne. Il ne faut donc pas mémoriser sa valeur de retour pour revenir en arrière.

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
	lang_select(null); // rétablit la langue du visiteur

	// Si le visiteur est déjà en anglais, on n'ajoute pas un doublon
	if ($sujet_en !== $sujet) {
		$sujet .= ' / ' . $sujet_en;
	}

	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Résultat pour un visiteur francophone :
`Article proposé à la publication / Article submitted for publication`

**Ce qu'il faut savoir**

- **Fichiers de langue :** aucun changement. La clé `email_proposition_sujet` existe déjà dans `lang/acme_fr.php` et dans `lang/acme_en.php`.
- **Retour en arrière :** `lang_select(null)` est appelé juste après la lecture du sujet anglais, avant de calculer le corps. Le corps reste donc dans la langue du visiteur, comme avant.
- **Doublon :** si le visiteur navigue déjà en anglais, les deux sujets sont identiques. Le code n'en garde alors qu'un. Si vous voulez quand même `Article submitted for publication / Article submitted for publication`, retirez le `if`.
- **Corps de l'email :** je n'y ai pas touché. Mais l'appel passe un texte (`'titre_article'`) en troisième argument de `_T()`. Or la signature documentée est `_T(string $cle, array $args = [], array $options = [])`, et les options connues sont `force` et `sanitize`. Ce texte ne correspond à aucune option documentée. Votre plugin déclare `compatibilite="[4.4.0;5.*]"` : je vous conseille de vérifier que cet appel fonctionne bien sous SPIP 5. Si vous n'avez pas besoin de ce troisième argument, le plus sûr est de le supprimer : `_T('acme:email_proposition_corps', ['titre' => $titre])`.

---

## spip-lang, cas 13

Voici la modification de `inc/acme_notifications.php`.

Au moment de l'appel, la langue courante est celle du visiteur, donc un `_T()` sans rien de plus renvoie déjà le sujet dans sa langue. Pour obtenir la version anglaise, on bascule temporairement en anglais avec `lang_select('en')`, puis on revient à la langue précédente avec `lang_select()` (sans argument). Il ne faut pas réutiliser la valeur de retour de `lang_select()` pour revenir en arrière : elle renvoie la langue qu'on vient de passer, pas la précédente.

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

	// Même sujet en anglais : bascule temporaire de langue, puis restauration
	lang_select('en');
	$sujet_en = _T('acme:email_proposition_sujet');
	lang_select();

	$sujet = $sujet_visiteur . ' / ' . $sujet_en;

	// Corps inchangé
	$corps = _T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article');

	$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
	$envoyer_mail($GLOBALS['meta']['email_webmaster'], $sujet, $corps);
}
```

Les fichiers de langue n'ont pas besoin de changer : la clé `email_proposition_sujet` existe déjà dans `lang/acme_fr.php` et dans `lang/acme_en.php`. Avec un visiteur francophone, le sujet devient :

> Article proposé à la publication / Article submitted for publication

Deux remarques :

- **Visiteur anglophone** : le sujet affichera deux fois la même chose (« Article submitted for publication / Article submitted for publication »). C'est bien ce que demande la consigne prise à la lettre. Si vous préférez ne garder qu'une version dans ce cas, il suffit de comparer les deux chaînes :
  ```php
  $sujet = ($sujet_visiteur === $sujet_en) ? $sujet_en : $sujet_visiteur . ' / ' . $sujet_en;
  ```
- **Corps (non modifié, comme demandé)** : l'appel au corps passe une chaîne `'titre_article'` en troisième argument de `_T()`. Or la signature documentée est `_T(string $cle, array $args = [], array $options = [])`, où le troisième argument est un tableau d'options (`force`, `sanitize`). Je n'y ai pas touché pour rester dans le périmètre demandé, mais cet argument mérite d'être vérifié : il n'a pas la forme attendue.

---

## spip-plugins, cas 14 — site avec `ecrire/src/SpipVersion.php` — tirage 1

#### Plugin `acme_bandeau`

##### Point d'attention : ce site est en SPIP 5.0

Le fichier `ecrire/src/SpipVersion.php` du site indique `VERSION = '5.0.0-beta'` (PHP 8.4 minimum). Le squelette d'exemple habituel, avec `compatibilite="[4.2.0;4.*]"`, **ne s'activerait pas** sur ce site : la borne `4.*` exclut 5.0. J'ai donc déclaré une compatibilité qui inclut la branche 5 (pré-versions comprises, puisque le site est en beta).

Réserve : ma documentation de référence décrit l'API des plugins de SPIP 4.4. Je n'ai pas de source qui confirme que le pipeline `affichage_final` et la globale `$GLOBALS['html']` fonctionnent à l'identique en 5.0. Ce sont des mécanismes historiques du cœur et il est probable qu'ils n'aient pas changé, mais il faudra tester sur le site (voir « Vérification » plus bas).

##### Arborescence

```
plugins/acme_bandeau/
├── paquet.xml
└── acme_bandeau_pipelines.php
```

Pas de table SQL, donc ni attribut `schema` ni `acme_bandeau_administrations.php`. Pas de fichier de langue non plus : le texte du bandeau est fixe, comme demandé.

##### `plugins/acme_bandeau/paquet.xml`

```xml
<paquet
	prefix="acme_bandeau"
	categorie="communication"
	version="1.0.0"
	etat="stable"
	compatibilite="[4.2.0;5.*]"
>
	<nom>Acme bandeau</nom>
	<!-- Insère un bandeau d'annonce en haut de chaque page publique -->

	<auteur>Acme</auteur>
	<licence lien="http://www.gnu.org/licenses/gpl-3.0.html">GPL</licence>

	<pipeline nom="affichage_final" inclure="acme_bandeau_pipelines.php" />
</paquet>
```

- `compatibilite="[4.2.0;5.*]"` : de 4.2.0 inclus jusqu'à toute la branche 5.x, ce qui couvre la 5.0.0-beta du site. Si vous ne visez que ce site, `compatibilite="[5.0.0-dev;5.*]"` convient aussi : c'est la forme qui part explicitement des pré-versions de la 5.0.
- Sans attribut `action`, SPIP appelle la fonction `acme_bandeau_affichage_final()` (convention `[prefix]_[pipeline]`) dans le fichier indiqué par `inclure`.

##### `plugins/acme_bandeau/acme_bandeau_pipelines.php`

```php
<?php

/**
 * Pipelines du plugin Acme bandeau.
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Insère le bandeau de maintenance juste après la balise <body>.
 *
 * affichage_final reçoit la page publique complète juste avant l'envoi
 * au navigateur (l'espace privé a son propre pipeline, affichage_final_prive).
 *
 * @param string $texte HTML complet de la page
 * @return string HTML modifié
 */
function acme_bandeau_affichage_final($texte) {
	// Ne rien faire si la réponse n'est pas une page HTML (flux RSS, JSON, CSS...)
	if (empty($GLOBALS['html'])) {
		return $texte;
	}

	// Ne pas insérer le bandeau deux fois
	if (str_contains($texte, 'class="acme-bandeau"')) {
		return $texte;
	}

	$bandeau = '<div class="acme-bandeau">Maintenance prévue le 3 novembre</div>';

	// <body> porte des attributs dans les squelettes du site
	// (ex. <body class="page_sommaire">) : on cible la balise ouvrante complète.
	return preg_replace(
		'/<body\b[^>]*>/i',
		'$0' . $bandeau,
		$texte,
		1
	);
}
```

Points clés :

- **Toutes les pages publiques, sans toucher aux squelettes** : `affichage_final` reçoit le HTML final de chaque page publique. Vos squelettes (`squelettes/sommaire.html`, etc.) n'ont pas besoin d'être modifiés.
- **`<body>` avec attributs** : `squelettes/sommaire.html` contient `<body class="page_sommaire">`. Un simple `str_replace('<body>', ...)` ne fonctionnerait pas ; l'expression régulière `/<body\b[^>]*>/i` couvre `<body>` et `<body ...>`, et `$0` réinsère la balise d'origine avant le bandeau.
- **Une seule insertion** : la limite `1` de `preg_replace()` et le test `str_contains()` évitent les doublons.
- **Pages non HTML** : `$GLOBALS['html']` ne vaut vrai que pour les réponses `text/html`. Les flux RSS, le JSON, etc. ne sont pas touchés.

##### Installation et vérification

1. Copiez le dossier dans `plugins/acme_bandeau/`.
2. Dans l'espace privé, ouvrez **Configuration → Gestion des plugins** et activez « Acme bandeau ». S'il apparaît comme incompatible, ajustez `compatibilite` comme indiqué plus haut.
3. Videz le cache (**Maintenance → Vider le cache**) ou chargez une page avec `?var_mode=recalcul`.
4. Affichez la source d'une page publique (sommaire, article, rubrique) : le `<div class="acme-bandeau">` doit suivre immédiatement la balise `<body ...>`.

Pour la mise en forme, ajoutez une règle `.acme-bandeau { ... }` dans la feuille de style de votre squelette. Si vous préférez que le plugin la fournisse, il faut ajouter un fichier `css/acme_bandeau.css` et le pipeline `insert_head_css` ; dites-le-moi et je l'ajoute.

Pour retirer le bandeau après le 3 novembre, il suffit de désactiver le plugin.

---

## spip-plugins, cas 14 — tirage 2

### Plugin `acme_bandeau`

#### Point d'attention : la version de SPIP du site

Le site tourne sous **SPIP 5.0.0-beta** (`ecrire/src/SpipVersion.php` : `VERSION = '5.0.0-beta'`, PHP 8.4 minimum).
Les exemples de ma documentation sont écrits pour SPIP 4.4 et utilisent `compatibilite="[4.2.0;4.*]"` :
avec cette borne, le plugin **ne serait pas activable** sur ce site. J'ai donc déclaré une
compatibilité SPIP 5 (`[5.0.0-dev;5.*]`, la forme `-dev` couvrant aussi les pré-versions beta).

Ma documentation ne décrit pas les éventuels changements de SPIP 5.0 sur les pipelines. Le
pipeline `affichage_final` et la globale `$GLOBALS['html']` sont documentés pour SPIP 4.4 ; je n'ai
pas pu vérifier qu'ils fonctionnent à l'identique en 5.0 beta. Testez le plugin sur le site avant la mise en production
(voir « Vérification » plus bas).

#### Approche

- Pipeline **`affichage_final`** : il reçoit la page HTML complète juste avant l'envoi au
  navigateur, et seulement dans l'espace public (l'espace privé a son propre pipeline,
  `affichage_final_prive`). C'est donc « chaque page publique », quel que soit le squelette.
- On ne traite que les réponses HTML (`$GLOBALS['html']`) pour ne pas toucher aux flux RSS, au JSON, etc.
- La balise `<body>` des squelettes porte des attributs (`<body class="page_sommaire">` dans
  `squelettes/sommaire.html`), donc un simple `str_replace('<body>', …)` ne marcherait pas : on cherche
  `<body …>` par une expression régulière, et on ne remplace que la première occurrence.
- Comme `affichage_final` s'exécute après le cache des squelettes, le bandeau apparaît dès
  l'activation du plugin, sans vider le cache, et disparaît dès sa désactivation.

#### Arborescence

```
plugins/acme_bandeau/
├── paquet.xml
└── acme_bandeau_pipelines.php
```

Pas de table SQL, donc pas d'attribut `schema` ni de fichier `acme_bandeau_administrations.php`.
Le texte du bandeau est imposé en français, donc le plugin ne déclare pas de module de langue.

#### `plugins/acme_bandeau/paquet.xml`

```xml
<paquet
	prefix="acme_bandeau"
	categorie="communication"
	version="1.0.0"
	etat="stable"
	compatibilite="[5.0.0-dev;5.*]"
>
	<nom>Acme Bandeau</nom>
	<!-- Insère un bandeau d'information en haut de chaque page publique -->
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
 * Insère le bandeau de maintenance juste après la balise <body> des pages publiques.
 *
 * @pipeline affichage_final
 * @param string $texte Page HTML complète
 * @return string
 */
function acme_bandeau_affichage_final($texte) {
	// Ne traiter que les pages HTML (pas les flux RSS, JSON, etc.)
	if (empty($GLOBALS['html'])) {
		return $texte;
	}

	$bandeau = '<div class="acme-bandeau">Maintenance prévue le 3 novembre</div>';

	// <body> peut porter des attributs (class, id…) : on cible la première balise ouvrante
	return preg_replace('/<body\b[^>]*>/i', '$0' . $bandeau, $texte, 1);
}
```

Remarques :
- `$0` reprend la balise `<body …>` d'origine telle quelle, le bandeau est placé juste derrière.
- Le texte du bandeau ne contient ni `$` ni `\`, il peut donc servir sans échappement dans le motif de remplacement.
- S'il n'y a pas de `<body>` (fragment AJAX, page HTML partielle), `preg_replace` ne change rien.
- Si le fichier est enregistré en UTF-8 (cas standard de SPIP), « prévue » s'affiche correctement.

#### Installation

1. Copier le dossier dans `plugins/acme_bandeau/`.
2. Dans l'espace privé : **Configuration → Gestion des plugins**, activer « Acme Bandeau ».

#### Vérification

- Ouvrir la page d'accueil (`squelettes/sommaire.html`), afficher le code source et vérifier
  `<body class="page_sommaire"><div class="acme-bandeau">Maintenance prévue le 3 novembre</div>`.
- Vérifier qu'aucun bandeau n'apparaît dans l'espace privé ni dans un flux RSS (`spip.php?page=backend`).
- Si le plugin refuse de s'activer ou si le bandeau n'apparaît pas, c'est probablement que SPIP 5.0 beta
  diffère de SPIP 4.4 (borne de compatibilité, signature ou déclenchement de `affichage_final`,
  `$GLOBALS['html']`). Ma documentation ne couvre que SPIP 4.4 : il faut alors vérifier dans les sources
  du cœur installé (`ecrire/`).

Pour retirer le bandeau après la maintenance, il suffit de désactiver le plugin.

---

## spip-plugins, cas 14 — tirage 3

### Plugin `acme_bandeau`

#### Point d'attention : ce site tourne sous SPIP 5.0 (beta)

Avant d'écrire le plugin, j'ai regardé la version du site : `ecrire/src/SpipVersion.php`
déclare `VERSION = '5.0.0-beta'` (PHP 8.4 minimum). Ma documentation porte sur
**SPIP 4.4** (exemples tirés des sources 4.4.15) et ne dit rien des changements de
SPIP 5.0. Il faut donc en tenir compte :

- l'intervalle `compatibilite` doit inclure la branche 5, sinon le plugin ne
  pourra pas être activé ; l'exemple type de la doc (`[4.2.0;4.*]`) l'exclurait ;
- le pipeline `affichage_final` et `$GLOBALS['html']` sont documentés pour SPIP 4.x.
  Je n'ai aucune source qui confirme qu'ils sont inchangés en 5.0. Le code
  ci-dessous se protège aussi avec un test sur la présence de `<body` dans le HTML,
  mais il faut **vérifier sur le site** que le bandeau s'affiche bien après activation.

#### Choix technique

- Pipeline **`affichage_final`** : il reçoit la page HTML complète juste avant son
  envoi, et seulement côté public (le privé a `affichage_final_prive`). Il couvre donc
  *toutes* les pages publiques, sans toucher aux squelettes (`squelettes/sommaire.html`,
  `inclure/header`…).
- Il s'exécute à chaque affichage, même quand la page vient du cache : le bandeau
  apparaît tout de suite après l'activation et disparaît dès qu'on désactive le plugin.
- La balise `<body>` du site a des attributs (`<body class="page_sommaire">`). On ne
  peut donc pas faire un `str_replace('<body>')`. Une expression régulière repère la
  première balise `<body …>` et insère le bandeau juste après.
- Les réponses non HTML (flux RSS, JSON, fichiers…) ne sont pas modifiées.
- Pas de base de données, donc ni `schema` ni `_administrations.php`.

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
	compatibilite="[4.2.0;5.*]"
>
	<nom>Acme bandeau</nom>
	<!-- Affiche un bandeau d'information en haut de chaque page publique -->

	<auteur>Acme</auteur>

	<licence lien="http://www.gnu.org/licenses/gpl-3.0.html">GNU/GPL</licence>

	<pipeline nom="affichage_final" inclure="acme_bandeau_pipelines.php" />
</paquet>
```

Sans attribut `action`, SPIP appelle la fonction `acme_bandeau_affichage_final()`
(préfixe + nom du pipeline), qu'il trouve dans le fichier indiqué par `inclure`.

#### `plugins/acme_bandeau/acme_bandeau_pipelines.php`

```php
<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Insère le bandeau de maintenance juste après la balise <body> des pages publiques.
 *
 * @param string $texte Page HTML complète
 * @return string Page HTML modifiée
 */
function acme_bandeau_affichage_final($texte) {
	// Pages HTML uniquement (pas de RSS, JSON, fichiers…)
	if (empty($GLOBALS['html']) || !is_string($texte) || stripos($texte, '<body') === false) {
		return $texte;
	}

	// Ne pas insérer le bandeau deux fois
	if (str_contains($texte, 'class="acme-bandeau"')) {
		return $texte;
	}

	$bandeau = '<div class="acme-bandeau">Maintenance prévue le 3 novembre</div>';

	// Première balise <body …> (avec ou sans attributs) : on insère le bandeau juste après
	return preg_replace('/<body\b[^>]*>/i', '$0' . $bandeau, $texte, 1);
}
```

Le pipeline doit toujours renvoyer la valeur reçue, modifiée ou non. Renvoyer `null`
casserait l'affichage de la page et les traitements des autres plugins.

#### Mise en place

1. Déposez le dossier `acme_bandeau/` dans `plugins/` (le dossier est vide pour l'instant).
2. Dans l'espace privé, ouvrez **Configuration → Gestion des plugins**, activez
   « Acme bandeau ».
3. Affichez une page publique et vérifiez dans le code source que
   `<div class="acme-bandeau">…</div>` suit bien `<body class="…">`. C'est le test
   indispensable, puisque la compatibilité avec SPIP 5.0 n'est pas documentée (voir plus haut).

#### Remarques

- **Style** : rien n'est imposé. Pour mettre le bandeau en forme, ajoutez une règle
  `.acme-bandeau { … }` à la CSS du site. On peut aussi en livrer une avec le plugin via
  le pipeline `insert_head_css`, si vous le souhaitez.
- **Retrait après le 3 novembre** : il suffit de désactiver le plugin. Le texte est en
  dur, comme demandé. Si l'annonce doit changer souvent, on pourra la rendre
  configurable (page de configuration) ou traduisible (fichier de langue).

---

## spip-plugins, cas 15 — plugin 4.4 + 5.0 — tirage 1

Voici les modifications pour ajouter le bandeau à acme.

#### Approche

Le bandeau doit apparaître sur **toutes les pages publiques**, quel que soit le squelette utilisé. Il faut donc agir sur le HTML final plutôt que sur un squelette. Le pipeline `affichage_final` reçoit la page complète juste avant son envoi au navigateur. Il est appelé côté public seulement ; l'espace privé a son propre pipeline, `affichage_final_prive`.

- On sort tout de suite si la réponse n'est pas du HTML (`$GLOBALS['html']`) : flux RSS, JSON, fichiers servis, etc.
- On lit le réglage `texte_bandeau` dans la meta `acme`, avec la fonction `acme_config()` déjà présente. Le formulaire enregistre ses champs dans le casier `acme` (`_meta_casier`), donc `lire_config('acme/texte_bandeau')` lirait la même valeur.
- Si le réglage est vide, ou ne contient que des espaces, on renvoie la page sans la modifier.
- On insère le `<div>` **juste après** la balise ouvrante `<body …>`. Une expression régulière gère une balise `body` qui porte des attributs (`<body class="page_article">`). Elle ne remplace que la première occurrence, et ne fait rien s'il n'y a pas de `<body>`.
- Le texte est échappé avec `htmlspecialchars()` avant l'insertion. Un réglage contenant `<`, `&` ou des guillemets ne casse donc pas la page et ne peut pas injecter de HTML.

#### 1. `paquet.xml` : déclarer le pipeline

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

J'ai monté la version à `1.3.0` (nouvelle fonctionnalité). Pas besoin d'attribut `schema` : on ne touche pas à la base.

#### 2. `acme_pipelines.php` : ajouter le gestionnaire

Ajoutez cette fonction à la fin du fichier. Le reste ne change pas.

```php
/**
 * Insère le bandeau acme juste après la balise <body> des pages publiques.
 *
 * Le texte vient du réglage texte_bandeau (formulaire configurer_acme).
 * Rien n'est inséré si ce réglage est vide.
 *
 * @param string $texte Page HTML complète
 * @return string
 */
function acme_affichage_final($texte) {
	if (empty($GLOBALS['html'])) {
		return $texte;
	}

	$config = acme_config();
	$bandeau = trim((string) ($config['texte_bandeau'] ?? ''));
	if ($bandeau === '') {
		return $texte;
	}

	$html = '<div class="acme-bandeau">'
		. htmlspecialchars($bandeau, ENT_QUOTES, $GLOBALS['meta']['charset'] ?? 'UTF-8')
		. '</div>';

	// Première balise <body …> seulement, attributs éventuels compris
	$resultat = preg_replace_callback(
		'/<body\b[^>]*>/i',
		static fn(array $m): string => $m[0] . "\n" . $html,
		$texte,
		1
	);

	return $resultat ?? $texte;
}
```

Détails :

- Le callback réinjecte la balise `<body …>` d'origine (`$m[0]`), avec ses attributs, puis place le bandeau juste derrière. Avec `preg_replace_callback`, le texte du bandeau n'est jamais interprété : il reste affiché tel quel, même s'il contient `$1` ou `\0`.
- `?? $texte` : si l'expression régulière échoue (`null`), on renvoie la page intacte.
- On renvoie toujours la valeur reçue, même sans modification, pour ne pas casser la chaîne des autres plugins branchés sur ce pipeline.

#### 3. Rien à changer dans le formulaire

`formulaires/configurer_acme.html` contient déjà le champ `texte_bandeau`, et `_meta_casier` vaut `acme`. Le réglage est donc déjà enregistré dans la meta `acme` au même endroit que `theme`.

#### Après la modification

- **Recharger la liste des plugins** : un nouveau pipeline déclaré dans `paquet.xml` n'est pris en compte qu'après une nouvelle lecture des plugins. Passez par la page de gestion des plugins dans l'espace privé, ou videz le cache.
- **Cache** : `affichage_final` s'exécute à chaque envoi de page, y compris pour une page servie depuis le cache. Une modification du texte dans la configuration s'affiche donc dès la page suivante, sans vider le cache des squelettes.
- **Style** : le `<div>` n'a pas de style. Si vous voulez un rendu par défaut, ajoutez une règle `.acme-bandeau { … }` dans une feuille CSS du plugin. Vous pouvez l'injecter comme `css/acme_sombre.css` via `acme_insert_head_css`, mais sans condition sur le thème.
- **SPIP 5** : le plugin se déclare compatible `[4.4.0;5.*]`. La documentation dont je dispose décrit SPIP 4.4 et ne parle pas de SPIP 5. Je n'ai donc pas pu vérifier que `affichage_final` et `$GLOBALS['html']` se comportent de la même façon en 5.x. Testez le bandeau sur un site SPIP 5 avant de publier cette version.

---

## spip-plugins, cas 15 — tirage 2

### Bandeau Acme après `<body>`

Le bandeau doit apparaître dans **toutes** les pages publiques, quel que soit le squelette. Le point d'accroche le plus adapté est donc le pipeline `affichage_final`. Il reçoit la page HTML complète juste avant son envoi au navigateur. Le pipeline `insert_head` ne convient pas ici, car il n'écrit que dans le `<head>`.

Le réglage `texte_bandeau` est déjà enregistré par `formulaires/configurer_acme.html` dans le casier `acme` (`_meta_casier`). Le plugin le lit déjà avec son helper `acme_config()`, que je réutilise.

#### 1. `paquet.xml` : déclarer le pipeline

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

J'ai monté la version à 1.3.0 parce que la fonctionnalité est nouvelle. Ce changement est facultatif. En revanche, après la modification de `paquet.xml`, il faut **recharger les plugins** (page « Gestion des plugins » ou vidage du cache) pour que SPIP prenne en compte le nouveau pipeline.

#### 2. `acme_pipelines.php` : ajouter le handler

Ajoutez cette fonction à la fin du fichier existant :

```php
/**
 * Insère le bandeau Acme juste après la balise <body> des pages publiques.
 *
 * Le texte vient du réglage texte_bandeau (formulaire configurer_acme).
 * Aucun bandeau n'est inséré si ce réglage est vide.
 */
function acme_affichage_final($texte) {
	// Seules les réponses HTML sont concernées (pas les flux RSS, JSON, etc.)
	if (empty($GLOBALS['html'])) {
		return $texte;
	}

	$config = acme_config();
	$bandeau = trim((string) ($config['texte_bandeau'] ?? ''));
	if ($bandeau === '') {
		return $texte;
	}

	// Le réglage est saisi en texte brut : on l'échappe avant de l'insérer dans le HTML
	$html = '<div class="acme-bandeau">' . spip_htmlspecialchars($bandeau) . '</div>';

	// Insertion juste après la balise ouvrante <body …>, même si elle porte des attributs
	// (class, dir, etc.), sur la première occurrence seulement
	return preg_replace('/<body\b[^>]*>/i', '$0' . "\n" . addcslashes($html, '\\$'), $texte, 1);
}
```

Points à noter :

- **`$GLOBALS['html']`** vaut `true` uniquement si la réponse est de type `text/html`. Ce test laisse intacts les flux RSS, JSON et autres contenus non HTML qui passent aussi par `affichage_final`.
- **Échappement** : le champ du formulaire est un simple `<input type="text">`, donc le texte est inséré tel quel, sans balises. `spip_htmlspecialchars()` empêche un `<` ou un `&` de casser la page ou d'injecter du HTML. Si vous voulez plus tard autoriser des raccourcis typographiques SPIP, remplacez-le par `typo($bandeau)`.
- **`addcslashes($html, '\\$')`** protège les `$` et les `\` éventuels du texte, que `preg_replace` interpréterait sinon comme des références de capture.
- **Balise `<body>` avec attributs** : les squelettes Z ou Dist produisent par exemple `<body class="page_article">`. Un simple `str_replace('<body>', …)` ne trouverait pas la balise, d'où la regex `<body\b[^>]*>`. Si la page n'a pas de `<body>` (fragment AJAX, par exemple), rien n'est inséré.
- **Cache** : `affichage_final` s'exécute à chaque affichage, après le cache des squelettes. Une modification du texte dans le formulaire de configuration est donc visible tout de suite, sans vider le cache.
- **Espace privé** : `affichage_final` agit sur les pages publiques. Le bandeau n'apparaît donc pas dans l'espace privé.

#### 3. (Optionnel) Un peu de style

Pour donner une apparence au bandeau, ajoutez une feuille `css/acme.css` et chargez-la toujours, pas seulement pour le thème sombre, dans le `acme_insert_head_css()` existant :

```php
function acme_insert_head_css($flux) {
	$flux .= '<link rel="stylesheet" href="' . find_in_path('css/acme.css') . '" type="text/css" />' . "\n";

	$config = acme_config();
	if (($config['theme'] ?? '') === 'sombre') {
		$flux .= '<link rel="stylesheet" href="' . find_in_path('css/acme_sombre.css') . '" type="text/css" />' . "\n";
	}

	return $flux;
}
```

```css
/* css/acme.css */
.acme-bandeau { padding: .5em 1em; background: #ffd; border-bottom: 1px solid #cc9; text-align: center; }
```

#### Remarque sur la compatibilité

Votre `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"`. Le code ci-dessus suit l'API SPIP 4.4 : pipeline `affichage_final`, `$GLOBALS['html']` et `spip_htmlspecialchars()`. Ma documentation porte sur SPIP 4.4 et ne décrit pas les éventuelles différences de SPIP 5. Avant de garder la borne `5.*`, testez donc le bandeau sur une instance SPIP 5, surtout la présence de `$GLOBALS['html']` et le comportement de `affichage_final`. Si vous ne pouvez pas faire ce test, restreignez la compatibilité à `[4.4.0;4.*]`.

---

## spip-plugins, cas 15 — tirage 3

Pour insérer du HTML juste après `<body>` sur toutes les pages publiques, le point d'accroche est le pipeline **`affichage_final`**. Il reçoit la page HTML complète juste avant son envoi au navigateur. Il ne s'exécute que côté public : l'espace privé a son propre pipeline, `affichage_final_prive`, donc le bandeau n'y apparaîtra pas.

Le réglage `texte_bandeau` est enregistré par `formulaire configurer_acme` dans le casier `acme` (champ caché `_meta_casier`), comme `theme`. Le plugin dispose déjà d'`acme_config()` pour lire ce casier. On la réutilise pour rester cohérent avec `acme_insert_head_css()`.

#### 1. `paquet.xml` : déclarer le pipeline

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

(J'ai aussi passé la version à 1.3.0 parce qu'il s'agit d'une nouvelle fonctionnalité. Ce n'est pas obligatoire, mais SPIP ne relit les pipelines déclarés dans `paquet.xml` qu'au recalcul du cache des plugins. Après la modification, il faut donc repasser par la page d'administration des plugins ou vider le cache.)

#### 2. `acme_pipelines.php` : ajouter la fonction

À ajouter à la fin du fichier, après `acme_insert_head_css()` :

```php
/**
 * Insère le bandeau Acme juste après la balise <body> des pages publiques.
 *
 * Le texte vient du réglage « texte_bandeau » (formulaire configurer_acme).
 * Aucun bandeau si le réglage est vide.
 *
 * @param string $texte Page HTML complète
 * @return string
 */
function acme_affichage_final($texte) {
	// Ne traiter que les réponses HTML (pas le CSS, le JS, les flux RSS…)
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

	// Insertion juste après la première balise <body …> (attributs éventuels compris)
	return preg_replace('/<body\b[^>]*>/i', '$0' . "\n" . $html, $texte, 1);
}
```

##### Points importants

- **`$GLOBALS['html']`** n'est vrai que pour une réponse `text/html`. Sans ce test, le pipeline modifierait aussi les squelettes qui produisent du CSS, du JS, du XML ou du JSON. Dans ce cas, `preg_replace` ne trouverait en général pas de `<body>`, mais le test évite aussi de lire la configuration pour rien.
- **Toujours renvoyer `$texte`**, même quand on ne le modifie pas. Renvoyer `null` casserait la chaîne pour les plugins suivants.
- **`<body>` avec attributs** : la plupart des squelettes ont `<body class="page_article …">` ou une balise du même genre. Un `str_replace('<body>', …)` échouerait dans ce cas, d'où l'expression régulière `<body\b[^>]*>`. Le `$0` réinsère la balise trouvée, et la limite `1` évite de toucher un éventuel second `<body` (par exemple dans un `<iframe srcdoc>`).
- **Échappement** : le texte vient d'un champ libre du formulaire de configuration. `htmlspecialchars()` l'insère tel quel, sans laisser passer de HTML. Si vous voulez autoriser du HTML ou des raccourcis SPIP dans le bandeau, il faudra un autre traitement, à décider explicitement.
- **Cache** : `affichage_final` s'exécute à chaque affichage, après le cache des squelettes. Une modification de `texte_bandeau` est donc visible tout de suite, sans vider le cache des pages. Lire la configuration ne coûte presque rien : les metas sont déjà en mémoire.

#### Style (facultatif)

Le plugin charge déjà une CSS via `insert_head_css`. Pour mettre en forme le bandeau, vous pouvez ajouter une règle `.acme-bandeau { … }` dans une feuille chargée de la même façon, par exemple une `css/acme.css` incluse sans condition à côté de `acme_sombre.css`.

#### Réserve sur la compatibilité

Le `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"`. Ma documentation de référence ne couvre que SPIP 4.x (elle a été écrite à partir de SPIP 4.4). Le code ci-dessus est valide pour SPIP 4.4. Je n'ai pas de documentation qui confirme que `affichage_final` et `$GLOBALS['html']` se comportent de la même façon sous SPIP 5. Il faut le vérifier sur un site SPIP 5 avant de considérer cette compatibilité comme acquise.

---

## spip-plugins, cas 16 — plugin 4.x

Pour insérer du HTML juste après `<body>` sur toutes les pages publiques, le point d'accroche adapté est le pipeline **`affichage_final`**. Il reçoit la page HTML complète juste avant l'envoi au navigateur. Il ne s'exécute que côté public : l'espace privé passe par `affichage_final_prive`. Il est aussi appelé à chaque affichage, après le cache des squelettes. Une modification du réglage `texte_bandeau` est donc visible tout de suite, sans vider le cache.

Le réglage est enregistré par `formulaires/configurer_acme.html` dans la meta `acme` (`_meta_casier` = `acme`). Je réutilise le helper `acme_config()` qui existe déjà dans le plugin. On pourrait aussi utiliser `lire_config('acme/texte_bandeau')`.

#### 1. `paquet.xml` : déclarer le pipeline

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

(J'ai passé la version à 1.3.0 parce que la fonctionnalité est nouvelle. Ce n'est pas obligatoire.)

#### 2. `acme_pipelines.php` : ajouter le handler

À ajouter à la fin du fichier, sans toucher à `acme_config()` ni à `acme_insert_head_css()` :

```php
/**
 * Insère le bandeau Acme juste après la balise <body> des pages publiques.
 *
 * Le texte vient du réglage « texte_bandeau » (formulaire configurer_acme).
 * Rien n'est inséré si le réglage est vide.
 */
function acme_affichage_final($texte) {
	// Ne traiter que les pages HTML (pas le CSS, le JS, les flux RSS…)
	if (empty($GLOBALS['html'])) {
		return $texte;
	}

	$config = acme_config();
	$texte_bandeau = trim((string) ($config['texte_bandeau'] ?? ''));
	if ($texte_bandeau === '') {
		return $texte;
	}

	$bandeau = '<div class="acme-bandeau">'
		. htmlspecialchars($texte_bandeau, ENT_QUOTES, 'UTF-8')
		. '</div>';

	// Insertion juste après la balise ouvrante <body ...> (attributs éventuels inclus),
	// une seule fois
	return preg_replace('/<body\b[^>]*>/i', '$0' . "\n" . $bandeau, $texte, 1);
}
```

Quelques points sur ce code :

- **`$GLOBALS['html']`** vaut `true` seulement quand la réponse est en `text/html`. Le test évite d'injecter le bandeau dans une feuille de style ou un flux XML produits par un squelette.
- **Expression régulière plutôt que `str_replace('<body>', …)`** : dans la plupart des squelettes, la balise a des attributs (`<body class="page_article">`, `dir="#LANG_DIR"`…). Un remplacement exact de `<body>` ne trouverait rien. `$0` reprend la balise d'origine telle quelle, et la limite à `1` empêche une double insertion.
- **Échappement** : le texte est saisi en back-office mais affiché tel quel sur toutes les pages publiques. `htmlspecialchars` évite qu'un caractère `<` ou `&` casse la page ou injecte du HTML. Si vous voulez autoriser du HTML ou des raccourcis typographiques SPIP dans le bandeau, remplacez-le par `typo($texte_bandeau)` et dites-le-moi.
- **Réglage vide** : on renvoie `$texte` sans le modifier. Un pipeline doit toujours renvoyer sa valeur d'entrée, sinon la chaîne des autres plugins est cassée.

#### Après la modification

- Comme `paquet.xml` déclare un nouveau pipeline, il faut **recharger la liste des plugins** pour que SPIP le prenne en compte : passez par la page « Gestion des plugins » de l'espace privé, ou videz le cache.
- Le style (`.acme-bandeau { … }`) peut aller dans une feuille CSS du plugin chargée par `insert_head_css`, comme celle du thème sombre.
- Il faudra peut-être ajouter dans `lang/acme_fr.php` la chaîne `label_texte_bandeau`, utilisée par le formulaire, si elle n'existe pas encore. Le dossier `lang/` ne fait pas partie des fichiers du plugin que j'ai consultés.
