# paquet.xml — Grammar and reference

Declarative manifest at the root of every plugin. Describes the plugin to SPIP's SVP manager: metadata, dependencies, pipelines, menus, scheduled tasks.

---

## Root element `<paquet>`

```xml
<paquet
    prefix="monplugin"
    categorie="outil"
    version="1.0.0"
    etat="stable"
    compatibilite="[4.2.0;4.*]"
    schema="1.0.0"
    logo="prive/themes/spip/images/monplugin-32.png"
    documentation="https://..."
>
```

### Attributes of `<paquet>`

| Attribute | Required | Description | Observed values |
|---|---|---|---|
| `prefix` | yes | Short identifier; prefixes all the plugin's PHP files and functions | `forum`, `porte_plume`, `bigup` |
| `categorie` | yes | SVP category | see below |
| `version` | yes | Semantic version `x.y.z` | `3.1.14`, `4.4.9` |
| `etat` | yes | Development state | `stable`, `test`, `dev` |
| `compatibilite` | yes | Compatible SPIP version range | see notation below |
| `schema` | no | Database schema version (triggers `_upgrade`) | `1.2.2`, `2022022303` |
| `logo` | no | Relative path to the plugin folder | `prive/themes/spip/images/forum-32.png` |
| `documentation` | no | External documentation URL | URL |
| `demonstration` | no | Demo URL | URL |
| `developpement` | no | Source repository URL | git URL |

### Categories (`categorie`)

```
communication  divers       edition      maintenance
multimedia     navigation   outil        performance
squelette      statistique
```

### Compatibility notation

| Example | Meaning |
|---|---|
| `[4.2.0;4.*]` | From 4.2.0 inclusive through 4.x inclusive |
| `[4.4.0-dev;4.*]` | From the 4.4.0-dev pre-release onward |
| `[7.4.0;[` | 7.4.0+ with no upper bound (used for PHP in `<necessite>`) |
| `];[` | No constraint at all (the SPIP core itself) |

Brackets follow mathematical interval notation: `[` = inclusive, `]` = exclusive.

---

## Child elements (must respect this order)

### `<nom>` — Required

Human-readable plugin name displayed in SVP.

```xml
<nom>Forum</nom>
<!-- Gestion des forums privés et publics dans SPIP -->
```

The XML comment immediately after serves as a short description in SVP (convention).

---

### `<auteur>` — Required, repeatable

```xml
<auteur>Collectif SPIP</auteur>
<auteur lien="http://magraine.net/">Matthieu Marcillaud</auteur>
```

| Attribute | Description |
|---|---|
| `lien` | Author profile or website URL (optional) |

---

### `<licence>` — Required

```xml
<licence lien="http://www.gnu.org/licenses/gpl-3.0.html">GPL</licence>
<licence>GNU/GPL</licence>
```

---

### `<credit>` — Optional, repeatable

Credits for third-party libraries or contributors.

```xml
<credit lien="https://github.com/flowjs/flow.js">Flow.js</credit>
<credit>Roman Ivanov</credit>
```

---

### `<traduire>` — Optional, repeatable

Declares a translation module managed on trad.spip.net (Salvatore).

```xml
<traduire module="forum" reference="fr" gestionnaire="salvatore" />
<traduire module="ecrire" reference="fr" gestionnaire="salvatore" />
```

| Attribute | Description |
|---|---|
| `module` | Module name (maps to `lang/[module].xml` files) |
| `reference` | Reference language |
| `gestionnaire` | Translation tool (`salvatore`) |

A plugin may declare several modules (e.g. the SPIP core declares `spip`, `ecrire`, `public`).

---

### `<pipeline>` — Optional, repeatable

Hooks the plugin into a SPIP pipeline.

```xml
<!-- Pipeline declared with no implementation (extension point for other plugins) -->
<pipeline nom="forum_objets_depuis_env" action="" />

<!-- Pipeline hooked to a function inside a file -->
<pipeline nom="autoriser" inclure="forum_autoriser.php" />

<!-- Pipeline with an explicitly named function -->
<pipeline nom="affichage_final" action="f_surligne" inclure="inc/pipelines.php" />

<!-- Same pipeline declared twice for two different actions -->
<pipeline nom="insert_head" inclure="bigup_pipelines.php" />
<pipeline nom="insert_head" inclure="compresseur_pipeline.php" />
```

| Attribute | Description |
|---|---|
| `nom` | Pipeline name (see `pipelines.md` for the full catalogue) |
| `action` | Function to call (optional; when absent, SPIP infers `[prefix]_[pipeline_name]`) |
| `inclure` | File to load to access the function (path relative to the plugin root) |

**Auto-inference rule:** when `action` is absent and `inclure` is set, SPIP looks for a function named `[prefix]_[nom_pipeline]` inside the included file.

The same pipeline may appear multiple times with different `action` values — SPIP calls each in sequence.

---

### `<necessite>` — Optional, repeatable

**Hard dependency**: the plugin will not work without it.

```xml
<necessite nom="archiviste" compatibilite="[2.2.0;]" />
<necessite nom="php" compatibilite="[7.4.0;[" />
```

The special name `php` constrains the required PHP version.

---

### `<utilise>` — Optional, repeatable

**Soft dependency**: activated if the plugin is present, but not required.

```xml
<utilise nom="mediabox" compatibilite="[1.2.0;]" />
<utilise nom="mots" compatibilite="[2.9.0;]" />
```

---

### `<procure>` — Optional, repeatable

Declares a virtual capability provided by this plugin. Lets other plugins depend on an abstraction rather than a specific plugin name.

```xml
<!-- dans ecrire/paquet.xml (core SPIP) -->
<procure nom="iterateurs" version="1.0.6" />
<procure nom="queue" version="0.6.8" />
<procure nom="jquery" version="3.6.4" />

<!-- dans compresseur/paquet.xml -->
<procure nom="csstidy" version="1.15.1" />
```

---

### `<menu>` — Optional, repeatable

Adds an entry to the private space navigation.

```xml
<menu
    nom="forum_reactions"
    titre="forum:icone_suivi_forums"
    parent="menu_activite"
    icone="images/forum-16.png"
    action="controler_forum"
/>

<menu
    nom="rubrique_creer"
    titre="public:rubrique"
    parent="outils_rapides"
    icone="images/rubrique-new-16.svg"
    action="rubrique_edit"
    parametres="new=oui&amp;id_parent=@id_rubrique@"
    position="-1"
/>
```

| Attribute | Required | Description |
|---|---|---|
| `nom` | yes | Unique menu identifier |
| `titre` | yes | i18n key in `module:key` or `key` form |
| `parent` | no | Parent menu slot (`menu_edition`, `menu_activite`, `menu_administration`, `menu_configuration`, `menu_publication`, `menu_squelette`, `outils_rapides`, `outils_collaboratifs`, …) |
| `icone` | no | Path to the icon relative to the private theme folder (usualy 'prive/themes/spip') |
| `action` | no | Target exec page |
| `parametres` | no | Additional GET parameters (use `&amp;`); `@id_rubrique@` is replaced dynamically |
| `position` | no | Relative position (`-1` = end) |

---

### `<onglet>` — Optional, repeatable

Adds a tab inside a private space page.

```xml
<onglet
    nom="stats_visites"
    titre="statistiques:icone_statistiques_visites"
    parent="statistiques"
    icone="images/statistique-24.png"
    action="stats_visites"
/>
```

Same attributes as `<menu>`. `parent` points to the `nom` of a `<menu>` or another `<onglet>`.

---

### `<genie>` — Optional, repeatable

Registers a scheduled task (SPIP cron).

```xml
<genie nom="nettoyer_repertoire_upload" periode="86400" />
<genie nom="optimiser_revisions" periode="86400" />
```

| Attribute | Description |
|---|---|
| `nom` | Identifier; SPIP loads `genie/[prefix]_[nom].php` and calls `[prefix]_[nom]()` |
| `periode` | Interval in seconds (`86400` = 1 day) |

---

### `<script>` — Optional, repeatable

Declares a JS file to include.

```xml
<script source="javascript/medias_edit.js" type="prive" />
```

| Attribute | Description |
|---|---|
| `source` | Relative path to the JS file |
| `type` | `prive` = private space only |

---

## Minimal complete example

```xml
<!-- plugins/monplugin/paquet.xml -->
<paquet
    prefix="monplugin"
    categorie="edition"
    version="1.0.0"
    etat="stable"
    compatibilite="[4.2.0;4.*]"
    schema="1.0.0"
    logo="prive/themes/spip/images/monplugin-32.png"
>
    <nom>Mon Plugin</nom>
    <!-- Fait quelque chose d'utile -->

    <auteur lien="https://example.com">Mon Nom</auteur>

    <licence lien="http://www.gnu.org/licenses/gpl-3.0.html">GPL</licence>

    <traduire module="monplugin" reference="fr" gestionnaire="salvatore" />

    <pipeline nom="autoriser" inclure="monplugin_autoriser.php" />
    <pipeline nom="declarer_tables_objets_sql" inclure="base/monplugin.php" />
    <pipeline nom="post_edition" inclure="monplugin_pipelines.php" />

    <necessite nom="archiviste" compatibilite="[2.2.0;]" />

    <menu nom="monplugin" titre="monplugin:titre_menu" parent="menu_edition"
          icone="images/monplugin-16.png" action="monplugin" />
</paquet>
```

---

## Notes

- The `schema` attribute triggers the `[prefix]_upgrade` and `[prefix]_vider_tables` functions defined in `[prefix]_administrations.php`. Without `schema`, those functions are never called automatically.
- The compatibility notation `];[` in the core (`ecrire/paquet.xml`) means "no SPIP constraint" — that file IS the core.
- A `<pipeline>` with `action=""` declares an extension point (other plugins can hook into it) without providing a default implementation.
