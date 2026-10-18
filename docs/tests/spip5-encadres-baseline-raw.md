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

---

## spip-testing, cas 17 — plugin 5.0 (`composer.json` requiert `spip/framework`) — tirage 1

### Test d'intégration de `verifier()` pour `inscription_lettre`

Le plugin a déjà l'infrastructure d'intégration (`phpunit.xml` avec la suite `integration`, `tests/bootstrap_integration.php`, `scripts/install-spip-test.sh`, script Composer `tests-integration`). Il suffit donc d'ajouter **un seul fichier** de test dans `tests/integration/`.

#### Fichier livré : `tests/integration/InscriptionLettreVerifierTest.php`

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration de formulaires_inscription_lettre_verifier_dist().
 *
 * Tourne contre le SPIP réel installé dans vendor/spip/spip/
 * (bootstrap : tests/bootstrap_integration.php).
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Le fichier fait `return` si _ECRIRE_INC_VERSION n'est pas défini :
        // il ne se charge qu'une fois SPIP amorcé par le bootstrap d'intégration.
        include_once dirname(__DIR__, 2) . '/formulaires/inscription_lettre.php';
    }

    protected function setUp(): void
    {
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        set_request('email', null);
    }

    /**
     * Simule la saisie du champ email.
     * On renseigne $_POST ET le cache de set_request() : voir la note SPIP 5 plus bas.
     */
    private function saisirEmail(string $email): void
    {
        $_POST['email'] = $email;
        set_request('email', $email);
    }

    /**
     * Garde-fou : vérifie que la valeur simulée atteint bien Spip::input(),
     * la fonction que lit verifier(). Si ce test échoue, les autres ne prouvent rien.
     */
    public function testLaSaisieSimuleeAtteintSpipInput(): void
    {
        $this->saisirEmail('sonde@example.org');
        $this->assertSame('sonde@example.org', Spip::input('email'));
    }

    #[DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurLeChampEmail(string $email): void
    {
        $this->saisirEmail($email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs);
        $this->assertNotSame('', (string) $erreurs['email']);
        // L'erreur ne porte que sur le champ email
        $this->assertSame(['email'], array_keys($erreurs));
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'        => ['pas-une-adresse'],
            'sans domaine'        => ['jean@'],
            'sans partie locale'  => ['@example.org'],
            'espace interne'      => ['jean dupont@example.org'],
        ];
    }

    #[DataProvider('emailsValides')]
    public function testEmailValideNeProduitAucuneErreur(string $email): void
    {
        $this->saisirEmail($email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }

    public static function emailsValides(): array
    {
        return [
            'simple'                  => ['jean@example.org'],
            'avec point et sous-dom.' => ['jean.dupont@mail.example.org'],
            'espaces autour (trim)'   => ['  jean@example.org  '],
        ];
    }
}
```

Remarques sur les choix :

- **Classe de base** : `PHPUnit\Framework\TestCase` (on appelle directement une fonction PHP, pas un squelette ; `SquelettesTestCase` serait inutile ici).
- **Chargement du formulaire** : `include_once` du fichier du plugin. On pourrait aussi passer par `find_in_path('formulaires/inscription_lettre.php', '', true)` puisque le bootstrap ajoute la racine du plugin au chemin SPIP, mais l'inclusion directe n'a aucune dépendance au chemin.
- **Fournisseurs de données** : PHPUnit 13 (celui du `composer.json`) ne lit plus les annotations `@dataProvider` ; on utilise l'attribut `#[DataProvider]`.
- **Pas de nettoyage SQL** : `verifier()` n'écrit rien en base, donc pas de `tearDownAfterClass()`.
- J'ai volontairement évité de tester la chaîne vide : c'est un autre cas (`info_obligatoire`) que vous n'avez pas demandé, mais il s'ajoute en une ligne dans `emailsInvalides()` si vous le voulez.

#### Lancer le test

```bash
composer install              # si ce n'est pas déjà fait
composer install-spip-test    # première fois : installe SPIP + active acme dans vendor/spip/spip/
composer tests-integration
# ou seulement ce test :
vendor/bin/phpunit --colors --bootstrap tests/bootstrap_integration.php \
  tests/integration/InscriptionLettreVerifierTest.php
```

#### Deux points d'attention : votre plugin cible SPIP 5.0

Votre `paquet.xml` déclare `compatibilite="[5.0.0-dev;5.*]"` et le formulaire lit les saisies avec `Spip::input()`. La documentation de test dont je dispose est écrite pour SPIP 4.x : je n'ai pas pu y vérifier ces deux points, d'où les précautions ci-dessous.

1. **Injection de la saisie.** La doc décrit la simulation d'un POST par `$_POST[...]` ou `set_request()`, qui alimentent `_request()`. Elle ne dit pas si `Spip::input()` (API SPIP 5) lit la même source. C'est pour ça que le test renseigne les deux et commence par `testLaSaisieSimuleeAtteintSpipInput()` : **si ce test-là échoue**, la saisie simulée n'arrive pas jusqu'à `verifier()`. Les tests « email valide » échoueraient alors eux aussi (champ vide, donc erreur `info_obligatoire`). Il faudra trouver le mécanisme d'injection propre à SPIP 5, par exemple en construisant ou en remplaçant la requête courante. Je ne l'invente pas ici faute de documentation.

2. **Version de SPIP installée pour les tests.** `scripts/install-spip-test.sh` télécharge le cœur avec `core:telecharger ... -b 4.4`. Or votre plugin exige SPIP ≥ 5.0 : sur un SPIP 4.4, `plugins:activer acme` sera refusé (le script s'arrêtera sur `Failed: acme`), et `Spip::input()` n'existera pas. Avant de lancer les tests, changez la branche passée à `-b` pour celle de SPIP 5.0 (à vérifier selon l'état actuel du dépôt du cœur), puis supprimez `vendor/spip/spip/` pour forcer une réinstallation propre.

Je n'ai pas exécuté le test : il n'y a pas d'environnement SPIP installé dans le plugin (`vendor/` absent). Le premier `composer tests-integration` montrera donc si le point 1 pose problème.

---

## spip-testing, cas 17 — tirage 2

Voici le test d'intégration pour `formulaires_inscription_lettre_verifier_dist()`.

#### Fichier livré

##### `tests/integration/InscriptionLettreVerifierTest.php`

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration de verifier() du formulaire inscription_lettre.
 *
 * Tier intégration : verifier() appelle include_spip('inc/filtres'),
 * email_valide() et _T(), qui demandent un SPIP réel
 * (bootstrap tests/bootstrap_integration.php).
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Le formulaire est livré par le plugin (formulaires/ est dans le chemin SPIP
        // grâce à _chemin() dans le bootstrap) : on le charge via find_in_path.
        find_in_path('formulaires/inscription_lettre.php', '', true);
    }

    protected function setUp(): void
    {
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        set_request('email', null);
    }

    public function testFormulaireEstCharge(): void
    {
        $this->assertTrue(
            function_exists('formulaires_inscription_lettre_verifier_dist'),
            'formulaires/inscription_lettre.php non trouvé dans le chemin SPIP'
        );
    }

    /**
     * @dataProvider emailsInvalides
     */
    public function testEmailInvalideProduitUneErreurSurEmail(string $email): void
    {
        $_POST['email'] = $email;
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs);
        $this->assertNotSame('', (string) $erreurs['email']);
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'   => ['pas-une-adresse'],
            'sans domaine'   => ['jean@'],
            'sans partie locale' => ['@example.org'],
        ];
    }

    public function testEmailValideNeProduitAucuneErreur(): void
    {
        $_POST['email'] = 'jean.dupont@example.org';
        set_request('email', 'jean.dupont@example.org');

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }
}
```

Le fichier se place dans `tests/integration/` avec le suffixe `Test.php` :
la suite `integration` de `phpunit.xml` le trouve sans autre réglage.

#### Lancer le test

```bash
composer install
composer install-spip-test   # première fois seulement
composer tests-integration
# ou directement :
vendor/bin/phpunit --colors --testsuite integration --bootstrap tests/bootstrap_integration.php \
  --filter InscriptionLettreVerifierTest
```

#### Points d'attention avant de lancer le test

1. **Version de SPIP installée pour les tests.** Le plugin vise SPIP 5.0
   (`compatibilite="[5.0.0-dev;5.*]"` dans `paquet.xml`, `spip/framework: ^5.x-dev`
   dans `composer.json`, et `verifier()` utilise `Spip::input()`). Or
   `scripts/install-spip-test.sh` télécharge le cœur avec `core:telecharger ... -b 4.4`.
   Sur une 4.4, le plugin ne s'active pas (compatibilité) et la classe `Spip`
   n'existe pas : le test échouera avant toute assertion. Il faut faire pointer
   le script vers une branche 5.0 du cœur. Je ne connais pas avec certitude le nom
   de branche à passer à `-b` ; vérifiez-le avant de modifier le script.

2. **Injection de la saisie.** La documentation dont je dispose (écrite pour
   SPIP 4.x) montre comment simuler une saisie pour `_request()` : `$_POST` ou
   `set_request()`. Le formulaire lit l'email avec `Spip::input('email')`, une API
   SPIP 5.0 que cette documentation ne couvre pas. Le test renseigne donc les deux
   (`$_POST` et `set_request()`), mais je ne peux pas garantir que `Spip::input()`
   les lise en SPIP 5.0 (par exemple si la requête est figée au démarrage).
   Si le cas « email valide » échoue avec une erreur `info_obligatoire`, c'est que
   la valeur n'arrive pas à `Spip::input()` : il faudra alors injecter la saisie
   par le mécanisme propre à SPIP 5.0.

3. **Plugin actif.** Le bootstrap appelle `_chemin()` sur la racine du plugin :
   `find_in_path('formulaires/inscription_lettre.php', '', true)` doit donc trouver
   le formulaire. Le test `testFormulaireEstCharge()` sert de garde-fou : s'il
   échoue, c'est un problème d'environnement, pas de `verifier()`.

4. **Pas de nettoyage en base.** `verifier()` n'écrit rien. Je n'ai pas testé
   `traiter()`, qui écrit dans `spip_inscrits` ; cette table n'est d'ailleurs déclarée
   nulle part dans le plugin.

Je n'ai pas exécuté ce test : je n'avais pas d'environnement SPIP installé
(`vendor/` absent).

---

## spip-testing, cas 18 — plugin 4.4 + 5.0 — tirage 1

Voici le test d'intégration pour `formulaires_inscription_lettre_verifier_dist()`.

Il va dans le tier **intégration** : `verifier()` appelle `include_spip('inc/filtres')`, `email_valide()` et `_T()`, qui sont de vraies fonctions de SPIP. Le test tourne donc sur l'instance installée dans `vendor/spip/spip/` par `composer install-spip-test`, avec le `tests/bootstrap_integration.php` déjà présent dans le plugin. Je n'ai rien changé à la configuration : `phpunit.xml` déclare déjà la suite `integration` (`tests/integration`, suffixe `Test.php`) et `composer tests-integration` la lance avec le bon bootstrap.

#### Fichier livré

##### `tests/integration/InscriptionLettreVerifierTest.php`

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration de verifier() du formulaire CVT inscription_lettre.
 *
 * S'exécute sur le vrai SPIP (vendor/spip/spip/) : email_valide() et _T()
 * sont les fonctions du cœur, pas des mocks.
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Le fichier du plugin. _ECRIRE_INC_VERSION est défini par le bootstrap
        // d'intégration, sinon le fichier ferait un return immédiat.
        include_once dirname(__DIR__, 2) . '/formulaires/inscription_lettre.php';
    }

    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'      => ['jean.dupont.example.org'],
            'sans domaine'      => ['jean.dupont@'],
            'sans partie locale'=> ['@example.org'],
            'espace interne'    => ['jean dupont@example.org'],
            'double arobase'    => ['jean@@example.org'],
        ];
    }

    #[DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurLeChampEmail(string $email): void
    {
        $_POST['nom'] = 'Jean Dupont';
        $_POST['email'] = $email;

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs, "« $email » aurait dû être refusée");
        $this->assertIsString($erreurs['email']);
        $this->assertNotSame('', trim($erreurs['email']), 'Le message d\'erreur est vide');
        // Erreur sur le champ email uniquement
        $this->assertSame(['email'], array_keys($erreurs));
    }

    public function testEmailValideNeProduitAucuneErreur(): void
    {
        $_POST['nom'] = 'Jean Dupont';
        $_POST['email'] = 'jean.dupont@example.org';

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }

    public function testEmailValideEntoureDEspacesEstAccepte(): void
    {
        // verifier() fait un trim() avant email_valide()
        $_POST['email'] = '  jean.dupont@example.org  ';

        $this->assertSame([], formulaires_inscription_lettre_verifier_dist());
    }

    public function testEmailVideProduitUneErreurSurLeChampEmail(): void
    {
        $_POST['email'] = '';

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertArrayHasKey('email', $erreurs);
        $this->assertNotSame('', trim((string) $erreurs['email']));
    }
}
```

Choix faits :

- **Données simulées par `$_POST`**, car `_request()` lit `$_GET` / `$_POST`. Les deux tableaux sont vidés dans `setUp()` et `tearDown()`, ce qui évite qu'un cas déborde sur le suivant.
- **Chargement par `include_once`** du fichier du plugin. Le bootstrap ajoute bien la racine du plugin au chemin SPIP (`_chemin(dirname(__DIR__))`), donc `find_in_path('formulaires/inscription_lettre.php', '', true)` marcherait aussi. Le chemin direct garantit qu'on teste ce fichier-là et pas une surcharge trouvée ailleurs dans le path.
- **Message d'erreur** : le test vérifie qu'il y a une erreur non vide sur `email`, sans comparer son texte. Le libellé vient de `_T('form_prop_indiquer_email')` et dépend de la langue et des chaînes du cœur. Si vous voulez figer le libellé, ajoutez `$this->assertSame(_T('form_prop_indiquer_email'), $erreurs['email']);`.
- Le test vérifie aussi que l'erreur ne concerne que `email` (`array_keys($erreurs) === ['email']`), puisque `nom` n'est pas contrôlé.
- J'ai ajouté deux cas proches de la demande : une adresse valide entourée d'espaces, qui doit passer grâce au `trim()`, et un champ vide, qui passe par la branche `info_obligatoire`.
- Le data provider utilise l'attribut `#[DataProvider]`. C'est la syntaxe attendue par PHPUnit 13 (`^13.0` dans votre `composer.json`) : l'annotation `@dataProvider` n'est plus lue.

#### Lancer le test

```bash
composer install              # si ce n'est pas déjà fait
composer install-spip-test    # première fois : SPIP 4.4 + SQLite + activation d'acme
composer tests-integration
# ou seulement ce fichier :
vendor/bin/phpunit --colors --bootstrap tests/bootstrap_integration.php \
    tests/integration/InscriptionLettreVerifierTest.php
```

Résultat attendu : 8 tests (5 cas invalides du provider + 3 autres), tous verts.

#### Points à noter

1. **Exemples à vérifier en premier en cas d'échec.** Si un cas invalide passe, regardez d'abord l'exemple : j'ai choisi des adresses que `email_valide()` devrait refuser, mais le dernier mot revient à SPIP, et `jean@@example.org` ou l'espace interne sont les plus sensibles à son implémentation. Retirez un cas seulement si SPIP l'accepte volontairement. Sinon, c'est un vrai défaut de validation.
2. **Version de SPIP testée.** `scripts/install-spip-test.sh` installe SPIP **4.4** (`-b 4.4`), alors que `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"`. Ce test ne valide donc que la 4.4. Pour couvrir SPIP 5, il faut une seconde instance de test en 5.x. Ma documentation ne détaille pas les différences d'API de la 5.0 : je ne peux pas garantir que la simulation par `$_POST` / `_request()` y fonctionne de la même façon.
3. **Tests unitaires.** `phpunit.xml` pointe vers `tests/bootstrap.php`, qui n'existe pas dans le plugin, donc `composer tests-unit` échouera. Ça ne gêne pas ce test : `tests-integration` passe `--bootstrap tests/bootstrap_integration.php`, qui remplace celui de `phpunit.xml`. Créez ce fichier de mocks seulement si vous ajoutez des tests dans `tests/unit/`.
4. **`traiter()` n'est pas testé ici.** Il insère dans `spip_inscrits`, et le plugin n'a ni `base/` ni `_administrations.php` qui déclarerait cette table. Un test de `traiter()` échouerait donc tant que le schéma n'existe pas.

---

## spip-testing, cas 18 — tirage 2

Voici le test d'intégration pour `verifier()` du formulaire `inscription_lettre`.

#### Ce qui est déjà en place dans le plugin

- `composer.json` contient les scripts `install-spip-test` et `tests-integration`.
- `phpunit.xml` déclare la suite `integration`, qui exécute `tests/integration/*Test.php`.
- `tests/bootstrap_integration.php` charge un vrai SPIP (`vendor/spip/spip/`), ajoute la racine du plugin au chemin SPIP et active les plugins.

Il suffit donc d'ajouter **un seul fichier**. Aucun autre fichier n'est à modifier.

#### Pourquoi un test d'intégration

`verifier()` appelle `_request()`, `include_spip('inc/filtres')`, `email_valide()` et `_T()`. Ces fonctions viennent du cœur de SPIP. Le niveau intégration utilise les vraies fonctions, sans les simuler, et `TestCase` suffit puisqu'on appelle directement une fonction PHP. `SquelettesTestCase` ne sert que pour rendre des squelettes.

#### Fichier à créer : `tests/integration/InscriptionLettreVerifierTest.php`

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration de formulaires_inscription_lettre_verifier_dist().
 *
 * Exécutés sur un vrai SPIP (tests/bootstrap_integration.php) :
 * _request(), email_valide() et _T() sont les fonctions du cœur.
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Le formulaire est livré par le plugin : on le charge par son chemin réel.
        include_once dirname(__DIR__, 2) . '/formulaires/inscription_lettre.php';
    }

    protected function setUp(): void
    {
        $this->viderRequete();
    }

    protected function tearDown(): void
    {
        $this->viderRequete();
    }

    private function viderRequete(): void
    {
        $_POST = [];
        $_GET = [];
        // set_request() avec null retire la valeur que _request() pourrait encore renvoyer
        set_request('email', null);
        set_request('nom', null);
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'       => ['jean.dupont.example.org'],
            'sans domaine'       => ['jean@'],
            'sans partie locale' => ['@example.org'],
            'avec espace'        => ['jean dupont@example.org'],
            'texte libre'        => ['pas un email'],
        ];
    }

    #[DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurLeChampEmail(string $email): void
    {
        set_request('nom', 'Jean Dupont');
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs, "« $email » devrait être refusé");
        $this->assertSame(_T('form_prop_indiquer_email'), $erreurs['email']);
        // L'erreur ne concerne que le champ email
        $this->assertSame(['email'], array_keys($erreurs));
    }

    public static function emailsValides(): array
    {
        return [
            'simple'              => ['jean@example.org'],
            'avec point et tiret' => ['jean.dupont-test@sous.example.org'],
            'espaces autour'      => ['  jean@example.org  '], // verifier() applique trim()
        ];
    }

    #[DataProvider('emailsValides')]
    public function testEmailValideNeProduitAucuneErreur(string $email): void
    {
        set_request('nom', 'Jean Dupont');
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertSame([], $erreurs);
    }
}
```

##### Notes

- **Chargement du formulaire** : `include_once` sur le fichier réel du plugin (`formulaires/inscription_lettre.php`), dont la garde `_ECRIRE_INC_VERSION` passe parce que le bootstrap d'intégration a chargé `ecrire/inc_version.php`. `find_in_path('formulaires/inscription_lettre.php', '', true)` fonctionnerait aussi, puisque le bootstrap ajoute la racine du plugin au chemin SPIP. `include_once` est plus direct.
- **Données de requête** : `set_request()` est le moyen fourni par SPIP au niveau intégration pour alimenter `_request()`. `$_POST` et `$_GET` sont vidés avant et après chaque test pour qu'une valeur ne passe pas d'un cas à l'autre.
- **Message attendu** : je compare le texte de l'erreur à `_T('form_prop_indiquer_email')`, la chaîne que retourne le code, plutôt qu'à un texte en dur, ce qui le rend indépendant de la langue. Si vous ne voulez vérifier que la présence d'une erreur sur le champ, retirez l'`assertSame` correspondant.
- **Hors périmètre demandé** : le cas « email vide » (erreur `info_obligatoire`) n'est pas couvert. On peut l'ajouter comme troisième test si vous le souhaitez.

#### Lancer le test

```bash
composer install            # PHPUnit, spip/tests, spip/spip-cli
composer install-spip-test  # télécharge et installe SPIP dans vendor/spip/spip (SQLite3), active acme
composer tests-integration  # exécute tests/integration/
```

Pour ne lancer que ce fichier :

```bash
vendor/bin/phpunit --colors --bootstrap tests/bootstrap_integration.php \
  tests/integration/InscriptionLettreVerifierTest.php
```

Je n'ai pas exécuté ces commandes : le résultat (5 cas invalides et 3 valides, 8 tests au total) reste à confirmer sur votre poste.

Remarque : `scripts/install-spip-test.sh` installe SPIP **4.4** (`core:telecharger -b 4.4`), alors que `paquet.xml` déclare `compatibilite="[4.4.0;5.*]"`. Ce test valide donc le formulaire sur SPIP 4.4. Pour tester aussi SPIP 5, il faut une seconde installation de test sur une branche 5.x.

---

## spip-testing, cas 19 — plugin 4.x

Voici le test d'intégration pour `formulaires_inscription_lettre_verifier_dist()`.

Le plugin est déjà équipé pour le niveau intégration : `composer.json` (scripts `install-spip-test` et `tests-integration`), `scripts/install-spip-test.sh` (SPIP 4.4 + SQLite3, préfixe `acme`) et `tests/bootstrap_integration.php`, qui ajoute la racine du plugin au chemin SPIP avec `_chemin()`. Il suffit donc d'ajouter **un seul fichier** dans `tests/integration/`.

#### Fichier livré : `tests/integration/InscriptionLettreVerifierTest.php`

```php
<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration de verifier() du formulaire CVT inscription_lettre.
 *
 * Exécuté avec le vrai SPIP (tests/bootstrap_integration.php) : _request(),
 * email_valide() et _T() sont les fonctions réelles du cœur, sans mock.
 */
final class InscriptionLettreVerifierTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Charge le fichier du formulaire via le chemin SPIP
        // (la racine du plugin y est ajoutée par le bootstrap d'intégration).
        $fichier = find_in_path('formulaires/inscription_lettre.php', '', true);
        self::assertNotEmpty($fichier, 'formulaires/inscription_lettre.php introuvable dans le chemin SPIP');
        self::assertTrue(
            function_exists('formulaires_inscription_lettre_verifier_dist'),
            'formulaires_inscription_lettre_verifier_dist() non définie'
        );
    }

    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public static function emailsInvalides(): array
    {
        return [
            'sans arobase'       => ['pas-une-adresse'],
            'sans domaine'       => ['jean@'],
            'sans partie locale' => ['@example.org'],
            'espace interne'     => ['jean dupont@example.org'],
        ];
    }

    public static function emailsValides(): array
    {
        return [
            'simple'                   => ['jean@example.org'],
            'sous-domaine et point'    => ['jean.dupont@mail.example.org'],
            'espaces autour (trim)'    => ['  jean@example.org  '],
        ];
    }

    #[DataProvider('emailsInvalides')]
    public function testEmailInvalideProduitUneErreurSurLeChampEmail(string $email): void
    {
        set_request('email', $email);

        $erreurs = formulaires_inscription_lettre_verifier_dist();

        $this->assertIsArray($erreurs);
        $this->assertArrayHasKey('email', $erreurs, "Aucune erreur pour l'adresse invalide « $email »");
        $this->assertNotSame('', trim((string) $erreurs['email']), 'Le message d\'erreur est vide');
        // L'erreur porte uniquement sur le champ email, pas de message_erreur global
        $this->assertSame(['email'], array_keys($erreurs));
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

##### Points de conception

- **Niveau intégration, `TestCase` simple** : on appelle directement une fonction PHP ; `SquelettesTestCase` ne sert que pour le rendu de squelettes.
- **Chargement par `find_in_path(..., true)`** plutôt que de compter sur un chargement automatique : le formulaire vit dans `formulaires/` du plugin, que le bootstrap rend accessible dans le chemin SPIP. Le garde `_ECRIRE_INC_VERSION` en tête du fichier est satisfait puisque SPIP est réellement chargé.
- **Données de requête** : `set_request()` (helper SPIP) alimente ce que lit `_request('email')`. `$_GET`/`$_POST` sont vidés avant et après chaque test pour éviter toute fuite d'état entre cas.
- **Pas d'assertion sur le texte exact du message** : il vient de `_T('form_prop_indiquer_email')` et dépend de la langue du site ; on vérifie la présence d'une erreur non vide sur la clé `email`.
- **Pas de nettoyage SQL** : `verifier()` n'écrit rien en base (seul `traiter()` fait un `sql_insertq`).
- Le cas « champ vide » (`_T('info_obligatoire')`) n'est pas demandé ; il s'ajoute en une ligne au fournisseur `emailsInvalides` (`'vide' => ['']`) si vous le souhaitez.

#### Lancer le test

```bash
composer install              # PHPUnit, spip/tests, spip-cli
composer install-spip-test    # première fois : SPIP 4.4 + SQLite3 dans vendor/spip/spip/, active acme
composer tests-integration
# ou ciblé :
vendor/bin/phpunit --colors --testsuite integration \
  --bootstrap tests/bootstrap_integration.php \
  --filter InscriptionLettreVerifierTest
```

Résultat attendu : 7 tests (4 adresses invalides, 3 valides), tous verts.

#### À noter (sans changer le livrable)

- `phpunit.xml` déclare `bootstrap="tests/bootstrap.php"`, fichier absent du plugin. Ce n'est pas gênant ici, car `tests-integration` force `--bootstrap tests/bootstrap_integration.php`, mais `composer tests-unit` échouera tant que ce bootstrap de mocks n'existe pas.
- Avant de conclure, vérifiez que l'environnement est réellement prêt plutôt que de vous fier au message final du script : `vendor/bin/spip plugins:lister | grep -E "^[[:space:]]*acme[[:space:]]"`.
- Le test vise SPIP 4.4 (`compatibilite="[4.2.0;4.4.*]"` dans `paquet.xml`, `-b 4.4` dans le script). Il n'a pas été exécuté de mon côté : je n'ai pas d'instance SPIP installée pour ce plugin.

---

## plugin-escal, cas 20 — site avec `bin/spip` — tirage 1

Avant de vous donner la recette, je dois vous signaler un blocage : **ce site ressemble à un SPIP 5.0, et Escal n'est pas déclaré compatible avec SPIP 5.**

#### Ce que montre le site

Le site contient `bin/spip`, `plugins/` et `squelettes/`, mais :

- `bin/spip` n'est pas le spip-cli classique. C'est la console intégrée au cœur, qui démarre `SpipLeague\Component\Kernel\Kernel::boot()` puis `SpipLeague\Component\Console\Application` sur `ecrire/inc_version.php`. C'est la structure d'un SPIP 5.0 installé avec Composer.
- `vendor/`, `ecrire/` et `config/connect.php` sont absents. Les dépendances Composer ne sont donc pas installées et le site n'est pas encore installé. En l'état, `bin/spip` s'arrête sur `Dependencies are missing. Try running "composer install".`

#### Le blocage : compatibilité d'Escal

La dernière version d'Escal que je connais (5.6.0, schéma 1.0.17) déclare la compatibilité `[4.1.0;4.*]`. SPIP 5.0 sort de cet intervalle. SVP refusera donc de l'activer, ou l'activation laissera un site dans un état non pris en charge. Je ne vous donne pas une suite de commandes « installe et active » pour ce site : elle échouerait, ou elle donnerait l'impression d'avoir marché sans que ce soit vrai.

De plus, la recette que je connais a été vérifiée avec **spip-cli 2.0.1 sur SPIP 4.4.14** (`plugins:svp:depoter`, `plugins:svp:telecharger`, `plugins:activer`, `plugins:maj:bdd`, `config:lire`/`config:ecrire`). Je ne peux pas garantir que la console de SPIP 5 (`bin/spip`) propose ces mêmes commandes.

#### À vérifier d'abord

Depuis la racine du site :

```bash
composer install                  # sans vendor/, bin/spip ne démarre pas
php bin/spip list                 # liste des commandes réellement disponibles
composer show spip/ecrire         # version exacte du cœur installé
```

Si `composer show spip/ecrire` indique une version 5.x, il y a trois options :

1. **Installer Escal sur un site SPIP 4.4** (recommandé si vous avez besoin d'Escal maintenant).
2. **Attendre une version d'Escal compatible avec SPIP 5** : vérifiez la balise `<paquet … compatibilite="…">` de la version publiée sur plugins.spip.net.
3. **Tester sur une copie jetable.** Ne forcez jamais la compatibilité sur un site réel.

#### Si le site est finalement un SPIP 4.4 (avec spip-cli)

Voici la recette vérifiée. Lancez-la depuis la racine SPIP, c'est-à-dire le dossier qui contient `config/connect.php`, une fois le site installé :

```bash
# 0. Vérifier que spip-cli intègre le correctif MR !91 (sinon les téléchargements échouent sans le dire)
grep -q 'UPPER(pl.prefixe) = UPPER' src/Command/PluginsSvpTelecharger.php \
  && echo "patché (MR!91)" || echo "NON patché — les téléchargements échoueront en silence"
#   (à lancer dans le répertoire d'installation de spip-cli)

# 1. Dossier de destination de SVP (core:preparer ne le crée pas)
mkdir -p plugins/auto && chmod u+rwx plugins/auto

# 2. Dépôt officiel (une seule fois)
spip plugins:svp:depoter https://plugins.spip.net/depots/principal.xml

# 3. Téléchargement d'Escal et de ses dépendances
spip plugins:svp:telecharger saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y

# 4. Activation, dépendances d'abord
spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y

# 5. Mises à jour de base, contrôle, cache
spip plugins:maj:bdd                  # doit afficher « Installation du plugin Escal … MAJ 1.0.17 »
spip plugins:lister --short           # chaque préfixe ci-dessus doit apparaître (+ calendriermini)
spip config:lire escal_base_version --json   # ne doit pas valoir null
spip cache:vider
```

Pour vérifier le résultat, ne vous fiez pas à la sortie de `plugins:svp:telecharger` : sans le correctif, elle annonce des succès même quand rien n'a été téléchargé. Seul `plugins:lister --short` fait foi. Si votre spip-cli n'a pas le correctif, déposez les plugins à la main dans `plugins/`, puis utilisez seulement `plugins:activer`.

Une installation en ligne de commande laisse aussi les trois articles de démarrage (*Édito*, *Accès direct*, *Mentions légales*) au statut `prepa`, parce qu'aucun auteur n'est authentifié. Pour les publier :

```bash
spip php:eval 'include_spip("action/editer_objet"); include_spip("base/abstract_sql");
$GLOBALS["visiteur_session"] = sql_fetsel("*", "spip_auteurs", "statut=" . sql_quote("0minirezo"));
$GLOBALS["visiteur_session"]["webmestre"] = "oui";
foreach (sql_allfetsel("id_article", "spip_articles", "statut=" . sql_quote("prepa")) as $x) {
  objet_instituer("article", $x["id_article"], ["statut" => "publie"]);
}'
spip cache:vider
```

Ensuite, configurez Escal dans l'espace privé, menu **Squelettes → Escal** (`?exec=configurer_escal`). Ne modifiez jamais les fichiers du plugin. Placez vos personnalisations dans `squelettes/` (`styles/perso.css`, `inclusions/inc-*.html`).

**En résumé :** commencez par lancer `composer install` puis `composer show spip/ecrire` et envoyez-moi la version. Si c'est un SPIP 5.0, Escal 5.6.0 ne s'y installe pas en l'état. Il faudra un site SPIP 4.4 ou une version d'Escal compatible avec SPIP 5.

---

## plugin-escal, cas 20 — tirage 2

Avant de vous donner les commandes, il y a un problème : **ce site semble tourner sous SPIP 5, et Escal n'est pas déclaré compatible avec SPIP 5.**

#### Ce que j'ai vu sur le site

- Le site a un exécutable `bin/spip` qui démarre `SpipLeague\Component\Kernel\Kernel` et `SpipLeague\Component\Console\Application`. C'est la console intégrée de SPIP 5, installée avec Composer. Sur un SPIP 4.x, on utilise plutôt la commande globale `spip` du paquet spip-cli.
- `plugins/` et `squelettes/` sont vides (juste un `.gitkeep`). Escal n'est donc pas encore présent.
- Dans la copie que j'ai sous les yeux, il n'y a ni `vendor/`, ni `ecrire/`, ni `config/connect.php`. Tel quel, `bin/spip` s'arrête avec « Dependencies are missing. Try running "composer install". » Il faut d'abord lancer `composer install` et faire l'installation de SPIP (base de données) avant toute commande sur les plugins.

#### Pourquoi ça bloque

Escal 5.6.0, la version actuelle, déclare la compatibilité **`[4.1.0;4.*]`**, donc SPIP 4.1 à 4.x seulement. SPIP refuse d'activer un plugin dont la plage de compatibilité exclut la version du cœur. Sur ce site, les commandes `plugins:activer … escal` échoueraient, ou Escal resterait inactif.

Je ne vous donne donc pas de « recette qui marche » pour ce site : je ne peux pas la garantir. La procédure que je connais a été vérifiée avec **spip-cli 2.0.1 sur SPIP 4.4.14**. Je n'ai aucune garantie que la console `bin/spip` de SPIP 5 propose les mêmes commandes (`plugins:svp:*`, `config:*`…).

#### Ce que je vous propose

1. **Vérifier la version du site** (après `composer install`) :

   ```bash
   bin/spip list                 # liste des commandes réellement disponibles
   bin/spip core:info 2>/dev/null || grep -n "spip_version_branche" ecrire/inc_version.php
   ```

   Si c'est bien SPIP 5.x, n'installez pas Escal sur ce site. Deux options :
   - attendre (ou demander à l'auteur) une version d'Escal compatible SPIP 5. Vérifiez la balise `<spip compatibilite="…">` du `paquet.xml` de la version téléchargée ;
   - utiliser un site en **SPIP 4.4**.

2. **Sur un SPIP 4.4 avec spip-cli**, voici la recette, à lancer depuis la racine du site (le dossier qui contient `config/connect.php`) :

   ```bash
   # 0. SVP décompresse dans plugins/auto/, que l'installation ne crée pas
   mkdir -p plugins/auto && chmod u+rwx plugins/auto

   # 0bis. spip-cli 2.0.1 non patché affiche des succès sans rien télécharger (MR !91)
   grep -q 'UPPER(pl.prefixe) = UPPER' src/Command/PluginsSvpTelecharger.php \
     && echo "patché (MR!91)" || echo "NON patché — téléchargez les plugins à la main dans plugins/"
   #   (à lancer dans le dossier de spip-cli)

   # 1. dépôt officiel (une seule fois)
   spip plugins:svp:depoter https://plugins.spip.net/depots/principal.xml

   # 2. téléchargement d'Escal et de ses dépendances (calendriermini suit : 11 paquets)
   spip plugins:svp:telecharger saisies verifier yaml agenda fullcalendarcompat \
        svpstats nospam facteur tri_par_rubrique aide escal -y

   # 3. activation, dépendances d'abord
   spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
        svpstats nospam facteur tri_par_rubrique aide escal -y

   # 4. mises à jour de base et vérification
   spip plugins:maj:bdd                 # « Installation du plugin Escal … MAJ 1.0.17 »
   spip plugins:lister --short          # tous les préfixes ci-dessus doivent apparaître
   spip config:lire escal_base_version --json   # ne doit pas valoir null
   spip cache:vider
   ```

   Ne vous fiez pas à ce qu'affiche `plugins:svp:telecharger` : contrôlez toujours avec `plugins:lister --short`.

   Une fois la recette passée, il faut aussi **publier les contenus de départ**. Lancée depuis la ligne de commande, l'installation laisse les articles *Édito*, *Accès direct* et *Mentions légales* en préparation, faute d'auteur connecté. Le site paraît alors cassé, avec des blocs vides :

   ```bash
   spip php:eval 'include_spip("action/editer_objet"); include_spip("base/abstract_sql");
   $GLOBALS["visiteur_session"] = sql_fetsel("*", "spip_auteurs", "statut=" . sql_quote("0minirezo"));
   $GLOBALS["visiteur_session"]["webmestre"] = "oui";
   foreach (sql_allfetsel("id_article", "spip_articles", "statut=" . sql_quote("prepa")) as $x) {
     objet_instituer("article", $x["id_article"], ["statut" => "publie"]);
   }'
   ```

   Ensuite, la configuration se fait dans l'espace privé : **Squelettes → Escal** (`?exec=configurer_escal`).

Dites-moi la version exacte de SPIP qu'affiche `bin/spip` (ou le contenu de `ecrire/inc_version.php`). Si c'est bien du 5.x, je vous aide à choisir entre migrer vers un SPIP 4.4 et attendre une version d'Escal compatible.

---

## plugin-escal, cas 21 — site passé en 5.0 (`vendor/spip/framework/`, Escal 5.6.2 présent) — tirage 1

### Escal ne s'affiche plus après la mise à jour : pourquoi la réactivation ne marchera pas

#### Ce que j'ai trouvé dans le site

1. **Escal est toujours présent sur le disque** : `plugins/auto/escal/v5.6.2/paquet.xml`. Il s'agit de la version 5.6.2, avec le schéma `1.0.17`.
2. **Escal 5.6.2 n'accepte que SPIP 4** :

   ```xml
   <paquet prefix="escal" categorie="squelette" version="5.6.2" schema="1.0.17"
           compatibilite="[4.1.0;4.*]" ...>
   ```

3. **Le site ne tourne plus sur SPIP 4.x.** On trouve `vendor/spip/framework/composer.json`, le paquet `spip/framework`. Il exige `"php": "^8.4"` et les bibliothèques `spip-league/*` : `kernel`, `http-kernel`, `plugin-manager`, `pipeline-bridge`, etc. Il est aussi en `"minimum-stability": "dev"`. Ce paquet n'existe pas dans une installation SPIP 4.4. Votre mise à jour a donc fait passer le site à la nouvelle génération de SPIP (5.x, encore en développement), pas à une version 4.4.x plus récente.

Le look a disparu à cause de ce changement de version. Quand SPIP charge les plugins, il ignore ceux dont l'intervalle `compatibilite` ne contient pas sa version. Avec `[4.1.0;4.*]`, Escal est écarté. Le site retombe alors sur les squelettes par défaut de SPIP, ce qui donne la page d'accueil « nue » que vous voyez. Vos réglages (meta `escal`, casier `escal/config`) et vos contenus ne sont pas perdus. Ils ne sont simplement plus utilisés.

**Une commande de réactivation ne suffira donc pas.** `spip plugins:activer escal -y` remettrait Escal dans la liste des plugins actifs, mais le contrôle de compatibilité l'écarterait de nouveau. Ne modifiez pas non plus la ligne `compatibilite` du `paquet.xml` pour « forcer » le chargement. Ce fichier est dans le répertoire du plugin (une mise à jour l'écrase), et Escal n'a jamais été validé sur cette version du cœur.

#### 1. Vérifier le diagnostic (lecture seule)

Lancez ces commandes depuis la racine du site, le répertoire qui contient `config/connect.php` :

```bash
# Version réellement installée du cœur et des paquets SPIP
composer show 'spip/*'
composer show 'spip-league/*'
php -v                                   # le cœur installé exige PHP >= 8.4

# Escal est-il encore listé comme actif ?
spip plugins:lister --short

# Les réglages Escal sont-ils toujours en base ? (ils devraient l'être)
spip config:lire escal_base_version --json   # {"escal_base_version":null} => jamais installé
spip config:lire escal --json
```

Attention : ma documentation sur spip-cli a été vérifiée sur SPIP 4.4 (spip-cli 2.0.1). Sur ce nouveau cœur, les noms ou le comportement des commandes `spip …` peuvent avoir changé. Si l'une d'elles échoue, lancez `spip list` pour voir les commandes disponibles. Les commandes `composer` et `php` restent valables dans tous les cas.

Les deux premières commandes vous donnent les révisions exactes du cœur. Si elles montrent un SPIP 5.x, le diagnostic est confirmé.

#### 2. Retrouver le look Escal : deux options

##### Option A (recommandée pour un site en production) : revenir à SPIP 4.4

Escal 5.6.2 est fait pour SPIP 4.1 à 4.x. Le seul moyen sûr de retrouver le site tel qu'il était est de le remettre sur un SPIP 4.4.x :

1. Restaurez le code et la base que vous aviez **sauvegardés avant la mise à jour**. Faites une sauvegarde de l'état actuel avant toute manipulation. La base a pu être migrée par le nouveau cœur, et je ne peux pas vous garantir qu'un SPIP 4.4 la relira telle quelle. Restaurez donc la base d'avant la mise à jour, pas seulement les fichiers.
2. Une fois le site revenu en 4.4, vérifiez puis réactivez si nécessaire :

```bash
spip plugins:lister --short              # escal et ses dépendances doivent apparaître
# si escal manque :
spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y
spip plugins:maj:bdd
spip cache:vider
```

Je n'ai pas de recette de « downgrade » du cœur SPIP 5 vers 4.4 à vous proposer. Ma documentation ne couvre pas ce cas, et le site n'en contient aucune trace. Partez de votre sauvegarde.

##### Option B : rester sur le nouveau cœur et attendre un Escal compatible

Si vous tenez à garder cette version de SPIP, il faut une version d'Escal dont le `paquet.xml` déclare une compatibilité qui inclut ce cœur. La version 5.6.2 installée ne la déclare pas. Je n'ai aucune information indiquant qu'une telle version existe aujourd'hui. Surveillez la page du plugin, https://contrib.spip.net/Escal-4077, et le dépôt SVP. Quand cette version sera publiée :

```bash
mkdir -p plugins/auto && chmod u+rwx plugins/auto
spip plugins:svp:telecharger escal -y
spip plugins:lister --short     # ne vous fiez qu'à cette commande : sur un spip-cli non patché,
                                # le téléchargement affiche « succès » sans rien télécharger
spip plugins:activer escal -y
spip plugins:maj:bdd
spip cache:vider
```

Il faudra aussi que les dépendances obligatoires d'Escal (saisies, verifier, yaml, agenda, fullcalendarcompat, svpstats, nospam, facteur, tri_par_rubrique) existent dans des versions compatibles avec ce cœur. Sinon, Escal ne pourra pas s'activer.

#### En résumé

- **Cause** : le site est passé à la nouvelle génération du cœur SPIP (`spip/framework`, PHP ≥ 8.4). Escal 5.6.2 déclare `compatibilite="[4.1.0;4.*]"`, donc SPIP le désactive et affiche ses squelettes par défaut.
- **Réactiver ne suffit pas**, et forcer la compatibilité dans le `paquet.xml` est à éviter.
- **Solution** : revenir à SPIP 4.4 depuis la sauvegarde d'avant la mise à jour (option A), ou attendre une version d'Escal compatible avec ce cœur (option B). Vos réglages Escal sont conservés dans la meta `escal` et reviendront dès qu'Escal sera de nouveau chargé.

---

## plugin-escal, cas 21 — tirage 2

### Escal ne s'affiche plus après la mise à jour : diagnostic

#### Ce que montre le site

- `plugins/auto/escal/v5.6.2/paquet.xml` déclare :

  ```xml
  compatibilite="[4.1.0;4.*]"
  ```

  Escal 5.6.2 n'accepte donc que **SPIP 4.1 à 4.x**.

- Le site contient maintenant `vendor/spip/framework/composer.json` (paquet `spip/framework`, `"php": "^8.4"`, dépendances `spip-league/*`, dont `spip-league/plugin-manager`). C'est l'ossature de **SPIP 5.0**, pas celle d'une 4.4.

**Conclusion probable :** la mise à jour a fait passer le site en SPIP 5.0. Sur cette version, SPIP refuse d'activer un plugin dont la compatibilité s'arrête à `4.*`. Escal a été désactivé, ou n'a pas pu être réactivé. Le site public retombe alors sur la dist, d'où la disparition complète du look Escal. Les fichiers ne sont pas cassés, et la configuration (meta `escal`, casier `escal/config`) est toujours en base.

#### Vérifier avant d'agir

Depuis la racine du site (le dossier qui contient `config/connect.php`) :

```bash
# Version d'Escal présente et sa compatibilité
grep -E 'version=|compatibilite=' plugins/auto/escal/*/paquet.xml | head -3

# Escal est-il actif ? (le préfixe escal doit apparaître)
spip plugins:lister --short

# La configuration Escal est-elle toujours en base ?
spip config:lire escal_base_version --json   # {"escal_base_version":null} => jamais installé
spip config:lire escal --json
```

Une réserve : ces commandes `spip …` sont celles de **spip-cli 2.0.1 sous SPIP 4.4**. Ma documentation ne couvre pas la console de SPIP 5.0. Si `spip` ne répond plus ou si les noms de commandes ont changé, lancez `spip list`. Sinon, vérifiez dans l'espace privé, page **Plugins** : Escal y est signalé comme incompatible.

#### Comment réactiver Escal

##### Ce qu'il ne faut pas faire

- **Ne réactivez pas Escal de force** (`spip plugins:activer escal -y`) sur ce SPIP 5.0. Son `paquet.xml` exclut cette version, et l'activation sera refusée ou ignorée.
- **Ne modifiez pas `compatibilite` dans `paquet.xml`** pour forcer l'activation. On ne touche jamais aux fichiers d'un plugin, car la mise à jour suivante les écrase. Surtout, rien ne garantit qu'Escal (squelettes, CVT de configuration, pipelines) tourne sur SPIP 5.0 : vous remplaceriez un site sans habillage par un site qui plante.
- **Ne réécrivez pas la meta `escal` à la main** : elle est intacte, et c'est `escal_upgrade()` qui la gère.

##### Option 1 (recommandée) : revenir en SPIP 4.4

C'est la seule configuration où Escal 5.6.2 est supporté. Restaurez la sauvegarde d'avant la mise à jour (fichiers et base), ou réinstallez le cœur SPIP 4.4 en gardant `config/`, `IMG/`, `plugins/` et `squelettes/`. Ensuite :

```bash
spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y
spip plugins:maj:bdd
spip plugins:lister --short     # escal et toutes ses dépendances doivent apparaître
spip cache:vider
```

Vérifiez dans la sortie de `plugins:lister --short` que tous les préfixes sont là, en particulier `escal`. Ne vous fiez pas au seul message de `plugins:activer`.

La configuration d'Escal étant restée en base, la mise en page, les blocs et les couleurs reviennent tels qu'ils étaient. Le `cache:vider` est indispensable : les pages qui incluent les feuilles de style générées sont en cache.

##### Option 2 : rester en SPIP 5.0 et attendre Escal

Il faut une version d'Escal dont le `paquet.xml` déclare une compatibilité avec SPIP 5. Je n'en connais pas : la version documentée ici (5.6.x) s'arrête à `4.*`. Surveillez la page du plugin (<https://contrib.spip.net/Escal-4077>). Quand une version compatible sera publiée, installez-la puis activez-la avec les mêmes commandes que ci-dessus. Pensez à vérifier que la commande de téléchargement fonctionne vraiment : sur spip-cli non patché, `plugins:svp:telecharger` annonce un succès sans rien télécharger. Contrôlez toujours avec `plugins:lister --short`.

D'ici là, le site public restera sur la dist de SPIP 5.0.

#### En résumé

Escal n'est pas en panne : il est **incompatible avec le SPIP 5.0** installé par la mise à jour, qui l'a désactivé. Pour retrouver le look Escal maintenant, revenez en SPIP 4.4, puis lancez `plugins:activer … escal`, `plugins:maj:bdd` et `cache:vider`. Sinon, attendez une version d'Escal compatible SPIP 5. Ne forcez pas la compatibilité dans `paquet.xml`.

---

## plugin-escal, cas 22 — site 4.4.22

### Installer et activer Escal

#### Ce que j'ai vérifié sur le site

- `ecrire/inc_version.php` indique **SPIP 4.4.22** (`_SPIP_VERSION_ID` 40422). Escal 5.6.0 est déclaré compatible `[4.1.0;4.*]`, donc il convient à cette version.
- `plugins/` et `squelettes/` sont vides : ni Escal ni ses dépendances ne sont encore présents, et il n'y a pas de `plugins/auto/`.
- **Je ne vois pas de `config/connect.php`** dans ce que vous m'avez fourni. Or spip-cli doit être lancé depuis la racine d'un SPIP **déjà installé**, celle qui contient `config/connect.php`. Si le site n'est pas encore installé, terminez d'abord l'installation de SPIP, en passant par `ecrire/` dans le navigateur ou par `spip core:installer`.

Lancez toutes les commandes ci-dessous depuis la racine du site.

#### 0. Vérifier votre spip-cli (important)

La version stock de spip-cli 2.0.1 contient un bug dans `plugins:svp:telecharger` : elle **ne télécharge rien, mais affiche quand même un succès**. Le correctif est la MR !91. Vérifiez votre copie depuis le répertoire de spip-cli :

```bash
grep -q 'UPPER(pl.prefixe) = UPPER' src/Command/PluginsSvpTelecharger.php \
  && echo "patché (MR!91)" || echo "NON patché — les téléchargements échoueront en silence"
```

Si votre spip-cli n'est pas patché, téléchargez les plugins à la main dans `plugins/`, ou passez par l'espace privé. Utilisez ensuite spip-cli seulement pour l'étape 3 (`plugins:activer`).

#### 1. Préparer le répertoire de SVP et déclarer le dépôt

```bash
# SVP décompresse dans plugins/auto/, qui n'existe pas encore sur ce site
mkdir -p plugins/auto && chmod u+rwx plugins/auto

# Déclarer le dépôt officiel (à faire une seule fois)
spip plugins:svp:depoter https://plugins.spip.net/depots/principal.xml
```

#### 2. Télécharger Escal et ses dépendances

```bash
spip plugins:svp:telecharger saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y
```

Vous devez obtenir **11 paquets**, car `calendriermini` est tiré en dépendance indirecte. SVP range chaque version dans son propre dossier, par exemple `plugins/auto/escal/v5.6.0/`.

#### 3. Activer, en commençant par les dépendances

```bash
spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y
```

L'activation d'`escal` lance `escal_upgrade()`. Cette fonction crée les groupes de mots-clés techniques (`affichage` et `Agenda_couleur`), une rubrique cachée et trois articles de départ : *Édito*, *Accès direct* et *Mentions légales*. **N'écrivez pas la meta `escal` à la main** pour « installer » le plugin.

#### 4. Mettre à jour la base et contrôler le résultat

```bash
spip plugins:maj:bdd          # doit afficher « Installation du plugin Escal … MAJ 1.0.17 »
spip plugins:lister --short   # chaque préfixe ci-dessus doit apparaître, escal compris
spip config:lire escal_base_version --json   # doit renvoyer une version, pas null
```

Pour savoir si l'installation a réussi, fiez-vous à `plugins:lister --short` et pas au message de `telecharger`.

#### 5. Publier les contenus de départ

Lancé depuis spip-cli, `escal_upgrade()` n'a pas d'auteur authentifié. Les trois articles de départ restent donc **en cours de rédaction** (`prepa`). Les blocs Édito et Accès direct s'affichent alors vides, et le site a l'air cassé. Pour publier ces articles en donnant une session à la CLI :

```bash
spip php:eval 'include_spip("action/editer_objet"); include_spip("base/abstract_sql");
$GLOBALS["visiteur_session"] = sql_fetsel("*", "spip_auteurs", "statut=" . sql_quote("0minirezo"));
$GLOBALS["visiteur_session"]["webmestre"] = "oui";
foreach (sql_allfetsel("id_article", "spip_articles", "statut=" . sql_quote("prepa")) as $x) {
  objet_instituer("article", $x["id_article"], ["statut" => "publie"]);
}'
```

Attention : cette commande publie **tous** les articles en `prepa`. Sur un site neuf, il n'y a que les trois articles d'Escal. Si le site contient déjà des brouillons, limitez la requête aux articles qu'Escal vient de créer, ou publiez-les depuis l'espace privé.

#### 6. Vider le cache

```bash
spip cache:vider
```

#### Ensuite

- Configuration : **espace privé → Squelettes → Escal** (réservé aux webmestres), page `?exec=configurer_escal`.
- Vous pouvez aussi régler Escal depuis la CLI. Par exemple, pour choisir la mise en page :

  ```bash
  spip config:ecrire escal/config/layout:PMPfluide
  spip cache:vider
  ```
- Pour personnaliser Escal, ne modifiez rien dans `plugins/auto/escal/`, car une mise à jour écrase ces fichiers. Placez vos surcharges dans `squelettes/`, par exemple `squelettes/styles/perso.css` ou `squelettes/inclusions/inc-‹nom›.html`.
