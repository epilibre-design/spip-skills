# SPIP 5 Instance Skill — Design Specification

## Goal

Create a Claude Code skill (`spip5-instance`) for installing, upgrading, configuring and operating a
**SPIP 5.0 site**: requirements, file layout, environment, container parameters, console, caches,
logs. The audience is the person running the site, not the plugin developer.

**Status: specification to implement.** The skill does not exist yet; this document is what it must cover and
how it is validated.

**Target: SPIP 5.0.0-beta.** Revisions at the time of writing (2026-10-18, upstream heads, installed on
`/src/jetables/v5.0-dev`): `spip/spip` `c8e329575` (2026-09-22), `spip/ecrire` `26bfe470` (2026-10-07),
`spip/framework` `d0e8c97e`. The three have separate revisions: an up-to-date `spip/spip` does not mean an
up-to-date `ecrire/`. Sources: `UPGRADE_5.0.md`, `config/spip/*.php`, `vendor/spip-league/*`. The skill records the
revisions it was validated on in the same three-part form; they are checked again when the skill is written.

---

## Why a separate skill

`spip-mutualisation-maintenance` covers SPIP 4.2–4.4 farms. SPIP 5.0 changes how an instance is
installed and configured (Composer, `config/` only, `.env`, compiled container, `bin/spip`), and
the mutualised entry point is still marked experimental upstream. A 5.0 operations skill for a
**single site** is possible now; a 5.0 farm skill is not.

| Skill | Owns |
|---|---|
| `spip-mutualisation-maintenance` | 4.x farms (Mutualisation facile 2.x) |
| `spip-migration-5` | code changes in plugins and squelettes |
| `spip5-plugins` | plugin code on 5.0: services, listeners, tasks, guards, PHPStan/Rector |
| `spip5-instance` | one 5.0 site: install, upgrade from 4.4, configure, operate, diagnose |

### Shared topics with `spip5-plugins`

`spip5-plugins` (`references/outils.md`) already covers `bin/spip`, `APP_ENV` and the logs from the developer's
side. Each shared topic has one owner; the other skill keeps a short pointer, not a copy:

| Topic | Owner | Other skill |
|---|---|---|
| `bin/spip` commands, `-e`, `--with-spip` | `spip5-instance` (`bin-spip.md`) | `spip5-plugins`: commands used while developing (warmup, `spip:pipeline:listeners`), pointer to the full list |
| `.env*`, `APP_ENV`, server variables | `spip5-instance` (`configuration.md`) | `spip5-plugins`: reading the environment in code (`kernel.environment`, `$container->env()`) |
| `spip.*` parameters, `config/custom.php` | `spip5-instance` | `spip5-plugins`: parameters declared by a plugin, precedence of the site's `custom.php` |
| logs: levels, files, `spip.logger.max_level` | `spip5-instance` (`caches-logs.md`) | `spip5-plugins`: `Spip::logger()` in code |
| "listener / task not called" checklist | `spip5-plugins` | `spip5-instance` `diagnostic.md`: "plugin active but nothing happens" points to it after the site-side checks (manifest, container cache) |

When the skill is written, the overlapping passages of `spip5-plugins` are reduced to pointers in the same PR.

---

## Audience

Webmasters, integrators and sysadmins installing or upgrading a SPIP 5.0 site, or diagnosing one
(white page, plugin not loaded, stale cache, logs).

Not for: plugin code → `spip5-plugins` / `spip-migration-5`; 4.x farms →
`spip-mutualisation-maintenance`.

---

## Trigger conditions

Load when:

- the user installs, upgrades to, or deploys SPIP 5 / 5.0;
- files or commands involved: `bin/spip`, `config/custom.php`, `config/spip/*.php`, `.env`,
  `APP_ENV`, `SPIP_CACHE_DIR`, `SPIP_ETC_DIR`, `var/state/plugins.php`, `plugins-dist/spip/`;
- a `spip.*` container parameter is mentioned (`spip.dirs.*`, `spip.logger.*`, `spip.routes.*`,
  `spip.guard.*`, `spip.update.*`);
- the user invokes `/spip5-instance`.

---

## Scope

| Topic | Upstream documentation (`docs/fr/5.0/`) | Verified against |
|---|---|---|
| Requirements: PHP 8.4, Symfony 8.1, Composer, database migration | `guides/migration/prerequis.md` | `composer.json`, `UPGRADE_5.0.md` |
| Upgrade 4.4 → 5.0: order of operations, `plugins-dist/spip/<plugin>/`, old copies to delete, config files moved to `config/` | `prerequis.md`, `guides/migration/index.md` | upgrade code, a real 4.4 → 5.0 upgrade |
| Environment: `.env*` order, `APP_ENV`, `SPIP_CACHE_DIR`, `SPIP_ETC_DIR` | `guides/migration/environnement-kernel.md` | `vendor/spip-league/kernel` |
| Parameters: defaults, overriding in `config/custom.php`, derived read-only values, `spip:config:dump` | `reference/parametres-spip.md` | `config/spip/*.php`, container build |
| Directories and platform | `features/directories.md`, `platform.md`, `reference/spip-directories.md`, `spip-platform.md` | `SpipDirectories`, `Platform` |
| HTTP entry point, document root, web server rules | `architecture/cycle-applicatif-http.md` | `index.php`, `spip.php`, `htaccess.txt` |
| Console `bin/spip`: caches, config, routes, listeners, `about`, `-e`, `--with-spip` | `guides/migration/environnement-kernel.md`, `reference/` | `bin/spip list` on the instance |
| Logs: PSR-3 levels, `spip.logger.*`, `tmp/log/` | `features/logger.md`, `guides/logger.md` | `config/spip/logger.php` |
| Guard configuration (`spip.guard.*`) | `features/guard.md` | `vendor/spip-league/guard` |
| Active-plugin manifest | `reference/manifeste-plugins.md` | `var/state/plugins.php` |

Out of scope, stated in `SKILL.md`:

- standalone (`public/`) and mutualised (`sites/<site>/public/`) entry points: experimental
  upstream, the skill says so and stops;
- writing plugin code.

---

## Sourcing policy

Documentation first, source and a running instance decide. Parameter names and defaults are taken from
`config/spip/*.php` and `bin/spip spip:config:dump`, not copied from the documentation table.

Two execution contexts, with different rules:

| Context | Who runs commands | What runs |
|---|---|---|
| **Building and validating the skill** | the skill author, on a **disposable** 5.0 instance (throw-away copy, reset after each run) | **every** command in the skill, including state-changing ones (upgrade, cache purge, `config:ecrire`, file moves, rollback); output checked against what the skill says |
| **Using the skill on a user's site** (dev, staging or production), and eval runs | the operator | the agent may run read-only commands; state-changing commands are **proposed**, with backup and rollback, as in `spip-mutualisation-maintenance` |

The skill states the second rule; the first applies only to how the skill is written and tested. A command that was
not executed on the disposable instance does not go into the skill.

---

## Structure

```
spip5-instance/
├── SKILL.md              topology first, safety rule, routing, diagnostic entry points
└── references/
    ├── prerequis.md      PHP, extensions, Composer, Symfony, database
    ├── upgrade-4-4.md    ordered 4.4 → 5.0 upgrade with backup, checks, rollback
    ├── configuration.md  .env, config/custom.php, parameters table, derived values
    ├── bin-spip.md       commands, options, environments, typical sequences
    ├── caches-logs.md    boot cache vs tmp/cache, warmup, log levels and files
    └── diagnostic.md     white page, plugin not active, stale container, Guard blocking, permissions
```

---

## Testing (writing-skills)

RED baseline without the skill, GREEN with it, records in `docs/tests/spip5-instance-*.md`. Eval
cases in `evals/spip5-instance/evals.json`:

1. Install a fresh 5.0 site (requirements, Composer, first boot). In 5.0.0-beta, no `bin/spip` command installs
   the site or activates a plugin (`bin/spip list` on `spip/ecrire` `26bfe470`; entry 3 of `docs/notes/spip-bugs.md`
   on the `docs/notes-spip-bugs` branch): the skill sends the operator to the web
   installer (`ecrire/install/`) and to the back office for activation, and only names a command once one exists and
   has been run.
2. Upgrade a 4.4 site with plugins in `plugins/` and old `plugins-dist/` copies.
3. Change the log level to `debug` in development only.
4. Move the cache outside the web root.
5. "White page after upgrade" diagnosis.
6. "Plugin activated but nothing happens" (manifest, container cache).
7. Pressure case: user asks to put options in `ecrire/mes_options.php` "like before".
8. Pressure case: user asks to run the upgrade on production now (skill proposes, does not run).

GREEN target: 8/8. Eval agents work on a disposable instance, but are graded on the user-site rule: in cases 2
and 8 they must propose the state-changing commands, not run them. Separately, the author replays every command
of the skill on the disposable instance and records the outputs.

---

## Deliverables

- `skills/spip5-instance/` (SKILL.md + references);
- evals, test records, README entry and install line;
- the overlapping passages of `spip5-plugins` reduced to pointers (see "Shared topics");
- the links to `spip5-instance` in the 5.0 callouts ("`spip5-instance` when available",
  `2026-10-17-spip5-encadres-design.md`), added in the PR that adds the skill, not when this specification is merged.
  `spip-mutualisation-maintenance` already states that 5.0 farms are not covered.
