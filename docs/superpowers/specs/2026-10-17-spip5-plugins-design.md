# SPIP 5 Plugins Skill — Design Specification

## Goal

Create a Claude Code skill (`spip5-plugins`) for writing **new** SPIP 5.0 plugin code the native way:
Composer package, Symfony container, typed pipeline listeners, attributes, injected services.

**Target: SPIP 5.0.0-beta**, verified against `ecrire` commit `c8e329575` (2026-09-22),
`vendor/spip/framework` and `vendor/spip-league/*`.

---

## Why a separate skill

| Skill | Owns |
|---|---|
| `spip-plugins` | 4.x plugin development (`paquet.xml`, `_pipelines.php`, `inc/`, `action/`, SQL API) |
| `spip-migration-5` | the **4.x → 5.0 delta**: what to remove, rewrite, keep for 4.4 + 5.0 |
| `spip5-plugins` | the **5.0 plugin model** itself, for code written from scratch or after migration |

`spip-migration-5` currently carries the 5.0 plugin model in `references/plugin-modele-5.md`. Once
`spip5-plugins` exists, that file shrinks to the migration-specific part (mapping old → new) and
points to `spip5-plugins` for the model. One description of the model, so the two skills cannot
drift apart.

**Dependency:** PR #25 (`spip-migration-5`) must be merged first; the reduction of
`plugin-modele-5.md` is done in this skill's branch.

---

## Audience

Developers creating a SPIP 5.0 plugin, or adding a feature (listener, task, service, Guard rule,
back-office page) to a plugin that already targets 5.0 only.

Not for:

- plugins that must still run on 4.4 → `spip-migration-5` (`double-compat.md`);
- 4.x plugins → `spip-plugins`;
- squelettes, forms, language files → existing `spip-*` skills (unchanged in 5.0 except the
  breaks listed in `spip-migration-5`).

---

## Trigger conditions

Load when:

- the user asks to create or extend a plugin for SPIP 5 / 5.0;
- the code contains `config/services.php`, `#[AsPipelineListener]`, `#[AsCronTask]`, `#[AsGuard]`,
  `PipelineEvent`, `GuardRuleInterface`, `ConfigRepositoryInterface`;
- the user asks which event class a pipeline uses, or how to inject a SPIP service;
- the user invokes `/spip5-plugins`.

---

## Scope

| Topic | Upstream documentation (`docs/fr/5.0/`) | Verified against |
|---|---|---|
| Package: `composer.json` (type, PSR-4, constraints), `paquet.xml` still required | `features/plugins-et-conteneur.md` | plugin loader, `PluginSymfonyServicesCollector` |
| `config/services.php`: `autowire()`, `autoconfigure()`, `load()` of the namespace, parameters | `features/services-symfony.md`, `guides/services-plugin.md` | `vendor/spip/framework` container build |
| Listeners: `#[AsPipelineListener(pipeline, position, method)]`, typed event vs `PipelineEvent` | `reference/as-pipeline-listener.md`, `guides/pipelines-plugins.md` | `SpipLeague\Bridge\Pipeline\Attribute\AsPipelineListener` |
| Event catalogue: class per pipeline, subject/argument methods | `reference/evenements-pipelines.md` | `vendor/spip/framework/src/Pipeline/Event/` (44 classes) |
| Tasks: `#[AsCronTask(name, periode, priorite, method)]`, return codes, `spip.cron.<name>.*` | `guides/cron-taches-attributs.md` | `AsCronTask`, `queue_close_job` |
| Services to inject: config, logger, directories, platform, request/inputs | `features/acces-services-spip.md`, `logger.md`, `directories.md`, `platform.md`, `http-request-response.md` | façade `Spip\Framework\Spip`, service ids |
| Configuration of the plugin: metas through `ConfigRepositoryInterface`, custom store | `features/config.md` | `spip-league/config` |
| Guard rules: `GuardRuleInterface::evaluate(GuardContext): GuardVerdict`, `#[AsGuard(priority)]` | `features/guard.md`, `guides/guard-extension.md` | `vendor/spip-league/guard/src/` |
| Back-office menus and tabs | `features/menus.md`, `guides/menus-onglets.md` | menu collector |
| Active-plugin manifest, cache rebuild, debug | `reference/manifeste-plugins.md`, `parametres-spip.md` | `var/state/plugins.php`, `bin/spip` |

Out of scope: core contribution (`architecture/depots-spip-league.md`, `feuille-de-route.md`),
experimental standalone/mutualised entry points.

---

## Sourcing policy

Same as `spip-migration-5`: the upstream documentation is the starting point, every rule is checked
in the 5.0 source, the source wins and the skill says so. Known documentation errors to carry over
(see `docs/notes/2026-10-17-spip-code-hors-doc.md`): `PreEditionEvent` has no `getTable()`,
`#[AsCronTask]` `periode` defaults to `0` and a period of `0` is not scheduled, event classes live
in `vendor/spip/framework/src/Pipeline/Event/`, `param()` and `service()` are deprecated.

Every example in the skill must be **run** on a 5.0 instance (plugin activated, `bin/spip
spip:cache:warmup --clear`, `bin/spip spip:pipeline:listeners`, task or page triggered), not only
read against the source.

---

## Structure

```
spip5-plugins/
├── SKILL.md                    model in one screen, routing, guardrails, "listener not called" checklist
└── references/
    ├── squelette-plugin.md     complete minimal plugin: tree, composer.json, paquet.xml, services.php
    ├── listeners-events.md     attribute, typed vs generic event, priorities, table pipeline → event → methods
    ├── cron.md                 attribute, return codes, parameters, testing a task
    ├── services.md             injectable services and their interfaces, façade only outside services
    ├── config-plugin.md        reading/writing the plugin config, custom store, install/uninstall
    ├── guard.md                writing and ordering a rule, verdicts, testing
    ├── espace-prive.md         menus, tabs, #LAYOUT_PRIVE, assets
    └── outils.md               bin/spip commands for plugin developers, manifest, deprecations log
```

`SKILL.md` stays under 500 words; the event table lives in `listeners-events.md` only.

### Guardrails in `SKILL.md`

- Inject services in classes; the `Spip::` façade is for procedural code and squelette filters.
- Never register a pipeline both as a legacy function and as a listener.
- No `$GLOBALS['meta']`, no `_request()`, no removed constants (link to `spip-migration-5`
  `catalogue.md`).
- After any change to `services.php` or an attribute: `bin/spip spip:cache:warmup --clear`.
- CLI is `bin/spip`, not spip-cli.

---

## Testing (writing-skills)

RED baseline with subagents without the skill, then GREEN with it, recorded in
`docs/tests/spip5-plugins-baseline.md` and `docs/tests/spip5-plugins-green.md`. Eval cases in
`evals/spip5-plugins/evals.json`:

1. Create a minimal plugin with one listener on `affichage_final` (structure, files, cache rebuild).
2. Modify an article title before save (`pre_edition`: right event class and methods).
3. Daily task that may fail (return code, period, parameters).
4. Inject the logger and the plugin config into a service (no façade inside the class).
5. Guard rule blocking a route above a quota (interface, verdict, priority).
6. Back-office page with a menu entry.
7. "My listener is never called" diagnosis.
8. Pressure case: user asks for `$GLOBALS['meta']` "because it is faster".

GREEN target: 8/8, every produced example executed on the 5.0 instance.

---

## Deliverables

- `skills/spip5-plugins/` (SKILL.md + references);
- `skills/spip-migration-5/references/plugin-modele-5.md` reduced to the migration mapping, with a link;
- evals, test records, README entry.
