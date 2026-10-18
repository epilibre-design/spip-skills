# Plugin Configuration Reference

Topics: configuration page (`configurer_`), `#FORMULAIRE_CONFIGURER_*`, `lire_config`, `ecrire_config`, `effacer_config`, `#CONFIG`, `spip_meta` table, alternative meta tables.

---

## How SPIP configuration works

Plugin settings are stored in the `spip_meta` table as key/value rows. The entire table is loaded into memory on every request, so reads are free — writes go through `ecrire_config()`.

A plugin declares its configuration page via a private-space template; SPIP automatically adds a "Configure" link next to the plugin in the plugin administration page.

---

## Creating a configuration page

### 1. The form file — `formulaires/configurer_myplugin.html`

Create only the HTML file. **No PHP** (no `charger`, `verifier`, `traiter` functions). SPIP's built-in `inc/cvt_configurer` handles saving automatically.

```html
<!-- formulaires/configurer_myplugin.html -->
<div class="formulaire_spip formulaire_configurer formulaire_#FORM">
  <h3 class="titrem"><:myplugin:titre_configurer:></h3>
  [<div class="reponse_formulaire reponse_formulaire_ok" role="status">(#ENV*{message_ok})</div>]
  [<div class="reponse_formulaire reponse_formulaire_erreur" role="alert">(#ENV*{message_erreur})</div>]
  [(#ENV{editable})
  <form method="post" action="#ENV{action}"><div>
    #ACTION_FORMULAIRE
    <p class="explication"><:myplugin:configurer_intro:></p>
    <div class="editer-groupe">
      <div class="editer editer_in_the_spotlight[ (#ENV*{erreurs/in_the_spotlight}|oui)erreur]">
        <label for="in_the_spotlight"><:myplugin:champ_in_the_spotlight:></label>
        [<span class="erreur_message">(#ENV*{erreurs/in_the_spotlight})</span>]
        <input type="text" name="in_the_spotlight" id="in_the_spotlight" class="text"
               value="[(#ENV{in_the_spotlight})]" />
      </div>
    </div>
    <p class="boutons"><input type="submit" class="btn submit" value="<:bouton_enregistrer:>" /></p>
  </div></form>
  ]
</div>
```

The `<form>` and `#ACTION_FORMULAIRE` are required: without them `#FORMULAIRE_CONFIGURER_MYPLUGIN` renders no form
and nothing can be saved. `#ACTION_FORMULAIRE` adds the hidden fields (`formulaire_action`, signed args). Field names
map directly to the config key; `charger()` of `inc/cvt_configurer` puts the stored values in `#ENV`. Title and
messages go outside `[(#ENV{editable})…]`, as in `plugins-dist` (`mots`, `sites`, `medias`). To group fields, use
`<fieldset><legend>` (`svp`, `sites`, `urls_etendues`).

### 2. The private page — `prive/squelettes/contenu/configurer_myplugin.html`

```html
[(#AUTORISER{configurer,myplugin}|sinon_interdire_acces)]

<h1><:myplugin:titre_page_configurer:></h1>

<div class="ajax">
  #FORMULAIRE_CONFIGURER_MYPLUGIN
</div>
```

`#AUTORISER{configurer,myplugin}` + `|sinon_interdire_acces` restricts access to administrators by default. Override with a custom `autoriser_myplugin_configurer_dist()` if needed.

### 3. Optional: menu entry in paquet.xml

To add a direct link in the private-space nav:

```xml
<menu nom="myplugin_config"
      titre="myplugin:itemdelangue_config"
      parent="bando_configuration"
      icone="images/myplugin-16.png" />
```

---

## Initialising and cleaning up config values

### On install / upgrade — in `myplugin_administrations.php`

```php
function myplugin_upgrade($nom_meta_base_version, $version_cible) {
    $maj = [];

    // On first install:
    $maj['create'] = [
        ['ecrire_config', 'myplugin/in_the_spotlight', '3'],
    ];

    // On a specific version upgrade:
    $maj['0.1'] = [
        ['ecrire_config', 'myplugin/in_the_spotlight', '3'],
    ];

    maj_plugin($nom_meta_base_version, $version_cible, $maj);
}
```

### On uninstall

```php
function myplugin_vider_tables($version) {
    effacer_config('myplugin/in_the_spotlight');
}
```

---

## Reading and writing config values in PHP

```php
// Read
$val = lire_config('myplugin/in_the_spotlight');         // returns null if absent
$val = lire_config('myplugin/in_the_spotlight', 3);      // with a default

// Write
ecrire_config('myplugin/in_the_spotlight', 3);

// Delete
effacer_config('myplugin/in_the_spotlight');
```

---

## Reading config values in templates

Use `#CONFIG{prefix/key}` — see [variables.md in spip-squelettes](../../spip-squelettes/references/variables.md#config--read-plugin-configuration) for full syntax.

```html
[(#CONFIG{myplugin/in_the_spotlight})]
[(#CONFIG{myplugin/in_the_spotlight, 3})]   <!-- with default -->
```

---

## Customising the save treatment

When additional processing is needed after saving, add a `traiter()` function and delegate to the built-in handler:

```php
function formulaires_configurer_myplugin_traiter_dist() {
    include_spip('inc/cvt_configurer');
    $retours = [];

    // Built-in save
    $trace = cvtconf_formulaires_configurer_enregistre('configurer_myplugin', []);

    // Custom logic here
    // e.g. flush a cache, notify an external service, etc.

    $retours['message_ok'] = _T('config_info_enregistree') . $trace;
    $retours['editable'] = true;

    return $retours;
}
```

---

## Using an alternative storage table

By default config is stored in `spip_meta`. To use a dedicated table (e.g. `meta_myplugin`), add a hidden input to the form:

```html
<input type="hidden" name="_meta_table" value="meta_myplugin" />
```

Then in PHP:

```php
ecrire_config('/meta_myplugin/description', 'blah');
lire_config('/meta_myplugin/description');
```

The leading `/` signals a non-default table.

Other advanced hidden fields:

| Field | Effect |
|-------|--------|
| `_meta_table` | Table name to store values in |
| `_meta_casier` | Key name inside a serialised array (defaults to `configurer_xx`) |
| `_meta_prefixe` | Prefix applied to each meta key instead of using a casier |
| `_meta_stockage` | External storage driver (none provided by core) |

---

## Gotcha: serialised array values

`lire_config` / `#CONFIG` return the raw `spip_meta` value. If the stored value is a serialised PHP array (which the default `cvt_configurer` handler produces when multiple fields share a prefix), `#CONFIG{myplugin}` returns the serialised string, not an array. Access individual keys with `#CONFIG{myplugin/key}`.
