# Pipelines — Complete catalogue

SPIP 4.4 — extracted from `ecrire/paquet.xml` (122 entries) and core implementations.

## Table of contents

1. [How pipelines work](#how-pipelines-work)
2. [Edition](#edition) — `pre_insertion`, `post_insertion`, `pre_edition`, `post_edition`, `pre_edition_lien`, `post_edition_lien`
3. [SQL table declaration](#sql-table-declaration) — `declarer_tables_*`
4. [Authorization](#authorization) — `autoriser`
5. [CVT formulaires](#cvt-formulaires) — `formulaire_charger`, `formulaire_verifier`, `formulaire_traiter`, `formulaire_fond`
6. [Public display](#public-display) — `affichage_final`, `insert_head`, `insert_head_css`, `recuperer_fond`, `styliser`
7. [Private space display](#private-space-display) — `header_prive`, `affiche_milieu`, `affiche_gauche`, `boite_infos`, `afficher_fiche_objet`
8. [Configuration and metadata](#configuration-and-metadata) — `configurer_liste_metas`, `taches_generales_cron`
9. [Database maintenance](#database-maintenance) — `optimiser_base_disparus`
10. [Search](#search) — `prepare_recherche`, `rechercher_liste_des_champs`
11. [Notifications](#notifications)
12. [Typography](#typography) — `pre_propre`, `post_propre`, `pre_liens`, `pre_typo`, `post_typo`
13. [Boucle compilation](#boucle-compilation) — `pre_boucle`
14. [Quick reference — all other pipelines](#quick-reference)

---

## How pipelines work

Pipelines are middleware chains. Each registered function receives a value, may modify it, and must return it. SPIP calls each registered function in sequence; the output of one becomes the input of the next.

**Two calling patterns:**

### Text pipeline — `string` in, `string` out

```php
// SPIP calls:
$texte = pipeline('affichage_final', $texte);

// Your handler:
function myplugin_affichage_final(string $texte): string {
    return str_replace('foo', 'bar', $texte);
}
```

### Array pipeline — `$flux` array in, `$flux` array out

```php
// SPIP calls:
$flux = pipeline('post_edition', [
    'args' => ['objet' => 'article', 'id_objet' => 42, ...],
    'data' => $champs,
]);

// Your handler:
function myplugin_post_edition(array $flux): array {
    $objet   = $flux['args']['objet'];
    $id      = $flux['args']['id_objet'];
    $champs  = $flux['data'];   // what was actually saved

    // do something
    return $flux;               // always return $flux
}
```

**Critical rule:** always `return $flux`, even unchanged. Returning `null` breaks the chain for every plugin that comes after you.

### Extension-point pipelines

Pipelines declared in `paquet.xml` with `action=""` have no default implementation. They exist so other plugins can hook into them:

```xml
<!-- declares the hook point -->
<pipeline nom="forum_objets_depuis_env" action="" />
```

Any plugin can then register its own handler against that pipeline name.

---

## Edition

These pipelines fire during object create and modify operations. They are called by `objet_inserer()` and `objet_modifier_champs()` in `ecrire/action/editer_objet.php` and `ecrire/inc/modifier.php`.

---

### `pre_insertion`

Fires **before** a new object row is inserted into the database. Use it to alter or augment the field values before insertion.

```
$flux['args']
    table       string   SQL table name, e.g. 'spip_forum'
    id_parent   int      Parent ID (rubrique or similar), 0 if none

$flux['data']   array    Field/value pairs about to be inserted — modify and return
```

```php
// forum_pipelines.php
function forum_pre_insertion($flux) {
    if ($flux['args']['table'] === 'spip_forum') {
        $flux['data']['ip'] = $_SERVER['REMOTE_ADDR'];
    }
    return $flux;
}
```

---

### `post_insertion`

Fires **after** the new row has been inserted. `$flux['data']` contains the fields that were inserted; `$flux['args']['id_objet']` is the new ID. The return value of this pipeline is **ignored** by SPIP — return `$flux` anyway by convention.

```
$flux['args']
    table       string   SQL table name
    id_parent   int      Parent ID
    id_objet    int      Newly created ID

$flux['data']   array    Fields that were inserted (read-only in practice)
```

```php
// revisions_pipeline.php
function revisions_post_insertion($flux) {
    // record a revision entry after any objet is created
    if ($flux['args']['id_objet'] > 0) {
        include_spip('inc/revisions');
        enregistrer_version($flux['args']['table'], $flux['args']['id_objet'], $flux['data']);
    }
    return $flux;
}
```

---

### `pre_edition`

Fires **before** modified fields are written to the database. Receives the fields that *will* be written; what you return in `$flux['data']` is what actually gets saved. Use it to add computed fields or veto changes.

```
$flux['args']
    table            string   SQL table name (e.g. 'spip_articles') — kept for BC
    table_objet      string   Short table name (e.g. 'articles')
    spip_table_objet string   SQL table name (canonical, use this)
    objet            string   Object type (e.g. 'article')
    id_objet         int      Object ID
    action           string   'modifier' | 'instituer' | custom
    champs_anciens   array    Row values before modification
    desc             array    Table description (field types etc.)

$flux['data']   array    Fields about to be written — modify and return
```

```php
// myplugin_pipelines.php
function myplugin_pre_edition($flux) {
    if ($flux['args']['objet'] === 'article' && isset($flux['data']['texte'])) {
        $flux['data']['texte'] = nettoyer_mon_texte($flux['data']['texte']);
    }
    return $flux;
}
```

---

### `post_edition`

Fires **after** the database write. Use it to trigger side-effects: invalidate caches, update related tables, send notifications. The pipeline return value is ignored by SPIP.

```
$flux['args']   same structure as pre_edition

$flux['data']   array    Fields that were actually written
```

```php
// medias_pipelines.php — excerpt
function medias_post_edition($flux) {
    if (
        isset($flux['args']['action'])
        && $flux['args']['action'] === 'ajouter_document'
    ) {
        include_spip('action/editer_document');
        document_instituer($flux['args']['id_objet']);
    }
    return $flux;
}
```

---

### `pre_edition_lien` / `post_edition_lien`

Same pattern as `pre_edition` / `post_edition` but for **link table** operations (the `spip_*_liens` tables). Fire when associations between objects are created or deleted.

```
$flux['args']
    table           string   Link table (e.g. 'spip_mots_liens')
    objet_source    string   Source object type
    id_objet_source int
    objet           string   Linked object type
    id_objet        int
    action          string   'associer' | 'dissocier'

$flux['data']   array   Link fields
```

---

## SQL table declaration

These pipelines run when SPIP builds its table registry. They receive an array of table descriptors and must return the augmented array. See `declarer-table.md` for the full descriptor format.

```php
// base/forum.php
function forum_declarer_tables_principales($tables_principales) {
    $tables_principales['spip_forum'] = [/* descriptor */];
    return $tables_principales;
}

function forum_declarer_tables_interfaces($tables_interfaces) {
    $tables_interfaces['table_des_tables']['forum'] = 'forum'; // plural alias
    return $tables_interfaces;
}
```

| Pipeline | Purpose |
|---|---|
| `declarer_tables_principales` | First-class editable objects (`spip_articles`, …) |
| `declarer_tables_auxiliaires` | Support tables without full CRUD (`spip_auteurs_liens`, …) |
| `declarer_tables_interfaces` | Aliases, joins, plural/singular mappings |
| `declarer_tables_objets_sql` | Full objet éditorial descriptor (URL, labels, status, …) |
| `declarer_tables_objets_surnoms` | Short-name aliases (`doc` → `document`) |
| `declarer_type_surnoms` | Type aliases |
| `declarer_url_objets` | URL routing patterns for objects |
| `declarer_filtres_squelettes` | Register custom filtres |

All receive and return an `array`. They fire on every request (cached after first run).

---

## Authorization

### `autoriser`

Does not receive a `$flux`. Its sole role is to **declare which file to load** that contains `autoriser_*_dist()` functions. SPIP auto-discovers the `autoriser_[objet]_[action]_dist()` convention.

```xml
<!-- paquet.xml -->
<pipeline nom="autoriser" inclure="forum_autoriser.php" />
```

```php
// forum_autoriser.php
if (!defined('_ECRIRE_INC_VERSION')) { return; }

function autoriser_forum_voir_dist($faire, $type, $id, $qui, $opts) {
    return true;
}

function autoriser_forum_modifier_dist($faire, $type, $id, $qui, $opts) {
    return $qui['statut'] === '0minirezo' || $qui['statut'] === '1comite';
}
```

Naming convention: `autoriser_[objet]_[action]_dist($faire, $type, $id_objet, $qui, $options)`

- `$faire` — action string (`'voir'`, `'modifier'`, `'publierdans'`, …)
- `$type` — object type
- `$id` — object ID
- `$qui` — current visitor's session array
- `$opts` — additional options array

Returns `bool`.

---

## CVT formulaires

These pipelines wrap the Charger/Vérifier/Traiter cycle. They fire for **every** active formulaire on every request — guard with the `form` argument.

### `formulaire_charger`

Fires during the load phase. Add default values or override loaded values.

```
$flux['args']
    form            string   Formulaire name (e.g. 'editer_article')
    args            array    Arguments passed to #FORMULAIRE_xxx
    je_suis_poste   bool     Whether this formulaire was just submitted

$flux['data']   array    Values returned by the formulaire's charger() function
```

```php
function myplugin_formulaire_charger($flux) {
    if ($flux['args']['form'] === 'editer_article') {
        // inject a default value if not already set
        if (!isset($flux['data']['mon_champ'])) {
            $flux['data']['mon_champ'] = 'default';
        }
    }
    return $flux;
}
```

---

### `formulaire_verifier`

Fires during the validation phase. Add error messages to `$flux['data']`; a non-empty data array blocks processing.

```
$flux['args']
    form    string
    args    array

$flux['data']   array    Error map: ['field_name' => 'error_message'] — add errors here
```

```php
function myplugin_formulaire_verifier($flux) {
    if ($flux['args']['form'] === 'editer_article') {
        $val = _request('mon_champ');
        if (empty($val)) {
            $flux['data']['mon_champ'] = _T('myplugin:erreur_champ_requis');
        }
    }
    return $flux;
}
```

---

### `formulaire_traiter`

Fires during the process phase (only when validation passed). Perform side-effects; add messages to `$flux['data']`.

```
$flux['args']
    form    string
    args    array

$flux['data']   array    Return info: may contain 'message_ok', 'message_erreur', 'redirect'
```

```php
function myplugin_formulaire_traiter($flux) {
    if ($flux['args']['form'] === 'editer_article') {
        $val = _request('mon_champ');
        sql_updateq('spip_articles', ['mon_champ' => $val],
            'id_article=' . intval($flux['args']['args'][0]));
    }
    return $flux;
}
```

---

### `formulaire_fond`

Lets a plugin override the squelette path used to render a formulaire.

```
$flux['args']
    form            string
    args            array
    je_suis_poste   bool

$flux['data']   string   Squelette path — override to substitute a different template
```

---

## Public display

### `affichage_final`

Receives the complete HTML page just before it is sent to the browser. Used to inject markup, rewrite URLs, surligne search terms.

```
Receives: string  (full HTML page)
Returns:  string  (modified HTML page)
```

```php
function myplugin_affichage_final(string $texte): string {
    if (!$GLOBALS['html']) {
        return $texte; // not an HTML page, skip
    }
    return str_replace('</body>', '<div id="myplugin-bar"></div></body>', $texte);
}
```

`$GLOBALS['html']` is `true` only when the response has `Content-Type: text/html`. Always check it before manipulating HTML structure.

---

### `insert_head`

Receives the content being accumulated for injection into `<head>` (public). Return additional HTML (script tags, meta tags, etc.).

```
Receives: string  (accumulated head content)
Returns:  string  (appended content)
```

```php
function myplugin_insert_head(string $texte): string {
    $texte .= '<link rel="stylesheet" href="' . find_in_path('css/myplugin.css') . '">';
    return $texte;
}
```

`insert_head_css` — same signature; by convention used for `<link>` tags only.

---

### `jquery_plugins`

Receives a list of JS file paths. Return the augmented list. SPIP resolves each path with `find_in_path()`.

```
Receives: array   list of JS relative paths
Returns:  array   augmented list
```

```php
function myplugin_jquery_plugins(array $liste): array {
    $liste[] = 'javascript/myplugin.js';
    return $liste;
}
```

---

### `recuperer_fond`

Fires after every squelette inclusion (public and private). Receives the rendered result and its context.

```
$flux['args']
    fond        string   Squelette path
    contexte    array    Variables passed to the squelette

$flux['data']
    texte       string   Rendered HTML
    entetes     array    HTTP headers set by the squelette
    ...
```

Rarely used directly in plugins; most private-space display work is done via `affiche_milieu`, `affiche_gauche`, etc.

---

### `styliser`

Lets a plugin remap a squelette path to a different file. Use it to override core squelettes without file overrides.

```
$flux['args']
    fond        string   Requested squelette name
    objet       string   Object type
    id_objet    int

$flux['data']   string   Resolved squelette path — override to redirect
```

---

## Private space display

### `header_prive`

Same as `insert_head` but for the private space `<head>`. Used for scripts and styles needed in the back-office.

```
Receives: string
Returns:  string
```

`header_prive_css` — same, by convention for `<link>` tags.

---

### `affiche_milieu`

Injects content into the main body area of a private page. Always fires in the private space, regardless of the current page. Filter by checking `$flux['args']` context.

```
$flux['args']   array    Current exec context (contains 'exec', 'id_article', etc.)
$flux['data']   string   HTML content of the main area — prepend or append to it
```

```php
function myplugin_affiche_milieu($flux) {
    $exec = $flux['args']['exec'] ?? '';
    if ($exec === 'article_edit' && isset($flux['args']['id_article'])) {
        $id_article = intval($flux['args']['id_article']);
        $flux['data'] .= recuperer_fond('prive/squelettes/inclure/myplugin-bloc',
            ['id_article' => $id_article], ['ajax' => true]);
    }
    return $flux;
}
```

`affiche_gauche`, `affiche_droite`, `affiche_pied` — same structure; target the left sidebar, right sidebar, and footer respectively.

---

### `boite_infos`

Renders the info box displayed on object view pages in the private space.

```
$flux['args']
    type    string   Object type ('article', 'forum', …)
    id      int      Object ID
    row     array    Database row for the object

$flux['data']   string   Accumulated HTML — append your box to it
```

```php
function myplugin_boite_infos($flux) {
    if ($flux['args']['type'] === 'article') {
        $flux['data'] .= recuperer_fond('prive/objets/infos/myplugin-article',
            ['id_article' => $flux['args']['id'], 'espace_prive' => 1]);
    }
    return $flux;
}
```

---

### `afficher_fiche_objet`

Fires when rendering the full object page in the private space.

```
$flux['args']
    contexte    array    Page context
    type        string   Object type
    id          int      Object ID

$flux['data']   string   Page HTML — insert content at the `<!--affiche_milieu-->` marker
```

---

### `affiche_formulaire_login`

Lets a plugin alter or replace the login form.

```
$flux['args']   array    Context
$flux['data']   string   Login form HTML
```

---

## Configuration and metadata

### `configurer_liste_metas`

Declares default values for `$GLOBALS['meta']` configuration entries. Fires at plugin activation time and on each request when the meta cache is built.

```
Receives: array   existing meta defaults  ['meta_key' => 'default_value']
Returns:  array   augmented defaults
```

```php
// forum_pipelines.php
function forum_configurer_liste_metas($metas) {
    $metas['forums_publics'] = 'posteriori';
    $metas['forum_prive']    = 'oui';
    return $metas;
}
```

Declared defaults are only applied if the meta has no stored value yet. Stored values in `spip_meta` always take precedence.

---

### `taches_generales_cron`

Registers scheduled tasks to run via the SPIP cron queue.

```
Receives: array   existing tasks  ['task_name' => period_seconds]
Returns:  array   augmented tasks
```

```php
// sites_pipelines.php
function sites_taches_generales_cron($taches) {
    if (($GLOBALS['meta']['activer_syndic'] ?? '') === 'oui') {
        $taches['syndic'] = 90; // run every 90 seconds
    }
    return $taches;
}
```

The task name maps to a function loaded via `charger_fonction('task_name', 'genie')`.

---

## Database maintenance

### `optimiser_base_disparus`

Fires during the "optimise database" admin operation. Use it to delete orphaned rows in your tables.

```
$flux['args']
    date    string   Cutoff date (ISO format) — rows older than this may be cleaned

$flux['data']   int   Running count of deleted rows — increment it
```

```php
// forum_pipelines.php
function forum_optimiser_base_disparus($flux) {
    $n = &$flux['data'];
    $mydate = $flux['args']['date'];

    $res = sql_select('id_forum', 'spip_forum',
        'statut=' . sql_quote('spam') . ' AND date_heure < ' . sql_quote($mydate));
    $n += optimiser_sansref('spip_forum', 'id_forum', $res);

    return $flux;
}
```

`optimiser_sansref()` is a SPIP helper that deletes rows matching a result set and returns the count.

---

## Search

### `prepare_recherche`

Fires when a user search query is being built. Lets plugins alter the query terms.

```
$flux['args']   array    Search context
$flux['data']   string   Search query string — normalize, stem, or expand it
```

### `rechercher_liste_des_champs`

Declares which columns of which tables should be searched.

```
Receives: array   ['table_sql' => ['field' => weight, ...], ...]
Returns:  array   augmented map
```

```php
function myplugin_rechercher_liste_des_champs($tables) {
    $tables['spip_monobjet']['titre']  = 8;
    $tables['spip_monobjet']['texte']  = 1;
    return $tables;
}
```

`rechercher_liste_des_jointures` — declares JOIN tables that widen search scope.

---

## Notifications

### `notifications`

Entry point for the notification system. Called with `charger_fonction('notifications', 'inc')`, not via `pipeline()` directly — but plugins hook into it via the `notifications` pipeline.

```
$flux['args']
    notification    string   Event name ('article_publier', 'objet_inserer', …)
    id              int      Object ID

$flux['data']   mixed   Notification payload
```

### `notifications_destinataires`

Builds the list of recipients for a notification.

```
$flux['args']
    notification    string
    id              int

$flux['data']   array   Recipient email addresses or auteur IDs
```

---

## Typography

These pipelines wrap SPIP's `propre()` / `typo()` text processing functions.

| Pipeline | Called by | Receives / returns |
|---|---|---|
| `pre_propre` | `propre()` before processing | `$flux` with `args.texte` and `data` (string) |
| `post_propre` | `propre()` after processing | `$flux` with `data` (string) |
| `pre_typo` | `typo()` before processing | `$flux` with `data` (string) |
| `post_typo` | `typo()` after processing | `$flux` with `data` (string) |
| `pre_liens` | link processing in `propre()` | `$flux` with `data` (string) |
| `nettoyer_raccourcis_typo` | shortcode cleanup | `$flux` with `data` (string) |

```php
// tw plugin — post_typo to apply TextWheel rules
function tw_post_typo($flux) {
    include_spip('inc/ressource-mini');
    $flux['data'] = post_typo($flux['data']);
    return $flux;
}
```

---

## Boucle compilation

### `pre_boucle`

Fires during squelette **compilation** (not at runtime). Receives the `Boucle` AST node and allows plugins to alter the SQL query that will be generated. Useful for automatically injecting WHERE conditions.

```
Receives: Boucle   AST node representing the <BOUCLE_xxx> being compiled
Returns:  Boucle   modified AST node
```

```php
// svp_pipelines.php — restrict PAQUETS boucles to remote depots by default
function svp_pre_boucle($boucle) {
    if ($boucle->type_requete === 'paquets' && !isset($boucle->modificateur['tout'])) {
        $boucle->where[] = ["'>'", "'{$boucle->id_table}.id_depot'", "'\"0\"'"];
    }
    return $boucle;
}
```

This pipeline requires knowledge of SPIP's compiler internals. See the `spip-expert` skill for `Boucle` object structure.

---

## Quick reference

All remaining pipelines from `ecrire/paquet.xml`. Most are extension points (`action=""`) for plugin-to-plugin communication.

| Pipeline | Type | Used for |
|---|---|---|
| `accueil_encours` | `$flux` | Dashboard "in progress" items |
| `accueil_informations` | `$flux` | Dashboard info blocks |
| `affichage_entetes_final` | array of headers | HTTP response headers (public) |
| `affichage_entetes_final_prive` | array of headers | HTTP response headers (private) |
| `affichage_final_prive` | string | Full private page HTML post-processing |
| `afficher_complement_objet` | `$flux` | Extra content on object view pages |
| `afficher_config_objet` | `$flux` | Config tab on object pages |
| `afficher_contenu_objet` | `$flux` | Object detail content area |
| `afficher_message_statut_objet` | `$flux` | Status message on object pages |
| `afficher_nombre_objets_associes_a` | `$flux` | Count of associated objects |
| `affiche_enfants` | `$flux` | Child objects listing (private) |
| `affiche_hierarchie` | `$flux` | Breadcrumb / hierarchy (private) |
| `alertes_auteur` | `$flux` | Author alert messages |
| `ajouter_menus` | array | Add private space menu entries |
| `ajouter_menus_args` | array | Extra menu URL arguments |
| `ajouter_onglets` | array | Add tabs to private pages |
| `arbo_creer_chaine_url` | `$flux` | Build arborescence-style URLs |
| `auth_administrer` | `$flux` | Admin authentication flow |
| `autoriser` | file include | Authorization function declaration |
| `base_admin_repair` | `$flux` | Database repair operations |
| `body_prive` | string | `<body>` tag attributes (private) |
| `calculer_rubriques` | `$flux` | Rubrique stats recalculation |
| `compter_contributions_auteur` | `$flux` | Author contribution counts |
| `declarer_hosts_distants` | array | Allowed remote hosts |
| `declarer_tables_objets_surnoms` | array | Object type short aliases |
| `declarer_type_surnoms` | array | Type name aliases |
| `declarer_url_objets` | array | URL patterns per object type |
| `delete_tables` | array | Tables to wipe on reset |
| `definir_session` | array | Session data fields |
| `detecter_fond_par_defaut` | `$flux` | Default squelette detection |
| `editer_contenu_objet` | `$flux` | Edit form content injection |
| `exec_init` | `$flux` | Exec page initialization |
| `exclure_id_conditionnel` | array | IDs to exclude from conditionals |
| `filtrer_liste_plugins` | array | Filter plugin list in admin |
| `formulaire_admin` | `$flux` | Admin form actions |
| `formulaire_verifier_etape` | `$flux` | Multi-step form step validation |
| `get_spip_doc` | `$flux` | SPIP documentation retrieval |
| `image_ecrire_tag_finir` | `$flux` | Image tag finalization |
| `image_ecrire_tag_preparer` | `$flux` | Image tag preparation |
| `image_extensions_logos` | array | Accepted logo extensions |
| `image_preparer_filtre` | `$flux` | Image filter pre-processing |
| `libeller_logo` | `$flux` | Logo label text |
| `lister_tables_noerase` | array | Tables protected from wipe |
| `lister_tables_noexport` | array | Tables excluded from export |
| `lister_tables_noimport` | array | Tables excluded from import |
| `notifications_envoyer_mails` | `$flux` | Email sending step |
| `objet_compte_enfants` | `$flux` | Child object count |
| `objet_lister_enfants` | `$flux` | List child objects |
| `objet_lister_parents` | `$flux` | List parent objects |
| `page_indisponible` | `$flux` | 503 / unavailable page |
| `post_boucle` | Boucle | Post-compilation boucle modification |
| `post_image_filtrer` | `$flux` | After image filter applied |
| `pre_echappe_html_propre` | `$flux` | Before HTML escaping in propre() |
| `pre_indexation` | `$flux` | Before search indexation |
| `pre_insertion` | `$flux` | (see Edition section above) |
| `preparer_fichier_session` | `$flux` | Session file preparation |
| `preparer_visiteur_session` | `$flux` | Visitor session preparation |
| `propres_creer_chaine_url` | `$flux` | Build propres-style URLs |
| `quete_logo_objet` | `$flux` | Find an object's logo |
| `rechercher_liste_des_jointures` | array | Search JOIN tables |
| `requete_dico` | `$flux` | Dictionary lookup query |
| `rubrique_encours` | `$flux` | "Current rubrique" context |
| `styliser` | `$flux` | Squelette path resolution |
| `traduire` | `$flux` | Translation override |
| `trig_auth_trace` | — | Auth trace trigger |
| `trig_calculer_langues_rubriques` | — | Rubrique language recalculation |
| `trig_calculer_prochain_postdate` | — | Next post-date recalculation |
| `trig_propager_les_secteurs` | — | Sector propagation |
| `trig_purger` | — | Cache purge trigger |
| `trig_supprimer_objets_lies` | `$flux` | Delete linked objects on removal |
| `trig_supprimer_objets_tables` | array | Cleanup when tables are dropped |
| `trig_trace_query` | — | SQL query tracing |
