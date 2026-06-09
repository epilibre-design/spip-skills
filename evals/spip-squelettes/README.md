# Evals — spip-squelettes

## Cas de test

| id | Nom court | Capacité testée | Type |
|---|---|---|---|
| 1 | BOUCLE paginée avec alternative | BOUCLE syntax, pagination, section zéro-résultat | nominal |
| 2 | {doublons} vedette + reste | {doublons} sur les deux boucles | piège |
| 3 | Arbre récursif ul/li | Boucle récursive BOUCLE_sous(BOUCLE_rubs) | nominal |
| 4 | INCLURE {ajax} | {ajax} sur INCLURE (pas sur la boucle interne) | piège |
| 5 | Crop centré 400×250 | Ordre image_reduire avant image_recadre | piège |
| 6 | {doublons} cross-INCLURE | Transmission explicite des doublons via INCLURE | limite |

## Erreurs typiques sans skill

| id | Erreur typique |
|---|---|
| 2 | Place `{doublons}` uniquement sur la deuxième boucle (reste). La première (vedette) n'a pas `{doublons}` et ne marque donc pas l'article, rendant l'exclusion inopérante. |
| 4 | Place `{ajax}` sur le critère `{pagination}` de la boucle interne du fragment (`{pagination 10}{ajax}`), au lieu de le mettre sur la balise `INCLURE` dans le squelette parent. |
| 5 | Applique `image_recadre` seul ou en premier, ce qui peut produire un résultat flouté par upscaling si l'image source est plus petite que la taille de crop cible. |
| 6 | Suppose que `{doublons}` est automatiquement transmis aux fragments inclus via `INCLURE`. En réalité il faut le passer explicitement : `<INCLURE{fond=inc-related,doublons}>`. |

## Lancer les évaluations

### Sans skill (baseline)

```bash
claude "Écris le code squelette SPIP pour afficher une liste paginée..."
```

### Avec skill

```bash
claude "/spip-squelettes Écris le code squelette SPIP pour afficher une liste paginée..."
```
