# Plugin settings

Metas and auxiliary tables are handled by `spip-league/config`: service `spip.config` (store `meta`) and
`spip.config.manager` (all stores).

---

## Read and write

| Context | Read | Write |
|---|---|---|
| class (injected `#[Autowire(service: 'spip.config')] ConfigRepositoryInterface $config`) | `$config->get('acme/seuil', 10)` | `$config->set('acme/seuil', 30)` |
| procedural code | `Spip::config('acme/seuil', 10)` | `Spip::configs()->set('acme/seuil', 30)` |
| other store | `Spip::configs('acme')->get('cle')` / `Spip::config('cle', null, 'acme')` | `Spip::configs('acme')->set(…)` |

`ConfigRepositoryInterface` also has `has()`, `delete()`, `refresh()`, `reset()`.

## Never `$GLOBALS['meta']`

Values that are not strings are stored as **JSON**; `$GLOBALS['meta']` is the raw SQL string, kept for BC only.
Verified: after `Spip::configs()->set('acme_seuil', 30)`, `Spip::config('acme_seuil')` is `int 30`, while
`$GLOBALS['meta']['acme_seuil']` and the `spip_meta` row are `'[30]'`. Writing into `$GLOBALS['meta']` persists
nothing.

A value written long ago by `ecrire_meta()` or never rewritten comes back as a string (`'30'`): cast scalars before a
strict comparison — `(int) $config->get('acme_seuil', 10)`. No manual `json_decode()` / `unserialize()`.

## Own store

```php
// config/services.php
use SpipLeague\Component\Config\Store\SqlConfigStore;

$services->set('acme.config.store', SqlConfigStore::class)
	->arg('$tableSuffix', 'acme')
	->tag('spip.config.store', ['name' => 'acme']);
// table spip_acme; then Spip::configs('acme') — registered in the meta `tables_config`
```

`installer_table_meta()` / `supprimer_table_meta()` in `acme_administrations.php` delegate to
`SqlConfigStore::install()` / `::uninstall()`.

## Configuration form

The CVT `formulaires/configurer_acme.html` + `prive/squelettes/contenu/configurer_acme.html` model is unchanged in
5.0: see `spip-formulaires`. In its PHP, read inputs with `Spip::input()`.
