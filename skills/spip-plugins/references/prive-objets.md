# Prive Objets — Private-space templates

The private space renders editorial objects through a fragment hierarchy under `prive/objets/`.
SPIP resolves each slot by looking for a type-specific template first, then falling back to a
generic one. A plugin only needs to provide the slots it wants to customise.

---

## Directory layout

```
prive/objets/
├── liste/    ← object list table (included by exec pages)
├── infos/    ← side panel: statut, id number, "view online" button
├── contenu/  ← main body: wysiwyg preview of field content
└── editer/   ← edit-tab supplements: links, logo, translations
```

### Fallback resolution

For each slot, SPIP tries in order:

1. `prive/objets/<slot>/<type>.html` — plugin-specific template
2. `prive/objets/<slot>/objet.html` — SPIP generic fallback

Example for type `monobjet`:
- `prive/objets/liste/mon_objets.html` → fallback → `prive/objets/liste/objets.html` (SPIP ≥ 4.2)
- `prive/objets/infos/monobjet.html`   → fallback → `prive/objets/infos/objet.html`
- `prive/objets/contenu/monobjet.html` → fallback → `prive/objets/contenu/objet.html`

---

## liste/ — list table

Rendered inside the `?exec=mon_objets` page (see `references/exec-generique.md`).

**Context variables available:**

| Variable | Source |
|---|---|
| `#ENV{id_rubrique}` | rubrique filter from the URL |
| `#ENV{recherche}` | search term |
| `#ENV{statut}` | statut filter |
| `#ENV{nb}` | items per page |
| `#ENV{pagination}` | pagination type |
| `#GRAND_TOTAL` | total result count |

**Typical template:**

```html
[(#REM) prive/objets/liste/mon_objets.html ]
<B_liste>
#ANCRE_PAGINATION
<div class="liste-objets mon_objets">
<table class='spip liste'>
  <caption><strong class="caption">(#GRAND_TOTAL|singulier_ou_pluriel{myplugin:info_1_monobjet,myplugin:info_nb_monobjets})</strong></caption>
  <thead>
    <tr>
      <th class='statut'>[(#TRI{statut,#,ajax})]</th>
      <th class='titre principale'>[(#TRI{titre,<:info_titre:>,ajax})]</th>
      <th class='date'>[(#TRI{date,<:date:>,ajax})]</th>
    </tr>
  </thead>
  <tbody>
<BOUCLE_liste(MON_OBJETS){recherche?}{statut?}{par #ENV{par,date}}{pagination #ENV{nb,25}}>
  <tr class="[(#COMPTEUR_BOUCLE|alterner{odd,even})]">
    <td class='statut'>[(#STATUT|puce_statut{monobjet,#ID_MON_OBJET,ajax})]</td>
    <td class='titre'>[(#ID_MON_OBJET|generer_objet_url_ecrire{monobjet}|lien{#TITRE|sinon{<:info_sans_titre:>}})]</td>
    <td class='date'>[(#DATE|affdate)]</td>
  </tr>
</BOUCLE_liste>
  </tbody>
</table>
[(#PAGINATION)]
</div>
</B_liste>
```

---

## infos/ — side info panel

Rendered in the `#extra` column of the object view/edit page.

**What the generic `prive/objets/infos/objet.html` already provides:**
- An id number block
- `#FORMULAIRE_INSTITUER_OBJET` (statut change dropdown)
- A "view online" button when the object is published and has a public URL

**When to override:** add plugin-specific links or restrict available actions.

```html
[(#REM) prive/objets/infos/monobjet.html ]
<INCLURE{fond=prive/objets/infos/objet,env} />
[(#AUTORISER{modifier,monobjet,#ENV{id}})
  <div class='infos'>
    <a href="[(#ENV{id}|generer_objet_url_ecrire{monobjet}|parametre_url{mon_action,oui})]">Custom action</a>
  </div>
]
```

---

## contenu/ — wysiwyg preview body

Rendered in the wysiwyg block of the object view page.

The generic `prive/objets/contenu/objet.html` iterates over `champs_contenu` declared in
`declarer_tables_interfaces` (see `references/declarer-table.md`). If those are declared,
no custom contenu template is needed.

**balises available in this context:** `#TITRE`, `#TEXTE`, `#DESCRIPTIF`, and any column
balise of the table are available directly if the enclosing boucle is on the matching table.

```html
[(#REM) prive/objets/contenu/monobjet.html — minimal override ]
<BOUCLE_contenu(MON_OBJETS){id_mon_objet=#ENV{id}}>
<div class='champ contenu_titre'>
  <div class='label'><:info_titre:></div>
  <div class='titre'>#TITRE</div>
</div>
<div class='champ contenu_texte'>
  <div class='label'><:info_texte:></div>
  <div class='texte'>#TEXTE</div>
</div>
</BOUCLE_contenu>
```

---

## editer/ — edit-tab supplements

Three generic templates reusable via `#INCLURE` inside any edit formulaire squelette:

| Template | Purpose |
|---|---|
| `prive/objets/editer/liens.html` | Links to related objects |
| `prive/objets/editer/logo.html` | Logo/thumbnail management |
| `prive/objets/editer/traductions.html` | Translation group linking (`id_trad`) |

These are included from the formulaire's HTML squelette, not from PHP.

---

## What SPIP provides for free

| Need | Covered by |
|---|---|
| Statut + view-online button | `infos/objet.html` |
| Field content display | `contenu/objet.html` (via `champs_contenu`) |
| Logo management | `editer/logo.html` |
| Translation linking | `editer/traductions.html` |
| Basic list rendering | `prive/echafaudage/contenu/objets.html` (via exec) |

**Not provided for free:**
- A formatted list table (create `liste/<table_objet>.html`)
- Plugin-specific extra fields in the info panel
- Custom content preview layout

---

## See also

- `references/exec-generique.md` — exec pages `?exec=mon_objets` and `?exec=mon_objet`
- `references/declarer-objet.md` — `texte_objets`, `texte_objet`, `icone_objet` descriptor keys
- `references/declarer-table.md` — `champs_contenu` in `declarer_tables_interfaces`
