# Evals — spip-migration-5

## Cas de test

| id | Nom court | Capacité testée | Type |
|---|---|---|---|
| 1 | Pipeline → listener | `#[AsPipelineListener]` + event typé + `config/services.php` + autoload PSR-4 | nominal |
| 2 | Constantes et helpers | `_IS_CLI`, `_ESPACE_PRIVE`, `_DIR_RESTREINT_ABS`, `_ROOT_RACINE`, `spip_log`, `$GLOBALS['meta']`, `param()` → façade `Spip` | nominal |
| 3 | Squelette | crochets / CSS, `{fusion}`, `</INCLURE>`, `#LOGO_*\|left`, boucle `POUR`, casse des items de langue | nominal |
| 4 | Miroir meta | sérialisation JSON `[150]`, `$GLOBALS['meta']` brut | piège |
| 5 | Tâche cron | `#[AsCronTask]`, inversion du code retour (`0` = succès) | piège |
| 6 | Double compatibilité 4.4 / 5.0 | API communes aux deux versions, façade absente en 4.4 | limite |
| 7 | Audit outillé | `trigger_deprecation` + `#[\Deprecated]`, `bin/spip spip:pipeline:listeners`, `var_mode=debug_js` | nominal |
| 8 | CLI 5.0 | `bin/spip` (pas spip-cli), `spip:cache:warmup --clear`, `spip:pipeline:listeners` | piège |

## Sources vérifiées

SPIP 5.0.0-beta (`ecrire` `c8e329575`, 2026-09-22) et SPIP 4.4.23. Documentation `dev.spip.net` (dépôt `spip/docs`, `docs/fr/5.0/`).

## Lancer les évaluations

### Sans skill (baseline)

```bash
claude "Mon plugin SPIP 4.4 (prefix 'acme') déclare dans paquet.xml <pipeline nom=\"affichage_final\" …"
```

### Avec skill

```bash
claude "/spip-migration-5 Mon plugin SPIP 4.4 (prefix 'acme') déclare dans paquet.xml <pipeline nom=\"affichage_final\" …"
```
