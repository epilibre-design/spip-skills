# Cache — duration, invalidation, and constants

SPIP's public-page cache stores compiled squelette output in `tmp/cache/` as files. The cache
layer is controlled by balises inside squelettes, PHP-level constants, and an invalidation
mechanism tied to content modifications.

Sources: `ecrire/public/cacher.php`, `ecrire/inc/invalideur.php`, `ecrire/public/balises.php`

---

## #CACHE{} — setting duration in a squelette

```
#CACHE{duree}
#CACHE{duree, type}
```

Place `#CACHE{}` anywhere in a squelette to set how long (in seconds) the compiled output
is kept. Without this balise, the default is `_DUREE_CACHE_DEFAUT` (24 hours).

| Usage | Effect |
|---|---|
| `#CACHE{3600}` | Cache for 1 hour; invalidated by content changes |
| `#CACHE{0}` | No cache — page recomputed on every request |
| `#CACHE{86400, statique}` | Cache for 24 hours; **not** invalidated by `derniere_modif` |
| `#CACHE{3600, cache-client}` | Cache for 1 hour + HTTP `Cache-Control: max-age`; implies `statique` |

**Implementation:** `#CACHE{}` compiles to `header("X-Spip-Cache: <duree>")` and optionally
`header("X-Spip-Statique: oui")`. The cache layer reads these headers when deciding whether
to serve or regenerate a cached file.

```html
[(#REM) page with short, content-independent cache ]
#CACHE{300, statique}
```

Source: `ecrire/public/balises.php:1813 balise_CACHE_dist()`

---

## Cache validation logic

On each request, `ecrire/public/cacher.php` decides whether to serve the cached file or
regenerate. It returns:

| Return | Meaning |
|---|---|
| `0` | Serve the cache |
| `1` | Regenerate and write a new cache file |
| `-1` | Regenerate but do **not** write to cache |

**Decision steps (in order):**

1. `_VAR_NOCACHE` defined and truthy → return `-1` (forced preview/debug)
2. `meta['cache_inhib']` is in the future → return `-1` (admin-triggered inhibition)
3. `_NO_CACHE` defined:
   - `== 0` and no cached text: return `1`
   - otherwise: return `_NO_CACHE`
4. No cached file → return `1` (or `-1` for bots)
5. Signature mismatch → return `1` (or `-1` for bots)
6. `X-Spip-Statique != oui` and `derniere_modif` is newer than cache date → return `1`
7. `X-Spip-Cache == 0` → return `-1` (`#CACHE{0}`)
8. Cache age > `X-Spip-Cache` → return `1` (or `-1` for bots)

---

## Constants reference

| Constant | Default | Set by | Effect |
|---|---|---|---|
| `_DUREE_CACHE_DEFAUT` | `86400` (24 h) | `cacher.php:431` | Default cache duration when no `#CACHE{}` balise |
| `_NO_CACHE` | not set | plugin / inc | Overrides the validation step; `0` = always regenerate |
| `_VAR_NOCACHE` | not set | URL param `var_nocache`, `inc/utils.php` | Forces regeneration without writing cache |
| `spip_interdire_cache` | not set | `req/mysql.php:1161` on fatal SQL error | Prevents writing cache for the current request |
| `_CACHE_PROFONDEUR_STOCKAGE` | `4` | site config | Cache directory depth (`16^n` max files) |

### Inhibiting the cache for a request (PHP)

```php
// Force regeneration + write for the current request (survives across includes)
if (!defined('_NO_CACHE')) {
    define('_NO_CACHE', 1);
}

// Prevent writing cache entirely (e.g. after a fatal error)
// — set by MySQL driver on connection failure, not intended for plugin use
define('spip_interdire_cache', true);
```

---

## Invalidation — suivre_invalideur

When content changes (a row is inserted, modified, or deleted), SPIP updates the `derniere_modif`
meta. On the next request, any non-statique cached page whose file date is older than
`derniere_modif` is regenerated.

**The mechanism:** `objet_modifier()` and `objet_instituer()` call `suivre_invalideur()` (via
`objet_modifier_champs()`), which writes the current timestamp to `meta['derniere_modif']`.

```php
// ecrire/inc/invalideur.php:92
function inc_suivre_invalideur_dist($cond, $modif = true) {
    // writes meta['derniere_modif_<objet>'] and meta['derniere_modif']
}
```

### Controlling which objects trigger invalidation

By default, any modification invalidates all non-statique caches. To restrict invalidation:

```php
// In a pipeline or early in the request:
// Only article and rubrique modifications should invalidate caches
$GLOBALS['derniere_modif_invalide'] = ['article', 'rubrique'];

// Disable content-based invalidation entirely (e.g. for read-only pages)
$GLOBALS['derniere_modif_invalide'] = false;
```

Source: `ecrire/inc/invalideur.php:107`

### Invalidation from PHP (raw SQL writes)

Raw SQL writes (`sql_updateq`, `sql_insertq`) do **not** automatically call `suivre_invalideur`.
When writing directly to a table that has cached dependents, trigger invalidation manually:

```php
sql_updateq('spip_mon_objets', ['statut' => 'publie'], 'id_mon_objet=' . intval($id));

// Manually trigger cache invalidation
include_spip('inc/invalideur');
suivre_invalideur("id='monobjet/" . intval($id) . "'");
```

---

## Session-keyed caches

Pages that include per-visitor data (e.g. a logged-in user's name) need session-keyed caches.
The invalideur infrastructure handles this: if a page's `$page['invalideurs']['session']` key
is set, SPIP creates a separate cache file per session.

This is handled automatically when squelettes use session-dependent balises (e.g. `#SESSION`,
`#AUTORISER` rendered at cache time). No explicit plugin action required.

---

## Invariants

- `#CACHE{0}` compiles to a `header()` call — it prevents the cache from being **served**,
  but the page still executes fully
- `statique` mode caches are never invalidated by `derniere_modif`; they expire only on
  duration or manual purge (`?action=purger`)
- Bot requests never write to cache (return `-1` for most regeneration paths)
- The cache signature (`crc32` of content + site secret) protects against injection of a
  tampered cache file

---

## See also

- `references/pipelines.md` → `cache_start`, `cache_end` pipelines (fired on cache read/write)
- `references/declarer-objet.md` → `objet_modifier()` triggers invalidation automatically
- `references/sql-api.md` → raw SQL writes require manual `suivre_invalideur()`
