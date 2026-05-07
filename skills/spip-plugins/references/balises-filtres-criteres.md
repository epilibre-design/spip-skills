# Balises, Filtres, Critères

This reference covers the three mechanisms a plugin uses to extend the SPIP squelette compiler:
custom balises, filtres, and critères.

---

## Balises

A balise is a compile-time function that transforms `#NOM` in a squelette into a PHP expression.
The compiler calls it once, not at render time.

### Signature

```php
use Spip\Compilateur\Noeud\Champ;

// in myplugin_fonctions.php or balise/mon_nom.php
function balise_MON_NOM_dist(Champ $p): Champ {
    $p->code = "some PHP expression";
    $p->interdire_scripts = false;   // set false when output is safe (no user input)
    return $p;
}
```

`$p->code` must be a string of valid PHP code — it is spliced verbatim into the compiled squelette.

### Key Champ properties

| Property | Type | Meaning |
|---|---|---|
| `$p->code` | string | **Required to set.** PHP expression the balise compiles to |
| `$p->interdire_scripts` | bool | `true` (default) = wrap in `interdire_scripts()`; set `false` for trusted output |
| `$p->nom_champ` | string | Raw balise name from squelette (`'MON_NOM'`) |
| `$p->id_boucle` | string | ID of the enclosing boucle (`''` if outside any boucle) |
| `$p->boucles` | array | Full boucle AST — used by `champ_sql()` / `index_pile()` |
| `$p->param` | array | Balise arguments `{arg1, arg2}`: `$p->param[0][1]`, `$p->param[0][2]`… |

### Helper functions (called inside a balise function)

```php
// Read a column from the current boucle's result row (returns a PHP code string)
$code = champ_sql('titre', $p);          // => "$Pile[N]['titre']" at render time
$code = champ_sql('date', $p, "''");     // with default value

// Read an explicit argument #{balise, arg1, arg2}
$arg1 = interprete_argument_balise(1, $p);  // null if not provided
$arg2 = interprete_argument_balise(2, $p);

// Walk up the boucle stack to find a column (useful outside loops)
$code = index_pile($p->id_boucle, 'id_rubrique', $p->boucles);
```

### Simple example

```php
// plugins-dist/spip/bigup/bigup_fonctions.php:90
function balise_BIGUP_TOKEN(Champ $p): Champ {
    $champ   = interprete_argument_balise(1, $p) ?? "@\$Pile[0]['nom']";
    $form    = interprete_argument_balise(3, $p) ?? "@\$Pile[0]['form']";
    $p->code = "calculer_balise_BIGUP_TOKEN($champ, ..., $form)";
    $p->interdire_scripts = false;
    return $p;
}
```

### Override a core balise

Drop the `_dist` suffix. The compiler prefers the unsuffixed version:

```php
// myplugin_fonctions.php — override #DATE in all loops
function balise_DATE(Champ $p): Champ {
    $p->code = champ_sql('ma_date', $p);  // redirect to a different column
    return $p;
}
```

Only override when you need to change the meaning globally. Prefer the `table_des_traitements`
mechanism (see below) to change rendering per-loop.

### Balise file placement

A balise named `#MON_NOM` can also live in `myplugin/balise/mon_nom.php` as
`balise_MON_NOM_dist()`. The compiler auto-loads it when the balise is encountered.

---

## Filtres

A filtre is a PHP function callable from squelettes with the pipe syntax `[(#CHAMP|mon_filtre)]`.

### Declaring a filtre

Any function defined in a file loaded during squelette execution is callable as a filtre.
The primary location is the plugin's `_fonctions.php` file (loaded by SPIP's autoloader):

```php
// myplugin/myplugin_fonctions.php
function mon_filtre($valeur, $option = '') {
    // $valeur: the piped value
    // return the transformed value
    return strtolower($valeur);
}
```

Usage in squelette: `[(#TITRE|mon_filtre)]` or `[(#TITRE|mon_filtre{option})]`

### Lazy-loaded filtres (spip_matrice)

For filtres defined in files that should not be loaded on every hit, register in `_fonctions.php`:

```php
// plugins-dist/spip/images/images_fonctions.php:24
$GLOBALS['spip_matrice']['image_recadre'] = 'filtres/images_transforme.php';
$GLOBALS['spip_matrice']['image_nb']      = 'filtres/images_transforme.php';
```

SPIP includes the file the first time the filtre is called. Use this for heavy filtres (image
processing, PDF generation, etc.) to avoid loading them on every page.

### Automatic balise treatments (table_des_traitements)

To apply a filtre automatically to a `#BALISE` in every loop of a given type, use the
`declarer_tables_interfaces` pipeline (see `references/declarer-table.md`):

```php
// in myplugin_declarer_tables_interfaces():
$interfaces['table_des_traitements']['TEXTE']['mon_type'] = 'safehtml(typo(%s))';
// 0 = default (all loops not explicitly overridden)
$interfaces['table_des_traitements']['MON_CHAMP'][0] = 'strtolower(%s)';
```

The `%s` placeholder is replaced with the compiled PHP expression of the balise.

---

## Critères

A critère is a compile-time function that modifies the SQL query of a boucle. It runs once at
compile time and mutates the `Boucle` object.

### Signature

```php
use Spip\Compilateur\Noeud\Critere;

function critere_mon_critere_dist(
    string $idb,       // boucle identifier (e.g., 'B1')
    array  &$boucles,  // full boucle AST, passed by reference
    Critere $crit      // the parsed critère node
): void {
    $boucle = &$boucles[$idb];

    // Add a WHERE condition (array of SQL fragments or nested arrays)
    $boucle->where[] = ["'='", "'$boucle->id_table.mon_champ'", sql_quote($valeur)];

    // Add a table to FROM (if joining)
    // $boucle->from['alias'] = 'spip_autre_table';

    // Override LIMIT
    // $boucle->limit = '0,5';
}
```

### Critere properties

| Property | Type | Meaning |
|---|---|---|
| `$crit->op` | ?string | Operator: `'='`, `'<'`, `'>'`, `'IN'`, etc. |
| `$crit->not` | bool | Negation present: `{!mon_critere}` |
| `$crit->exclus` | string | `'!'` if exclusion syntax used |
| `$crit->cond` | bool | Conditional: `{mon_critere ?}` |
| `$crit->param` | array | Params: `$crit->param[0]` = left side, `$crit->param[1]` = right side |

### Boucle properties modified by critères

| Property | What to write |
|---|---|
| `$boucle->where` | Append conditions; each item is a `['op', 'field', 'value']` triple or nested array |
| `$boucle->from` | Array of `'alias' => 'table_sql'` for extra JOINs |
| `$boucle->limit` | String `'offset,count'` or just `'count'` |
| `$boucle->groupby` | Array of GROUP BY expressions |
| `$boucle->orderby` | Array of ORDER BY expressions |
| `$boucle->primary` | String: primary key column name (read-only in most critères) |
| `$boucle->id_table` | String: table alias (e.g., `'spip_articles'`) — used to prefix columns |

### Real example (racine critère)

```php
// ecrire/public/criteres.php:47
function critere_racine_dist($idb, &$boucles, $crit) {
    $boucle     = &$boucles[$idb];
    $id_parent  = $GLOBALS['exceptions_des_tables'][$boucle->id_table]['id_parent'] ?? 'id_parent';
    $c          = ["'='", "'$boucle->id_table.$id_parent'", 0];
    $boucle->where[] = $crit->not ? ["'NOT'", $c] : $c;
}
```

### Registering a critère

No explicit registration needed. Name the function `critere_NOM_dist()` in any file loaded during
squelette compilation (typically `myplugin_fonctions.php` or `inc/criteres.php`). SPIP discovers
it via `charger_fonction()`.

---

## File summary

| Extension point | Where to put the function | Naming pattern |
|---|---|---|
| Balise | `myplugin_fonctions.php` or `balise/nom.php` | `balise_NOM_dist(Champ $p)` |
| Filtre | `myplugin_fonctions.php` | any function name |
| Lazy filtre | `myplugin_fonctions.php` → `$GLOBALS['spip_matrice']` | any function name |
| Critère | `myplugin_fonctions.php` | `critere_NOM_dist($idb, &$boucles, $crit)` |

---

## See also

- `references/declarer-table.md` — `declarer_tables_interfaces` for `table_des_traitements` and
  `table_des_tables`
- `references/pipelines.md` — `recuperer_fond` pipeline for altering squelette selection at render time
