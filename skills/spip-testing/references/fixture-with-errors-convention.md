# Convention des fixtures erronnées (`*Errone*.html`)

## Objectif

Un fixture nommé `*Errone*.html` (ex. `boucleErronnee.html`) est un squelette SPIP **intentionnellement incorrect**.
Les tests PHPUnit qui l'utilisent décrivent le comportement **correct attendu** et **échouent** tant que le fixture n'est pas corrigé — phase TDD RED.

Ce pattern permet de :
- Valider qu'un test détecte bien une erreur donnée
- Documenter précisément ce que le squelette devrait faire vs. ce qu'il fait
- Générer des tests exécutables à partir du commentaire structuré

---

## Règle de nommage

| Fichier | Contenu |
|---|---|
| `*Errone*.html` | Squelette intentionnellement incorrect — **ne pas corriger** |
| `*.html` (sans "Errone") | Squelette correct (vert) |

---

## Format du commentaire structuré

Tout fixture `*Errone*.html` doit commencer par un bloc `<!--spip-test ... -->` contenant un YAML.

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
| `CRITERE_MANQUANT` | Critère absent dans la BOUCLE | `{par date}{inverse}` oublié |
| `CRITERE_INVALIDE` | Syntaxe de critère incorrecte | `{limit 5}` au lieu de `{0,5}` |
| `BALISE_INCONNUE` | Balise mal orthographiée ou inexistante | `#TITTRE` au lieu de `#TITRE` |
| `DOUBLE_DIESE` | `##BALISE` au lieu de `#BALISE` | `##DATE` rend le texte littéral `#DATE` |
| `SECTION_ALTERNATIVE_MAL_POSITIONNEE` | Texte de fallback hors de la section alternative | Texte après `<//B_boucle>` au lieu d'avant |

---

## Syntaxe correcte de la section alternative SPIP

```html
<B_boucle>
  <!-- contenu affiché quand la boucle a des résultats -->
  <BOUCLE_boucle(TABLE){critères}>
    <!-- rendu de chaque ligne -->
  </BOUCLE_boucle>
</B_boucle>
Texte affiché quand la boucle est vide.
<//B_boucle>
```

Ordre impératif : `</B_boucle>` → texte alternatif → `<//B_boucle>`.
Si le texte est placé après `<//B_boucle>`, il s'affiche **inconditionnellement**.

---

## Exemple complet — `boucleErronnee.html`

```html
<!--spip-test
spec: >
  Affiche les 5 derniers articles publiés de la rubrique 3,
  du plus récent au plus ancien (titre, lien, date).
  Affiche "Aucun article n'est publié dans cette rubrique."
  si aucun article publié dans la rubrique.
errors:
  - id: CRITERE_MANQUANT
    location: "BOUCLE_articles"
    found: "{id_rubrique=3}{statut=publie}{0,5}"
    expected: "{id_rubrique=3}{statut=publie}{par date}{inverse}{0,5}"
    symptom: "Articles affichés dans l'ordre d'insertion, pas du plus récent au plus ancien"

  - id: BALISE_INCONNUE
    location: "#UURL_ARTICLE"
    expected: "#URL_ARTICLE"
    symptom: "href vide dans les liens générés"

  - id: BALISE_INCONNUE
    location: "#TITTRE"
    expected: "#TITRE"
    symptom: "Texte du lien vide (balise inconnue)"

  - id: DOUBLE_DIESE
    location: "##DATE"
    expected: "#DATE"
    symptom: "Affiche le texte littéral '#DATE' au lieu de la date formatée"

  - id: SECTION_ALTERNATIVE_MAL_POSITIONNEE
    location: "Aucun article... placé après <//B_articles>"
    expected: "Texte alternatif entre </B_articles> et <//B_articles>"
    symptom: "Message 'Aucun article...' affiché inconditionnellement, même quand des articles existent"
-->
```

---

## Prompt Claude Code — générer le commentaire structuré

Quand un utilisateur fournit un squelette SPIP et demande d'analyser ses erreurs, utiliser ce prompt :

> Analyse ce squelette SPIP. Identifie :
> 1. La **spec** — ce que ce squelette est supposé faire (comportement attendu)
> 2. Les **erreurs** présentes — balises incorrectes, critères manquants, syntaxes invalides, sections alternatives mal positionnées
>
> Génère le bloc de commentaire `<!--spip-test ... -->` complet selon la convention du skill spip-testing (`references/errone-fixture-convention.md`).
> Utilise uniquement les `id` de la taxonomie définie : `CRITERE_MANQUANT`, `CRITERE_INVALIDE`, `BALISE_INCONNUE`, `DOUBLE_DIESE`, `SECTION_ALTERNATIVE_MAL_POSITIONNEE`.

---

## Workflow de génération de tests depuis le commentaire

Un agent générateur (subagent ou script) lit le bloc `<!--spip-test ... -->` et crée un fichier PHPUnit `*ErronneTest.php` :

1. **`spec`** → doc-block de la classe et des méthodes de test
2. Chaque `error` → une méthode de test `testXxx()` qui :
   - Charge le fixture via `Templating::fromString()->render(file_get_contents($fixture))`
   - Décrit le **comportement correct attendu** (pas l'erreur)
   - **Échoue** tant que le fixture est incorrect (TDD RED)

### Correspondance error.id → assertion PHPUnit

| id | Assertion typique |
|---|---|
| `CRITERE_MANQUANT` | `assertEqualsCode($correctOrder, $fixedBoucle)` sur les IDs retournés |
| `BALISE_INCONNUE` | `assertStringNotContainsString('href=""', $rendered)` ou `assertStringContainsString($expectedTitle, $rendered)` |
| `DOUBLE_DIESE` | `assertStringNotContainsString('#DATE', $rendered)` |
| `SECTION_ALTERNATIVE_MAL_POSITIONNEE` | `assertStringNotContainsString($fallbackText, $renderedWithData)` |

### Règles de teardown pour tests avec fixtures de fichier

Quand plusieurs classes de test (`*Test.php` et `*VideTest.php`) partagent le même fixture sur la même `id_rubrique`, coordonner les insertions :
- Utiliser une `id_rubrique` fixe et forcer son existence avec `sql_delete` + `sql_insertq` dans `setUpBeforeClass`
- Nettoyer dans `tearDownAfterClass` : articles avant rubriques (FK)
