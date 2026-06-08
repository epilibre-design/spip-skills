# GREEN Test — spip-squelettes skill

Date: 2026-04-30
Score: 8/8

## Results

| # | Question | Score | Notes |
|---|---|---|---|
| 1 | BOUCLE listing last 5 articles from rubrique #3, newest first | PASS | Exact example in boucles.md |
| 2 | `#LOGO_ARTICLE` with `#LOGO_RUBRIQUE` fallback | PASS | Two valid patterns: `#LOGO_ARTICLE_RUBRIQUE` (simpler) and `sinon` chain |
| 3 | Pagination critère + link balise | PASS | `{pagination N}` + `#PAGINATION` in SKILL.md |
| 4 | Recursive BOUCLE for rubrique tree | PASS | Full pattern in boucles.md |
| 5 | `{doublons}` mechanism + featured/remaining example | PASS | Full example in balises-criteres.md |
| 6 | AJAX INCLURE fragment | PASS | `{ajax}` syntax + requirements in avance.md |
| 7 | `image_reduire` + `image_recadre` on `#LOGO_RUBRIQUE` | PASS | Exact signatures in filtres.md; order gotcha documented |
| 8 | Login form balise + placement | PASS | `#FORMULAIRE_LOGIN` in avance.md formulaires table |

---

## Answers

### Q1 — BOUCLE listing last 5 articles from rubrique #3, newest first

```html
<BOUCLE_arts(ARTICLES){id_rubrique=3}{par date}{inverse}{limit 5}>
  <li><a href="#URL_ARTICLE">#TITRE</a></li>
</BOUCLE_arts>
```

Source: `references/boucles.md` — ARTICLES section has this exact example. Also expressible as `{0,5}` instead of `{limit 5}`.

---

### Q2 — Show `#LOGO_ARTICLE` conditionally, fall back to `#LOGO_RUBRIQUE` if empty

**Option A — simplest:** Use `#LOGO_ARTICLE_RUBRIQUE`, a built-in balise that automatically falls back to the rubrique logo when the article has none.

```html
[(#LOGO_ARTICLE_RUBRIQUE)]
```

**Option B — explicit sinon chain:** Useful when you need different sizing or classes for each case.

```html
[(#LOGO_ARTICLE|sinon{[(#LOGO_RUBRIQUE)]})]
```

Source: `SKILL.md` documents both `#LOGO_ARTICLE` and `#LOGO_ARTICLE_RUBRIQUE`; `references/filtres.md` documents `sinon`.

---

### Q3 — Critère for pagination + balise for page links

- **Critère:** `{pagination N}` — activates paginated output of N items per page
- **Balise:** `#PAGINATION` — renders previous/next page navigation links

```html
<BOUCLE_arts(ARTICLES){id_rubrique}{par date}{inverse}{pagination 10}>
  <li><a href="#URL_ARTICLE">#TITRE</a></li>
</BOUCLE_arts>
<BB_arts>
[(#PAGINATION)]
</BB_arts>
```

`#PAGINATION` must be placed inside the post-section (`<BB_name>`) or after the loop. It has no effect without a `{pagination N}` critère on the same boucle.

Source: `SKILL.md` Key Critères table + Key Balises table.

---

### Q4 — Recursive BOUCLE for full rubrique tree as nested `<ul>`

```html
<B_rubs>
<ul>

<BOUCLE_rubs(RUBRIQUES){id_parent}{par num titre, titre}>
  <li>
    <a href="#URL_RUBRIQUE">#TITRE</a>
    <BOUCLE_sous(BOUCLE_rubs) />
  </li>
</BOUCLE_rubs>

</ul>
</B_rubs>
```

- `<ul>` et `</ul>` sont **dans le même bloc `<B_rubs>...</B_rubs>`** — ils n'apparaissent que si la boucle a des résultats.
- First call: `{id_parent}` defaults to 0 → top-level rubriques.
- `<BOUCLE_sous(BOUCLE_rubs) />` recursively calls the outer loop with the current rubrique's `id_rubrique` as the new `id_parent`.
- Recursion stops automatically when a rubrique has no children.

Source: `references/boucles.md` — Recursive BOUCLEs section.

---

### Q5 — How `{doublons}` works, with featured + remaining example

`{doublons}` marks every item output by a boucle as "already seen." A second boucle that also carries `{doublons}` will automatically skip those items. Both boucles must use the same doublons namespace (default: unnamed).

```html
<!-- Step 1: pick the featured article — marks it as a doublon -->
<BOUCLE_vedette(ARTICLES){id_rubrique}{par date}{inverse}{limit 1}{doublons}>
  <div class="featured">
    [(#LOGO_ARTICLE_RUBRIQUE|image_reduire{600})]
    <h2><a href="#URL_ARTICLE">#TITRE</a></h2>
    [(#DESCRIPTIF)]
  </div>
</BOUCLE_vedette>

<!-- Step 2: remaining articles — {doublons} skips the featured one -->
<BOUCLE_reste(ARTICLES){id_rubrique}{par date}{inverse}{doublons}>
  <p><a href="#URL_ARTICLE">#TITRE</a></p>
</BOUCLE_reste>
```

Named doublons (`{doublons rouge}`, `{doublons bleu}`) maintain separate exclusion sets.

Source: `references/balises-criteres.md` — Doublons section.

---

### Q6 — INCLURE a fragment with AJAX reload

```html
<!-- In the parent squelette -->
<INCLURE{fond=inc-liste-articles,env}{ajax} />
```

**How it works:**
1. SPIP wraps the output in `<div id="spip_ancre_NNN">…</div>`
2. SPIP's JavaScript intercepts links and form submissions inside that div
3. Only the fond's HTML is fetched and replaced — no full page reload

**Requirements:**
- SPIP's JS must be loaded on the page (included by default in SPIP 4.x distributions via `spip_javascripts` plugin)
- The fragment must be a self-contained `.html` squelette file
- Same-origin only (no cross-domain AJAX)

**With custom container ID:**
```html
<INCLURE{fond=inc-recherche,env}{ajax id=zone-resultats} />
```

Source: `references/avance.md` — AJAX section.

---

### Q7 — `image_reduire` then `image_recadre` on `#LOGO_RUBRIQUE`

```html
[(#LOGO_RUBRIQUE|image_reduire{300}|image_recadre{300,200})]
```

- `image_reduire{300}` — resize to max 300px wide, preserving aspect ratio (never upscales)
- `image_recadre{300,200}` — crop to exactly 300×200px, anchored center (default)

**Why this order matters:** If the source image is smaller than 300×200, applying `image_recadre` directly would upscale it (blurry result). `image_reduire` first ensures the image is large enough before cropping.

For explicit center anchor:
```html
[(#LOGO_RUBRIQUE|image_reduire{300}|image_recadre{300,200,'center','center'})]
```

Source: `references/filtres.md` — image_reduire and image_recadre sections; order gotcha explicitly documented.

---

### Q8 — Balise for SPIP login form + placement

The balise is `#FORMULAIRE_LOGIN`. It renders SPIP's built-in login/logout form — no PHP, no configuration needed.

```html
<!-- In a sidebar or header -->
#FORMULAIRE_LOGIN

<!-- On a dedicated login page (login.html) -->
<div class="login-page">
  <h1>Connexion</h1>
  #FORMULAIRE_LOGIN
</div>
```

`#FORMULAIRE_LOGIN` can be placed anywhere in any squelette. It automatically shows:
- A login form when the visitor is not logged in
- A logout link when the visitor is already authenticated

Source: `references/avance.md` — Native Formulaires table.

---

## Verdict

**PASS — 8/8**

All 8 questions answered correctly and completely from the skill files. The skill provides:
- Exact BOUCLE syntax and common patterns in `SKILL.md` and `boucles.md`
- Complete critères catalog including `{doublons}` and `{pagination}` in `balises-criteres.md`
- Image filter signatures with the critical `image_reduire`→`image_recadre` order documented in `filtres.md`
- INCLURE/AJAX and formulaires reference in `avance.md`

The skill meets the ≥7/8 passing threshold with a perfect score.

---

## Legacy spip subset (merged from spip-green.md)

This section preserves the original 4-question GREEN verification from `spip` so the file can be deleted without losing verification coverage.

### L1: BOUCLE listing last 5 published articles from rubrique #3, date descending

```html
<BOUCLE_articles(ARTICLES){id_rubrique=3}{statut=publie}{par date}{inverse}{limit 5}>
  <a href="#URL_ARTICLE">#TITRE</a> - #DATE
</BOUCLE_articles>
```

### L2: Display #TITRE only when not empty

```html
[(#TITRE)]
```

### L3: Pagination critere + page links balise

- Critere: `{pagination 10}` (or any N)
- Balise: `[(#PAGINATION)]` placed after the closing BOUCLE tag

### L4: image_reduire at 300px on #LOGO_ARTICLE

```html
[(#LOGO_ARTICLE|image_reduire{300})]
```

### Legacy verification table

| Expected | Pass? |
|---|---|
| BOUCLE uses `(ARTICLES){id_rubrique=3}{statut=publie}{par date}{inverse}{limit 5}` | PASS |
| Optional tag uses `[(#TITRE)]` syntax | PASS |
| Mentions both `{pagination N}` and `[(#PAGINATION)]` | PASS |
| Filter syntax is `[(#LOGO_ARTICLE|image_reduire{300})]` | PASS |

Legacy subset verdict: PASS (4/4).
