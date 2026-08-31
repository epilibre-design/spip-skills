# Shared SPIP core upgrade

Read [inventory-diagnosis.md](inventory-diagnosis.md) first and [backup-rollback.md](backup-rollback.md) before preparing any mutation.

Sources: [official SPIP update guide](https://www.spip.net/fr_article1318.html), [official SPIP Git](https://git.spip.net/spip/spip), and the current [Mutualisation facile package](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/paquet.xml).

## Hard gate

Do not execute the upgrade. Do not even present it as ready to run until all boxes are evidenced:

- exact current and target SPIP releases verified at task time from the installed tree and the exact upstream release/tag/archive, not a remembered “latest” ([official SPIP Git](https://git.spip.net/spip/spip));
- Mutualisation facile's installed/current package declares the target compatible; the current 2.x package advertises compatibility `[4.2.0;4.*]`, which still must be rechecked against the resolved installed copy before each plan ([current Mutualisation facile `paquet.xml`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/paquet.xml));
- shared plugin compatibility and dependencies have been inventoried;
- core provenance and local modifications are understood;
- every affected database and persistent file set has a recent verified backup;
- restoration has been tested in isolation or a concrete restore drill is scheduled before production;
- capacity and a coordinated maintenance window are sufficient.

Root access or urgency does not waive this gate.

## 1. Build the compatibility record

Record evidence, not a remembered “latest” version. SPIP's official update guidance is release-oriented, and on a mutualised farm every shared-core update route affects all sites at once ([official SPIP update guide](https://www.spip.net/fr_article1318.html)).

| Component | Installed | Target | Declared range | Provenance | Local changes | Decision |
|---|---:|---:|---|---|---|---|
| SPIP core | | | 4.2–4.4 skill scope | Git/Composer/archive/unknown | | |
| Mutualisation facile | | | installed `paquet.xml` | | | |
| Each shared plugin | | | installed/target `paquet.xml` | | | |

Also check target PHP requirements and the PHP version actually used by every FPM pool, not only `php -v` on the CLI.

## 2. Preserve the installed method

Never switch methods silently.

### Git-managed core — proposed operator command shape

Read-only evidence:

```bash
git -C "$spip_root" status --short
git -C "$spip_root" remote
git -C "$spip_root" branch --show-current
git -C "$spip_root" describe --tags --always --dirty
```

Do not return `git remote -v` or a raw remote URL to the agent: URLs may embed credentials. Verify the URL locally against the operator's allowlisted upstream and return only the remote name, scheme/hostname with userinfo/query redacted, and a trusted/untrusted verdict.

Any unexplained tracked or untracked change is a blocker until classified. A later operator proposal may fetch and check out an exact reviewed tag/commit, but must not use an unpinned moving branch as the rollback identity.

If the current provenance is Git, keep the production method Git-backed and prepare the exact target in isolation; the agent never runs this block:

```bash
# proposed — operator runs this, agent does not
spip_root=/srv/www/example-spip/current
release_parent=/srv/www/example-spip/releases
old_release=/srv/www/example-spip/releases/spip-4.3.8
target_release=/srv/www/example-spip/releases/spip-4.4.1
origin_url=verified_git_remote_url
target_ref=verified_reviewed_tag_or_commit

spip_root="$(realpath -e -- "$spip_root")"
release_parent="$(realpath -e -- "$release_parent")"
old_release="$(realpath -e -- "$old_release")"
target_parent="$(realpath -e -- "$(dirname -- "$target_release")")"
test -n "$spip_root" && test "$spip_root" != /
test -n "$release_parent" && test "$release_parent" != /
test -n "$old_release" && test "$old_release" != /
test ! -e "$target_release"
test "$target_parent" = "$release_parent"
case "$old_release" in "$release_parent"/*) ;; *) exit 1;; esac

git clone --origin origin --no-checkout "$origin_url" "$target_release"
git -C "$target_release" checkout --detach "$target_ref"
git -C "$target_release" status --short
```

### Composer-managed core — proposed operator command shape

Read-only evidence:

```bash
composer --working-dir="$spip_root" show --locked
composer --working-dir="$spip_root" validate --no-check-publish
git -C "$spip_root" diff -- composer.json composer.lock
```

The proposal must change constraints deliberately, retain the previous lock file, run a dry-run where supported, and build an isolated release before switching production. Do not run `composer update` generically at the live root.

If the current provenance is Composer, keep the update scoped and stage it away from production; the agent never runs this block:

```bash
# proposed — operator runs this, agent does not
spip_root=/srv/www/example-spip/current
build_parent=/srv/build/spip-core
prepared_release=/srv/build/spip-core/2026-08-28-spip-4.4.1
composer_bin=/usr/local/bin/composer
target_constraint=4.4.1

spip_root="$(realpath -e -- "$spip_root")"
build_parent="$(realpath -e -- "$build_parent")"
prepared_parent="$(realpath -e -- "$(dirname -- "$prepared_release")")"
test -n "$spip_root" && test "$spip_root" != /
test -n "$build_parent" && test "$build_parent" != /
test "$prepared_parent" = "$build_parent"
test -f "$spip_root/composer.json" && test -f "$spip_root/composer.lock"
test ! -e "$prepared_release"

cp -a -- "$spip_root" "$prepared_release"
"$composer_bin" --working-dir="$prepared_release" validate --no-check-publish
"$composer_bin" --working-dir="$prepared_release" require "spip/core:$target_constraint" --no-update
"$composer_bin" --working-dir="$prepared_release" update spip/core --with-all-dependencies --dry-run
"$composer_bin" --working-dir="$prepared_release" update spip/core --with-all-dependencies
```

### Archive-managed core — proposed operator command shape

SPIP's official update guide includes archive-based release replacement as an ordinary route; on a farm it still changes the shared core for every site, so the prepared artifact must be an exact reviewed release with a recorded checksum rather than an unspecified “latest” archive ([official SPIP update guide](https://www.spip.net/fr_article1318.html)).

```bash
# proposed — operator runs this, agent does not
release_parent=/srv/www/example-spip/releases
target_release=/srv/www/example-spip/releases/spip-4.4.1
target_archive=/srv/dist/spip-v4.4.1.zip
target_archive_sha256=verified_sha256

release_parent="$(realpath -e -- "$release_parent")"
target_parent="$(realpath -e -- "$(dirname -- "$target_release")")"
target_archive="$(realpath -e -- "$target_archive")"
test -n "$release_parent" && test "$release_parent" != /
test "$target_parent" = "$release_parent"
test ! -e "$target_release"
printf '%s  %s\n' "$target_archive_sha256" "$target_archive" | sha256sum -c -
mkdir -- "$target_release"
bsdtar -xf "$target_archive" -C "$target_release"
```

### spip_loader-managed core — proposed operator command shape

SPIP's official guide also keeps `spip_loader` as a normal update route, but in a mutualised farm it still writes the shared core and therefore carries farm-wide blast radius ([official SPIP update guide](https://www.spip.net/fr_article1318.html)). Keep the provenance explicit: pin the exact loader artifact and target release, verify both before maintenance, and stop if the operator cannot supply the reviewed loader procedure already used for that farm.

```bash
# proposed — operator runs this, agent does not
loader_artifact=/srv/dist/spip_loader.php
loader_sha256=verified_loader_sha256
target_release_label=4.4.1
maintenance_root=/srv/www/example-spip/current

loader_artifact="$(realpath -e -- "$loader_artifact")"
maintenance_root="$(realpath -e -- "$maintenance_root")"
test -n "$maintenance_root" && test "$maintenance_root" != /
printf '%s  %s\n' "$loader_sha256" "$loader_artifact" | sha256sum -c -
php -l "$loader_artifact"
```

Do not invent `spip_loader` flags or a live in-place sequence from memory. If the operator cannot provide the exact reviewed procedure for the current farm, stop and fall back to further provenance discovery instead of guessing.

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
7. Upgrade/validate each site database sequentially through the authenticated SPIP mechanism appropriate to that installation. `mutualiser.php` only dispatches the upgrade route when `upgrade=oui`, and `mutualiser_upgrade.php` checks a request secret derived from live metadata before running the SPIP schema upgrade and purging `_DIR_TMP`, so the agent must not automate guessed upgrade URLs or secrets from cached files such as `tmp/meta_cache.php` ([`mutualiser.php`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/mutualiser.php), [`mutualiser_upgrade.php`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/master/mutualiser_upgrade.php)).
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
