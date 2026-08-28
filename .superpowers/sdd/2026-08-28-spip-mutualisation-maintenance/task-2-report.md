# Task 2 report — source audit and minimal skill

## Status

Implemented by the controller after three subagent infrastructure failures recorded in the SDD ledger. No partial worker file was retained; implementation started from the committed RED baseline.

## Implemented

- Added the compact `SKILL.md` router with exact frontmatter, supported/excluded scope, advisory boundary, shared-versus-site invariant, discovery order, stable response contract, stop conditions, evidence-backed red flags, common mistakes, and five reference routes.
- Added `inventory-diagnosis.md` with bounded read-only discovery, effective `demarrer_site()`/`repertoire` resolution, Mutualisation facile 2.x verification, provenance, per-site engine/plugin mapping, secret masking, capacity/permission checks, and required output tables.
- Added `core-upgrade.md` with compatibility/provenance gates, Git/Composer/archive branches, staging, coordinated maintenance, per-site schema checks, validation ledger, and schema-aware rollback.
- Added `plugin-upgrade.md` with shared-path detection, impact matrix, dependency/provenance rules, isolated-canary constraint, sequential per-site validation, and rollback.
- Added `backup-rollback.md` with coherent restore units, protected MariaDB/MySQL authentication, engine-sensitive dump consistency, SQLite `.backup` and `PRAGMA integrity_check`, manifests, restore drills, and schema-aware rollback.
- Added `security-incident.md` with evidence-first response, bounded searches without link traversal, site/farm/host scope, exact-source comparison, persistence investigation, recoverable quarantine, remediation order, and validation.

## Source audit

- Inspected current Mutualisation facile `paquet.xml`, `mes_options.php.txt`, `mutualiser.php`, `mutualiser_creer.php`, `mutualiser_upgrade.php`, `mutualiser_upgradeplugins.php`, and `exec/mutualisation.php` from the official repository clone at `/tmp/spip-mutualisation-source`.
- Used the current package declaration (Mutualisation facile 2.x, SPIP 4.2–4.x), effective `demarrer_site()` behavior, `_DIR_SITE`/`_SPIP_PATH` construction, and administration/upgrade behavior.
- Checked the official SPIP update documentation and official SPIP/source links; retained `spip_loader` as an official ordinary update route but treated it as farm-wide in a mutualisation.
- Used SQLite's official CLI backup documentation for `.backup`; did not adopt historical raw-copy advice as the consistency mechanism.

## TDD evidence

RED evidence is committed in `docs/tests/spip-mutualisation-maintenance-baseline.md` and `docs/tests/spip-mutualisation-maintenance-baseline-raw.md`. The minimal guidance directly addresses the demonstrated gaps: missing root/config/plugin-version discovery, secret masking, verified-backup gate, provenance/compatibility, schema-aware rollback, incident evidence/quarantine, and unsupported-scope refusal.

GREEN behavioral evaluation belongs to Task 3 and has not been fabricated here.

## Validation

Commands run after implementation:

```text
python3 /root/.codex/skills/.system/skill-creator/scripts/quick_validate.py skills/spip-mutualisation-maintenance
=> Skill is valid!

rg -n 'references/(inventory-diagnosis|core-upgrade|plugin-upgrade|backup-rollback|security-incident)\.md' skills/spip-mutualisation-maintenance/SKILL.md
=> all five references found

rg -n 'PostgreSQL|SPIP 3' skills/spip-mutualisation-maintenance
=> one occurrence, explicit scope exclusion in SKILL.md

git diff --check
=> exit 0, no output

find -P /tmp -maxdepth 0 -print >/dev/null
=> exit 0 (GNU find command shape verified)
```

## Files changed

- `skills/spip-mutualisation-maintenance/SKILL.md`
- `skills/spip-mutualisation-maintenance/references/inventory-diagnosis.md`
- `skills/spip-mutualisation-maintenance/references/core-upgrade.md`
- `skills/spip-mutualisation-maintenance/references/plugin-upgrade.md`
- `skills/spip-mutualisation-maintenance/references/backup-rollback.md`
- `skills/spip-mutualisation-maintenance/references/security-incident.md`

## Self-review

- Removed a `SVIP` typo in favor of `SVP`.
- Confirmed no executable script or README/GREEN modification was added.
- Confirmed examples use task-specific variables rather than `HOME`, reject `/`, and do not contain credentials.
- Confirmed historical documentation is subordinate to installed/current source.

## Concerns

- Exact production command blocks necessarily remain conditional on discovered deployment provenance and paths; the skill explicitly stops instead of inventing them.
- The independent Task 2 reviewer and Task 3 behavioral tests remain required.

## Fix round 1

- Added claim-local source URLs beside the specific assertions the reviewer flagged: Mutualisation facile version/compatibility, `demarrer_site()`/`_DIR_SITE`/`_SPIP_PATH` behavior, incomplete-install and `tmp/meta_cache.php` conventions, the authenticated update route, and SQLite `.backup`/integrity-check behavior.
- Reworked `references/core-upgrade.md` so Git, Composer, archive, and `spip_loader` provenance each have an explicit proposed operator command shape with pinned placeholders, path guards, staging/isolation where applicable, and repeated “agent does not run this” framing. The Composer branch now scopes the update to `spip/core` in a staged copy rather than a generic live `composer update`.
- Reworked `references/backup-rollback.md` so read-only tool detection (`command -v` plus `--version`) happens before dump selection, then branches explicitly between `mariadb-dump` and `mysqldump`.
- Replaced the hardcoded `"$spip_root/mutualisation/paquet.xml"` path in `references/inventory-diagnosis.md` with a verified `mutualisation_path` derived from the effective include/configuration and guarded it against escaping the shared root.
