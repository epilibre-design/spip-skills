# Generic Exec — objet_edit and liste_objets out-of-the-box

SPIP provides two generic pages for any declared objet éditorial:
- `?exec=mon_objets` — list page
- `?exec=mon_objet` — view/edit page

These are built dynamically from the type name declared in `declarer_tables_objets_sql` — no
exec file needs to be created for standard use cases.

Source: `prive/echafaudage/`

---

## How SPIP resolves `?exec=XXX`

1. Looks for `ecrire/exec/XXX.php` — plugin or core exec file
2. If absent: looks for `prive/squelettes/contenu/XXX.html`
3. If absent: tries to infer the object type from `XXX`

For declared types, SPIP automatically recognises:
- `?exec=mon_objets` → list page (the `table_objet` plural value)
- `?exec=mon_objet` → view/edit page (the `type` singular value)

---

## List page — `?exec=mon_objets`

SPIP serves `prive/echafaudage/contenu/objets.html`, which:

1. Checks `autoriser('voir_liste_<table_objet>')` or `autoriser('voir')`, calls `sinon_interdire_acces`
2. Displays the heading via `objet_info{texte_objets}`
3. Includes `prive/objets/liste/<table_objet>.html` (or the generic fallback)
4. Shows a "create" button if `autoriser('creer', $type)` and `editable = 'oui'`

```html
[(#REM) prive/echafaudage/contenu/objets.html — simplified ]
[(#AUTORISER{voir,[_(#OBJET|objet_info{table_objet})]}|sinon_interdire_acces)]
<h1 class="grostitre">[(#OBJET|objet_info{texte_objets}|_T)]</h1>
<INCLURE{fond=prive/objets/liste/#OBJET|objet_info{table_objet},env,ajax} />
[(#OBJET|objet_info{editable}|et{#AUTORISER{creer,#OBJET}})
  [(#REM|generer_objet_url_ecrire_edit{#OBJET}|parametre_url{new,oui}|icone_verticale{...})]
]
```

---

## View/edit page — `?exec=mon_objet`

SPIP serves `prive/echafaudage/contenu/objet.html`, which handles two sub-modes:

**Edit mode** (when `exec` matches the object's `url_edit`):
- Includes `prive/squelettes/contenu/<exec>.html` in ajax mode
- On click from the view page, loads the edit formulaire inline without a page reload

**View mode** (when `exec` matches the object's `url_voir`):
1. Checks `autoriser('voir', $type, $id)`, calls `sinon_interdire_acces`
2. Loads the object's lang (`#INFO_LANG`) and calls `changer_typo`
3. Shows title + edit button (if `autoriser('modifier', ...)` and `editable`)
4. Includes `prive/objets/contenu/<type>.html` (wysiwyg preview)
5. Includes `prive/objets/contenu/<type>-enfants.html` if it exists
6. Fires pipeline `affiche_enfants`
7. Fires pipeline `afficher_complement_objet`

---

## What you get for free

| Feature | Condition |
|---|---|
| `?exec=mon_objets` URL works | `table_objet` declared |
| `?exec=mon_objet` URL works | `type` declared, `url_voir = type` |
| Authorisation checks | `autoriser('voir', ...)`, `autoriser('modifier', ...)` |
| Edit / create buttons | `editable = 'oui'` in descriptor |
| Breadcrumb (fil d'Ariane) | `parent` declared (object has rubrique parent) |
| Left navigation panel | Automatic if table is known |
| Inline ajax edit formulaire | `url_edit` declared + CVT formulaire present |
| Info side panel (statut, id) | Generic `prive/objets/infos/objet.html` |
| Wysiwyg content preview | `prive/objets/contenu/objet.html` + `champs_contenu` |
| "View online" button | `page != ''` in descriptor |
| `afficher_complement_objet` pipeline | Automatic (third-party plugins hook here) |

---

## What you must create

| Need | File to create |
|---|---|
| Formatted list table | `prive/objets/liste/<table_objet>.html` |
| Custom content preview | `prive/objets/contenu/<type>.html` |
| Custom info panel | `prive/objets/infos/<type>.html` |
| Edit formulaire | `formulaires/editer_<type>.html` + PHP |
| Statut change form | Automatic if `statut_textes_instituer` declared |
| Sub-objects (children) | `prive/objets/contenu/<type>-enfants.html` (optional) |

---

## Overriding a generic exec page

When the generic behaviour is insufficient, create explicit squelette files — SPIP picks
these up before the echafaudage fallbacks:

```
prive/squelettes/contenu/mon_objet.html        ← view
prive/squelettes/contenu/mon_objet_edit.html   ← edit formulaire wrapper
prive/squelettes/navigation/mon_objet.html     ← left navigation
prive/squelettes/hierarchie/mon_objet.html     ← breadcrumb
```

---

## Declaring url_edit and url_voir in the descriptor

```php
// base/myplugin.php
$tables['spip_mon_objets'] = [
    'type'     => 'monobjet',
    'url_voir' => 'monobjet',        // ?exec=monobjet (default = type)
    'url_edit' => 'monobjet_edit',   // ?exec=monobjet_edit (default = type + '_edit')
    // ...
];
```

If the defaults are acceptable, omit these keys.

---

## Invariants

- SPIP deduces `url_voir` = `type` and `url_edit` = `type . '_edit'` when they are absent
- The echafaudage pages use `#OBJET` and `#ID_OBJET` from the URL environment — the object
  type and id are passed as `objet=monobjet&id_objet=42` query parameters
- Setting `editable = 'non'` hides the edit button but does not prevent direct URL access;
  authorisation functions must enforce access control independently

---

## See also

- `references/prive-objets.md` — liste/, infos/, contenu/, editer/ templates
- `references/declarer-objet.md` — descriptor keys `url_voir`, `url_edit`, `editable`, `page`
- `references/autorisations.md` — `autoriser()`, `sinon_interdire_acces`
