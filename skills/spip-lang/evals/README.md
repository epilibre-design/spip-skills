# Evals — spip-lang

## Cas de test

| id | Nom court | Capacité testée | Type |
|---|---|---|---|
| 1 | Fichier lang complet | Format return[], en-tête, préfixes de clés, pluriel | nominal |
| 2 | Syntaxe squelette | <:module:clé:> vs alternatives | piège |
| 3 | _T() avec placeholder | Appel _T() avec tableau d'arguments | nominal |
| 4 | Clés paquet-prefix | Nommage {prefix}_description / {prefix}_slogan | piège |
| 5 | lang_select() pour email | Changement de langue contextuel | limite |

## Erreurs typiques sans skill

| id | Erreur typique |
|---|---|
| 1 | Utilise `$GLOBALS['i18n']['acme'] = [...]` au lieu de `return [...]`. Oublie l'en-tête fixe. Invente des préfixes de clés non conventionnels. Ne sait pas qu'il faut deux clés pour le singulier/pluriel. |
| 2 | Utilise `<?php echo _T('acme:titre') ?>` en PHP inline dans le HTML. Ou utilise `[(#VAL{acme:titre}|_T)]` comme forme principale. Ne connaît pas `<:module:clé:>`. |
| 4 | Nomme les clés `description` et `nom` au lieu de `acme_description` et `acme_slogan`. Ne sait pas que le module à utiliser pour y accéder est `paquet-acme:`. |
| 5 | Invente un troisième argument langue à `_T()` (n'existe pas). Ne connaît pas `lang_select()`. |

## Lancer les évaluations

### Sans skill (baseline)

```bash
claude "Crée le fichier de langue de référence français pour un plugin SPIP avec le prefix 'acme'..."
```

### Avec skill

```bash
claude "/spip-lang Crée le fichier de langue de référence français pour un plugin SPIP avec le prefix 'acme'..."
```
