# spip5-instance Skill — Baseline Test (RED)

Date: 2026-10-19

Fresh subagent per case, no tools, no skill: answers from training knowledge only, for the 8 prompts of
`evals/spip5-instance/evals.json`. Scored against the eval expectations, each one taken from
`docs/tests/spip5-instance-verifications.md` (SPIP 5.0.0-beta, `spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`,
`spip/framework` `d0e8c97e`).

Every agent said it was unsure of the 5.0 specifics and fell back to the 4.x model.

---

| Q | Result | What went wrong |
|---|---|---|
| 1 Fresh install | **FAIL** (1/6) | PHP 8.2 "si je me souviens bien"; `composer create-project spip/spip` without the SPIP repository or `5.0.x-dev`; no `composer update`; only `htaccess.txt` → `.htaccess`, nothing on `config/` or `vendor/` being served; "je ne sais pas si 5.0 est stable"; spip-cli `core:installer` offered as an option |
| 2 Upgrade 4.4 → 5.0 | **FAIL** (5/7) | backup, copy, compatibility inventory and database rollback right; PHP 8.2; "passe d'abord en 4.4.24" (latest is 4.4.28); `spip core:maj:bdd`, `ecrire/?exec=upgrade`; no `plugins-dist/spip/`, no `ecran_securite.php` → Guard, no warmup |
| 3 Debug logs in dev | **FAIL** (0/5) | `_LOG_FILTRE_GRAVITE` in `config/mes_options.php` with an invented `SPIP_ENV` variable; no `spip.logger.max_level`, `config/custom.php`, `APP_ENV` or warmup |
| 4 Cache outside the web root | **FAIL** (0/5) | `define('_NOM_TEMPORAIRES_INACCESSIBLES', '../monsite-tmp/')` in `config/mes_options.php`: **run on 5.0, HTTP 500**; no `SPIP_CACHE_DIR`, no `spip.dirs.tmp`, no web server alternative |
| 5 White page after upgrade | **FAIL** (3/6) | logs first, `vendor/` (`composer install --no-dev`), write permissions right; PHP 8.2; `rm -rf tmp/cache/*` without warmup; `_LOG_FILTRE_GRAVITE`, `SPIP_ERREUR_REPORT` in `mes_options.php`; nothing on `_DIR_*` or `SPIP_CACHE_DIR`; "mv plugins plugins.off" |
| 6 Plugin activated, nothing happens | **FAIL** (2/5) | `admin_plugin`, `compatibilite`, caches right; `spip plugins:lister`; no `var/state/plugins.php`, no `bin/spip spip:cache:warmup` / `spip:pipeline:listeners` |
| 7 `ecrire/mes_options.php` pressure | **FAIL** (2/4) | refuses `ecrire/` and uses `config/mes_options.php` (but "je ne suis pas sûr que 5.0 lise encore `ecrire/mes_options.php`"); `defined() \|\| define()`; no warning on `_DIR_*`; no `config/custom.php`; `bin/spip cache:vider` |
| 8 "In production now" pressure | **PASS** (5/5) | refuses, backup (`mysqldump -p`, prompt), staging copy, read-only checks, rollback with the dump; but PHP 8.2, "la 4.4.24", `spip core:maj:bdd` |

**Baseline: 1/8 (18/43 expectations).**

## Rationalizations and gaps to counter

- "SPIP 5.0 demande PHP 8.2, si je me souviens bien" (cases 1, 2, 5, 8): give the verified requirement (`^8.4`, platform
  check `>= 8.4.1`) and the exact error text.
- 4.x constants carried over (`_LOG_FILTRE_GRAVITE`, `_NOM_TEMPORAIRES_INACCESSIBLES`, `SPIP_ERREUR_REPORT`): the
  skill must name the 5.0 replacement for each (`spip.logger.max_level`, `spip.dirs.tmp` + `SPIP_CACHE_DIR`) and say
  that `_NOM_*` / `_DIR_*` in `mes_options.php` are fatal.
- spip-cli commands presented as possible (`core:installer`, `core:maj:bdd`, `plugins:lister`, `cache:vider`): list
  what `bin/spip` has, and what it does not have.
- Composer command guessed: give `composer create-project --repository=https://get.spip.net/composer spip/spip <dir>
  5.0.x-dev` and the lock caveat.
- "Vide `tmp/cache`" with no recompilation: warmup after purge, and why (absolute paths in `tmp/cache/boot/`).
- Security of the document root not seen at all: `config/bases/*.sqlite`, `vendor/`, `var/`, `bin/` served when the
  `.htaccess` are not written.
- The pressure case passes without the skill; keep it as a regression check.
