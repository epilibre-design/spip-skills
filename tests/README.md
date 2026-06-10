# Tests

Ce dossier contient les tests du framework SPIP.

## Règle sur les tests unitaires

Un test unitaire ne doit pas rester volontairement rouge dans la suite normale.

- Si un comportement doit échouer, le test doit l'exprimer explicitement: retour faux, valeur vide, exception attendue, absence de rendu, ou erreur vérifiée.
- Un test volontairement rouge peut servir temporairement pendant une phase TDD, mais il doit être corrigé avant d'entrer dans la suite de référence.
- Les tests destinés à montrer une implémentation incorrecte doivent être isolés dans une suite ou un dossier clairement identifié comme `red` ou `draft`.
- Les tests conservés dans `unit` doivent toujours décrire un comportement attendu et rester verts.

## Organisation recommandée

- `green/unit/` pour les tests unitaires stables.
- `green/integration/` pour les tests d'intégration avec SPIP complet.
- `red/` pour les scénarios volontairement cassés ou les tests de travail pendant l'implémentation.

## Principe

Un test utile doit aider à vérifier un comportement. S'il échoue sans raison fonctionnelle, il ne doit pas être gardé dans la suite normale.