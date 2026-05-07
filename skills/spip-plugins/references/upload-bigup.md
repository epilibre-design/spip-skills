# Upload — Bigup integration in CVT formulaires

Bigup is a SPIP plugin (included in `plugins-dist`) that adds chunked file upload, drag-and-drop,
and client-side image resizing to CVT formulaires. It hooks into the formulaire pipeline
automatically once enabled from `charger()`.

Source: `plugins-dist/spip/bigup/`

---

## Prerequisite

Bigup must be active. Check in `paquet.xml`:

```xml
<necessite id="bigup" version="[4.0.0;4.*]" />
```

---

## Enabling bigup in a formulaire

The only change to `charger()` is one key:

```php
// formulaires/mon_formulaire.php
function formulaires_mon_formulaire_charger_dist($id = 0) {
    return [
        'titre' => '',
        // --- enable bigup for this formulaire ---
        '_bigup_rechercher_fichiers' => true,
    ];
}
```

Bigup reads `_bigup_rechercher_fichiers` in the `formulaire_charger` pipeline. If truthy, it:
- Looks for any files already uploaded for this form/visitor pair
- Injects a hidden `bigup_retrouver_fichiers` token into `_hidden`
- Adds file lists as `_bigup_fichiers[<champ>]` in the template context

Source: `bigup/bigup_pipelines.php:167 bigup_formulaire_charger()`

---

## HTML squelette — declaring file inputs

In the formulaire's HTML squelette, use `#SAISIE_FICHIER` (or a plain `<input type="file">` with
`#BIGUP_TOKEN`):

### Option A — #SAISIE_FICHIER (recommended)

```html
[(#SAISIE_FICHIER{mon_fichier, label=Fichier à déposer})]
```

`#SAISIE_FICHIER` wraps an `<input type="file">` with the bigup token, progress indicator,
and preview. The `name` attribute becomes the key used to retrieve the file.

### Option B — manual input with #BIGUP_TOKEN

```html
<input type="file" name="mon_fichier" />
[(#BIGUP_TOKEN{mon_fichier})]
```

`#BIGUP_TOKEN{name}` generates a signed token specific to this form + field combination.

For a multiple-file input:

```html
<input type="file" name="fichiers[]" multiple />
[(#BIGUP_TOKEN{fichiers[]})]
[(#REM) or equivalently: #BIGUP_TOKEN{fichiers/} ]
```

Source: `bigup/bigup_fonctions.php` (balise_BIGUP_TOKEN_dist)

---

## verifier() — files are in $_FILES

By the time `verifier()` runs, bigup has re-injected all already-uploaded files into `$_FILES`
via the `formulaire_receptionner` pipeline hook (`bigup_formulaire_receptionner()`).

Validate the upload exactly as you would a standard PHP file upload:

```php
function formulaires_mon_formulaire_verifier_dist($id = 0) {
    $erreurs = [];

    if (empty($_FILES['mon_fichier']['tmp_name'])) {
        $erreurs['mon_fichier'] = _T('info_obligatoire');
    } elseif ($_FILES['mon_fichier']['error'] !== UPLOAD_ERR_OK) {
        $erreurs['mon_fichier'] = _T('bigup:erreur_upload');
    }

    return $erreurs;
}
```

---

## traiter() — moving uploaded files

Files in `$_FILES` at `traiter()` time are still in bigup's temporary directory. Move them
to their final location using standard PHP or SPIP helpers:

```php
// formulaires/mon_formulaire.php
function formulaires_mon_formulaire_traiter_dist($id = 0) {
    if (!empty($_FILES['mon_fichier']['tmp_name'])) {
        $src  = $_FILES['mon_fichier']['tmp_name'];
        $dest = _DIR_IMG . 'myplugin/' . basename($_FILES['mon_fichier']['name']);
        sous_repertoire(_DIR_IMG, 'myplugin');

        if (move_uploaded_file($src, $dest) or rename($src, $dest)) {
            // file moved successfully
            $chemin = $dest;
        }
    }

    // ... save $chemin to DB ...

    return ['message_ok' => _T('myplugin:fichier_sauvegarde')];
}
```

**After a successful `traiter()`:** bigup's `formulaire_traiter` pipeline hook
(`bigup_formulaire_traiter()`) automatically purges the temporary bigup cache for this form.

**After a failed `traiter()`** (returns `message_erreur`): temporary files are preserved and
will be re-injected on the next form render.

---

## Pipeline flow with bigup active

```
Page render (charger):
  formulaire_charger pipeline:
    → bigup_formulaire_charger(): if _bigup_rechercher_fichiers, inject file list + token

POST received:
  formulaire_receptionner pipeline:
    → bigup_formulaire_receptionner(): re-inject files into $_FILES from bigup cache

  verifier():
    → $_FILES is populated with previously-uploaded files
  formulaire_verifier pipeline:
    → bigup_formulaire_verifier(): handle delete requests, verify file list integrity

  if erreurs == []:
    traiter():
      → move files out of bigup temp dir
    formulaire_traiter pipeline:
      → bigup_formulaire_traiter(): purge bigup cache for this form on success
```

---

## Multiple formulaires on the same page

Bigup scopes its token to the form name + its arguments hash (`formulaire_args`). Two instances
of the same formulaire (e.g. two `#FORMULAIRE_MON_FORMULAIRE{1}` and `{2}`) are automatically
treated separately — their file caches do not interfere.

---

## Client-side configuration

Bigup reads PHP-defined constants for client-side image resizing:

| Constant | Default | Effect |
|---|---|---|
| `_IMG_MAX_WIDTH` | not set | Max width before client-side resize |
| `_IMG_MAX_HEIGHT` | not set | Max height before client-side resize |
| `_IMG_GD_QUALITE` | `85` | JPEG quality after resize |

If neither `_IMG_MAX_WIDTH` nor `_IMG_MAX_HEIGHT` is defined, bigup reads from its admin config.

---

## Enabling bigup on the public side

By default bigup loads its JS only in the private space. To enable it on public forms:

```php
// config/mes_options.php or a pipeline
define('_BIGUP_CHARGER_PUBLIC', true);
// or via admin config: lire_config('bigup/charger_public')
```

---

## Invariants

- `_bigup_rechercher_fichiers` must be in the array returned by `charger()`, not just in `_hidden`
- The bigup token is bound to the form name, its arguments, and the visitor's session; tokens
  from one visitor cannot be used by another
- Temporary files are stored under `tmp/bigup/` and cleaned by a `genie` task
  (`bigup_nettoyer_repertoire_upload`)
- If bigup is not installed, `_bigup_rechercher_fichiers` is silently ignored — fall back to
  standard `$_FILES` handling with a regular `enctype="multipart/form-data"` form

---

## See also

- `../../spip-formulaires/references/cvt-formulaires.md` — formulaire CVT contract (`charger`, `verifier`, `traiter`)
- `references/pipelines.md` → `formulaire_charger`, `formulaire_receptionner`, `formulaire_traiter`
- `references/paquet-xml.md` → `<necessite>` for declaring the bigup dependency
