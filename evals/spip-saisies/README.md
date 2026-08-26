# Evals — spip-saisies

## Cas de test

| id | Nom court | Capacité testée | Type |
|---|---|---|---|
| 1 | Nouveau type de saisie | Quatuor de fichiers, contrat de `_base.html`, structure du `.yaml` | nominal |
| 2 | Champ ajouté à un formulaire tiers | Pipeline `formulaire_saisies`, forme du flux | nominal |
| 3 | Option ajoutée au constructeur | Pipeline `saisies_construire_formulaire_config`, nommage imbriqué | piège |
| 4 | Valeur d'un champ imbriqué | `saisies_request()` vs `_request()`, notations `nom` / `name` | limite |
| 5 | Contrôle croisé entre deux champs | Ordre `verifier()` → vérifications déclaratives → `verifier_post_saisies()` | piège |

## Erreurs typiques sans skill

| id | Erreur typique |
|---|---|
| 1 | Régénère le label, le conteneur `.editer` et le bloc d'erreur dans le `.html` du type, produisant un label en double. Recopie les options communes au lieu d'inclure les fragments `saisies/_base/*.yaml`. Présente le `.php` comme obligatoire. Invente des clés de `.yaml` (`champs`, `parametres`) au lieu de `options` / `defaut`. |
| 2 | Propose de surcharger le fichier du plugin tiers dans `squelettes/`, ou d'utiliser le pipeline SPIP `formulaire_charger`. Retourne `$flux['data']` au lieu de `$flux`. Oublie la déclaration dans `paquet.xml`. |
| 3 | Ne connaît pas `saisies_construire_formulaire_config` et propose `formulaire_saisies`. Nomme l'option `type_document` à plat, si bien que la valeur n'est jamais rattachée à la saisie configurée. |
| 4 | Répond `_request('adresse/ville')`, qui renvoie `null` : le champ est posté sous `adresse[ville]`. Ou propose `$_POST['adresse']['ville']` en contournant l'API. |
| 5 | Met le contrôle dans `verifier()`, qui s'exécute **avant** les vérifications déclaratives et voit donc des valeurs non normalisées. Ou supprime les `verifier` des saisies pour tout réécrire à la main. |

## Lancer les évaluations

### Sans skill (baseline)

```bash
claude "Dans un plugin SPIP de préfixe 'acme', crée un nouveau type de saisie 'commune'..."
```

### Avec skill

```bash
claude "/spip-saisies Dans un plugin SPIP de préfixe 'acme', crée un nouveau type de saisie 'commune'..."
```
