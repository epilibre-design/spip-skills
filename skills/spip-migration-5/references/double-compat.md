# One plugin for SPIP 4.4 and 5.0

The upstream documentation does not cover this case; rules below come from comparing the 4.4.23 and 5.0.0-beta sources.

---

## Strategy

1. Keep the **legacy model** for shared code: pipeline functions + `<pipeline>` in `paquet.xml`, `genie/` tasks,
   `<menu>` in `paquet.xml`. All still run in 5.0.
2. Replace removed symbols by APIs that exist in **both** versions (table below).
3. Do not call the `Spip` façade, `Spip\SpipVersion` or `Spip::directories()` unguarded: they do not exist in 4.4.
4. Declare `compatibilite="[4.4.0;5.*]"` in `paquet.xml`. PHP: the 5.0 site needs 8.4; the code must still run on the
   PHP versions the 4.4 sites use (7.4 → 8.5) — avoid PHP 8.4-only syntax in shared files.
5. Move to listeners / `#[AsCronTask]` only when 4.4 support is dropped (one branch per major version if the gap grows).
6. Test on a 4.4 and a 5.0 instance with deprecations logged.

## Replacements valid on both

| Removed/deprecated in 5.0 | Use (exists in 4.4 and 5.0) |
|---|---|
| `defined('_ESPACE_PRIVE')` | `test_espace_prive()` |
| `_IS_CLI` | `PHP_SAPI === 'cli'` |
| `generer_url_entite()` & family `generer_*_entite()` | `generer_objet_url()` & family `generer_objet_*()` |
| `compacte()` | `minifier()` |
| `debut_cadre*()`, `debut_boite_*()`, `cadre_depliable()` | `boite_ouvrir()` / `boite_fermer()` |
| `$GLOBALS['formats_logos']` | `_image_extensions_logos()` / pipeline `image_extensions_logos` |
| `spip_sanitize_from_request($env, '*')` | `spip_sanitize_env_from_request($env)` |
| `$GLOBALS['meta']['x']` | `lire_config('x')` / `ecrire_config('x', $v)` (not deprecated in 5.0) |
| `<INCLURE{…}></INCLURE>`, `<INCLURE(x.php)>` | `<INCLURE{…} />`, `.html` only |
| `#LARGEUR_ECRAN` | `#LAYOUT_PRIVE{…}` |
| `_DIR_RESTREINT_ABS` in a squelette | `#PARAM{spip.routes.back_office}` |
| `{fusion}`, `{fusion_supprimer}`, `{collecte}` | **keep them** in shared squelettes: `{groupby}`, `{groupby_supprimer}`, `{collate}` do not exist in 4.4; the old names still work (deprecated) in 5.0 |

## Do not use in shared code

| Looks portable but is not | Why |
|---|---|
| `spip_logger()` | **does not exist in 4.4**, deprecated in 5.0 → keep `spip_log()` in shared code |
| `Spip::…` | absent in 4.4 |
| `SpipVersion::…` | absent in 4.4. `$GLOBALS['spip_version_branche']` is still set in 5.0 but holds the **full** version (`SpipVersion::VERSION`, ex. `5.0.0-beta`): compare with `version_compare()`, never `=== '5.0'` |
| `\SpipLeague\Component\Kernel\param()` | exists in 4.4 but deprecated in 5.0 |
| `_ROOT_RACINE`, `_DIR_IMG_PACK` | removed in 5.0 |

## Guarded 5.0 code

When a 5.0 behaviour is required, branch on the façade, not on a version string:

```php
if (class_exists(\Spip\Framework\Spip::class)) {
    $racine = \Spip\Framework\Spip::directories()->getProjectDir();
} else {
    $racine = _ROOT_RACINE;
}
```

A `config/services.php` and `src/` listeners are ignored by 4.4: shipping them alongside the legacy pipeline functions
would run the code **twice** on 5.0. Do not register the same pipeline both ways.
