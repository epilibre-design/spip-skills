# Shared SPIP core upgrade

Read [inventory-diagnosis.md](inventory-diagnosis.md) first and [backup-rollback.md](backup-rollback.md) before preparing any mutation.

Sources: [official SPIP update guide](https://www.spip.net/fr_article1318.html), [official SPIP Git](https://git.spip.net/spip/spip), and the current [Mutualisation facile package](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/main/paquet.xml).

## Hard gate

Do not execute the upgrade. Do not even present it as ready to run until all boxes are evidenced:

- exact current and target SPIP releases verified at task time;
- Mutualisation facile's installed/current package declares the target compatible;
- shared plugin compatibility and dependencies have been inventoried;
- core provenance and local modifications are understood;
- every affected database and persistent file set has a recent verified backup;
- restoration has been tested in isolation or a concrete restore drill is scheduled before production;
- capacity and a coordinated maintenance window are sufficient.

Root access or urgency does not waive this gate.

## 1. Build the compatibility record

Record evidence, not a remembered “latest” version:

| Component | Installed | Target | Declared range | Provenance | Local changes | Decision |
|---|---:|---:|---|---|---|---|
| SPIP core | | | 4.2–4.4 skill scope | Git/Composer/archive/unknown | | |
| Mutualisation facile | | | installed `paquet.xml` | | | |
| Each shared plugin | | | installed/target `paquet.xml` | | | |

Also check target PHP requirements and the PHP version actually used by every FPM pool, not only `php -v` on the CLI.

## 2. Preserve the installed method

Never switch methods silently.

### Git-managed core

Read-only evidence:

```bash
git -C "$spip_root" status --short
git -C "$spip_root" remote -v
git -C "$spip_root" branch --show-current
git -C "$spip_root" describe --tags --always --dirty
```

Any unexplained tracked or untracked change is a blocker until classified. A later operator proposal may fetch and check out an exact reviewed tag/commit, but must not use an unpinned moving branch as the rollback identity.

### Composer-managed core

Read-only evidence:

```bash
composer --working-dir="$spip_root" show --locked
composer --working-dir="$spip_root" validate --no-check-publish
git -C "$spip_root" diff -- composer.json composer.lock
```

The proposal must change constraints deliberately, retain the previous lock file, run a dry-run where supported, and build an isolated release before switching production. Do not run `composer update` generically at the live root.

### Archive/spip_loader-managed core

SPIP's official guide recommends `spip_loader` for ordinary updates, but on a farm it modifies the shared core and therefore affects all sites. Verify the loader source and target branch, the shared root it will write, local overrides, and the farm-wide rollback first. An archive proposal must use an exact official release and verified checksum/signature when published; never overlay an unspecified “latest” archive.

### Unknown or mixed provenance

Stop. Propose only further read-only identification. Do not choose Git, Composer, or an archive on the operator's behalf.

## 3. Stage the entire farm behavior

A valid pilot uses an isolated copy of shared code plus representative site data and databases. It is not one production site still loading the same core.

Test at minimum:

- one site for every important plugin combination;
- both MariaDB/MySQL and SQLite when both exist;
- public pages, authentication/private area, forms, uploads, scheduled jobs, and URL rewriting;
- database upgrades and plugin schema upgrades;
- PHP-FPM/web-server logs and SPIP logs;
- rollback using the actual backup format.

## 4. Proposed production sequence

Label every command block **proposed — not executed by the agent**.

1. Freeze content changes or announce the exact backup consistency boundary.
2. Re-run the inventory and confirm the site list has not changed.
3. Create and verify the coherent backup set described in [backup-rollback.md](backup-rollback.md).
4. Enter a farm-wide maintenance response using the deployment's existing web-server/load-balancer mechanism. Do not invent a SPIP constant.
5. Prepare the target in a separate release directory when the deployment supports atomic switching; otherwise document the controlled exact-file replacement.
6. Switch shared code once.
7. Upgrade/validate each site database sequentially through the authenticated SPIP mechanism appropriate to that installation. The Mutualisation facile administration page can identify sites needing upgrade, but do not automate its request secrets from `tmp/meta_cache.php`.
8. After each site, run the per-site checks below. Stop the sequence on the first unexplained failure.
9. Validate the administration site and shared jobs, leave maintenance, then monitor.

Do not purge all caches before capturing errors. Cache cleanup, if required by the exact release procedure, is a proposed mutation with its own scope and happens only after useful evidence is retained.

## 5. Per-site validation ledger

| Site | Public HTTP | Private login | Schema current | Plugins active | Forms/uploads | Jobs | PHP/SPIP logs | Result |
|---|---|---|---|---|---|---|---|---|

Use explicit HTTP status/content checks and authenticated manual validation where required. A successful administration site does not prove the other databases migrated.

## 6. Rollback boundary

Record a checkpoint per site:

- **Before any schema migration:** switching back to the exact old shared release can be sufficient if no writes used the new code.
- **After a site's schema migration or writes under new code:** restore that site's matching database and persistent data as well as the old shared release.
- **Unclear migration state:** treat the site as requiring database restoration.

Do not mix an old core with a database whose schema was advanced by the target release. Use the engine-specific restore procedure and validation in [backup-rollback.md](backup-rollback.md).

## Proposed command contract

Commands supplied to the operator must use verified values such as `spip_root`, `old_release`, `target_release`, and a backup manifest. Before any switch, show read-only assertions proving that the paths are non-empty, not `/`, contained under the expected release parent, and match the recorded manifest. Never execute the command block inside the agent session.
