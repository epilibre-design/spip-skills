# Variables Reference

Topics: #ENV (environment and URL parameters), #SET/#GET (template-scoped variables), #SESSION_SET/#SESSION (session-scoped data), #CONFIG (plugin/site configuration).

---

## #ENV — Read environment and URL parameters

`#ENV{key}` reads from:
1. Arguments passed in the current `INCLURE`
2. URL parameters (`?key=value`)
3. The current page's context

```html
<!-- URL: ?page=article&id_article=42 -->
#ENV{id_article}         → 42
#ENV{page}               → article

<!-- With a default value if key is absent -->
#ENV{mode, liste}        → "liste" if mode not in URL

<!-- Used in a critère -->
<BOUCLE_a(ARTICLES){id_article=#ENV{id_article}}>
  #TITRE
</BOUCLE_a>
```

---

## #SET and #GET — Template-scoped variables

`#SET` stores a value; `#GET` retrieves it. Scope is **limited to the current template file** — values do NOT cross INCLURE boundaries.

```html
<!-- Set a variable -->
#SET{compteur, 0}
#SET{titre_formate, [(#TITRE|maj|couper{50})]}

<!-- Read it back -->
[(#GET{titre_formate})]

<!-- Useful for avoiding double computation -->
<BOUCLE_a(ARTICLES){id_article}>
  #SET{url, #URL_ARTICLE}
  <a href="[(#GET{url})]">#TITRE</a>
  <link rel="canonical" href="[(#GET{url})]" />
</BOUCLE_a>
```

### Gotcha: #SET does not cross INCLURE

```html
<!-- parent.html -->
#SET{myvar, hello}
<INCLURE{fond=child} />

<!-- child.html -->
[(#GET{myvar})]   ← EMPTY — #SET does not cross INCLURE boundary
```

To pass a value into an INCLURE, use an explicit argument:

```html
<!-- CORRECT -->
<INCLURE{fond=child,myvar=#GET{myvar}} />

<!-- child.html -->
#ENV{myvar}   ← "hello" ✓
```

---

## #SESSION and #SESSION_SET — Session data

`#SESSION{key}` reads data from the current visitor's session. For **logged-in users**, this includes their profile fields (`nom`, `email`, `statut`, `id_auteur`, …). For **any visitor**, it can also hold arbitrary data written with `#SESSION_SET`.

`#SESSION_SET{variable, value}` stores an arbitrary value in the session (`$GLOBALS['visiteur_session']['variable']`), retrievable at any point during the open session via `#SESSION{variable}`. Scope is **the entire session** — values persist across page loads until the session ends.

```html
<!-- Read built-in user fields (logged-in visitors) -->
[(#SESSION{nom})]          <!-- username -->
[(#SESSION{email})]        <!-- email -->
[(#SESSION{statut})]       <!-- 0minirezo (admin), 1comite (editor), 6forum (visitor) -->

<!-- Conditional block for logged-in users only -->
<BOUCLE_connecte(CONDITION){si #SESSION{id_auteur}}>
  Bonjour [(#SESSION{nom})] !
</BOUCLE_connecte>
<B_connecte>
  <a href="#URL_PAGE{login}">Connexion</a>
</B_connecte>

<!-- Store arbitrary data in the session -->
#SESSION_SET{mypassion, elephants}

<!-- Retrieve it on any page during the session -->
[(#SESSION{mypassion})]    <!-- outputs: elephants -->
```

### Gotcha: scope differences

| Tag | Scope | Crosses INCLURE? | Persists across pages? |
|-----|-------|-----------------|------------------------|
| `#SET` / `#GET` | Current template file | No | No |
| `#SESSION_SET` / `#SESSION` | Visitor session | Yes | Yes |

---

## #CONFIG — Read plugin configuration

`#CONFIG{key}` reads a value from the `spip_meta` table. The entire table is loaded into memory on every request, so reads are free.

```html
<!-- Read a global SPIP setting -->
[(#CONFIG{accepter_visiteurs})]          <!-- "oui" or "non" -->
[(#CONFIG{adresse_site})]                <!-- site URL -->

<!-- With a default value if the key is absent -->
[(#CONFIG{adresse_site, no URL defined})]

<!-- Read a plugin config value (stored as prefix/key) -->
[(#CONFIG{myplugin/in_the_spotlight})]
[(#CONFIG{myplugin/in_the_spotlight, 3})]   <!-- with default -->
```

The `/` separator lets you reach inside a serialised array stored under a plugin prefix — the convention used by SPIP's `cvt_configurer` system.

### Gotcha: serialised arrays

`#CONFIG{myplugin}` returns the raw serialised string if the plugin stores all its settings under one meta key. Always use `#CONFIG{myplugin/key}` to reach individual values.

### Writing config from PHP

Config is read-only from squelettes. To write or delete values, use PHP in a plugin:

```php
ecrire_config('myplugin/key', 'value');   // write
lire_config('myplugin/key', 'default');   // read
effacer_config('myplugin/key');           // delete
```

See `spip-plugins` skill → `references/config.md` for the full configuration page pattern.
