# CVT forms

A formulaire CVT (Charger / Vérifier / Traiter) is a three-function PHP contract that powers every `#FORMULAIRE_NOM` balise in SPIP. The framework handles AJAX submission, CSRF signing, error display, and re-population; plugins only implement the three contract functions.

---

## Naming convention

For a formulaire named `mon_form` (squelette at `formulaires/mon_form.html`):

```
formulaires/
├── mon_form.html         ← the template (displays data, errors and #SAISIES)
└── mon_form.php          ← the three contract functions
    (or mon_form/         ← dir layout: charger.php / verifier.php / traiter.php)
```

Function names follow a fixed pattern (always with `_dist` suffix for overridable functions):

```php
function formulaires_mon_form_charger_dist(/* same args as #FORMULAIRE_MON_FORM */): array|false|string
function formulaires_mon_form_verifier_dist(/* same args */): array
function formulaires_mon_form_traiter_dist(/* same args */): array

// optional – identifies which posted form matches this instance (page with multiple forms)
function formulaires_mon_form_identifier_dist(/* same args */): array
```

Arguments come directly from the balise call: `#FORMULAIRE_MON_FORM{$a, $b}` → each function receives `($a, $b)` in the same order.

---

## charger() — load the environment

**Called on every page render** (not only after a POST). Returns the array of values passed as context to `formulaires/mon_form.html`.

```php
// ecrire/balise/formulaire_.php:333
function formulaire__charger($form, $args, $poste) {
    if ($charger_valeurs = charger_fonction('charger', "formulaires/$form", true)) {
        $valeurs = $charger_valeurs(...$args);
    } else {
        $valeurs = [];
    }
    $valeurs = pipeline('formulaire_charger', ['args' => [...], 'data' => $valeurs]);
    // ...multi-step and pipeline injection...
    return $valeurs;
}
```

### Return values

| Return type | Meaning |
|---|---|
| `array` | Normal: values passed to the squelette |
| `false` | Form not applicable — renders nothing |
| `string` | Form not applicable — renders the string as explanation text |

### Reserved array keys (underscore-prefixed = meta, not field values)

| Key | Type | Purpose |
|---|---|---|
| `_hidden` | string | Raw HTML appended inside the `<form>` (hidden inputs, JS) |
| `_pipelines` | array | Additional pipelines to run on the squelette (e.g. `formulaire_fond`) |
| `_forcer_request` | bool | Force re-population from `_request()` even without a POST |
| `_autosave_id` | mixed | Enables session autosave; value is the uniqueness key (see [Autosave](#autosave)) |
| `_etapes` | int | Number of steps for multi-step forms (see [Multi-step](#multi-step-formulaires)) |
| `action` | string | Override the `action=` attribute; `''` disables the action attribute |
| `editable` | bool/string | Control whether the form is shown editable (truthy) or read-only |

Any non-underscore key becomes a template variable and is re-populated from `_request()` on POST.

### Minimal example

```php
// plugins-dist/spip/mots/formulaires/editer_mot.php:49
function formulaires_editer_mot_charger_dist($id_mot = 'new', $id_groupe = 0, $retour = '', ...) {
    $valeurs = formulaires_editer_objet_charger('mot', $id_mot, $id_groupe, '', $retour, ...);
    if (intval($id_mot) and !autoriser('modifier', 'mot', intval($id_mot))) {
        $valeurs['editable'] = '';   // read-only for unauthorised visitors
    }
    return $valeurs;
}
```

---

## verifier() — validate the POST

**Called only when the form is submitted** (after `charger()`). Returns an array of errors keyed by field name; an empty array means "all valid, proceed to `traiter()`".

```php
// ecrire/public/aiguiller.php:258
$post["erreurs_$form"] = pipeline(
    'formulaire_verifier',
    ['args' => [...], 'data' => $verifier ? $verifier(...$args) : []]
);
```

### Error array

```php
return [
    'titre'          => _T('info_obligatoire'),   // field-level error
    'texte'          => _T('forum:forum_attention_dix_caracteres'),
    'message_erreur' => 'Explicit global message', // overrides the automatic count message
];
```

| Key | Meaning |
|---|---|
| any field name | Error message for that field (accessible as `[(#ERREURS|table_valeur{titre})]` in squelette) |
| `message_erreur` | Global error banner; auto-generated from field count if absent |
| `message_erreur => ''` | Suppress global banner (e.g. when returning a `previsu` instead) |

If `verifier()` is absent, the framework uses an empty array (always valid).

### Real example (forum verification)

```php
// plugins-dist/spip/forum/formulaires/forum.php:235
function formulaires_forum_verifier_dist($objet, $id_objet, ...) {
    $erreurs = [];
    if (strlen(_request('texte')) < 10) {
        $erreurs['texte'] = _T('forum:forum_attention_dix_caracteres');
    }
    if (strlen(_request('titre')) < 3 and $GLOBALS['meta']['forums_titre'] == 'oui') {
        $erreurs['titre'] = _T('forum:forum_attention_trois_caracteres');
    }
    // special: 'previsu' key triggers preview, not real error
    if (!count($erreurs) and !_request('confirmer_previsu_forum')) {
        $erreurs['previsu'] = inclure_previsu(...);
        $erreurs['message_erreur'] = '';  // no error banner during preview
    }
    return $erreurs;
}
```

---

## traiter() — execute the action

**Called only when `verifier()` returns an empty array**. Performs the side effect (DB write, email, file upload, etc.) and returns a result array.

```php
// ecrire/public/aiguiller.php:295
if ($traiter = charger_fonction('traiter', "formulaires/$form/", true)) {
    $rev = $traiter(...$args);
}
$rev = pipeline('formulaire_traiter', ['args' => [...], 'data' => $rev]);
```

### Result array keys

| Key | Effect |
|---|---|
| `redirect` | URL to redirect to after success (302 or JS depending on context) |
| `message_ok` | Success message shown in the squelette after redirect |
| `message_erreur` | Treat as failure: no redirect, error banner shown |
| `editable` | If present: override editability of the reloaded form |
| `id_XX` | Convention: return the created/modified object's id for pipeline consumers |

### Error handling and partial failures

**SPIP does not rollback on failure.** There is no automatic transaction wrapping `traiter()`. If a partial write occurs (e.g. the row was inserted but the email notification failed), the side effect is permanent. To handle partial failures safely, wrap the critical section in a SQL transaction yourself:

```php
// ecrire/base/abstract_sql.php — transaction helpers
sql_demarrer_transaction();
$id = sql_insertq('spip_monplugin', $champs);
if (!$id) {
    // nothing to rollback yet, but don't continue
    return ['message_erreur' => _T('monplugin:erreur_insertion')];
}
// ... more work ...
sql_terminer_transaction();
```

Return `['message_erreur' => ...]` to signal failure; the form re-displays with the error. No automatic cleanup of already-applied effects occurs.

```php
// plugins-dist/spip/forum/formulaires/forum.php:528
function formulaires_forum_traiter_dist($objet, $id_objet, ..., $retour) {
    $id_reponse = $forum_insert($objet, $id_objet, $id_forum);
    if ($id_reponse) {
        return ['redirect' => $retour . '#forum' . $id_reponse, 'id_forum' => $id_reponse];
    }
    return ['message_erreur' => _T('forum:erreur_enregistrement_message')];
}
```

---

## identifier() — disambiguate multiple forms

When a page contains several instances of the same formulaire (e.g. two `#FORMULAIRE_FORUM` in a thread), SPIP must decide which one was posted. Without `identifier()`, it compares full argument lists.

```php
// plugins-dist/spip/forum/formulaires/forum.php:30
function formulaires_forum_identifier_dist($objet, $id_objet, $id_forum, ...) {
    return [$objet, $id_objet, $id_forum];  // subset that uniquely identifies this instance
}
```

Return the minimal subset of arguments that makes two form instances distinguishable.

---

## Pipeline flow

```
Page render:
  charger_fonction('charger', 'formulaires/mon_form')
  → pipeline('formulaire_charger', ...)     # plugins may add/modify values
  → [if _etapes] cvtmulti_formulaire_charger_etapes()
  → squelette context

POST received:
  → pipeline('formulaire_receptionner', ...)   # before any validation
  charger_fonction('verifier', 'formulaires/mon_form')
  → pipeline('formulaire_verifier', ...)
  → [if cvtm_prev_post] cvtmulti_formulaire_verifier_etapes()
  if erreurs == []:
    charger_fonction('traiter', 'formulaires/mon_form')
    → pipeline('formulaire_traiter', ...)
```

The four related pipelines (`formulaire_charger`, `formulaire_verifier`, `formulaire_traiter`,
`formulaire_receptionner`) are documented in full in `references/pipelines.md`.

---

## Multi-step forms

Declare the total number of steps in `charger()`:

```php
function formulaires_inscription_charger_dist() {
    return [
        '_etapes' => 3,      // 3 steps — activates cvt_multietapes
        'nom'    => '',
        'email'  => '',
        'code'   => '',
    ];
}
```

SPIP automatically:
- Injects `_etape` (current step, 1-based) into the squelette context
- Picks `formulaires/inscription_2.html` for step 2 (falls back to `inscription.html`)
- Serialises posted values from previous steps into a hidden `cvtm_prev_post` input
- Re-injects earlier step values into `_request()` so `verifier()` sees the full data

### Per-step verification

Implement per-step verifiers alongside the standard one:

```php
// called for step 1 only
function formulaires_inscription_verifier_1_dist($args...) { ... }

// called for step 2 only
function formulaires_inscription_verifier_2_dist($args...) { ... }

// alternative: single function with step number as first argument
function formulaires_inscription_verifier_etape_dist($etape, $args...) { ... }
```

Additionally, the pipeline `formulaire_verifier_etape` fires for each step with `$args['etape']` set.

`traiter()` is called only when all steps pass validation.
Source: `ecrire/inc/cvt_multietapes.php`

---

## Autosave

Add `_autosave_id` to the `charger()` return to enable session-backed autosave (saves the form in the visitor's session every few seconds via JS):

```php
// plugins-dist/spip/forum/formulaires/forum.php:174
return array_merge($vals, [
    '_autosave_id' => $ids,   // array used as uniqueness key (md5-hashed)
    // ...
]);
```

What happens:
- On first non-POST load: any previously saved session data repopulates field values
- On each keystroke: JS posts to the autosave endpoint and updates `session_autosave_{key}`
- On `traiter()`: `cvtautosave_formulaire_traiter()` (via `formulaire_traiter` pipeline) clears the session entry and GC's entries older than `_AUTOSAVE_GB_DELAY` (default 72 hours)

Source: `ecrire/inc/cvt_autosave.php`

---

## See also

- `../../spip-plugins/references/pipelines.md` — full `$flux` shapes for `formulaire_charger`, `formulaire_verifier`, `formulaire_traiter`, `formulaire_receptionner`
- `../../spip-plugins/references/declarer-objet.md` — `formulaires_editer_objet_charger()` pattern used by all objet éditorial edit forms
