# Exemples Reference

Copy-paste ready SPIP 4.1 template patterns. Each snippet is self-contained — adapt IDs and class names to your project.

---

## 1. Breadcrumb (fil d'Ariane)

Displays the ancestor rubrique chain from the site root to the current page using `BOUCLE HIERARCHIE`. The current node is always included.

```html
<nav aria-label="Fil d'Ariane">
  <ol>
    <BOUCLE_fil(HIERARCHIE){id_rubrique}>
    <li>
      [(#URL_RUBRIQUE|strlen|>{0})<a href="#URL_RUBRIQUE">#TITRE</a>]
      [<span>(#TITRE)</span>]
    </li>
    </BOUCLE_fil>
  </ol>
</nav>
```

> **Gotcha:** `BOUCLE HIERARCHIE` always includes the current node last. Use `{id_article}` or `{id_rubrique}` depending on context. When inside a BOUCLE_ARTICLES, the context propagates automatically.

---

## 2. Navigation menu (arborescence récursive)

Recursive rubrique tree rendered as nested `<ul>`. Works at any depth.

```html
<nav>
  <ul>
    <BOUCLE_menu(RUBRIQUES){racine}{par titre}>
    <li>
      <a href="#URL_RUBRIQUE">#TITRE</a>
      <ul>
        <BOUCLE_sous_menu(RUBRIQUES){id_parent}{par titre}>
        <li><a href="#URL_RUBRIQUE">#TITRE</a></li>
        </BOUCLE_sous_menu>
      </ul>
    </li>
    </BOUCLE_menu>
  </ul>
</nav>
```

For a fully recursive tree using a recursive BOUCLE (any depth):

```html
<ul>
  <BOUCLE_arbre(RUBRIQUES){racine}{par titre}>
  <li>
    <a href="#URL_RUBRIQUE">#TITRE</a>
    <INCLURE{fond=inclure/sous-rubriques}{id_rubrique=#ID_RUBRIQUE} />
  </li>
  </BOUCLE_arbre>
</ul>
```

`inclure/sous-rubriques.html`:

```html
<ul>
  <BOUCLE_enfants(RUBRIQUES){id_parent}{par titre}>
  <li>
    <a href="#URL_RUBRIQUE">#TITRE</a>
    <INCLURE{fond=inclure/sous-rubriques}{id_rubrique=#ID_RUBRIQUE} />
  </li>
  </BOUCLE_enfants>
</ul>
```

---

## 3. Liste d'articles avec pagination

Article list with pre/post sections and empty-state fallback. The `<B_liste>` pre-section only renders when results exist; `<//B_liste>` renders when there are zero results.

```html
<B_liste>
<section class="articles">
  <h2>Articles</h2>
  <ul>

<BOUCLE_liste(ARTICLES){id_rubrique}{par date}{inverse}{pagination 10}>
    <li>
      <a href="#URL_ARTICLE">#TITRE</a>
      <time datetime="[(#DATE|date_iso)]">[(#DATE|affdate_court)]</time>
      [(#DESCRIPTIF|strlen|>{0})<p>#DESCRIPTIF</p>]
    </li>
</BOUCLE_liste>

  </ul>
  #PAGINATION
</section>
</B_liste>

<p>Aucun article dans cette rubrique.</p>
<//B_liste>
```

---

## 4. Article mis en avant + liste restante (doublons)

Feature the first (most recent) article prominently, then list the rest — The criteria `{doublons}` excludes any article already processed in a previous BOUCLE with the same criteria.

```html
<!-- Article mis en avant -->
<BOUCLE_une(ARTICLES){id_rubrique}{par date}{inverse}{limit 1}{doublons}>
<article class="featured">
  [(#LOGO_ARTICLE_RUBRIQUE|image_reduire{600})]
  <h2><a href="#URL_ARTICLE">#TITRE</a></h2>
  [<p class="chapo">(#CHAPO)</p>]
</article>
</BOUCLE_une>

<!-- Articles restants (exclus du premier bloc) -->
<ul class="articles-rest">
  <BOUCLE_reste(ARTICLES){id_rubrique}{par date}{inverse}{doublons}{limit 9}>
  <li>
    <a href="#URL_ARTICLE">#TITRE</a>
    <time>[(#DATE|affdate_court)]</time>
  </li>
  </BOUCLE_reste>
</ul>
```

> **Gotcha:** `{doublons}` only works within the same squelette rendering pass. Doublons tracked in a parent squelette are not automatically available inside an included squelette.
>
> **Workaround:** pass `doublons` on the include call, for example: `<INCLURE{fond=mapage, doublons}>`.

---

## 5. Galerie d'images (DOCUMENTS)

Displays all images attached to the current article as a gallery.

```html
<BOUCLE_galerie(DOCUMENTS){id_article}{extension IN jpg,jpeg,png,gif,webp}{par num titre}>
<figure>
  [(#LOGO_DOCUMENT|image_reduire{800,600})]
  [<figcaption>(#TITRE)</figcaption>]
</figure>
</BOUCLE_galerie>
```

For thumbnail grid linking to full size:

```html
<ul class="galerie">
  <BOUCLE_thumbs(DOCUMENTS){id_article}{extension IN jpg,jpeg,png,gif,webp}{par num titre}>
  <li>
    <a href="[(#URL_DOCUMENT)]">
      [(#LOGO_DOCUMENT|image_reduire{300}|image_recadre{300,200,center})]
    </a>
    [<span>(#TITRE)</span>]
  </li>
  </BOUCLE_thumbs>
</ul>
```

---

## 6. Articles liés par mot-clé

Shows articles sharing any mot-clé with the current article. Requires two nested BOUCLEs: first fetch the mots, then find articles tagged with each.

```html
<BOUCLE_mots(MOTS){id_article}>
<section class="related">
  <h3>Articles liés : [(#TITRE)]</h3>
  <ul>
    <BOUCLE_lies(ARTICLES){id_mot}{par date}{inverse}{limit 5}>
    <li><a href="#URL_ARTICLE">#TITRE</a></li>
    </BOUCLE_lies>
  </ul>
</section>
</BOUCLE_mots>
```

> **Gotcha:** The inner `BOUCLE_lies` inherits `{id_mot}` from the outer loop automatically. No need to pass it explicitly.

---

## 7. Archives par mois

Groups articles by publication month, newest first.

```html
<BOUCLE_annees(ARTICLES){id_rubrique}{par date}{inverse}{doublons annee}>
<h2>[(#DATE|annee)]</h2>
<ul>
  <BOUCLE_mois(ARTICLES){id_rubrique}{par date}{inverse}{annee}{doublons mois}>
  <li class="mois-header">[(#DATE|affdate{F Y})]
    <ul>
      <BOUCLE_articles_mois(ARTICLES){id_rubrique}{par date}{inverse}{annee}{mois}>
      <li><a href="#URL_ARTICLE">#TITRE</a> — <time>[(#DATE|affdate_jourcourt)]</time></li>
      </BOUCLE_articles_mois>
    </ul>
  </li>
  </BOUCLE_mois>
</ul>
</BOUCLE_annees>
```

---

## 8. Sélecteur de langue (traductions)

Lists all available translations of the current article. Use inside a BOUCLE_ARTICLES context.

```html
<BOUCLE_article_courant(ARTICLES){id_article}>
<nav class="lang-switcher">
  <ul>
    <BOUCLE_traductions(ARTICLES){id_trad=#ID_TRAD}{par langue}>
    <li[(#LANG|=={#ENV{lang}}| alors { class="active"})>
      <a href="#URL_ARTICLE" hreflang="#LANG">#LANG</a>
    </li>
    </BOUCLE_traductions>
  </ul>
</nav>
</BOUCLE_article_courant>
```

> **Gotcha:** `{id_trad}` matches all articles sharing the same translation group, including the current one. Filter out the current article with `{id_article!=#ID_ARTICLE}` if you want only the other languages.

---

## 9. Fragment INCLURE rechargeable via AJAX

A fragment that can be reloaded without a full page refresh. SPIP JS must be included in the page (via `#CHEMIN{javascript/jquery.js}` or equivalent).

Main squelette:

```html
<!-- Load SPIP's JavaScript (required for ajax) -->
<script src="#CHEMIN{javascript/jquery.js}"></script>
<script src="#CHEMIN{javascript/ajax.js}"></script>

<!-- AJAX-reloadable zone -->
<INCLURE{fond=inclure/liste-articles}{id_rubrique=#ID_RUBRIQUE}{ajax} />
```

`inclure/liste-articles.html`:

```html
<div id="liste-articles">
  <BOUCLE_ajax_liste(ARTICLES){id_rubrique}{par date}{inverse}{pagination 5}>
  <article>
    <h2><a href="#URL_ARTICLE">#TITRE</a></h2>
    <time>[(#DATE|affdate_court)]</time>
  </article>
  </BOUCLE_ajax_liste>
  #PAGINATION
</div>
```

> **Gotcha:** `{ajax}` on `INCLURE` requires the fragment to be in a separate file. The fragment must not include `<html>`, `<head>`, or `<body>` tags — it is inserted into the DOM directly.

---

## 10. Affichage conditionnel (connecté vs. anonyme)

Shows different content depending on whether the visitor is logged in. Uses `BOUCLE CONDITION` with `#SESSION`.

```html
<!-- For logged-in visitors -->
<BOUCLE_connecte(CONDITION){si #SESSION{id_auteur}}>
<div class="member-area">
  <p>Bienvenue, [(#SESSION{nom})] !</p>
  <a href="#URL_LOGOUT">Se déconnecter</a>
</div>
</BOUCLE_connecte>
</B_connecte>

<!-- For anonymous visitors -->
<div class="visitor-area">
  #FORMULAIRE_LOGIN
  <p>Pas encore inscrit ? <a href="#URL_INSCRIPTION">Créer un compte</a></p>
</div>
<//B_connecte>
```

For finer-grained role checks (e.g., only admins):

```html
<BOUCLE_admin(CONDITION){si #SESSION{statut}|=={0minirezo}}>
<p>Vous êtes administrateur.</p>
</BOUCLE_admin>
```

> **Gotcha:** `#SESSION{statut}` values are SPIP internal codes: `0minirezo` = admin, `1comite` = editor, `6forum` = registered subscriber. Test exact string values with `|=={value}`.
