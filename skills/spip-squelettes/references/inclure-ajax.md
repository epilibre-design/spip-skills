# INCLURE & AJAX Reference

Topics: INCLURE fragments, file resolution order, argument passing, AJAX partial reload.

---

## INCLURE — Including Fragments

`INCLURE` composes a squelette from smaller reusable parts. The fond argument names the template file (without `.html`).

### Syntax forms

```html
<!-- Basic include — mandatory, errors if fond missing -->
<INCLURE{fond=inc-sidebar} />

<!-- Optional include — renders nothing if fond not found -->
[(#INCLURE{fond=inc-sidebar})]

<!-- Pass the full current environment -->
<INCLURE{fond=inc-sidebar,env} />

<!-- Pass specific arguments -->
<INCLURE{fond=inc-liste-articles,id_rubrique=#ID_RUBRIQUE,nb=5} />

<!-- Mix: pass env + override one key -->
<INCLURE{fond=inc-header,env,titre_page=Mon titre} />
```

### Argument passing

Arguments become `#ENV{key}` inside the included file:

```html
<!-- parent template -->
<INCLURE{fond=inc-card,id_article=#ID_ARTICLE,classe=featured} />

<!-- inc-card.html -->
<BOUCLE_a(ARTICLES){id_article=#ENV{id_article}}>
  <article class="[(#ENV{classe})]">
    <h2>#TITRE</h2>
  </article>
</BOUCLE_a>
```

### File resolution order

SPIP searches for the template in this order:

1. firstly in the list of folders specified in the `$dossier_squelettes` folder, if one has been defined;
2. then in the 'squelettes/' folder located at site root;
3. then in the list of folders in the `$plugins` variable;
4. then in the site root;
5. then in the 'squelettes-dist/' folder;
6. and finally in the 'ecrire/' directory.

This means you can override any default fond by creating a same-named file in `squelettes/`.

### Gotcha: env is not inherited automatically

Without `env` or explicit arguments, the included template starts with an empty environment. Always be explicit about what the fragment needs.

```html
<!-- WRONG: inc-nav.html has no access to #ID_RUBRIQUE -->
<INCLURE{fond=inc-nav} />

<!-- CORRECT: pass what the fragment needs -->
<INCLURE{fond=inc-nav,id_rubrique=#ID_RUBRIQUE} />
<!-- or pass everything -->
<INCLURE{fond=inc-nav,env} />
```

---

## AJAX — Partial Reload

Adding `{ajax}` to an INCLURE makes the block reloadable without a full page refresh. SPIP's built-in JavaScript intercepts links and form submissions inside the block and replaces only that block's HTML.

### Syntax

```html
<!-- INCLURE with ajax reloading -->
<INCLURE{fond=inc-liste-articles,env}{ajax} />

<!-- Custom container ID (useful for JS targeting) -->
<INCLURE{fond=inc-recherche,env}{ajax id=zone-resultats} />
```

### How it works

1. SPIP wraps the output in `<div id="spip_ancre_NNN">…</div>`
2. Any link or form inside that div gets intercepted by SPIP JS
3. SPIP fetches the new content for just that fond and replaces the div

### Requirements

SPIP's JavaScript must be loaded in the page. Add this in your main squelette:

```html
[(#CHEMIN{javascript/jquery.form.js}|oui)]
```

Or use the `spip_javascripts` plugin, which is included by default in SPIP 4.x distributions.

### Limitations

- The included fond must be a self-contained SPIP template (no inline PHP)
- The fond is re-executed server-side on each AJAX request — keep it cacheable
- Does not work across different domains (same-origin only)
- `{ajax}` is not inherited by nested INCLUREs — add it to each one explicitly

### Gotcha: CACHE and AJAX

If the included fond is cached (`#CACHE{3600}`), AJAX reloads serve cached content until the cache expires. Use `#CACHE{0}` for live data, or set a short TTL.

```html
<!-- inc-live-results.html -->
#CACHE{0}
<BOUCLE_arts(ARTICLES){recherche}>
  <p>#TITRE</p>
</BOUCLE_arts>
```
