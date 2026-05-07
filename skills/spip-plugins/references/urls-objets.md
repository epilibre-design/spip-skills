# Public object URLs and custom `#URL_*` tags

How SPIP resolves and generates URLs for objet types, and what to declare when you add a custom object.

---

## What `#URL_<TYPE>` really does

`#URL_<TYPE>` is mostly generic in SPIP.

- `#URL_ARTICLE`, `#URL_RUBRIQUE`, etc. have dedicated overrides for historical/special cases.
- For all other types, the compiler falls back to the generic `#URL_` implementation.
- The generated URL is built through `generer_objet_url()` (legacy alias: `generer_url_entite()`, deprecated since SPIP 4.1).

That means a custom tag like `#URL_MON_OBJET` does **not** require writing a custom balise in most cases.

---

## Minimal declarations for a custom object URL

For an objet declared in `declarer_tables_objets_sql`:

1. Declare a valid object descriptor (`type`, `field`, `key`, ...).
2. Provide a public page type via `page` (usually same value as `type`) if you want routable public pages.
3. Provide a title accessor (`titre`) and/or `table_titre` mapping for readable URL modes (`propres`, `arbo`, ...).
4. Provide a public template matching the page type (for example `mon_objet.html` or an equivalent via `styliser`).

Example descriptor:

```php
$tables['spip_mon_objet'] = [
    'type'       => 'mon_objet',
    'table_objet'=> 'mon_objets',
    'principale' => 'oui',
    'page'       => 'mon_objet',
    'titre'      => "titre, '' AS lang",
    // field, key, ...
];
```

And in `declarer_tables_interfaces` (optional but recommended for meaningful URLs):

```php
$interfaces['table_titre']['mon_objets'] = "titre, '' AS lang";
```

---

## When `declarer_url_objets` is needed

`declarer_url_objets` provides the list of object shortcuts that SPIP can parse in standard object URLs
(`?objet12`, `objet12`, `objet12.html`, depending on URL mode).

- Many declared objets are already included automatically when their descriptor has a public page.
- Use `declarer_url_objets` to add extra entries (aliases, historical names, non-standard object shortcuts).

Typical handler:

```php
function monplugin_declarer_url_objets($objets) {
    $objets[] = 'mon_objet';
    // optional alias for backward compatibility
    // $objets[] = 'monobjet';
    return array_values(array_unique($objets));
}
```

Declare it in `paquet.xml`:

```xml
<pipeline nom="declarer_url_objets" inclure="base/urls.php" />
```

---

## Using a custom `#URL_*` in templates

Inside a boucle on your object:

```html
<a href="#URL_MON_OBJET">#TITRE</a>
```

Outside a boucle (explicit id):

```html
<a href="#URL_MON_OBJET{#ID_MON_OBJET}">Voir</a>
```

In PHP:

```php
$url = generer_objet_url((int) $id_mon_objet, 'mon_objet');
```

---

## Troubleshooting checklist

- `#URL_MON_OBJET` renders empty or wrong URL:
  - verify `type` and `id_table_objet(type)` convention (`id_mon_objet`)
  - verify the context has `#ID_MON_OBJET` (or pass id explicitly)
- URL resolves but 404s:
  - verify a matching public template exists (`mon_objet.html`)
  - verify `page` in descriptor is not empty
- Non-readable URL when using propres/arbo:
  - verify `titre`/`table_titre` declaration
- Historical URL patterns no longer resolve:
  - add aliases through `declarer_url_objets`

---

## Related references

- `references/declarer-objet.md`
- `references/declarer-table.md`
- `references/pipelines.md` (`declarer_url_objets`)
- `references/arborescence.md` (`base/urls.php`)
