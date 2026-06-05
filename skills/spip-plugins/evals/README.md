# Evals — spip-plugins

## Cas de test

| id | Nom court | Capacité testée | Type |
|---|---|---|---|
| 1 | paquet.xml avec schema | Structure paquet.xml + attribut schema | nominal |
| 2 | Handler post_edition | Pipeline args — clé objet vs table | piège |
| 3 | SQL API — articles publiés | sql_allfetsel / sql_select+sql_fetch | nominal |
| 4 | _administrations.php | Convention fichier install/upgrade | piège |
| 5 | autoriser() dans une action | API d'autorisation SPIP | limite |

## Erreurs typiques sans skill

| id | Erreur typique |
|---|---|
| 2 | Utilise `$flux['args']['table'] === 'spip_articles'` (clé dépréciée "kept for BC") au lieu de `$flux['args']['objet'] === 'article'` |
| 4 | Invente une balise `<install>` dans paquet.xml (n'existe pas). Nomme les fonctions `acme_install()` / `acme_uninstall()` au lieu de `acme_upgrade()` / `acme_vider_tables()`. Ignore la convention de nommage `{prefix}_administrations.php`. |

## Lancer les évaluations

### Sans skill (baseline)

Demander à Claude Code de répondre aux prompts **sans** charger le skill :

```bash
# Exemple pour l'eval 1
claude "Écris un paquet.xml minimal valide pour un plugin SPIP 4.2+ avec le prefix 'acme'..."
```

### Avec skill

Charger le skill avant de poser la question :

```bash
# Soit via /spip-plugins dans Claude Code
# Soit en plaçant le SKILL.md dans le contexte
claude "/spip-plugins Écris un paquet.xml minimal valide..."
```

### Vérification manuelle des assertions

Chaque eval définit des `expectations` vérifiables. Pour chaque réponse :
1. Compter combien d'assertions sont satisfaites
2. Calculer le score avec/sans skill
3. Les assertions différentiantes sont celles qui passent avec skill et échouent sans

## Fixtures

| Fichier | Utilisé par | Description |
|---|---|---|
| `files/paquet-sans-schema.xml` | eval 4 | paquet.xml sans attribut schema ni _administrations.php |
