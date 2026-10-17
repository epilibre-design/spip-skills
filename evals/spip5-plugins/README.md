# Evals — spip5-plugins

## Cas de test

| id | Nom court | Capacité testée | Type |
|---|---|---|---|
| 1 | Plugin minimal | `paquet.xml` 5.0, `composer.json` PSR-4, `config/services.php`, listener `affichage_final`, warmup | nominal |
| 2 | `pre_edition` | `PreEditionEvent`, `getSqlTable()`, `getSubject()` / `setSubject()` | piège (API de la doc fausse) |
| 3 | Tâche quotidienne | `#[AsCronTask]`, `return 0`, `spip.cron.<name>.enabled`, bug de la file en 5.0.0-beta | piège |
| 4 | Logger et config injectés | `#[Autowire(service: …)]`, canal `spip.logger_registry`, `ConfigRepositoryInterface` | piège (pas d'alias) |
| 5 | Règle Guard 429 | `GuardRuleInterface`, `#[AsGuard]`, `GuardVerdict::tooManyRequests()`, priorités | nominal |
| 6 | Page privée et menu | `config/menus.php`, `prive/squelettes/contenu/` | nominal |
| 7 | Listener jamais appelé | checklist, `bin/spip spip:cache:warmup --clear`, `spip:pipeline:listeners` | diagnostic |
| 8 | Pression `$GLOBALS['meta']` | miroir brut JSON, injection ou `Spip::config()` | pression |

## Sources vérifiées

Chaque attente a été vérifiée en exécutant le code sur une instance jetable SPIP 5.0.0-beta (`ecrire` `c8e329575`,
PHP 8.4.25, SQLite) : plugin `acme` activé, listeners listés et observés sur une page servie, `pre_edition` sur un
article, règle Guard (429 à la 31ᵉ requête), menu et page privée, injection, désactivation de tâche par
`config/custom.php`, file de tâches.

## Lancer les évaluations

### Sans skill (baseline)

```bash
claude "Crée un plugin SPIP 5.0 minimal (préfixe 'acme', namespace Acme) …"
```

### Avec skill

```bash
claude "/spip5-plugins Crée un plugin SPIP 5.0 minimal (préfixe 'acme', namespace Acme) …"
```
