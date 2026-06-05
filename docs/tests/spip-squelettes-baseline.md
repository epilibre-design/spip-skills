# SPIP Squelettes Skill — Baseline Test (RED)

Date: 2026-04-30

## Subagent Answers

### Q1 — Article list

```html
<BOUCLE_articles(ARTICLES){id_rubrique=3}{statut=publie}{par date}{inverse}{limit 5}>
  <a href="#URL_ARTICLE">#TITRE</a> — #DATE
</BOUCLE_articles>
```

### Q2 — Conditional logo with fallback

Use the built-in balise `#LOGO_ARTICLE_RUBRIQUE` which automatically falls back to the rubrique logo when the article has no logo:

```html
[(#LOGO_ARTICLE_RUBRIQUE)]
```

Alternatively, use `|sinon` filter to chain manually:

```html
[(#LOGO_ARTICLE|sinon{[(#LOGO_RUBRIQUE)]})]
```

### Q3 — Pagination

Critère: `{pagination N}` (N = items per page)  
Balise: `[(#PAGINATION)]` placed outside the BOUCLE body.

```html
<BOUCLE_arts(ARTICLES){id_rubrique}{pagination 10}>
  <a href="#URL_ARTICLE">#TITRE</a>
</BOUCLE_arts>
[(#PAGINATION)]
```

### Q4 — Recursive rubrique tree

```html
<ul>
<BOUCLE_arbre(RUBRIQUES){id_parent}{tout}>
  <li>
    <a href="#URL_RUBRIQUE">#TITRE</a>
    <ul><BOUCLE_arbre></ul>
  </li>
</BOUCLE_arbre>
</ul>
```

The `<BOUCLE_arbre>` self-reference triggers the recursive call with the current row's `id_rubrique` as the new `id_parent`.

### Q5 — {doublons}

`{doublons}` skips any item (by primary key) already output by a previous BOUCLE of the same type on the same page render.

```html
<!-- Featured: most recent article -->
<BOUCLE_vedette(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{limit 1}>
  <article class="featured">
    <h2><a href="#URL_ARTICLE">#TITRE</a></h2>
    [(#DESCRIPTIF)]
  </article>
</BOUCLE_vedette>

<!-- Remaining: skip the featured article automatically -->
<ul>
<BOUCLE_reste(ARTICLES){id_rubrique}{statut=publie}{doublons}{par date}{inverse}{limit 9}>
  <li><a href="#URL_ARTICLE">#TITRE</a></li>
</BOUCLE_reste>
</ul>
```

### Q6 — AJAX fragment

Use `{ajax}` on the `<INCLURE>` tag to make the block reload asynchronously. The inner BOUCLE also needs `{ajax}` on the pagination critère.

```html
<!-- In main squelette -->
<INCLURE{fond=fragments/article-list, id_rubrique=#ENV{id_rubrique}}{ajax}>

<!-- squelettes/fragments/article-list.html -->
<BOUCLE_arts(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{pagination 10}{ajax}>
  <a href="#URL_ARTICLE">#TITRE</a>
</BOUCLE_arts>
[(#PAGINATION)]
```

SPIP's JS (loaded by default) handles the XHR swap. The fragment URL changes on pagination clicks and only the block reloads.

### Q7 — Image filter chain

```html
[(#LOGO_RUBRIQUE|image_reduire{300,0}|image_recadre{300,200,center})]
```

- `image_reduire{300,0}` — resize to max 300px wide, unconstrained height (0 = no constraint)
- `image_recadre{300,200,center}` — crop to exactly 300×200, anchored at center

### Q8 — Login form

`#FORMULAIRE_LOGIN` renders the SPIP login form. Place it anywhere in a squelette:

```html
[(#FORMULAIRE_LOGIN)]
```

Typical placement: inside an `<INCLURE>` fragment in the header or sidebar, or conditionally using BOUCLE CONDITION to show only when the visitor is not logged in.

## Self-Assessment

| Q | SPIP syntax used correctly? | Notes |
|---|---|---|
| 1 | Y | Correct BOUCLE syntax, critères, ordering |
| 2 | Y | `#LOGO_ARTICLE_RUBRIQUE` is canonical; `\|sinon` approach also valid |
| 3 | Y | `{pagination N}` + `[(#PAGINATION)]` correct |
| 4 | Y | Recursive self-reference `<BOUCLE_arbre>` correct |
| 5 | Y | `{doublons}` mechanism and example correct |
| 6 | Y | `{ajax}` on INCLURE and inner BOUCLE correct |
| 7 | Y | Filter chain syntax and argument order correct |
| 8 | Y | `#FORMULAIRE_LOGIN` correct |

**Score: 8/8**

---

## Legacy spip subset (merged from spip-baseline.md)

This section preserves the original 4-question baseline from `spip` so the file can be deleted without losing test history.

### L1 - BOUCLE listing last 5 published articles from rubrique #3, sorted by date descending

```html
<BOUCLE_articles(ARTICLES){id_rubrique=3}{statut=publie}{par date}{inverse}{limit 5}>
  <a href="#URL_ARTICLE">#TITRE</a>
</BOUCLE_articles>
```

Assessment: Correct BOUCLE syntax, correct table alias, correct criteres, correct ordering.

### L2 - Display #TITRE only when not empty

```html
[(#TITRE)]
```

Assessment: Correct optional balise syntax.

### L3 - Critere for pagination and balise for page links

- Critere: `{pagination 10}`
- Balise: `[(#PAGINATION)]`

Assessment: Both pagination critere and balise were present.

### L4 - Apply image_reduire filter (max 300px) to #LOGO_ARTICLE

```html
[(#LOGO_ARTICLE|image_reduire{300})]
```

Assessment: Correct filter syntax.

### Legacy summary

Legacy subset score: 4/4.
