# Convention des fixtures avec erreurs (`*WithError*.html`)

## Objectif

Un fixture nommé `*WithError*.html` (ex. `paginationWithError.html`) est un squelette SPIP **intentionnellement incorrect**.
Les tests PHPUnit qui l'utilisent décrivent le comportement **correct attendu** et **échouent** tant que le fixture n'est pas corrigé — phase TDD RED.

Ce pattern permet de :
- Valider qu'un test détecte bien une erreur donnée
- Documenter précisément ce que le squelette devrait faire vs. ce qu'il fait
- Générer des tests exécutables à partir du commentaire structuré

---

## Règle de nommage

| Fichier | Contenu |
|---|---|
| `*WithError*.html` | Squelette intentionnellement incorrect — **ne pas corriger** |
| `*.html` (sans "WithError") | Squelette correct (vert) |

> Note : les fixtures créés avant l'adoption de cette convention peuvent utiliser `*Errone*.html` (ex. `boucleErronnee.html`). La convention `*WithError*` s'applique aux nouveaux fixtures.

---

## Format du commentaire structuré

Tout fixture `*WithError*.html` doit commencer par un bloc `<!--spip-test ... -->` contenant un YAML.

```html
<!--spip-test
spec: >
  Description de ce que le squelette DEVRAIT faire (comportement attendu).
  Multi-lignes possible avec le bloc "> ".
errors:
  - id: <ID_ERREUR>
    location: "<où dans le squelette>"
    found: "<ce qui est présent>"      # optionnel si found = absence
    expected: "<ce qui devrait être>"
    symptom: "<ce que l'utilisateur voit comme anomalie>"

  - id: <ID_ERREUR_2>
    ...
-->
```

### Champs obligatoires par erreur

| Champ | Rôle |
|---|---|
| `id` | Type d'erreur (voir taxonomie ci-dessous) |
| `location` | Où dans le squelette (balise, critère, section) |
| `expected` | Ce qui devrait être là |
| `symptom` | Ce que le développeur observe comme anomalie |

### Champ optionnel

| Champ | Rôle |
|---|---|
| `found` | Ce qui est réellement présent (utile pour erreurs de syntaxe) |

---

## Taxonomie des `id` d'erreur SPIP

| id | Signification | Exemple |
|---|---|---|
| `CRITERE_MANQUANT` | Critère absent dans la BOUCLE | `{statut=publie}` ou `{par date}{inverse}` oublié |
| `CRITERE_INVALIDE` | Syntaxe de critère incorrecte | `{limit 5}` au lieu de `{0,5}` |
| `BALISE_INCONNUE` | Balise mal orthographiée ou inexistante | `#TITTRE` au lieu de `#TITRE` |
| `DOUBLE_DIESE` | `##BALISE` au lieu de `#BALISE` | `##DATE` rend le texte littéral `#DATE` |
| `SECTION_ALTERNATIVE_MAL_POSITIONNEE` | Texte de fallback hors de la section alternative | Texte après `<//B_boucle>` au lieu d'avant |
| `SECTION_ALTERNATIVE_ABSENTE` | Aucune section alternative définie | Ni `</B_boucle>` ni `<//B_boucle>` présents |
| `LISTE_NON_STRUCTUREE` | Éléments de liste sans élément parent | `<li>` sans `<ul>` parent |

---

## Syntaxe correcte de la section alternative SPIP

```html
<B_boucle>
  <!-- contenu affiché quand la boucle a des résultats -->
  <ul>
  <BOUCLE_boucle(TABLE){critères}>
    <li><!-- rendu de chaque ligne --></li>
  </BOUCLE_boucle>
  </ul>
</B_boucle>
Texte affiché quand la boucle est vide.
<//B_boucle>
```

Ordre impératif : `</B_boucle>` → texte alternatif → `<//B_boucle>`.
Si le texte est placé après `<//B_boucle>`, il s'affiche **inconditionnellement**.

---

## Exemple complet — `paginationWithError.html`

```html
<!--spip-test
spec: >
  Affiche les articles publiés de la rubrique 4, par pages de 5,
  du plus récent au plus ancien, dans une liste HTML structurée (<ul><li>).
  Affiche les liens de navigation entre pages via #PAGINATION.
  Affiche "Aucun article publié dans cette rubrique." si aucun article publié.
errors:
  - id: CRITERE_MANQUANT
    location: "BOUCLE_arts"
    found: "{id_rubrique}{par date}{inverse}{pagination 10}"
    expected: "{id_rubrique=4}{statut=publie}{par date}{inverse}{pagination 5}"
    symptom: "Affiche les articles non publiés (brouillons, corbeille)"

  - id: LISTE_NON_STRUCTUREE
    location: "contenu de BOUCLE_arts"
    found: "<li>...</li> sans parent <ul>"
    expected: "<ul> encadrant tous les <li>"
    symptom: "HTML invalide : <li> sans élément <ul> parent"

  - id: SECTION_ALTERNATIVE_ABSENTE
    location: "après </BOUCLE_arts>"
    expected: "</B_arts>Aucun article publié dans cette rubrique.<//B_arts>"
    symptom: "Aucun message affiché quand la rubrique ne contient pas d'articles publiés"
-->
```

---

## Prompt Claude Code — générer le commentaire structuré

Quand un utilisateur fournit un squelette SPIP et demande d'analyser ses erreurs, utiliser ce prompt :

> Analyse ce squelette SPIP. Identifie :
> 1. La **spec** — ce que ce squelette est supposé faire (comportement attendu)
> 2. Les **erreurs** présentes — balises incorrectes, critères manquants, syntaxes invalides, listes non structurées, sections alternatives absentes ou mal positionnées
>
> Génère le bloc de commentaire `<!--spip-test ... -->` complet selon la convention du skill spip-testing (`references/fixture-with-errors-convention.md`).
> Utilise uniquement les `id` de la taxonomie définie : `CRITERE_MANQUANT`, `CRITERE_INVALIDE`, `BALISE_INCONNUE`, `DOUBLE_DIESE`, `SECTION_ALTERNATIVE_MAL_POSITIONNEE`, `SECTION_ALTERNATIVE_ABSENTE`, `LISTE_NON_STRUCTUREE`.

---

## Workflow de génération de tests depuis le commentaire

Un agent générateur (subagent ou script) lit le bloc `<!--spip-test ... -->` et crée un fichier PHPUnit `*WithErrorTest.php` :

1. **`spec`** → doc-block de la classe et des méthodes de test
2. Chaque `error` → une méthode de test `testXxx()` qui :
   - Charge le fixture via `Templating::fromString()->render(file_get_contents($fixture), $contexte)`
   - Décrit le **comportement correct attendu** (pas l'erreur)
   - **Échoue** tant que le fixture est incorrect (TDD RED)

### Correspondance error.id → assertion PHPUnit

| id | Assertion typique |
|---|---|
| `CRITERE_MANQUANT` (`{statut=publie}`) | `assertStringNotContainsString($draftTitle, $rendered)` |
| `CRITERE_MANQUANT` (tri) | `assertEqualsCode($correctOrder, $fixedBoucle)` sur les IDs retournés |
| `BALISE_INCONNUE` | `assertStringNotContainsString('href=""', $rendered)` |
| `DOUBLE_DIESE` | `assertStringNotContainsString('#DATE', $rendered)` |
| `SECTION_ALTERNATIVE_MAL_POSITIONNEE` | `assertStringNotContainsString($fallbackText, $renderedWithData)` |
| `SECTION_ALTERNATIVE_ABSENTE` | `assertStringContainsString($fallbackText, $renderedEmpty)` |
| `LISTE_NON_STRUCTUREE` | `assertStringContainsString('<ul>', $rendered)` |

### Règles de teardown pour tests avec fixtures de fichier

Quand plusieurs classes de test partagent le même fixture sur la même `id_rubrique`, coordonner les insertions :
- Utiliser une `id_rubrique` fixe et forcer son existence avec `sql_delete` + `sql_insertq` dans `setUpBeforeClass`
- Nettoyer dans `tearDownAfterClass` : articles avant rubriques (FK)
- Utiliser des `id_rubrique` différents par fixture pour éviter les conflits entre classes de test
