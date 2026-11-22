# Secured Actions

An action is a PHP script in `action/` that performs a side effect (delete, toggle, move…) triggered
by a URL or a form. SPIP secures every action with an HMAC hash so that arbitrary URLs cannot trigger
state changes.

---

## Table of contents

1. [Anatomy of an action file](#anatomy-of-an-action-file)
2. [securiser_action — generate and verify](#securiser_action--generate-and-verify)
3. [Generating action URLs and forms](#generating-action-urls-and-forms)
4. [Balises for squelettes](#balises-for-squelettes)
5. [Checking authorisation inside an action](#checking-authorisation-inside-an-action)
6. [Redirecting after the action](#redirecting-after-the-action)
7. [Invariants](#invariants)
8. [See also](#see-also)

---

## Anatomy of an action file

File: `monplugin/action/supprimer_item.php`

```php
<?php
// action/supprimer_item.php
if (!defined('_ECRIRE_INC_VERSION')) { return; }

function action_supprimer_item_dist() {
    // 1. Verify the HMAC and retrieve the argument
    $securiser_action = charger_fonction('securiser_action', 'inc');
    $id_item = intval($securiser_action());  // exits with error page if hash invalid

    // 2. Check authorisation
    include_spip('inc/autoriser');
    if (!autoriser('supprimer', 'item', $id_item)) {
        spip_log("Suppression item $id_item interdite", _LOG_ERREUR);
        return;
    }

    // 3. Perform the side effect
    sql_delete('spip_items', 'id_item=' . $id_item);

    // 4. No redirect here: SPIP redirects to _request('redirect') after the action
    //    (traiter_appels_actions(), sanitised by securiser_redirect_action()).
}
```

Naming convention: `action_[nom]_dist()`. The file is `action/nom.php`; the function name derives
from the file name.

---

## securiser_action — generate and verify

The same function serves both purposes depending on how it is called.

```php
// ecrire/inc/securiser_action.php
$securiser_action = charger_fonction('securiser_action', 'inc');
```

### Verifying an incoming action (inside action/nom.php)

```php
// Call with NO arguments inside an action handler:
$arg = $securiser_action();
// Returns _request('arg') if HMAC is valid; exits with an error page otherwise.
```

The function reads `_request('action')`, `_request('arg')`, and `_request('hash')` from the current
request and verifies the HMAC. If verification fails, it prints an error page and calls `exit`.

### Generating an action URL (outside action/)

```php
// ecrire/inc/actions.php — generer_action_auteur()
// Returns a URL string by default ($mode = false uses &amp; separators)
$url = generer_action_auteur('supprimer_item', $id_item, $redirect_url);

// Returns a URL with bare & (for use in PHP redirects)
$url = generer_action_auteur('supprimer_item', $id_item, $redirect_url, true);

// Returns ['action' => ..., 'arg' => ..., 'hash' => ...] for manual use
$parts = generer_action_auteur('supprimer_item', $id_item, $redirect_url, -1);
```

Signature:
```php
generer_action_auteur(
    string $action,     // name of the file in action/ (without .php)
    string $arg,        // argument passed to $securiser_action()
    string $redirect,   // URL to redirect to after the action completes
    bool|int|string $mode = false,  // false=URL with &amp;, true=URL with &, -1=array, string=form HTML
    string|int $att = '',           // id_auteur (URL/array) or form attributes (form mode)
    bool $public = false            // true = public-space URL, false = private-space URL
): array|string
```

---

## Generating action URLs and forms

### Private-space action button (redirige_action_auteur)

```php
// ecrire/inc/actions.php — redirige_action_auteur()
// Returns a URL pointing at the private ecrire/ space
$url = redirige_action_auteur(
    'supprimer_item',  // action name
    $id_item,          // arg
    'items',           // exec page to return to (exec=items)
    'id_item=' . $id_item  // extra query string for the return URL
);
```

### POST form (redirige_action_post)

```php
// ecrire/inc/actions.php — redirige_action_post()
// Returns an HTML <form> element with a hidden hash field
$form_html = redirige_action_post(
    'supprimer_item',
    $id_item,
    'items',           // return exec
    '',                // extra query string
    "Supprimer l'objet"  // button label (corps parameter)
);
```

---

## Balises for squelettes

In squelettes and private-space HTML, use the SPIP balises instead of calling PHP directly:

```html
<!-- Generate a secured URL -->
#URL_ACTION_AUTEUR{supprimer_item, #ID_ITEM, #SELF}

<!-- Generate an action button (renders as <a> with AJAX support) -->
[(#BOUTON_ACTION{Supprimer, #URL_ACTION_AUTEUR{supprimer_item,#ID_ITEM,#SELF}, 'ajax'})]
```

`#URL_ACTION_AUTEUR{action, arg, redirect}` wraps `generer_action_auteur()`.

**Empty `arg`: write `''`, never an empty slot.** `#URL_ACTION_AUTEUR{mon_action,,#SELF}` (or `{mon_action,,}`)
kills the page compilation: HTTP 500, `TypeError: Cannot assign null to property …Champ::$code` in
`balise_URL_ACTION_AUTEUR_dist()`. `{mon_action,'',#SELF}` or `{mon_action,#ENV{vide},#SELF}` produce `arg=` and work.

---

## Checking authorisation inside an action

Always load `inc/autoriser` and check before performing the side effect:

```php
include_spip('inc/autoriser');
if (!autoriser('supprimer', 'item', $id_item)) {
    spip_log("Echec : action interdite", _LOG_ERREUR);
    return;  // never redirige_par_entete() here; set $GLOBALS['redirect'] to send elsewhere
}
```

See `references/autorisations.md` for the full `autoriser()` API.

---

## Redirecting after the action

Do **not** redirect from the action: never call `redirige_par_entete()` in an action since 4.4.26
(securite#4938) — it would skip the check of the target. The caller passes `redirect` in the action URL
(`generer_action_auteur($action, $arg, $redirect)`, `#URL_ACTION_AUTEUR{action, arg, #SELF}`,
`#BOUTON_ACTION`). `traiter_appels_actions()` (`ecrire/public/aiguiller.php`) secures it with
`securiser_redirect_action()` before calling the action (an external URL is dropped unless
`_AUTORISER_ACTION_ABS_REDIRECT`), then redirects to it with `redirige_action()` once the action returns. It also
carries the Ajax environment (`var_ajax`, `var_ajax_env`).

To impose the target from the action, set it:

```php
$GLOBALS['redirect'] = generer_url_ecrire('items');   // trusted: not sanitised (4.4.28, !413)
```

Never copy a request value into `$GLOBALS['redirect']`: it would bypass the check. With no redirect at all, SPIP
answers `204 No Content`.

Behind a server that is not Apache, nginx or Cherokee (e.g. `php -S`), the redirect is an HTML page with a
`Refresh:` header and status 200, not a `302` with `Location:`; test the target, not the status code.

---

## Invariants

- `securiser_action()` with no arguments exits if the hash is invalid — the action body is never
  reached for tampered or expired URLs
- The `arg` parameter is a plain string; parse it yourself (e.g. `explode('-', $arg)` for composite
  arguments like `"42-publie"` in `instituer_forum`)
- `generer_action_auteur()` hashes with the current auteur's session key; links are per-user and
  expire with the session
- An action never redirects itself (4.4.26+): SPIP redirects to the secured `redirect` parameter, or to
  `$GLOBALS['redirect']` when the action sets it
- A public action (`$public = true`) can be triggered by unauthenticated visitors; use only when
  the action is intentionally public (e.g. forum posting)

---

## See also

- `references/autorisations.md` — `autoriser()` API
- `../../spip-formulaires/references/cvt-formulaires.md` — for form-based interactions (preferred over raw actions)
- `references/arborescence.md` → `action/` section
