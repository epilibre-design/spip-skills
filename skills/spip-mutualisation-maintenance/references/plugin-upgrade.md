# Plugin upgrades in a mutualisation

Read [inventory-diagnosis.md](inventory-diagnosis.md) first and [backup-rollback.md](backup-rollback.md) before preparing a plugin mutation.

Sources: SPIP's [`paquet.xml` reference](https://docs.spip.net/paquet.xml.html), the installed plugin packages, and Mutualisation facile's `_SPIP_PATH` construction in [`mutualiser.php`](https://git.spip.net/spip-contrib-extensions/mutualisation/-/blob/main/mutualiser.php).

## 1. Determine whether code is shared

Do not infer scope from plugin name. Resolve the actual loaded path and precedence:

- `plugins-dist/` belongs to the shared SPIP release;
- a farm-level `plugins/` or `plugins/auto/` is shared by all sites that activate it;
- `_DIR_PLUGINS_SUPPL`, `_SPIP_PATH`, or site configuration can add other paths;
- a plugin physically isolated inside one site's path can be site-specific only after effective path resolution confirms it.

One directory such as `plugins/auto/agenda` cannot simultaneously contain two versions for a one-site production canary. Use an isolated staging copy of the farm or deliberately design and verify a site-specific plugin path. Do not replace the shared directory “briefly” for a pilot.

## 2. Build the impact matrix

| Plugin | Current → target | Loaded path | Provenance | Active sites | SPIP compatibility | Dependencies | Local changes | Schema change |
|---|---|---|---|---|---|---|---|---|

For both current and target packages, inspect `prefix`, `version`, `compatibilite`, dependencies (`necessite`/`utilise`), and schema declarations. Verify target metadata from the actual target tag/archive, not a catalogue snippet alone.

Read-only provenance checks:

```bash
plugin_path="$spip_root/plugins/auto/agenda"  # use the resolved path
test -d "$plugin_path" && test "$plugin_path" != /
git -C "$plugin_path" status --short 2>/dev/null
git -C "$plugin_path" remote -v 2>/dev/null
git -C "$plugin_path" describe --tags --always --dirty 2>/dev/null
test -f "$spip_root/composer.lock" && composer --working-dir="$spip_root" show --locked
rg -n '^(<paquet|[[:space:]]*(prefix|version|compatibilite|schema)=|[[:space:]]*<(necessite|utilise))' "$plugin_path/paquet.xml"
```

Unknown provenance, unexplained local changes, or incompatible dependency ranges are blockers.

## 3. Keep provenance stable

| Current method | Operator proposal may use | Must retain for rollback |
|---|---|---|
| Git | Exact reviewed tag/commit from the same trusted remote | old commit/tag and local-diff patch |
| SVP/archive | Exact official target archive | original archive/version/checksum and directory snapshot |
| Composer | Deliberate constraint plus reviewed lock change | previous `composer.json` and `composer.lock` |
| Unknown/mixed | Read-only investigation only | no mutation until resolved |

Do not turn an SVP/archive plugin into a Git checkout, or update every Composer dependency, merely to update one plugin.

## 4. Test outside shared production code

The staging copy must reproduce:

- the shared SPIP version and plugin search paths;
- every significant dependency/active-plugin combination;
- a representative database copy for sites that activate the plugin;
- both supported database engines when plugin behavior or schema touches them.

Test activation, public/private behavior, forms/tasks, schema upgrade, logs, and restoration. If isolation cannot be proven, the “pilot” remains farm-wide and is not a canary.

## 5. Proposed production sequence

All command blocks are **proposed — not executed by the agent**.

1. Confirm the impact matrix and affected-site list.
2. Verify the coherent backup and restore drill.
3. Enter the appropriate maintenance scope; shared dependency changes generally require farm-wide maintenance.
4. Prepare the exact target separately and compare its package/dependencies.
5. Switch the plugin code once, preserving the prior version recoverably.
6. Visit or run the authenticated upgrade mechanism sequentially for each active site.
7. Validate each active site and stop on the first unexplained activation/schema/log error.
8. Validate sites where the plugin is inactive for shared dependency regressions.
9. Leave maintenance and monitor.

Do not combine a core upgrade and an unrelated plugin upgrade. Combine them only when the compatibility graph requires a coordinated transition, and retain separate checkpoints in the manifest.

## 6. Validation and rollback

| Site | Plugin active | Target version observed | Dependencies active | Schema current | Functional checks | Logs clean | Result |
|---|---|---|---|---|---|---|---|

Before a schema migration, reverting the exact plugin code may be sufficient if no new-version writes occurred. After migration or writes, restore the site's matching database plus the previous plugin code. If a shared dependency changed, restore the whole coordinated plugin set recorded in the manifest rather than mixing versions.

## Common traps

- Assuming every directory beneath `plugins/auto/` is managed by the same mechanism.
- Reading only the new plugin's SPIP range and ignoring dependency ranges.
- Declaring success after the administration site works.
- Updating inactive plugins without checking that their shared bootstrap/dependencies cannot load.
- Deleting the old directory instead of retaining an exact recoverable version until validation completes.
