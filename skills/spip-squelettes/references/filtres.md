# Filtres Reference

Filters transform balise output using the `|` pipe character. Multiple filters chain left to right.

```
[(#BALISE|filtre)]
[(#BALISE|filtre{arg1, arg2})]
[(#BALISE|filtre1|filtre2|filtre3)]
```

The first argument to a filter function is always the left-hand value; additional arguments follow in `{}`.

---

## Text Filters

### `typo`

Applies SPIP typographic corrections: non-breaking spaces before `:`, `;`, `!`, `?` (French rules), curly quotes, em-dashes.

```
[(#TITRE|typo)]
```

**Gotcha:** `#TITRE` already receives `|typo` automatically via `$table_des_traitements`. Don't double-apply unless you have a raw field.

---

### `propre`

Applies full SPIP shortcode processing (bold, italic, internal links, images) **plus** typographic corrections. This is the full SPIP rich-text pipeline.

```
[(#TEXTE|propre)]
```

`#TEXTE` already receives `propre` automatically. Use `|propre` on custom fields or data from BOUCLE DATA.

---

### `textebrut`

Strips all HTML; converts `<p>`, `<br>`, and newlines to single spaces; collapses repeated whitespace. Use for `<meta>` content, `<title>`, and `alt` attributes.

Signature: `textebrut($texte): string`

```
<title>[(#TITRE|textebrut)]</title>
<meta name="description" content="[(#DESCRIPTIF|textebrut|couper{160})]">
```

---

### `couper`

Truncates text to N characters (default 50). Strips HTML tags, avoids cutting mid-word, appends `(...)` if truncated.

Signature: `couper($texte, int $longueur=50, string $suite='(...)'): string`

```
[(#TEXTE|couper{200})]
[(#TEXTE|couper{80, '…'})]
<a href="#URL_ARTICLE" title="[(#TITRE|couper{80}|attribut_html)]">#TITRE</a>
```

**Gotcha:** The cut length is approximate — SPIP won't split words, so the result may be slightly shorter than requested.

---

### `supprimer_tags`

Removes all HTML tags, keeping their text content. Brutal — no newline conversion.

Signature: `supprimer_tags($texte): string`

```
[(#TEXTE|supprimer_tags)]
```

---

### `entites_html`

Converts special characters (`<`, `>`, `&`, `"`) to HTML entities.

Signature: `entites_html($texte): string`

```
[(#TITRE|entites_html)]
```

---

### `maj` / `min`

`|maj` converts to uppercase; `|min` converts to lowercase (PHP `mb_strtoupper`/`mb_strtolower`).

```
[(#TITRE|maj)]
[(#TITRE|min)]
```

---

### `liens_absolus`

Converts all relative links and image `src` attributes in a block of HTML text to absolute URLs (prepends current protocol + hostname).

Signature: `liens_absolus($texte, string $base=''): string`

```
[(#TEXTE|liens_absolus)]
[(#TEXTE|liens_absolus{#URL_SITE_SPIP})]
```

Useful in RSS squelettes where relative links break feed readers.

---

### `nettoyer_titre_url`

Converts a string to a URL-safe slug: lowercased, accents removed, spaces replaced by hyphens.

Signature: `nettoyer_titre_url($texte): string`

```
[(#TITRE|nettoyer_titre_url)]
<!-- "Mon Article Été" → "mon-article-ete" -->
```

---

## Date Filters

Apply to any balise that returns a SPIP date string (e.g., `#DATE`, `#DATE_REDAC`, `#DATE_MODIF`).

### `affdate`

Displays date as localized text: "13 janvier 2001". Accepts an optional PHP `date()` format string or a SPIP format name.

Signature: `affdate($date, string $format=''): string`

```
[(#DATE|affdate)]                     <!-- "13 janvier 2001" -->
[(#DATE|affdate{'Y-m-d'})]            <!-- "2001-01-13" -->
[(#DATE|affdate{'d/m/Y'})]            <!-- "13/01/2001" -->
[(#DATE|affdate{'saison'})]           <!-- "hiver" / "printemps" ... -->
```

---

### `affdate_court`

Shows day + month name. If the year differs from the current year, shows month + year only (omits day number).

```
[(#DATE|affdate_court)]   <!-- "19 Avril" or "Novembre 2004" -->
```

---

### `affdate_jourcourt`

Shows day + month name. If the year differs, adds the year.

```
[(#DATE|affdate_jourcourt)]   <!-- "19 Avril" or "1 Novembre 2004" -->
```

---

### `timestamp`

Returns a Unix timestamp (integer seconds since 1970-01-01).

```
[(#DATE|timestamp)]   <!-- "1010880000" -->
```

---

### `date_iso`

Returns ISO 8601 format (`Y-m-d\TH:i:s`).

```
<time datetime="[(#DATE|date_iso)]">[(#DATE|affdate)]</time>
```

---

### `date_relative`

Returns a human-readable relative string: "il y a 3 jours", "dans 2 heures" (language-aware).

```
[(#DATE|date_relative)]
```

---

## URL Filters

### `url_absolue`

Converts a relative URL returned by a balise to an absolute URL (adds protocol + domain).

Signature: `url_absolue($url, string $base=''): string`

```
[(#URL_ARTICLE|url_absolue)]
```

---

### `parametre_url`

Adds or replaces a query parameter in a URL. With a single argument, extracts the parameter value.

Signature: `parametre_url($url, string $param, string $valeur=''): string`

```
<!-- Add/replace a parameter -->
[(#SELF|parametre_url{'id_article','12'})]

<!-- Remove a parameter (empty value) -->
[(#SELF|parametre_url{'id_article',''})]

<!-- Extract a parameter value -->
[(#SELF|parametre_url{id_article})]
```

---

## Conditional Filters

### `sinon`

Returns the fallback string if the input is empty; otherwise returns the input unchanged.

Signature: `sinon($valeur, string $fallback): string`

```
[(#DESCRIPTIF|sinon{#CHAPO})]
[(#DESCRIPTIF|sinon{#TEXTE|couper{200}})]
```

---

### `oui`

Returns a non-empty string (a space) if the input is truthy; returns empty string if falsy. Used to trigger optional bracket sections.

```
[(#CHAPO|oui) Ce contenu a un chapeau ]
```

---

### `non`

Inverse of `oui`. Returns truthy value if input is falsy.

```
[(#CHAPO|non) Pas de chapeau ]
```

---

### `=={val}`

Returns truthy if the input equals `val`, empty otherwise.

```
[(#STATUT|=={publie}|oui) Article publié ]
```

Also available: `!={val}`, `>{val}`, `<{val}`, `>={val}`, `<={val}`, `==={val}` (strict).

---

### `?{sioui, sinon}`

Ternary: returns `sioui` if input is truthy, `sinon` otherwise.

```
[(#CHAPO|?{'a un chapo','pas de chapo'})]
[(#TOTAL_BOUCLE|=={0}|?{'Aucun résultat','Des résultats'})]
```

---

### `et` / `ou`

`et{val}` is truthy only when **both** the input and `val` are non-empty.
`ou{val}` is truthy when **either** is non-empty.

```
[(#CHAPO|et{#TEXTE}) Il y a un chapo et un texte ]
[(#CHAPO|ou{#TEXTE}) Il y a du contenu ]
```

---

## Image Filters

Image filters work on balises that return an `<img ...>` HTML tag (logos, `#LOGO_*`). They output a transformed `<img>` tag. Results are cached in `local/cache-gd2/` and `local/cache-vignettes/`.

Requires GD library on the server. The "Filtres Images et Couleurs" extension (active by default in SPIP 4) provides `image_recadre`, `image_passe_partout`, `image_format`, and `image_nb`.

### `image_reduire`

Resizes an image to fit within a maximum bounding box, preserving aspect ratio. Never upscales.

Signature: `image_reduire($img, int $largeur, int $hauteur=0): string`

- Single argument: constrain by width only
- Two arguments: fit within width × height box
- Pass `0` for one dimension to constrain only the other

```
[(#LOGO_ARTICLE|image_reduire{300})]          <!-- max 300px wide -->
[(#LOGO_ARTICLE|image_reduire{300,200})]      <!-- fit in 300×200 box -->
[(#LOGO_RUBRIQUE|right|image_reduire{130})]   <!-- aligned right, max 130px -->
```

---

### `image_recadre`

Crops the image to exact pixel dimensions. Default anchor: center.

Signature: `image_recadre($img, int $largeur, int $hauteur, string $h='center', string $v='center'): string`

Anchor values — horizontal: `left`, `center`, `right`; vertical: `top`, `center`, `bottom`.

```
[(#LOGO_ARTICLE|image_recadre{300,200})]
[(#LOGO_ARTICLE|image_recadre{300,200,'left','top'})]
```

**Gotcha (critical):** Always apply `image_reduire` **before** `image_recadre`. If the source image is smaller than the crop target, `image_recadre` will upscale it (producing a blurry result). `image_reduire` ensures the image is at least as large as the crop box before cropping.

```
<!-- CORRECT: reduce first, then crop -->
[(#LOGO_RUBRIQUE|image_reduire{300,200}|image_recadre{300,200})]

<!-- WRONG: may upscale small images -->
[(#LOGO_RUBRIQUE|image_recadre{300,200})]
```

---

### `image_passe_partout`

Fits the image inside the target dimensions by padding with a background color (letterbox). Produces exact pixel dimensions without cropping.

Signature: `image_passe_partout($img, int $largeur, int $hauteur, string $couleur='ffffff'): string`

```
[(#LOGO_ARTICLE|image_passe_partout{300,200})]
[(#LOGO_ARTICLE|image_passe_partout{300,200,'f0f0f0'})]
```

---

### `image_format`

Converts the image to a different format.

Signature: `image_format($img, string $format): string`

Valid formats: `jpg`, `gif`, `png`, `webp`

```
[(#LOGO_ARTICLE|image_reduire{300}|image_format{'webp'})]
```

---

### `image_nb`

Converts the image to greyscale.

Signature: `image_nb($img): string`

```
[(#LOGO_ARTICLE|image_reduire{200}|image_nb)]
```

---

### `image_src`

Extracts the `src` attribute value from an `<img ...>` tag. Returns a plain URL string.

Signature: `image_src($img): string`

```
[(#LOGO_ARTICLE|image_reduire{300}|image_src)]
<!-- outputs: /local/cache-vignettes/L300x200/logo-xxx.jpg -->
```

Useful when you need the raw URL to use in CSS `background-image` or JSON data.

---

## Number Filters

### `taille_en_octets`

Converts a byte count to a human-readable string (B, Ko, Mo, Go).

Signature: `taille_en_octets(int $octets): string`

```
<BOUCLE_docs(DOCUMENTS){id_article}>
  [(#TAILLE|taille_en_octets)]  <!-- "24.4 Mo" -->
</BOUCLE_docs>
```

---

## Filter Chaining

### Image pipeline example

```
[(#LOGO_RUBRIQUE
  |image_reduire{300,200}
  |image_recadre{300,200}
  |image_format{'webp'}
)]
```

Step-by-step:
1. `image_reduire{300,200}` — scale down to fit in the 300×200 box (no upscaling)
2. `image_recadre{300,200}` — crop to exactly 300×200 (source is now ≥300×200 so no upscaling)
3. `image_format{'webp'}` — convert to WebP for smaller file size

**Order rule:** Always `image_reduire` before `image_recadre`. Never swap them.

---

### Text pipeline example

```
<meta name="description" content="[(#DESCRIPTIF|sinon{#TEXTE}|textebrut|couper{160}|attribut_html)]">
```

1. `sinon{#TEXTE}` — fall back to article body if no description
2. `textebrut` — strip all HTML
3. `couper{160}` — truncate to 160 chars
4. `attribut_html` — escape quotes/special chars safe for the HTML attribute

---

## Filter Syntax Notes

- Arguments in `{}`  are comma-separated: `|filtre{arg1, arg2}`
- Arguments can be balises: `[(#DESCRIPTIF|sinon{[(#CHAPO|sinon{#TEXTE}|couper{300})]})]`
- SPIP resolves filter name `x` by looking for `filtre_x`, then `filtre_x_dist`, then `x` — so any PHP function is usable as a filter (e.g., `|strlen`, `|strtolower`, `|rtrim{'.?!'}`)
- Custom filters go in `squelettes/mes_fonctions.php` (or root `mes_fonctions.php`) — never modify SPIP core files
