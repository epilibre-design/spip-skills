# Evals — spip5-instance

## Cas de test

| id | Nom court | Capacité testée | Type |
|---|---|---|---|
| 1 | Installation neuve | PHP 8.4, `composer create-project` puis `composer update`, installeur web, répertoires inscriptibles, chemins à ne pas servir | nominal |
| 2 | Montée 4.4 → 5.0 | sauvegarde, inventaire `compatibilite`, installation côte à côte, reprise des données, warmup, mises à jour des plugins, retour arrière avec la base | nominal |
| 3 | Logs debug en dev | `spip.logger.max_level` sous `$container->env() === 'dev'`, `APP_ENV` dans `.env.local`, warmup | nominal |
| 4 | Cache hors racine web | bug de `SPIP_CACHE_DIR` en beta, combinaison `spip.dirs.tmp` relatif + `SPIP_CACHE_DIR` | piège (la doc est fausse en beta) |
| 5 | Page blanche après mise à jour | logs, PHP, `vendor/`, `tmp/cache` périmé ou non inscriptible, `_DIR_*` | diagnostic |
| 6 | Plugin activé sans effet | `admin_plugin`, `var/state/plugins.php`, caches, renvoi à `spip5-plugins` | diagnostic |
| 7 | Pression `ecrire/mes_options.php` | fichier ignoré en 5.0, `config/mes_options.php`, `_DIR_*` interdits | pression |
| 8 | Pression « en prod maintenant » | refus d'exécuter, plan avec sauvegarde, copie, retour arrière | pression |

## Sources vérifiées

Chaque attente vient de `docs/tests/spip5-instance-verifications.md` : commandes exécutées le 2026-10-18 sur des
copies jetables de SPIP 5.0.0-beta (`spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`, `spip/framework`
`d0e8c97e`, PHP 8.4.25, SQLite) et de SPIP 4.4.21, servies par le serveur PHP intégré, Apache 2.4.52 + PHP-FPM 8.4 et
nginx.

## Lancer les évaluations

Les agents travaillent sans outils (RED) ou en lisant seulement le skill (GREEN). Les cas 2 et 8 sont notés sur le
fait de **proposer** les commandes qui modifient l'état, pas de les exécuter.

### Sans skill (baseline)

Sous-agent neuf, sans outils ni skill, une invite par cas, réponse depuis ses seules connaissances.

### Avec skill

Sous-agent neuf, limité à la lecture de `skills/spip5-instance/` (`SKILL.md` et `references/`), mêmes invites.
