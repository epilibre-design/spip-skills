# Config and metas in SPIP 5.0

Metas and auxiliary config tables are handled by `spip-league/config`: services `spip.config` (store `meta`) and
`spip.config.manager` (all stores), PSR-16 cache under `tmp/cache/config/`, values (de)serialized by `spip.serializer`.

---

## API

| Need | 5.0 | Bridge (still supported) |
|---|---|---|
| read | `Spip::config('cle')`, `Spip::config('casier/sous_cle', $defaut)` | `lire_config('casier/sous_cle', $defaut)` |
| write | `Spip::configs()->set('cle', $valeur)` | `ecrire_config('cle', $valeur)` |
| other store | `Spip::configs('meta_perso')->get('cle')` / `Spip::config('cle', null, 'meta_perso')` | `lire_config('/meta_perso/cle')` — **deprecated** |
| refresh from DB | `Spip::configs($store)->refresh()` | `touch_meta()` — removed |
| purge cache + mirror | `Spip::configs()->reset()` | `spip_unlink(_FILE_META)` — removed |
| object code | inject `ConfigRepositoryInterface` with `#[Autowire(service: 'spip.config')]` / `ConfigManagerInterface` with `#[Autowire(service: 'spip.config.manager')]` (no alias: a plain type breaks the container) | |

`lire_config()`, `ecrire_config()`, `effacer_config()`, `ecrire_meta()` keep their signatures and are **not**
deprecated; only the `/<store>/` prefix and `lire_config(,, false)` are.

## Storage rule — the trap

Every value is persisted as a **string**:

- already a `string` → stored as is (`oui`, `utf-8`);
- anything else (`int`, `bool`, `array`) written through `ecrire_config()` / `Spip::configs()->set()` → **JSON**:
  `150` → `"[150]"`, `true` → `"[true]"`, array → `"[…]"`.

`$GLOBALS['meta']` (and `$GLOBALS[$store]`) is a **mirror of the raw SQL string**, kept for BC, never unserialized,
never the source of truth. Consequences after an upgrade:

```php
$GLOBALS['meta']['taille_preview'] == '150';       // false once rewritten: value is "[150]"
unserialize($GLOBALS['meta']['acme_reglages']);    // fails once rewritten: JSON, not serialize()
$GLOBALS['meta']['acme_x'] = 'oui';                // no persistence, no cache update
```

Fix: read through the API, which decodes JSON **and** legacy `serialize()` blobs:

```php
if ((int) Spip::config('taille_preview') === 150) { … }   // cast: see type note below
$reglages = Spip::config('acme_reglages', []);
Spip::configs()->set('acme_x', 'oui');
```

Returned type depends on how the key was last written: `"[150]"` (JSON) → `int 150`; a legacy `"150"` (never rewritten,
or written by `ecrire_meta()`) → `string "150"`; a legacy `serialize()` blob → decoded value. Cast scalars before strict
comparisons.

There is no SQL migration of old values: a key switches to JSON on its **next write**, so the same code may work on one
site and fail on another. `ecrire_meta()` casts to string instead (`150` → `"150"`); mixing both writers on one key
gives inconsistent mirrors.

## Checklist

- Replace every read of `$GLOBALS['meta'][…]` by `Spip::config()` — first numeric/boolean comparisons and `unserialize()`.
- Replace every write `$GLOBALS['meta'][…] = …` by `Spip::configs()->set()`.
- No manual `json_decode()` / `unserialize()` on the mirror.
- Same rules for auxiliary meta tables (`$GLOBALS['spip_<table>']`).
- Remove `methode::cle` prefixes (now read as literal keys).

## Custom store

```php
$services->set('acme.config.store', SqlConfigStore::class)
    ->arg('$tableSuffix', 'acme')
    ->tag('spip.config.store', ['name' => 'acme']);
// then: Spip::configs('acme') — registered automatically in the meta `tables_config`
```

`installer_table_meta()` / `supprimer_table_meta()` delegate to `SqlConfigStore::install()` / `::uninstall()`.
