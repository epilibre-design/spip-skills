# Plugin directory layout

Standard structure of a SPIP 4.1+ plugin. Extracted from the core's plugins-dist.

## Table of contents

1. [Directory tree](#directory-tree)
2. [Special root-level files](#special-root-level-files)
3. [Directories](#directories)
4. [Summary: when is each file/directory loaded?](#summary-when-is-each-filedirectory-loaded)

---

## Directory tree

```
monplugin/
├── paquet.xml                        # required manifest
│
├── monplugin_options.php             # loaded 1st (constants, paths, pipelines)
├── monplugin_fonctions.php           # loaded 2nd (public functions and filtres)
├── monplugin_pipelines.php           # pipeline handlers (lazy-loaded)
├── monplugin_autoriser.php           # authorization functions
├── monplugin_administrations.php     # install / upgrade / uninstall
├── monplugin_ieconfig.php            # configuration export/import (optional)
│
├── action/                           # secured action scripts
├── balise/                           # custom SPIP balises
├── base/                             # SQL table declarations
├── critere/                          # custom boucle critères
├── css/                              # stylesheets
├── exec/                             # private space pages
├── formulaires/                      # CVT formulaires
├── genie/                            # scheduled tasks
├── images/                           # plugin icons and images
├── inc/                              # helper functions (lazy-loaded)
├── javascript/                       # JS files
├── lang/                             # language files
├── lib/                              # third-party libraries
├── modeles/                          # public models
├── prive/                            # private space squelettes
└── src/                              # PHP classes (PSR-4 autoload)
```

---

## Special root-level files

### Loading order

SPIP loads these files in a fixed order on every request:

```
1. [prefix]_options.php           ← always, very early (before pipelines)
2. [prefix]_fonctions.php         ← always, after options
3. [prefix]_pipelines.php         ← on demand, when a pipeline fires
4. [prefix]_autoriser.php         ← on demand, via the `autoriser` pipeline
5. [prefix]_administrations.php   ← only during install/upgrade
```

---

### `[prefix]_options.php`

Loaded **first**, before SPIP is fully initialized. Used to:
- Define constants (`define('_MA_CONSTANTE', 'valeur')`)
- Add file search paths
- Declare pipelines in pure PHP (alternative to `paquet.xml`)
- Initialize global configuration variables

```php
// compresseur_options.php
if (defined('_AUTO_GZIP_HTTP') && _AUTO_GZIP_HTTP) {
    // early compression configuration
}
```

**Constraint:** do not call SPIP functions that require the database — it is not available yet.

---

### `[prefix]_fonctions.php`

Loaded right after options. Contains:
- Squelette filtres (`function monplugin_filtre($val)`)
- Public utility functions
- Balises as functions (alternative to the `balise/` directory)

```php
// monplugin_fonctions.php
if (!defined('_ECRIRE_INC_VERSION')) { return; }

function monplugin_ma_fonction($arg) {
    // available in squelettes as |ma_fonction
}
```

---

### `[prefix]_pipelines.php`

Contains **pipeline handlers**: the functions called when SPIP runs a pipeline. Lazy-loaded — included only when the relevant pipeline executes.

```php
// forum_pipelines.php
if (!defined('_ECRIRE_INC_VERSION')) { return; }

function forum_accueil_encours($flux) {
    // processing
    return $flux;
}
```

See `pipelines.md` for the full catalogue and signatures.

---

### `[prefix]_autoriser.php`

Contains authorization functions. Loaded via the `autoriser` pipeline.

```php
// forum_autoriser.php
if (!defined('_ECRIRE_INC_VERSION')) { return; }

function autoriser_forum_voir_dist($faire, $type, $id, $qui, $opts) {
    return true;
}
```

Naming convention: `autoriser_[objet]_[action]_dist($faire, $type, $id, $qui, $opts)`.

---

### `[prefix]_administrations.php`

Loaded only during plugin installation, upgrades, and uninstallation. Must define:

```php
// forum_administrations.php
if (!defined('_ECRIRE_INC_VERSION')) { return; }

function forum_upgrade($nom_meta_base_version, $version_cible) {
    $maj = [];
    $maj['create'] = [
        ['maj_tables', ['spip_forum']],
    ];
    $maj['1.1.0'] = [
        ['sql_alter', 'TABLE spip_forum ADD id_objet bigint(21) DEFAULT 0 NOT NULL'],
    ];
    maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

function forum_vider_tables($nom_meta_base_version) {
    sql_drop_table('spip_forum');
    effacer_meta($nom_meta_base_version);
}
```

- `[prefix]_upgrade()` — called on activation and on every `schema` bump in `paquet.xml`
- `[prefix]_vider_tables()` — called on deactivation when the user checks "delete data"

**Only needed when `schema` is declared in `paquet.xml`.**

---

### `[prefix]_ieconfig.php`

Convention for hooking into the configuration import/export system (pipeline `ieconfig_metas`). Not universal — present in most plugins-dist.

---

## Directories

### `action/`

Secured action scripts, called via `?action=nom&arg=...&hash=...`.

```
action/
├── editer_forum.php        # function action_editer_forum_dist()
├── instituer_forum.php
└── supprimer_document.php
```

Each file defines `function action_[nom]_dist()`. The `arg` parameter carries HMAC-secured data.

---

### `balise/`

Custom SPIP balises, one per file. The filename matches the balise name in lowercase.

```
balise/
├── formulaire_forum.php    # balise FORMULAIRE_FORUM
└── saisie_fichier.php      # balise SAISIE_FICHIER
```

Each file defines `function balise_[NOM]_dist(&$p)` where `$p` is a `Champ` object.

---

### `base/`

SQL table declarations, loaded via the `declarer_tables_*` pipelines.

```
base/
├── forum.php       # declarer_tables_objets_sql, declarer_tables_interfaces
├── medias.php      # declarer_tables_principales, declarer_tables_auxiliaires, ...
└── urls.php        # declarer_url_objets (object URL shortcuts / routing list)
```

See `declarer-table.md` and `declarer-objet.md` for function signatures.
See `urls-objets.md` for `#URL_<TYPE>` behavior and custom object URL declarations.

---

### `critere/`

Custom critères for SPIP boucles.

```
critere/
└── mon_critere.php     # function critere_MON_CRITERE_dist(&$b, &$crit)
```

Rare in plugins-dist; most plugins rely on the core's built-in critères.

---

### `css/`

Stylesheets. Typically registered via the `insert_head_css` or `header_prive_css` pipelines.

```
css/
├── bigup.css
└── vignettes.css.html      # .html = SPIP squelette that generates dynamic CSS
```

---

### `exec/`

Full private-space pages, reachable via `?exec=nom`.

```
exec/
└── admin_plugin.php    # function exec_admin_plugin_dist()
```

Each file defines `function exec_[nom]_dist()`.

---

### `formulaires/`

CVT formulaires (Charger/Vérifier/Traiter). Each formulaire is a pair of files:

```
formulaires/
├── editer_forum.html       # HTML squelette of the formulaire
├── editer_forum.php        # charger / verifier / traiter functions
├── inc-forum_previsu.html  # partial (inc- prefix = not a standalone CVT formulaire)
└── methodes_upload/        # sub-directories for variants
```

See `cvt-formulaires.md` for the full CVT function signatures.

---

### `genie/`

Scheduled tasks (SPIP cron), declared via `<genie>` in `paquet.xml`.

```
genie/
├── medias_nettoyer_repertoire_upload.php   # function medias_nettoyer_repertoire_upload()
└── optimiser_revisions.php                 # function optimiser_revisions()
```

The file may be named `[prefix]_[nom].php` or just `[nom].php`. The function name matches the filename (without `.php`).

---

### `images/`

Plugin icons and images. Paths are referenced in `paquet.xml` (`logo`), in `<menu>` (`icone`), or inside squelettes.

```
images/
├── forum-16.png
├── forum-32.png
└── forum-16.svg
```

---

### `inc/`

Helper functions loaded **on demand** via `charger_fonction('nom', 'inc')`.

```
inc/
├── forum.php               # general forum functions
├── forum_insert.php        # charger_fonction('forum_insert', 'inc')
└── email_notification_forum.php
```

Each file exposes one or more functions, guarded by `if (!defined('_ECRIRE_INC_VERSION')) { return; }`.

---

### `javascript/`

JavaScript files. Registered via the `jquery_plugins`, `insert_head`, or `header_prive` pipelines, or via `<script>` in `paquet.xml`.

```
javascript/
├── bigup.js
└── bigup.trads.js.html     # .html = SPIP squelette that generates dynamic JS
```

---

### `lang/`

Language files. Two types:

**Metadata file** (generated by Salvatore):
```
lang/
└── forum.xml               # translation statistics per language
```

**Translation files**:
```
lang/
├── forum_fr.php            # reference language
├── forum_en.php
├── forum_ar.php
└── paquet-forum.xml        # paquet-specific module (SVP information)
```

Some plugins have multiple language modules (e.g. `forum.xml` + `adminmots.xml` in mots).

---

### `lib/`

Third-party libraries (non-Composer). Usually kept with their own internal structure.

```
lib/
├── flow/                   # Flow.js (bigup)
└── lity/                   # Lity lightbox (mediabox)
```

---

### `modeles/`

Public models, invocable from squelettes via `<modele|nom>` or typographic shortcuts.

```
modeles/
├── document.html           # <docXX> in content text
├── document_desc.html
└── image_env.html
```

---

### `prive/`

Squelettes for the private space. Canonical structure mirroring the core:

```
prive/
├── squelettes/
│   ├── contenu/            # main body of private pages
│   ├── inclure/            # reusable partials
│   ├── navigation/         # navigation bars
│   └── top/                # page headers
├── objets/
│   ├── liste/              # object list views (tables)
│   ├── infos/              # object summary card
│   ├── contenu/            # detailed object view
│   └── editer/             # edit formulaires
├── themes/
│   └── spip/
│       └── images/         # icons for the private interface
└── modeles/                # private space models
```

Files under `prive/squelettes/inclure/` may have an associated `_fonctions.php` (same name, `_fonctions` suffix).

---

### `src/`

PHP classes with PSR-4 autoloading via Composer. Used in modern plugins.

```
src/
└── Monplugin/
    ├── Service.php
    └── Repository.php
```

Must be declared in the plugin's `composer.json`.

---

## Summary: when is each file/directory loaded?

| File / directory | When loaded |
|---|---|
| `_options.php` | Every request, first |
| `_fonctions.php` | Every request, after options |
| `_pipelines.php` | On demand, when a pipeline fires |
| `_autoriser.php` | On demand, via the `autoriser` pipeline |
| `_administrations.php` | Plugin install / upgrade / uninstall only |
| `action/` | Request with `?action=nom` |
| `balise/` | Squelette compilation when the balise is used |
| `base/` | Via `declarer_tables_*` pipelines |
| `exec/` | Private space request with `?exec=nom` |
| `formulaires/` | Request that renders the formulaire |
| `genie/` | Scheduled cron task |
| `inc/` | Explicit `charger_fonction('nom', 'inc')` call |
| `modeles/` | Model rendering inside a squelette |
| `prive/` | Any private space request |
