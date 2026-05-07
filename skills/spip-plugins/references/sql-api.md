# SQL API

SPIP wraps the database in an abstraction layer (`ecrire/base/abstract_sql.php`) that makes code
portable across MySQL, PostgreSQL, and SQLite. **Never write raw SQL.** Always use the `sql_*`
functions documented here.

All functions share two optional trailing parameters:
- `$serveur = ''` — database connexion name (`''` = default SPIP connexion)
- `$option = true` — `true` = execute; `false` = return query string; `'continue'` = don't fail if server unavailable

---

## Reading data

### sql_select — execute a SELECT

```php
sql_select(
    $select  = [],   // columns: 'id_article, titre' or ['id_article', 'titre']
    $from    = [],   // tables: 'spip_articles' or ['spip_articles AS a', ...]
    $where   = [],   // conditions (ANDed): "statut='publie'" or ['statut='.sql_quote($s), ...]
    $groupby = [],   // GROUP BY: 'id_rubrique'
    $orderby = [],   // ORDER BY: 'date DESC'
    $limit   = '',   // LIMIT: '0,10'
    $having  = [],   // HAVING clause
    $serveur = '',
    $option  = true
): resource|false
```

Returns an opaque result resource; iterate with `sql_fetch()` or consume with helpers below.

```php
// ecrire/public/aiguiller.php — iterating a result
$res = sql_select('id_article, titre', 'spip_articles', "statut='publie'", '', 'date DESC', '0,10');
while ($row = sql_fetch($res)) {
    // $row = ['id_article' => 5, 'titre' => '...']
}
sql_free($res);
```

### sql_fetsel — fetch first row

```php
// Returns first matching row as associative array, or empty array if no match
$row = sql_fetsel('id_rubrique, titre', 'spip_rubriques', 'id_rubrique='.intval($id));
```

### sql_allfetsel — fetch all rows

```php
// Returns array of associative arrays
$rows = sql_allfetsel('id_article, titre', 'spip_articles', "id_rubrique=".intval($id_rub));
```

### sql_countsel — count rows

```php
$nb = sql_countsel('spip_articles', ["statut='publie'", 'id_rubrique='.intval($id)]);
if ($nb > 0) { ... }
```

---

## Writing data

### sql_insertq — insert one row (auto-quoted)

```php
// Values are automatically escaped. Returns insert id or true.
$id = sql_insertq('spip_mots', [
    'titre' => $titre,
    'id_groupe' => intval($id_groupe),
    'statut' => 'publie',
]);
```

### sql_insertq_multi — insert multiple rows

```php
sql_insertq_multi('spip_mots_liens', [
    ['id_mot' => 3, 'objet' => 'article', 'id_objet' => 12],
    ['id_mot' => 3, 'objet' => 'article', 'id_objet' => 14],
]);
```

### sql_updateq — update rows (auto-quoted)

```php
// Values are automatically escaped.
sql_updateq('spip_articles', ['statut' => 'publie'], 'id_article='.intval($id));
```

### sql_update — update with raw SQL expressions

Use when a column must be set to a computed value (e.g. increment a counter):

```php
// NOT escaped — use for SQL expressions, not user input
sql_update('spip_forum', ['nb_reponses' => 'nb_reponses+1'], 'id_forum='.intval($id));
```

### sql_delete — delete rows

```php
sql_delete('spip_mots_liens', ['objet='.sql_quote('article'), 'id_objet='.intval($id)]);
```

---

## Escaping and conditions

### sql_quote — escape a value

```php
// Produce a quoted, escaped SQL literal safe to embed in a WHERE clause
$where = 'titre=' . sql_quote($titre);

// With explicit type hint (deduced from table descriptor when $desc is provided)
$where = 'id_objet=' . sql_quote($id, '', 'int');
```

**Always use `sql_quote()` for user-supplied strings.** Never interpolate raw user input.

Integers: prefer `intval()` for numeric IDs; `sql_quote()` works but `intval()` is simpler.

### sql_in_quote — IN clause (safe for strings)

```php
// Produces: "statut IN ('publie','prop')"
$where = sql_in_quote('statut', ['publie', 'prop']);

// NOT IN
$where = sql_in_quote('statut', ['spam'], 'NOT');
```

### sql_in — IN clause (legacy, integers only)

```php
// Accepts comma-separated string or int array; forces cast to int
$where = sql_in('id_article', '3,4,5');        // from string
$where = sql_in('id_article', [3, 4, 5]);       // from array
```

Prefer `sql_in_quote()` for anything that isn't a plain integer list.

---

## Schema operations

### sql_alter

```php
// ALTER TABLE — pass the clause after "ALTER "
sql_alter('TABLE spip_articles ADD COLUMN mon_champ TEXT DEFAULT ""');
sql_alter('TABLE spip_articles DROP COLUMN ancien_champ');
```

Use in `_administrations.php` migration steps, not at runtime. See `references/cycle-de-vie.md`.

### sql_drop_table

```php
sql_drop_table('spip_mon_plugin');          // fails if table does not exist
sql_drop_table('spip_mon_plugin', 'exist'); // silently ignores missing table
```

---

## Transactions

```php
if (sql_preferer_transaction()) {
    sql_demarrer_transaction();
}

sql_insertq(...);
sql_insertq(...);

if (sql_preferer_transaction()) {
    sql_terminer_transaction();
}
```

Used for bulk inserts (SQLite benefits especially). Note: SPIP does not expose an explicit ROLLBACK;
if an operation fails mid-transaction, SPIP logs the error and the transaction remains open until
`sql_terminer_transaction()`.

---

## When to use objet_modifier() instead of raw SQL

For **objet éditorial** types (those declared with `declarer_tables_objets_sql`), always prefer the
high-level API over direct SQL writes:

| You want to… | Use… |
|---|---|
| Create a new objet éditorial | `objet_inserer($type)` |
| Modify fields on an existing objet | `objet_modifier($type, $id, $champs)` |
| Change statut/parent (publier, proposer…) | `objet_instituer($type, $id, $qualif)` |
| Read any field | `sql_fetsel` directly is fine |
| Write to a non-objet link table | `sql_insertq` / `sql_updateq` / `sql_delete` |
| Low-level migration (schema changes) | `sql_alter` / `sql_drop_table` in `_administrations.php` |

`objet_modifier()` fires the `pre_edition` and `post_edition` pipelines, updates `maj` (last-modified
timestamp), and invalidates caches. Bypassing it via raw SQL skips all of that.

```php
// Correct — pipelines fire, cache invalidated
objet_modifier('article', $id, ['titre' => $nouveau_titre]);

// Wrong for objet éditorial fields — bypasses pipelines
sql_updateq('spip_articles', ['titre' => $nouveau_titre], 'id_article='.intval($id));
```

See `references/declarer-objet.md` for full `objet_modifier` / `objet_inserer` / `objet_instituer`
signatures and contracts.

---

## Common patterns

```php
// Safe pattern: fetch one row and check existence
$article = sql_fetsel('id_article, titre, statut', 'spip_articles', 'id_article='.intval($id));
if (!$article) {
    // not found
}

// Insert and get new ID
$id_mot = sql_insertq('spip_mots', ['titre' => $titre, 'id_groupe' => intval($id_groupe)]);

// Conditional update (no side-effects on objet éditorial)
sql_updateq('spip_forum', ['statut' => 'publie'], [
    'id_forum=' . intval($id),
    "statut != 'publie'",
]);
```

---

## See also

- `references/declarer-table.md` — how to declare `$desc` for your own tables
- `references/declarer-objet.md` — `objet_modifier`, `objet_inserer`, `objet_instituer`
- `references/cycle-de-vie.md` — when to run `sql_alter` / `sql_drop_table` in migrations
