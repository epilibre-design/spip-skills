# Modèles Reference

Topics: SPIP modèles, editorial shortcuts, `#MODELE`, mini-squelettes, `modeles/`, `#ENV`, parameters, inserting into content.

---

## What is a modèle?

A modèle is a mini-squelette SPIP stored in a `modeles/` directory. It can be inserted:

- **in editorial content** (articles, brèves, etc.) using a shortcut of the form `<model_name1>`;
- **in a squelette** using the `#MODELE{name}` balise.

Modèles extend the classic shortcuts `<img1>` and `<doc1>`, which map to `modeles/img.html` and `modeles/doc.html` respectively.

---

## Syntax in squelettes: `#MODELE`

```html
<!-- Simple inclusion — parent loop id is passed as the "id" parameter -->
[(#MODELE{name})]

<!-- With extra arguments (preferred syntax) -->
[(#MODELE{name, arg1=value, arg2=value})]

<!-- With a filter applied to the result -->
[(#MODELE{name, p1=foo, p2=bar}|filtre)]

<!-- Alternative syntax — accepted but discouraged -->
[(#MODELE{name}{arg=xx}{arg2})]
```

**Note:** `#MODELE` is a **static** inclusion (cached). Do not use dynamic balises or `#FORMULAIRE_XYZ` inside a modèle. Use `#INCLURE` when dynamic behaviour is needed.

---

## Syntax in editorial content

To avoid collisions with HTML tags, `<model_name>` alone is not valid. One of these forms is required:

1. **A numeric identifier**: `<modele1>` — passes `#ENV{id}=1` and `#ENV{id_modele}=1`;
2. **A trailing pipe**: `<modele|>` — passes `#ENV{id}=0`.

```
// Basic call with identifier
<my_modele1>

// Without identifier (id will be 0)
<my_modele|>

// With parameters
<my_modele1|param1=value|param2=value>

// Multi-line parameters (readability)
<modele10
  |country=Germany
  |population=82000000
  |area=357027
  |anthem=<i>Das Lied der Deutschen</i>
  |url=http://fr.wikipedia.org/wiki/Allemagne
>

// CSS class or sub-modèle
<img1|right>               → passes align=right
<img1|stamp>               → looks for modeles/img_stamp.html
<img1|unknown_class>       → looks for modeles/img_unknown_class.html, falls back to img.html with class="unknown_class"
```

---

## Accessing parameters inside a modèle

Inside `modeles/my_modele.html`, all parameters are available via `#ENV`:

| Balise | Description |
|---|---|
| `#ENV{id}` | Numeric identifier passed in the shortcut |
| `#ENV{id_modele}` | Numeric identifier (alias specific to the modèle name) |
| `#ENV{param}` | Named parameter, **HTML-escaped** (safe) |
| `#ENV*{param}` | Named parameter, **raw HTML** (when the parameter may contain HTML) |
| `[(#ENV*{param}\|typo)]` | Raw HTML parameter with SPIP inline typography |
| `[(#ENV*{param}\|propre)]` | Raw HTML parameter with full SPIP formatting (multi-paragraph) |
| `#ENV{align}` | `left`, `right`, or `center` when alignment is passed |
| `#ENV{lien}` | URL when called as `[<modele1>->url]` |
| `#ENV{lien_class}` | CSS class of the link (`spip_out`, `spip_in`, etc.) |

**Debug tip:** write `#ENV` alone in the modèle to dump the full serialized environment — useful to identify exact parameter names received.

---

## Examples

### Modèle linked to an article

```html
<!-- Called in an article as: <article3|chapo> -->
<!-- File: modeles/article_chapo.html -->

<BOUCLE_a(ARTICLES){id_article=#ENV{id}}>
  <div class="chapo">#CHAPO</div>
</BOUCLE_a>
```

### Modèle with free parameters

```html
<!-- Called as: <son19|color=#ff0000|caption=The great <i>Count Basie</i>|photo=12> -->
<!-- File: modeles/son.html -->

<BOUCLE_photo(DOCUMENTS){id_document=#ENV{photo}}>
  <figure style="border-color: [(#ENV{color})]">
    #LOGO_DOCUMENT
    <figcaption>[(#ENV*{caption}|typo)]</figcaption>
  </figure>
</BOUCLE_photo>
```

### Modèle from a squelette (`#MODELE`)

```html
<!-- In article.html — list translations of the current article -->
<BOUCLE_art(ARTICLES){id_article}>
  #MODELE{article_traductions}
</BOUCLE_art>

<!-- With extra parameters -->
[(#MODELE{article_mots, limite=5})]
```

### Form inserted as a modèle

```
<!-- Inside an article body -->
<formulaire|login>
```

---

## Link handling in a modèle

When a modèle is called as `[<modele1>->url]`, the URL is passed via `#ENV{lien}`. The modèle must handle it explicitly and add `class="spip_lien_ok"` to its outermost HTML element to signal that the link has been taken care of. Without this, SPIP wraps the entire output in `<a href="...">` automatically.

```html
<!-- modeles/my_modele.html -->
<div class="spip_lien_ok">
  <a href="[(#ENV{lien})]">#TITRE</a>
</div>
```

---

## File location and resolution order

SPIP searches for modèles in `modeles/` directories following the standard squelette resolution order:

1. `squelettes/modeles/` — local override, highest priority
2. Active plugin directories
3. `squelettes-dist/modeles/`

**Convention:** always name files in **lowercase only** (`modeles/my_modele.html`). Case-sensitive filesystems will not find `My_Modele.html`.

---

## Built-in modèles

| Modèle | Editorial shortcut | Description |
|---|---|---|
| `img.html` | `<img1>` | Display an image |
| `doc.html` | `<doc1>` | Display a document |
| `emb.html` | `<emb1>` | Embed multimedia / CSV |
| `article_mots.html` | `<article1\|mots>` | Keywords linked to an article |
| `article_traductions.html` | `<article1\|traductions>` | Links to translations |
| `lesauteurs.html` | *(not usable as shortcut)* | Output of `#LESAUTEURS` |
| `pagination*.html` | *(pagination)* | Set of pagination modèles |

---

## Best practices

- **Design the syntax first**: is the modèle tied to an article, a rubrique, or neither? This determines the filename (`article_xxx.html`, `rubrique_xxx.html`, or `xxx.html`).
- **Debug with `#ENV`**: place `#ENV` alone in the file to see all received parameters.
- **Prefer boucles over PHP**: write modèles entirely with SPIP boucles and balises. If PHP is present, the computed result is cached — not the script itself.
- **No dynamic balises**: `#FORMULAIRE_XYZ` and dynamic elements do not work in a modèle (static cache). Use `<INCLURE{fond=...,env}{ajax}>` instead.
- **Handle `align` and `lien`**: implement `#ENV{align}` (CSS float) and `#ENV{lien}` + `class="spip_lien_ok"` so the modèle is compatible with all calling syntaxes.
- **`#ENV{param}` vs `#ENV*{param}`**: use `#ENV*` only when the parameter is expected to contain HTML, and always filter it with `|typo` or `|propre`.

---

## See also

- [balises.md](balises.md) — `#ENV`, `#INCLURE`, `#MODELE`
- [inclure-ajax.md](inclure-ajax.md) — difference between modèle and `<INCLURE>`, ajax
- [boucles.md](boucles.md) — boucles usable inside a modèle
