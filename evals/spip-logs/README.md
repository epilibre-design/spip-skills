# Evals — spip-logs

## Cas de test

| id | Nom court | Capacité testée | Type |
|---|---|---|---|
| 1 | spip_log warning plugin | Signature spip_log() + constante + $type | nominal |
| 2 | _LOG_DEBUG silencieux | Mécanisme _LOG_FILTRE_GRAVITE | piège |
| 3 | journal() avec objet lié | journal() options id_objet/objet/etat | nominal |
| 4 | spip_log vs journal | Distinction fichiers vs base de données | nominal |
| 5 | Tableau passé à spip_log | var_export() automatique | limite |

## Erreurs typiques sans skill

| id | Erreur typique |
|---|---|
| 1 | Utilise `LOG_WARNING` (constante PHP native) au lieu de `_LOG_AVERTISSEMENT`. Inverse l'ordre des arguments (gravité en 2e position au lieu de $type). Indique le mauvais chemin de log (`logs/` au lieu de `tmp/log/`). |
| 2 | Ne connaît pas `_LOG_FILTRE_GRAVITE`. Pense que `_LOG_DEBUG` est écrit par défaut. Ne sait pas comment activer le debug logging. |
| 3 | Utilise `spip_log()` au lieu de `journal()` pour un événement d'audit visible dans l'espace privé. Ignore les options `id_objet`, `objet`, `etat`. |
| 5 | Appelle `var_export()` ou `json_encode()` manuellement avant de passer le tableau à `spip_log()`. |

## Lancer les évaluations

### Sans skill (baseline)

```bash
claude "Dans un plugin SPIP avec le prefix 'acme', écris l'appel pour logger un avertissement..."
```

### Avec skill

```bash
claude "/spip-logs Dans un plugin SPIP avec le prefix 'acme', écris l'appel pour logger un avertissement..."
```
