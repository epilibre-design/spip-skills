# Evals — spip-formulaires

## Cas de test

| id | Nom court | Capacité testée | Type |
|---|---|---|---|
| 1 | Template CVT complet | Structure HTML canonique du formulaire | nominal |
| 2 | Erreur champ titre | Syntaxe #ENV**{erreurs}\|table_valeur | piège |
| 3 | Review form-broken.html | Détection des erreurs de structure | piège |
| 4 | charger() retourne false | Contrat de retour de charger() | limite |
| 5 | Saisies Method 1 | #GENERER_SAISIES vs PHP API + HTML vide | piège |

## Erreurs typiques sans skill

| id | Erreur typique |
|---|---|
| 1 | Utilise `<p class="message_ok">` au lieu de `<div class="reponse_formulaire reponse_formulaire_ok">`. Utilise `ul/li` pour `.editer-groupe`. Oublie l'étoile dans `#ENV*{message_ok}`. |
| 2 | Utilise `#ENV{erreurs.titre}` (dot-notation invalide en SPIP) ou `#ENV{erreurs}` sans `table_valeur`. Oublie la double étoile `**`. Ignore la classe `erreur_message`. |
| 3 | Ne détecte pas la dot-notation invalide pour les erreurs. Ne signale pas les mauvaises classes sur les messages globaux. |
| 5 | Propose `#GENERER_SAISIES` comme première approche plutôt que la PHP API avec HTML vide (Method 1). |

## Lancer les évaluations

### Sans skill (baseline)

```bash
claude "Écris le template HTML complet pour un formulaire CVT SPIP nommé 'inscription'..."
```

### Avec skill

```bash
claude "/spip-formulaires Écris le template HTML complet pour un formulaire CVT SPIP nommé 'inscription'..."
```

### Pour l'eval 3 (review)

Passer le fichier fixture en contexte :

```bash
claude "/spip-formulaires @skills/spip-formulaires/evals/files/form-broken.html Examine ce template et liste tous les problèmes..."
```

## Fixtures

| Fichier | Utilisé par | Description |
|---|---|---|
| `files/form-broken.html` | eval 3 | Template avec 5 erreurs délibérées de structure/classes |
