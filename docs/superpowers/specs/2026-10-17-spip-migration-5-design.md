# SPIP Migration 5.0 Skill — Design Specification

## Goal

Create a Claude Code skill (`spip-migration-5`) that audits and rewrites SPIP 4.x plugin and
squelette code for **SPIP 5.0**, including code that must stay compatible with 4.4 and 5.0 during
the transition.

**Target: SPIP 5.0.0-beta**, verified against `ecrire` commit `c8e329575` (2026-09-22),
`vendor/spip/framework`, `vendor/spip-league/*`, and SPIP 4.4.23 for the dual-compatibility rules.

---

## Why a separate skill

The existing `spip-*` skills describe SPIP 4.1+ and stay correct for 4.x sites. SPIP 5.0 changes the
plugin model (Symfony container, typed pipeline events, attributes), removes many historical
symbols, and changes stored config values. Mixing that into each 4.x skill would blur which rule
applies to which version. A migration skill owns the **4.x → 5.0 delta**; a later `spip5-plugins`
skill can own native 5.0 development.

---

## Audience

Developers maintaining SPIP plugins or squelettes who move them to 5.0, or keep one codebase working
on both 4.4 and 5.0.

Not for: writing a 4.x plugin → `spip-plugins`; template syntax in general → `spip-squelettes`.

---

## Trigger conditions

Load when:

- the user mentions SPIP 5, `5.0`, migration, upgrade, or compatibility with SPIP 5,
- code under review uses `#[AsPipelineListener]`, `#[AsCronTask]`, `Spip::`, `config/services.php`,
- a deprecation message mentions `since 5.0` or `'spip', '5.0'`,
- the user invokes `/spip-migration-5`.

---

## Sourcing policy

- The French `dev.spip.net` documentation (`spip/docs`, `docs/fr/5.0/`) is the starting point.
- Every rule is checked against the 5.0 source. Where the documentation and the source disagree, the
  skill follows the source and says so (examples: `lire_config()` is not deprecated; `param()` /
  `service()` are; `#[AsCronTask]` `periode` defaults to `0`; event classes live in
  `vendor/spip/framework/src/Pipeline/Event/`).
- SPIP 5.0 is in development: the skill states the verified version and tells the agent to confirm in
  the source and with `bin/spip` when a symbol looks off.

---

## Structure

```
spip-migration-5/
├── SKILL.md                     routing, guardrails, quick table of the highest-risk changes
└── references/
    ├── catalogue.md             symbol → replacement → status (removed / deprecated / SPIP 6), grep patterns
    ├── plugin-modele-5.md       services.php, PSR-4, listeners, events, cron, menus, Guard
    ├── squelettes.md            template syntax breaks
    ├── config-metas.md          JSON serialization, $GLOBALS['meta'] mirror, stores
    ├── double-compat.md         code that runs on 4.4 and 5.0
    └── audit-outils.md          deprecation markers, bin/spip, debug_js, rector
```

---

## Testing

Eight eval cases in `evals/spip-migration-5/` (pipeline → listener, constants and helpers,
squelette, meta mirror trap, cron return-code trap, dual compatibility, tooled audit, CLI).
Baseline (RED) recorded in `docs/tests/spip-migration-5-baseline.md`, results with the skill (GREEN)
in `docs/tests/spip-migration-5-green.md`.
