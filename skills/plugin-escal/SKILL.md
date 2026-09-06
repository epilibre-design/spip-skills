---
name: plugin-escal
description: Use when working on a SPIP site that uses the Escal squelette — installing or activating it, placing or configuring its noisettes (blocs latéraux, onglets « À la une »), reading or writing its escal/config settings, using its special pages (agenda, contact, recherche, annuaire, trombinoscope, forum de site, mots-clés, auteur, 404), choosing a layout, using its technical mots-clés, or restyling it without forking.
---

# Escal (squelette SPIP)

## Overview

Escal is a **jeu de squelettes** (full theme) for SPIP, prefix `escal`, category `squelette`.
Current release read here: **5.6.0**, schema `1.0.17`, GNU/GPL, compatible `[4.1.0;4.*]`.
Author: Jean-Christophe Villeneuve. Docs: <https://contrib.spip.net/Escal-4077>.

**The one thing to understand:** Escal is a *composition engine driven by one meta*.
A single SPIP meta named `escal`, casier `escal/config`, decides both

1. **which block goes where** — every slot in every page is an `<INCLURE>` whose *fond name* is read from the meta, and
2. **what the site looks like** — six stylesheets are SPIP squelettes (`*.css.html`, `#CACHE{0}`) recompiled per request with `#CONFIG` values injected.

```spip
[(#CONFIG{escal/config/blocnav3}|=={rien}|non)
  <div class="cadre-couleur">
    <INCLURE {fond=inclusions/inc-#CONFIG{escal/config/blocnav3,edito}}{env}{ajax}>
  </div>
]
```

Nothing about the composition is hard-coded in the page squelettes. Changing a setting changes which file gets included.

## Vocabulary traps — verify before answering

These are the things agents reliably get wrong about Escal. Every row was an actual hallucination in baseline testing.

| Wrong (do not say) | Correct |
|---|---|
| `noisettes/noisette_*.html` | Noisettes live in **`inclusions/inc-*.html`**. There is no `noisettes/` directory. |
| `spip plugin:install escal` | spip-cli uses **`plugins:svp:telecharger`** then **`plugins:activer`**. There is no `plugin:install`. |
| Meta is `config_escal` | Meta is **`escal`**; settings sit in casier **`escal/config`**; read with `#CONFIG{escal/config/‹cle›}` or `lire_config('escal/config/‹cle›')`. |
| `bloc_gauche_2`, `bloc_droite_1` | Slot keys are **`blocnav‹n›`** (colonne navigation) and **`blocextra‹n›`** (colonne extra), with a page-family suffix: `blocnavart3`, `blocextrarub2`, `blocnavpages7`, `blocnavforumsite4`. |
| "`plugins:svp:telecharger` said it worked, so the plugin is there" | On **unpatched** spip-cli it prints successes while downloading nothing (MR !91). Always confirm with `plugins:lister --short`, never with the download command's own output. |
| Escal has a "paste your CSS here" field | It does not. Surcharge via **`squelettes/styles/perso.css`** and **`squelettes/persoconfig.css.html`**. |
| "fluide/mixte change the column order" | Column order is the **letters** (`PMP`, `MPP`, `PPM`, `MP`, `PM`). `fixe`/`fluide`/`mixte` are **width modes**, orthogonal to order. |
| "`blocnav` = colonne de gauche" | **Not always.** `navigation`/`extra` are *slots*, not sides — which side they land on depends on the layout, and RTL swaps them. See the placement table below. |
| Tab value = noisette filename | The `onglet‹n›` values are their own vocabulary: `derniersarticles` → `inc-une_derniers.html`, `rubrique2` → `inc-rubrique_accueil2.html`. See references/noisettes.md. |

**When unsure of an exact key or filename, read the plugin source rather than guessing.** The authoritative list of block values is the `'data'` arrays in `formulaires/configurer_escal_choix_blocs.php`.

## Install with spip-cli

Verified against **spip-cli 2.0.1** on SPIP 4.4.14. Run `spip list` if a command name looks off.

### ⚠ `plugins:svp:telecharger` is broken in stock spip-cli

Upstream 2.0.1 **cannot download a plugin at all**, and hides it:

- the depot lookup compares `UPPER(pl.prefixe)` to `LOWER("PREFIXE")` — never equal, so every plugin
  reports *« Le plugin xxx n'est pas référencé »*;
- past that, the `autoriser_exception` is registered under the wrong key and `action/teleporter.php`
  refuses the download;
- and `one_action()` returns the action description *before* running it, so failures were printed as
  successes.

Fixed by [MR !91](https://git.spip.net/spip-contrib-outils/spip-cli/-/merge_requests/91). Check the
local copy before trusting a download:

```bash
grep -q 'UPPER(pl.prefixe) = UPPER' src/Command/PluginsSvpTelecharger.php \
  && echo "patché (MR!91)" || echo "NON patché — les téléchargements échoueront en silence"
```

On an unpatched CLI, install the plugins by hand into `plugins/` (or via the private area) and use
spip-cli only for `plugins:activer`.

### Recette

Run from the SPIP root (the directory holding `config/connect.php`).

```bash
# 0. SVP unpacks into plugins/auto/ — core:preparer does NOT create it.
#    Without it every download fails with
#    « Le répertoire de paquets plugins/auto/ n'est pas accessible en écriture ».
mkdir -p plugins/auto && chmod u+rwx plugins/auto

# 1. register the official depot (once)
spip plugins:svp:depoter https://plugins.spip.net/depots/principal.xml

# 2. download Escal and its hard dependencies
spip plugins:svp:telecharger saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y

# 3. activate, dependencies first
spip plugins:activer saisies verifier yaml agenda fullcalendarcompat \
     svpstats nospam facteur tri_par_rubrique aide escal -y

# 4. run the plugin upgrades, then check
spip plugins:maj:bdd                 # prints « Installation du plugin Escal … MAJ 1.0.17 »
spip plugins:lister --short          # every prefix above must appear
spip cache:vider
```

SVP unpacks one directory per version: `plugins/auto/escal/v5.6.0/`, not `plugins/auto/escal/`.
Step 2 also pulls **`calendriermini`** as a transitive dependency — expect 11 packages, not 10.

### ⚠ After a CLI install, the starter contents stay unpublished

`escal_upgrade()` creates a hidden rubrique plus three articles — *Édito*, *Accès direct*,
*Mentions légales* — already tagged, and asks for `statut = publie`. From the private area that
works. **From spip-cli it does not:** there is no authenticated author, `autoriser('publierdans', …)`
returns false, and the three articles are left in `prepa`. The Édito and Accès direct blocks then
render empty and the site looks broken. Publish them by giving the CLI a session:

```bash
spip php:eval 'include_spip("action/editer_objet"); include_spip("base/abstract_sql");
$GLOBALS["visiteur_session"] = sql_fetsel("*", "spip_auteurs", "statut=" . sql_quote("0minirezo"));
$GLOBALS["visiteur_session"]["webmestre"] = "oui";
foreach (sql_allfetsel("id_article", "spip_articles", "statut=" . sql_quote("prepa")) as $x) {
  objet_instituer("article", $x["id_article"], ["statut" => "publie"]);
}'
```

Note the `sql_quote()`: the whole PHP program travels inside shell single quotes, so any nested
`\"` is mangled before PHP sees it. Build SQL string literals with `sql_quote()` rather than
escaping quotes — that is the form that actually runs.

Same limitation for anything scripted through `php:eval` that crosses an `autoriser()` check — set
the session first. `config:ecrire` is unaffected: it writes `spip_meta` directly.

### Notes

`plugins:activer` and `plugins:svp:telecharger` take prefixes as positional arguments, or
`--from-list=a,b,c`; `-y` skips the prompts. Activating `escal` runs `escal_upgrade()`, which creates
the two mots-clés groups and the starter contents — never hand-write the `escal` meta to "install" it.

Optional plugins Escal detects if present: `palette`, `spip_400`, `orr` (typographic shortcuts),
`Shoutbox`, `reservation_evenement`, `reservations_multiples`. Enable the matching noisettes in the
*Plugins* config screen.

## Reading and writing settings from the CLI

`config:lire` and `config:ecrire` operate on `spip_meta`, so they reach Escal's casier directly —
handy for scripted setup and for inspecting a site you cannot log into.

```bash
spip config:lire escal --json                     # dump the whole configuration
spip config:lire escal/config/blocnav2            # one setting

spip config:ecrire escal/config/blocnav2:derniers_articles     # full path as the option
spip config:ecrire -p escal config/blocnav2:derniers_articles  # same thing, -p prepends "escal/"
spip config:ecrire escal/config/nomsite --valeur 'Mon site'    # value containing ":" or spaces
```

The argument is split on the **first** colon only. A value that itself contains a colon (a URL, a
time) must go through `--valeur`.

Several settings at once — note the nesting, `-p escal` plus a `config` sub-object rebuilds
`escal/config/…`:

```bash
spip config:ecrire -p escal --json '{"config":{"blocnav1":"edito","blocnav2":"derniers_articles","layout":"PMPfluide"}}'
spip cache:vider
```

Always `cache:vider` afterwards: the generated stylesheets are `#CACHE{0}` but the pages that include
them are not.

## Placing a noisette

Configuration UI: **espace privé → Squelettes → Escal** (webmestre only). Page `?exec=configurer_escal&cfg=choix_blocs`.

The slot key encodes column + page family + rank:

| Page family | Colonne navigation | Colonne extra | Centre |
|---|---|---|---|
| Sommaire | `blocnav1…10` | `blocextra1…10` | `blocune1…3` |
| Article | `blocnavart1…6` | `blocextraart1…6` | — |
| Rubrique | `blocnavrub1…6` | `blocextrarub1…6` | — |
| Autres pages | `blocnavpages1…10` | `blocextrapages1…10` | — |
| Forum de site | `blocnavforumsite1…10` | — | — |

The value is a **noisette short name** (no `inc-` prefix, no `.html`), or `rien` for an empty slot.
Example — *Derniers articles* in second position of the navigation column on the home page:

```php
ecrire_config('escal/config/blocnav2', 'derniers_articles');
// → renders inclusions/inc-derniers_articles.html
```

Each family accepts a **different subset** of noisettes (27 on the sommaire, 20 on article, 14 on the site forum). Setting a value outside its family's list yields an empty slot, not an error.

**Full catalogue, per-family availability, and the central « À la une » tabs: `references/noisettes.md`.**

## Layouts

Order is spelled by the letters, read left to right: **P** = narrow column, **M** = `main#contenu`.

- Orders: `PMP`, `MPP`, `PPM`, `MP`, `PM`
- Width modes: *fixe* (suffix none), *fluide*, *mixte* (side columns fixed, centre elastic)
- Mixte exists only for the 3-column orders → **13** `layout*.css.html` files

The 13 valid values of `escal/config/layout`, verbatim:

`PMP` `MPP` `PPM` `MP` `PM` · `PMPfluide` `MPPfluide` `PPMfluide` `MPfluide` `PMfluide` · `PMPmixte` `MPPmixte` `PPMmixte`

Widths: `largeurlayoutbase` + `colonnelayoutbase` (fixe), `largeurmaxlayoutfluide` (fluide), `colonnelayoutmixte` (mixte).

**Where each column actually lands** — `navigation` is not a synonym for "left":

| Ordre | Gauche → droite | Colonne la plus à gauche | Colonne la plus à droite |
|---|---|---|---|
| `PMP` | nav · contenu · extra | `blocnav*` | `blocextra*` |
| `PPM` | nav · extra · contenu | `blocnav*` | `blocextra*` (2ᵉ en partant de la gauche) |
| `PM` | nav · extra · contenu | `blocnav*` | `blocextra*` (2ᵉ en partant de la gauche) |
| `MPP` | contenu · nav · extra | `blocnav*` (mais à droite du contenu) | `blocextra*` |
| `MP` | contenu · nav · extra | `blocnav*` (mais à droite du contenu) | `blocextra*` |

`blocnav*` always precedes `blocextra*` in reading order; what changes between layouts is where the
pair sits relative to the content. In `MPP` and `MP` there is **no** column left of the content — a
request for "the left column" there needs a layout change (`PMP`, `PPM` or `PM`) first.

In RTL (`#LANG_DIR` = `rtl`) the two `<section>` elements are emitted in the reverse order, mirroring the whole thing. So "put this block in the left column" is only answerable once you know the site's layout — check `escal/config/layout` first.

The layout stylesheet is loaded with `media="screen and (min-width: 641px)"` — below that, `styles/mobile.css` stacks everything. `#ENV{layout}` overrides `#CONFIG`, so `?layout=MPfluide` previews without saving.

## Mots-clés techniques

Installation creates two groups: **`affichage`** (44 mots) and **`Agenda_couleur`** (5 mots, hex code or CSS colour in the *descriptif*). Squelettes test them with `{titre_mot=…}`.

Three roles — full list in `references/configuration.md`:

- **Reroute the squelette**: `pleinepage` on an article → `article_pleinepage.html`; `forum` on a secteur → `forumSite-*.html`; `trombino`, `popup`.
- **Designate content**: `edito`, `acces-direct`, `actus`, `annonce`, `citations`, `mentions-legales` — the noisette fetches the article carrying the mot.
- **Exclude from a listing**: `invisible`, `pas-au-menu`, `pas-a-la-une`, `pas-au-plan`, `site-exclu`.

Two further groups, **`type_article`** and **`type_rubrique`**, are **not created by the installer** — the webmestre creates them. Attach such a mot to a *rubrique* and Escal includes `inclusions/inc-article_‹mot›.html` / `inc-rubrique_‹mot›.html` when that file exists, falling back to `inc-article.html` / `inc-rubrique_normal.html`. This is the supported way to give a section its own gabarit.

To create one: espace privé → **Mots-clés** → *Créer un nouveau groupe de mots*, name it exactly
`type_rubrique` (or `type_article`), tick *Rubriques* (resp. *Articles*) in its `tables_liees`,
add a mot, then attach that mot to the rubrique.

**How the filename is derived:** the squelette does `#SET{type, #TITRE}` and includes
`inclusions/inc-article_#GET{type}.html` — the mot's **titre is used verbatim**, with no slugifying,
no lowercasing and no accent stripping. A mot titled `Recettes` looks for `inc-article_Recettes.html`;
the shipped `inc-rubrique_forumSite.html` is matched by a mot titled exactly `forumSite`. Give the mot
a short ASCII title with no spaces, and name the file with the identical string. If the file is
missing, Escal silently falls back to `inc-article.html` / `inc-rubrique_normal.html` — a wrong case
looks exactly like "the feature doesn't work".

## Restyling without forking

SPIP resolves `squelettes/` before plugins, and both hooks load *after* every generated stylesheet.

| Goal | File to create |
|---|---|
| Plain CSS overrides | `squelettes/styles/perso.css` |
| CSS that reads `#CONFIG` | `squelettes/persoconfig.css.html` (ship-empty file exists for this) |
| Colour one section | `squelettes/styles/secteur‹ID_SECTEUR›.css` |
| Replace a block | `squelettes/inclusions/inc-‹nom›.html` |
| Add a new block | `squelettes/inclusions/inc-‹monbloc›.html`, then set a slot to `monbloc` |

Never edit files inside the plugin directory — upgrades overwrite them.

## Reference files

- `references/noisettes.md` — the 107 noisettes: catalogue, per-family availability, « À la une » tabs, per-noisette settings
- `references/pages-speciales.md` — agenda, mini-calendrier, auteur, forum d'article, recherche, 404, contact, annuaire, mots-clés, trombinoscope, forum de site: URL, activation, dependencies
- `references/configuration.md` — the 24 config screens, key-naming conventions, the 44 technical mots-clés, PHP surface (filtres, balises, pipelines)
- `references/documentation-amont.md` — map of the author's tutorial site to noisettes, pages and settings. **Beginner-oriented human docs, secondary to the code:** never quote an identifier or a default from it without confirming it in the source.
