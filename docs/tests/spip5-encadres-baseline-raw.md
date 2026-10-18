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

## spip-mutualisation-maintenance, cas 23 — ferme 5.0, mise à jour des plugins partagés — tirage 1

# Mise à jour des plugins partagés de la ferme : préparation

**Conclusion : je ne prépare pas de commandes de mise à jour prêtes à lancer.** La copie de la racine partagée montre un cœur **SPIP 5.0.0-beta**. Cette procédure de maintenance couvre SPIP 4.2–4.4 avec Mutualisation facile 2.x. Le plugin de mutualisation installé et le seul plugin partagé visible ne déclarent pas SPIP 5 dans leur compatibilité. Il faut d'abord établir ce qui tourne réellement en production. Ci-dessous : ce que j'ai observé, les blocages, et l'inventaire en lecture seule à lancer de votre côté.

## Constats

Établis à partir de la copie (`…/p23/farm/`), en lecture seule :

| Constat | Preuve |
|---|---|
| Cœur SPIP **5.0.0-beta** (`ID = 50000`, `CODE = 2026_09_22`, `BASE = 2026_08_03_00`, PHP 8.4.0–8.5.99) | `ecrire/src/SpipVersion.php` |
| Installation de type Composer / SPIP 5 : `bin/spip` (console `SpipLeague`), `vendor/spip/framework` (qui dépend entre autres de `spip-league/plugin-manager ^1.x-dev`, en `minimum-stability: dev`) | `bin/spip`, `vendor/spip/framework/composer.json` |
| Mutualisation facile **2.0.1**, `compatibilite="[4.2.0;4.*]"`, `<chemin path="" type="aucun" />` | `mutualisation/paquet.xml` |
| Le `mes_options.php` partagé fait un `require` de `_DIR_RACINE.'mutualisation/mutualiser.php'` (répertoire de premier niveau, pas sous `plugins/`) | `config/mes_options.php` |
| `demarrer_site()` effectif : `'repertoire' => 'sites'`, `creer_site => true`, `creer_base => true`, `code => 'ecureuil'`, `url_img_courtes => true`, `cookie_prefix => true`, `table_prefix => false` | `config/mes_options.php` |
| `_SPIP_PATH` = `_DIR_SITE : _DIR_RACINE : squelettes-dist/ : prive/ : _DIR_RESTREINT`. Le `plugins/` de la racine est donc partagé par tous les sites | `mutualisation/mutualiser.php:184-191` |
| Sites présents dans la copie : `sites/asso-exemple.org`, `sites/mairie-exemple.fr` | `sites/` |
| Un seul plugin partagé visible : **Champs Extras (`cextras`) 4.3.3**, `compatibilite="[4.1.0;4.*]"`, dépendances obligatoires `saisies [4.2.0;]` et `yaml [3.0.0;]`, dépendances facultatives `verifier`, `crayons` | `plugins/auto/cextras/v4.3.3/paquet.xml` |
| Le chemin `plugins/auto/<prefix>/v<version>` évoque SVP. C'est une hypothèse, pas une preuve de provenance | arborescence |

**Inconnues** (aucune preuve dans la copie) :

- La version réellement servie en production, et si la copie est complète : elle ne contient ni `ecrire/inc_version.php`, ni `spip.php`, ni `composer.json`/`composer.lock` à la racine, ni `plugins-dist/`.
- Le moteur de base de chaque site : les deux `connect.php` sont des fichiers masqués, sans appel de connexion.
- Les plugins actifs sur chaque site, et l'emplacement de `saisies` et `yaml`, absents de `plugins/auto/` dans la copie.
- La provenance des plugins (SVP, Composer, Git) et d'éventuelles modifications locales.
- La pile web/PHP (Apache ou Nginx/PHP-FPM, version de PHP).
- L'existence d'une sauvegarde récente, vérifiée et restaurable.
- Le site d'administration : `_SITES_ADMIN_MUTUALISATION` vaut `admin-ferme.example`, mais aucun `sites/admin-ferme.example` n'existe dans la copie.

## Risques

1. **Bloquant : hors périmètre et incompatibilité déclarée.** Un cœur 5.0.0-beta fait tourner Mutualisation facile 2.0.1 (`[4.2.0;4.*]`) et cextras 4.3.3 (`[4.1.0;4.*]`). Soit la copie ne reflète pas la production, soit la ferme tourne déjà avec des composants qui ne déclarent pas SPIP 5. Dans les deux cas, je ne peux pas appliquer une procédure 4.x de mise à jour des plugins. SPIP 5 change aussi le modèle de plugins (gestionnaire de plugins Composer, `spip-league/plugin-manager`). Il faut une procédure dédiée, documentée pour la révision exacte du cœur et des paquets.
2. **Rayon d'impact : toute la ferme.** `plugins/` est dans le `_SPIP_PATH` de chaque site. Remplacer `plugins/auto/cextras` touche tous les sites qui l'activent, et peut aussi toucher les autres par le chargement partagé. On ne peut pas tester sur un seul site de production : il faut une copie isolée de la ferme.
3. **Dépendances introuvables.** `saisies` et `yaml` sont obligatoires pour cextras mais absents de l'arbre partagé copié. Si cextras est actif quelque part, ils sont ailleurs (chemin à résoudre). Une mise à jour isolée de cextras peut casser ses dépendances, ou être cassée par elles.
4. **Provenance mêlée possible.** Le cœur est géré par Composer (`vendor/`, `bin/spip`) et le plugin a une disposition de type SVP. Il ne faut pas mélanger les deux méthodes pendant une mise à jour.
5. **Anomalies de sécurité, à traiter à part de la mise à jour :**
   - Le `config/mes_options.php` partagé contient des identifiants SQL en clair. Je ne les reproduis pas ici. Leur valeur semble être celle de l'exemple fourni avec le plugin : à vérifier.
   - `creer_site => true` et `creer_base => true` avec le code d'activation par défaut `ecureuil` (public) : n'importe qui peut potentiellement créer un site et une base sur un domaine qui pointe vers la ferme.
   - Les actions `renouvelle_alea`, `dirliste` et `dirsize` de `?exec=mutualisation` répondent sans authentification sur chaque site.
6. **Effet de bord du tableau de bord.** `upgradeplugins` (et le bouton « Upgrader tout ») vide largement les caches de chaque site, sans point de contrôle par site.

## Procédure proposée

Uniquement des observations en lecture seule et de la planification. Aucune mutation n'est prête.

1. **Confirmer la version réellement servie** (lecture seule, sur le serveur) :
   ```bash
   farm_root=/chemin/verifie/de/la/racine/partagee   # à fournir par vous
   farm_root="$(realpath -e -- "$farm_root")" && test "$farm_root" != /
   rg -n "VERSION =|const int ID" "$farm_root/ecrire/src/SpipVersion.php" 2>/dev/null
   rg -n "spip_version_branche|_SPIP_VERSION_ID" "$farm_root/ecrire/inc_version.php" 2>/dev/null
   test -f "$farm_root/composer.lock" && composer --working-dir="$farm_root" show --locked 'spip/*'
   ```
2. **Lister les plugins partagés et leur provenance** :
   ```bash
   find -P "$farm_root/plugins" "$farm_root/plugins-dist" -maxdepth 4 -name paquet.xml -print 2>/dev/null
   for p in $(find -P "$farm_root/plugins" -maxdepth 4 -name paquet.xml -printf '%h\n' 2>/dev/null); do
     echo "== $p"
     rg -n '^(<paquet|[[:space:]]*(prefix|version|compatibilite|schema)=|[[:space:]]*<(necessite|utilise))' "$p/paquet.xml"
     git -C "$p" describe --tags --always --dirty 2>/dev/null
     git -C "$p" status --short 2>/dev/null
   done
   ```
3. **Chercher les autres chemins de plugins** (`_DIR_PLUGINS_SUPPL`, plugins par site) :
   ```bash
   rg -n "_DIR_PLUGINS_SUPPL|_DIR_PLUGINS_AUTO" "$farm_root/config" "$farm_root/sites"/*/config/mes_options.php 2>/dev/null
   find -P "$farm_root/sites" -mindepth 2 -maxdepth 2 -type d \( -name plugins -o -name squelettes \) -print
   ```
4. **Identifier le moteur de base de chaque site** sans afficher les identifiants :
   ```bash
   for c in "$farm_root"/sites/*/config/connect.php; do
     printf '%s: ' "$c"; grep -oE "'(mysql|sqlite3|sqlite2|pg)'" "$c" | sort -u | tr '\n' ' '; echo
   done
   ```
5. **Identifier les plugins actifs par site**, sur la liste des plugins actifs dans le `spip_meta` de chaque site. Lecture SQL seulement, avec un fichier d'options protégé que vous fournissez (jamais de `-p<mot de passe>`). Ne me transmettez que les préfixes et versions.
6. **Me renvoyer la sortie de ces observations.** Selon le résultat :
   - **Production en SPIP 4.2–4.4** (la copie n'était pas représentative) : je construis la matrice d'impact (plugin, version actuelle → cible, chemin, provenance, sites actifs, compatibilité, dépendances, schéma), puis le plan de sauvegarde, de recette sur copie isolée et de mise à jour site par site.
   - **Production en SPIP 5.0** : la mise à jour des plugins partagés n'entre pas dans cette procédure. La première question n'est d'ailleurs pas la mise à jour de cextras, mais la compatibilité de Mutualisation facile 2.0.1 et des plugins `[…;4.*]` avec 5.0. Il faut une procédure dédiée, à rechercher et valider pour les révisions exactes de `spip/spip`, `spip/ecrire` et des paquets Composer, sur une copie isolée.
7. Quelle que soit l'issue, les actions de sécurité correspondent aux lignes 1 et 2 du plan d'actions. Elles se préparent à part et ne se mélangent pas à la mise à jour.

## Commandes

Plan d'actions : **inventaire complet des mutations envisagées.** Aucune n'est prête à lancer. Toutes sont **proposées, non exécutées**, et bloquées tant que leurs préconditions ne sont pas remplies.

| # | Action | Objectif | Préconditions | Portée exacte | Impact | Sauvegarde / preuve | Commande proposée | Contrôle de succès | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Restreindre la création automatique de sites | Empêcher la création de site ou de base par un tiers avec le code public `ecureuil` | Confirmer la configuration effective en production ; décision de l'exploitant (`creer_site`/`creer_base` à `false` ou code fort et secret) ; recette sur copie isolée | Le seul fichier `<farm_root>/config/mes_options.php` (ferme entière) | Tous les sites ; plus de création automatique de nouveaux sites | Copie horodatée du fichier hors racine web, mode `0600`, empreinte SHA-256 | **Non préparée, bloquée.** Modification à rédiger après confirmation (édition manuelle relue, pas de `sed` aveugle) | `php -l` sur le fichier ; un domaine inconnu n'obtient plus de formulaire de création ; tous les sites existants répondent | Restaurer la copie horodatée vérifiée par son empreinte, puis refaire le contrôle |
| 2 | Rotation des identifiants SQL du `mes_options.php` partagé | Ne plus dépendre d'identifiants en clair qui semblent être ceux de l'exemple | Confirmer s'ils sont réels et ce qu'ils permettent ; plan de rotation côté SGBD ; sauvegarde vérifiée (ligne 3) | Compte SQL et `<farm_root>/config/mes_options.php` | Création de bases ; éventuellement connexions si le compte est partagé | Copie du fichier (mode `0600`) ; liste des privilèges du compte, sans mot de passe | **Non préparée, bloquée.** Procédure à rédiger avec l'administrateur SGBD | Connexions des sites intactes ; ancien secret refusé | Rétablir l'ancien compte et la copie du fichier pendant la fenêtre convenue |
| 3 | Sauvegarde cohérente avant toute mise à jour (code partagé, configuration globale, `config/` et `IMG/` de chaque site, base de chaque site) | Disposer d'une unité de restauration vérifiée | Version, moteur de chaque site, sites actifs et chemins connus (procédure, étapes 1–5) ; destination hors racine web, avec copie hors de l'hôte | Chemins explicitement listés dans le manifeste ; une base par site | Lecture des données ; charge d'E/S | Manifeste (UTC, hôte, sites, versions, préfixes, tailles, SHA-256, versions des outils) | **Non préparée, bloquée** jusqu'au moteur identifié : modèles MariaDB/MySQL (`--defaults-file` + `--no-login-paths`) ou SQLite (`.backup`) à instancier avec les chemins vérifiés | Code de sortie 0, fichiers non vides, `sha256sum -c` OK, **restauration test isolée réussie** | Sans objet (aucun changement de production) ; supprimer un artefact raté seulement après accord |
| 4 | Mise à jour du ou des plugins partagés (cextras et dépendances) | Passer à la version cible | Production confirmée en 4.2–4.4 (sinon procédure SPIP 5 dédiée) ; matrice d'impact ; compatibilité de la cible vérifiée sur son vrai `paquet.xml` ; provenance stable ; ligne 3 validée ; recette sur copie isolée | `<farm_root>/plugins/auto/<prefix>/` (ferme entière) | Tous les sites qui activent le plugin, et chargement partagé pour les autres | Ancien répertoire conservé tel quel (archive + SHA-256), manifeste de la ligne 3 | **Non préparée, bloquée** | Version cible visible ; plugin actif sur les sites concernés ; journaux propres | Remettre l'ancien répertoire exact. Si le schéma a migré : restaurer aussi la base correspondante (ligne 3) |
| 5 | Mise à niveau des plugins site par site (`?exec=mutualisation&upgradeplugins=oui&secret=…`) | Appliquer les migrations de schéma site par site | Ligne 4 faite ; preuves et journaux du site capturés ; maintenance active ; **pas** « Upgrader tout » | Un site à la fois | Schéma du site ; vidage large des caches de ce site | Journaux et état de `spip_meta` avant l'appel | **Non préparée, bloquée** | Schéma à jour, site public et privé OK, journaux propres ; arrêt à la première erreur | Restaurer la base de ce site depuis le même point de contrôle que le code (ligne 3), puis ligne 4 |

## Validation

Aucune validation de mise à jour pour l'instant : aucune mutation n'est prête. Lectures à faire sur **chaque** site (`asso-exemple.org`, `mairie-exemple.fr` et le site d'administration, une fois localisé), pas seulement sur l'administration :

- Réponse HTTP publique et accès à l'espace privé.
- Version SPIP affichée, liste des plugins actifs et versions.
- Journaux PHP et SPIP du site (`tmp/log/` du site), sans les purger.

Une fois les lignes débloquées, les contrôles propres aux mutations sont ceux des lignes 3, 4 et 5, dans cet ordre. Pour la ligne 1, seulement après la décision de l'exploitant.

## Retour arrière

- **Situation actuelle : rien n'est changé**, donc rien n'est à restaurer.
- **Déclencheurs futurs** : une erreur d'activation, de schéma ou de journal sur un site ; une régression sur un site où le plugin est inactif ; une version observée différente de la cible.
- **Unité de restauration** : le manifeste de la ligne 3. Il couvre le code partagé exact (plugins et dépendances, en ensemble cohérent), la configuration globale, ainsi que la base, `config/` et `IMG/` de chaque site, tous issus du **même** point de contrôle. Ne jamais restaurer la base d'un site depuis un autre point de contrôle que le code qu'elle attend.
- **Ordre** : arrêter la ligne 5 en cours ; appliquer le retour arrière de la ligne 5 pour chaque site déjà migré, puis celui de la ligne 4. Les retours arrière des lignes 1 et 2 sont indépendants de la mise à jour.

**Ce qu'il me faut pour avancer :** la sortie des étapes 1 à 5 de la procédure (version réellement servie, plugins et provenance, moteur de chaque site, plugins actifs par site), et l'emplacement du site d'administration.

## spip-mutualisation-maintenance, cas 23 — tirage 2

# Mise à jour des plugins partagés de la ferme : préparation

**Je ne peux pas encore vous donner une procédure de mise à jour prête à lancer.** La copie de la racine partagée montre un cœur **SPIP 5.0.0-beta**. Or Mutualisation facile 2.0.1 et le seul plugin partagé présent (Champs Extras 4.3.3) ne déclarent une compatibilité qu'avec SPIP 4.x. Cette procédure (Mutualisation facile 2.x sur SPIP 4.2–4.4) ne couvre pas cette situation. Je vous propose ci-dessous uniquement des **contrôles en lecture seule**. Pour chaque modification qu'il faudra faire plus tard, je donne la ligne du plan d'action, marquée « bloquée ».

Ce que j'ai lu : uniquement la copie de la racine partagée fournie. Je n'ai rien exécuté sur le serveur et rien modifié. Je n'ai pas affiché les fichiers `connect.php`.

## Constats

Faits vérifiés dans la copie (fichiers datés du 2026-10-18 14:52) :

| Élément | Constat | Preuve |
|---|---|---|
| Version du cœur | `5.0.0-beta`, `ID = 50000`, `CODE = 2026_09_22`, `BASE = 2026_08_03_00`, PHP 8.4.0–8.5.99 | `ecrire/src/SpipVersion.php` |
| Provenance du cœur | Il y a des signes d'une installation par Composer (`vendor/spip/framework/composer.json`, `bin/spip` qui demande `vendor/autoload_runtime.php`). En revanche, la copie ne contient **ni `composer.json`, ni `composer.lock` à la racine, ni `ecrire/inc_version.php`** | arborescence de la copie |
| Chargement de la mutualisation | `config/mes_options.php` exige `_DIR_RACINE.'mutualisation/mutualiser.php'` | `config/mes_options.php`, ligne 60 |
| Version de Mutualisation facile | `2.0.1`, `compatibilite="[4.2.0;4.*]"`, `<chemin path="" type="aucun" />` | `mutualisation/paquet.xml` |
| `repertoire` effectif | `'sites'` | appel à `demarrer_site()`, `config/mes_options.php`, ligne 175 |
| Autres options | `table_prefix => false` (une base par site, préfixe `spip`), `cookie_prefix => true`, `url_img_courtes => true`, `creer_site => true`, `creer_base => true` | idem, lignes 166–185 |
| Calcul de `$site` | `HTTP_HOST` sans `www.` (avec redirection), `:` remplacé par `_` sauf pour le port 80 | lignes 65–87 |
| Site d'administration | `_SITES_ADMIN_MUTUALISATION = 'admin-ferme.example'` | ligne 53 |
| Sites présents dans `sites/` | `asso-exemple.org` et `mairie-exemple.fr`. Chacun a `config/connect.php`, `IMG/`, `local/` et `tmp/` | arborescence |
| Plugins partagés | un seul : `plugins/auto/cextras/v4.3.3/` (Champs Extras `4.3.3`, `compatibilite="[4.1.0;4.*]"`). Il exige `saisies [4.2.0;]` et `yaml [3.0.0;]`, et utilise `verifier` et `crayons` | `plugins/auto/cextras/v4.3.3/paquet.xml` |
| `plugins-dist/` | absent de la copie | arborescence |

Points inconnus (à établir sur le serveur réel) :

- Le serveur en production tourne-t-il vraiment en 5.0.0-beta ? La copie est-elle complète ? Il lui manque `composer.lock`, `inc_version.php` et `plugins-dist/`.
- Où se trouvent `saisies` et `yaml`, dont Champs Extras a besoin ? Ils ne sont pas dans `plugins/` de la copie.
- Quel moteur de base de données utilise chaque site ? `_INSTALL_SERVER_DB` vaut `mysql` à la création, mais chaque `connect.php` reste à vérifier sans l'afficher.
- Quels plugins sont actifs sur chaque site ?
- Quelle est la pile web/PHP (Apache ou Nginx/PHP-FPM, versions) ? Le dossier `sites/admin-ferme.example` existe-t-il ailleurs ?
- Quelle version cible visez-vous pour chaque plugin, et quelles sont ses déclarations de compatibilité ?
- Existe-t-il une sauvegarde récente, **vérifiée et testée en restauration** ?

Anomalies :

1. **Cœur en dehors des compatibilités déclarées.** SPIP 5.0.0-beta tourne avec Mutualisation facile `[4.2.0;4.*]` et Champs Extras `[4.1.0;4.*]`. Si c'est bien l'état de la production, la ferme fonctionne déjà hors de toute compatibilité déclarée.
2. **Bases peut-être déjà migrées.** Avec `BASE = 2026_08_03_00`, les bases des sites ont peut-être déjà été migrées vers le schéma 5.0. Revenir en 4.x supposerait alors des bases d'**avant** cette migration, pas seulement de remettre l'ancien code.
3. **Plugin installé à la manière de SVP.** `plugins/auto/cextras/v4.3.3/` a la structure d'un téléchargement SVP. À côté, le cœur semble géré par Composer. Ces provenances mélangées sont à éclaircir avant toute mise à jour.
4. **Dépendances de Champs Extras introuvables.** `saisies` et `yaml` ne figurent pas dans la copie.
5. **Site d'administration absent de `sites/`.** `admin-ferme.example` n'y est pas.
6. **Identifiants SQL en clair.** `config/mes_options.php` contient en clair l'identifiant et le mot de passe SQL de création (`_INSTALL_USER_DB` / `_INSTALL_PASS_DB`). Ce sont **les mêmes valeurs que dans le fichier d'exemple public** `mutualisation/mes_options.php.txt`. Je ne reproduis pas la valeur ici.
7. **Création automatique de site active.** Avec `creer_site => true` et `creer_base => true`, tout nom d'hôte qui arrive sur la ferme peut lancer la création d'un site. La protection repose sur le code d'activation, qui est ici celui par défaut (`ecureuil`).
8. **Points d'entrée sans authentification.** `?exec=mutualisation&renouvelle_alea=yo`, `&dirliste=oui` et `&dirsize=oui` répondent sans authentification sur **tous** les sites (`mutualiser.php`, lignes 704–729). Seuls `upgrade` et `upgradeplugins` sont protégés par un secret.

## Risques

- **Périmètre touché : toute la ferme.** `plugins/auto/` est partagé. Changer `cextras` (ou ses dépendances) touche tous les sites qui l'activent, et chaque chargement passe par le même chemin. Une mise à jour « pilote » sur un seul site de production n'est pas possible : il faut une copie isolée de la ferme.
- **Incompatibilité bloquante.** Aucun paquet installé ne déclare SPIP 5.0. Mettre à jour Champs Extras vers une version 4.x ne règle rien. Une version qui déclare 5.0 suppose aussi des `saisies` et `yaml` compatibles 5.0, et Mutualisation facile elle-même reste déclarée 4.x. Je n'ai pas vérifié les dernières versions disponibles en amont (pas d'accès réseau ici). Il faut le faire au moment de l'opération, à partir des `paquet.xml` des archives ou tags cibles.
- **Provenance mal établie.** Cœur sous Composer d'après les indices, plugin de type SVP, `composer.lock` absent de la copie : on ne peut pas encore dire quelle méthode doit servir pour la mise à jour, ni ce qu'il faudra garder pour revenir en arrière.
- **Retour arrière.** Si le schéma SQL d'un site a migré (vers 5.0 pour le cœur, ou pour la nouvelle version d'un plugin), remettre l'ancien code partagé sans la base correspondante casse le site.
- **Bouton « Upgrader tout ».** Il lance `upgradeplugins` sur tous les sites d'un coup et vide largement les caches avant toute validation. Il ne faut pas s'en servir.
- **Identifiants.** Le mot de passe SQL en clair, identique à celui de l'exemple public, est un risque de sécurité indépendant de la mise à jour. Il ne doit apparaître ni dans les sauvegardes lisibles, ni dans les journaux, ni dans les tickets.

**Points bloquants (conditions d'arrêt atteintes) :** version cible et compatibilité non vérifiées, cœur hors périmètre (5.0.0-beta), provenance du cœur et des plugins incertaine, liste des sites et plugins actifs par site non établie, aucune sauvegarde vérifiée et restaurable.

## Procédure proposée

Toutes les étapes ci-dessous sont des **observations en lecture seule** à lancer par vous. Renvoyez-moi uniquement les sorties filtrées, jamais le contenu de `connect.php` ni de `mes_options.php`.

1. **Confirmer la racine et la version en production.**

   ```bash
   spip_root=/chemin/verifie/de/la/ferme      # à renseigner
   spip_root="$(realpath -e -- "$spip_root")" && test "$spip_root" != /
   rg -n "const (string VERSION|int (ID|CODE|BASE))" "$spip_root/ecrire/src/SpipVersion.php"
   test -f "$spip_root/ecrire/inc_version.php" && echo inc_version:present || echo inc_version:absent
   ls -d -- "$spip_root"/plugins-dist 2>/dev/null
   ```

2. **Établir la provenance.**

   ```bash
   git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null
   git -C "$spip_root" status --short 2>/dev/null
   test -f "$spip_root/composer.json" && test -f "$spip_root/composer.lock" && echo composer:oui
   composer --working-dir="$spip_root" show --locked 2>/dev/null | rg -i '^spip'
   ```

3. **Établir la liste exacte des plugins partagés et leurs dépendances.**

   ```bash
   find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -mindepth 1 -maxdepth 4 \
     -type f -name paquet.xml -print 2>/dev/null
   # pour chaque paquet.xml listé :
   rg -n '^(<paquet|[[:space:]]*(prefix|version|compatibilite|schema)=|[[:space:]]*<(necessite|utilise))' \
     "<chemin/paquet.xml>"
   rg -n '_DIR_PLUGINS_SUPPL|_SPIP_PATH|dossier_squelettes' "$spip_root/config" "$spip_root/sites" --glob '*.php'
   ```

4. **Identifier le moteur de base de chaque site sans rien afficher d'autre que le résultat.**

   ```bash
   for c in "$spip_root"/sites/*/config/connect.php; do
     s="${c#"$spip_root"/sites/}"; s="${s%%/*}"
     if rg -q "'sqlite3'" -- "$c"; then e=sqlite3; elif rg -q "'mysql'" -- "$c"; then e=mysql; else e=inconnu; fi
     printf '%s\t%s\n' "$s" "$e"
   done
   find -P "$spip_root/sites" -xdev -mindepth 1 -maxdepth 1 -printf '%y %f\n' | sort
   find -P "$spip_root/sites" -xdev -mindepth 2 -maxdepth 2 -type l -printf '%p -> %l\n'
   ```

5. **Plugins actifs par site.** Pour chaque site, relevez depuis l'espace privé (page Plugins) ou la méta `plugin`, en indiquant date et heure : la liste des préfixes et versions actifs, et `version_installee` pour chaque plugin à schéma. Ne vous fiez pas à un cache `tmp/` non daté.

6. **Pile web/PHP et exposition.** Exécutez les commandes filtrées de l'inventaire (`nginx -T` passé par `awk` sur `server_name` / `root` / `fastcgi_pass`, ou `apache2ctl -S`), puis `php -v`. Vérifiez ensuite dans les journaux d'accès les appels `exec=mutualisation` contenant `dirliste`, `dirsize` ou `renouvelle_alea`.

7. **Compatibilité des cibles.** Pour chaque plugin à mettre à jour (et pour Mutualisation facile), récupérez l'archive ou le tag cible **sur une copie isolée**. Relevez `version`, `compatibilite` et `necessite` dans son `paquet.xml`, puis remplissez la matrice d'impact :

   | Plugin | Actuel → cible | Chemin chargé | Provenance | Sites actifs | Compat. SPIP 5.0.0-beta | Dépendances | Modifs locales | Schéma |
   |---|---|---|---|---|---|---|---|---|

8. **Décider.**
   - Si la production est bien en 5.0.0-beta, il faut une procédure dédiée, préparée à part pour cet environnement (cœur 5.0, mutualisation, dépendances). Il ne s'agit plus d'une simple mise à jour de plugins.
   - Si la production est en réalité en 4.2–4.4 et que la copie est inexacte, recommencez l'inventaire sur une copie fidèle. Je pourrai alors détailler les lignes 1 à 7 du plan d'action.

Ensuite seulement, une fois les blocages levés et sur décision explicite : lignes 1 à 3 du plan d'action, dans l'ordre (sauvegarde et test de restauration) ; ligne 4 (essai sur une copie isolée) ; puis lignes 5 à 7 en production, dans l'ordre. La ligne 8 relève d'un chantier séparé.

## Commandes

Plan d'action complet. **Toutes les lignes sont bloquées.** Aucune commande de modification n'est fournie tant que les conditions préalables ne sont pas remplies. Statut : **proposée — non exécutée — bloquée**.

| # | Action | Objectif | Conditions préalables | Périmètre exact | Impact | Sauvegarde/preuve | Commande proposée | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Créer le répertoire de sauvegarde | Avoir une destination sûre | `backup_root` résolu, hors de `spip_root`, différent de `/`, parent existant | `<backup_root>` uniquement (hors racine web) | Hôte : disque | — | **bloquée** : chemin non fourni ; modèle `mkdir`/`chmod 700` après les assertions de chemin | `realpath -e` correct, mode `700` | Supprimer le répertoire vide créé (sur votre décision) |
| 2 | Sauvegarder chaque site et le code partagé | Créer l'unité de restauration | Ligne 1 faite ; moteur de chaque site connu (étape 4) ; fichier d'options SQL dédié (mode `0600`, préparé par vous) ; provenance connue (étape 2) | Base de `asso-exemple.org` et de `mairie-exemple.fr` ; leurs `config/` et `IMG/` ; `config/mes_options.php` ; `mutualisation/` ; `plugins/` ; `composer.json`/`composer.lock` si présents | Lecture des bases, écriture dans `<backup_root>` | Manifeste (UTC, versions, préfixe `spip`, tailles) et `SHA256SUMS` | **bloquée** : moteurs et provenance inconnus ; modèle `mariadb-dump`/`mysqldump --defaults-file … --single-transaction` (sans `--databases`) ou `sqlite3 .backup`, puis `tar` d'une liste explicite | Code de sortie 0, fichiers non vides, `sha256sum -c` OK, `gzip -t`/`tar -tf` OK | Sans effet sur la production ; la sauvegarde se conserve |
| 3 | Tester la restauration dans un environnement isolé | Prouver que la sauvegarde se restaure | Ligne 2 faite ; serveur de test isolé (sans accès à la production) ; compte d'import limité à une seule base | Base de test pré-créée, distincte de la source, sur l'environnement isolé | Environnement isolé uniquement | Contrôle de `spip_meta` (`CHECK TABLE` / `PRAGMA integrity_check`), site restauré qui fonctionne | **bloquée** : environnement isolé non désigné | Tables `spip_meta` présentes et OK ; site restauré consultable | Supprimer la base de test après consignation des résultats (sur votre décision) |
| 4 | Essayer les plugins cibles sur une copie isolée de la ferme | Valider compatibilité et schéma hors production | Matrice de l'étape 7 complète ; cibles compatibles avec **la version réelle du cœur** ; copie représentative des bases | Copie de la ferme (même cœur, mêmes chemins de plugins, mêmes combinaisons actives) | Copie isolée uniquement | Journaux de la copie, `version_installee` avant et après | **bloquée** : aucune cible compatible 5.0 établie | Activation, schéma, formulaires et tâches OK sur chaque combinaison | Jeter la copie |
| 5 | Mettre la ferme en maintenance | Geler les écritures pendant la bascule | Lignes 2 à 4 validées | Toute la ferme (code partagé) | Tous les sites indisponibles en écriture | Heure UTC consignée | **bloquée** : méthode de maintenance non déterminée (pile web inconnue) | Sites en maintenance, aucune écriture | Lever la maintenance |
| 6 | Remplacer le code des plugins partagés en une fois | Installer les versions cibles | Ligne 5 active ; même provenance qu'actuellement (SVP/archive ou Composer, sans mélange) ; ancienne version conservée | `plugins/auto/<prefix>/…` exacts issus de la matrice (et dépendances coordonnées) | **Toute la ferme** | Copie de l'ancien répertoire ou ancien `composer.lock`, sommes de contrôle | **bloquée** : provenance et cible inconnues | `paquet.xml` en place = version cible ; aucune erreur PHP au chargement | Remettre exactement l'ancien répertoire ou l'ancien lock (ligne 2) |
| 7 | Lancer `upgradeplugins` site par site | Migrer le schéma de chaque site actif | Ligne 6 faite ; journaux et état de chaque site capturés **avant** ; secret obtenu depuis le tableau de bord | Un site à la fois : `asso-exemple.org`, puis `mairie-exemple.fr` (si actifs) | Schéma du site ; purge large des caches du site | Journaux du site, `version_installee` avant et après | **bloquée** ; ne jamais utiliser « Upgrader tout » | Version observée = cible, schéma à jour, journaux propres ; arrêt à la première erreur | Restaurer **la base de ce site** (ligne 2) **et** l'ancien code (ligne 6, retour arrière) |
| 8 | Changer l'identifiant SQL exposé dans `mes_options.php` | Supprimer l'identifiant identique à l'exemple public | Inventaire des droits du compte ; préparation séparée | Compte SQL de création et `config/mes_options.php` (ferme et hôte) | Création de nouveaux sites ; éventuels sites qui utilisent ce compte | État actuel des droits (sans le secret) | **bloquée** : hors de cette préparation, à préparer à part | Ancien identifiant refusé ; sites existants OK | Selon la procédure dédiée |

## Validation

Lecture seule, sur **chaque** site (`asso-exemple.org`, `mairie-exemple.fr`, et le site d'administration `admin-ferme.example` une fois localisé), pas uniquement sur le site d'administration :

- avant toute modification : capturer pour chaque site la réponse HTTP publique, la connexion à l'espace privé, la liste des plugins actifs avec leurs versions et `version_installee`, et les dernières lignes des journaux PHP/SPIP ;
- après les lignes 6 et 7 : les contrôles de réussite des lignes 6 et 7, site par site, à reporter dans ce tableau :

  | Site | Plugin actif | Version observée | Dépendances actives | Schéma à jour | Contrôles fonctionnels | Journaux propres | Résultat |
  |---|---|---|---|---|---|---|---|

- sur les sites où le plugin est **inactif** : vérifier qu'aucune régression ne vient d'une dépendance partagée ;
- garder la maintenance (ligne 5) jusqu'à ce que ce tableau soit entièrement rempli.

## Retour arrière

- **Unité de restauration :** le code partagé de la ferme (exact, selon sa provenance), `config/mes_options.php`, `mutualisation/`, `plugins/`, plus, **pour chaque site**, la base et les fichiers `config/` et `IMG/` pris **au même point de sauvegarde** (ligne 2), avec son manifeste et ses `SHA256SUMS`. On n'utilise jamais « la dernière sauvegarde » sans la nommer. `local/` et `tmp/` sont régénérés.
- **Ce qui déclenche un retour arrière :** une erreur à l'activation ou à la migration de schéma, une erreur PHP fatale au chargement, ou un contrôle de la section Validation en échec sur n'importe quel site.
- **Ordre :**
  - avant la ligne 7, pas de migration de schéma : appliquer le retour arrière de la ligne 6 ;
  - après la ligne 7 sur un ou plusieurs sites : appliquer le retour arrière de la ligne 7 pour chacun de ces sites, puis celui de la ligne 6 ;
  - si une dépendance partagée a changé : restaurer tout l'ensemble de plugins coordonné indiqué dans le manifeste, sans mélanger les versions.
- **Spécificité de cette ferme :** si les bases ont déjà migré vers le schéma 5.0 (`BASE = 2026_08_03_00`), revenir à un code 4.x impose des bases d'avant cette migration. Aucune sauvegarde de ce type n'est identifiée pour l'instant.

---

Contrôle de couverture : chaque modification évoquée dans ce document (répertoire de sauvegarde, sauvegardes, test de restauration, essai isolé, maintenance, remplacement du code, `upgradeplugins` par site, changement de l'identifiant SQL) a sa ligne complète, de 1 à 8, dans le plan d'action. Toutes sont bloquées tant que les points ci-dessus ne sont pas levés.

**Pour avancer, renvoyez-moi les sorties filtrées des étapes 1 à 6 et la liste des versions cibles souhaitées.**

## spip-mutualisation-maintenance, cas 24 — ferme 5.0, tous les sites identiques — tirage 1

# Ferme SPIP : tous les sites affichent le même contenu depuis la mise à jour

## Constats

Ce que j'ai vérifié dans la copie de la racine partagée (`farm/`). Je n'ai pas accès au serveur réel.

**Ce qui est établi**

1. **Le cœur partagé est maintenant un SPIP 5.0.0-beta.** `ecrire/src/SpipVersion.php` déclare `VERSION = '5.0.0-beta'`, `BASE = 2026_08_03_00` et `PHP_MIN = '8.4.0'`. La racine contient aussi `vendor/spip/framework` (`spip-league/kernel`, `http-kernel`, `plugin-manager`…) et un `bin/spip` qui démarre un `SpipLeague\Component\Kernel\Kernel`. C'est la nouvelle architecture de SPIP 5.0. La « mise à jour du serveur » a donc très probablement fait passer le cœur de SPIP 4.x à 5.0 beta.
2. **Mutualisation facile n'est pas compatible avec cette version.** `mutualisation/paquet.xml` déclare `version="2.0.1"` et `compatibilite="[4.2.0;4.*]"`. SPIP 5.0 est hors de cette plage.
3. **La configuration de la ferme n'a pas bougé.** `config/mes_options.php` fait `require _DIR_RACINE.'mutualisation/mutualiser.php'`, calcule `$site` à partir de `HTTP_HOST` (en retirant `www.`), puis appelle `demarrer_site($site, [... 'repertoire' => 'sites', 'cookie_prefix' => true, 'table_prefix' => false, 'url_img_courtes' => true, 'creer_site' => true, 'creer_base' => true ...])`. Elle ne diffère de `mes_options.php.txt` que par `_SITES_ADMIN_MUTUALISATION = 'admin-ferme.example'`.
4. **La séparation des sites repose sur les mécanismes de démarrage de SPIP 4.** `demarrer_site()` (`mutualiser.php`) définit `_DIR_SITE` et `_SPIP_PATH`, puis appelle `spip_initialisation_core()` avec les répertoires `config/`, `IMG/`, `tmp/` et `local/` de `sites/<domaine>/`. Tout cela suppose que `config/mes_options.php` soit chargé, avec les constantes `_DIR_*` / `_NOM_*` et `_ECRIRE_INC_VERSION` déjà définies, **avant** l'initialisation par défaut de SPIP. Sinon, chaque requête retombe sur `config/`, `IMG/`, `local/` et `tmp/` à la racine, donc sur une seule connexion et un seul jeu de documents pour tous les domaines. C'est exactement le symptôme décrit.
5. **Les répertoires des sites existent toujours.** `sites/asso-exemple.org/` et `sites/mairie-exemple.fr/` ont chacun `config/connect.php`, `IMG/`, `local/` et `tmp/`. Les données des sites ne semblent donc pas perdues : c'est l'aiguillage vers elles qui ne fonctionne plus.
6. **Le plugin partagé `plugins/auto/cextras/v4.3.3` est lui aussi incompatible** : il déclare `compatibilite="[4.1.0;4.*]"`.

**Ce qui reste inconnu**

- Comment SPIP 5.0 a été installé (Composer, Git, archive), et la version précise qui tournait avant : la copie ne contient ni `composer.json`/`composer.lock` à la racine, ni `.git`, ni `ecrire/inc_version.php`.
- Si SPIP 5.0 charge encore `config/mes_options.php`, et à quel moment du démarrage du Kernel. **C'est une hypothèse à confirmer** (observations 3 et 4), pas un fait établi.
- Vers quelle base de données tous les sites sont envoyés aujourd'hui (celle de `config/connect.php` à la racine ? une autre ?).
- Si une mise à jour de la base vers le schéma 5.0 (`ecrire/?exec=upgrade` ou équivalent) a déjà été lancée sur cette base, ou sur celle d'un site.
- Le moteur de base de chaque site : la détection par liste blanche sur `sites/*/config/connect.php` ne trouve ni `mysql` ni `sqlite3`. Je n'ai pas affiché ces fichiers.
- Le serveur web, les versions de PHP (CLI et FPM), et l'existence d'une sauvegarde **vérifiée et restaurable** d'avant la mise à jour.

## Risques

- **Portée : toute la ferme.** Le code est partagé, donc tous les domaines servis par cette racine sont touchés, y compris le site d'administration `admin-ferme.example`.
- **Écritures croisées en cours.** Tant que la situation dure, toute action d'un rédacteur ou d'un visiteur atterrit dans la base et les répertoires communs, pas dans ceux de son site : connexion, article, document téléversé, forum, calcul du cache. Plus on attend, plus il faudra démêler de données.
- **Migration de base irréversible.** Si quelqu'un passe dans `ecrire/`, SPIP 5.0 peut proposer ou lancer la mise à jour de la base vers `BASE = 2026_08_03_00`. On ne peut pas annuler cette migration en restaurant seulement les fichiers : il faut restaurer la base. **Que personne ne lance de mise à jour de la base** ni `?exec=mutualisation&upgrade=oui` d'ici là.
- **Création de sites non voulue.** Avec `creer_site` et `creer_base` à `true` et le code d'activation par défaut (`ecureuil`), un aiguillage défaillant peut aboutir à des créations ou installations inattendues. À vérifier dans les journaux.
- **Hors périmètre.** SPIP 5.0 (beta) avec Mutualisation facile 2.x sort du périmètre pris en charge (SPIP 4.2–4.4) : je ne propose pas de « réparer » la ferme en 5.0. Faire tourner une ferme mutualisée sous 5.0 demanderait une version de Mutualisation compatible et une procédure étudiée à part. Il ne faut pas modifier `mutualiser.php` à la main sur la production.
- **Secret en clair.** `config/mes_options.php` contient un mot de passe SQL en clair (`_INSTALL_PASS_DB`). Je ne le reproduis pas ici. Ne transmettez ni ce fichier ni les `connect.php` sans les expurger.
- **Blocages.** Je ne fournis pas de commande de retour arrière prête à lancer tant que trois choses ne sont pas établies : la méthode d'installation, une sauvegarde d'avant la mise à jour vérifiée, et l'état du schéma des bases.

## Procédure proposée

Étapes en lecture seule, à lancer par vous, avec `spip_root` = le chemin réel de la racine partagée :

1. **Vérifier la version servie et la date du changement :**
   ```bash
   spip_root="$(realpath -e -- /chemin/de/la/racine)"; test "$spip_root" != /
   rg -n "const string VERSION|const int BASE" "$spip_root/ecrire/src/SpipVersion.php"
   ls -la --time-style=full-iso "$spip_root/ecrire/src/SpipVersion.php" "$spip_root/vendor" "$spip_root/composer.lock" 2>/dev/null
   php -v | head -1
   ```
2. **Identifier la méthode d'installation et l'état d'avant :**
   ```bash
   git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null && git -C "$spip_root" log -3 --format='%h %ci %s' && git -C "$spip_root" status --short | head -50
   test -f "$spip_root/composer.lock" && jq -r '.packages[] | select(.name|test("^spip/")) | "\(.name) \(.version) \(.source.reference // "")"' "$spip_root/composer.lock"
   ```
   Retrouver aussi ce qu'a fait « la mise à jour du serveur » : script de déploiement, `composer update`, gestionnaire de paquets. Et quelle version tournait avant.
3. **Confirmer le mécanisme de la panne** (code du cœur, pas de secrets) : `rg -n "mes_options" "$spip_root/ecrire" "$spip_root/vendor/spip" | head -40`. Cela montre si et quand SPIP 5.0 charge `config/mes_options.php`.
4. **Repérer où vont les écritures depuis la mise à jour** (sans lire de contenu) :
   ```bash
   ls -la --time-style=full-iso "$spip_root"/config/connect.php "$spip_root"/IMG "$spip_root"/local "$spip_root"/tmp 2>/dev/null
   find -P "$spip_root/IMG" "$spip_root/config" "$spip_root/sites" -xdev -type f -newermt 'AAAA-MM-JJ HH:MM' -printf '%TY-%Tm-%TdT%TH:%TM %p\n' 2>/dev/null | sort | head -200
   ```
   Remplacez `AAAA-MM-JJ HH:MM` par la date de la mise à jour.
5. **Chercher dans les journaux d'accès** des passages par `exec=upgrade`, `exec=install`, `exec=mutualisation` ou `upgrade=oui` depuis cette date, sur tous les domaines.
6. **Vérifier le schéma de chaque base concernée** (racine et chaque site), sans mot de passe dans la commande :
   ```bash
   mysql --defaults-extra-file=/chemin/protégé/client.cnf -N -e "SELECT nom, valeur FROM spip_meta WHERE nom IN ('version_installee','nom_site','adresse_site')" NOM_BASE
   ```
   Le préfixe de table attendu est `spip` (`table_prefix` à `false`). Pour SQLite, faites la même requête sur le fichier de `config/bases/`. Une valeur `version_installee` alignée sur 5.0 (`2026080300`) signale une base déjà migrée.
7. **Vérifier qu'une sauvegarde d'avant la mise à jour existe** et la tester : code + `config/` + `sites/*/` + une copie de chaque base, avec une empreinte et une restauration d'essai à l'écart.
8. **Ensuite seulement**, appliquer les lignes 1 à 7 du plan d'action, dans l'ordre :
   - lignes 1 et 2 dès que possible ;
   - ligne 3 une fois les étapes 2, 6 et 7 éclaircies ;
   - ligne 4 seulement si l'étape 6 a montré une base migrée ;
   - ligne 5 seulement si l'étape 4 a montré des écritures égarées ;
   - lignes 6 et 7 en dernier.

## Commandes

Plan d'action : toutes les commandes sont **proposées — non exécutées**. Les valeurs entre `<…>` sont à remplacer par des valeurs vérifiées.

| # | Action | Objectif | Préconditions | Périmètre exact | Impact | Sauvegarde / preuve | Commande proposée | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Mettre la ferme en maintenance | Stopper les écritures croisées entre sites | Serveur web identifié (Apache ou Nginx) ; liste des vhosts de la ferme connue | Tous les vhosts dont la racine est `spip_root` | Sites indisponibles pour le public (503), sauf IP d'administration | Copie de la conf vhost avant modification : `cp -a <fichier_vhost> <fichier_vhost>.avant-maintenance` | Nginx : ajouter dans chaque `server` de la ferme `if ($remote_addr != <IP_admin>) { return 503; }`, puis `nginx -t && systemctl reload nginx`. Apache : règle `RewriteCond %{REMOTE_ADDR} !^<IP_admin>$` + `RewriteRule ^ - [R=503,L]`, puis `apache2ctl configtest && systemctl reload apache2` | `curl -sI https://<chaque_domaine>/` renvoie 503 depuis l'extérieur | Remettre `<fichier_vhost>.avant-maintenance`, tester la conf, recharger |
| 2 | Figer l'état actuel comme preuve | Garder de quoi comparer et récupérer les écritures égarées | Ligne 1 faite ; espace disque vérifié (`df -hP`) ; `<dossier_preuves>` hors de `spip_root` | Code partagé, `config/`, `IMG/`, `local/` et `tmp/` à la racine, `sites/`, chaque base | Lecture seule pour SPIP ; consomme de l'espace disque | Empreintes SHA-256 des archives | `tar -C "$spip_root" -cpzf <dossier_preuves>/ferme-etat-5.0-$(date +%F).tgz .` ; pour chaque base : `mysqldump --defaults-extra-file=<client.cnf> --single-transaction --routines <NOM_BASE> > <dossier_preuves>/<NOM_BASE>-etat-5.0.sql` (SQLite : `sqlite3 <fichier.sqlite> ".backup '<dossier_preuves>/<site>.sqlite'"`) ; puis `sha256sum <dossier_preuves>/* > <dossier_preuves>/SHA256SUMS` | `tar -tzf` lisible ; `sha256sum -c` OK ; dumps non vides | Aucun : ne fait que créer des fichiers dans `<dossier_preuves>` |
| 3 | Revenir au code SPIP 4.x d'avant la mise à jour | Rétablir un cœur compatible avec Mutualisation 2.0.1 [4.2.0;4.*] et cextras [4.1.0;4.*] | Ligne 2 faite ; méthode d'installation établie (étape 2) ; version 4.x d'origine connue ; sauvegarde d'avant vérifiée et restaurable (étape 7) ; schéma des bases vérifié (étape 6) | Code partagé seulement (`ecrire/`, `prive/`, `squelettes-dist/`, `plugins-dist/`, `vendor/`, `bin/`, fichiers racine). **Ne pas toucher** `sites/`, `config/`, `mutualisation/`, `plugins/` | Toute la ferme | Archive de la ligne 2 | Selon la méthode, une seule : Git : `git -C "$spip_root" checkout <tag_ou_commit_4.x_d_avant>` ; Composer : remettre `composer.json`/`composer.lock` d'avant puis `composer install --no-dev -d "$spip_root"` ; archive : restaurer les fichiers du cœur depuis `<sauvegarde_avant>` dans un dossier vide, vérifier, puis échanger les dossiers par renommage | `SpipVersion`/`inc_version.php` affiche la version 4.x d'avant ; chaque domaine montre son propre contenu (Validation) | Restaurer le code depuis l'archive de la ligne 2 |
| 4 | *(Conditionnelle)* Restaurer une base déjà migrée vers 5.0 | Remettre un schéma 4.x pour une base touchée par la migration | L'étape 6 montre `version_installee` en 5.0 pour `<NOM_BASE>` ; dump d'avant la mise à jour vérifié ; ligne 2 faite (garde l'état migré) | Une seule base `<NOM_BASE>` à la fois | Le site concerné perd ce qui a été écrit dans cette base depuis le dump (à récupérer via la ligne 5) | Dump de la ligne 2 et dump d'avant | `mysql --defaults-extra-file=<client.cnf> <NOM_BASE> < <sauvegarde_avant>/<NOM_BASE>.sql` (SQLite : remettre le fichier `.sqlite` d'avant à sa place, site en maintenance) | `version_installee` revient à la valeur d'avant ; le site s'affiche sans demander de mise à jour | Recharger le dump de la ligne 2 |
| 5 | *(Conditionnelle)* Rapatrier les écritures égarées | Remettre dans le bon site ce qui a été écrit dans les répertoires ou la base communs | Liste des fichiers ou objets créés depuis la mise à jour (étape 4) ; site d'origine de chacun identifié (journaux, auteurs) | Fichiers listés un par un, vers `sites/<domaine>/IMG/` ; contenus de base à ressaisir ou réimporter un par un | Le site cible récupère le contenu | Ligne 2 | Pour chaque fichier vérifié : `cp -a -- "$spip_root/IMG/<chemin>" "$spip_root/sites/<domaine>/IMG/<chemin>"` (copie, sans suppression) ; contenus SQL : ressaisie depuis l'espace privé du bon site | Le document ou contenu apparaît sur le seul bon site | Supprimer la copie ajoutée dans `sites/<domaine>/IMG/` (liste tenue pendant l'opération) |
| 6 | Bloquer une nouvelle montée automatique de version | Empêcher que la prochaine mise à jour du serveur réinstalle SPIP 5.0 | Ligne 3 réussie ; mécanisme qui a fait la mise à jour identifié (étape 2) | Le dépôt, le fichier Composer ou le script de déploiement de la ferme | Les prochaines mises à jour restent sur 4.x | Copie du fichier modifié | Selon le mécanisme : contrainte `"spip/spip": "~<4.4.x>"` dans `composer.json` (vérifier ensuite avec `composer validate`), ou étiquette 4.x fixée dans le script de déploiement | Un essai à blanc (`composer update --dry-run` ou déploiement de test) ne propose plus 5.0 | Remettre la copie du fichier |
| 7 | Lever la maintenance | Remettre les sites en ligne | Validation réussie pour **tous** les domaines | Mêmes vhosts que la ligne 1 | Sites de nouveau publics | Conf de maintenance conservée à part | Remettre `<fichier_vhost>.avant-maintenance` sur chaque vhost, puis `nginx -t && systemctl reload nginx` (ou `apache2ctl configtest && systemctl reload apache2`) | `curl -sI` renvoie 200 sur chaque domaine, avec son propre contenu | Réappliquer la ligne 1 |

## Validation

En lecture seule, pour **chaque** site de `sites/` (au moins `asso-exemple.org` et `mairie-exemple.fr`) **et** pour `admin-ferme.example` :

- `curl -s https://<domaine>/ | grep -o '<title>[^<]*'` affiche un titre différent et attendu pour chaque domaine ;
- `curl -sI https://<domaine>/IMG/<document_connu_du_site>` (avec `url_img_courtes`) sert le document de **ce** site ;
- la requête `spip_meta` de l'étape 6 renvoie le bon `nom_site` / `adresse_site` et une `version_installee` 4.x pour chaque base ;
- les nouveaux fichiers de cache vont dans `sites/<domaine>/tmp/` et plus dans le `tmp/` de la racine (relancer le `find -newermt` de l'étape 4 avec une date postérieure à la ligne 3) ;
- `?exec=mutualisation` n'est accessible que sur `admin-ferme.example`. Vérifier aussi dans les journaux s'il y a eu des appels non authentifiés à `renouvelle_alea`, `dirliste` ou `dirsize` sur les autres domaines.

Pour les opérations elles-mêmes, voir la colonne « Contrôle de réussite » des lignes 1 à 7.

## Retour arrière

- **Déclencheurs :** un contrôle de la ligne 3 échoue ; un site affiche encore un autre contenu ; une erreur PHP apparaît après le retour au code 4.x ; un site demande une mise à jour de la base.
- **Unité de restauration :** le code partagé et l'ensemble des bases forment un seul bloc. On ne restaure pas les fichiers seuls si une base a migré.
- **Ordre :** garder la ligne 1 en place, puis appliquer le retour arrière de la ligne 3 (si une base a été restaurée, celui de la ligne 4 avant) et revenir à l'état figé par la ligne 2. Lever la maintenance (ligne 7) seulement après une validation complète.

**En pratique, tout de suite :** mettez la ferme en maintenance (ligne 1), interdisez toute mise à jour de base et toute action dans `ecrire/`, et envoyez-moi les sorties des étapes 1, 2 et 6. Ensuite je pourrai préciser la commande de retour au cœur 4.x (ligne 3) pour votre méthode d'installation.

## spip-mutualisation-maintenance, cas 24 — tirage 2

# Diagnostic : tous les sites de la ferme affichent le même contenu

Résumé : la mise à jour a installé le **cœur SPIP 5.0.0-beta** sous une ferme qui tourne avec **Mutualisation facile 2.0.1, déclarée compatible `[4.2.0;4.*]` seulement**. Le symptôme (même contenu et mêmes documents sur tous les domaines) correspond à une mutualisation qui ne s'applique plus : chaque requête serait alors servie avec la configuration, la base et l'`IMG/` de la racine partagée au lieu de ceux de `sites/<domaine>/`. Cette ferme sous SPIP 5.0 sort du périmètre pris en charge (SPIP 4.2–4.4). Je ne propose donc pas de « correctif » pour SPIP 5. Je propose de figer la situation, de mesurer l'impact, puis de revenir au cœur 4.x d'avant la mise à jour. Le retour arrière dépend de prérequis encore inconnus (voir plus bas).

Je n'ai rien exécuté et rien modifié. J'ai lu uniquement la copie de la racine partagée (`farm/`).

## Constats

Faits vérifiés dans la copie de la racine partagée :

| # | Constat | Preuve |
|---|---|---|
| F1 | Le cœur installé est **SPIP 5.0.0-beta** (`ID = 50000`, `BASE = 2026_08_03_00`, PHP 8.4.0 minimum) | `ecrire/src/SpipVersion.php` |
| F2 | L'installation est de type SPIP 5 / Composer : `vendor/spip/framework` (qui exige `spip-league/kernel`, `plugin-manager`, `http-kernel`…) et `bin/spip` qui démarre `SpipLeague\Component\Kernel\Kernel` | `vendor/spip/framework/composer.json`, `bin/spip` |
| F3 | Mutualisation facile **2.0.1**, `compatibilite="[4.2.0;4.*]"`, chargée par `require _DIR_RACINE.'mutualisation/mutualiser.php'` depuis `config/mes_options.php` (et non depuis `plugins/`) | `mutualisation/paquet.xml`, `config/mes_options.php` |
| F4 | Configuration effective : `demarrer_site($site, …)` avec `'repertoire' => 'sites'`, `$site` tiré de `HTTP_HOST` (sans `www.`, port remplacé par `_`), `cookie_prefix` à true, `table_prefix` à false, `url_img_courtes` à true, `creer_site` et `creer_base` à true. `_SITES_ADMIN_MUTUALISATION` vaut `admin-ferme.example` | `config/mes_options.php` |
| F5 | La mutualisation repose sur des mécanismes du démarrage de SPIP 4 : la garde `if (!defined('_ECRIRE_INC_VERSION')) return;`, la définition de `_DIR_SITE` et de `_SPIP_PATH`, puis l'appel à `spip_initialisation_core()` / `spip_initialisation()` avec les répertoires du site | `mutualisation/mutualiser.php` |
| F6 | Deux sites existent sur disque : `sites/mairie-exemple.fr` et `sites/asso-exemple.org`. Chacun a `config/connect.php`, `IMG/`, `local/` et `tmp/` | arborescence `sites/` |
| F7 | Le plugin partagé `plugins/auto/cextras/v4.3.3` déclare `[4.1.0;4.*]`, donc il est lui aussi incompatible avec 5.0 | `plugins/auto/cextras/v4.3.3/paquet.xml` |
| F8 | `config/mes_options.php` contient en clair des identifiants SQL d'installation (`_INSTALL_USER_DB` / `_INSTALL_PASS_DB`). Je ne les reproduis pas ici | `config/mes_options.php` |

Hypothèse principale, non vérifiée sur le serveur : sous SPIP 5.0, le démarrage passe par le noyau `spip-league`. La mutualisation 2.x ne parvient plus à rediriger SPIP vers `sites/<domaine>/` avant l'initialisation. Deux causes sont possibles :
- `config/mes_options.php` n'est plus chargé au même moment, ou sort tout de suite à cause de la garde `_ECRIRE_INC_VERSION` ;
- `spip_initialisation*()` n'a plus d'effet parce que les répertoires ont déjà été fixés.

Dans les deux cas, tous les domaines retombent sur la configuration de la racine. Je n'ai pas vérifié ce mécanisme dans le code de SPIP 5.0, et il ne change rien à la conduite à tenir : l'outil n'est pas compatible.

Inconnues :
- **Méthode d'installation exacte** du cœur avant et après la mise à jour (Composer, Git ou archive) : `composer.json` et `composer.lock` à la racine, `.git` et `inc_version.php` ne figurent pas dans la copie.
- **Version 4.x d'avant la mise à jour**, et existence d'une **sauvegarde vérifiée et restaurable** des fichiers partagés et des bases.
- **Moteur de base** de chaque site : le contrôle filtré renvoie `unknown` pour les deux `connect.php` de la copie, qui semblent être des fichiers de remplacement.
- Ce qui sert réellement le contenu « commun » : un `config/connect.php` à la racine, une base de la racine ou un `IMG/` à la racine ? Rien de cela n'existe dans la copie.
- **Les bases des sites ont-elles été migrées au schéma 5.0 ?** Ce serait le cas si un administrateur a été connecté ou si `exec=upgrade` / `?exec=mutualisation&upgrade=oui` a été lancé depuis la mise à jour.
- Des écritures (articles, documents envoyés) ont-elles eu lieu depuis la mise à jour, et à quel endroit ?
- Serveur web (Apache ou Nginx/PHP-FPM) et version de PHP.

## Risques

- **Rayon d'impact : toute la ferme.** Le cœur, `mutualisation/` et `plugins/auto/` sont partagés. Tous les domaines sont touchés, y compris le site d'administration `admin-ferme.example`.
- **Écritures au mauvais endroit.** Tant que la situation dure, ce qu'un rédacteur publie ou envoie peut aboutir dans la base ou l'`IMG/` communs. Les données se mélangent alors entre clients, et des documents d'un site peuvent devenir visibles sur un autre. C'est un **risque de confidentialité**.
- **Créations de sites parasites.** Avec `creer_site` et `creer_base` à true, une mutualisation qui fonctionne en partie pourrait créer des répertoires ou des bases pour des domaines de passage.
- **Migration de schéma.** Si une base de site est passée au schéma 5.0 (`BASE 2026_08_03_00`), remettre seulement les fichiers 4.x ne suffit pas pour ce site. Il faudra aussi restaurer sa base d'avant la mise à jour, en perdant les écritures postérieures (cf. ligne 5).
- **Plugins incompatibles.** cextras 4.3.3, et probablement d'autres plugins, ne sont pas compatibles 5.0.
- **SPIP 5.0.0 est une bêta.** Elle n'est pas adaptée à une ferme en production avec Mutualisation 2.x.
- **Identifiants en clair** dans `config/mes_options.php` (F8). Ne jamais coller ce fichier entier dans un ticket ou un chat.

**Blocages avant tout retour arrière** : la méthode d'installation est inconnue ; aucune sauvegarde 4.x vérifiée n'est identifiée ; on ne sait pas si les bases ont migré.

## Procédure proposée

1. **Figer** : suivre la ligne 1 du plan d'action (maintenance de toute la ferme). Personne ne doit se connecter à l'espace privé ni lancer de mise à niveau.
2. **Observations en lecture seule** (à lancer par vous, en remplaçant `<racine>` par le chemin réel) :
   ```bash
   spip_root="$(realpath -e -- <racine>)"; test "$spip_root" != /
   # Version et méthode d'installation
   rg -n "VERSION|BASE" "$spip_root/ecrire/src/SpipVersion.php" "$spip_root/ecrire/inc_version.php" 2>/dev/null
   git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null && git -C "$spip_root" status --short | head -50
   ls -la "$spip_root"/composer.json "$spip_root"/composer.lock 2>/dev/null
   # Ce qui existe à la racine (fichier de connexion, IMG commun), sans afficher le contenu
   ls -la "$spip_root/config/" "$spip_root/IMG" "$spip_root/tmp" 2>/dev/null | head -50
   # Écritures depuis la mise à jour (remplacer la date)
   find -P "$spip_root/IMG" "$spip_root/config" "$spip_root/sites" -xdev -maxdepth 3 -newermt '<date de la mise à jour>' -printf '%TY-%Tm-%Td %TH:%TM %p\n' 2>/dev/null | sort | head -100
   # Sites créés ou modifiés depuis la mise à jour
   find -P "$spip_root/sites" -xdev -mindepth 1 -maxdepth 1 -newermt '<date de la mise à jour>' -printf '%p\n'
   # Moteur de base de chaque site, sans afficher connect.php
   for f in "$spip_root"/sites/*/config/connect.php; do e=unknown; grep -q "'sqlite3'" "$f" && e=sqlite3; grep -q "'mysql'" "$f" && e=mysql; echo "$f: $e"; done
   # Sauvegardes disponibles (adapter le chemin)
   ls -la <dossier_sauvegardes> | tail -20
   ```
   Pour chaque base de site, relevez seulement la valeur de `version_installee`, sans afficher le reste :
   `SELECT valeur FROM spip_meta WHERE nom='version_installee';` (lancé avec `mysql --defaults-extra-file=<fichier_identifiants_protégé>`, jamais avec `-p<motdepasse>`). Une valeur proche de `2026080300` indique une base migrée au schéma 5.0.
3. **Mesurer l'impact** : listez les contenus et documents créés depuis la mise à jour, à la racine comme dans chaque site, et les éventuels nouveaux répertoires sous `sites/`.
4. **Préserver l'état actuel** : suivre la ligne 2 avant toute restauration.
5. **Préparer la restauration** : identifiez la version 4.x exacte d'avant la mise à jour et sa sauvegarde, puis testez cette sauvegarde à part (copie isolée et restauration à blanc). Si l'un de ces points reste inconnu, **arrêtez-vous là** et revenez vers moi avec les résultats.
6. Une fois les prérequis vérifiés, suivre les lignes 3, 4 et 5 dans l'ordre (la ligne 5 seulement pour les sites dont la base a migré), puis la ligne 6, puis la ligne 7.
7. **Plus tard** : une ferme sous SPIP 5.0 demande une étude à part (mutualisation adaptée au noyau 5.0, plugins compatibles), d'abord testée en préproduction et jamais directement sur la ferme en production.

## Commandes

Toutes les commandes sont **proposées — non exécutées**. Les `<…>` sont des valeurs à remplacer par des valeurs vérifiées.

| # | Action | Objectif | Préconditions | Périmètre exact | Impact | Sauvegarde / preuve | Commande proposée | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Mettre la ferme en maintenance | Empêcher de nouvelles écritures au mauvais endroit et l'exposition croisée des documents | Liste des vhosts de la ferme connue ; page 503 prête | Tous les vhosts qui pointent vers `<racine>` (serveur web de l'hôte) | Tous les sites sont indisponibles | Copie du fichier vhost avant modification : `cp -a <vhost.conf> <vhost.conf>.avant-maintenance` | Apache : `RewriteEngine On` + `RewriteRule ^ - [R=503,L]` dans les vhosts concernés, puis `apache2ctl configtest && systemctl reload apache2`. Nginx : `return 503;` dans les `server` concernés, puis `nginx -t && systemctl reload nginx` | `curl -sI https://<chaque domaine>/ \| head -1` renvoie 503 | Remettre `<vhost.conf>.avant-maintenance` en place, `configtest`/`-t`, puis `reload` |
| 2 | Conserver l'état actuel | Garder une trace des écritures faites depuis la mise à jour et pouvoir revenir sur la restauration | Ligne 1 faite ; espace disque suffisant (`df -hP`) | `<racine>` complète (sauf caches), base(s) de la racine si elles existent, base de chaque site | Aucun impact sur le service (lecture seule de la source) | Archive horodatée et son empreinte sha256 | `tar -C <racine> --exclude='./sites/*/tmp/cache' -czf <sauvegardes>/ferme-spip5-$(date +%F-%H%M).tgz . && sha256sum <archive> > <archive>.sha256` ; pour chaque base MySQL : `mysqldump --defaults-extra-file=<fichier_identifiants_protégé> --single-transaction <base> \| gzip > <sauvegardes>/<base>-spip5-$(date +%F).sql.gz` ; pour SQLite : `sqlite3 <fichier.sqlite> ".backup '<sauvegardes>/<site>-spip5.sqlite'"` | `sha256sum -c <archive>.sha256` ; `gzip -t` sur chaque dump ; `sqlite3 <copie> 'PRAGMA integrity_check;'` renvoie `ok` | Aucun (action sans effet sur le service) |
| 3 | Revenir au cœur 4.x d'avant la mise à jour | Rétablir un cœur compatible avec Mutualisation 2.0.1 | Ligne 2 vérifiée ; version 4.x exacte connue ; méthode d'installation connue ; sauvegarde des fichiers vérifiée et testée à part | Fichiers partagés du cœur dans `<racine>` (`ecrire/`, `prive/`, `squelettes-dist/`, `plugins-dist/`, `vendor/`, `bin/`, fichiers racine), **sans** `config/`, `sites/`, `mutualisation/` ni `plugins/` | Toute la ferme (code partagé) | Archive de la ligne 2 | Selon la méthode vérifiée, sans en mélanger deux. Git : `git -C <racine> checkout <tag-ou-commit-4.x>`. Composer : remettre `composer.json`/`composer.lock` d'avant la mise à jour, puis `composer install --no-dev` dans `<racine>`. Archive : restaurer `<sauvegarde-fichiers-4.x>` dans un répertoire de travail, puis l'échanger avec le cœur actuel | `rg -n 'spip_version_branche' <racine>/ecrire/inc_version.php` renvoie la branche 4.x attendue ; `test ! -f <racine>/ecrire/src/SpipVersion.php` (ou version 4.x) | Remettre les fichiers SPIP 5 depuis l'archive de la ligne 2 (pour revenir sur cette ligne seulement) |
| 4 | Remettre les plugins partagés d'avant la mise à jour | Revenir à des plugins compatibles 4.x (cextras 4.3.3 et autres) si la mise à jour les a changés | Ligne 3 faite ; inventaire `plugins/` d'avant la mise à jour connu ; on vérifie avec `diff` qu'ils ont réellement changé | `<racine>/plugins/` (dont `plugins/auto/`), seulement les répertoires modifiés | Toute la ferme | Archive de la ligne 2 | Restaurer depuis `<sauvegarde-fichiers-4.x>` les seuls répertoires `plugins/<…>` modifiés | `rg -n 'version=\|compatibilite=' <racine>/plugins/*/paquet.xml <racine>/plugins/auto/*/*/paquet.xml` montre les versions d'avant la mise à jour | Restaurer ces répertoires depuis l'archive de la ligne 2 |
| 5 | (Conditionnelle) Restaurer la base des sites migrés au schéma 5.0 | Réaligner le schéma avec le cœur 4.x | `version_installee` > version 4.x pour ce site ; dump d'avant la mise à jour vérifié ; ligne 2 faite pour cette base | La base du site `<domaine>` seulement, une ligne d'exécution par site | Ce site seul ; les écritures faites depuis la mise à jour sont perdues dans la base (on peut les récupérer depuis la ligne 2) | Dump de la ligne 2 pour ce site | MySQL : `gunzip -c <dump-avant-maj>.sql.gz \| mysql --defaults-extra-file=<fichier_identifiants_protégé> <base_du_site>` ; SQLite : remettre `<copie-avant-maj>.sqlite` à la place du fichier de `sites/<domaine>/config/bases/` | `version_installee` revient à la valeur 4.x ; le nombre d'articles correspond à la sauvegarde | Réimporter le dump de la ligne 2 pour ce site |
| 6 | Vider les caches calculés sous SPIP 5 | Éviter de servir des squelettes compilés ou du contenu mis en cache par mauvais aiguillage | Lignes 3 à 5 faites ; ligne 2 conservée | `<racine>/tmp/cache/` et `sites/<domaine>/tmp/cache/` de chaque site (chemins résolus par `realpath -e`, tous sous `<racine>`) | Caches seulement ; recalcul plus lent au premier passage | Ligne 2 | Pour chaque chemin vérifié : `find -P "<chemin_cache_vérifié>" -xdev -mindepth 1 -maxdepth 1 -print` (relecture), puis déplacer le contenu vers `<quarantaine>/` (`mv`) plutôt que le supprimer | Les pages se recalculent ; plus de contenu commun d'un domaine à l'autre | Remettre le contenu depuis `<quarantaine>/` |
| 7 | Lever la maintenance | Remettre la ferme en service | Validation faite (section suivante) sur un accès de test, ou après lecture des domaines en liste blanche | Les mêmes vhosts que la ligne 1 | Tous les sites redeviennent accessibles | Fichiers `<vhost.conf>.avant-maintenance` | Remettre les fichiers `.avant-maintenance`, puis `apache2ctl configtest && systemctl reload apache2` ou `nginx -t && systemctl reload nginx` | Chaque domaine renvoie 200 avec **son** contenu | Repasser par la ligne 1 |

Les contenus et documents publiés au mauvais endroit depuis la mise à jour, repérés aux étapes 3 et 4 de la procédure, ne doivent pas être recopiés automatiquement. Il faut d'abord établir, site par site, à qui ils appartiennent. Je vous proposerai alors des lignes d'action dédiées.

## Validation

En lecture seule, sur **chaque** site (`mairie-exemple.fr`, `asso-exemple.org`, le site d'administration `admin-ferme.example` et tout autre site présent sous `sites/`), et non seulement sur le site d'administration :

- `curl -s https://<domaine>/ | rg -o '<title>[^<]*</title>'` : le titre diffère d'un domaine à l'autre et correspond au site attendu.
- `curl -sI https://<domaine>/spip.php?page=sommaire | rg -i '^(HTTP|x-spip|composed-by)'` : la version annoncée est la 4.x attendue.
- Un document connu de chaque site (`https://<domaine>/IMG/<fichier connu>`) est servi depuis `sites/<domaine>/IMG/`, et le document d'un site n'est **pas** accessible depuis un autre domaine.
- `version_installee` de chaque base reste égale à la valeur 4.x (relevée seule, sans afficher le reste de `spip_meta`).
- Aucun nouveau répertoire n'est apparu sous `sites/` : refaire le `find … -newermt` de la procédure.
- Pour les contrôles propres à chaque mutation, voir la colonne « Contrôle de réussite » des lignes 1 à 7.

## Retour arrière

- **Déclencheurs** : une version 4.x qui ne démarre pas, un site en erreur, une base qui demande une mise à niveau, ou des contenus encore partagés entre domaines après les lignes 3 à 6.
- **Unité de restauration** : le **couple fichiers partagés + base de chaque site**, pris au même instant. On ne remet jamais les fichiers 4.x devant une base au schéma 5.0, ni l'inverse.
- **Ordre** : remettre la ferme en maintenance (ligne 1), puis appliquer la colonne « Retour arrière » des lignes 6, 5, 4 et 3, dans cet ordre inverse. On revient alors à l'état conservé à la ligne 2.

## Vérification de couverture

Chaque action qui modifie l'état dans cette réponse a sa ligne complète dans le plan :
- maintenance : ligne 1 ;
- conservation de l'état actuel : ligne 2 ;
- retour du cœur 4.x : ligne 3 ;
- retour des plugins partagés : ligne 4 ;
- restauration conditionnelle des bases migrées : ligne 5 ;
- caches : ligne 6 ;
- levée de la maintenance : ligne 7.

Le rapatriement des contenus mal placés est volontairement laissé hors du plan tant que leur appartenance n'est pas établie.

## spip-mutualisation-maintenance, cas 25 — ferme 4.4.22 (contrôle)

# Mise à jour des plugins partagés : préparation

**Conclusion : la mise à jour ne peut pas encore être lancée.** Plusieurs conditions d'arrêt sont réunies (voir « Risques ») : moteur de base inconnu pour les deux sites, provenance du code inconnue, dépendances de `cextras` introuvables dans la copie, version cible non fixée, aucune sauvegarde vérifiée. Voici donc les constats, les vérifications en lecture seule à faire d'abord, et le plan d'actions complet avec des emplacements à compléter. Aucune commande de ce document n'a été exécutée. Les actions du tableau sont **proposées — non exécutées** et restent bloquées tant que leurs préconditions ne sont pas levées.

Toutes les observations viennent de la copie de la racine partagée qui m'a été fournie (désignée `<racine_ferme>` ci-dessous). Je n'ai pas eu accès au serveur.

## Constats

### Faits vérifiés (dans la copie)

| Élément | Constat | Preuve |
|---|---|---|
| Cœur SPIP partagé | `4.4.22` (`_SPIP_VERSION_ID` 40422) | `ecrire/inc_version.php:464-465` |
| Chargement de Mutualisation facile | `config/mes_options.php` fait `require _DIR_RACINE.'mutualisation/mutualiser.php'` : le plugin est dans `mutualisation/` à la racine, pas dans `plugins/` | `config/mes_options.php:14-18` |
| Version de Mutualisation facile | `2.0.1`, compatibilité `[4.2.0;4.*]`, `<chemin path="" type="aucun" />` | `mutualisation/paquet.xml:4,6,23` |
| Calcul de `$site` | `HTTP_HOST` sans le `www.` | `config/mes_options.php:23-24` |
| Appel `demarrer_site()` | `'repertoire' => 'sites'`, `'table_prefix' => false`, `'cookie_prefix' => true`, `'url_img_courtes' => true` | `config/mes_options.php:124-134` |
| Site d'administration | `_SITES_ADMIN_MUTUALISATION` = `admin-ferme.example` | `config/mes_options.php:11` |
| Sites présents sous `sites/` | `asso-exemple.org` et `mairie-exemple.fr`. Chacun a `config/`, `IMG/`, `local/`, `tmp/` et un fichier `config/connect.php` | arborescence |
| Plugins partagés | Un seul trouvé : `plugins/auto/cextras/v4.3.3/` (`cextras` 4.3.3, compatibilité `[4.1.0;4.*]`) | `plugins/auto/cextras/v4.3.3/paquet.xml:2-6` |
| Dépendances de `cextras` 4.3.3 | `necessite` : `saisies` `[4.2.0;]` et `yaml` `[3.0.0;]`. `utilise` : `verifier` `[1.16.2;]` et `crayons` `[2.3.0;]` | `paquet.xml:29-32` |
| Chemins de plugins supplémentaires | Aucune définition active de `_DIR_PLUGINS_SUPPL` ni de `_SPIP_PATH` dans `config/` ni `sites/`. `dossier_squelettes` n'apparaît que dans des commentaires | recherche ciblée |

### Inconnues

- **Moteur de base de chaque site.** Les deux `config/connect.php` font 2 lignes et ne contiennent aucun appel `spip_connect_db`. J'ai seulement compté les lignes et cherché le motif, sans afficher le contenu. Il peut s'agir d'une installation incomplète, d'un fichier de substitution, ou d'une connexion déplacée via `_DIR_CONNECT` / `_FILE_CONNECT_INS`. La valeur `_INSTALL_SERVER_DB` de la configuration globale ne s'applique qu'à l'installation : elle ne prouve rien sur un site déjà installé.
- **Provenance du code.** La copie ne contient ni `.git`, ni `composer.json`/`composer.lock`, ni `plugins-dist/`. La disposition `plugins/auto/<prefixe>/v<version>/` fait penser à SVP, mais c'est une piste, pas une preuve.
- **Où sont `saisies` et `yaml`.** Ils sont obligatoires pour `cextras` mais absents de la copie. Ils sont peut-être dans `plugins-dist/` ou ailleurs sur le serveur. Sinon `cextras` ne peut pas être actif.
- **Plugins actifs sur chaque site**, et état de leurs schémas (`spip_meta`).
- **Version cible** de chaque plugin à mettre à jour. Elle n'a pas été donnée et ses métadonnées n'ont pas été vérifiées.
- **Site d'administration.** Le domaine `admin-ferme.example` n'a pas de répertoire sous `sites/` dans la copie. Je ne sais pas où et comment il est servi.
- Pile web/PHP (Apache ou Nginx/PHP-FPM, versions), comptes propriétaires réels sur le serveur, espace disque et inodes libres.
- Si `?exec=mutualisation` est joignable sans authentification sur les vhosts des sites.

### Anomalies

1. **Un mot de passe de base de données est écrit en clair dans `config/mes_options.php`** (`_INSTALL_USER_DB` / `_INSTALL_PASS_DB`, l. 57-58). Ce fichier est partagé et se trouve sous la racine web. Je ne reproduis pas la valeur. Attention : ma recherche filtrée a quand même fait passer ces lignes dans mon contexte. Considérez donc cet identifiant comme exposé (voir action 12).
2. Les deux `connect.php` ne contiennent pas d'appel de connexion (voir ci-dessus).
3. Dans la copie, tout est en `664`/`775` (écriture pour le groupe) et appartient à un seul compte. Ces droits ne valent que pour la copie : à comparer avec ceux du serveur. Je ne propose aucun `chmod`/`chown`.

## Risques

- **Toute la ferme est touchée.** `plugins/auto/` est partagé : changer un plugin change le code de tous les sites qui l'activent. Il est impossible de tester d'abord « sur un seul site » en production. Le seul vrai banc d'essai est une copie isolée de la ferme (action 5).
- **Dépendances.** Une nouvelle version de `cextras` peut exiger des versions plus récentes de `saisies` et `yaml`. Ces plugins sont partagés et leur emplacement est inconnu. Les mettre à jour touche aussi les sites où `cextras` est inactif.
- **Schéma.** `cextras` crée des champs dans les tables éditoriales. Une fois la mise à jour de schéma faite sur un site, revenir à l'ancien code ne suffit plus : il faut aussi restaurer la base de ce site.
- **Vidage des caches.** La route `upgradeplugins` vide largement les caches du site visé : `_DIR_CACHE`, `cache-css`/`cache-js`, `_DIR_SKELS`, `plugin_xml_cache.gz`, etc. C'est une mutation à part entière. Le bouton « Upgrader tout » le fait pour tous les sites d'un coup, sans point de contrôle entre eux : ne pas l'utiliser.
- **Points bloquants, avant toute commande prête à lancer :** moteur de base inconnu, provenance inconnue, emplacement de `saisies`/`yaml` inconnu, liste des plugins actifs par site inconnue, version cible non vérifiée, aucune sauvegarde vérifiée et restaurable.
- **Exposition.** `renouvelle_alea`, `dirliste` et `dirsize` de `?exec=mutualisation` s'exécutent sans authentification. À vérifier pendant l'inventaire.

## Procédure proposée

### Phase A : observations en lecture seule (à lancer par vous, sur le serveur)

1. Fixer et valider la racine réelle :
   ```bash
   spip_root="$(realpath -e -- '<racine_ferme_réelle>')"
   test -n "$spip_root" && test "$spip_root" != /
   test -f "$spip_root/ecrire/inc_version.php" && test -f "$spip_root/spip.php"
   mutu_dir="$(realpath -e -- "$spip_root/sites")"
   case "$mutu_dir" in "$spip_root"/*) ;; *) echo 'STOP: sites/ sort de la racine' >&2 ;; esac
   find -P "$mutu_dir" -xdev -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort
   find -P "$mutu_dir" -xdev -mindepth 2 -maxdepth 2 -type l -printf '%p -> %l\n'
   ```
2. Établir la provenance du cœur et des plugins :
   ```bash
   git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null; git -C "$spip_root" status --short 2>/dev/null
   ls -1 "$spip_root"/composer.json "$spip_root"/composer.lock 2>/dev/null
   find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -mindepth 1 -maxdepth 4 \
     -type f -name paquet.xml -print 2>/dev/null
   for p in "$spip_root"/plugins/auto/*/*/; do
     git -C "$p" describe --tags --always --dirty 2>/dev/null && echo "  ^ git: $p"
   done
   ```
3. Trouver `saisies`, `yaml`, `verifier` et `crayons`, avec leur version et leur compatibilité :
   ```bash
   find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -maxdepth 4 -name paquet.xml -print0 2>/dev/null \
     | xargs -0 rg -n '^[[:space:]]*(prefix|version|compatibilite|schema)=|<necessite|<utilise'
   ```
4. Identifier le moteur de base de chaque site, **sans afficher le fichier**. Lancez ceci de votre côté et ne me renvoyez que le résultat filtré :
   ```bash
   for s in asso-exemple.org mairie-exemple.fr; do
     c="$mutu_dir/$s/config/connect.php"
     e=$(rg -o "spip_connect_db\(.*" "$c" 2>/dev/null | rg -oi "'(mysql|sqlite3|pg)'" | tr -d "'" | sort -u)
     echo "$s: ${e:-unknown} ; bases_sqlite=$(ls "$mutu_dir/$s/config/bases/"*.sqlite 2>/dev/null | wc -l)"
   done
   rg -l '_DIR_CONNECT|_FILE_CONNECT_INS' "$spip_root/config" "$mutu_dir" --glob '*.php'
   ```
5. Lister les plugins actifs et l'état du schéma de chaque site, à partir de sa base (`spip_meta`, entrées `plugin` et `<prefixe>_base_version`) ou de la page d'administration du plugin sur chaque site. Si vous lisez `tmp/meta_cache.php`, horodatez le résultat et recontrôlez-le en base.
6. Repérer le site d'administration `admin-ferme.example` : son vhost et la racine qu'il sert. Filtrez la sortie de configuration (`server_name`/`root`/`fastcgi_pass`, ou `apache2ctl -S`).
7. Mesurer la capacité et les droits : `df -hP`, `df -iP`, propriétaires et droits de `plugins/auto/` et de `sites/*`, compte qui exécute PHP-FPM ou Apache.
8. Fixer la cible de chaque plugin. Lire `paquet.xml` dans l'archive ou le tag exact de la cible (version, compatibilité avec 4.4.22, `necessite`/`utilise`, `schema`) et remplir la matrice ci-dessous.

| Plugin | Actuelle → cible | Chemin chargé | Provenance | Sites actifs | Compat. SPIP 4.4.22 | Dépendances | Modifs locales | Changement de schéma |
|---|---|---|---|---|---|---|---|---|
| cextras | 4.3.3 → `<cible>` | `plugins/auto/cextras/v4.3.3` | `<à établir>` | `<à établir>` | actuelle `[4.1.0;4.*]` ; cible `<à vérifier>` | saisies, yaml (+ verifier, crayons) | `<à établir>` | `<à vérifier>` |
| saisies / yaml / autres | `<à établir>` | `<à établir>` | | | | | | |

### Phase B : mutations, une fois les blocages levés

Quand chaque point de la phase A est résolu, suivre dans l'ordre les **lignes 1 à 4** du plan (sauvegarde et contrôles), puis la **ligne 5** (essai sur une copie isolée), puis la **ligne 6** (création de la base d'exercice et restauration de la sauvegarde). Ne passer aux **lignes 7 à 11** (production) qu'après la réussite des lignes 5 et 6. Pour les sites, l'ordre est d'abord `asso-exemple.org` (ligne 9), puis `mairie-exemple.fr` (ligne 10). S'arrêter à la première erreur inexpliquée. La **ligne 12** (rotation de l'identifiant exposé) se planifie à part, hors de la fenêtre de mise à jour. La **ligne 13** (nettoyage de la base d'exercice) vient en dernier, après votre accord.

## Commandes

Toutes les commandes ci-dessous sont **proposées — non exécutées**. Les `<…>` sont des valeurs à remplacer par des valeurs vérifiées. Chaque bloc commence par les contrôles de chemins de `backup-rollback.md` §2 : racine résolue, différente de `/`, sauvegarde hors de la racine web.

| # | Action | Objectif | Préconditions | Portée exacte | Impact | Sauvegarde / preuve | Commande proposée | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Créer le répertoire de sauvegarde | Stocker la sauvegarde hors de la racine web | `backup_parent` résolu, hors `spip_root`, différent de `/` ; espace disque suffisant | `<backup_parent>/<horodatage UTC>/` et son sous-répertoire `databases/` | Hôte (disque) | Manifeste créé à cette étape | `( umask 077; mkdir -- "$backup_root" "$backup_root/databases" )` | `stat -c '%a %U' "$backup_root"` donne `700` et le bon compte | `rmdir` des deux répertoires vides, avec votre accord |
| 2 | Sauvegarder la base de `asso-exemple.org` | Avoir un point de restauration pour ce site | Moteur identifié (phase A.4). MySQL/MariaDB : fichier d'options `0600` fourni par vous, `--no-login-paths` pris en charge, moteurs de tables vérifiés (`--single-transaction` seulement si tout est InnoDB). SQLite : chemin exact de la base | La base de ce site uniquement | Lecture seule pour la source ; écriture dans `$backup_root/databases/` | Fichier de sauvegarde, code de sortie, taille, SHA256 dans `SHA256SUMS` | MySQL : la séquence de `backup-rollback.md` §3, avec `"$dump_client" --defaults-file="$client_opts" --no-login-paths --single-transaction --quick --routines --events --triggers --no-create-db "<db_asso>" >"$dump_path"` (sous `umask 077; set -C`). SQLite : `sqlite3 "<base_asso>" ".timeout 5000" ".backup '$backup_root/databases/asso-exemple.org.sqlite'"` | Code de sortie 0, fichier non vide. SQLite : `PRAGMA integrity_check` = `ok` et `foreign_key_check` vide | Rien à défaire. Supprimer un fichier partiel seulement avec votre accord |
| 3 | Sauvegarder la base de `mairie-exemple.fr` | Idem, pour ce site | Idem ligne 2, pour ce site | La base de ce site uniquement | Idem | Idem | Idem ligne 2, avec `<db_mairie>` / `<base_mairie>` | Idem ligne 2 | Idem ligne 2 |
| 4 | Archiver les fichiers persistants et le code partagé, puis calculer les sommes de contrôle | Avoir une unité de restauration cohérente | Lignes 1 à 3 réussies ; liste explicite des chemins validée ; liens symboliques inattendus examinés | `config/mes_options.php`, `mutualisation/`, `plugins/auto/` (répertoires concernés, et tous les plugins dont on change la version), `plugins-dist/` si concerné, et pour chaque site `sites/<site>/config/` et `sites/<site>/IMG/` (et `squelettes/`, `plugins/` s'ils existent). **Pas** `local/` ni `tmp/` | Hôte (disque) | `tar` + `sha256sum`, manifeste : heure UTC, hôte, sites, chemins, versions, préfixe de tables `spip`, tailles, versions des outils, copie hors hôte | `tar -C "$spip_root" -cf "$backup_root/code-partage.tar" -- config/mes_options.php mutualisation plugins/auto/<prefixe>…` et un `tar` par site, puis `sha256sum -- "$backup_root"/*.tar "$backup_root"/databases/* >>"$backup_root/SHA256SUMS"`, puis copie hors hôte vers `<destination>` | `tar -tf` sans erreur ; `sha256sum -c "$backup_root/SHA256SUMS"` OK ; présence de la copie hors hôte confirmée | Rien à défaire en production. Supprimer les archives seulement avec votre accord |
| 5 | Essayer la mise à jour sur une copie isolée de la ferme | Valider la cible, les dépendances et le schéma hors production | Lignes 1 à 4 réussies ; environnement isolé (autre hôte ou autre conteneur, pas d'accès à la production) avec SPIP 4.4.22, les mêmes chemins de plugins et des copies des bases des sites qui activent le plugin | La copie isolée uniquement | Aucun sur la production | Journaux de l'essai, versions observées, sommes de contrôle de l'archive cible | Déposer l'archive cible exacte (`<archive_cible>`, somme vérifiée) dans le même chemin relatif que la production, puis lancer `upgradeplugins` site par site sur la copie | Plugin à la version cible, dépendances actives, schéma à jour, journaux propres, formulaires et champs extras fonctionnels sur chaque site de la copie | Détruire la copie isolée |
| 6 | Exercice de restauration | Prouver que les sauvegardes des lignes 2 à 4 se restaurent | Point d'arrivée isolé, d'une identité différente de la source ; base vide créée d'avance ; compte d'import limité à cette base ; fichier d'options dédié | La base d'exercice et la copie de site isolée uniquement | Aucun sur la production | Comparaison des identités des deux serveurs, résultats `CHECK TABLE` / `integrity_check`, compte rendu du test SPIP | MySQL : la séquence complète de `backup-rollback.md` §3 « Restore verification », avec `restore_db=<base_exercice>` et `table_prefix=spip`. SQLite : copier la sauvegarde sous un nouveau nom isolé, puis `PRAGMA integrity_check` | `spip_meta` présente, `CHECK TABLE` OK ; site restauré navigable dans la copie | Supprimer la base d'exercice (ligne 13) |
| 7 | Activer la maintenance sur toute la ferme | Bloquer les écritures pendant la bascule | Lignes 5 et 6 réussies ; méthode de maintenance choisie et testée (page de maintenance au niveau du vhost, `<méthode>`) ; créneau annoncé | Tous les vhosts de la ferme (le code est partagé) | Tous les sites indisponibles en écriture | Configuration d'origine sauvegardée avant modification | `<commande de maintenance validée, ex. activation d'une config vhost de maintenance + rechargement>` | Chaque domaine répond par la page de maintenance ; l'accès de l'opérateur reste possible | Revenir à la configuration d'origine sauvegardée et recharger |
| 8 | Mettre en place le code cible du plugin (et de ses dépendances si la matrice l'exige) | Changer le code partagé en une seule fois, sans perdre l'ancien | Ligne 7 active ; même provenance que l'existant (archive/SVP → archive officielle exacte ; Git → tag vérifié du même dépôt ; Composer → changement de lock relu) ; ancienne version archivée (ligne 4) | `plugins/auto/<prefixe>/` (et les dépendances listées dans la matrice) | **Tous les sites** qui activent ces plugins | Somme de contrôle de l'archive cible ; ancien répertoire **conservé** tel quel | Archive/SVP : extraire `<archive_cible>` vers un nouveau répertoire, par exemple `plugins/auto/cextras/v<cible>/`, sans supprimer `v4.3.3/`. Git : `git -C <chemin> checkout <tag_vérifié>` | `rg -n 'version=' <nouveau_chemin>/paquet.xml` donne la cible ; l'ancien répertoire est intact | Remettre en service l'ancien répertoire (`v4.3.3/`) ou l'ancien commit. Si un schéma a déjà migré : voir lignes 9 et 10 |
| 9 | Mettre à jour les plugins de `asso-exemple.org` | Appliquer la mise à jour de schéma et l'activation sur ce site | Ligne 8 réussie ; journaux et preuves du site capturés **avant** le vidage de caches ; sauvegarde de la ligne 2 vérifiée | `asso-exemple.org` uniquement | Vide les caches de ce site ; modifie son schéma | Copie des journaux `tmp/log/` du site, avant et après | Ouvrir, authentifié sur ce site, `https://asso-exemple.org/ecrire/?exec=mutualisation&upgradeplugins=oui&secret=<secret généré par le tableau de bord>` (lien du tableau de bord, site par site ; **pas** « Upgrader tout ») | Version cible affichée, `<prefixe>_base_version` à jour dans `spip_meta`, aucune erreur dans les journaux, contrôles fonctionnels OK | Restaurer la base de la ligne 2 **et** l'ancien code (ligne 8). Si d'autres sites ont déjà migré, les restaurer aussi (même point de contrôle) |
| 10 | Mettre à jour les plugins de `mairie-exemple.fr` | Idem, pour ce site | Ligne 9 validée ; mêmes préconditions, pour ce site | `mairie-exemple.fr` uniquement | Idem | Idem | Idem ligne 9, avec `https://mairie-exemple.fr/…` | Idem ligne 9 | Restaurer la base de la ligne 3 + l'ancien code, et la base de `asso-exemple.org` (ligne 2) si elle a migré |
| 11 | Sortir de la maintenance | Remettre la ferme en service | Validation complète de tous les sites (voir « Validation ») | Tous les vhosts | Fin de l'indisponibilité | Journal de validation | Inverse de la ligne 7 | Chaque domaine sert ses pages normales | Réactiver la maintenance (ligne 7) |
| 12 | Faire tourner l'identifiant de base exposé et le sortir de `config/mes_options.php` | Corriger l'anomalie 1 | Comptes qui l'utilisent recensés ; fenêtre dédiée, **hors** de la fenêtre de mise à jour ; sauvegarde de `config/mes_options.php` | Le compte SQL nommé par `_INSTALL_USER_DB` et `config/mes_options.php` | Installations de nouveaux sites, et peut-être d'autres usages de ce compte | Copie de `mes_options.php` (ligne 4) | Changement de mot de passe sur le serveur SQL, fait par vous (sans mot de passe dans la ligne de commande), puis retrait ou externalisation de la constante | Les sites existants fonctionnent ; l'ancien secret est refusé | Remettre le `mes_options.php` sauvegardé ; changer de nouveau le mot de passe |
| 13 | Nettoyer la base d'exercice | Libérer l'environnement d'exercice | Résultats de la ligne 6 consignés ; votre accord | La base d'exercice uniquement, sur le point d'arrivée isolé | Aucun sur la production | Compte rendu de la ligne 6 | `DROP` de `<base_exercice>` sur le point d'arrivée isolé seulement, avec son fichier d'options dédié | La base d'exercice n'existe plus ; la production est intacte | Réimporter depuis la sauvegarde si besoin |

## Validation

Contrôles en lecture seule, pour **chaque** site (`asso-exemple.org`, `mairie-exemple.fr`, et le site d'administration `admin-ferme.example`) :

| Site | Plugin actif | Version observée | Dépendances actives | Schéma à jour | Contrôles fonctionnels | Journaux propres | Résultat |
|---|---|---|---|---|---|---|---|
| asso-exemple.org | | | | | | | |
| mairie-exemple.fr | | | | | | | |
| admin-ferme.example | | | | | | | |

- Pages publiques en HTTP 200, connexion à l'espace privé, page des plugins sans erreur, formulaires et champs extras, une image de `IMG/` (URL courtes actives), tâches planifiées, journaux PHP et SPIP.
- Sites où le plugin est **inactif** : vérifier qu'une dépendance partagée mise à jour ne les casse pas.
- Les contrôles propres à chaque mutation sont ceux des lignes 1 à 11. Ne jamais conclure à partir du seul site d'administration.

## Retour arrière

**Déclencheurs :** toute erreur inexpliquée d'activation, de schéma ou de journal pendant les lignes 9 ou 10 ; une régression sur un site où le plugin est inactif ; un contrôle de validation en échec avant la ligne 11.

**Unité de restauration :** le point de contrôle de la ligne 4, désigné par son manifeste et `SHA256SUMS`, pas par « la dernière sauvegarde ». Il comprend le code partagé des plugins concernés, plus la base et les fichiers persistants de chaque site touché.

- Code basculé mais aucun schéma migré : appliquer le retour arrière de la ligne 8.
- Un site migré : appliquer le retour arrière des lignes 9 et 10 dans l'ordre inverse (d'abord la 10, puis la 9), puis celui de la ligne 8. Ne jamais restaurer une base d'un point de contrôle différent de celui du code qu'elle attend. Si une dépendance partagée a changé, restaurer tout le jeu de plugins du manifeste.
- État incertain : restaurer le jeu de code connu comme sain et **toutes** les bases touchées.
- Rester en maintenance (ligne 7) jusqu'à la fin de la validation après restauration.

---

**Ce qu'il me faut pour la suite :** les résultats filtrés des étapes A.1 à A.8, en particulier le moteur de base et l'emplacement de la connexion pour chaque site, la provenance des plugins, l'emplacement de `saisies`/`yaml`, les plugins actifs par site et les versions cibles. Avec ces éléments, je remplacerai les `<…>` par des valeurs vérifiées.

## spip-mutualisation-maintenance, cas 26 — ferme 4.4.22, passage à SPIP 5.0 — tirage 1

# Passage de la ferme à SPIP 5.0 : préparation

**Conclusion : on ne peut pas encore préparer la bascule vers SPIP 5.0.** Deux choses bloquent. Mutualisation facile 2.0.1, installé sur la ferme, se déclare compatible `[4.2.0;4.*]`, donc pas avec SPIP 5.0. Et la procédure que je suis ne couvre que SPIP 4.2 à 4.4 : pour la 5.0, il faut une procédure recherchée à part. Ci-dessous : l'état vérifié de la ferme, ce qui reste inconnu, les observations en lecture seule à faire maintenant, et le plan d'actions futur. Ce plan est **bloqué** : aucune commande qui modifie quoi que ce soit n'est prête à être lancée.

Toutes les constatations ci-dessous portent sur la copie de la racine partagée qui m'a été fournie, pas sur le serveur en production.

## Constatations

### Socle partagé (touche toute la ferme)

| Élément | Constat vérifié | Preuve |
|---|---|---|
| Cœur SPIP | `4.4.22` (`_SPIP_VERSION_ID` 40422), PHP accepté de `7.4.0` à `8.5.99` | `ecrire/inc_version.php:464-465`, `:40-41` |
| Chargement de Mutualisation | `config/mes_options.php` fait `require _DIR_RACINE.'mutualisation/mutualiser.php'` : le plugin est à la racine, dans `mutualisation/`, pas dans `plugins/` | `config/mes_options.php:14-18` |
| Version de Mutualisation facile | `2.0.1`, stable, `compatibilite="[4.2.0;4.*]"`, `<chemin path="" type="aucun" />` | `mutualisation/paquet.xml` |
| Dossier des sites (`repertoire`) | `'sites'`, passé à `demarrer_site()` | `config/mes_options.php:133` |
| Site d'administration | `_SITES_ADMIN_MUTUALISATION` = `admin-ferme.example` | `config/mes_options.php:11` |
| Calcul de `$site` | `HTTP_HOST` sans `www.`, avec redirection canonique (liste d'exceptions `$www` vide) | `config/mes_options.php:21-24` |
| Options de `demarrer_site()` | `table_prefix => false`, `cookie_prefix => true`, `url_img_courtes => true`, `creer_site => true`, `creer_base => true`, `creer_user_base => false` ; les valeurs `mail`, `code`, `url_creer_base` et `avant_initialisation` ne sont pas reproduites ici | `config/mes_options.php:124-147` |
| Écart avec le modèle | Par rapport à `mutualisation/mes_options.php.txt`, le seul écart est le nom du site d'administration. Je n'ai pas fait de comparaison plus fine | diff des fichiers, espaces ignorés |
| Plugins partagés | `plugins/auto/cextras/v4.3.3/` : `cextras` `4.3.3`, `compatibilite="[4.1.0;4.*]"`, nécessite `saisies [4.2.0;]` et `yaml [3.0.0;]`, utilise `verifier` et `crayons` | `paquet.xml` du plugin |
| `plugins-dist/` | Absent de la copie fournie | arborescence |

### Sites (dans `sites/`)

| Site | Chemin | Installation | Moteur de base | Données persistantes | Plugins actifs |
|---|---|---|---|---|---|
| `asso-exemple.org` | `sites/asso-exemple.org/` | `config/connect.php` présent ; `IMG/`, `local/`, `tmp/` présents (vides dans la copie) | **inconnu** : le fichier (52 octets) ne contient aucun appel `spip_connect_db` | inconnues | inconnus |
| `mairie-exemple.fr` | `sites/mairie-exemple.fr/` | idem | **inconnu**, même raison | inconnues | inconnus |
| `admin-ferme.example` | **non trouvé** dans `sites/` | — | — | — | — |

### Inconnues

- Provenance du cœur et des plugins (Git, Composer, archive ou SVP) et modifications locales. La copie ne contient ni `.git`, ni `composer.json`, ni `composer.lock`. Le chemin `plugins/auto/cextras/v4.3.3/` ressemble à une installation par SVP, mais une ressemblance ne prouve rien.
- Version exacte de SPIP 5.0 visée (release ou tag) et versions de PHP qu'elle exige. Je n'ai rien vérifié en amont aujourd'hui et je ne retiens aucune version « de mémoire ».
- Existence d'une version de Mutualisation facile qui se déclare compatible 5.0 (dépôt `spip-contrib-extensions/mutualisation`).
- Existence de versions de `cextras`, `saisies`, `yaml`, `verifier` et `crayons` compatibles 5.0. Les plugins `saisies` et `yaml`, exigés par `cextras`, ne figurent pas dans la copie : leur emplacement est inconnu.
- Moteur de base, préfixe de tables et taille de chaque site ; plugins actifs sur chaque site.
- Emplacement du site d'administration `admin-ferme.example` (à la racine ou ailleurs).
- Serveur web, versions de PHP-FPM et de PHP CLI, espace disque, inodes, sauvegardes existantes.
- Accès à `?exec=mutualisation` depuis les vhosts autres que celui d'administration.

## Risques

- **Toute la ferme est concernée.** Le cœur, `mutualisation/` et `plugins/auto/` sont partagés : changer de cœur fait passer tous les sites en même temps. On ne peut pas tester sur un seul site en production. Un pilote demande une copie isolée de toute la ferme.
- **Blocage de compatibilité : Mutualisation facile.** `[4.2.0;4.*]` exclut 5.0. Or ce plugin est chargé par `require` avant le pipeline des plugins : s'il ne fonctionne pas, aucun site ne démarre (il définit `_DIR_SITE`, `_SPIP_PATH` et les préfixes). Tant qu'aucune version compatible 5.0 n'existe et n'a été testée, la migration est impossible.
- **Blocage de compatibilité : `cextras` 4.3.3.** `[4.1.0;4.*]` exclut aussi 5.0. Ses dépendances (`saisies`, `yaml`) restent à inventorier.
- **Retour en arrière.** Une fois qu'un site a migré son schéma de base vers 5.0, remettre l'ancien cœur 4.4.22 ne suffit plus. Il faut aussi restaurer la base de ce site et ses fichiers persistants pris au même point de contrôle.
- **Sauvegardes.** Aucune sauvegarde vérifiée et testée en restauration n'est démontrée, et le moteur de base de chaque site est inconnu. C'est bloquant à lui seul.
- **Création automatique de sites.** `creer_site => true` et `creer_base => true` : un nom d'hôte inattendu peut créer un site ou une base. À garder en tête pendant la maintenance et pendant les tests en préproduction.
- **Exposition de `?exec=mutualisation`.** Les actions `renouvelle_alea`, `dirliste` et `dirsize` s'exécutent sans authentification sur chaque site. La route `upgrade=oui` ne repose que sur un secret faible, `md5(version_installee-popularite_total)`, que l'on peut retrouver dans `tmp/meta_cache.php`. Il faut vérifier dans les journaux d'accès si elle a été appelée. La bloquer au niveau du proxy serait une modification à prévoir comme une ligne du plan, pas une observation.
- **Droits à vérifier.** Dans la copie, `connect.php` est en `664` et les dossiers `config/` en `775` (accessibles en écriture au groupe). Il faut le confirmer sur le serveur réel et le comparer au compte PHP-FPM ou Apache. Je ne propose aucun `chmod` ni `chown`.

## Procédure proposée

Les étapes 1 à 6 sont en lecture seule. L'opérateur les lance et me renvoie uniquement les faits demandés.

1. **Délimiter la racine réelle.** `spip_root=<racine partagée vérifiée>` ; `realpath -e`, refuser `/`, vérifier que `ecrire/inc_version.php` et `spip.php` existent. Lister les vhosts avec les filtres de `inventory-diagnosis.md` §1 (directives `server_name`, `root` et `fastcgi_pass` seulement).
2. **Établir la provenance**, sans jamais renvoyer d'URL de remote brute :
   ```bash
   git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null
   git -C "$spip_root" status --short 2>/dev/null
   git -C "$spip_root" describe --tags --always --dirty 2>/dev/null
   test -f "$spip_root/composer.json" && test -f "$spip_root/composer.lock" && echo composer
   find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -mindepth 1 -maxdepth 3 -name paquet.xml -print 2>/dev/null
   ```
3. **Moteur de base, site par site.** Ne rien afficher d'autre que `mysql`, `sqlite3` ou `unknown` :
   ```bash
   for f in "$spip_root"/sites/*/config/connect.php; do
     t=unknown
     rg -q "spip_connect_db\(.*'sqlite3'" -- "$f" && t=sqlite3
     rg -q "spip_connect_db\(.*'mysql'" -- "$f" && t=mysql
     printf '%s %s\n' "${f%/config/connect.php}" "$t"
   done
   find -P "$spip_root/sites" -xdev -mindepth 2 -maxdepth 4 -path '*/config/bases/*' -type f -printf '%p %s\n'
   ```
4. **Site d'administration et chemins de plugins supplémentaires** : `rg -n '_DIR_PLUGINS_SUPPL|_SPIP_PATH|dossier_squelettes' "$spip_root/config" "$spip_root/sites" --glob '*.php'` ; trouver où se trouve `admin-ferme.example`.
5. **Capacité, versions de PHP et droits** : `df -hP` et `df -iP` sur la racine ; `php -v` ; la version PHP de **chaque** pool FPM ; propriétaires et modes avec `find … -printf '%u:%g %m %y %p'` (inventaire §6). Rechercher dans les journaux d'accès les appels à `exec=mutualisation`.
6. **Plugins actifs par site.** Les lire dans l'espace privé de chaque site ou dans `spip_meta`, pas dans un cache `tmp/` sans horodatage.
7. **Compatibilité, à vérifier en amont aujourd'hui** : tag exact de SPIP 5.0 visé et PHP qu'il exige ; `paquet.xml` actuel de Mutualisation facile (branche principale et tags) ; `paquet.xml` des versions compatibles 5.0 de `cextras`, `saisies`, `yaml`, `verifier` et `crayons`. Remplir le tableau de compatibilité de `core-upgrade.md` §1.
8. **Décision.** Si Mutualisation facile n'a aucune version qui déclare 5.0 et qui a été testée, **on arrête** : la ferme reste en 4.4.22. Sinon, il faut une procédure 5.0 recherchée à part (hors du périmètre 4.2–4.4 de la procédure actuelle), puis les lignes 1 à 3 du plan d'actions, dans l'ordre. Les lignes 4 à 8 ne s'exécutent qu'après une préproduction 5.0 réussie et la levée de toutes les conditions d'arrêt.

## Commandes : plan d'actions (**proposé, non exécuté** ; **bloqué** tant que la procédure ci-dessus n'est pas terminée)

| # | Action | Objectif | Préconditions | Portée exacte | Impact | Sauvegarde / preuve | Commande proposée | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Créer le dossier de sauvegarde | Réserver un emplacement hors de la racine web pour le jeu de sauvegardes | Étapes 1 à 3 faites ; `backup_parent` résolu, différent de `/` et hors de `spip_root` (`backup-rollback.md` §2) | `<backup_parent>/<horodatage UTC>/` et `databases/` | Hôte seulement ; aucun site touché | Manifeste : hôte, heure UTC, chemins | `umask 077; mkdir -- "$backup_root" "$backup_root/databases"`, après les assertions du §2 | `realpath -e` donne le chemin attendu, en mode 700 | `rmdir` des dossiers vides, après accord de l'opérateur |
| 2 | Sauvegarder chaque site : base et fichiers persistants, plus le socle partagé | Disposer d'un point de restauration cohérent pour **chaque** site et pour le code partagé en 4.4.22 | Ligne 1 faite ; moteur connu pour chaque site ; pour MySQL, fichier d'options en 0600 préparé par l'opérateur ; écritures gelées ou limite de cohérence annoncée | Bases de `asso-exemple.org`, de `mairie-exemple.fr` et du site d'administration ; leurs `config/` et `IMG/` ; `config/mes_options.php` ; `mutualisation/` ; `plugins/` ; `ecrire/` et le reste du cœur selon la provenance | Lecture des sites ; écriture dans `backup_root` seulement | `SHA256SUMS`, tailles, codes de retour, versions des outils, copie hors de l'hôte | MySQL : le modèle de dump de `backup-rollback.md` §3 avec `--defaults-file`, `--no-login-paths` et `--single-transaction`, si les tables sont InnoDB. SQLite : `.backup` puis `PRAGMA integrity_check` (§4). Fichiers : `tar` sur une liste explicite (§5) | Dump non vide et code de retour 0 ; `integrity_check` = `ok` ; `sha256sum -c` OK | Aucune modification à annuler : on conserve les artefacts |
| 3 | Test de restauration isolé | Prouver que la sauvegarde de la ligne 2 se restaure | Ligne 2 validée ; serveur de test isolé, sans accès ni identifiants de production | Une base de test par site ; une copie isolée du site | Environnement de test seulement | Rapport du test, identité distincte du serveur de test | Modèle de `backup-rollback.md` §3 (vérification de restauration) ou §4 (SQLite) | Table `<prefix>_meta` présente et `CHECK TABLE` OK ; site restauré fonctionnel | Supprimer la base de test après relevé des résultats et accord de l'opérateur |
| 4 | Construire une préproduction SPIP 5.0 de toute la ferme | Tester en 5.0 le cœur, Mutualisation facile et les plugins, avec des copies des bases | Lignes 2 et 3 faites ; tag 5.0 exact vérifié ; **une version de Mutualisation facile qui déclare 5.0** ; versions compatibles de tous les plugins ; procédure 5.0 recherchée à part ; provenance connue | Dossier de préproduction distinct de `spip_root` | Aucun en production | Manifeste des versions et sommes de contrôle | **Non fourni** : il dépend de la provenance, du tag 5.0 et de la procédure 5.0 qui restent inconnus | Chaque site de test passe le registre de validation (`core-upgrade.md` §5) | Supprimer la préproduction |
| 5 | Mettre toute la ferme en maintenance | Geler les écritures pendant la bascule | Ligne 4 réussie ; fenêtre de maintenance annoncée ; mécanisme existant du serveur web ou du répartiteur identifié | Tous les vhosts de la ferme | Sites indisponibles | Configuration du vhost et du proxy sauvegardée | **Non fourni** : il dépend du serveur web détecté à l'étape 1. Aucune constante SPIP inventée | Réponse de maintenance sur chaque domaine | Remettre la configuration sauvegardée |
| 6 | Basculer le code partagé vers 5.0 | Remplacer cœur, `mutualisation/` et plugins en une seule opération | Lignes 2 à 5 faites ; inventaire relancé, liste des sites inchangée | `ecrire/` et le reste du cœur, `mutualisation/`, `plugins/` | **Toute la ferme** | Ancienne version exacte conservée (ligne 2) | **Non fourni** : selon la méthode d'installation actuelle (`core-upgrade.md` §2), sans en changer | Version 5.0 lue dans le cœur basculé | Remettre l'ancienne version exacte, si aucun schéma n'a migré |
| 7 | Mettre à jour la base de chaque site, un site après l'autre | Faire migrer le schéma de chaque base vers 5.0 | Ligne 6 faite ; journaux de chaque site relevés avant | Un site à la fois | Schéma de ce site, plus purge de son `tmp/` | Journaux SPIP et PHP de ce site ; point de contrôle de la ligne 2 | Mise à jour authentifiée dans l'espace privé (`ecrire/`) ; **pas** de route `upgrade=oui` dont on devinerait le secret | Schéma à jour, connexion à l'espace privé, pages publiques, formulaires, journaux propres | Remettre le code de la ligne 2 **et** la base et les fichiers de ce site au même point de contrôle |
| 8 | Sortir de la maintenance | Rouvrir les sites | Tous les sites validés, y compris celui d'administration | Tous les vhosts | Retour au service | Registre de validation complet | Annuler la ligne 5 | Code HTTP et contenu attendus sur chaque domaine | Revenir à la ligne 5 |

## Validation

- **Maintenant (lecture seule)** : les faits des étapes 1 à 7 pour **chaque** site (`asso-exemple.org`, `mairie-exemple.fr`, `admin-ferme.example`). Aucun moteur `unknown`, aucune provenance `unknown`, et un tableau de compatibilité rempli à partir des `paquet.xml` actuels.
- **Plan d'actions** : contrôles de réussite des lignes 1 à 3 avant toute préproduction ; ceux de la ligne 4 sur un site de test par combinaison de plugins (et par moteur, MySQL et SQLite, s'il y a les deux) ; puis, dans l'ordre, ceux des lignes 6, 7 (site par site) et 8. Que le site d'administration fonctionne ne prouve pas que les autres bases ont migré.

## Retour arrière

- **Déclencheurs** : échec inexpliqué de l'un des contrôles des lignes 6 ou 7, erreur PHP ou SPIP dans les journaux, schéma non à jour, ou état de migration incertain.
- **Unité à restaurer** : le code partagé 4.4.22 exact, plus, pour chaque site qui a migré ou écrit avec le nouveau code, sa base et ses fichiers persistants pris au même point de contrôle (ligne 2). Un état incertain se traite comme une migration : on restaure la base.
- **Ordre** : retour arrière de la ligne 7, site par site, puis de la ligne 6, puis rester sous la ligne 5 jusqu'à ce que le registre de validation soit à nouveau complet, puis ligne 8. On ne fait jamais tourner un cœur 4.4 sur une base migrée en 5.0.

---

Vérification de couverture : les seules actions qui modifient quelque chose dans cette réponse sont la création du dossier de sauvegarde, les sauvegardes, le test de restauration, la préproduction, la maintenance, la bascule du code, la mise à jour des bases et la sortie de maintenance. Elles correspondent aux lignes 1 à 8, et aucune n'a été exécutée. Pour avancer, envoyez-moi d'abord les résultats des étapes 1 à 7 (en particulier : la provenance, le moteur de base de chaque site, et la réponse à la question « existe-t-il une version de Mutualisation facile compatible 5.0 ? »).

## spip-mutualisation-maintenance, cas 26 — tirage 2

# Passage de la ferme à SPIP 5.0 : préparation

**Réponse courte : je ne peux pas vous fournir une procédure de migration vers SPIP 5.0 prête à lancer.** Deux blocages ressortent des fichiers. D'abord, Mutualisation facile 2.0.1 installé déclare `compatibilite="[4.2.0;4.*]"`, ce qui exclut SPIP 5.0. Ensuite, la procédure de maintenance que j'applique ne couvre que les fermes SPIP 4.2–4.4. Je vous donne ci-dessous ce que j'ai constaté, ce qui manque, et les seules actions qu'on peut préparer sans risque dès maintenant. Je n'ai rien exécuté ni modifié. J'ai travaillé uniquement sur la copie de la racine partagée.

## Constats

Faits vérifiés dans la copie de la racine partagée :

| Élément | Constat | Preuve |
|---|---|---|
| Cœur SPIP partagé | 4.4.22 (`_SPIP_VERSION_ID` 40422, `spip_version_code` 2026_09_01, `spip_version_base` 2026_08_03_00) | `ecrire/inc_version.php:464-475` |
| PHP accepté par ce cœur | 7.4.0 à 8.5.99 | `ecrire/inc_version.php:40-41` |
| Chargement de la mutualisation | `config/mes_options.php` fait un `require` de `_DIR_RACINE.'mutualisation/mutualiser.php'`. Le plugin est dans `mutualisation/` à la racine, pas dans `plugins/` | `config/mes_options.php:14-18` |
| Mutualisation facile | version 2.0.1, `compatibilite="[4.2.0;4.*]"`, `<chemin path="" type="aucun" />` | `mutualisation/paquet.xml` |
| Appel effectif `demarrer_site()` | `repertoire => 'sites'`, `table_prefix => false`, `cookie_prefix => true`, `url_img_courtes => true`, `creer_site => true`, `creer_base => true` | `config/mes_options.php:124-143` |
| Calcul de `$site` | depuis `HTTP_HOST` : retrait de `www.` avec redirection, `:80` et les ports remplacés par `_` | `config/mes_options.php:23-45` |
| Site d'administration | `_SITES_ADMIN_MUTUALISATION = 'admin-ferme.example'` | `config/mes_options.php:11` |
| Moteur SQL par défaut à l'installation | `_INSTALL_SERVER_DB = 'mysql'`, préfixe de base `mu_` | `config/mes_options.php:51-55` |
| Sites trouvés dans `sites/` | `asso-exemple.org` et `mairie-exemple.fr`. Chacun a `config/connect.php`, `IMG/`, `local/` et `tmp/` | arborescence |
| Plugin partagé | `plugins/auto/cextras/v4.3.3` : Champs Extras 4.3.3, `compatibilite="[4.1.0;4.*]"`. Il exige `saisies` ≥ 4.2.0 et `yaml` ≥ 3.0.0 | `plugins/auto/cextras/v4.3.3/paquet.xml` |

Inconnues (je ne peux pas conclure avec la copie seule) :

- **Origine du cœur** : la copie ne contient ni `.git`, ni `composer.json`/`composer.lock`, ni `spip.php`, ni `plugins-dist/`. Je ne sais donc pas si le cœur a été installé par Git, Composer, archive ou spip_loader, ni s'il porte des modifications locales.
- **Moteur SQL réel de chaque site** : mon filtre restreint n'a reconnu ni `mysql` ni `sqlite3` dans les deux `connect.php`. Le moteur reste **inconnu** par site. Il faut le confirmer sur le serveur avec un filtre restreint, sans afficher le fichier.
- **`saisies` et `yaml`**, dont cextras a besoin, sont absents de `plugins/auto/` dans la copie. Ils sont peut-être dans `plugins-dist/` ou dans un chemin ajouté, mais je ne l'ai pas vu.
- **Plugins actifs par site**, plugins propres à un site, `_DIR_PLUGINS_SUPPL` : rien dans la copie, à vérifier.
- Pile web/PHP-FPM, versions PHP par pool, espace disque et inodes, sauvegardes existantes : rien de vérifiable ici.
- **Exigences de SPIP 5.0** (PHP minimal, paquets Composer, compatibilité des plugins) : je ne les ai pas vérifiées sur les sources officielles aujourd'hui. Je ne les donne pas de mémoire.

## Risques

- **Blocage de compatibilité (bloquant)** : Mutualisation facile 2.0.1 se déclare compatible jusqu'à SPIP 4.* seulement. Tant qu'aucune version publiée de Mutualisation ne déclare SPIP 5.0 compatible, passer le cœur partagé en 5.0 met **toute la ferme** hors du périmètre supporté. Ce plugin est chargé par `require` avant le pipeline des plugins, donc s'il casse, tous les sites cassent. Il faut vérifier sur le dépôt actuel (https://git.spip.net/spip-contrib-extensions/mutualisation) s'il existe une branche ou une version compatible 5.0. Je n'ai pas pu le faire depuis ici.
- **Blocage de compatibilité des plugins** : Champs Extras 4.3.3 déclare `[4.1.0;4.*]`. Il faudra trouver une version compatible 5.0 de cextras, et aussi de `saisies` et `yaml`.
- **Périmètre** : SPIP 5.0 sort du cadre de cette procédure de maintenance (4.2–4.4). La bascule demande une procédure documentée séparément, pour la version 5.0 exacte visée et ses paquets.
- **Rayon d'impact** : un seul cœur et un seul `mutualisation/` servent tous les sites. On ne peut pas tester la 5.0 sur un seul site de production : il faut une copie isolée complète (code partagé, sites, bases).
- **Irréversibilité côté base** : dès qu'une base de site a été migrée par le cœur 5.0, revenir au cœur 4.4.22 ne suffit plus. Il faut restaurer la base et les fichiers persistants de ce site.
- **Origine du cœur inconnue** : c'est une condition d'arrêt. Je ne choisis pas la méthode (Git, Composer ou archive) à votre place.
- **Anomalie de sécurité, à part** : `config/mes_options.php`, partagé par toute la ferme, contient des identifiants de connexion MySQL en clair (lignes 55-58). Je ne les reproduis pas ici. Vérifiez qui peut lire ce fichier et s'il est servi par le web. Ce point est hors du sujet de la migration, mais il ne faut pas copier ce fichier vers un environnement de test moins protégé.
- **Exposition de `?exec=mutualisation`** : `renouvelle_alea`, `dirliste` et `dirsize` répondent sans authentification sur chaque site. La route `upgrade=oui` ne repose que sur un secret calculé à partir de données présentes dans `tmp/meta_cache.php`. À contrôler dans les journaux d'accès et à bloquer côté proxy hors des fenêtres de maintenance (ce blocage est une modification, à traiter à part).

## Procédure proposée

Seulement des observations en lecture seule et des étapes de planification, dans cet ordre :

1. **Compatibilité amont** (sans toucher au serveur) : sur les sources officielles, relevez l'étiquette exacte de SPIP 5.0 visée, son PHP minimal, et les `paquet.xml` actuels de Mutualisation facile, cextras, saisies et yaml. **Si Mutualisation ne déclare pas SPIP 5.0, on s'arrête là** : la ferme reste en 4.4.x et on surveille l'amont.
2. **Origine du cœur**, sur le serveur, en lecture seule (remplacez `<racine_ferme>` par le chemin vérifié) :
   ```bash
   spip_root="$(realpath -e -- <racine_ferme>)"; test "$spip_root" != /
   git -C "$spip_root" rev-parse --show-toplevel 2>/dev/null
   git -C "$spip_root" status --short 2>/dev/null
   git -C "$spip_root" describe --tags --always --dirty 2>/dev/null
   ls -1 "$spip_root" | grep -E '^(composer\.(json|lock)|spip\.php|plugins-dist|vendor)$'
   ```
   N'envoyez pas `git remote -v` : l'URL peut contenir des identifiants.
3. **Moteur SQL par site**, sans afficher `connect.php` :
   ```bash
   for d in "$spip_root"/sites/*/; do
     printf '%s ' "$(basename "$d")"
     grep -qE "'sqlite3'" "$d/config/connect.php" && echo sqlite3 && continue
     grep -qE "'mysql'" "$d/config/connect.php" && echo mysql || echo unknown
   done
   ```
4. **Plugins** : chemins et versions des plugins partagés, et plugins actifs sur chaque site :
   ```bash
   find -P "$spip_root/plugins" "$spip_root/plugins-dist" -xdev -mindepth 1 -maxdepth 3 -name paquet.xml -print 2>/dev/null
   rg -n '_DIR_PLUGINS_SUPPL|_SPIP_PATH' "$spip_root/config" "$spip_root/sites" --glob '*.php' -l
   ```
   Pour chaque `paquet.xml` trouvé, ne remontez que les lignes `prefix`, `version` et `compatibilite`.
5. **Pile et capacité** : version PHP de chaque pool FPM (pas seulement `php -v`), `df -hP` et `df -iP` sur la racine.
6. **Sauvegardes** : suivez les lignes 1 et 2 du plan d'actions. Elles sont utiles dès maintenant, quelle que soit la décision sur la 5.0.
7. Une fois les points 1 à 5 levés sans blocage, préparez la répétition isolée (ligne 3). La bascule de production (ligne 4) reste **bloquée** tant qu'une procédure 5.0 dédiée n'a pas été rédigée et validée sur cette répétition.

Pour les modifications, suivez les lignes 1 à 4 du plan d'actions dans l'ordre. Chaque ligne n'est possible que si ses préconditions sont remplies.

## Commandes

Plan d'actions. Toutes les commandes sont **proposées et non exécutées**. Les `<...>` sont à remplacer par des valeurs vérifiées.

| # | Action | Objectif | Préconditions | Portée exacte | Impact | Sauvegarde / preuve | Commande proposée (non exécutée) | Contrôle de réussite | Retour arrière |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Sauvegarder la base de chaque site | Disposer d'un point de restauration par site | Moteur de chaque site confirmé (étape 3). Pour MySQL, fichier d'options client protégé (`chmod 600`), sans mot de passe en argument. Espace disque suffisant | Une base par site : `asso-exemple.org`, `mairie-exemple.fr` (et tout autre site trouvé) | Lecture sur la base. Charge passagère | Fichier de dump horodaté et son SHA-256 dans un manifeste | MySQL : `mysqldump --defaults-extra-file=<fichier_options_600> --single-transaction --routines --triggers <base_du_site> > <dossier_sauvegarde>/<site>-<date>.sql && sha256sum <dossier_sauvegarde>/<site>-<date>.sql >> <manifeste>`. SQLite : `sqlite3 <racine>/sites/<site>/config/bases/<fichier>.sqlite ".backup '<dossier_sauvegarde>/<site>-<date>.sqlite'"` | Le dump n'est pas vide et `sha256sum -c <manifeste>` passe. **Restauration testée dans une base isolée** (ligne 3) | Aucun : la sauvegarde ne modifie rien. Supprimer les fichiers de sauvegarde ratés dans `<dossier_sauvegarde>` seulement |
| 2 | Archiver le code partagé et les fichiers persistants | Pouvoir restaurer le cœur 4.4.22, `mutualisation/`, `plugins/` et `config/`, plus `IMG/` et `config/` de chaque site | Racine résolue par `realpath`, différente de `/`. Espace disque suffisant | `<racine_ferme>` sans `sites/*/local` ni `sites/*/tmp` (caches, non persistants) | Lecture seule sur la ferme | Archive horodatée et son SHA-256. **L'archive contient des identifiants (`config/mes_options.php`, `connect.php`) : à stocker avec des droits restreints** | `tar --exclude='sites/*/local' --exclude='sites/*/tmp' -C <racine_ferme> -czf <dossier_sauvegarde>/ferme-<date>.tgz . && sha256sum <dossier_sauvegarde>/ferme-<date>.tgz >> <manifeste>` | `tar -tzf` liste bien `ecrire/inc_version.php`, `mutualisation/paquet.xml`, `sites/*/IMG`. Le SHA-256 correspond | Aucun : rien n'est modifié |
| 3 | Monter une répétition isolée de la ferme en 5.0 | Tester cœur 5.0, Mutualisation et plugins sur une copie | Lignes 1 et 2 vérifiées. **Mutualisation, cextras, saisies et yaml en versions déclarées compatibles 5.0** (étape 1). Origine du cœur connue (étape 2). Procédure 5.0 dédiée rédigée | Hôte ou vhost de test séparé, bases copiées sous d'autres noms, identifiants différents de ceux de production | Aucun sur la production | Journal de la répétition et grille de validation par site | **Pas de commande tant que la précondition de compatibilité n'est pas remplie.** Ensuite : restaurer les sauvegardes 1 et 2 dans `<racine_test>`, installer SPIP 5.0 `<étiquette_vérifiée>` avec la **même méthode** que la production, puis mettre à jour chaque base de test par l'espace privé authentifié | Tous les sites de test passent la grille de validation (public, connexion privée, schéma à jour, plugins actifs, formulaires et envois de fichiers, tâches, journaux) | Supprimer `<racine_test>` et les bases de test, après avoir vérifié par `realpath` que le chemin est bien celui de test |
| 4 | Basculer le cœur partagé de production en 5.0 | Migration effective | Ligne 3 réussie sur **tous** les sites. Sauvegardes 1 et 2 de moins de <délai> et restauration testée. Fenêtre de maintenance pour toute la ferme. Procédure 5.0 validée | Toute la ferme : cœur partagé, `mutualisation/`, `plugins/`, puis chaque base de site, l'une après l'autre | **Tous les sites** | Manifeste des lignes 1 et 2. Point de contrôle par site (base migrée oui ou non) | **Bloquée, aucune commande fournie** : Mutualisation 2.0.1 ne déclare pas SPIP 5.0 et ce cas sort du périmètre 4.2–4.4. La commande sera rédigée à partir de la procédure 5.0 validée en ligne 3 | Grille de validation remplie pour chaque site, pas seulement le site d'administration | Avant migration des bases : remettre le code archivé en ligne 2. Après migration d'une base : remettre le code de la ligne 2 **et** restaurer la base de ce site (ligne 1) et son `IMG/` |

## Validation

En lecture seule, pour **chaque** site (`asso-exemple.org`, `mairie-exemple.fr`, `admin-ferme.example` et tout autre site trouvé), pas seulement le site d'administration :

- avant toute action : la liste des sites sous `sites/` est inchangée depuis l'inventaire, et le moteur SQL est identifié pour chacun ;
- `curl -sS -o /dev/null -w '%{http_code}\n' https://<site>/` renvoie 200 et la page contient le contenu attendu ;
- connexion manuelle à l'espace privé, puis contrôle des plugins actifs et de l'état « base à jour » ;
- journaux PHP-FPM, serveur web et SPIP sans nouvelle erreur.

Pour les modifications, les contrôles de réussite sont dans les lignes 1 à 4 du plan d'actions.

## Retour arrière

- **Déclencheurs** : un site échoue à la grille de validation, une erreur PHP fatale apparaît, Mutualisation ne charge plus (toute la ferme tombe) ou un schéma de base est incohérent.
- **Unité de restauration** : le code partagé (archive de la ligne 2) pour toute la ferme, plus, pour chaque site dont la base a été migrée ou modifiée sous le nouveau cœur, sa base (ligne 1) et son `IMG/` (ligne 2). Ne jamais remettre le cœur 4.4.22 face à une base déjà migrée par la 5.0.
- **Ordre** : dans la répétition, appliquez le retour arrière de la ligne 3. En production, appliquez celui de la ligne 4, qui renvoie aux lignes 2 puis 1. Si l'état de migration d'un site n'est pas clair, considérez que sa base doit être restaurée.

**En résumé :** sauvegardez maintenant (lignes 1 et 2, avec un test de restauration), complétez l'inventaire (étapes 2 à 5), et vérifiez sur les sources actuelles si une version de Mutualisation facile déclarée compatible SPIP 5.0 existe. Tant que ce n'est pas le cas, la ferme doit rester en SPIP 4.4.x.
