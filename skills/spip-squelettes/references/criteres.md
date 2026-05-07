# Balises & Critères Reference

---

## Critères

Critères control what a BOUCLE selects and how results are ordered. They are placed in `{…}` braces after the table name. Multiple critères combine with AND logic.

```html
<BOUCLE_a(ARTICLES){id_rubrique}{statut=publie}{par date}{inverse}{pagination 10}>
```

---

### Filtering Critères

#### By identifier

| Critère | Effect |
|---|---|
| `{id_article}` | Article whose `id_article` matches the context (URL or parent loop) |
| `{id_article=5}` | Exactly article #5 |
| `{id_rubrique}` | Articles in the rubrique matching context |
| `{id_rubrique=3}` | Articles in rubrique #3 |
| `{id_secteur}` | All articles in a top-level secteur (rubrique at root) |
| `{id_secteur=2}` | Articles in secteur #2 |
| `{id_mot}` | Articles tagged with a given mot-clé |
| `{id_parent}` | Sub-rubriques of the parent rubrique in context |
| `{racine}` | Top-level rubriques only (equivalent to `{id_parent=0}`) |

```html
<!-- All articles in rubrique 3 -->
<BOUCLE_arts(ARTICLES){id_rubrique=3}>
  <a href="#URL_ARTICLE">#TITRE</a>
</BOUCLE_arts>
```

**Gotcha:** `{id_rubrique}` without a value reads `id_rubrique` from the current context (URL or enclosing loop). If neither provides one, it returns all articles. Always verify the context is what you expect.

---

#### Scope Critères

| Critère | Effect |
|---|---|
| `{branche}` | Articles in the current rubrique AND all its sub-rubriques |
| `{branche?}` | Same as `{branche}` but optional — applies only if a rubrique is in context; otherwise returns all |
| `{!branche}` | Articles NOT in the current rubrique or its sub-rubriques |

```html
<!-- All articles in current section tree -->
<BOUCLE_section(ARTICLES){branche}>
  #TITRE<br>
</BOUCLE_section>
```

**Gotcha:** `{branche}` is expensive on large sites because SPIP must compute the full subtree of rubrique IDs. Use sparingly; prefer `{id_secteur}` when you only need the top-level scope.

---

#### Statut Critère

| Critère | Effect |
|---|---|
| `{statut=publie}` | Published articles only (default for most boucles) |
| `{statut=prepa}` | Draft articles (private space only) |
| `{statut=prop}` | Articles submitted for validation |
| `{tout}` | All statuses — published, draft, submitted, refused |

```html
<!-- All articles including drafts (admin view) -->
<BOUCLE_all(ARTICLES){tout}>
  [#STATUT] - #TITRE<br>
</BOUCLE_all>
```

**Gotcha:** By default, BOUCLE ARTICLES only returns `statut=publie` articles on the public site. You only need to write `{statut=publie}` explicitly if you have mixed contexts. `{tout}` is needed to show drafts.

---

#### Language Critères

| Critère | Effect |
|---|---|
| `{lang}` | Articles in the current site language |
| `{lang_select}` | Articles in the language selected by the visitor |
| `{lang=fr}` | Articles in French specifically |

```html
<!-- Articles in visitor's chosen language -->
<BOUCLE_traduits(ARTICLES){id_rubrique}{lang_select}>
  #TITRE
</BOUCLE_traduits>
```

---

#### Doublons (deduplication)

`{doublons}` prevents an item already displayed by another `{doublons}` boucle from appearing again. Both boucles must carry `{doublons}`.

When using `<INCLURE>`, doublons are not transmitted automatically to the included squelette. Pass `doublons` explicitly on the include call, for example: `<INCLURE{fond=mapage, doublons}>`.

```html
<!-- Featured article -->
<BOUCLE_vedette(ARTICLES){id_rubrique}{par date}{inverse}{limit 1}{doublons}>
<div class="featured"><h2>#TITRE</h2>#DESCRIPTIF</div>
</BOUCLE_vedette>

<!-- Remaining articles (excludes the featured one) -->
<BOUCLE_reste(ARTICLES){id_rubrique}{par date}{inverse}{doublons}>
<p><a href="#URL_ARTICLE">#TITRE</a></p>
</BOUCLE_reste>
```

Named doublons: `{doublons rouge}` and `{doublons bleu}` are independent sets — articles excluded from one set are not excluded from the other.

`{exclus}` is the local equivalent: it excludes the current article (the one whose page is being rendered) from the boucle result.

---

#### Recherche (full-text search)

| Critère | Effect |
|---|---|
| `{recherche}` | Filter to articles matching the search query in `?recherche=` URL param |

```html
<!-- Search results page -->
<BOUCLE_resultats(ARTICLES){recherche}{par points}{inverse}>
  <a href="#URL_ARTICLE">#TITRE</a> (#POINTS points)<br>
</BOUCLE_resultats>
<//B_resultats>Aucun résultat.

Use `#POINTS` (the relevance score) to sort results. Only valid inside a `{recherche}` boucle.

---

#### Jointure

`{jointure table}` forces a SQL JOIN with another table when the automatic join resolution isn't enough.

```html
<!-- Articles that have at least one document -->
<BOUCLE_avec_docs(ARTICLES){id_rubrique}{jointure documents}>
  #TITRE
</BOUCLE_avec_docs>
```

---

#### Date Critères

| Critère | Effect |
|---|---|
| `{age<30}` | Published less than 30 days ago |
| `{age>365}` | Published more than 365 days ago |
| `{age<0}` | Post-dated articles (future publication date) |
| `{age_redac<30}` | Authored (first redaction) less than 30 days ago |
| `{annee=2024}` | Published in year 2024 |
| `{mois=1}` | Published in January (of any year) |
| `{mois_redac=6}` | Authored in June |
| `{annee<=2000}` | Published before end of year 2000 |

```html
<!-- Articles from this month -->
<BOUCLE_recents(ARTICLES){age<31}{par date}{inverse}>
  #TITRE - [(#DATE|affdate)]<br>
</BOUCLE_recents>
```

```html
<!-- Articles published in 2023 -->
<BOUCLE_archive(ARTICLES){annee=2023}{par date}>
  #TITRE<br>
</BOUCLE_archive>
```

---

### Sorting Critères

| Critère | Effect |
|---|---|
| `{par titre}` | Alphabetical by title |
| `{par date}` | Chronological (oldest first) |
| `{par date}{inverse}` | Newest first |
| `{par date_redac}` | By authoring date |
| `{par points}` | By search relevance score (only with `{recherche}`) |
| `{par hasard}` | Random order |
| `{inverse}` | Reverse any preceding `{par …}` sort |

Multiple `{par …}` critères can be combined for secondary sort.

```html
<!-- Random featured article from rubrique 5 -->
<BOUCLE_rand(ARTICLES){id_rubrique=5}{par hasard}{limit 1}>
  <h2>#TITRE</h2>
</BOUCLE_rand>
```

---

### Pagination & Limit Critères

| Critère | Effect |
|---|---|
| `{limit N}` | At most N results |
| `{limit A,B}` | Skip A results, then show B (e.g., `{limit 3,5}` = results 4–8) |
| `{pagination N}` | N results per page; enables `#PAGINATION` and `debut_X` URL param |
| `{0, n-10}` | All results except the last 10 |
| `{n-5, 5}` | The last 5 results |

**Gotcha:** `{a, n}`, `{a, n-b}` and `{n-a, b}` do NOT work together with `{pagination}` — combine them and you get inconsistent results.

```html
<!-- Paginated list of 10 articles per page -->
<B_articles>
<ul>

<BOUCLE_articles(ARTICLES){id_rubrique}{par date}{inverse}{pagination 10}>
  <li><a href="#URL_ARTICLE">#TITRE</a></li>
</BOUCLE_articles>

</ul>
[(#PAGINATION)]
</B_articles>

<p>Aucun article dans cette rubrique.</p>
<//B_articles>
```

The `debut_boucle` URL parameter (auto-generated by `#PAGINATION`) controls the offset. Its exact name depends on the boucle name: `debut_articles` for `BOUCLE_articles`.


---

## Common Patterns & Gotchas Summary

| Issue | Solution |
|---|---|
| `{pagination}` + `{0, n-5}` broken | Never combine `{pagination}` with `{n-a, b}` variants |
| `#ENV{key}` not available in INCLURE | Pass explicitly: `<INCLURE{fond=frag, key=#ENV{key}}>` |
| RUBRIQUES boucle skips empty rubriques | Add `{tout}` to include empty ones |
| `{doublons}` not working | Both boucles must carry `{doublons}`; with `<INCLURE>`, pass `doublons` explicitly: `<INCLURE{fond=frag, doublons}>` |
